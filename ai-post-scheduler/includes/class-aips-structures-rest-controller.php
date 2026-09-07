<?php
/**
 * REST controller for article structures.
 *
 * Routes:
 *   GET    /aips/v1/structures
 *   GET    /aips/v1/structures/{id}
 *   POST   /aips/v1/structures
 *   PUT    /aips/v1/structures/{id}
 *   PATCH  /aips/v1/structures/{id}          Partial update (used for is_active toggle)
 *   DELETE /aips/v1/structures/{id}
 *
 * Canonical replacement for wp_ajax_aips_(get|save|delete|toggle)_structure(s).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Structures_Rest_Controller extends AIPS_Rest_Controller {

	/** @var AIPS_Article_Structure_Repository */
	private $repository;

	/** @var AIPS_Article_Structure_Manager */
	private $manager;

	protected $rest_base = 'structures';

	public function __construct() {
		parent::__construct();
		$this->repository = new AIPS_Article_Structure_Repository();
		$this->manager    = new AIPS_Article_Structure_Manager();
	}

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_items'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array(
					'active_only' => array(
						'description' => __('Only return active structures.', 'ai-post-scheduler'),
						'type'        => 'boolean',
						'default'     => false,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->structure_args(true),
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
				'methods'             => WP_REST_Server::EDITABLE, // POST | PUT | PATCH
				'callback'            => array($this, 'update_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array_merge($this->id_arg(), $this->structure_args(false)),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));
	}

	public function get_items($request) {
		return $this->respond(array(
			'structures' => $this->repository->get_all((bool) $request->get_param('active_only')),
		));
	}

	public function get_item($request) {
		$structure = $this->repository->get_by_id((int) $request['id']);
		if (!$structure) {
			return $this->error_not_found(__('Structure', 'ai-post-scheduler'));
		}
		return $this->respond(array('structure' => $structure));
	}

	public function create_item($request) {
		$new_id = $this->manager->create_structure(
			(string) $request->get_param('name'),
			$this->sanitize_sections($request->get_param('sections')),
			(string) $request->get_param('prompt_template'),
			(string) $request->get_param('description'),
			(bool) $request->get_param('is_active')
		);
		if (is_wp_error($new_id)) {
			return $this->error('aips_invalid_request', $new_id->get_error_message(), 400);
		}
		return $this->respond_created(array(
			'structure_id' => (int) $new_id,
			'structure'    => $this->repository->get_by_id($new_id),
			'message'      => __('Structure created.', 'ai-post-scheduler'),
		));
	}

	public function update_item($request) {
		$id = (int) $request['id'];
		if (!$this->repository->get_by_id($id)) {
			return $this->error_not_found(__('Structure', 'ai-post-scheduler'));
		}

		// PATCH: partial update — currently supports is_active toggle only.
		if ('PATCH' === $request->get_method()) {
			$is_active = $request->get_param('is_active');
			if (null === $is_active) {
				return $this->error_invalid_request(__('No fields to update.', 'ai-post-scheduler'));
			}
			if (!$this->repository->set_active($id, $is_active ? 1 : 0)) {
				return $this->error_server(__('Failed to update active status.', 'ai-post-scheduler'));
			}
			return $this->respond(array(
				'structure_id' => $id,
				'structure'    => $this->repository->get_by_id($id),
				'message'      => __('Structure status updated.', 'ai-post-scheduler'),
			));
		}

		$result = $this->manager->update_structure(
			$id,
			(string) $request->get_param('name'),
			$this->sanitize_sections($request->get_param('sections')),
			(string) $request->get_param('prompt_template'),
			(string) $request->get_param('description'),
			(bool) $request->get_param('is_active')
		);
		if (is_wp_error($result)) {
			return $this->error('aips_invalid_request', $result->get_error_message(), 400);
		}
		return $this->respond(array(
			'structure_id' => $id,
			'structure'    => $this->repository->get_by_id($id),
			'message'      => __('Structure updated.', 'ai-post-scheduler'),
		));
	}

	public function delete_item($request) {
		$id = (int) $request['id'];
		if (!$this->repository->get_by_id($id)) {
			return $this->error_not_found(__('Structure', 'ai-post-scheduler'));
		}
		$result = $this->manager->delete_structure($id);
		if (is_wp_error($result)) {
			return $this->error('aips_conflict', $result->get_error_message(), 409);
		}
		return $this->respond(array('message' => __('Structure deleted.', 'ai-post-scheduler')));
	}

	private function sanitize_sections($sections) {
		if (!is_array($sections)) {
			return array();
		}
		return AIPS_Utilities::sanitize_string_array($sections);
	}

	private function structure_args($required) {
		return array(
			'name' => array(
				'type'              => 'string',
				'required'          => (bool) $required,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'sections' => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array('type' => 'string'),
			),
			'prompt_template' => array(
				'type'              => 'string',
				'required'          => (bool) $required,
				'sanitize_callback' => 'wp_kses_post',
			),
			'is_active' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}
}
