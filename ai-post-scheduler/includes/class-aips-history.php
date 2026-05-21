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
        add_action('wp_ajax_aips_get_history_modal_html', array($this, 'ajax_get_history_modal_html'));
        add_action('wp_ajax_aips_reload_history', array($this, 'ajax_reload_history'));
        add_action('wp_ajax_aips_retry_generation', array($this, 'ajax_retry_generation'));
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
            AIPS_Ajax_Response::permission_denied();
        }

        $status_filter = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $search_query = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $domain_filter = isset($_POST['domain']) ? sanitize_key(wp_unslash($_POST['domain'])) : '';
        $actor_filter = isset($_POST['actor']) ? sanitize_key(wp_unslash($_POST['actor'])) : '';
        $correlation_id = isset($_POST['correlation_id']) ? sanitize_text_field(wp_unslash($_POST['correlation_id'])) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';

        // Get max records limit from configuration
        $config = AIPS_Config::get_instance();
        $max_records = (int) $config->get_option('history_export_max_records', 10000);

        // Fetch all matching records
        $history = $this->get_history(array(
            'page' => 1,
            'per_page' => $max_records,
            'status' => $status_filter,
            'search' => $search_query,
            'domain' => $domain_filter,
            'actor' => $actor_filter,
            'correlation_id' => $correlation_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
        ));

        $filename = 'aips-history-export-' . date('Y-m-d-H-i-s') . '.csv';
        $filename = sanitize_file_name($filename);

        $output = fopen('php://output', 'w');
        if ($output === false) {
            AIPS_Ajax_Response::error(__('Failed to open output stream for CSV export.', 'ai-post-scheduler'));
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
     * Build a normalized modal payload for a history container.
     *
     * Centralizes log normalization, AI request/response pairing, filter counts,
     * and JSON tree preparation so both modal entry points render the same data.
     *
     * @param object $history_item History container row with attached log rows.
     * @param bool   $format_dates Whether to format created/completed times for display.
     * @return array<string,mixed>
     */
    private function prepare_history_modal_view_data($history_item, $format_dates = false) {
        $logs = $this->normalize_history_logs(isset($history_item->log) ? $history_item->log : array());
        $container = $this->build_history_modal_container($history_item, $format_dates);
        $analysis = $this->analyze_history_modal_summary($container, $logs);
        $container = $this->finalize_history_modal_container($container, $analysis);
        $display_logs = $this->build_history_display_logs($logs);
        $filter_counts = $this->build_history_filter_counts($display_logs);

        return array(
            'container' => $container,
            'logs' => $logs,
            'display_logs' => $display_logs,
            'filter_counts' => $filter_counts,
        );
    }

    /**
     * Convert the base container + derived analysis into the final modal payload.
     *
     * @param array<string,mixed> $container Base container metadata.
     * @param array<string,mixed> $analysis  Derived summary analysis.
     * @return array<string,mixed>
     */
    private function finalize_history_modal_container($container, $analysis) {
        return array(
            'id' => isset($container['id']) ? (int) $container['id'] : 0,
            'header_title' => $this->build_history_modal_header_title($container),
            'status' => isset($container['status']) ? (string) $container['status'] : '',
            'status_class' => isset($container['status_class']) ? (string) $container['status_class'] : '',
            'header_actions' => $this->build_history_modal_header_actions($container),
            'summary_lines' => $this->build_history_summary_lines($analysis),
            'summary_meta' => $this->build_history_summary_meta($container),
            'detail_cards' => $this->build_history_detail_cards($container),
        );
    }

    /**
     * Derive high-level summary insights from the container and its logs.
     *
     * @param array<string,mixed>               $container Prepared container payload.
     * @param array<int,array<string,mixed>>    $logs      Normalized logs.
     * @return array<string,string>
     */
    private function analyze_history_modal_summary($container, $logs) {
        $text = strtolower(((string) $container['creation_method']) . ' ' . ((string) $container['template_name']));
        if (strpos($text, 'research') !== false) {
            $what_happened = __('Research run', 'ai-post-scheduler');
        } elseif (strpos($text, 'embedding') !== false) {
            $what_happened = __('Embeddings processing', 'ai-post-scheduler');
        } elseif (strpos($text, 'author') !== false && strpos($text, 'topic') !== false) {
            $what_happened = __('Author topic generation', 'ai-post-scheduler');
        } elseif (strpos($text, 'schedule') !== false) {
            $what_happened = __('Scheduled post generation', 'ai-post-scheduler');
        } else {
            $what_happened = $this->history_logs_include_ai_activity($logs)
                ? __('Post generation', 'ai-post-scheduler')
                : __('Automation task', 'ai-post-scheduler');
        }

        $outcome = $container['status'] === 'completed'
            ? __('Success', 'ai-post-scheduler')
            : ($container['status'] === 'failed'
                ? __('Failed', 'ai-post-scheduler')
                : __('In progress', 'ai-post-scheduler'));

        $saw_title_change = false;
        $saw_content_change = false;
        $saw_image_change = false;
        $saw_published_result = false;
        $saw_draft_result = false;

        foreach ($logs as $log) {
            $details = !empty($log['details']) && is_array($log['details']) ? $log['details'] : array();
            $this->scan_history_values_for_changes($details, $saw_title_change, $saw_content_change, $saw_image_change, $saw_published_result, $saw_draft_result);
        }

        $changes = array();
        if ($saw_title_change) {
            $changes[] = __('Title updated', 'ai-post-scheduler');
        }
        if ($saw_content_change) {
            $changes[] = __('Content updated', 'ai-post-scheduler');
        }
        if ($saw_image_change) {
            $changes[] = __('Image generated/updated', 'ai-post-scheduler');
        }
        if ($saw_published_result) {
            $changes[] = __('Published result', 'ai-post-scheduler');
        } elseif ($saw_draft_result) {
            $changes[] = __('Draft result', 'ai-post-scheduler');
        }
        if ($container['status'] === 'failed') {
            $changes[] = __('Run ended with an error', 'ai-post-scheduler');
        }

        return array(
            'what_happened' => $what_happened,
            'outcome_label' => $outcome,
            'what_changed' => !empty($changes) ? implode('; ', $changes) : __('No major content changes detected', 'ai-post-scheduler'),
        );
    }

    /**
     * Build the modal header title shown above the log content.
     *
     * @param array<string,mixed> $container Base container metadata.
     * @return string
     */
    private function build_history_modal_header_title($container) {
        $id = isset($container['id']) ? (int) $container['id'] : 0;
        $title = isset($container['generated_title']) ? trim((string) $container['generated_title']) : '';

        if ($title !== '') {
            return $title . ' #' . $id;
        }

        return __('History Details', 'ai-post-scheduler') . ($id > 0 ? ' #' . $id : '');
    }

    /**
     * Build top-of-modal action links.
     *
     * @param array<string,mixed> $container Base container metadata.
     * @return array<int,array<string,string>>
     */
    private function build_history_modal_header_actions($container) {
        $actions = array();

        if (!empty($container['post_url'])) {
            $actions[] = array(
                'label' => !empty($container['post_id'])
                    ? sprintf(__('View Post (ID: %d)', 'ai-post-scheduler'), (int) $container['post_id'])
                    : __('View Post', 'ai-post-scheduler'),
                'url' => (string) $container['post_url'],
            );
        }

        if (!empty($container['post_edit_url'])) {
            $actions[] = array(
                'label' => __('Edit', 'ai-post-scheduler'),
                'url' => (string) $container['post_edit_url'],
            );
        }

        return $actions;
    }

    /**
     * Build the combined Summary section lines.
     *
     * @param array<string,mixed> $analysis Derived analysis payload.
     * @return array<int,array<string,string>>
     */
    private function build_history_summary_lines($analysis) {
        $lines = array();

        if (!empty($analysis['outcome_label'])) {
            $lines[] = array(
                'label' => __('Outcome', 'ai-post-scheduler'),
                'value' => (string) $analysis['outcome_label'],
            );
        }

        if (!empty($analysis['what_happened'])) {
            $lines[] = array(
                'label' => __('What happened', 'ai-post-scheduler'),
                'value' => (string) $analysis['what_happened'],
            );
        }

        if (!empty($analysis['what_changed'])) {
            $lines[] = array(
                'label' => __('What changed', 'ai-post-scheduler'),
                'value' => (string) $analysis['what_changed'],
            );
        }

        return $lines;
    }

    /**
     * Build stacked Summary metadata values.
     *
     * @param array<string,mixed> $container Final base container metadata.
     * @return array<int,array<string,string>>
     */
    private function build_history_summary_meta($container) {
        $items = array();

        if (!empty($container['created_at'])) {
            $items[] = array(
                'label' => __('Created', 'ai-post-scheduler'),
                'value' => (string) $container['created_at'],
            );
        }

        if (!empty($container['duration_label'])) {
            $items[] = array(
                'label' => __('Duration', 'ai-post-scheduler'),
                'value' => (string) $container['duration_label'],
            );
        }

        return $items;
    }

    /**
     * Build the remaining detail cards shown beneath Summary.
     *
     * @param array<string,mixed> $container Base container metadata.
     * @return array<int,array<string,string>>
     */
    private function build_history_detail_cards($container) {
        $cards = array();

        if (!empty($container['template_name'])) {
            $cards[] = array(
                'label' => __('Template', 'ai-post-scheduler'),
                'value' => (string) $container['template_name'],
                'class' => '',
            );
        }

        if (!empty($container['creation_method'])) {
            $cards[] = array(
                'label' => __('Method', 'ai-post-scheduler'),
                'value' => ucfirst(str_replace('_', ' ', (string) $container['creation_method'])),
                'class' => '',
            );
        }

        if (!empty($container['post_id']) && empty($container['post_url']) && empty($container['post_edit_url'])) {
            $cards[] = array(
                'label' => __('Post ID', 'ai-post-scheduler'),
                'value' => (string) $container['post_id'],
                'class' => '',
            );
        }

        if (!empty($container['error_message'])) {
            $cards[] = array(
                'label' => __('Error', 'ai-post-scheduler'),
                'value' => (string) $container['error_message'],
                'class' => 'aips-history-summary-item-error',
            );
        }

        return $cards;
    }

    /**
     * Determine whether the log set contains AI activity.
     *
     * @param array<int,array<string,mixed>> $logs Normalized logs.
     * @return bool
     */
    private function history_logs_include_ai_activity($logs) {
        foreach ($logs as $log) {
            if ($this->is_ai_request_history_log($log) || $this->is_ai_response_history_log($log)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively scan log detail values for coarse content change indicators.
     *
     * @param mixed $value Current value to scan.
     * @param bool  $saw_title_change
     * @param bool  $saw_content_change
     * @param bool  $saw_image_change
     * @param bool  $saw_published_result
     * @param bool  $saw_draft_result
     * @return void
     */
    private function scan_history_values_for_changes($value, &$saw_title_change, &$saw_content_change, &$saw_image_change, &$saw_published_result, &$saw_draft_result) {
        if (is_string($value) && $value !== '') {
            $normalized = strtolower($value);
            if (strpos($normalized, 'title') !== false) {
                $saw_title_change = true;
            }
            if (strpos($normalized, 'content') !== false || strpos($normalized, 'body') !== false) {
                $saw_content_change = true;
            }
            if (strpos($normalized, 'featured image') !== false || strpos($normalized, 'image') !== false) {
                $saw_image_change = true;
            }
            if (strpos($normalized, 'publish') !== false) {
                $saw_published_result = true;
            }
            if (strpos($normalized, 'draft') !== false) {
                $saw_draft_result = true;
            }
            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $child) {
            $this->scan_history_values_for_changes($child, $saw_title_change, $saw_content_change, $saw_image_change, $saw_published_result, $saw_draft_result);
        }
    }

    /**
     * Normalize raw DB log rows into a consistent array structure.
     *
     * @param array $raw_logs Raw aips_history_log rows.
     * @return array<int,array<string,mixed>>
     */
    private function normalize_history_logs($raw_logs) {
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
                'id' => (int) $log->id,
                'log_type' => (string) $log->log_type,
                'history_type_id' => (int) $log->history_type_id,
                'type_label' => AIPS_History_Type::get_label((int) $log->history_type_id),
                'type_class' => $this->get_history_type_class((int) $log->history_type_id),
                'timestamp' => isset($log->timestamp) ? (string) $log->timestamp : '',
                'details' => $details,
            );
        }

        return $logs;
    }

    /**
     * Build container metadata for the logs modal.
     *
     * @param object $history_item  History container row.
     * @param bool   $format_dates  Whether to convert times to human-readable strings.
     * @return array<string,mixed>
     */
    private function build_history_modal_container($history_item, $format_dates = false) {
        $duration_seconds = $this->calculate_history_duration_seconds($history_item);
        $post_id = !empty($history_item->post_id) ? (int) $history_item->post_id : null;
        $post_urls = $this->build_history_post_urls($post_id);
        $created_at = isset($history_item->created_at) ? $history_item->created_at : '';
        if ($format_dates) {
            $created_at = !empty($created_at) ? AIPS_DateTime::formatRelativeOrAbsolute($created_at) : '';
        }

        return array(
            'id' => (int) $history_item->id,
            'status' => isset($history_item->status) ? (string) $history_item->status : '',
            'status_class' => $this->get_history_status_class(isset($history_item->status) ? (string) $history_item->status : ''),
            'generated_title' => isset($history_item->generated_title) ? $history_item->generated_title : '',
            'template_name' => isset($history_item->template_name) ? $history_item->template_name : '',
            'created_at' => $created_at,
            'error_message' => isset($history_item->error_message) ? $history_item->error_message : '',
            'post_id' => $post_id,
            'post_url' => $post_urls['post_url'],
            'post_edit_url' => $post_urls['post_edit_url'],
            'creation_method' => isset($history_item->creation_method) ? $history_item->creation_method : null,
            'duration_seconds' => $duration_seconds,
            'duration_label' => $this->format_history_duration_label($duration_seconds),
        );
    }

    /**
     * Calculate duration for a history container from its timestamps.
     *
     * @param object $history_item History container row.
     * @return int|null
     */
    private function calculate_history_duration_seconds($history_item) {
        $start = $this->normalize_history_timestamp_for_math(isset($history_item->created_at) ? $history_item->created_at : null);
        $end = $this->normalize_history_timestamp_for_math(isset($history_item->completed_at) ? $history_item->completed_at : null);

        if ($start === null || $end === null || $end < $start) {
            return null;
        }

        return $end - $start;
    }

    /**
     * Convert stored timestamps into a unix timestamp when possible.
     *
     * @param mixed $value Raw timestamp value.
     * @return int|null
     */
    private function normalize_history_timestamp_for_math($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $int_value = (int) $value;
            if ($int_value > 100000000) {
                return $int_value;
            }
        }

        $parsed = strtotime((string) $value);
        return $parsed !== false ? $parsed : null;
    }

    /**
     * Build post and edit URLs for a linked post.
     *
     * @param int|null $post_id Linked post ID.
     * @return array<string,string|null>
     */
    private function build_history_post_urls($post_id) {
        $post_url = null;
        $post_edit_url = null;

        if (!empty($post_id)) {
            $raw_post_url = get_permalink((int) $post_id);
            if (!empty($raw_post_url)) {
                $sanitized_post_url = esc_url_raw($raw_post_url);
                $post_url = !empty($sanitized_post_url) ? $sanitized_post_url : null;
            }

            $raw_post_edit_url = get_edit_post_link((int) $post_id, 'raw');
            if (!empty($raw_post_edit_url)) {
                $sanitized_post_edit_url = esc_url_raw($raw_post_edit_url);
                $post_edit_url = !empty($sanitized_post_edit_url) ? $sanitized_post_edit_url : null;
            }
        }

        return array(
            'post_url' => $post_url,
            'post_edit_url' => $post_edit_url,
        );
    }

    /**
     * Pair AI request/response logs in a single pass and prepare display rows.
     *
     * @param array<int,array<string,mixed>> $logs Normalized logs.
     * @return array<int,array<string,mixed>>
     */
    private function build_history_display_logs($logs) {
        $display_logs = array();
        $pending_requests = array();
        $detail_sequence = 0;

        foreach ($logs as $log) {
            if ($this->is_ai_request_history_log($log)) {
                $phase_key = $this->derive_ai_phase_key($log);
                $display_logs[] = array(
                    'timestamp' => $log['timestamp'],
                    'type_label' => __('AI Request / Response', 'ai-post-scheduler'),
                    'type_class' => $this->get_history_type_class(5),
                    'log_type' => $this->humanize_ai_phase_label($phase_key),
                    'type_ids' => array('5'),
                    'sections' => array(
                        $this->build_history_log_section($log, __('AI Request', 'ai-post-scheduler'), $detail_sequence),
                    ),
                );
                $display_index = count($display_logs) - 1;
                if (!isset($pending_requests[$phase_key])) {
                    $pending_requests[$phase_key] = array();
                }
                $pending_requests[$phase_key][] = $display_index;
                $detail_sequence++;
                continue;
            }

            if ($this->is_ai_response_history_log($log)) {
                $phase_key = $this->derive_ai_phase_key($log);
                if (!empty($pending_requests[$phase_key])) {
                    $display_index = array_shift($pending_requests[$phase_key]);
                    $display_logs[$display_index]['sections'][] = $this->build_history_log_section($log, __('AI Response', 'ai-post-scheduler'), $detail_sequence);
                    if (!in_array('6', $display_logs[$display_index]['type_ids'], true)) {
                        $display_logs[$display_index]['type_ids'][] = '6';
                    }
                } else {
                    $display_logs[] = array(
                        'timestamp' => $log['timestamp'],
                        'type_label' => __('AI Response', 'ai-post-scheduler'),
                        'type_class' => $this->get_history_type_class(6),
                        'log_type' => $this->humanize_ai_phase_label($phase_key),
                        'type_ids' => array('6'),
                        'sections' => array(
                            $this->build_history_log_section($log, __('AI Response', 'ai-post-scheduler'), $detail_sequence),
                        ),
                    );
                }
                $detail_sequence++;
                continue;
            }

            $display_logs[] = array(
                'timestamp' => $log['timestamp'],
                'type_label' => $log['type_label'],
                'type_class' => $log['type_class'],
                'log_type' => $log['log_type'],
                'type_ids' => array((string) $log['history_type_id']),
                'sections' => array(
                    $this->build_history_log_section($log, '', $detail_sequence),
                ),
            );
            $detail_sequence++;
        }

        return $display_logs;
    }

    /**
     * Prepare the detail-section payload for one rendered log section.
     *
     * @param array<string,mixed> $log             Normalized log entry.
     * @param string              $section_label   Label displayed above the section.
     * @param int                 $detail_sequence Unique detail panel sequence.
     * @return array<string,mixed>
     */
    private function build_history_log_section($log, $section_label, $detail_sequence) {
        $extra = $this->extract_history_log_extra_details($log);
        $raw_json = !empty($extra)
            ? wp_json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';

        return array(
            'label' => $section_label,
            'show_header' => $section_label !== '',
            'timestamp' => isset($log['timestamp']) ? $log['timestamp'] : '',
            'message_html' => !empty($log['details']['message'])
                ? $this->format_history_multiline_text($log['details']['message'])
                : '',
            'has_extra' => !empty($extra),
            'detail_id' => 'aips-log-detail-' . (int) $detail_sequence,
            'tree_html' => !empty($extra) ? $this->render_history_json_tree_html($extra) : '',
            'raw_json' => $raw_json ? $raw_json : '',
        );
    }

    /**
     * Build filter counts for rendered display rows.
     *
     * @param array<int,array<string,mixed>> $display_logs Prepared display rows.
     * @return array<string,int>
     */
    private function build_history_filter_counts($display_logs) {
        $counts = array('all' => count($display_logs));

        foreach ($display_logs as $display_log) {
            if (empty($display_log['type_ids']) || !is_array($display_log['type_ids'])) {
                continue;
            }

            foreach ($display_log['type_ids'] as $type_id) {
                $type_key = (string) $type_id;
                $counts[$type_key] = isset($counts[$type_key]) ? $counts[$type_key] + 1 : 1;
            }
        }

        return $counts;
    }

    /**
     * Return the CSS class for a history type badge.
     *
     * @param int $type_id History type ID.
     * @return string
     */
    private function get_history_type_class($type_id) {
        $map = array(
            1 => 'aips-badge-neutral',
            2 => 'aips-badge-error',
            3 => 'aips-badge-warning',
            4 => 'aips-badge-info',
            5 => 'aips-badge-ai',
            6 => 'aips-badge-ai',
            7 => 'aips-badge-neutral',
            8 => 'aips-badge-success',
            9 => 'aips-badge-neutral',
            10 => 'aips-badge-neutral',
        );

        return isset($map[$type_id]) ? $map[$type_id] : 'aips-badge-neutral';
    }

    /**
     * Return the CSS class for a history container status badge.
     *
     * @param string $status Container status.
     * @return string
     */
    private function get_history_status_class($status) {
        if ($status === 'completed') {
            return 'aips-badge-success';
        }

        if ($status === 'failed') {
            return 'aips-badge-error';
        }

        return 'aips-badge-neutral';
    }

    /**
     * Format a duration label for display.
     *
     * @param int|null $duration_seconds Duration in seconds.
     * @return string
     */
    private function format_history_duration_label($duration_seconds) {
        if ($duration_seconds === null) {
            return '';
        }

        if ($duration_seconds < 60) {
            return sprintf(__('%d seconds', 'ai-post-scheduler'), $duration_seconds);
        }

        return sprintf(
            __('%d min %d sec', 'ai-post-scheduler'),
            intdiv((int) $duration_seconds, 60),
            ((int) $duration_seconds) % 60
        );
    }

    /**
     * Determine whether a normalized log entry is an AI request.
     *
     * @param array<string,mixed> $log Normalized log entry.
     * @return bool
     */
    private function is_ai_request_history_log($log) {
        return (string) $log['history_type_id'] === '5' || (isset($log['log_type']) && $log['log_type'] === 'ai_request');
    }

    /**
     * Determine whether a normalized log entry is an AI response.
     *
     * @param array<string,mixed> $log Normalized log entry.
     * @return bool
     */
    private function is_ai_response_history_log($log) {
        return (string) $log['history_type_id'] === '6' || (isset($log['log_type']) && $log['log_type'] === 'ai_response');
    }

    /**
     * Infer the AI phase key for a log entry.
     *
     * Uses nested component metadata first, then falls back to message heuristics.
     *
     * @param array<string,mixed> $log Normalized log entry.
     * @return string
     */
    private function derive_ai_phase_key($log) {
        $details = !empty($log['details']) && is_array($log['details']) ? $log['details'] : array();
        $context = isset($details['context']) && is_array($details['context']) ? $details['context'] : array();
        $input = isset($details['input']) && is_array($details['input']) ? $details['input'] : array();
        $input_context = isset($input['context']) && is_array($input['context']) ? $input['context'] : array();
        $output = isset($details['output']) && is_array($details['output']) ? $details['output'] : array();

        $candidates = array(
            isset($context['component']) ? $context['component'] : '',
            isset($input_context['component']) ? $input_context['component'] : '',
            isset($details['phase']) ? $details['phase'] : '',
            isset($details['component']) ? $details['component'] : '',
            isset($details['content_type']) ? $details['content_type'] : '',
            isset($details['request_type']) ? $details['request_type'] : '',
            isset($details['target']) ? $details['target'] : '',
            isset($details['section']) ? $details['section'] : '',
            isset($details['field']) ? $details['field'] : '',
            isset($details['item_type']) ? $details['item_type'] : '',
            isset($details['stage']) ? $details['stage'] : '',
            isset($output['component']) ? $output['component'] : '',
        );

        foreach ($candidates as $candidate) {
            $normalized = $this->normalize_ai_phase_key($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $message = isset($details['message']) ? strtolower((string) $details['message']) : '';
        if (preg_match('/for\s+(.+?)(?:[\.:]|$)/i', $message, $matches)) {
            $normalized = $this->normalize_ai_phase_key($matches[1]);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        if (strpos($message, 'title') !== false) {
            return 'post_title';
        }
        if (strpos($message, 'excerpt') !== false) {
            return 'post_excerpt';
        }
        if (strpos($message, 'featured image') !== false || strpos($message, 'image') !== false) {
            return 'featured_image';
        }
        if (strpos($message, 'content') !== false || strpos($message, 'article') !== false) {
            return 'post_content';
        }

        return 'general';
    }

    /**
     * Normalize a freeform phase string into a stable key.
     *
     * @param mixed $value Raw phase value.
     * @return string
     */
    private function normalize_ai_phase_key($value) {
        return trim((string) preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $value))), '_');
    }

    /**
     * Convert a normalized AI phase key into a user-friendly label.
     *
     * @param string $phase_key Normalized phase key.
     * @return string
     */
    private function humanize_ai_phase_label($phase_key) {
        $normalized = $this->normalize_ai_phase_key($phase_key ?: 'general');
        $map = array(
            'post_title' => __('Post Title', 'ai-post-scheduler'),
            'title' => __('Post Title', 'ai-post-scheduler'),
            'post_content' => __('Post Content', 'ai-post-scheduler'),
            'content' => __('Post Content', 'ai-post-scheduler'),
            'article' => __('Post Content', 'ai-post-scheduler'),
            'body' => __('Post Content', 'ai-post-scheduler'),
            'post_excerpt' => __('Post Excerpt', 'ai-post-scheduler'),
            'excerpt' => __('Post Excerpt', 'ai-post-scheduler'),
            'featured_image' => __('Featured Image', 'ai-post-scheduler'),
            'image' => __('Featured Image', 'ai-post-scheduler'),
            'topic' => __('Topic', 'ai-post-scheduler'),
            'research' => __('Research', 'ai-post-scheduler'),
            'general' => __('General', 'ai-post-scheduler'),
        );

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        return ucwords(str_replace('_', ' ', $normalized));
    }

    /**
     * Extract non-message details from a log entry.
     *
     * @param array<string,mixed> $log Normalized log entry.
     * @return array<string,mixed>
     */
    private function extract_history_log_extra_details($log) {
        $details = !empty($log['details']) && is_array($log['details']) ? $log['details'] : array();
        unset($details['message'], $details['timestamp']);
        return $details;
    }

    /**
     * Format a multiline text value for HTML display.
     *
     * @param mixed $value Raw text value.
     * @return string
     */
    private function format_history_multiline_text($value) {
        return nl2br(esc_html((string) $value));
    }

    /**
     * Render a JSON tree block for the structured details viewer.
     *
     * @param mixed       $value JSON-compatible value.
     * @param string|null $label Optional key label.
     * @param int         $depth Current nesting depth.
     * @return string
     */
    private function render_history_json_tree_html($value, $label = null, $depth = 0) {
        $label_html = $label !== null
            ? '<span class="aips-json-key">' . esc_html((string) $label) . '</span>: '
            : '';

        if (!is_array($value)) {
            return '<div class="aips-json-leaf">' . $label_html . $this->render_history_json_scalar_html($value) . '</div>';
        }

        if (empty($value)) {
            $is_list = array_values($value) === $value;
            return '<div class="aips-json-leaf">' . $label_html . '<span class="aips-json-value aips-json-value-empty">' . ($is_list ? '[]' : '{}') . '</span></div>';
        }

        $is_list = array_values($value) === $value;
        $summary = '<span class="aips-json-summary-label">' . $label_html . '</span>'
            . '<span class="aips-json-meta">' . ($is_list ? 'Array[' . count($value) . ']' : 'Object{' . count($value) . '}') . '</span>';

        $html = '<details class="aips-json-node"' . ($depth <= 1 ? ' open' : '') . '>';
        $html .= '<summary class="aips-json-summary">' . $summary . '</summary>';
        $html .= '<div class="aips-json-children">';

        foreach ($value as $child_key => $child_value) {
            $html .= $this->render_history_json_tree_html($child_value, $child_key, $depth + 1);
        }

        $html .= '</div></details>';

        return $html;
    }

    /**
     * Render one scalar JSON value with semantic styling.
     *
     * @param mixed $value Scalar value.
     * @return string
     */
    private function render_history_json_scalar_html($value) {
        if ($value === null) {
            return '<span class="aips-json-value aips-json-value-null">null</span>';
        }

        if (is_string($value)) {
            return '<span class="aips-json-value aips-json-value-string">"' . $this->format_history_multiline_text($value) . '"</span>';
        }

        if (is_bool($value)) {
            return '<span class="aips-json-value aips-json-value-boolean">' . esc_html($value ? 'true' : 'false') . '</span>';
        }

        if (is_numeric($value)) {
            return '<span class="aips-json-value aips-json-value-number">' . esc_html((string) $value) . '</span>';
        }

        return '<span class="aips-json-value">' . esc_html((string) $value) . '</span>';
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

        $modal_view = $this->prepare_history_modal_view_data($history_item, false);

        AIPS_Ajax_Response::success($modal_view);
    }

    /**
     * AJAX handler to retrieve and return the full modal HTML for a history container.
     *
     * This returns the pre-rendered modal with all content, allowing it to be opened
     * directly without navigating to the History page.
     *
     * @return void
     */
    public function ajax_get_history_modal_html() {
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

        $modal_view = $this->prepare_history_modal_view_data($history_item, true);
        $container = $modal_view['container'];
        $display_logs = $modal_view['display_logs'];
        $filter_counts = $modal_view['filter_counts'];

        // Render the modal HTML
        ob_start();
        include AIPS_PLUGIN_DIR . 'templates/partials/history-modal-content.php';
        $modal_html = ob_get_clean();

        AIPS_Ajax_Response::success(array(
            'modal_html' => $modal_html,
            'container'  => $container,
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
        $domain_filter = isset($_POST['domain']) ? sanitize_key(wp_unslash($_POST['domain'])) : '';
        $actor_filter = isset($_POST['actor']) ? sanitize_key(wp_unslash($_POST['actor'])) : '';
        $correlation_id = isset($_POST['correlation_id']) ? sanitize_text_field(wp_unslash($_POST['correlation_id'])) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';
        $paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;

        $history = $this->get_history(array(
            'page'   => $paged,
            'status' => $status_filter,
            'search' => $search_query,
            'domain' => $domain_filter,
            'actor' => $actor_filter,
            'correlation_id' => $correlation_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
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
        $domain_filter = isset($_GET['domain']) ? sanitize_key(wp_unslash($_GET['domain'])) : '';
        $actor_filter = isset($_GET['actor']) ? sanitize_key(wp_unslash($_GET['actor'])) : '';
        $correlation_id = isset($_GET['correlation_id']) ? sanitize_text_field(wp_unslash($_GET['correlation_id'])) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

        $history = $this->get_history(array(
            'page'   => $current_page,
            'status' => $status_filter,
            'search' => $search_query,
            'domain' => $domain_filter,
            'actor' => $actor_filter,
            'correlation_id' => $correlation_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
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
}
