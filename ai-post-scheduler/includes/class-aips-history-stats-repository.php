<?php
if (!defined('ABSPATH')) exit;

class AIPS_History_Stats_Repository {
    use AIPS_Cacheable_Repository;
    private $wpdb;
    private $table_name;
    private $table_name_log;
    private $schedule_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'aips_history';
        $this->table_name_log = $wpdb->prefix . 'aips_history_log';
        $this->schedule_table = $wpdb->prefix . 'aips_schedule';
    }

    /**
     * Count completed generations for a schedule.
     *
     * @param int|object $schedule Schedule ID or schedule object.
     * @return int
     */
    public function count_completed_for_schedule($schedule) {
        if (is_numeric($schedule)) {
            $schedule_id = absint($schedule);
            if (!$schedule_id) {
                return 0;
            }

            $schedule = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT id, template_id FROM {$this->schedule_table} WHERE id = %d",
                $schedule_id
            ));
        } else {
            if (!is_object($schedule) || empty($schedule->id)) {
                return 0;
            }

            $schedule_id = absint($schedule->id);

            if (empty($schedule->template_id)) {
                $schedule = $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT id, template_id FROM {$this->schedule_table} WHERE id = %d",
                    $schedule_id
                ));
            }
        }

        if (!$schedule || empty($schedule->template_id)) {
            return 0;
        }

        return $this->cache_read(
            'history.get_schedule_completed_count',
            array( 'schedule_id' => $schedule_id ),
            function() use ( $schedule, $schedule_id ) {
                return (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name}
                    WHERE template_id = %d
                    AND status = %s
                    AND created_at >= (
                        SELECT created_at FROM {$this->schedule_table} WHERE id = %d
                    )",
                    (int) $schedule->template_id,
                    'completed',
                    $schedule_id
                ));
            }
        );
    }

    /**
     * Invalidate the cached completed-count for a schedule.
     *
     * @param int $schedule_id Schedule ID.
     * @return void
     */
    public function invalidate_schedule_completed_count_cache($schedule_id) {
        $this->invalidate_cache_domain( 'history', array( 'schedule_id' => absint( $schedule_id ) ), 'schedule_count_invalidated' );
    }

    public function get_daily_success_failure_trend($days = 14) {
        $days = max(1, absint($days));

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(FROM_UNIXTIME(created_at)) AS metric_date, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS success_count, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failure_count FROM {$this->table_name} WHERE created_at >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY metric_date ORDER BY metric_date ASC",
            $days
        ), ARRAY_A);
    }

    public function get_average_duration_by_flow($days = 14) {
        $days = max(1, absint($days));

        // completed_at / created_at are UNSIGNED BIGINT timestamps defaulting to 0.
        // Incomplete rows keep completed_at = 0, and `completed_at IS NOT NULL` never
        // filters them because the column is NOT NULL. A bare `completed_at - created_at`
        // then underflows the unsigned type (MySQL error 1690). Guard on
        // completed_at >= created_at so only genuinely finished rows are averaged.
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT COALESCE(NULLIF(creation_method, ''), 'unknown') AS flow_type, AVG(completed_at - created_at) AS avg_duration_seconds, COUNT(*) AS sample_count FROM {$this->table_name} WHERE completed_at >= created_at AND created_at >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY flow_type ORDER BY avg_duration_seconds DESC",
            $days
        ), ARRAY_A);
    }

    public function get_retry_counts_by_service($days = 14) {
        $days = max(1, absint($days));

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details, '$.context')), ''), 'unknown') AS service_key, COUNT(*) AS retry_count FROM {$this->table_name_log} WHERE history_type_id = %d AND JSON_UNQUOTE(JSON_EXTRACT(details, '$.log_subtype')) = %s AND timestamp >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY service_key ORDER BY retry_count DESC",
            AIPS_History_Type::LOG,
            'retry',
            $days
        ), ARRAY_A);
    }

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
     * Get the estimated generation time based on recent successful generations.
     *
     * Retrieves the average of the most recent recorded generation times
     * from post metadata to provide an estimate for bulk generation tasks.
     *
     * @param int $limit Number of recent samples to use for calculation (default: 20).
     * @return array {
     *     @type int $per_post_seconds Estimated seconds per post.
     *     @type int $sample_size      Number of valid samples used for the estimate.
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
                AIPS_Post_Manager::META_POST_GENERATION_TOTAL_TIME,
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

    public function get_stats() {
        return $this->cache_read(
            'history.get_stats',
            array(),
            function() {
                $auxiliary_methods = $this->get_auxiliary_creation_methods();
                $auxiliary_placeholders = implode(', ', array_fill(0, count($auxiliary_methods), '%s'));
                $results = $this->wpdb->get_row(
                    $this->wpdb->prepare(
                        "SELECT
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                            SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial
                         FROM {$this->table_name}
                         WHERE COALESCE(creation_method, '') NOT IN ({$auxiliary_placeholders})
                           AND NOT (creation_method IS NULL AND template_id IS NULL AND topic_id IS NULL AND post_id IS NULL AND author_id IS NULL)",
                        ...$auxiliary_methods
                    )
                );

                $stats = array(
                    'total'      => isset($results->total)      ? (int) $results->total      : 0,
                    'completed'  => isset($results->completed)  ? (int) $results->completed  : 0,
                    'failed'     => isset($results->failed)     ? (int) $results->failed     : 0,
                    'processing' => isset($results->processing) ? (int) $results->processing : 0,
                    'partial'    => isset($results->partial)    ? (int) $results->partial    : 0,
                );

                $stats['success_rate'] = $stats['total'] > 0
                    ? round(($stats['completed'] / $stats['total']) * 100, 1)
                    : 0;

                $durations = $this->wpdb->get_col(
                    $this->wpdb->prepare(
                        "SELECT completed_at - created_at
                         FROM {$this->table_name}
                         WHERE completed_at > 0
                           AND completed_at >= created_at
                           AND COALESCE(creation_method, '') NOT IN ({$auxiliary_placeholders})
                           AND NOT (creation_method IS NULL AND template_id IS NULL AND topic_id IS NULL AND post_id IS NULL AND author_id IS NULL)
                         ORDER BY completed_at - created_at ASC",
                        ...$auxiliary_methods
                    )
                );
                $duration_count = count($durations);
                $stats['median_duration'] = null;
                if ($duration_count > 0) {
                    $middle = intdiv($duration_count, 2);
                    $stats['median_duration'] = $duration_count % 2
                        ? (int) $durations[$middle]
                        : (int) round(((int) $durations[$middle - 1] + (int) $durations[$middle]) / 2);
                }

                return $stats;
            }
        );
    }

    /**
     * Get per-day generation counts for the last N days.
     *
     * Returns an array keyed by ISO date string (Y-m-d) where each value is an
     * associative array with 'completed', 'failed', and 'total' counts.
     * Days with no records are omitted; callers should fill gaps as needed.
     *
     * Applies the same row-exclusion filters as get_stats() so that
     * auxiliary lifecycle rows and empty-shell records are not counted.
     *
     * @param int $days Number of calendar days to look back (inclusive today). Default 14.
     * @return array<string, array{completed: int, failed: int, total: int}>
     */
    public function get_daily_generation_counts( $days = 14 ) {
        $days      = max( 1, absint( $days ) );
        $start_day = AIPS_DateTime::now()->advance( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' );
        $start     = AIPS_DateTime::fromDate( $start_day )->timestamp();

        $auxiliary_methods = $this->get_auxiliary_creation_methods();
        $auxiliary_placeholders = implode(', ', array_fill(0, count($auxiliary_methods), '%s'));
        $query_args = array_merge(array($start), $auxiliary_methods);
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    DATE(FROM_UNIXTIME(created_at)) AS day,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed,
                    COUNT(*) AS total
                 FROM {$this->table_name}
                 WHERE created_at >= %d
                   AND COALESCE(creation_method, '') NOT IN ({$auxiliary_placeholders})
                   AND NOT (creation_method IS NULL AND template_id IS NULL AND topic_id IS NULL AND post_id IS NULL AND author_id IS NULL)
                 GROUP BY DATE(FROM_UNIXTIME(created_at))
                 ORDER BY day ASC",
                ...$query_args
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
     * Return creation_method values used for internal lifecycle containers.
     *
     * @return string[]
     */
    public function get_auxiliary_creation_methods() {
        return array(
            'schedule_lifecycle',
            'template_lifecycle',
            'campaign_lifecycle',
            'notification_sent',
        );
    }
}
