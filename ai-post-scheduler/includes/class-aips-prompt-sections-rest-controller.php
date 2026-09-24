<?php
/**
 * REST controller for prompt sections.
 *
 * Routes:
 *   GET    /aips/v1/prompt-sections
 *   GET    /aips/v1/prompt-sections/{id}
 *   POST   /aips/v1/prompt-sections
 *   PUT    /aips/v1/prompt-sections/{id}
 *   PATCH  /aips/v1/prompt-sections/{id}    Partial update (is_active toggle)
 *   DELETE /aips/v1/prompt-sections/{id}
 *
 * Canonical replacement for wp_ajax_aips_(get|save|delete|toggle)_prompt_section(s).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Prompt_Sections_Rest_Controller extends AIPS_Rest_Controller {

	/** @var AIPS_Prompt_Section_Repository */
	private $repository;

	protected $rest_base = 'prompt-sections';

	public function __construct() {
		parent::__construct();
		$this->repository = new AIPS_Prompt_Section_Repository();
	}

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_items'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array(
					'active_only' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->section_args(true),
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
				'args'                => array_merge($this->id_arg(), $this->section_args(false)),
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
			'sections' => $this->repository->get_all((bool) $request->get_param('active_only')),
		));
	}

	public function get_item($request) {
		$section = $this->repository->get_by_id((int) $request['id']);
		if (!$section) {
			return $this->error_not_found(__('Section', 'ai-post-scheduler'));
		}
		return $this->respond(array('section' => $section));
	}

	public function create_item($request) {
		$data = $this->collect_input($request, true);

		if ($this->repository->key_exists($data['section_key'], 0)) {
			return $this->error_conflict(__('Section key already exists.', 'ai-post-scheduler'));
		}

		$new_id = $this->repository->create($data);
		if (!$new_id) {
			return $this->error_server(__('Failed to create prompt section.', 'ai-post-scheduler'));
		}

		return $this->respond_created(array(
			'section_id' => (int) $new_id,
			'section'    => $this->repository->get_by_id($new_id),
			'message'    => __('Section created.', 'ai-post-scheduler'),
		));
	}

	public function update_item($request) {
		$id = (int) $request['id'];
		if (!$this->repository->get_by_id($id)) {
			return $this->error_not_found(__('Section', 'ai-post-scheduler'));
		}

		// PATCH: partial update — is_active toggle only.
		if ('PATCH' === $request->get_method()) {
			$is_active = $request->get_param('is_active');
			if (null === $is_active) {
				return $this->error_invalid_request(__('No fields to update.', 'ai-post-scheduler'));
			}
			if (!$this->repository->set_active($id, $is_active ? 1 : 0)) {
				return $this->error_server(__('Failed to update active status.', 'ai-post-scheduler'));
			}
			return $this->respond(array(
				'section_id' => $id,
				'section'    => $this->repository->get_by_id($id),
				'message'    => __('Section status updated.', 'ai-post-scheduler'),
			));
		}

		$data = $this->collect_input($request, false);

		if (isset($data['section_key']) && $this->repository->key_exists($data['section_key'], $id)) {
			return $this->error_conflict(__('Section key already exists.', 'ai-post-scheduler'));
		}

		if (!$this->repository->update($id, $data)) {
			return $this->error_server(__('Failed to update prompt section.', 'ai-post-scheduler'));
		}

		return $this->respond(array(
			'section_id' => $id,
			'section'    => $this->repository->get_by_id($id),
			'message'    => __('Section updated.', 'ai-post-scheduler'),
		));
	}

	public function delete_item($request) {
		$id = (int) $request['id'];
		if (!$this->repository->get_by_id($id)) {
			return $this->error_not_found(__('Section', 'ai-post-scheduler'));
		}
		if (!$this->repository->delete($id)) {
			return $this->error_server(__('Failed to delete prompt section.', 'ai-post-scheduler'));
		}
		return $this->respond(array('message' => __('Section deleted.', 'ai-post-scheduler')));
	}

	private function collect_input($request, $require_all) {
		$fields = array('name', 'description', 'section_key', 'content', 'is_active');
		$data   = array();
		foreach ($fields as $field) {
			$value = $request->get_param($field);
			if (null === $value && !$require_all) {
				continue;
			}
			$data[$field] = 'is_active' === $field ? ($value ? 1 : 0) : $value;
		}
		return $data;
	}

	private function section_args($required) {
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
			'section_key' => array(
				'type'              => 'string',
				'required'          => (bool) $required,
				'sanitize_callback' => 'sanitize_key',
			),
			'content' => array(
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
