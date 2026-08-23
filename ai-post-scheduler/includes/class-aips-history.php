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
     * Maximum number of items shown in timeline sidebar.
     */
    private const TIMELINE_MAX_ITEMS = 30;
    
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
        new AIPS_History_Ajax_Controller($this, $this->repository);
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
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

        $history = $this->get_history(array(
            'page'   => $current_page,
            'status' => $status_filter,
            'search' => $search_query,
            'domain' => $domain_filter,
            'actor' => $actor_filter,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'fields' => 'list',
        ));

        $this->prepare_items_for_display($history['items']);
        $stats = $this->get_stats();

        // Pass handler to template for helper methods
        $history_handler = $this;

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
}
