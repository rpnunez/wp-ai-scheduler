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
        add_action('wp_ajax_aips_get_history_top_level', array($this, 'ajax_get_history_top_level'));
        add_action('wp_ajax_aips_get_operation_children', array($this, 'ajax_get_operation_children'));
    }

    /**
     * AJAX handler to bulk delete selected history records.
     *
     * @return void
     */
    public function ajax_bulk_delete_history() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

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
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

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
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

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
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
        
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
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

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

        // Calculate duration between created_at and completed_at.
        // created_at and completed_at are stored as Unix timestamps (bigint).
        $duration_seconds = null;
        if ( ! empty( $history_item->created_at ) && ! empty( $history_item->completed_at ) ) {
            $start = (int) $history_item->created_at;
            $end   = (int) $history_item->completed_at;
            if ( $start > 0 && $end >= $start ) {
                $duration_seconds = $end - $start;
            }
        }

        // Build post URLs when a post is linked.
        $post_url      = null;
        $post_edit_url = null;
        if ( ! empty( $history_item->post_id ) ) {
            $raw_post_url = get_permalink( (int) $history_item->post_id );
            if ( ! empty( $raw_post_url ) ) {
                $sanitized_post_url = esc_url_raw( $raw_post_url );
                $post_url           = ! empty( $sanitized_post_url ) ? $sanitized_post_url : null;
            }

            $raw_post_edit_url = get_edit_post_link( (int) $history_item->post_id, 'raw' );
            if ( ! empty( $raw_post_edit_url ) ) {
                $sanitized_post_edit_url = esc_url_raw( $raw_post_edit_url );
                $post_edit_url           = ! empty( $sanitized_post_edit_url ) ? $sanitized_post_edit_url : null;
            }
        }

        AIPS_Ajax_Response::success(array(
            'container' => array(
                'id'               => (int) $history_item->id,
                'status'           => $history_item->status,
                'generated_title'  => $history_item->generated_title,
                'template_name'    => isset( $history_item->template_name ) ? $history_item->template_name : '',
                'created_at'       => $history_item->created_at,
                'completed_at'     => $history_item->completed_at,
                'error_message'    => $history_item->error_message,
                'post_id'          => $history_item->post_id ? (int) $history_item->post_id : null,
                'post_url'         => $post_url,
                'post_edit_url'    => $post_edit_url,
                'creation_method'  => isset( $history_item->creation_method ) ? $history_item->creation_method : null,
                'duration_seconds' => $duration_seconds,
                'parent_id'        => isset( $history_item->parent_id ) ? ( $history_item->parent_id ? (int) $history_item->parent_id : null ) : null,
                'is_parent'        => isset( $history_item->parent_id ) ? ( is_null($history_item->parent_id) && in_array(isset($history_item->creation_method) ? $history_item->creation_method : '', AIPS_History_Operation_Type::get_parent_types(), true) ) : false,
                'operation_label'  => isset( $history_item->creation_method ) ? AIPS_History_Operation_Type::get_label( $history_item->creation_method ) : '',
                'child_summary'    => $this->repository->get_child_summary( $history_id ),
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
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $status_filter = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $search_query = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;

        $history = $this->get_history(array(
            'page'   => $paged,
            'status' => $status_filter,
            'search' => $search_query,
            'fields' => 'list',
        ));

        $this->prepare_items_for_display($history['items']);

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
            'items_html'      => $items_html,
            'pagination_html' => $pagination_html,
            'paged'           => $paged,
            'stats'           => $this->get_stats(),
        ));
    }

    /**
     * AJAX handler to retry generation for a history item template.
     *
     * @return void
     */
    public function ajax_retry_generation() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
        
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
            'page'   => $current_page,
            'status' => $status_filter,
            'search' => $search_query,
            'fields' => 'list',
        ));

        $this->prepare_items_for_display($history['items']);

        // Pass handler to template for helper methods
        $history_handler = $this;

        include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
    }

    /**
     * Enrich a list of history items with display-ready fields.
     *
     * Calls get_option() once per request so per-row template code does not
     * repeat the call for every item in the list.
     *
     * @param array $items Array of history item objects (passed by reference).
     * @return void
     */
    private function prepare_items_for_display( array &$items ) {
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $format      = $date_format . ' ' . $time_format;

        foreach ($items as $item) {
            $item->formatted_date = date_i18n($format, strtotime($item->created_at));
        }
    }

    /**
     * AJAX handler to retrieve top-level (parent) history operations.
     *
     * Supports optional `operation_type` and `paged` filters.
     *
     * @return void
     */
    public function ajax_get_history_top_level() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $args = array(
            'page'     => isset($_POST['paged']) ? absint($_POST['paged']) : 1,
            'per_page' => 20,
        );

        if (!empty($_POST['operation_type'])) {
            $args['operation_type'] = sanitize_text_field(wp_unslash($_POST['operation_type']));
        }

        $result = $this->repository->get_top_level($args);

        $items = array();
        foreach ($result['items'] as $item) {
            $child_summary = $this->repository->get_child_summary((int) $item->id);
            $items[] = array(
                'id'              => (int) $item->id,
                'status'          => $item->status,
                'creation_method' => isset($item->creation_method) ? $item->creation_method : '',
                'operation_label' => AIPS_History_Operation_Type::get_label(isset($item->creation_method) ? $item->creation_method : ''),
                'trigger_name'    => isset($item->trigger_name) ? $item->trigger_name : '',
                'created_at'      => $item->created_at,
                'completed_at'    => $item->completed_at,
                'child_summary'   => $child_summary,
            );
        }

        AIPS_Ajax_Response::success(array(
            'items'      => $items,
            'total'      => isset($result['total']) ? (int) $result['total'] : 0,
            'page'       => (int) $args['page'],
            'per_page'   => (int) $args['per_page'],
        ));
    }

    /**
     * AJAX handler to retrieve child history items for a parent operation.
     *
     * @return void
     */
    public function ajax_get_operation_children() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }

        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

        if (!$history_id) {
            AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
        }

        $children = $this->repository->get_children($history_id);

        $items = array();
        foreach ($children as $child) {
            $duration_seconds = null;
            if (!empty($child->created_at) && !empty($child->completed_at)) {
                $start = (int) $child->created_at;
                $end   = (int) $child->completed_at;
                if ($start > 0 && $end >= $start) {
                    $duration_seconds = $end - $start;
                }
            }

            $post_url = null;
            if (!empty($child->post_id)) {
                $raw_url = get_permalink((int) $child->post_id);
                if ($raw_url) {
                    $post_url = esc_url_raw($raw_url);
                }
            }

            $items[] = array(
                'id'               => (int) $child->id,
                'status'           => $child->status,
                'generated_title'  => $child->generated_title,
                'creation_method'  => isset($child->creation_method) ? $child->creation_method : '',
                'template_name'    => isset($child->template_name) ? $child->template_name : '',
                'created_at'       => $child->created_at,
                'completed_at'     => $child->completed_at,
                'error_message'    => $child->error_message,
                'post_id'          => $child->post_id ? (int) $child->post_id : null,
                'post_url'         => $post_url,
                'duration_seconds' => $duration_seconds,
            );
        }

        AIPS_Ajax_Response::success(array('children' => $items));
    }
}
