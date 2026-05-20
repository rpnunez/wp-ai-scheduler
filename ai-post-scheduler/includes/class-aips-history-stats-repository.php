<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repository for AI Post Scheduler history statistics.
 *
 * Extracted from AIPS_History_Repository to mitigate God Object anti-pattern.
 * Handles analytical queries and statistics generation.
 *
 * @since 1.2.0
 */
class AIPS_History_Stats_Repository {

    /**
     * @var wpdb WordPress database instance.
     */
    protected $wpdb;

    /**
     * @var string History table name.
     */
    protected $table_name;

    /**
     * @var string History log table name.
     */
    protected $table_name_log;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_history';
        $this->table_name_log = $wpdb->prefix . 'aips_history_log';
    }

    /**
     * Get the daily trend of successful and failed generations.
     *
     * @param int $days Number of days to include. Default 14.
     * @return array Array of associative arrays with 'metric_date', 'success_count', 'failure_count'.
     */
    public function get_daily_success_failure_trend($days = 14) {
        $days = max(1, absint($days));

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(FROM_UNIXTIME(created_at)) AS metric_date, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS success_count, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failure_count FROM {$this->table_name} WHERE created_at >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY metric_date ORDER BY metric_date ASC",
            $days
        ), ARRAY_A);
    }

    /**
     * Get average generation duration grouped by creation method (flow).
     *
     * @param int $days Number of days to analyze. Default 14.
     * @return array Array of associative arrays with 'flow_type', 'avg_duration_seconds', 'sample_count'.
     */
    public function get_average_duration_by_flow($days = 14) {
        $days = max(1, absint($days));

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT COALESCE(NULLIF(creation_method, ''), 'unknown') AS flow_type, AVG(completed_at - created_at) AS avg_duration_seconds, COUNT(*) AS sample_count FROM {$this->table_name} WHERE completed_at IS NOT NULL AND created_at >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY flow_type ORDER BY avg_duration_seconds DESC",
            $days
        ), ARRAY_A);
    }

    /**
     * Get retry counts grouped by service within logs.
     *
     * @param int $days Number of days to include. Default 14.
     * @return array Array of associative arrays with 'service_key' and 'retry_count'.
     */
    public function get_retry_counts_by_service($days = 14) {
        $days = max(1, absint($days));

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details, '$.context')), ''), 'unknown') AS service_key, COUNT(*) AS retry_count FROM {$this->table_name_log} WHERE log_type = %s AND timestamp >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY service_key ORDER BY retry_count DESC",
            'retry',
            $days
        ), ARRAY_A);
    }

    /**
     * Get the most frequent failure reasons.
     *
     * @param int $days Number of days to analyze. Default 14.
     * @param int $limit Number of top reasons to return. Default 8.
     * @return array Array of associative arrays with 'reason' and 'failure_count'.
     */
    public function get_top_failure_reasons($days = 14, $limit = 8) {
        $days = max(1, absint($days));
        $limit = max(1, absint($limit));

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT COALESCE(NULLIF(TRIM(error_message), ''), 'Unknown failure') AS reason, COUNT(*) AS failure_count FROM {$this->table_name} WHERE status = 'failed' AND created_at >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY reason ORDER BY failure_count DESC LIMIT %d",
            $days,
            $limit
        ), ARRAY_A);
    }

    /**
     * Get estimated generation time per post.
     *
     * Calculates the average generation time based on the most recent completed posts.
     *
     * @param int $limit Number of recent records to sample. Default 20.
     * @return array {
     *     @type int $per_post_seconds Estimated seconds per post.
     *     @type int $sample_size      Number of records used for calculation.
     * }
     */
    public function get_estimated_generation_time($limit = 20) {
        $default_seconds = 30;
        $limit           = absint($limit);

        // Retrieve the most recent recorded generation times.
        $times = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT meta_value FROM {$this->wpdb->postmeta}
                 WHERE meta_key = %s
                 ORDER BY meta_id DESC
                 LIMIT %d",
                '_aips_post_generation_total_time',
                $limit
            )
        );

        if (!empty($times)) {
            $numeric_times = array_filter(array_map('floatval', $times), function($v) {
                return $v > 0;
            });

            if (!empty($numeric_times)) {
                $avg              = array_sum($numeric_times) / count($numeric_times);
                $per_post_seconds = (int) ceil($avg);
            } else {
                $per_post_seconds = $default_seconds;
            }

            $sample_size = count($numeric_times);
        } else {
            $per_post_seconds = $default_seconds;
            $sample_size      = 0;
        }

        return array(
            'per_post_seconds' => $per_post_seconds,
            'sample_size'      => $sample_size,
        );
    }

    /**
     * Get overall generation statistics.
     *
     * Retrieves counts for total, completed, failed, processing, and partial statuses.
     *
     * @return array Associative array of statistics.
     */
    public function get_stats() {
        $cached_stats = get_transient('aips_history_stats');

        if ($cached_stats !== false) {
            return $cached_stats;
        }

        $results = $this->wpdb->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial
            FROM {$this->table_name}
            WHERE COALESCE(creation_method, '') <> 'schedule_lifecycle'
                AND NOT (creation_method IS NULL AND template_id IS NULL AND topic_id IS NULL AND post_id IS NULL AND author_id IS NULL)
        ");

        $stats = array(
            'total' => isset($results->total) ? (int) $results->total : 0,
            'completed' => isset($results->completed) ? (int) $results->completed : 0,
            'failed' => isset($results->failed) ? (int) $results->failed : 0,
            'processing' => isset($results->processing) ? (int) $results->processing : 0,
            'partial' => isset($results->partial) ? (int) $results->partial : 0,
        );

        $stats['success_rate'] = $stats['total'] > 0
            ? round(($stats['completed'] / $stats['total']) * 100, 1)
            : 0;

        set_transient('aips_history_stats', $stats, HOUR_IN_SECONDS);

        return $stats;
    }

    /**
     * Get per-day generation counts for the last N days.
     *
     * Returns an array keyed by ISO date string (Y-m-d) where each value is an
     * associative array with 'completed', 'failed', and 'total' counts.
     *
     * @param int $days Number of days to look back. Default 14.
     * @return array Generation counts per day.
     */
    public function get_daily_generation_counts( $days = 14 ) {
        $days  = max( 1, absint( $days ) );
        $start = wp_date( 'Y-m-d', current_time( 'timestamp', true ) - ( ( $days - 1 ) * DAY_IN_SECONDS ), wp_timezone() );

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    DATE(created_at) AS day,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed,
                    COUNT(*) AS total
                 FROM {$this->table_name}
                 WHERE created_at >= %s
                   AND COALESCE(creation_method, '') <> 'schedule_lifecycle'
                   AND NOT (creation_method IS NULL AND template_id IS NULL AND topic_id IS NULL AND post_id IS NULL AND author_id IS NULL)
                 GROUP BY DATE(created_at)
                 ORDER BY day ASC",
                $start
            )
        );

        $data = array();
        foreach ( $results as $row ) {
            $data[ $row->day ] = array(
                'completed' => (int) $row->completed,
                'failed'    => (int) $row->failed,
                'total'     => (int) $row->total,
            );
        }

        return $data;
    }

    /**
     * Get statistics for a specific template.
     *
     * @param int $template_id Template ID.
     * @return int Number of completed posts for this template.
     */
    public function get_template_stats($template_id) {
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE template_id = %d AND status = 'completed'",
            $template_id
        ));
    }

    /**
     * Get statistics for all templates.
     *
     * @return array Associative array of template ID => count.
     */
    public function get_all_template_stats() {
        $results = $this->wpdb->get_results("
            SELECT template_id, COUNT(*) as count
            FROM {$this->table_name}
            WHERE status = 'completed'
            GROUP BY template_id
        ");

        $stats = array();
        foreach ($results as $row) {
            $stats[$row->template_id] = (int) $row->count;
        }

        return $stats;
    }

    /**
     * Get generated-post counts for schedule history containers.
     *
     * Counts activity/error logs that represent a generated post event.
     * The key is history_id (schedule_history_id on schedules table).
     *
     * @param array $history_ids History container IDs.
     * @return array Associative array of history_id => generated count.
     */
    public function get_schedule_generated_post_counts($history_ids) {
        $history_ids = array_map('absint', (array) $history_ids);
        $history_ids = array_filter($history_ids);

        if (empty($history_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($history_ids), '%d'));

        $sql = "
            SELECT history_id, COUNT(*) AS count
            FROM {$this->table_name_log}
            WHERE history_id IN ({$placeholders})
                AND history_type_id IN (%d, %d)
                AND (
                    details LIKE %s
                    OR details LIKE %s
                    OR details LIKE %s
                )
            GROUP BY history_id
        ";

        $args = $history_ids;
        $args[] = AIPS_History_Type::ACTIVITY;
        $args[] = AIPS_History_Type::ERROR;
        $args[] = '%"event_type":"post_published"%';
        $args[] = '%"event_type":"post_draft"%';
        $args[] = '%"event_type":"manual_schedule_completed"%';

        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $args));

        $counts = array();
        foreach ($results as $row) {
            $counts[(int) $row->history_id] = (int) $row->count;
        }

        return $counts;
    }

    /**
     * Get history logs for a specific author and specific event types.
     *
     * @param int   $author_id   Author ID.
     * @param array $event_types Array of event types (e.g., 'topic_generated', 'post_published').
     * @param int   $limit       Maximum number of logs to return. Default 100.
     * @return array Array of log objects.
     */
    public function get_author_schedule_logs_by_event_types($author_id, $event_types = array(), $limit = 100) {
        $author_id = absint($author_id);
        $limit = absint($limit);
        $event_types = array_filter(array_map('sanitize_key', (array) $event_types));

        if (!$author_id || $limit < 1 || empty($event_types)) {
            return array();
        }

        $where_events = array();
        $args = array(
            $author_id,
            AIPS_History_Type::ACTIVITY,
            AIPS_History_Type::ERROR,
        );

        foreach ($event_types as $event_type) {
            $where_events[] = 'hl.details LIKE %s';
            $args[] = '%"event_type":"' . $this->wpdb->esc_like($event_type) . '"%';
        }

        $args[] = $limit;

        $sql = "
            SELECT hl.*
            FROM {$this->table_name_log} hl
            INNER JOIN {$this->table_name} h ON hl.history_id = h.id
            WHERE h.author_id = %d
                AND hl.history_type_id IN (%d, %d)
                AND (" . implode(' OR ', $where_events) . ")
            ORDER BY hl.timestamp DESC
            LIMIT %d
        ";

        return $this->wpdb->get_results($this->wpdb->prepare($sql, $args));
    }

    /**
     * Get activity feed (high-level events)
     *
     * Returns only ACTIVITY type entries for display in activity feed.
     *
     * @param int   $limit   Maximum number of entries to return. Default 50.
     * @param int   $offset  Offset for pagination. Default 0.
     * @param array $filters Optional. Array of filters ('event_type', 'event_status', 'search').
     * @return array         Array of log objects with basic history info joined.
     */
    public function get_activity_feed($limit = 50, $offset = 0, $filters = array()) {
        $where_clauses = array("history_type_id = %d");
        $where_args = array(AIPS_History_Type::ACTIVITY);

        // Event type filter
        if (!empty($filters['event_type'])) {
            $where_clauses[] = "details LIKE %s";
            $where_args[] = '%"event_type":"' . $this->wpdb->esc_like($filters['event_type']) . '"%';
        }

        // Event status filter
        if (!empty($filters['event_status'])) {
            $where_clauses[] = "details LIKE %s";
            $where_args[] = '%"event_status":"' . $this->wpdb->esc_like($filters['event_status']) . '"%';
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search_term = '%' . $this->wpdb->esc_like($filters['search']) . '%';
            $where_clauses[] = "(log_type LIKE %s OR details LIKE %s)";
            $where_args[] = $search_term;
            $where_args[] = $search_term;
        }

        $where_sql = implode(' AND ', $where_clauses);
        $where_args[] = $limit;
        $where_args[] = $offset;

        $sql = "SELECT hl.*, h.post_id, h.template_id
                FROM {$this->table_name_log} hl
                LEFT JOIN {$this->table_name} h ON hl.history_id = h.id
                WHERE $where_sql
                ORDER BY hl.timestamp DESC
                LIMIT %d OFFSET %d";

        if (empty($where_args)) {
            return $this->wpdb->get_results($sql);
        }

        return $this->wpdb->get_results($this->wpdb->prepare($sql, $where_args));
    }

}
