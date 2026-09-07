<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles history management for AI post generation runs.
 *
 * Coordinates history retrieval, stats, and admin page rendering.
 */
class AIPS_History {

    /**
     * Maximum number of items shown in timeline sidebar.
     */
    private const TIMELINE_MAX_ITEMS = 30;
    
    /**
     * @var AIPS_History_Repository Repository for database operations
     */
    private $repository;

    /**
     * Initialize history handler dependencies.
     *
     * @return void
     */
    public function __construct() {
        $this->repository = new AIPS_History_Repository();
    }

    /**
     * Human-readable labels for known creation_method values.
     *
     * @return array<string,string>
     */
    private static function creation_method_labels(): array {
        return array(
            'manual'                  => __( 'Manual', 'ai-post-scheduler' ),
            'scheduled'               => __( 'Scheduled', 'ai-post-scheduler' ),
            'template_schedule'       => __( 'Template Schedule', 'ai-post-scheduler' ),
            'author_topic_gen'        => __( 'Author Topics', 'ai-post-scheduler' ),
            'author_post_gen'         => __( 'Author Posts', 'ai-post-scheduler' ),
            'author_topic_generation' => __( 'Author Topics', 'ai-post-scheduler' ),
            'author_post_generation'  => __( 'Author Posts', 'ai-post-scheduler' ),
            'author_embeddings'       => __( 'Author Embeddings', 'ai-post-scheduler' ),
            'content_indexing'        => __( 'Content Indexing', 'ai-post-scheduler' ),
            'post_generation'         => __( 'Post Generation', 'ai-post-scheduler' ),
            'bulk_generate'           => __( 'Bulk Generation', 'ai-post-scheduler' ),
            'bulk_generate_now'       => __( 'Bulk Generation', 'ai-post-scheduler' ),
            'bulk_generation'         => __( 'Bulk Generation', 'ai-post-scheduler' ),
            'bulk_regenerate'         => __( 'Bulk Regeneration', 'ai-post-scheduler' ),
        );
    }

    /**
     * Return a display title for a history row, falling back to the creation
     * method label or a generic placeholder when no generated title exists.
     *
     * @param object $item History row object from wp_aips_history.
     * @return string
     */
    public static function get_display_title( object $item ): string {
        if ( ! empty( $item->generated_title ) ) {
            return $item->generated_title;
        }
        $labels = self::creation_method_labels();
        if ( ! empty( $item->creation_method ) && isset( $labels[ $item->creation_method ] ) ) {
            return $labels[ $item->creation_method ];
        }
        return __( 'Generation Event', 'ai-post-scheduler' );
    }

