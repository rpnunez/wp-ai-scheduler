<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles history management for AI post generation runs.
 *
 * Registers history-related AJAX endpoints and coordinates history
 * retrieval, export, stats, and admin page rendering.
 */
class AIPS_History {
    
    /**
     * @var AIPS_History_Repository Repository for database operations
     */
    private $repository;

    /**
     * Initialize history handler dependencies and AJAX hooks.
     *
     * @return void
     */
    public function __construct() {
        $this->repository = new AIPS_History_Repository();
        
        add_action('wp_ajax_aips_bulk_delete_history', array($this, 'ajax_bulk_delete_history'));
        add_action('wp_ajax_aips_clear_history', array($this, 'ajax_clear_history'));
        add_action('wp_ajax_aips_export_history', array($this, 'ajax_export_history'));
        add_action('wp_ajax_aips_get_history_details', array($this, 'ajax_get_history_details'));
        add_action('wp_ajax_aips_get_history_logs', array($this, 'ajax_get_history_logs'));
        add_action('wp_ajax_aips_reload_history', array($this, 'ajax_reload_history'));
        add_action('wp_ajax_aips_retry_generation', array($this, 'ajax_retry_generation'));
    }

    /**
     * AJAX handler to bulk delete selected history records.
     *
     * @return void
     */
    public function ajax_bulk_delete_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();

        if (empty($ids)) {
            AIPS_Ajax_Response::error(__('No items selected.', 'ai-post-scheduler'));
        }

        $result = $this->repository->delete_bulk($ids);

        if ($result === false) {
            AIPS_Ajax_Response::error(__('Failed to delete items.', 'ai-post-scheduler'));
        }

        AIPS_Ajax_Response::success(array(), __('Selected items deleted successfully.', 'ai-post-scheduler'));
    }

    /**
     * AJAX handler to clear history, optionally filtered by status.
     *
     * @return void
     */
    public function ajax_clear_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

        $this->clear_history($status);

        AIPS_Ajax_Response::success(array(), __('History cleared successfully.', 'ai-post-scheduler'));
    }

    /**
     * AJAX handler to export history records as CSV.
     *
     * @return void
     */
    public function ajax_export_history() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'ai-post-scheduler'));
        }

        $status_filter = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $search_query = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

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

        $output = fopen('php://output', 'w');
        if ($output === false) {
            wp_die(__('Failed to open output stream for CSV export.', 'ai-post-scheduler'));
        }

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }

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

    /**
     * AJAX handler to return details for a single history item.
     *
     * @return void
     */
    public function ajax_get_history_details() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
        
        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
        
        if (!$history_id) {
            AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
        }
        
        $history_item = $this->repository->get_by_id($history_id);
        
        if (!$history_item) {
            AIPS_Ajax_Response::error(__('History item not found.', 'ai-post-scheduler'));
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
        
        AIPS_Ajax_Response::success($response);
    }
    
    /**
     * AJAX handler to retrieve all log entries for a specific history container.
     *
     * Returns every row from aips_history_log for the given history_id, plus
     * summary data from the history record itself, so the modal can display
     * the complete picture of that generation run.
     *
     * @return void
     */
    public function ajax_get_history_logs() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

        if (!$history_id) {
            AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
        }

        $history_item = $this->repository->get_by_id($history_id);

        if (!$history_item) {
            AIPS_Ajax_Response::error(__('History container not found.', 'ai-post-scheduler'));
        }

        // $history_item->log is already populated by get_by_id(); reuse it to
        // avoid a second trip to aips_history_log.
        $raw_logs = isset($history_item->log) ? $history_item->log : array();

        $logs = array();
        foreach ($raw_logs as $log) {
            $details = array();
            if (!empty($log->details)) {
                $decoded = json_decode($log->details, true);
                if (is_array($decoded)) {
                    $details = $decoded;
                }
            }

            $logs[] = array(
                'id'               => (int) $log->id,
                'log_type'         => $log->log_type,
                'history_type_id'  => (int) $log->history_type_id,
                'type_label'       => AIPS_History_Type::get_label((int) $log->history_type_id),
                'timestamp'        => $log->timestamp,
                'details'          => $details,
            );
        }

        AIPS_Ajax_Response::success(array(
            'container' => array(
                'id'              => (int) $history_item->id,
                'status'          => $history_item->status,
                'generated_title' => $history_item->generated_title,
                'template_name'   => isset($history_item->template_name) ? $history_item->template_name : '',
                'created_at'      => $history_item->created_at,
                'completed_at'    => $history_item->completed_at,
                'error_message'   => $history_item->error_message,
                'post_id'         => $history_item->post_id ? (int) $history_item->post_id : null,
            ),
            'logs'      => $logs,
        ));
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
            AIPS_Ajax_Response::permission_denied();
        }

        $status_filter = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $search_query = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
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

        AIPS_Ajax_Response::success(array(
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
     * AJAX handler to retry generation for a history item template.
     *
     * @return void
     */
    public function ajax_retry_generation() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
        
        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;
        
        if (!$history_id) {
            AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
        }
        
        $history_item = $this->repository->get_by_id($history_id);
        
        if (!$history_item || !$history_item->template_id) {
            AIPS_Ajax_Response::error(__('History item not found or no template associated.', 'ai-post-scheduler'));
        }
        
        $templates = new AIPS_Templates();
        $template = $templates->get($history_item->template_id);
        
        if (!$template) {
            AIPS_Ajax_Response::error(__('Template no longer exists.', 'ai-post-scheduler'));
        }
        
        $generator = new AIPS_Generator();
        $result = $generator->generate_post($template);
        
        if (is_wp_error($result) && !is_int($result)) {
            AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
        }
        
        AIPS_Ajax_Response::success(array(
            'message' => __('Post regenerated successfully!', 'ai-post-scheduler'),
            'post_id' => $result
        ));
    }

    /**
     * Retrieve paginated history records.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_history($args = array()) {
        return $this->repository->get_history($args);
    }

    /**
     * Get aggregate history statistics.
     *
     * @return array
     */
    public function get_stats() {
        return $this->repository->get_stats();
    }

    /**
     * Get statistics for a specific template.
     *
     * @param int $template_id Template ID.
     * @return array
     */
    public function get_template_stats($template_id) {
        return $this->repository->get_template_stats($template_id);
    }

    /**
     * Get statistics for all templates.
     *
     * @return array
     */
    public function get_all_template_stats() {
        return $this->repository->get_all_template_stats();
    }

    /**
     * Clear history records, optionally filtered by status.
     *
     * @param string $status Status filter.
     * @return mixed
     */
    public function clear_history($status = '') {
        return $this->repository->delete_by_status($status);
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

    /**
     * Render the history admin page.
     *
     * @return void
     */
    public function render_page() {
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $search_query = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        
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
