<?php
/**
 * History Stats Repository
 *
 * Database abstraction layer for history statistics and analytics operations.
 * Separated from AIPS_History_Repository to prevent God Object anti-pattern.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    die;
}

/**
 * Class AIPS_History_Stats_Repository
 *
 * Repository pattern implementation for history statistics data access.
 */
class AIPS_History_Stats_Repository {

    /**
     * @var string The history table name (with prefix)
     */
    private $table_name;

    /**
     * @var string The history log table name (with prefix)
     */
    private $table_name_log;

    /**
     * @var wpdb WordPress database abstraction object
     */
    private $wpdb;

    /**
     * Initialize the repository.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_history';
        $this->table_name_log = $wpdb->prefix . 'aips_history_log';
    }

    /**
     * Return estimated generation timing stats.
     *
     * @param int $limit Number of rows to sample.
     * @return array
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
     * Get statistics.
     *
     * @return array
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
     * Days with no records are omitted; callers should fill gaps as needed.
     *
     * Applies the same row-exclusion filters as get_stats() so that
     * schedule-lifecycle rows and empty-shell records are not counted.
     *
     * @param int $days Number of calendar days to look back (inclusive today). Default 14.
     * @return array<string, array{completed: int, failed: int, total: int}>
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
}
