<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_History_Controller
 *
 * Handles HTTP requests (AJAX) and View rendering for the History domain.
 * Separated from AIPS_History (Service) to adhere to Single Responsibility Principle.
 *
 * @since 1.7.0
 */
class AIPS_History_Controller {

    /**
     * @var AIPS_History_Repository Repository for database operations
     */
    private $repository;

    public function __construct() {
        $this->repository = new AIPS_History_Repository();

        add_action('wp_ajax_aips_clear_history', array($this, 'ajax_clear_history'));
        add_action('wp_ajax_aips_retry_generation', array($this, 'ajax_retry_generation'));
        add_action('wp_ajax_aips_get_history_details', array($this, 'ajax_get_history_details'));
        add_action('wp_ajax_aips_bulk_delete_history', array($this, 'ajax_bulk_delete_history'));
    }

    /**
     * Handle AJAX request to clear history.
     */
    public function ajax_clear_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        // Use repository directly
        $this->repository->delete_by_status($status);

        wp_send_json_success(array('message' => __('History cleared successfully.', 'ai-post-scheduler')));
    }

    /**
     * Handle AJAX request to bulk delete history items.
     */
    public function ajax_bulk_delete_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $ids = isset($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();

        if (empty($ids)) {
            wp_send_json_error(array('message' => __('No items selected.', 'ai-post-scheduler')));
        }

        $result = $this->repository->delete_bulk($ids);

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete items.', 'ai-post-scheduler')));
        }

        wp_send_json_success(array('message' => __('Selected items deleted successfully.', 'ai-post-scheduler')));
    }

    /**
     * Handle AJAX request to retry post generation.
     */
    public function ajax_retry_generation() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

        if (!$history_id) {
            wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
        }

        $history_item = $this->repository->get_by_id($history_id);

        if (!$history_item || !$history_item->template_id) {
            wp_send_json_error(array('message' => __('History item not found or no template associated.', 'ai-post-scheduler')));
        }

        $templates = new AIPS_Templates();
        $template = $templates->get($history_item->template_id);

        if (!$template) {
            wp_send_json_error(array('message' => __('Template no longer exists.', 'ai-post-scheduler')));
        }

        $generator = new AIPS_Generator();
        $result = $generator->generate_post($template);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Post regenerated successfully!', 'ai-post-scheduler'),
            'post_id' => $result
        ));
    }

    /**
     * Handle AJAX request to get history details.
     */
    public function ajax_get_history_details() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

        if (!$history_id) {
            wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
        }

        $history_item = $this->repository->get_by_id($history_id);

        if (!$history_item) {
            wp_send_json_error(array('message' => __('History item not found.', 'ai-post-scheduler')));
        }

        $generation_log = array();
        if (!empty($history_item->generation_log)) {
            $generation_log = json_decode($history_item->generation_log, true);
        }

        $response = array(
            'id' => $history_item->id,
            'status' => $history_item->status,
            'created_at' => $history_item->created_at,
            'completed_at' => $history_item->completed_at,
            'generated_title' => $history_item->generated_title,
            'post_id' => $history_item->post_id,
            'error_message' => $history_item->error_message,
            'generation_log' => $generation_log,
        );

        wp_send_json_success($response);
    }

    /**
     * Render the History admin page.
     */
    public function render_page() {
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        $history = $this->repository->get_history(array(
            'page' => $current_page,
            'status' => $status_filter,
            'search' => $search_query,
        ));

        $stats = $this->repository->get_stats();

        include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
    }
}
