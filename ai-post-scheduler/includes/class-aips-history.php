<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_History {
    
    private $table_name;
    
    /**
     * @var AIPS_History_Repository Repository for database operations
     */
    private $repository;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_history';
        $this->repository = new AIPS_History_Repository();
        
        add_action('wp_ajax_aips_clear_history', array($this, 'ajax_clear_history'));
        add_action('wp_ajax_aips_retry_generation', array($this, 'ajax_retry_generation'));
        add_action('wp_ajax_aips_get_history_details', array($this, 'ajax_get_history_details'));
        add_action('wp_ajax_aips_bulk_delete_history', array($this, 'ajax_bulk_delete_history'));
        add_action('wp_ajax_aips_reload_history', array($this, 'ajax_reload_history'));
        add_action('wp_ajax_aips_export_history', array($this, 'ajax_export_history'));
    }
    
    public function get_history($args = array()) {
        return $this->repository->get_history($args);
    }

    /**
     * Generate pagination HTML for history table.
     *
     * @param array  $history       History data array.
     * @param string $base_url      Base URL for pagination links.
     * @param string $status_filter Current status filter.
     * @return string HTML for pagination.
     */
    public function generate_pagination_html($history, $base_url, $status_filter = '') {
        if ($history['pages'] <= 1) {
            return '';
        }

        $url = $base_url;
        if ($status_filter) {
            $url = add_query_arg('status', $status_filter, $url);
        }

        ob_start();
        include AIPS_PLUGIN_DIR . 'templates/partials/history-pagination.php';
        return ob_get_clean();
    }
    
    public function get_stats() {
        return $this->repository->get_stats();
    }

    public function get_template_stats($template_id) {
        return $this->repository->get_template_stats($template_id);
    }

    public function get_all_template_stats() {
        return $this->repository->get_all_template_stats();
    }
    
    public function clear_history($status = '') {
        return $this->repository->delete_by_status($status);
    }
    
    public function ajax_clear_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        $this->clear_history($status);
        
        wp_send_json_success(array('message' => __('History cleared successfully.', 'ai-post-scheduler')));
    }

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
            'generated_content' => $history_item->generated_content,
            'prompt' => $history_item->prompt,
            'post_id' => $history_item->post_id,
            'error_message' => $history_item->error_message,
            'generation_log' => $generation_log,
        );
        
        wp_send_json_success($response);
    }

    /**
     * AJAX handler to reload the history table and updated stats.
     *
     * Returns the latest items HTML (table body only), pagination HTML, and stats
     * so the client can refresh the view without a full page reload.
     */
    public function ajax_reload_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
        }

        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;

        $history = $this->get_history(array(
            'page' => $paged,
            'status' => $status_filter,
            'search' => $search_query,
            'fields' => 'list',
        ));

        $stats = $this->get_stats();

        ob_start();
        if (!empty($history['items'])) {
            foreach ($history['items'] as $item) {
                include AIPS_PLUGIN_DIR . 'templates/partials/history-row.php';
            }
        }
        $items_html = ob_get_clean();

        ob_start();
        $this->render_pagination_html($history, $status_filter, $search_query);
        $pagination_html = ob_get_clean();

        wp_send_json_success(array(
            'items_html' => $items_html,
            'pagination_html' => $pagination_html,
            'paged' => $paged,
            'stats' => array(
                'total' => (int) $stats['total'],
                'completed' => (int) $stats['completed'],
                'failed' => (int) $stats['failed'],
                'success_rate' => (float) $stats['success_rate'],
            ),
        ));
    }

    /**
     * Render pagination HTML for history table (used by template and AJAX).
     *
     * @param array  $history       History result with total, pages, current_page.
     * @param string $status_filter Status filter value.
     * @param string $search_query  Search query.
     */
    public function render_pagination_html($history, $status_filter = '', $search_query = '') {
        include AIPS_PLUGIN_DIR . 'templates/partials/history-pagination.php';
    }
    
    /**
     * Sanitize a CSV cell value to prevent formula injection.
     * 
     * Prevents CSV injection by prefixing cells that start with special characters
     * that could be interpreted as formulas (=, +, -, @, tab, carriage return).
     * 
     * @param string $value The value to sanitize.
     * @return string The sanitized value.
     */
    private function sanitize_csv_cell($value) {
        if (empty($value)) {
            return $value;
        }
        
        // Convert to string if not already
        $value = (string) $value;
        
        // Check if value starts with dangerous characters
        $first_char = substr($value, 0, 1);
        if (in_array($first_char, array('=', '+', '-', '@', "\t", "\r"), true)) {
            // Prefix with a single quote to neutralize the formula
            return "'" . $value;
        }
        
        return $value;
    }

    public function ajax_export_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'ai-post-scheduler'));
        }

        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        // Get max records limit from configuration
        $config = AIPS_Config::get_instance();
        $max_records = (int) $config->get_option('history_export_max_records', 10000);

        // Fetch all matching records
        $history = $this->get_history(array(
            'page' => 1,
            'per_page' => $max_records,
            'status' => $status_filter,
            'search' => $search_query,
        ));

        $filename = 'aips-history-export-' . date('Y-m-d-H-i-s') . '.csv';
        $filename = sanitize_file_name($filename);

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }

        $output = fopen('php://output', 'w');

        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Headers
        fputcsv($output, array(
            'ID',
            'Date',
            'Title',
            'Status',
            'Template',
            'Post ID',
            'Error Message'
        ));

        if (!empty($history['items'])) {
            foreach ($history['items'] as $item) {
                fputcsv($output, array(
                    $item->id,
                    $item->created_at,
                    $this->sanitize_csv_cell($item->generated_title),
                    $item->status,
                    $this->sanitize_csv_cell($item->template_name),
                    $item->post_id,
                    $this->sanitize_csv_cell($item->error_message)
                ));
            }
        }

        fclose($output);
        exit;
    }

    public function render_page() {
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $history = $this->get_history(array(
            'page' => $current_page,
            'status' => $status_filter,
            'search' => $search_query,
            'fields' => 'list',
        ));
        
        $stats = $this->get_stats();
        
        // Pass handler to template for helper methods
        $history_handler = $this;

        include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
    }
}
