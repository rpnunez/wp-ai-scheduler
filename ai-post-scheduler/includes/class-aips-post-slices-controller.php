<?php
/**
 * Post Slices Controller
 *
 * AJAX CRUD controller for post slices.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Slices_Controller
 */
class AIPS_Post_Slices_Controller {

	/**
	 * @var AIPS_Post_Slices_Repository
	 */
	private $repository;

	/**
	 * Initialize controller.
	 */
	public function __construct() {
		$this->repository = AIPS_Post_Slices_Repository::instance();

		add_action('wp_ajax_aips_get_post_slices', array($this, 'ajax_get_post_slices'));
		add_action('wp_ajax_aips_get_post_slice', array($this, 'ajax_get_post_slice'));
		add_action('wp_ajax_aips_save_post_slice', array($this, 'ajax_save_post_slice'));
		add_action('wp_ajax_aips_delete_post_slice', array($this, 'ajax_delete_post_slice'));
		add_action('wp_ajax_aips_toggle_post_slice_active', array($this, 'ajax_toggle_post_slice_active'));
		add_action('wp_ajax_aips_bulk_toggle_post_slices', array($this, 'ajax_bulk_toggle_post_slices'));
		add_action('wp_ajax_aips_bulk_delete_post_slices', array($this, 'ajax_bulk_delete_post_slices'));
	}

	/**
	 * @return void
	 */
	private function authorize() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	/**
	 * Get all post slices.
	 *
	 * @return void
	 */
	public function ajax_get_post_slices() {
		$this->authorize();

		AIPS_Ajax_Response::success(
			array(
				'slices' => $this->repository->get_all(false),
				'counts' => $this->repository->get_counts(),
			)
		);
	}

	/**
	 * Get one post slice.
	 *
	 * @return void
	 */
	public function ajax_get_post_slice() {
		$this->authorize();

		$id = isset($_POST['slice_id']) ? absint($_POST['slice_id']) : 0;
		if ($id < 1) {
			AIPS_Ajax_Response::error(__('Invalid post slice ID.', 'ai-post-scheduler'));
		}

		$slice = $this->repository->get_by_id($id);
		if (!$slice) {
			AIPS_Ajax_Response::error(__('Post slice not found.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array('slice' => $slice));
	}

	/**
	 * Save a post slice.
	 *
	 * @return void
	 */
	public function ajax_save_post_slice() {
		$this->authorize();

		$id = isset($_POST['slice_id']) ? absint($_POST['slice_id']) : 0;
		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
		$sort_order = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
		$is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

		if ($name === '') {
			AIPS_Ajax_Response::error(__('A slice name is required.', 'ai-post-scheduler'));
		}

		if ($this->repository->name_exists($name, $id)) {
			AIPS_Ajax_Response::error(__('A post slice with that name already exists.', 'ai-post-scheduler'));
		}

		$data = array(
			'name'        => $name,
			'description' => $description,
			'sort_order'  => $sort_order,
			'is_active'   => $is_active ? 1 : 0,
		);

		if ($id > 0) {
			if (!$this->repository->get_by_id($id)) {
				AIPS_Ajax_Response::not_found(__('Post slice', 'ai-post-scheduler'));
			}

			$result = $this->repository->update($id, $data);
			if ($result === false) {
				AIPS_Ajax_Response::error(__('Failed to update post slice.', 'ai-post-scheduler'));
			}

			$slice = $this->repository->get_by_id($id);
			AIPS_Ajax_Response::success(
				array(
					'message' => __('Post slice updated.', 'ai-post-scheduler'),
					'slice_id' => $id,
					'slice' => $slice,
				)
			);
		}

		$new_id = $this->repository->create($data);
		if (!$new_id) {
			AIPS_Ajax_Response::error(__('Failed to create post slice.', 'ai-post-scheduler'));
		}

		$slice = $this->repository->get_by_id($new_id);
		AIPS_Ajax_Response::success(
			array(
				'message' => __('Post slice created.', 'ai-post-scheduler'),
				'slice_id' => $new_id,
				'slice' => $slice,
			)
		);
	}

	/**
	 * Delete one post slice.
	 *
	 * @return void
	 */
	public function ajax_delete_post_slice() {
		$this->authorize();

		$id = isset($_POST['slice_id']) ? absint($_POST['slice_id']) : 0;
		if ($id < 1) {
			AIPS_Ajax_Response::error(__('Invalid post slice ID.', 'ai-post-scheduler'));
		}

		if (!$this->repository->get_by_id($id)) {
			AIPS_Ajax_Response::not_found(__('Post slice', 'ai-post-scheduler'));
		}

		$result = $this->repository->delete($id);
		if ($result === false || $result < 1) {
			AIPS_Ajax_Response::error(__('Failed to delete post slice.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array(), __('Post slice deleted.', 'ai-post-scheduler'));
	}

	/**
	 * Toggle active status.
	 *
	 * @return void
	 */
	public function ajax_toggle_post_slice_active() {
		$this->authorize();

		$id = isset($_POST['slice_id']) ? absint($_POST['slice_id']) : 0;
		$is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

		if ($id < 1) {
			AIPS_Ajax_Response::error(__('Invalid post slice ID.', 'ai-post-scheduler'));
		}

		if (!$this->repository->get_by_id($id)) {
			AIPS_Ajax_Response::not_found(__('Post slice', 'ai-post-scheduler'));
		}

		if ($this->repository->set_active($id, $is_active) === false) {
			AIPS_Ajax_Response::error(__('Failed to update post slice status.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array(), __('Post slice status updated.', 'ai-post-scheduler'));
	}

	/**
	 * Bulk toggle post slices.
	 *
	 * @return void
	 */
	public function ajax_bulk_toggle_post_slices() {
		$this->authorize();

		$ids = isset($_POST['slice_ids']) ? array_map('absint', (array) $_POST['slice_ids']) : array();
		$is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;
		$ids = array_values(array_filter($ids));

		if (empty($ids)) {
			AIPS_Ajax_Response::error(__('No post slices selected.', 'ai-post-scheduler'));
		}

		$updated = $this->repository->bulk_set_active($ids, $is_active);
		if ($updated === false) {
			AIPS_Ajax_Response::error(__('Failed to update post slices.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(
			array('updated' => $updated),
			$is_active ? __('Selected post slices enabled.', 'ai-post-scheduler') : __('Selected post slices disabled.', 'ai-post-scheduler')
		);
	}

	/**
	 * Bulk delete post slices.
	 *
	 * @return void
	 */
	public function ajax_bulk_delete_post_slices() {
		$this->authorize();

		$ids = isset($_POST['slice_ids']) ? array_map('absint', (array) $_POST['slice_ids']) : array();
		$ids = array_values(array_filter($ids));

		if (empty($ids)) {
			AIPS_Ajax_Response::error(__('No post slices selected.', 'ai-post-scheduler'));
		}

		$deleted = $this->repository->bulk_delete($ids);
		if ($deleted === false) {
			AIPS_Ajax_Response::error(__('Failed to delete post slices.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(
			array('deleted' => $deleted),
			__('Selected post slices deleted.', 'ai-post-scheduler')
		);
	}
}
