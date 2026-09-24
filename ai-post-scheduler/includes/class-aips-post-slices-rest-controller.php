<?php
/**
 * REST controller for post slices.
 *
 * Routes:
 *   GET    /aips/v1/post-slices
 *   GET    /aips/v1/post-slices/{id}
 *   POST   /aips/v1/post-slices
 *   PUT    /aips/v1/post-slices/{id}
 *   PATCH  /aips/v1/post-slices/{id}           Partial update (is_active toggle)
 *   DELETE /aips/v1/post-slices/{id}
 *   POST   /aips/v1/post-slices/bulk-toggle    { ids: [], is_active: bool }
 *   POST   /aips/v1/post-slices/bulk-delete    { ids: [] }
 *
 * Canonical replacement for wp_ajax_aips_(get|save|delete|toggle|bulk_*)_post_slice(s).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Slices_Rest_Controller extends AIPS_Rest_Controller {

	/** @var AIPS_Post_Slices_Repository */
	private $repository;

	protected $rest_base = 'post-slices';

	public function __construct() {
		parent::__construct();
		$this->repository = AIPS_Post_Slices_Repository::instance();
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
				'args'                => $this->slice_args(true),
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
				'args'                => array_merge($this->id_arg(), $this->slice_args(false)),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/bulk-toggle', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'bulk_toggle'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => array_merge($this->ids_arg(), array(
				'is_active' => array(
					'type'     => 'boolean',
					'required' => true,
				),
			)),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/bulk-delete', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'bulk_delete'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => $this->ids_arg(),
		));
	}

	public function get_items($request) {
		return $this->respond(array(
			'slices' => $this->repository->get_all((bool) $request->get_param('active_only')),
			'counts' => $this->repository->get_counts(),
		));
	}

	public function get_item($request) {
		$slice = $this->repository->get_by_id((int) $request['id']);
		if (!$slice) {
			return $this->error_not_found(__('Post slice', 'ai-post-scheduler'));
		}
		return $this->respond(array('slice' => $slice));
	}

	public function create_item($request) {
		$data = $this->collect_input($request, true);

		if ($this->repository->name_exists($data['name'], 0)) {
			return $this->error_conflict(__('A post slice with that name already exists.', 'ai-post-scheduler'));
		}

		$new_id = $this->repository->create($data);
		if (!$new_id) {
			return $this->error_server(__('Failed to create post slice.', 'ai-post-scheduler'));
		}

		return $this->respond_created(array(
			'slice_id' => (int) $new_id,
			'slice'    => $this->repository->get_by_id($new_id),
			'message'  => __('Post slice created.', 'ai-post-scheduler'),
		));
	}

	public function update_item($request) {
		$id = (int) $request['id'];
		if (!$this->repository->get_by_id($id)) {
			return $this->error_not_found(__('Post slice', 'ai-post-scheduler'));
		}

		// PATCH: partial update — is_active toggle only.
		if ('PATCH' === $request->get_method()) {
			$is_active = $request->get_param('is_active');
			if (null === $is_active) {
				return $this->error_invalid_request(__('No fields to update.', 'ai-post-scheduler'));
			}
			if ($this->repository->set_active($id, $is_active ? 1 : 0) === false) {
				return $this->error_server(__('Failed to update post slice status.', 'ai-post-scheduler'));
			}
			return $this->respond(array(
				'slice_id' => $id,
				'slice'    => $this->repository->get_by_id($id),
				'message'  => __('Post slice status updated.', 'ai-post-scheduler'),
			));
		}

		$data = $this->collect_input($request, false);

		if (isset($data['name']) && $this->repository->name_exists($data['name'], $id)) {
			return $this->error_conflict(__('A post slice with that name already exists.', 'ai-post-scheduler'));
		}

		if ($this->repository->update($id, $data) === false) {
			return $this->error_server(__('Failed to update post slice.', 'ai-post-scheduler'));
		}

		return $this->respond(array(
			'slice_id' => $id,
			'slice'    => $this->repository->get_by_id($id),
			'message'  => __('Post slice updated.', 'ai-post-scheduler'),
		));
	}

	public function delete_item($request) {
		$id = (int) $request['id'];
		if (!$this->repository->get_by_id($id)) {
			return $this->error_not_found(__('Post slice', 'ai-post-scheduler'));
		}
		$result = $this->repository->delete($id);
		if ($result === false || $result < 1) {
			return $this->error_server(__('Failed to delete post slice.', 'ai-post-scheduler'));
		}
		return $this->respond(array('message' => __('Post slice deleted.', 'ai-post-scheduler')));
	}

	public function bulk_toggle($request) {
		$ids       = (array) $request->get_param('ids');
		$is_active = $request->get_param('is_active') ? 1 : 0;

		$updated = $this->repository->bulk_set_active($ids, $is_active);
		if ($updated === false) {
			return $this->error_server(__('Failed to update post slices.', 'ai-post-scheduler'));
		}

		return $this->respond(array(
			'updated' => (int) $updated,
			'message' => $is_active
				? __('Selected post slices enabled.', 'ai-post-scheduler')
				: __('Selected post slices disabled.', 'ai-post-scheduler'),
		));
	}

	public function bulk_delete($request) {
		$deleted = $this->repository->bulk_delete((array) $request->get_param('ids'));
		if ($deleted === false) {
			return $this->error_server(__('Failed to delete post slices.', 'ai-post-scheduler'));
		}
		return $this->respond(array(
			'deleted' => (int) $deleted,
			'message' => __('Selected post slices deleted.', 'ai-post-scheduler'),
		));
	}

	private function collect_input($request, $require_all) {
		$fields = array('name', 'description', 'sort_order', 'is_active');
		$data   = array();
		foreach ($fields as $field) {
			$value = $request->get_param($field);
			if (null === $value && !$require_all) {
				continue;
			}
			if ('is_active' === $field) {
				$data[$field] = $value ? 1 : 0;
			} elseif ('sort_order' === $field) {
				$data[$field] = (int) $value;
			} else {
				$data[$field] = $value;
			}
		}
		return $data;
	}

	private function slice_args($required) {
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
			'sort_order' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'is_active' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}
}
