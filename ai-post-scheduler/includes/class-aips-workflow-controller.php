<?php
/**
 * Workflow Controller
 *
 * Handles the admin Workflows page rendering, admin-post form submissions,
 * and AJAX endpoints for workflow status updates.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.5
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Workflow_Controller
 *
 * Registers admin_post_* and wp_ajax_* hooks, renders the Workflows admin page,
 * and delegates all business logic to AIPS_Workflow_Service.
 */
class AIPS_Workflow_Controller {

	/**
	 * @var AIPS_Workflow_Service
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * Registers admin-post and AJAX hooks.
	 *
	 * @param AIPS_Workflow_Service|null $service Optional service instance for dependency injection.
	 */
	public function __construct(AIPS_Workflow_Service $service = null) {
		$this->service = $service ?: new AIPS_Workflow_Service();

		add_action('admin_post_aips_save_workflow',       array($this, 'handle_save_workflow'));
		add_action('admin_post_aips_delete_workflow',     array($this, 'handle_delete_workflow'));
		add_action('wp_ajax_aips_update_workflow_status', array($this, 'handle_update_workflow_status'));
	}

	/**
	 * Render the Workflows admin page.
	 */
	public function render_page() {
		$message_key  = isset($_GET['message']) ? sanitize_key($_GET['message']) : '';
		$message      = AIPS_Workflow_Service::get_message_for_key($message_key);
		$statuses     = AIPS_Workflow_Service::get_statuses();
		$workflows    = $this->service->get_all_workflows();
		$edit_workflow = $this->get_workflow_from_request();
		include AIPS_PLUGIN_DIR . 'templates/admin/workflows.php';
	}

	/**
	 * Handle saving (creating or updating) a workflow via admin-post.php.
	 */
	public function handle_save_workflow() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Permission denied.', 'ai-post-scheduler'));
		}

		check_admin_referer('aips_save_workflow');

		$workflow_id = isset($_POST['workflow_id']) ? absint($_POST['workflow_id']) : 0;
		$name        = isset($_POST['workflow_name'])        ? sanitize_text_field($_POST['workflow_name'])        : '';
		$description = isset($_POST['workflow_description']) ? sanitize_textarea_field($_POST['workflow_description']) : '';
		$status      = $this->service->sanitize_status($_POST['workflow_status'] ?? '');

		if (empty($name)) {
			$extra = $workflow_id ? array('workflow_id' => $workflow_id) : array();
			$this->redirect_with_message('workflow_name_required', $extra);
		}

		$data = array(
			'name'        => $name,
			'description' => $description,
			'status'      => $status,
		);

		if ($workflow_id) {
			if (!$this->service->get_workflow($workflow_id)) {
				$this->redirect_with_message('workflow_not_found');
			}

			$updated = $this->service->get_repo()->update($workflow_id, $data);
			if ($updated === false) {
				$this->redirect_with_message('workflow_save_failed', array('workflow_id' => $workflow_id));
			}

			$this->redirect_with_message('workflow_updated', array('workflow_id' => $workflow_id));
		}

		$inserted_id = $this->service->get_repo()->create($data);
		if ($inserted_id === false) {
			$this->redirect_with_message('workflow_save_failed');
		}

		$this->redirect_with_message('workflow_created', array('workflow_id' => $inserted_id));
	}

	/**
	 * Handle deleting a workflow via admin-post.php.
	 */
	public function handle_delete_workflow() {
		if (!current_user_can('manage_options')) {
			wp_die(__('Permission denied.', 'ai-post-scheduler'));
		}

		check_admin_referer('aips_delete_workflow');

		$workflow_id = isset($_POST['workflow_id']) ? absint($_POST['workflow_id']) : 0;
		if (!$workflow_id) {
			$this->redirect_with_message('workflow_not_found');
		}

		if (!$this->service->get_workflow($workflow_id)) {
			$this->redirect_with_message('workflow_not_found');
		}

		$deleted = $this->service->get_repo()->delete($workflow_id);
		if (!$deleted) {
			$this->redirect_with_message('workflow_delete_failed');
		}

		$this->redirect_with_message('workflow_deleted');
	}

	/**
	 * AJAX handler to update the workflow status on a history entry.
	 */
	public function handle_update_workflow_status() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
		if (!$history_id) {
			wp_send_json_error(array('message' => __('Missing history ID.', 'ai-post-scheduler')));
		}

		$status      = isset($_POST['workflow_status']) ? sanitize_key($_POST['workflow_status']) : null;
		$workflow_id = isset($_POST['workflow_id']) && $_POST['workflow_id'] !== '' ? absint($_POST['workflow_id']) : null;

		$success = $this->service->set_history_workflow($history_id, $status, $workflow_id);

		if (!$success) {
			wp_send_json_error(array('message' => __('Failed to update workflow status.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Workflow status updated.', 'ai-post-scheduler')));
	}

	/**
	 * Redirect back to the Workflows admin page with a status message.
	 *
	 * @param string $message_key
	 * @param array  $extra       Additional query args to include in the redirect URL.
	 */
	private function redirect_with_message($message_key, array $extra = array()) {
		$args = array_merge($extra, array('message' => $message_key));
		$url  = add_query_arg($args, $this->get_base_page_url());
		wp_safe_redirect($url);
		exit;
	}

	/**
	 * Return the base admin URL for the Workflows page.
	 *
	 * @return string
	 */
	private function get_base_page_url() {
		return admin_url('admin.php?page=aips-workflows');
	}

	/**
	 * Get the workflow requested via query-string (for the edit form).
	 *
	 * @return object|null
	 */
	private function get_workflow_from_request() {
		$workflow_id = isset($_GET['workflow_id']) ? absint($_GET['workflow_id']) : 0;
		if (!$workflow_id) {
			return null;
		}
		return $this->service->get_workflow($workflow_id);
	}
}
