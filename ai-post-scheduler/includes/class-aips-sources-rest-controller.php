<?php
/**
 * REST controller for trusted sources.
 *
 * Routes:
 *   GET    /aips/v1/sources                     List sources with term ids attached
 *   GET    /aips/v1/sources/{id}                Fetch one source
 *   POST   /aips/v1/sources                     Create a source
 *   PUT    /aips/v1/sources/{id}                Update a source
 *   PATCH  /aips/v1/sources/{id}                Partial update (is_active toggle)
 *   DELETE /aips/v1/sources/{id}                Delete a source and its fetched data
 *
 * `fetch_source_now` stays on admin-ajax (long-running fetch action, per the
 * migration keep-list).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Sources_Rest_Controller extends AIPS_Rest_Controller {

	/** @var AIPS_Sources_Repository */
	private $repo;

	/** @var AIPS_Sources_Data_Repository */
	private $data_repo;

	protected $rest_base = 'sources';

	public function __construct() {
		parent::__construct();
		$this->repo      = new AIPS_Sources_Repository();
		$this->data_repo = new AIPS_Sources_Data_Repository();
	}

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_items'),
				'permission_callback' => array($this, 'permission_check'),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->source_args(true),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array($this, 'update_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array_merge($this->id_arg(), $this->source_args(false)),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));
	}

	public function get_items() {
		$sources    = $this->repo->get_all(false);
		$source_ids = array();
		foreach ($sources as $source) {
			if (isset($source->id) && (int) $source->id > 0) {
				$source_ids[] = (int) $source->id;
			}
		}
		$term_ids_map = array();
		if (!empty($source_ids)) {
			$fetched = $this->repo->get_term_ids_for_sources(array_values(array_unique($source_ids)));
			if (is_array($fetched)) {
				$term_ids_map = $fetched;
			}
		}
		foreach ($sources as $source) {
			$sid = isset($source->id) ? (int) $source->id : 0;
			$source->term_ids = ($sid > 0 && isset($term_ids_map[$sid]) && is_array($term_ids_map[$sid]))
				? $term_ids_map[$sid]
				: array();
		}
		return $this->respond(array('sources' => $sources));
	}

	public function get_item($request) {
		$source = $this->repo->get_by_id((int) $request['id']);
		if (!$source) {
			return $this->error_not_found(__('Source', 'ai-post-scheduler'));
		}
		$source->term_ids = $this->repo->get_source_term_ids((int) $request['id']);
		return $this->respond(array('source' => $source));
	}

	public function create_item($request) {
		$url      = $request->get_param('url');
		$interval = (string) $request->get_param('fetch_interval');
		$term_ids = array_map('absint', (array) $request->get_param('term_ids'));

		if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
			return $this->error_invalid_request(__('Please enter a valid URL (e.g. https://example.com).', 'ai-post-scheduler'));
		}
		if ($this->repo->url_exists($url)) {
			return $this->error_conflict(__('This URL is already in the sources list.', 'ai-post-scheduler'));
		}
		if (!empty($interval) && !AIPS_Interval_Calculator::instance()->is_valid_frequency($interval)) {
			return $this->error_invalid_request(__('Invalid fetch interval. Please choose a valid option.', 'ai-post-scheduler'));
		}

		$new_id = $this->repo->create(array(
			'url'         => $url,
			'label'       => (string) $request->get_param('label'),
			'description' => (string) $request->get_param('description'),
			'is_active'   => $request->get_param('is_active') ? 1 : 0,
		));
		if (!$new_id) {
			return $this->error_server(__('Failed to create source.', 'ai-post-scheduler'));
		}

		if ($interval && false === $this->repo->set_fetch_schedule($new_id, $interval)) {
			return $this->error_server(__('Source was created, but scheduling auto-fetch failed. Please try again.', 'ai-post-scheduler'));
		}
		$this->repo->set_source_terms($new_id, $term_ids);

		$source           = $this->repo->get_by_id($new_id);
		$source->term_ids = $this->repo->get_source_term_ids($new_id);

		return $this->respond_created(array(
			'source_id' => (int) $new_id,
			'source'    => $source,
			'message'   => __('Source added.', 'ai-post-scheduler'),
		));
	}

	public function update_item($request) {
		$id = (int) $request['id'];
		if (!$this->repo->get_by_id($id)) {
			return $this->error_not_found(__('Source', 'ai-post-scheduler'));
		}

		// PATCH: partial is_active toggle.
		if ('PATCH' === $request->get_method()) {
			$is_active = $request->get_param('is_active');
			if (null === $is_active) {
				return $this->error_invalid_request(__('No fields to update.', 'ai-post-scheduler'));
			}
			if (!$this->repo->set_active($id, $is_active ? 1 : 0)) {
				return $this->error_server(__('Failed to update source status.', 'ai-post-scheduler'));
			}
			return $this->respond(array(
				'source_id' => $id,
				'source'    => $this->repo->get_by_id($id),
				'message'   => __('Source status updated.', 'ai-post-scheduler'),
			));
		}

		$url      = $request->get_param('url');
		$interval = (string) $request->get_param('fetch_interval');
		$term_ids = array_map('absint', (array) $request->get_param('term_ids'));

		if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
			return $this->error_invalid_request(__('Please enter a valid URL (e.g. https://example.com).', 'ai-post-scheduler'));
		}
		if ($this->repo->url_exists($url, $id)) {
			return $this->error_conflict(__('This URL already exists as another source.', 'ai-post-scheduler'));
		}
		if (!empty($interval) && !AIPS_Interval_Calculator::instance()->is_valid_frequency($interval)) {
			return $this->error_invalid_request(__('Invalid fetch interval. Please choose a valid option.', 'ai-post-scheduler'));
		}

		if (!$this->repo->update($id, array(
			'url'         => $url,
			'label'       => (string) $request->get_param('label'),
			'description' => (string) $request->get_param('description'),
			'is_active'   => $request->get_param('is_active') ? 1 : 0,
		))) {
			return $this->error_server(__('Failed to update source.', 'ai-post-scheduler'));
		}

		if ($interval) {
			if (false === $this->repo->set_fetch_schedule($id, $interval)) {
				return $this->error_server(__('Source was updated, but saving the auto-fetch schedule failed. Please try again.', 'ai-post-scheduler'));
			}
		} else {
			$this->repo->set_fetch_schedule($id, null);
		}
		$this->repo->set_source_terms($id, $term_ids);

		$source           = $this->repo->get_by_id($id);
		$source->term_ids = $this->repo->get_source_term_ids($id);

		return $this->respond(array(
			'source_id' => $id,
			'source'    => $source,
			'message'   => __('Source updated.', 'ai-post-scheduler'),
		));
	}

	public function delete_item($request) {
		$id = (int) $request['id'];
		if (!$this->repo->get_by_id($id)) {
			return $this->error_not_found(__('Source', 'ai-post-scheduler'));
		}

		$this->repo->delete_source_terms($id);
		$this->data_repo->delete_by_source_id($id);

		if (!$this->repo->delete($id)) {
			return $this->error_server(__('Failed to delete source.', 'ai-post-scheduler'));
		}
		return $this->respond(array('message' => __('Source deleted.', 'ai-post-scheduler')));
	}

	private function source_args($required) {
		return array(
			'url' => array(
				'type'              => 'string',
				'format'            => 'uri',
				'required'          => (bool) $required,
				'sanitize_callback' => 'esc_url_raw',
			),
			'label'          => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
			'description'    => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field'),
			'is_active'      => array('type' => 'boolean', 'default' => false),
			'fetch_interval' => array('type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'),
			'term_ids'       => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array('type' => 'integer'),
			),
		);
	}
}