    /**
     * Return a human-readable label for a creation_method value.
     *
     * @param string $method Raw creation_method value from the DB.
     * @return string
     */
    public static function get_creation_method_label( string $method ): string {
        $labels = self::creation_method_labels();
        return $labels[ $method ] ?? ucwords( str_replace( '_', ' ', $method ) );
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
     * Format a duration for the History summary UI.
     *
     * @param int|null $duration_seconds Duration in seconds.
     * @return string
     */
    public function format_duration_for_display($duration_seconds) {
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
  
    private function analyze_history_modal_summary($container, $logs) {
        $text = strtolower(((string) $container['creation_method']) . ' ' . ((string) $container['template_name']));
        if (strpos($text, 'content_index') !== false || strpos($text, 'content index') !== false) {
            $what_happened = __('Content indexing', 'ai-post-scheduler');
        } elseif (strpos($text, 'research') !== false) {
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

        $outcome = in_array($container['status'], array('completed', 'indexed'), true)
            ? __('Success', 'ai-post-scheduler')
            : ($container['status'] === 'failed'
                ? __('Failed', 'ai-post-scheduler')
                : __('In progress', 'ai-post-scheduler'));

        $saw_title_change = false;
        $saw_content_change = false;
        $saw_image_change = false;
        $saw_published_result = false;
        $saw_draft_result = false;
        $saw_embedding = false;
        $saw_relationships = false;
        $embedding_dims = 0;

        foreach ($logs as $log) {
            $details = !empty($log['details']) && is_array($log['details']) ? $log['details'] : array();
            $dimensions_found = false;
            $dimensions = $this->find_history_detail_value($details, 'dimensions', $dimensions_found);
            if ($dimensions_found && (int) $dimensions > 0) {
                $saw_embedding = true;
                $embedding_dims = (int) $dimensions;
            }

            $relationships_found = false;
            $this->find_history_detail_value($details, 'relationships_saved', $relationships_found);
            if ($relationships_found) {
                $saw_relationships = true;
            }
            $this->scan_history_values_for_changes($details, $saw_title_change, $saw_content_change, $saw_image_change, $saw_published_result, $saw_draft_result);
        }

        $changes = array();
        if ($saw_embedding) {
            if ($embedding_dims > 0) {
                $changes[] = sprintf(__('Generated %d-dimension vector', 'ai-post-scheduler'), $embedding_dims);
            } else {
                $changes[] = __('Generated embedding vector', 'ai-post-scheduler');
            }
        }
        if ($saw_relationships) {
            $changes[] = __('Recomputed related posts', 'ai-post-scheduler');
        }
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
     * Find a keyed value anywhere in nested History log details.
     *
     * AIPS_History_Container::record() nests caller data below input, output,
     * or context, so summary metrics cannot assume top-level keys.
     *
     * @param mixed  $value Current value to inspect.
     * @param string $key   Key to find.
     * @param bool   $found Whether the key was found.
     * @return mixed|null
     */
    private function find_history_detail_value($value, $key, &$found) {
        if (!is_array($value)) {
            return null;
        }

        if (array_key_exists($key, $value)) {
            $found = true;
            return $value[$key];
        }

        foreach ($value as $child) {
            $result = $this->find_history_detail_value($child, $key, $found);
            if ($found) {
                return $result;
            }
        }

        return null;
    }

     

    /**
     * Pair AI request/response logs in a single pass and prepare display rows.
     *
     * @param array<int,array<string,mixed>> $logs             Normalized logs.
     * @param string                         $detail_id_prefix Unique prefix for detail panel IDs in this render.
     * @return array<int,array<string,mixed>>
     */
    private function build_history_display_logs($logs, $detail_id_prefix) {
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
                        $this->build_history_log_section($log, __('AI Request', 'ai-post-scheduler'), $detail_sequence, $detail_id_prefix),
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
                    $display_logs[$display_index]['sections'][] = $this->build_history_log_section($log, __('AI Response', 'ai-post-scheduler'), $detail_sequence, $detail_id_prefix);
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
                            $this->build_history_log_section($log, __('AI Response', 'ai-post-scheduler'), $detail_sequence, $detail_id_prefix),
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
                    $this->build_history_log_section($log, '', $detail_sequence, $detail_id_prefix),
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
     * @param string              $detail_id_prefix Unique detail panel prefix for this render.
     * @return array<string,mixed>
     */
    private function build_history_log_section($log, $section_label, $detail_sequence, $detail_id_prefix) {
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
            'detail_id' => $detail_id_prefix . '-' . (int) $detail_sequence,
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
        $counts = array(
            'all' => count($display_logs),
            'ai_request_response' => 0,
        );

        foreach ($display_logs as $display_log) {
            if (empty($display_log['type_ids']) || !is_array($display_log['type_ids'])) {
                continue;
            }

            $row_has_ai_pair_type = false;

            foreach ($display_log['type_ids'] as $type_id) {
                $type_key = (string) $type_id;
                $counts[$type_key] = isset($counts[$type_key]) ? $counts[$type_key] + 1 : 1;

                if ($type_key === '5' || $type_key === '6') {
                    $row_has_ai_pair_type = true;
                }
            }

            if ($row_has_ai_pair_type) {
                $counts['ai_request_response']++;
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
        if ($status === 'completed' || $status === 'indexed') {
            return 'aips-badge-success';
        }

        if ($status === 'failed') {
            return 'aips-badge-error';
        }

        if ($status === 'processing') {
            return 'aips-badge-info';
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

    public function format_duration_for_display($duration_seconds) {
        return $this->format_history_duration_label($duration_seconds);
    }

    /**
     * Determine whether a normalized log entry is an AI request.
     *
     * @param array<string,mixed> $log Normalized log entry.
     * @return bool
     */
    private function is_ai_request_history_log($log) {
        return (int) $log['history_type_id'] === AIPS_History_Type::AI_REQUEST;
    }

    /**
     * Determine whether a normalized log entry is an AI response.
     *
     * @param array<string,mixed> $log Normalized log entry.
     * @return bool
     */
    private function is_ai_response_history_log($log) {
        return (int) $log['history_type_id'] === AIPS_History_Type::AI_RESPONSE;
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
        $post_type_filter = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
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
            'post_type' => $post_type_filter,
            'correlation_id' => $correlation_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'fields' => 'list',
        ));

        $this->prepare_items_for_display($history['items']);

        $items_html = !empty($history['items']) ? $this->render_table_rows_html($history['items']) : '';

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
    public function sanitize_csv_cell($value) {
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
        $post_type_filter = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
        $correlation_id = isset($_GET['correlation_id']) ? sanitize_text_field(wp_unslash($_GET['correlation_id'])) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

        $history = $this->get_history(array(
            'page'   => $current_page,
            'status' => $status_filter,
            'search' => $search_query,
            'domain' => $domain_filter,
            'actor' => $actor_filter,
            'post_type' => $post_type_filter,
            'correlation_id' => $correlation_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'fields' => 'list',
        ));

        $this->prepare_items_for_display($history['items']);
        $stats = $this->get_stats();

        // Pass handler to template for helper methods
        $history_handler = $this;
        $selectable_post_types = AIPS_Utilities::get_selectable_post_types();

        include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
    }

    /**
     * Build and render timeline HTML for the History page sidebar.
     *
     * @param array $items Prepared history items for the current view.
     * @return void
     */
    public function render_timeline_html(array $items) {
        $timeline_items = array_slice($items, 0, self::TIMELINE_MAX_ITEMS);
        $now_timestamp = current_time('timestamp', true);
        $history_handler = $this;

        include AIPS_PLUGIN_DIR . 'templates/partials/history-timeline.php';
    }

    /**
     * Get the grouped date label used in timeline cards.
     *
     * @param int $timestamp Unix timestamp.
     * @param int|null $now_timestamp Optional reference timestamp.
     * @return string
     */
    public function get_timeline_group_label($timestamp, $now_timestamp = null) {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return __('Older', 'ai-post-scheduler');
        }

        if ($now_timestamp === null) {
            $now_timestamp = current_time('timestamp', true);
        }

        $site_timezone = wp_timezone();
        $today = wp_date('Y-m-d', $now_timestamp, $site_timezone);
        $yesterday = wp_date('Y-m-d', $now_timestamp - DAY_IN_SECONDS, $site_timezone);
        $item_date = wp_date('Y-m-d', $timestamp, $site_timezone);

        if ($item_date === $today) {
            return __('Today', 'ai-post-scheduler');
        }

        if ($item_date === $yesterday) {
            return __('Yesterday', 'ai-post-scheduler');
        }

        if ($timestamp >= ($now_timestamp - WEEK_IN_SECONDS)) {
            return __('In the last week', 'ai-post-scheduler');
        }

        if ($timestamp >= ($now_timestamp - (30 * DAY_IN_SECONDS))) {
            return __('In the last month', 'ai-post-scheduler');
        }

        return __('Older', 'ai-post-scheduler');
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
    public function prepare_items_for_display( array &$items ) {
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $format      = $date_format . ' ' . $time_format;

        foreach ($items as $item) {
            $created_at = isset( $item->created_at ) ? $item->created_at : 0;
            $date_time  = is_numeric( $created_at )
                ? AIPS_DateTime::fromTimestampOrNull( absint( $created_at ) )
                : AIPS_DateTime::fromMysqlOrNull( (string) $created_at );

            if ( ! ( $date_time instanceof AIPS_DateTime ) ) {
                $item->formatted_date = '';
                continue;
            }

            $item->formatted_date = $date_time->toDisplay( $format );
            $item->relative_date = AIPS_DateTime::formatRelativeOrAbsolute($created_at);
            $item->duration_label = isset($item->duration_seconds) && $item->duration_seconds !== null
                ? $this->format_history_duration_label((int) $item->duration_seconds)
                : '';
            $item->warning_count = isset($item->warning_count) ? (int) $item->warning_count : 0;
            $item->error_count = isset($item->error_count) ? (int) $item->error_count : 0;
            $item->ai_call_count = isset($item->ai_call_count) ? (int) $item->ai_call_count : 0;
            if (!empty($item->latest_message)) {
                $latest_details = json_decode((string) $item->latest_message, true);
                if (is_array($latest_details) && !empty($latest_details['message'])) {
                    $item->latest_message = sanitize_text_field((string) $latest_details['message']);
                } elseif (strpos(ltrim((string) $item->latest_message), '{') === 0) {
                    $item->latest_message = '';
                }
            }
        }
    }

    /**
     * Group contiguous history items sharing the same creation method.
     *
     * Clusters runs of 2 or more adjacent items of the same activity type into
     * a collapsed group structure for cleaner list presentation.
     *
     * @param array $items List of prepared history item objects.
     * @return array Array of group descriptors and individual item wrappers.
     */
    public function group_contiguous_items( array $items ): array {
        if ( empty( $items ) ) {
            return array();
        }

        $grouped = array();
        $count   = count( $items );
        $i       = 0;
        $group_index = 1;

        while ( $i < $count ) {
            $current_item   = $items[ $i ];
            $current_method = ! empty( $current_item->creation_method ) ? (string) $current_item->creation_method : 'post_generation';

            // Look ahead for identical consecutive methods
            $j = $i + 1;
            while ( $j < $count ) {
                $next_method = ! empty( $items[ $j ]->creation_method ) ? (string) $items[ $j ]->creation_method : 'post_generation';
                if ( $next_method !== $current_method ) {
                    break;
                }
                $j++;
            }

            $run_length = $j - $i;

            if ( $run_length >= 2 ) {
                $slice      = array_slice( $items, $i, $run_length );
                $completed  = 0;
                $failed     = 0;
                $processing = 0;
                $post_types = array();
                $ids        = array();

                foreach ( $slice as $item ) {
                    $ids[] = (int) $item->id;
                    if ( $item->status === 'completed' || $item->status === 'indexed' ) {
                        $completed++;
                    } elseif ( $item->status === 'failed' ) {
                        $failed++;
                    } elseif ( $item->status === 'processing' ) {
                        $processing++;
                    }
                    if ( ! empty( $item->post_type ) && ! in_array( $item->post_type, $post_types, true ) ) {
                        $post_types[] = $item->post_type;
                    }
                }

                $first_item = $slice[0];
                $last_item  = $slice[ $run_length - 1 ];

                $first_date = ! empty( $first_item->relative_date ) ? (string) $first_item->relative_date : '';
                $last_date  = ! empty( $last_item->relative_date ) ? (string) $last_item->relative_date : '';

                $grouped[] = array(
                    'is_group'        => true,
                    'group_id'        => 'grp_' . $group_index . '_' . $first_item->id,
                    'method'          => $current_method,
                    'label'           => self::get_creation_method_label( $current_method ),
                    'count'           => $run_length,
                    'items'           => $slice,
                    'completed_count' => $completed,
                    'failed_count'    => $failed,
                    'processing_count'=> $processing,
                    'post_types'      => $post_types,
                    'ids'             => $ids,
                    'first_date'      => $first_date,
                    'last_date'       => $last_date,
                );

                $group_index++;
                $i = $j;
            } else {
                $grouped[] = array(
                    'is_group' => false,
                    'item'     => $current_item,
                );
                $i++;
            }
        }

        return $grouped;
    }

    /**
     * Render the table rows HTML (with contiguous grouping) for a list of items.
     *
     * @param array $items Prepared history item objects.
     * @return string HTML of rows.
     */
    public function render_table_rows_html( array $items ): string {
        $grouped_entries = $this->group_contiguous_items( $items );

        ob_start();
        foreach ( $grouped_entries as $entry ) {
            if ( $entry['is_group'] ) {
                $group = $entry;
                include AIPS_PLUGIN_DIR . 'templates/partials/history-group-row.php';
                foreach ( $entry['items'] as $item ) {
                    $is_child_row = true;
                    $group_id     = $entry['group_id'];
                    include AIPS_PLUGIN_DIR . 'templates/partials/history-row.php';
                }
            } else {
                $is_child_row = false;
                $group_id     = '';
                $item         = $entry['item'];
                include AIPS_PLUGIN_DIR . 'templates/partials/history-row.php';
            }
        }
        return (string) ob_get_clean();
    }
}
