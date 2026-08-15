<?php
/**
 * Operations Insights Repository
 *
 * Database abstraction layer for operations insights operations.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    die;
}

/**
 * Class AIPS_Operations_Insights_Repository
 */
class AIPS_Operations_Insights_Repository {

    /**
     * @var string The history table name (with prefix)
     */
    private $table_name;
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
     * Get the daily success and failure trend.
     *
     * @param int $days Number of days.
     * @return array
     */
    public function get_daily_success_failure_trend($days = 14) {
        $days = max(1, absint($days));

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(FROM_UNIXTIME(created_at)) AS metric_date, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS success_count, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failure_count FROM {$this->table_name} WHERE created_at >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY metric_date ORDER BY metric_date ASC",
            $days
        ), ARRAY_A);
    }

    /**
     * Get average duration by flow.
     *
     * @param int $days Number of days.
     * @return array
     */
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

    /**
     * Get retry counts by service.
     *
     * @param int $days Number of days.
     * @return array
     */
    public function get_retry_counts_by_service($days = 14) {
        $days = max(1, absint($days));

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(details, '$.context')), ''), 'unknown') AS service_key, COUNT(*) AS retry_count FROM {$this->table_name_log} WHERE history_type_id = %d AND JSON_UNQUOTE(JSON_EXTRACT(details, '$.log_subtype')) = %s AND timestamp >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY service_key ORDER BY retry_count DESC",
            AIPS_History_Type::LOG,
            'retry',
            $days
        ), ARRAY_A);
    }

    /**
     * Get top failure reasons.
     *
     * @param int $days Number of days.
     * @param int $limit Limit.
     * @return array
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
}
