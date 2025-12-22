<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_History
 *
 * Handles history UI and AJAX operations.
 * Uses AIPS_History_Repository for database operations.
 *
 * @package AI_Post_Scheduler
 * @since 1.0.0
 */
class AIPS_History {
    
    /**
     * @var AIPS_History_Repository History repository instance
     */
    private $repository;
    
    /**
     * Initialize history handler.
     */
    public function __construct() {
        $this->repository = new AIPS_History_Repository();
        
        add_action('wp_ajax_aips_clear_history', array($this, 'ajax_clear_history'));
        add_action('wp_ajax_aips_retry_generation', array($this, 'ajax_retry_generation'));
        add_action('wp_ajax_aips_get_history_details', array($this, 'ajax_get_history_details'));
    }
    
    /**
     * Get paginated history with filtering.
     *
     * @param array $args Query arguments.
     * @return array Array containing items, total, pages, and current_page.
     */
    public function get_history($args = array()) {
        return $this->repository->get_history($args);
    }
    
    /**
     * Get history statistics.
     *
     * @return array Statistics array.
     */
    public function get_stats() {
        return $this->repository->get_stats();
    }

    /**
     * Get statistics for a specific template.
     *
     * @param int $template_id Template ID.
     * @return int Number of completed posts for the template.
     */
    public function get_template_stats($template_id) {
        return $this->repository->get_template_stats($template_id);
    }
    
    /**
     * Clear history records.
     *
     * @param string $status Optional. Status to filter by (empty for all).
     * @return int|bool Number of records deleted or result of truncate.
     */
    public function clear_history($status = '') {
        return $this->repository->clear_history($status);
    }
    
    /**
     * AJAX handler for clearing history.
     */
    public function ajax_clear_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        $this->clear_history($status);
        
        wp_send_json_success(array('message' => __('History cleared successfully.', 'ai-post-scheduler')));
    }
    
    /**
     * AJAX handler for retrying a failed generation.
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
        
        $history_item = $this->repository->find($history_id);
        
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
     * AJAX handler for getting history details.
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
        
        $history_item = $this->repository->find($history_id);
        
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
     * Render the history page.
     */
    public function render_page() {
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $history = $this->get_history(array(
            'page' => $current_page,
            'status' => $status_filter,
            'search' => $search_query,
        ));
        
        $stats = $this->get_stats();
        
        include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
    }
}
