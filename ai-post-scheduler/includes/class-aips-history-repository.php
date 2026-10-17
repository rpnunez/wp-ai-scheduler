<?php
/**
 * History Repository
 *
 * Database abstraction layer for history operations.
 * Provides a clean interface for CRUD operations on the history table.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
    require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_History_Repository
 *
 * Repository pattern implementation for history data access.
 * Encapsulates all database operations related to generation history.
 */
class AIPS_History_Repository implements AIPS_History_Repository_Interface {
    use AIPS_Cacheable_Repository;

    /**
     * Default maximum rows collapsed into a single History group.
     */
    const GROUP_MAX_ROWS = 50;

    /**
     * Seconds without activity after which an in-progress run is shown as stalled.
     */
    const STALLED_AFTER_SECONDS = 900;

    /**
     * Seconds without activity after which a long-running chained job (indexing,
     * embeddings, batches) is shown as stalled. These jobs legitimately wait on
     * rate limits and cron slices between log entries.
     */
    const STALLED_AFTER_CHAIN_SECONDS = 21600;

    /**
     * creation_method values that are events or configuration changes rather than
     * generation runs. They never own a generated post's components, so lookups by
     * post must skip them.
     *
     * @return string[]
     */
    public static function event_only_creation_methods() {
        return array_merge(
            AIPS_History_Event_Recorder::lifecycle_creation_methods(),
            array(
                'notification_sent', 'topic_post_generation', 'bulk_delete', 'bulk_delete_feedback',
                'bulk_schedule', 'settings_connection_test', 'stress_test', 'taxonomy_generation',
            )
        );
    }

    /**
     * Placeholders string for the event-only creation methods.
     *
     * @return string e.g. "'a', 'b'" (values are code constants, escaped anyway).
     */
    private static function event_only_in_sql() {
        return self::quoted_sql_list(self::event_only_creation_methods());
    }

    /**
     * Comma-separated, escaped SQL string literals for a list of values.
     *
     * @param string[] $values Values to quote.
     * @return string e.g. "'a', 'b'"
     */
    private static function quoted_sql_list(array $values) {
        return implode(', ', array_map(function ($value) {
            return "'" . esc_sql($value) . "'";
        }, $values));
    }

    /**
     * creation_method values written by real post-generation runs.
     *
     * These classify a row as a generation run when it is written without an explicit
     * record_kind, and during the one-off backfill of existing rows (see
     * AIPS_History_Record_Kind). Queries never use this list: they filter on the stored
     * record_kind through generation_run_condition(). The generator tags every run
     * explicitly, so this list only matters for rows written some other way.
     *
     * 'bulk_generate' is the Planner's per-post run; the bulk summary container shares
     * the value, so the bulk service tags that container 'job' explicitly.
     * 'post_generation' is the run container's own type, which becomes its
     * creation_method when the caller passes none.
     *
     * @return string[]
     */
    public static function generation_run_creation_methods() {
        $methods = array('scheduled', 'manual', 'regenerate', 'retry', 'bulk_generate', 'post_generation', 'author_post_gen');

        /**
         * Filters the creation_method values classified as post-generation runs when a
         * row is written without an explicit record_kind, and by the backfill.
         *
         * It does not change rows that are already stored: after adding a method here,
         * run AIPS_History_Repository::backfill_record_kind() to promote its existing
         * rows (only rows still marked 'event' are touched).
         *
         * @param string[] $methods creation_method values.
         */
        $methods = apply_filters('aips_generation_run_creation_methods', $methods);

        return array_values(array_unique(array_filter(array_map('strval', (array) $methods))));
    }

    /**
     * SQL condition matching one or more record kinds.
     *
     * @param string|string[] $kinds Single kind or array of kinds ('generation', 'job', 'event').
     * @param string          $alias Optional table alias (without the dot).
     * @return string SQL snippet e.g. "record_kind = 'generation'" or "h.record_kind IN ('generation', 'job')".
     */
    public static function kind_condition($kinds, $alias = '') {
        $kinds  = array_values(array_intersect((array) $kinds, AIPS_History_Record_Kind::all()));
        $prefix = ($alias !== '') ? $alias . '.' : '';

        if (empty($kinds)) {
            return '1=0';
        }

        if (count($kinds) === 1) {
            return $prefix . "record_kind = '" . esc_sql($kinds[0]) . "'";
        }

        $in_sql = implode("', '", array_map('esc_sql', $kinds));
        return $prefix . "record_kind IN ('" . $in_sql . "')";
    }

    /**
     * SQL condition matching generation runs: rows whose stored record_kind is
     * 'generation'.
     *
     * The kind is written with the row (see AIPS_History_Record_Kind), so no query has
     * to guess from creation_method names which rows are real post generations.
     *
     * @param string $alias Optional table alias (without the dot).
     * @return string
     */
    public static function generation_run_condition($alias = '') {
        return self::kind_condition(AIPS_History_Record_Kind::GENERATION, $alias);
    }

    /**
     * SQL condition matching background-job rows: content indexing, embeddings, bulk
     * slices, source fetches and the like.
     *
     * Reported separately from the run figures (see get_stats()['jobs']).
     *
     * @param string $alias Optional table alias (without the dot).
     * @return string
     */
    public static function background_job_condition($alias = '') {
        return self::kind_condition(AIPS_History_Record_Kind::JOB, $alias);
    }

    /**
     * Derived table holding the newest completed/partial generation run per post.
     *
     * Join on `latest_history_id = h.id`. Event rows are always newer than the run
     * they describe, so a bare MAX(id) per post would pick them instead.
     *
     * @param array $filters {
     *     Optional filters.
     *
     *     @type string $post_status Restrict to posts with this wp_posts.post_status. The
     *                               newest run per post is unchanged by it; it only keeps
     *                               the aggregate from scanning posts the caller will
     *                               discard anyway.
     *     @type int    $campaign_id Restrict to this campaign. Unlike post_status this
     *                               changes which run is newest: only that campaign's runs
     *                               are considered.
     *     @type int    $post_id     Restrict to this post (an indexed lookup, for
     *                               single-post updates).
     * }
     * @return string Parenthesised sub-select exposing post_id and latest_history_id.
     */
    public static function latest_generation_run_subquery(array $filters = array()) {
        global $wpdb;

        $table       = $wpdb->prefix . 'aips_history';
        $join        = '';
        $post_status = isset($filters['post_status']) ? (string) $filters['post_status'] : '';
        $campaign_id = isset($filters['campaign_id']) ? absint($filters['campaign_id']) : 0;
        $post_id     = isset($filters['post_id']) ? absint($filters['post_id']) : 0;
        if ($post_status !== '') {
            $join = "INNER JOIN {$wpdb->posts} p ON p.ID = r.post_id AND p.post_status = '" . esc_sql($post_status) . "'";
        }
        $campaign_sql = $campaign_id ? "AND r.campaign_id = {$campaign_id}" : '';
        $post_sql     = $post_id ? "AND r.post_id = {$post_id}" : '';

        return "(SELECT r.post_id, MAX(r.id) AS latest_history_id
                FROM {$table} r
                {$join}
                WHERE r.status IN ('completed', 'partial')
                  AND r.post_id IS NOT NULL
                  {$campaign_sql}
                  {$post_sql}
                  AND " . self::generation_run_condition('r') . "
                GROUP BY r.post_id)";
    }

    /**
     * creation_method LIKE patterns of chained, long-running jobs.
     *
     * @return string[]
     */
    public static function chain_method_patterns() {
        return array('%content_index%', '%embedding%', '%batch%', '%internal_link%', '%bulk%');
    }

    /**
     * Whether a creation method belongs to a chained, long-running job.
     *
     * @param string|null $creation_method Raw creation_method.
     * @return bool
     */
    public static function is_chain_method($creation_method) {
        $creation_method = (string) $creation_method;
        foreach (self::chain_method_patterns() as $pattern) {
            if ($creation_method !== '' && strpos($creation_method, trim($pattern, '%')) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inactivity threshold (seconds) after which a processing run counts as stalled.
     *
     * @param string|null $creation_method Raw creation_method.
     * @return int
     */
    public static function stalled_threshold_seconds($creation_method = '') {
        $default = self::is_chain_method($creation_method) ? self::STALLED_AFTER_CHAIN_SECONDS : self::STALLED_AFTER_SECONDS;

        /**
         * Filters how long (seconds) an in-progress History run may go without
         * logged activity before it is shown as Stalled.
         *
         * @param int    $seconds         Threshold in seconds.
         * @param string $creation_method Run creation method.
         */
        return max(60, (int) apply_filters('aips_history_stalled_after_seconds', $default, (string) $creation_method));
    }

    /**
     * Resolve the display status of a run: 'stalled' replaces 'processing' once the
     * run has been idle past its threshold; every other status is returned as is.
     *
     * @param string      $status          Stored status.
     * @param string|null $creation_method Raw creation_method.
     * @param int         $last_activity   Unix time of the newest log entry or the run start.
     * @param int|null    $now             Current time (defaults to now).
     * @return string
     */
    public static function resolve_effective_status($status, $creation_method, $last_activity, $now = null) {
        if ((string) $status !== 'processing') {
            return (string) $status;
        }

        $now = $now === null ? AIPS_DateTime::now()->timestamp() : (int) $now;

        return ($now - (int) $last_activity) >= self::stalled_threshold_seconds($creation_method) ? 'stalled' : 'processing';
    }

    /**
     * @var self|null Singleton instance.
     */
    private static $instance = null;

    /**
     * Get the shared singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @var string The history table name (with prefix)
     */
    private $table_name;
    private $table_name_log;
	private $schedule_table;
    
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
        } elseif (is_object($schedule)) {
            $schedule_id = !empty($schedule->schedule_id)
                ? absint($schedule->schedule_id)
                : (!empty($schedule->id) ? absint($schedule->id) : 0);
        } else {
            $schedule_id = 0;
        }

        if (!$schedule_id) {
            return 0;
        }

        return $this->cache_read(
            'history.get_schedule_completed_count',
            array( 'schedule_id' => $schedule_id ),
            function() use ( $schedule_id ) {
                $generation_cond = self::generation_run_condition();
                $count = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COALESCE(COUNT(*), 0) FROM {$this->table_name}
                    WHERE schedule_id = %d
                    AND status = %s
                    AND {$generation_cond}",
                    $schedule_id,
                    'completed'
                ));

                return max(0, (int) $count);
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

    /**
     * Backfill missing campaign attribution from linked templates.
     *
     * Repairs history rows created before generator-facing template entries
     * started carrying campaign_id forward.
     *
     * @param int $campaign_id Optional campaign ID scope.
     * @return void
     */
    public function repair_missing_campaign_ids($campaign_id = 0) {
        $templates_table = $this->wpdb->prefix . 'aips_templates';
        $campaign_id = absint($campaign_id);

        if ($campaign_id > 0) {
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->table_name} h
                INNER JOIN {$templates_table} t ON h.template_id = t.id
                SET h.campaign_id = t.campaign_id
                WHERE h.campaign_id IS NULL
                AND t.campaign_id = %d",
                $campaign_id
            ));
            return;
        }

        $this->wpdb->query(
            "UPDATE {$this->table_name} h
            INNER JOIN {$templates_table} t ON h.template_id = t.id
            SET h.campaign_id = t.campaign_id
            WHERE h.campaign_id IS NULL
            AND t.campaign_id IS NOT NULL"
        );
    }

    public function get_daily_success_failure_trend($days = 14) {
        $days = max(1, absint($days));
        $generation_cond = self::generation_run_condition();

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(FROM_UNIXTIME(created_at)) AS metric_date, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS success_count, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failure_count FROM {$this->table_name} WHERE {$generation_cond} AND created_at >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY metric_date ORDER BY metric_date ASC",
            $days
        ), ARRAY_A);
    }

    public function get_average_duration_by_flow($days = 14) {
        $days = max(1, absint($days));
        $generation_cond = self::generation_run_condition();

        // completed_at / created_at are UNSIGNED BIGINT timestamps defaulting to 0.
        // Incomplete rows keep completed_at = 0, and `completed_at IS NOT NULL` never
        // filters them because the column is NOT NULL. A bare `completed_at - created_at`
        // then underflows the unsigned type (MySQL error 1690). Guard on
        // completed_at >= created_at so only genuinely finished rows are averaged.
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT COALESCE(NULLIF(creation_method, ''), 'unknown') AS flow_type, AVG(completed_at - created_at) AS avg_duration_seconds, COUNT(*) AS sample_count FROM {$this->table_name} WHERE {$generation_cond} AND completed_at >= created_at AND created_at >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY flow_type ORDER BY avg_duration_seconds DESC",
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
        $generation_cond = self::generation_run_condition();

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT COALESCE(NULLIF(TRIM(error_message), ''), 'Unknown failure') AS reason, COUNT(*) AS failure_count FROM {$this->table_name} WHERE {$generation_cond} AND status = 'failed' AND created_at >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL %d DAY)) GROUP BY reason ORDER BY failure_count DESC LIMIT %d",
            $days,
            $limit
        ), ARRAY_A);
    }

    /**
     * Fetch recent stress-test runs from history.
     *
     * @param int $limit Maximum rows to return.
     * @return array<int, array<string, mixed>>
     */
    public function get_stress_test_runs($limit = 20) {
        $limit = max(1, absint($limit));

        $records = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT h.id, h.uuid, h.status, h.created_at, hl.details
             FROM {$this->table_name} h
             LEFT JOIN {$this->table_name_log} hl ON h.id = hl.history_id AND hl.history_type_id = %d
             WHERE h.creation_method = %s
             ORDER BY h.created_at DESC
             LIMIT %d",
            AIPS_History_Type::SESSION_METADATA,
            'stress_test',
            $limit
        ));

        if (!is_array($records)) {
            return array();
        }

        $runs = array();
        foreach ($records as $row) {
            $details = !empty($row->details) ? json_decode($row->details, true) : array();
            $payload = isset($details['output']) && is_array($details['output'])
                ? $details['output']
                : (isset($details['context']) && is_array($details['context']) ? $details['context'] : $details);
            $totals  = isset($payload['totals']) ? $payload['totals'] : array();
            $env     = isset($payload['environment']) ? $payload['environment'] : array();

            $formatted_date = '';
            if (!empty($row->created_at)) {
                if (is_numeric($row->created_at)) {
                    $formatted_date = AIPS_DateTime::fromTimestamp((int) $row->created_at)->format('M j, Y H:i:s');
                } else {
                    $dt = AIPS_DateTime::fromMysqlOrNull((string) $row->created_at);
                    $formatted_date = $dt ? $dt->format('M j, Y H:i:s') : (string) $row->created_at;
                }
            }

            $runs[] = array(
                'id'             => (int) $row->id,
                'uuid'           => $row->uuid,
                'status'         => $row->status,
                'created_at'     => $row->created_at,
                'formatted_date' => $formatted_date,
                'provider'       => isset($env['provider']) ? $env['provider'] : 'Unknown',
                'model'          => isset($env['model']) ? $env['model'] : '',
                'total_cases'    => isset($totals['cases']) ? (int) $totals['cases'] : 0,
                'passed'         => isset($totals['passed']) ? (int) $totals['passed'] : 0,
                'failed'         => isset($totals['failed']) ? (int) $totals['failed'] : 0,
                'duration_ms'    => isset($totals['duration_ms']) ? (int) $totals['duration_ms'] : 0,
            );
        }

        return $runs;
    }

    /**
     * Fetch one stress-test run by ID.
     *
     * @param int $history_id History row ID.
     * @return array<string, mixed>|null
     */
    public function get_stress_test_run_by_id($history_id) {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT h.id, h.uuid, h.status, h.created_at, hl.details
             FROM {$this->table_name} h
             LEFT JOIN {$this->table_name_log} hl ON h.id = hl.history_id AND hl.history_type_id = %d
             WHERE h.id = %d AND h.creation_method = %s",
            AIPS_History_Type::SESSION_METADATA,
            absint($history_id),
            'stress_test'
        ));

        if (!$row) {
            return null;
        }

        $details = !empty($row->details) ? json_decode($row->details, true) : array();
        $payload = isset($details['output']) && is_array($details['output'])
            ? $details['output']
            : (isset($details['context']) && is_array($details['context']) ? $details['context'] : $details);

        $formatted_date = '';
        if (!empty($row->created_at)) {
            if (is_numeric($row->created_at)) {
                $formatted_date = AIPS_DateTime::fromTimestamp((int) $row->created_at)->format('M j, Y H:i:s');
            } else {
                $dt = AIPS_DateTime::fromMysqlOrNull((string) $row->created_at);
                $formatted_date = $dt ? $dt->format('M j, Y H:i:s') : (string) $row->created_at;
            }
        }

        return array(
            'id'             => (int) $row->id,
            'uuid'           => $row->uuid,
            'status'         => $row->status,
            'created_at'     => $row->created_at,
            'formatted_date' => $formatted_date,
            'environment'    => isset($payload['environment']) ? $payload['environment'] : array(),
            'totals'         => isset($payload['totals']) ? $payload['totals'] : array(),
            'results'        => isset($payload['results']) ? $payload['results'] : array(),
        );
    }
    
        /**
     * Return creation_method values used for internal lifecycle containers.
     *
     * @return string[]
     */
    private function get_auxiliary_creation_methods() {
        return array_merge(
            AIPS_History_Event_Recorder::lifecycle_creation_methods(),
            array('notification_sent')
        );
    }

    /**
     * Creation-method LIKE patterns that define each non-default history domain.
     *
     * @return array<string,string> Domain key => SQL LIKE pattern.
     */
    private function get_domain_patterns() {
        return array(
            'content_indexing' => '%content_index%',
            'author_topics' => 'author_topic%',
            'research' => '%research%',
            'sources' => '%source%',
            'embeddings' => '%embedding%',
            'internal_links' => '%internal_link%',
            'batch_jobs' => '%batch%',
            'post_review' => 'post_review_action',
        );
    }

    /**
     * SQL condition that is true for a processing run that has gone idle.
     *
     * Idle means no log entry (falling back to created_at) within the run's
     * threshold. The threshold comes from stalled_threshold_seconds() for each
     * creation method currently in progress, so the SQL (filter, stats, queue
     * health) and the per-row badge share one definition, including any
     * aips_history_stalled_after_seconds override. Placeholders and args are
     * returned together so callers can splice the condition into any clause.
     *
     * @param string $alias History table alias.
     * @return array{0:string,1:array} Tuple of [sql, args].
     */
    private function stalled_condition($alias = 'h') {
        $now           = AIPS_DateTime::now()->timestamp();
        $last_activity = "GREATEST({$alias}.created_at, COALESCE((SELECT MAX(sl.timestamp) FROM {$this->table_name_log} sl WHERE sl.history_id = {$alias}.id), 0))";

        $methods = $this->wpdb->get_col("SELECT DISTINCT COALESCE(creation_method, '') FROM {$this->table_name} WHERE status = 'processing'");

        $cases = '';
        $args  = array();
        foreach ((array) $methods as $method) {
            if ($method === '') {
                continue;
            }
            $cases .= ' WHEN %s THEN %d';
            $args[] = (string) $method;
            $args[] = $now - self::stalled_threshold_seconds($method);
        }
        $args[] = $now - self::stalled_threshold_seconds('');

        // With no in-progress method to special-case, a CASE without any WHEN is a SQL
        // syntax error, so fall back to the default threshold alone.
        $threshold = $cases === ''
            ? '%d'
            : "CASE COALESCE({$alias}.creation_method, ''){$cases} ELSE %d END";

        $sql = "({$alias}.status = 'processing' AND {$last_activity} < {$threshold})";

        return array($sql, $args);
    }

    /**
     * Attach last_activity_at and effective_status to rows.
     *
     * Only processing rows need a lookup, so this stays cheap on a paginated page.
     *
     * @param array $rows History rows (modified in place).
     * @return void
     */
    private function attach_effective_status(array $rows) {
        $processing_ids = array();
        foreach ($rows as $row) {
            $row->effective_status = isset($row->status) ? (string) $row->status : '';
            if ($row->effective_status === 'processing') {
                $processing_ids[] = (int) $row->id;
            }
        }

        if (empty($processing_ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($processing_ids), '%d'));
        $activity     = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT history_id, MAX(timestamp) AS last_log FROM {$this->table_name_log} WHERE history_id IN ({$placeholders}) GROUP BY history_id",
            $processing_ids
        ), OBJECT_K);

        $now = AIPS_DateTime::now()->timestamp();
        foreach ($rows as $row) {
            if ($row->effective_status !== 'processing') {
                continue;
            }
            $last_log              = isset($activity[(int) $row->id]) ? (int) $activity[(int) $row->id]->last_log : 0;
            $row->last_activity_at = max((int) $row->created_at, $last_log);
            $row->effective_status = self::resolve_effective_status('processing', isset($row->creation_method) ? $row->creation_method : '', $row->last_activity_at, $now);
        }
    }

    /**
     * Build the shared WHERE clause for history listing queries.
     *
     * @param array $args            Normalised get_history() arguments.
     * @param array $domain_patterns Domain key => LIKE pattern map.
     * @return array{0:string,1:array} Tuple of [where_sql, where_args].
     */
    private function build_history_where($args, $domain_patterns) {
        // Build where clauses
        $where_clauses = array("1=1");
        $where_args = array();

        if (!empty($args['record_kind'])) {
            $where_clauses[] = self::kind_condition($args['record_kind'], 'h');
        } elseif (!empty($args['domain']) && sanitize_key($args['domain']) === 'post_review') {
            // Explicit post review event domain requested.
        } elseif (!empty($args['creation_method']) && in_array($args['creation_method'], AIPS_History_Record_Kind::event_creation_methods(), true)) {
            // Explicit event creation method filter requested.
        } else {
            // Default list hides all event rows (lifecycle containers, notifications, review actions, wrappers).
            $where_clauses[] = self::kind_condition(array(AIPS_History_Record_Kind::GENERATION, AIPS_History_Record_Kind::JOB), 'h');
        }
        // Keep excluding legacy orphaned containers that have no contextual linkage.
        $where_clauses[] = "NOT (h.creation_method IS NULL AND h.template_id IS NULL AND h.schedule_id IS NULL AND h.topic_id IS NULL AND h.post_id IS NULL AND h.author_id IS NULL)";

        if (!empty($args['status'])) {
            if ($args['status'] === 'completed') {
                $where_clauses[] = "(h.status = %s OR h.status = 'indexed')";
                $where_args[] = 'completed';
            } elseif ($args['status'] === 'in_progress') {
                // Raw processing rows, stalled or not (used to resume an open container).
                $where_clauses[] = "h.status = 'processing'";
            } elseif ($args['status'] === 'generated') {
                // Runs that produced output: completed, partial, or indexed.
                $where_clauses[] = "h.status IN ('completed', 'partial', 'indexed')";
            } elseif ($args['status'] === 'stalled') {
                list($stalled_sql, $stalled_args) = $this->stalled_condition('h');
                $where_clauses[] = $stalled_sql;
                $where_args      = array_merge($where_args, $stalled_args);
            } elseif ($args['status'] === 'processing') {
                // A run that has gone idle is Stalled, not Processing.
                list($stalled_sql, $stalled_args) = $this->stalled_condition('h');
                $where_clauses[] = "(h.status = 'processing' AND NOT {$stalled_sql})";
                $where_args      = array_merge($where_args, $stalled_args);
            } else {
                $where_clauses[] = "h.status = %s";
                $where_args[] = $args['status'];
            }
        }

        if (!empty($args['creation_method'])) {
            // Legacy rows may have an empty creation_method; they still match any type.
            $where_clauses[] = "(h.creation_method = %s OR COALESCE(h.creation_method, '') = '')";
            $where_args[] = (string) $args['creation_method'];
        }

        if (!empty($args['template_id'])) {
            $where_clauses[] = "h.template_id = %d";
            $where_args[] = $args['template_id'];
        }

        if (!empty($args['schedule_id'])) {
            $where_clauses[] = "h.schedule_id = %d";
            $where_args[] = absint($args['schedule_id']);
        }

        if (!empty($args['campaign_id'])) {
            $where_clauses[] = "h.campaign_id = %d";
            $where_args[] = $args['campaign_id'];
        }

        if (!empty($args['author_id'])) {
            $where_clauses[] = "h.author_id = %d";
            $where_args[] = $args['author_id'];
        }

        if (!empty($args['post_type'])) {
            $where_clauses[] = "h.post_type = %s";
            $where_args[] = sanitize_key($args['post_type']);
        }

        if (!empty($args['correlation_id'])) {
            $where_clauses[] = "h.correlation_id = %s";
            $where_args[] = sanitize_text_field((string) $args['correlation_id']);
        }

        if (!empty($args['domain'])) {
            $domain = sanitize_key($args['domain']);

            if (isset($domain_patterns[$domain])) {
                $where_clauses[] = "COALESCE(h.creation_method, '') LIKE %s";
                $where_args[] = $domain_patterns[$domain];
            } elseif ($domain === 'generation_runs') {
                // Exactly the rows the run statistics count.
                $where_clauses[] = self::generation_run_condition('h');
            } elseif ($domain === 'background_jobs') {
                $where_clauses[] = self::background_job_condition('h');
            } elseif ($domain === 'post_generation') {
                $post_generation_clauses = array();
                foreach ($domain_patterns as $pattern) {
                    $post_generation_clauses[] = "COALESCE(h.creation_method, '') LIKE %s";
                    $where_args[] = $pattern;
                }
                $where_clauses[] = 'NOT (' . implode(' OR ', $post_generation_clauses) . ')';
            }
        }

        if (!empty($args['actor'])) {
            $actor = sanitize_key($args['actor']);

            if ($actor === 'admin') {
                $where_clauses[] = "(COALESCE(h.creation_method, '') LIKE %s OR COALESCE(h.creation_method, '') LIKE %s)";
                $where_args[] = '%manual%';
                $where_args[] = '%admin%';
            } elseif ($actor === 'system') {
                $where_clauses[] = "(COALESCE(h.creation_method, '') NOT LIKE %s AND COALESCE(h.creation_method, '') NOT LIKE %s)";
                $where_args[] = '%manual%';
                $where_args[] = '%admin%';
            }
        }

        if (!empty($args['date_from'])) {
            $date_from = sanitize_text_field($args['date_from']);
            $date_from_ts = strtotime($date_from . ' 00:00:00');

            if ($date_from_ts !== false) {
                $where_clauses[] = "h.created_at >= %d";
                $where_args[] = $date_from_ts;
            }
        }

        if (!empty($args['date_to'])) {
            $date_to = sanitize_text_field($args['date_to']);
            $date_to_ts = strtotime($date_to . ' 23:59:59');

            if ($date_to_ts !== false) {
                $where_clauses[] = "h.created_at <= %d";
                $where_args[] = $date_to_ts;
            }
        }

        if (!empty($args['search'])) {
            $where_clauses[] = "h.generated_title LIKE %s";
            $where_args[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }

        $where_sql = implode(' AND ', $where_clauses);

        return array($where_sql, $where_args);
    }

    /**
     * Get paginated history with optional filtering.
     *
     * @param array $args {
     *     Optional. Query arguments.
     *
     *     @type int    $per_page    Number of items per page. Default 20.
     *     @type int    $page        Current page number. Default 1.
     *     @type string $status      Filter by status. Default empty.
     *     @type string $search      Search term for title. Default empty.
     *     @type int    $template_id Filter by template ID. Default 0.
     *     @type string $orderby     Column to order by. Default 'created_at'.
     *     @type string $order       Order direction (ASC/DESC). Default 'DESC'.
     *     @type bool   $collapse    Paginate by display entries instead of rows: adjacent rows with the
     *                               same creation method form one entry (a group), so a page holds
     *                               `per_page` entries. Default false (row-based LIMIT/OFFSET).
     *     @type int    $group_max_rows Maximum rows per collapsed group (min 2). Default GROUP_MAX_ROWS.
     * }
     * @return array {
     *     @type array $items         Array of history items (every row on the page).
     *     @type int   $total         Total number of rows matching the filters.
     *     @type int   $pages         Total number of pages (entry-based when collapsing).
     *     @type int   $current_page  Current page number.
     *     @type int   $total_entries Total display entries. Only present when collapsing.
     *     @type int   $group_max_rows Normalised group cap used. Only present when collapsing.
     * }
     */
    public function get_history($args = array()) {
        $defaults = array(
            'per_page' => 50,
            'page' => 1,
            'status' => '',
            'search' => '',
            'template_id' => 0,
            'schedule_id' => 0,
            'campaign_id' => 0,
            'author_id' => 0,
            'creation_method' => '',
            'domain' => '',
            'record_kind' => '',
            'actor' => '',
            'post_type' => '',
            'correlation_id' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'fields' => 'all',
            'collapse' => false,
            'group_max_rows' => self::GROUP_MAX_ROWS,
        );

        $args = wp_parse_args($args, $defaults);
        $args['per_page'] = max(1, (int) $args['per_page']);
        $args['page'] = max(1, (int) $args['page']);
        $args['group_max_rows'] = max(2, (int) $args['group_max_rows']);

        $offset = ($args['page'] - 1) * $args['per_page'];

        $domain_patterns = $this->get_domain_patterns();

        $event_domain_case_parts = array('CASE');
        foreach ($domain_patterns as $domain_key => $domain_pattern) {
            $event_domain_case_parts[] = sprintf(
                "WHEN COALESCE(h.creation_method, '') LIKE '%s' THEN '%s'",
                esc_sql($domain_pattern),
                esc_sql($domain_key)
            );
        }
        $event_domain_case_parts[] = "ELSE 'post_generation'";
        $event_domain_case_parts[] = 'END';
        $event_domain_case_sql = implode("\n", $event_domain_case_parts);
        $event_label_case_sql = "CASE
                WHEN h.generated_title IS NOT NULL AND h.generated_title <> '' THEN h.generated_title
                WHEN h.topic_id IS NOT NULL THEN CONCAT('Topic #', h.topic_id)
                WHEN h.post_id IS NOT NULL THEN CONCAT('Post #', h.post_id)
                WHEN COALESCE(h.creation_method, '') LIKE '%content_index%' THEN 'Content Indexing'
                ELSE 'Generation Event'
            END";
        $actor_type_case_sql = "CASE
                WHEN COALESCE(h.creation_method, '') LIKE '%manual%' OR COALESCE(h.creation_method, '') LIKE '%admin%' THEN 'admin'
                ELSE 'system'
            END";

        // Build select fields
        if ($args['fields'] === 'list') {
            $fields_sql = "h.id, h.uuid, h.correlation_id, h.post_id, h.post_type, h.template_id, h.schedule_id, h.campaign_id, h.author_id, h.topic_id, h.status, h.generated_title, h.created_at, h.error_message, h.completed_at, h.creation_method,
                {$event_domain_case_sql} AS event_domain,
                {$event_label_case_sql} AS event_label,
                {$actor_type_case_sql} AS actor_type,
                t.name as template_name,
                CASE WHEN h.completed_at > 0 AND h.completed_at >= h.created_at THEN h.completed_at - h.created_at ELSE NULL END AS duration_seconds";
        } elseif ($args['fields'] === 'all') {
            // Include longtext fields only when 'all' is explicitly requested or defaulted to, to prevent breaking changes
            $fields_sql = "h.id, h.uuid, h.correlation_id, h.post_id, h.post_type, h.template_id, h.schedule_id, h.campaign_id, h.status, h.generated_title, h.error_message, h.created_at, h.completed_at, h.author_id, h.topic_id, h.creation_method, h.prompt, h.generated_content, h.generation_log,
                {$event_domain_case_sql} AS event_domain,
                {$event_label_case_sql} AS event_label,
                {$actor_type_case_sql} AS actor_type,
                t.name as template_name";
        } else {
            // For specifically 'performance' or any other restricted fields
            $fields_sql = "h.id, h.uuid, h.correlation_id, h.post_id, h.post_type, h.template_id, h.schedule_id, h.campaign_id, h.status, h.generated_title, h.error_message, h.created_at, h.completed_at, h.author_id, h.topic_id, h.creation_method, h.prompt, t.name as template_name";
        }

        list($where_sql, $where_args) = $this->build_history_where($args, $domain_patterns);

        // Validate orderby and order
        $orderby = in_array($args['orderby'], array('created_at', 'completed_at', 'status')) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $templates_table = $this->wpdb->prefix . 'aips_templates';

        // Query for items
        $collapse_plan = null;
        if (!empty($args['collapse'])) {
            // Entry-based pagination: a page is `per_page` display entries (collapsed groups or single rows),
            // so the rows to hydrate are picked by the planner rather than by LIMIT/OFFSET.
            $collapse_plan = $this->plan_collapsed_page($where_sql, $where_args, $orderby, $order, $args);
            $page_ids = $collapse_plan['ids'];
            if (empty($page_ids)) {
                $results = array();
            } else {
                $ids_in = implode(',', array_fill(0, count($page_ids), '%d'));
                $results = $this->wpdb->get_results($this->wpdb->prepare("
                    SELECT $fields_sql
                    FROM {$this->table_name} h
                    LEFT JOIN {$templates_table} t ON h.template_id = t.id
                    WHERE h.id IN ({$ids_in})
                    ORDER BY h.$orderby $order, h.id $order
                ", $page_ids));
            }
        } else {
            $query_args = $where_args;
            $query_args[] = $args['per_page'];
            $query_args[] = $offset;

            $results = $this->wpdb->get_results($this->wpdb->prepare("
                SELECT $fields_sql
                FROM {$this->table_name} h
                LEFT JOIN {$templates_table} t ON h.template_id = t.id
                WHERE $where_sql
                ORDER BY h.$orderby $order, h.id $order
                LIMIT %d OFFSET %d
            ", $query_args));
        }

        // If 'list' fields were requested, attach aggregated log statistics for the paginated slice only.
        // This replaces the full-table log aggregation subquery that previously caused severe performance degradation.
        if (!empty($results) && $args['fields'] === 'list') {
            $history_ids = array_map('intval', wp_list_pluck($results, 'id'));
            if (!empty($history_ids)) {
                $history_ids_in = implode(',', array_fill(0, count($history_ids), '%d'));
                $log_stats = $this->wpdb->get_results($this->wpdb->prepare("
                    SELECT history_id,
                        SUM(CASE WHEN history_type_id = 3 THEN 1 ELSE 0 END) AS warning_count,
                        SUM(CASE WHEN history_type_id = 2 THEN 1 ELSE 0 END) AS error_count,
                        SUM(CASE WHEN history_type_id = 5 THEN 1 ELSE 0 END) AS ai_call_count,
                        LEFT(SUBSTRING_INDEX(GROUP_CONCAT(details ORDER BY timestamp DESC SEPARATOR '||'), '||', 1), 180) AS latest_message
                    FROM {$this->table_name_log}
                    WHERE history_id IN ({$history_ids_in})
                    GROUP BY history_id
                ", $history_ids), OBJECT_K);

                foreach ($results as $item) {
                    $hid = (int) $item->id;
                    if (isset($log_stats[$hid])) {
                        $item->warning_count  = (int) $log_stats[$hid]->warning_count;
                        $item->error_count    = (int) $log_stats[$hid]->error_count;
                        $item->ai_call_count  = (int) $log_stats[$hid]->ai_call_count;
                        $item->latest_message = $log_stats[$hid]->latest_message;
                    } else {
                        $item->warning_count  = 0;
                        $item->error_count    = 0;
                        $item->ai_call_count  = 0;
                        $item->latest_message = null;
                    }
                }
            }
        }

        if (!empty($results)) {
            $this->attach_effective_status($results);
        }

        if ($collapse_plan !== null) {
            return array(
                'items' => $results,
                'total' => $collapse_plan['total_rows'],
                'total_entries' => $collapse_plan['total_entries'],
                'pages' => (int) ceil($collapse_plan['total_entries'] / $args['per_page']),
                'current_page' => $args['page'],
                'group_max_rows' => (int) $args['group_max_rows'],
            );
        }

        // Query for total count
        if (!empty($where_args)) {
            $total = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} h WHERE $where_sql",
                $where_args
            ));
        } else {
            $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} h WHERE $where_sql");
        }

        return array(
            'items' => $results,
            'total' => (int) $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        );
    }

    /**
     * Normalise a creation method to the key used for grouping.
     *
     * Mirrors the SQL expression COALESCE(NULLIF(creation_method, ''), 'post_generation').
     *
     * @param mixed $method Raw creation_method value.
     * @return string
     */
    public static function normalize_method_key($method) {
        $method = (string) $method;
        return $method === '' ? 'post_generation' : $method;
    }

    /**
     * Split an ordered list of creation-method keys into collapsed-entry sizes.
     *
     * Adjacent rows sharing a key form a run. Runs are chunked into groups of at
     * most $max_rows; a chunk of 1 is a single (ungrouped) entry. This is the one
     * definition of "what is an entry" shared by pagination and rendering.
     *
     * @param string[] $keys     Normalised creation-method keys in display order.
     * @param int      $max_rows Maximum rows per collapsed group (min 2).
     * @return int[] Row count of each entry, in display order.
     */
    public static function plan_entry_sizes(array $keys, $max_rows = self::GROUP_MAX_ROWS) {
        $max_rows = max(2, (int) $max_rows);
        $sizes    = array();
        $run      = 0;
        $prev     = null;

        foreach ($keys as $key) {
            if ($prev !== null && $key !== $prev) {
                self::push_run_sizes($sizes, $run, $max_rows);
                $run = 0;
            }
            $run++;
            $prev = $key;
        }
        if ($run > 0) {
            self::push_run_sizes($sizes, $run, $max_rows);
        }

        return $sizes;
    }

    /**
     * Append the chunk sizes for one run of identical rows.
     *
     * @param int[] $sizes    Sizes accumulator (by reference).
     * @param int   $run      Run length.
     * @param int   $max_rows Chunk cap.
     * @return void
     */
    private static function push_run_sizes(array &$sizes, $run, $max_rows) {
        while ($run > 0) {
            $chunk   = min($run, $max_rows);
            $sizes[] = $chunk;
            $run    -= $chunk;
        }
    }

    /**
     * Work out which history rows belong on a collapsed page.
     *
     * Scans only (id, normalised creation method) for the filtered set, builds the
     * entry plan, and returns the ids of the rows covered by the requested page of
     * `per_page` entries.
     *
     * @param string $where_sql  Shared WHERE clause.
     * @param array  $where_args Placeholder values for the WHERE clause.
     * @param string $orderby    Validated order column.
     * @param string $order      Validated direction.
     * @param array  $args       Normalised get_history() arguments.
     * @return array{ids:int[],total_rows:int,total_entries:int}
     */
    private function plan_collapsed_page($where_sql, array $where_args, $orderby, $order, array $args) {
        $sql = "SELECT h.id, COALESCE(NULLIF(h.creation_method, ''), 'post_generation') AS method_key
            FROM {$this->table_name} h
            WHERE $where_sql
            ORDER BY h.$orderby $order, h.id $order";
        if (!empty($where_args)) {
            $sql = $this->wpdb->prepare($sql, $where_args);
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_N);
        $rows = is_array($rows) ? $rows : array();

        // Split into parallel id/key lists and free the row objects early: this scan
        // covers every matching row, so avoid holding both representations.
        $ids_all = array();
        $keys    = array();
        foreach ($rows as $row) {
            $ids_all[] = (int) $row[0];
            $keys[]    = (string) $row[1];
        }
        unset($rows);

        $sizes     = self::plan_entry_sizes($keys, (int) $args['group_max_rows']);
        unset($keys);
        $per_page  = (int) $args['per_page'];
        $first     = ((int) $args['page'] - 1) * $per_page;
        $row_start = (int) array_sum(array_slice($sizes, 0, $first));
        $row_count = (int) array_sum(array_slice($sizes, $first, $per_page));

        return array(
            'ids' => array_slice($ids_all, $row_start, $row_count),
            'total_rows' => count($ids_all),
            'total_entries' => count($sizes),
        );
    }


    /**
     * Get paginated posts whose latest completed generation is incomplete.
     *
     * @param array $args {
     *     Optional. Query arguments.
     *
     *     @type int    $per_page    Number of items per page. Default 20.
     *     @type int    $page        Current page number. Default 1.
     *     @type string $search      Search term for title. Default empty.
     *     @type int    $template_id Filter by template ID. Default 0.
     *     @type int    $author_id   Filter by author ID. Default 0.
     *     @type string $orderby     Column to order by. Default 'created_at'.
     *     @type string $order       Order direction (ASC/DESC). Default 'DESC'.
     * }
     * @return array {
     *     @type array $items        Array of partial generation items.
     *     @type int   $total        Total number of items.
     *     @type int   $pages        Total number of pages.
     *     @type int   $current_page Current page number.
     * }
     */
    public function get_partial_generations($args = array()) {
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'search' => '',
            'template_id' => 0,
            'campaign_id' => 0,
            'author_id' => 0,
            'post_type' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);
        $args['page'] = max(1, (int) $args['page']);
        $args['per_page'] = (int) $args['per_page'];

        $use_limit = $args['per_page'] > 0;
        $offset = $use_limit ? (($args['page'] - 1) * $args['per_page']) : 0;

        $where_clauses = array(
            "h.status IN ('completed', 'partial')",
            "h.post_id IS NOT NULL",
            "(pm_incomplete.meta_value = 'true' OR pm_had_partial.meta_value = 'true')",
        );
        $where_args = array();

        if (!empty($args['template_id'])) {
            $where_clauses[] = 'h.template_id = %d';
            $where_args[] = $args['template_id'];
        }

        if (!empty($args['campaign_id'])) {
            $where_clauses[] = 'h.campaign_id = %d';
            $where_args[] = $args['campaign_id'];
        }

        if (!empty($args['author_id'])) {
            $where_clauses[] = 'h.author_id = %d';
            $where_args[] = $args['author_id'];
        }

        if (!empty($args['post_type'])) {
            // The post is authoritative: history rows written before the column existed have NULL.
            $where_clauses[] = 'p.post_type = %s';
            $where_args[] = sanitize_key($args['post_type']);
        }

        if (!empty($args['search'])) {
            $where_clauses[] = '(h.generated_title LIKE %s OR p.post_title LIKE %s)';
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where_args[] = $search_term;
            $where_args[] = $search_term;
        }

        $where_sql = implode(' AND ', $where_clauses);
        $orderby = in_array($args['orderby'], array('created_at', 'completed_at', 'post_title', 'post_modified', 'post_status'), true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        if ($orderby === 'post_title') {
            $orderby_sql = "p.post_title $order";
        } elseif ($orderby === 'post_modified' || $orderby === 'post_status') {
            $orderby_sql = "p.$orderby $order";
        } else {
            $orderby_sql = "h.$orderby $order";
        }

        $templates_table = $this->wpdb->prefix . 'aips_templates';
        $posts_table = $this->wpdb->posts;
        $postmeta_table = $this->wpdb->postmeta;
        // Newest generation run per post: event rows are always newer than the run they describe.
        $latest_run = self::latest_generation_run_subquery();

        $query = "SELECT
                h.*,
                t.name as template_name,
                p.post_title,
                p.post_status,
                p.post_modified,
                p.post_date,
                pm_incomplete.meta_value as is_currently_incomplete,
                pm_status.meta_value as component_statuses
            FROM {$this->table_name} h
            INNER JOIN {$latest_run} latest ON latest.latest_history_id = h.id
            INNER JOIN {$posts_table} p ON h.post_id = p.ID
            LEFT JOIN {$postmeta_table} pm_incomplete ON pm_incomplete.post_id = p.ID AND pm_incomplete.meta_key = '" . AIPS_Post_Manager::META_GENERATION_INCOMPLETE . "'
            LEFT JOIN {$postmeta_table} pm_had_partial ON pm_had_partial.post_id = p.ID AND pm_had_partial.meta_key = '" . AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL . "'
            LEFT JOIN {$postmeta_table} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = '" . AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES . "'
            LEFT JOIN {$templates_table} t ON h.template_id = t.id
            WHERE $where_sql
                ORDER BY $orderby_sql";

        $query_args = $where_args;

        if ($use_limit) {
            $query .= ' LIMIT %d OFFSET %d';
            $query_args[] = $args['per_page'];
            $query_args[] = $offset;
        }

        if (!empty($query_args)) {
            $results = $this->wpdb->get_results($this->wpdb->prepare($query, $query_args));
        } else {
            $results = $this->wpdb->get_results($query);
        }

        if (!empty($where_args)) {
            $total = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$this->table_name} h
                INNER JOIN {$latest_run} latest ON latest.latest_history_id = h.id
                INNER JOIN {$posts_table} p ON h.post_id = p.ID
                LEFT JOIN {$postmeta_table} pm_incomplete ON pm_incomplete.post_id = p.ID AND pm_incomplete.meta_key = '" . AIPS_Post_Manager::META_GENERATION_INCOMPLETE . "'
                LEFT JOIN {$postmeta_table} pm_had_partial ON pm_had_partial.post_id = p.ID AND pm_had_partial.meta_key = '" . AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL . "'
                WHERE $where_sql",
                $where_args
            ));
        } else {
            $total = $this->wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$this->table_name} h
                INNER JOIN {$latest_run} latest ON latest.latest_history_id = h.id
                INNER JOIN {$posts_table} p ON h.post_id = p.ID
                LEFT JOIN {$postmeta_table} pm_incomplete ON pm_incomplete.post_id = p.ID AND pm_incomplete.meta_key = '" . AIPS_Post_Manager::META_GENERATION_INCOMPLETE . "'
                LEFT JOIN {$postmeta_table} pm_had_partial ON pm_had_partial.post_id = p.ID AND pm_had_partial.meta_key = '" . AIPS_Post_Manager::META_GENERATION_HAD_PARTIAL . "'
                WHERE $where_sql"
            );
        }

        return array(
            'items' => $results,
            'total' => (int) $total,
            'pages' => $use_limit ? (int) ceil($total / $args['per_page']) : ($total > 0 ? 1 : 0),
            'current_page' => $args['page'],
        );
    }

  
    /**
     * Get a single history item by ID.
     *
     * @param int $id History item ID.
     * @return object|null History item or null if not found.
     */
    public function get_by_id($id) {
        $history = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));

        if ($history) {
            $history->log = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->table_name_log} WHERE history_id = %d ORDER BY timestamp ASC, id ASC",
                $id
            ));

            $last_log = !empty($history->log) ? (int) end($history->log)->timestamp : 0;
            $history->last_activity_at = max((int) $history->created_at, $last_log);
            $history->effective_status = self::resolve_effective_status($history->status, isset($history->creation_method) ? $history->creation_method : '', $history->last_activity_at);
        }

        return $history;
    }
    
    /**
     * Check if a post has a completed history entry.
     *
     * @param int $post_id The post ID to check.
     * @return bool True if the post has a completed history entry, false otherwise.
     */
    public function post_has_history_and_completed($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return false;
        }

        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
             WHERE post_id = %d AND status IN ('completed', 'partial')
               AND " . self::generation_run_condition(),
            $post_id
        ));
        
        return (bool) $count;
    }
    
    /**
     * Get history record by post ID.
     *
     * Selects only metadata columns (excludes the `generated_content` and
     * `generation_log` longtext columns) since callers only need the record ID
     * and lightweight metadata fields. Use get_by_id() when full content is required.
     *
     * @param int $post_id The post ID to find.
     * @return object|null History record or null if not found.
     */
    public function get_by_post_id($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return null;
        }

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id, uuid, correlation_id, post_id, template_id, campaign_id, author_id, topic_id, schedule_id,
                    creation_method, status, generated_title, error_message,
                    created_at, completed_at, record_kind
             FROM {$this->table_name}
             WHERE post_id = %d AND " . self::generation_run_condition() . "
             ORDER BY created_at DESC, id DESC LIMIT 1",
            $post_id
        ));
    }
    
    /**
     * Add a log entry to a history item.
     *
     * @param int          $history_id      The ID of the history item.
     * @param array|string $details         The details of the log entry (include 'log_subtype' for semantic label).
     * @param int|null     $history_type_id Optional. History type constant from AIPS_History_Type. Default AIPS_History_Type::LOG.
     * @return int|false The inserted ID on success, false on failure.
     */
    public function add_log_entry($history_id, $details, $history_type_id = null) {
        if ($history_type_id === null) {
            $history_type_id = AIPS_History_Type::LOG;
        }

        $insert_data = array(
            'history_id'      => $history_id,
            'history_type_id' => $history_type_id,
            'details'         => wp_json_encode($details),
            'timestamp'       => AIPS_DateTime::now()->timestamp(),
        );

        $format = array('%d', '%d', '%s', '%d');

        // Mirror the event identity into indexed columns so consumers can filter
        // on event_type / event_status without LIKE-scanning the serialized
        // details blob. The canonical source of truth remains details.input;
        // these columns are a best-effort denormalized projection of it. Guard
        // on column existence so a database that has not yet applied the 3.5.0
        // schema (e.g. a failed/retrying upgrade) still records the log entry —
        // the backfill migration populates the columns once the schema catches up.
        if ($this->history_log_has_event_columns()) {
            list($event_type, $event_status) = $this->extract_event_identity($details);
            $insert_data['event_type']   = $event_type;
            $insert_data['event_status'] = $event_status;
            $format[] = '%s';
            $format[] = '%s';
        }

        $result = $this->wpdb->insert($this->table_name_log, $insert_data, $format);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Whether the history-log table carries the indexed event_type / event_status
     * columns introduced in 3.5.0.
     *
     * Resolved once per request and memoized: a lightweight guard so log writes
     * never fail against a database that has not yet applied the 3.5.0 schema.
     *
     * @var bool|null
     */
    private $history_log_has_event_columns = null;

    /**
     * Determine (and memoize) whether the indexed event columns exist.
     *
     * @return bool
     */
    private function history_log_has_event_columns() {
        if ($this->history_log_has_event_columns !== null) {
            return $this->history_log_has_event_columns;
        }

        $column = $this->wpdb->get_var($this->wpdb->prepare(
            "SHOW COLUMNS FROM `{$this->table_name_log}` LIKE %s",
            'event_type'
        ));

        $this->history_log_has_event_columns = ($column === 'event_type');

        return $this->history_log_has_event_columns;
    }

    /**
     * Extract the event_type / event_status identity from a log details array.
     *
     * The identity is written by producers into the `input` block (canonically
     * via AIPS_History_Event). Returns raw stored values (not canonicalized) so
     * the indexed columns faithfully mirror the serialized payload; alias
     * resolution happens on read.
     *
     * @param array|string $details Log details.
     * @return array{0: string|null, 1: string|null} [event_type, event_status]
     */
    private function extract_event_identity($details) {
        if (!is_array($details) || !isset($details['input']) || !is_array($details['input'])) {
            return array(null, null);
        }

        $input = $details['input'];

        $event_type = isset($input['event_type']) && $input['event_type'] !== ''
            ? substr(sanitize_text_field((string) $input['event_type']), 0, 64)
            : null;
        $event_status = isset($input['event_status']) && $input['event_status'] !== ''
            ? substr(sanitize_text_field((string) $input['event_status']), 0, 32)
            : null;

        return array($event_type, $event_status);
    }

    /**
     * Get overall statistics for history.
     *
     * @return array {
     *     @type int   $total        Total number of items.
     *     @type int   $completed    Number of completed items.
     *     @type int   $failed       Number of failed items.
     *     @type int   $processing   Number of processing items.
     *     @type float $success_rate Success rate percentage.
     * }
     */

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
        $stats = $this->cache_read(
            'history.get_stats',
            array(),
            function() {
                // Generation runs only: event containers, indexing and other background
                // jobs would otherwise inflate the totals and the success rate.
                $run_condition = self::generation_run_condition();
                $results = $this->wpdb->get_row(
                    "SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                        SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial
                     FROM {$this->table_name}
                     WHERE {$run_condition}"
                );

                // Background jobs (indexing, bulk slices, source fetches...) are not generation
                // runs, so they are reported next to the run figures instead of inside them.
                $job_results = $this->wpdb->get_row(
                    "SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing
                     FROM {$this->table_name}
                     WHERE " . self::background_job_condition()
                );

                $stats = array(
                    'total'      => isset($results->total)      ? (int) $results->total      : 0,
                    'completed'  => isset($results->completed)  ? (int) $results->completed  : 0,
                    'failed'     => isset($results->failed)     ? (int) $results->failed     : 0,
                    'processing' => isset($results->processing) ? (int) $results->processing : 0,
                    'partial'    => isset($results->partial)    ? (int) $results->partial    : 0,
                    'stalled'    => 0,
                    'jobs'       => array(
                        'total'      => isset($job_results->total)      ? (int) $job_results->total      : 0,
                        'failed'     => isset($job_results->failed)     ? (int) $job_results->failed     : 0,
                        'processing' => isset($job_results->processing) ? (int) $job_results->processing : 0,
                        'stalled'    => 0,
                    ),
                );

                // Only terminal outcomes count toward the rate; `processing`
                // rows are still in flight and would deflate it.
                $stats['resolved']     = AIPS_Outcome_Rate::resolved($stats['completed'], $stats['failed'], $stats['partial']);
                $stats['success_rate'] = AIPS_Outcome_Rate::success_rate($stats['completed'], $stats['failed'], $stats['partial']);

                $durations = $this->wpdb->get_col(
                    "SELECT completed_at - created_at
                     FROM {$this->table_name}
                     WHERE completed_at > 0
                       AND completed_at >= created_at
                       AND {$run_condition}
                     ORDER BY completed_at - created_at ASC"
                );
                $duration_count = count($durations);
                $stats['median_duration'] = null;
                if ($duration_count > 0) {
                    $middle = intdiv($duration_count, 2);
                    $stats['median_duration'] = $duration_count % 2
                        ? (int) $durations[$middle]
                        : (int) round(((int) $durations[$middle - 1] + (int) $durations[$middle]) / 2);
                }

                // Activity: runs started in the last 24h versus the 24h before that.
                $activity = $this->wpdb->get_row(
                    "SELECT
                        SUM(CASE WHEN created_at >= UNIX_TIMESTAMP() - 86400 THEN 1 ELSE 0 END) as last_24h,
                        SUM(CASE WHEN created_at < UNIX_TIMESTAMP() - 86400 AND created_at >= UNIX_TIMESTAMP() - 172800 THEN 1 ELSE 0 END) as prev_24h
                     FROM {$this->table_name}
                     WHERE created_at >= UNIX_TIMESTAMP() - 172800
                       AND {$run_condition}"
                );
                $stats['runs_24h']      = isset($activity->last_24h) ? (int) $activity->last_24h : 0;
                $stats['runs_prev_24h'] = isset($activity->prev_24h) ? (int) $activity->prev_24h : 0;

                // Most common failure reason across generation runs in the last 7 days.
                $top_failure = $this->wpdb->get_row(
                    "SELECT COALESCE(NULLIF(TRIM(error_message), ''), 'Unknown failure') AS reason, COUNT(*) AS failure_count
                     FROM {$this->table_name}
                     WHERE status = 'failed'
                       AND created_at >= UNIX_TIMESTAMP() - 604800
                       AND {$run_condition}
                     GROUP BY reason
                     ORDER BY failure_count DESC
                     LIMIT 1"
                );
                $stats['top_failure'] = $top_failure
                    ? array('reason' => (string) $top_failure->reason, 'count' => (int) $top_failure->failure_count)
                    : null;

                return $stats;
            }
        );

        // Whether an in-progress run is Stalled depends on the current time and no write
        // marks the moment it goes idle, so the split is computed live, outside the cache.
        if ($stats['processing'] > 0) {
            $stalled = $this->count_stalled_rows(self::generation_run_condition('h'));

            $stats['stalled']     = min($stalled, $stats['processing']);
            $stats['processing'] -= $stats['stalled'];
        }

        if ($stats['jobs']['processing'] > 0) {
            $job_stalled = $this->count_stalled_rows(self::background_job_condition('h'));

            $stats['jobs']['stalled']     = min($job_stalled, $stats['jobs']['processing']);
            $stats['jobs']['processing'] -= $stats['jobs']['stalled'];
        }

        return $stats;
    }

    /**
     * Count in-progress rows matching a condition that have gone idle past their threshold.
     *
     * @param string $row_condition SQL condition on the `h` alias.
     * @return int
     */
    private function count_stalled_rows($row_condition) {
        list($stalled_sql, $stalled_args) = $this->stalled_condition('h');

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$this->table_name} h
                 WHERE {$stalled_sql}
                   AND {$row_condition}",
                $stalled_args
            )
        );
    }

    /**
     * Get per-day generation counts for the last N days.
     *
     * Returns an array keyed by ISO date string (Y-m-d) where each value is an
     * associative array with 'completed', 'failed', and 'total' counts.
     * Days with no records are omitted; callers should fill gaps as needed.
     *
     * Applies the same row filter as get_stats(): generation runs only, so event
     * containers, background jobs and empty-shell records are not counted.
     *
     * @param int $days Number of calendar days to look back (inclusive today). Default 14.
     * @return array<string, array{completed: int, failed: int, total: int}>
     */
    public function get_daily_generation_counts( $days = 14 ) {
        $days      = max( 1, absint( $days ) );
        $start_day = AIPS_DateTime::now()->advance( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' );
        $start     = AIPS_DateTime::fromDate( $start_day )->timestamp();

        $run_condition = self::generation_run_condition();
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    DATE(FROM_UNIXTIME(created_at)) AS day,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed,
                    COUNT(*) AS total
                 FROM {$this->table_name}
                 WHERE created_at >= %d
                   AND {$run_condition}
                 GROUP BY DATE(FROM_UNIXTIME(created_at))
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
        $generation_cond = self::generation_run_condition();

        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE template_id = %d AND status IN ('completed', 'partial') AND {$generation_cond}",
            $template_id
        ));
    }

    /**
     * Get statistics for all templates.
     *
     * @return array Associative array of template ID => count.
     */
    public function get_all_template_stats() {
        $generation_cond = self::generation_run_condition();
        $results = $this->wpdb->get_results("
            SELECT template_id, COUNT(*) as count
            FROM {$this->table_name}
            WHERE status IN ('completed', 'partial')
              AND {$generation_cond}
            GROUP BY template_id
        ");

        $stats = array();
        foreach ($results as $row) {
            $stats[$row->template_id] = (int) $row->count;
        }

        return $stats;
    }

    /**
     * Get generated-post counts for schedules by schedule_id (or legacy history_ids).
     *
     * Counts completed/partial generation runs linked by aips_history.schedule_id,
     * and merges with legacy schedule_lifecycle log counts when present.
     *
     * @param array $schedule_or_history_ids Schedule IDs (or legacy history container IDs).
     * @param bool  $by_schedule_id          When true (default), keys and filters by schedule_id.
     * @return array Associative array of id => generated count.
     */
    public function get_schedule_generated_post_counts($schedule_or_history_ids, $by_schedule_id = true) {
        $ids = array_map('absint', (array) $schedule_or_history_ids);
        $ids = array_values(array_filter($ids));

        if (empty($ids)) {
            return array();
        }

        $id_placeholders = implode(',', array_fill(0, count($ids), '%d'));

        // Events that represent a generated post for a schedule container.
        $event_types = AIPS_History_Event_Type::expand(array(
            AIPS_History_Event_Type::POST_PUBLISHED,
            AIPS_History_Event_Type::POST_DRAFT,
            AIPS_History_Event_Type::MANUAL_SCHEDULE_COMPLETED,
        ));
        $event_placeholders = implode(', ', array_fill(0, count($event_types), '%s'));

        if (!$by_schedule_id) {
            $sql = "
                SELECT history_id, COUNT(*) AS count
                FROM {$this->table_name_log}
                WHERE history_id IN ({$id_placeholders})
                    AND history_type_id IN (%d, %d)
                    AND event_type IN ({$event_placeholders})
                GROUP BY history_id
            ";

            $args = $ids;
            $args[] = AIPS_History_Type::ACTIVITY;
            $args[] = AIPS_History_Type::ERROR;
            $args = array_merge($args, $event_types);

            $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $args));

            $counts = array();
            foreach ((array) $results as $row) {
                $counts[(int) $row->history_id] = (int) $row->count;
            }

            return $counts;
        }

        $counts = array();
        $generation_cond = self::generation_run_condition();

        // 1. Count completed/partial generation runs directly by schedule_id.
        $gen_rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT schedule_id, COUNT(*) AS count
             FROM {$this->table_name}
             WHERE schedule_id IN ({$id_placeholders})
               AND status IN ('completed', 'partial')
               AND {$generation_cond}
             GROUP BY schedule_id",
            $ids
        ));

        foreach ((array) $gen_rows as $row) {
            $counts[(int) $row->schedule_id] = (int) $row->count;
        }

        // 2. Also count schedule lifecycle post events by h.schedule_id (covers legacy rows without generation rows).
        $event_kind = self::kind_condition(AIPS_History_Record_Kind::EVENT, 'h');
        $log_args   = $ids;
        $log_args[] = AIPS_History_Type::ACTIVITY;
        $log_args[] = AIPS_History_Type::ERROR;
        $log_args   = array_merge($log_args, $event_types);

        $log_rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT h.schedule_id, COUNT(*) AS count
             FROM {$this->table_name_log} hl
             INNER JOIN {$this->table_name} h ON hl.history_id = h.id
             WHERE h.schedule_id IN ({$id_placeholders})
               AND {$event_kind}
               AND hl.history_type_id IN (%d, %d)
               AND hl.event_type IN ({$event_placeholders})
             GROUP BY h.schedule_id",
            $log_args
        ));

        foreach ((array) $log_rows as $row) {
            $sid = (int) $row->schedule_id;
            $cnt = (int) $row->count;
            $counts[$sid] = isset($counts[$sid]) ? max($counts[$sid], $cnt) : $cnt;
        }

        return $counts;
    }

    /**
     * Get all lifecycle/event log entries for a specific schedule across all of its
     * history containers, ordered by timestamp descending.
     *
     * @param int   $schedule_id         The schedule ID.
     * @param array $type_filter         Optional array of AIPS_History_Type constants to filter by.
     * @param int   $limit               Optional maximum rows to return (0 = no limit).
     * @param int   $fallback_history_id Optional schedule_history_id from aips_schedule for un-backfilled rows.
     * @return array Array of log entry objects.
     */
    public function get_schedule_history_logs($schedule_id, $type_filter = array(), $limit = 0, $fallback_history_id = 0) {
        $schedule_id         = absint($schedule_id);
        $fallback_history_id = absint($fallback_history_id);
        $limit               = absint($limit);

        if (!$schedule_id && !$fallback_history_id) {
            return array();
        }

        $limit_sql   = $limit > 0 ? ' LIMIT ' . $limit : '';
        $event_kind  = self::kind_condition(AIPS_History_Record_Kind::EVENT, 'h');
        $where_parts = array();
        $args        = array();

        if ($schedule_id > 0 && $fallback_history_id > 0) {
            $where_parts[] = "( (h.schedule_id = %d AND {$event_kind}) OR h.id = %d )";
            $args[]        = $schedule_id;
            $args[]        = $fallback_history_id;
        } elseif ($schedule_id > 0) {
            $where_parts[] = "(h.schedule_id = %d AND {$event_kind})";
            $args[]        = $schedule_id;
        } else {
            $where_parts[] = "h.id = %d";
            $args[]        = $fallback_history_id;
        }

        $where_parts[] = "(hl.details IS NULL OR (hl.details NOT LIKE %s AND hl.details NOT LIKE %s))";
        $args[]        = '%"log_subtype":"trigger_source"%';
        $args[]        = '%"log_subtype":"trigger_method"%';

        if (!empty($type_filter)) {
            $type_filter  = array_values(array_map('intval', $type_filter));
            $placeholders = implode(', ', array_fill(0, count($type_filter), '%d'));
            $where_parts[] = "hl.history_type_id IN ({$placeholders})";
            $args          = array_merge($args, $type_filter);
        }

        $where_sql = implode(' AND ', $where_parts);

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT hl.*
                 FROM {$this->table_name_log} hl
                 INNER JOIN {$this->table_name} h ON hl.history_id = h.id
                 WHERE {$where_sql}
                 ORDER BY hl.timestamp DESC, hl.id DESC" . $limit_sql,
                $args
            )
        );
    }

    /**
     * Get author schedule logs filtered by event types.
     *
     * @param int   $author_id Author ID.
     * @param array $event_types Event types to include.
     * @param int   $limit Max rows to return.
     * @return array Raw log rows from aips_history_log.
     */
    public function get_author_schedule_logs_by_event_types($author_id, $event_types = array(), $limit = 100) {
        $author_id = absint($author_id);
        $limit = absint($limit);
        $event_types = array_filter(array_map('sanitize_key', (array) $event_types));

        if (!$author_id || $limit < 1 || empty($event_types)) {
            return array();
        }

        // Expand each requested event type to its canonical name plus every
        // registered legacy alias, then match the indexed event_type column so
        // both new canonical rows and historical rows surface.
        $match_types = AIPS_History_Event_Type::expand($event_types);
        $event_placeholders = implode(', ', array_fill(0, count($match_types), '%s'));

        $args = array(
            $author_id,
            AIPS_History_Type::ACTIVITY,
            AIPS_History_Type::ERROR,
        );
        $args = array_merge($args, $match_types);
        $args[] = $limit;

        $sql = "
            SELECT hl.*
            FROM {$this->table_name_log} hl
            INNER JOIN {$this->table_name} h ON hl.history_id = h.id
            WHERE h.author_id = %d
                AND hl.history_type_id IN (%d, %d)
                AND hl.event_type IN ({$event_placeholders})
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
     * @param int $limit Number of items to return
     * @param int $offset Offset for pagination
     * @param array $filters Optional filters (event_type, event_status, search)
     * @return array Activity entries
     */
    public function get_activity_feed($limit = 50, $offset = 0, $filters = array()) {
        $where_clauses = array("hl.history_type_id = %d");
        $where_args = array(AIPS_History_Type::ACTIVITY);

        // Event type filter — match the requested canonical name plus every
        // registered legacy alias, using the indexed event_type column.
        if (!empty($filters['event_type'])) {
            $type_names = AIPS_History_Event_Type::expand(array($filters['event_type']));
            $placeholders = implode(', ', array_fill(0, count($type_names), '%s'));
            $where_clauses[] = "hl.event_type IN ({$placeholders})";
            $where_args = array_merge($where_args, $type_names);
        }

        // Event status filter — match every stored synonym of the canonical
        // status, using the indexed event_status column.
        if (!empty($filters['event_status'])) {
            $status_values = AIPS_History_Event_Status::synonyms_for($filters['event_status']);
            if (empty($status_values)) {
                $status_values = array((string) $filters['event_status']);
            }
            $placeholders = implode(', ', array_fill(0, count($status_values), '%s'));
            $where_clauses[] = "hl.event_status IN ({$placeholders})";
            $where_args = array_merge($where_args, $status_values);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search_term = '%' . $this->wpdb->esc_like($filters['search']) . '%';
            $where_clauses[] = "hl.details LIKE %s";
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

    /**
     * Get all log entries for a specific history record, ordered by timestamp ascending.
     *
     * @param int   $history_id      The history record ID.
     * @param array $type_filter     Optional array of AIPS_History_Type constants to filter by.
     * @return array Array of log entry objects.
     */
    public function get_logs_by_history_id($history_id, $type_filter = array(), $limit = 0) {
        $limit = absint($limit);
        $limit_sql = $limit > 0 ? ' LIMIT ' . $limit : '';

        if (!empty($type_filter)) {
            $placeholders = implode(', ', array_fill(0, count($type_filter), '%d'));
            $args = array_merge(array($history_id), $type_filter);
            return $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name_log}
                     WHERE history_id = %d AND history_type_id IN ($placeholders)
                     ORDER BY timestamp DESC" . $limit_sql,
                    $args
                )
            );
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name_log} WHERE history_id = %d ORDER BY timestamp DESC" . $limit_sql,
                $history_id
            )
        );
    }

    /**
     * Create a new history entry.
     *
     * @param array $data {
     *     History data.
     *
     *     @type int    $template_id        Template ID.
     *     @type int    $schedule_id        Schedule ID.
     *     @type int    $author_id          Author ID (for topic-based generation).
     *     @type int    $topic_id           Topic ID (for topic-based generation).
     *     @type string $creation_method    Creation method (manual or scheduled).
     *     @type string $status             Status (pending, processing, completed, failed).
     *     @type string $prompt             AI prompt used.
     *     @type string $generated_title    Generated post title.
     *     @type string $generated_content  Generated post content.
     *     @type string $generation_log     JSON-encoded generation log.
     *     @type string $error_message      Error message if failed.
     *     @type int    $post_id            WordPress post ID if created.
     * }
     * @return int|false The inserted ID on success, false on failure.
     */
    public function create($data) {
        if (!isset($data['created_at'])) {
            $data['created_at'] = AIPS_DateTime::now()->timestamp();
        }

        $generation_log = null;
        if (isset($data['generation_log'])) {
            $generation_log = is_string($data['generation_log'])
                ? $data['generation_log']
                : wp_json_encode($data['generation_log']);
        }

        $insert_data = array(
            'uuid' => isset($data['uuid']) ? $data['uuid'] : null,
            'correlation_id' => !empty($data['correlation_id']) ? sanitize_text_field($data['correlation_id']) : null,
            'template_id' => isset($data['template_id']) ? absint($data['template_id']) : null,
            'schedule_id' => !empty($data['schedule_id']) ? absint($data['schedule_id']) : null,
            'campaign_id' => isset($data['campaign_id']) ? absint($data['campaign_id']) : null,
            'author_id' => isset($data['author_id']) ? absint($data['author_id']) : null,
            'topic_id' => isset($data['topic_id']) ? absint($data['topic_id']) : null,
            'creation_method' => isset($data['creation_method']) ? sanitize_text_field($data['creation_method']) : null,
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'pending',
            'prompt' => isset($data['prompt']) ? wp_kses_post($data['prompt']) : '',
            'generated_title' => isset($data['generated_title']) ? sanitize_text_field($data['generated_title']) : '',
            'generated_content' => isset($data['generated_content']) ? wp_kses_post($data['generated_content']) : '',
            'generation_log' => $generation_log,
            'error_message' => isset($data['error_message']) ? sanitize_text_field($data['error_message']) : '',
            'post_id' => isset($data['post_id']) ? absint($data['post_id']) : null,
            'post_type' => isset($data['post_type']) ? sanitize_key($data['post_type']) : null,
            'created_at' => absint($data['created_at']),
            'record_kind' => AIPS_History_Record_Kind::resolve($data),
        );

        $format = array('%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s');
        
        $result = $this->wpdb->insert($this->table_name, $insert_data, $format);
        $insert_id = $result ? (int) $this->wpdb->insert_id : false;
        
        if ($result) {
            $cache_ctx = !empty($insert_data['schedule_id']) ? array('schedule_id' => (int) $insert_data['schedule_id']) : array();
            $this->invalidate_cache_domain( 'history', $cache_ctx, 'history_mutated' );
        }

        return $insert_id;
    }
    
    /**
     * Update an existing history entry.
     *
     * @param int   $id   History item ID.
     * @param array $data Data to update (same structure as create).
     * @return bool True on success, false on failure.
     */
    public function update($id, $data) {
        $update_data = array();
        $format = array();
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $format[] = '%s';
        }
        
        if (isset($data['post_id'])) {
            $update_data['post_id'] = absint($data['post_id']);
            $format[] = '%d';
        }

        if (isset($data['post_type'])) {
            $update_data['post_type'] = sanitize_key($data['post_type']);
            $format[] = '%s';
        }

        if (isset($data['record_kind']) && AIPS_History_Record_Kind::is_valid($data['record_kind'])) {
            $update_data['record_kind'] = $data['record_kind'];
            $format[] = '%s';
        }

        if (isset($data['generated_title'])) {
            $update_data['generated_title'] = sanitize_text_field($data['generated_title']);
            $format[] = '%s';
        }
        
        if (isset($data['generated_content'])) {
            $update_data['generated_content'] = wp_kses_post($data['generated_content']);
            $format[] = '%s';
        }

        if (array_key_exists('generation_log', $data)) {
            $update_data['generation_log'] = is_string($data['generation_log']) || is_null($data['generation_log'])
                ? $data['generation_log']
                : wp_json_encode($data['generation_log']);
            $format[] = '%s';
        }
        
        if (isset($data['error_message'])) {
            $update_data['error_message'] = sanitize_text_field($data['error_message']);
            $format[] = '%s';
        }
        
        if (isset($data['author_id'])) {
            $update_data['author_id'] = absint($data['author_id']);
            $format[] = '%d';
        }
        
        if (isset($data['topic_id'])) {
            $update_data['topic_id'] = absint($data['topic_id']);
            $format[] = '%d';
        }

        if (array_key_exists('schedule_id', $data)) {
            $update_data['schedule_id'] = !empty($data['schedule_id']) ? absint($data['schedule_id']) : null;
            $format[] = '%d';
        }

        if (array_key_exists('campaign_id', $data)) {
            $update_data['campaign_id'] = !empty($data['campaign_id']) ? absint($data['campaign_id']) : null;
            $format[] = '%d';
        }
        
        if (isset($data['creation_method'])) {
            $update_data['creation_method'] = sanitize_text_field($data['creation_method']);
            $format[] = '%s';
        }
        
        if (isset($data['completed_at'])) {
            $update_data['completed_at'] = absint($data['completed_at']);
            $format[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );

        if ($result !== false) {
            $cache_ctx = !empty($update_data['schedule_id']) ? array('schedule_id' => (int) $update_data['schedule_id']) : array();
            $this->invalidate_cache_domain( 'history', $cache_ctx, 'history_mutated' );
        }

        return $result !== false;
    }
    
    /**
     * Delete history entries.
     *
     * @param string $status Optional. Delete only entries with this status. Default empty (all).
     * @return int|false Number of rows affected or false on failure.
     */
    public function delete_by_status($status = '') {
        delete_transient('aips_history_stats');

        if (empty($status)) {
            return false;
        }

        if ($status === 'all') {
            return $this->wpdb->query("DELETE FROM {$this->table_name}");
        }
        
        return $this->wpdb->delete($this->table_name, array('status' => $status), array('%s'));
    }
    
    /**
     * Delete a single history entry by ID.
     *
     * @param int $id History item ID.
     * @return bool True on success, false on failure.
     */
    public function delete($id) {
        $result = $this->wpdb->delete($this->table_name, array('id' => $id), array('%d'));

        if ($result !== false) {
            $this->invalidate_cache_domain( 'history', array(), 'history_mutated' );
        }

        return $result !== false;
    }

    /**
     * Delete multiple history entries by ID.
     *
     * @param array $ids Array of history item IDs.
     * @return int|false Number of rows affected or false on failure.
     */
    public function delete_bulk($ids) {
        if (empty($ids)) {
            return 0;
        }

        // Sanitize IDs
        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $query = $this->wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
            $ids
        );

        $result = $this->wpdb->query($query);

        if ($result !== false) {
            $this->invalidate_cache_domain( 'history', array(), 'history_mutated' );
        }

        return $result;
    }

    /**
     * Clear history entries based on filters.
     * 
     * This method provides a centralized way to delete history records
     * with optional filtering by status and age.
     *
     * @param array $args {
     *     Optional. Filter arguments.
     *     @type string $status         Status to filter by ('all', 'completed', 'failed', 'processing'). Default 'all'.
     *     @type int    $older_than_days Only delete records older than this many days. Default 0 (no age filter).
     * }
     * @return array {
     *     @type bool   $success Whether the operation succeeded.
     *     @type int    $deleted Number of records deleted.
     *     @type string $message Human-readable message.
     * }
     */
    public function clear_history($args = array()) {
        $defaults = array(
            'status' => 'all',
            'older_than_days' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $query_args = array();
        
        // Add age filter if specified
        if ($args['older_than_days'] > 0) {
			$date = AIPS_DateTime::now()->timestamp() - (absint($args['older_than_days']) * DAY_IN_SECONDS);
			$where[] = "created_at < %d";
            $query_args[] = $date;
        }
        
        // Add status filter if not 'all'
        if ($args['status'] !== 'all') {
            $where[] = "status = %s";
            $query_args[] = $args['status'];
        }
        
        // Build WHERE clause
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Count records that will be deleted
        if (!empty($query_args)) {
            $count = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} $where_clause", $query_args));
            $deleted = $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->table_name} $where_clause", $query_args));
        } elseif (($args['status'] ?? '') === 'all' && empty($where)) {
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
            $deleted = $this->wpdb->query("DELETE FROM {$this->table_name}");
        } else {
            return array(
                'success' => false,
                'deleted' => 0,
                'message' => "Invalid filter arguments for history deletion"
            );
        }
        
        // Clear cache
        if ($deleted !== false && $deleted > 0) {
            $this->invalidate_cache_domain( 'history', array(), 'history_mutated' );
        }
        
        return array(
            'success' => $deleted !== false,
            'deleted' => $deleted !== false ? (int) $deleted : 0,
            'message' => $deleted !== false ? "Deleted {$deleted} history records" : "Failed to delete history records"
        );
    }

    /**
     * Count generation runs that are in progress but idle past their threshold.
     *
     * Scoped to generation runs by default to match the Overview "Generation runs: stalled"
     * tile and provide accurate stuck job counts for queue health.
     *
     * @param string|string[] $kinds Record kinds to inspect. Default 'generation'.
     * @return array{count:int,oldest_idle_seconds:int|null}
     */
    public function get_stalled_summary($kinds = AIPS_History_Record_Kind::GENERATION) {
        list($stalled_sql, $stalled_args) = $this->stalled_condition('h');
        $kind_sql                          = self::kind_condition($kinds, 'h');

        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) AS total,
                    MIN(GREATEST(h.created_at, COALESCE((SELECT MAX(sl.timestamp) FROM {$this->table_name_log} sl WHERE sl.history_id = h.id), 0))) AS oldest_activity
             FROM {$this->table_name} h
             WHERE {$stalled_sql}
               AND {$kind_sql}",
            $stalled_args
        ));

        $count = $row ? (int) $row->total : 0;

        return array(
            'count'               => $count,
            'oldest_idle_seconds' => ($count > 0 && $row->oldest_activity !== null)
                ? max(0, AIPS_DateTime::now()->timestamp() - (int) $row->oldest_activity)
                : null,
        );
    }

    /**
     * Keep a post's latest generation run in step with its component state.
     *
     * A generation that finished with missing components is 'partial'; once the
     * components are fixed it becomes 'completed' again (and vice versa). The row that
     * moves is the post's newest completed/partial generation run, the same row the
     * Pending Review and Partial Generations tabs show. Indexing, other background jobs
     * and event containers carry the post_id too and are always newer than the run, so
     * they are never the target; failed and in-progress rows are left alone.
     *
     * @param int  $post_id    Post ID.
     * @param bool $incomplete Whether any component is still missing.
     * @return bool True when a row changed.
     */
    public function sync_generation_status_for_post($post_id, $incomplete) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return false;
        }

        // One conditional UPDATE: no read is needed to find out the row is already correct.
        $target     = $incomplete ? 'partial' : 'completed';
        $latest_run = self::latest_generation_run_subquery(array('post_id' => $post_id));

        $updated = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_name} h
             INNER JOIN {$latest_run} latest ON latest.latest_history_id = h.id
             SET h.status = %s
             WHERE h.status <> %s",
            $target,
            $target
        ));

        return $this->finish_bulk_status_update($updated) > 0;
    }

    /**
     * Classify existing rows as generation runs or background jobs.
     *
     * The schema upgrade adds record_kind with the default 'event', so every existing
     * row starts as an event. This promotes the rows that are generation runs or jobs
     * from their creation_method, in batches. Only rows still marked 'event' are
     * touched, so a kind written by newer code is never overwritten and a second run
     * changes nothing.
     *
     * 'bulk_generate' is both the Planner's per-post run and the bulk summary container:
     * a completed row without a post is the summary and becomes a job; with a post, or
     * failed, pending or in progress, it is a run. New rows are tagged when written;
     * this is the best available rule for historic ones.
     *
     * Rows that carry a post but match no known method are left as events; they are
     * returned in `unclassified` (method => count) so the caller can report them.
     *
     * @param int $batch_size Rows updated per statement.
     * @return array{generation:int, job:int, errors:int, unclassified:array<string,int>}
     */
    public function backfill_record_kind($batch_size = 1000) {
        $batch_size = max(1, absint($batch_size));

        $runs   = self::quoted_sql_list(self::generation_run_creation_methods());
        $events = self::quoted_sql_list(AIPS_History_Record_Kind::event_creation_methods());
        $jobs   = self::quoted_sql_list(AIPS_History_Record_Kind::job_creation_methods());
        $chain  = array();
        foreach (self::chain_method_patterns() as $pattern) {
            $chain[] = "creation_method LIKE '{$pattern}'";
        }

        // Only a finished row can be told apart: a Planner run that is still pending or
        // in progress has no post yet either, and must stay a generation.
        $bulk_summary = "(creation_method = 'bulk_generate' AND post_id IS NULL AND status = 'completed')";

        $conditions = array(
            AIPS_History_Record_Kind::GENERATION => "((creation_method IN ({$runs}) AND NOT {$bulk_summary})"
                . " OR ((creation_method IS NULL OR creation_method = '')"
                . ' AND (template_id IS NOT NULL OR topic_id IS NOT NULL OR post_id IS NOT NULL OR author_id IS NOT NULL)))',
            AIPS_History_Record_Kind::JOB        => '(((creation_method IN (' . $jobs . ') OR ' . implode(' OR ', $chain) . ')'
                . " AND creation_method NOT IN ({$runs}) AND creation_method NOT IN ({$events}))"
                . " OR {$bulk_summary})",
        );

        $counts = array(AIPS_History_Record_Kind::GENERATION => 0, AIPS_History_Record_Kind::JOB => 0);
        $errors = 0;

        // The SQL contains LIKE wildcards, so it is not passed through prepare(); every
        // interpolated value is a code constant or an integer.
        foreach ($conditions as $kind => $condition) {
            $total = 0;
            do {
                $changed = $this->wpdb->query(
                    "UPDATE {$this->table_name} SET record_kind = '{$kind}' WHERE record_kind = 'event' AND {$condition} LIMIT {$batch_size}"
                );
                if ($changed === false) {
                    $errors++;
                    break;
                }
                $total += (int) $changed;
            } while ((int) $changed === $batch_size);

            $counts[$kind] = $this->finish_bulk_status_update($total);
        }

        return array(
            'generation'   => $counts[AIPS_History_Record_Kind::GENERATION],
            'job'          => $counts[AIPS_History_Record_Kind::JOB],
            'errors'       => $errors,
            'unclassified' => $this->unclassified_methods_with_posts(),
        );
    }

    /**
     * Creation methods of rows that carry a post but are still marked 'event' without
     * being a known event type: the rows a name-based backfill may have misjudged.
     *
     * @param int $limit Maximum number of methods to return.
     * @return array<string,int> creation_method => row count, most frequent first.
     */
    private function unclassified_methods_with_posts($limit = 10) {
        $events = self::quoted_sql_list(AIPS_History_Record_Kind::event_creation_methods());
        $rows   = $this->wpdb->get_results(
            "SELECT creation_method AS method, COUNT(*) AS n
             FROM {$this->table_name}
             WHERE record_kind = 'event'
               AND post_id IS NOT NULL
               AND creation_method NOT IN ({$events})
             GROUP BY creation_method
             ORDER BY n DESC
             LIMIT " . absint($limit)
        );

        $methods = array();
        foreach ((array) $rows as $row) {
            $methods[(string) $row->method] = (int) $row->n;
        }

        return $methods;
    }

    /**
     * Fill aips_history.post_type from the linked post for generation runs that lack it.
     *
     * Rows written after the column was introduced (3.4.0) but before the write path
     * populated it have a NULL post_type. Only generation-run rows whose post still
     * exists are touched, an existing value is never overwritten, and event rows are
     * left alone. Batched and idempotent.
     *
     * @param int $batch_size Rows updated per statement.
     * @return int Number of rows changed.
     */
    public function backfill_post_type($batch_size = 500) {
        $batch_size    = max(1, absint($batch_size));
        $posts_table   = $this->wpdb->posts;
        $run_condition = self::generation_run_condition('h');
        $total         = 0;

        do {
            $ids = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT h.id
                 FROM {$this->table_name} h
                 INNER JOIN {$posts_table} p ON p.ID = h.post_id
                 WHERE h.post_id IS NOT NULL
                   AND (h.post_type IS NULL OR h.post_type = '')
                   AND p.post_type <> ''
                   AND {$run_condition}
                 LIMIT %d",
                $batch_size
            ));
            if (empty($ids)) {
                break;
            }

            $ids          = array_map('intval', $ids);
            $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
            $updated      = $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->table_name} h
                 INNER JOIN {$posts_table} p ON p.ID = h.post_id
                 SET h.post_type = p.post_type
                 WHERE h.id IN ({$placeholders})",
                $ids
            ));
            if ($updated === false || $updated < 1) {
                break;
            }
            $total += (int) $updated;
        } while (count($ids) === $batch_size);

        return $this->finish_bulk_status_update($total);
    }

    /**
     * Backfill aips_history.schedule_id on existing History rows.
     *
     * 1. Links rows referenced by aips_schedule.schedule_history_id.
     * 2. Extracts schedule_id from aips_history_log entries (trigger_source / lifecycle context).
     * 3. Propagates schedule_id across sibling rows sharing the same correlation_id.
     *
     * Idempotent: only rows where schedule_id IS NULL are modified.
     *
     * @param int $batch_size Rows inspected per batch when scanning log details.
     * @return int Total number of rows updated.
     */
    public function backfill_schedule_id($batch_size = 500) {
        $batch_size = max(1, absint($batch_size));
        $total      = 0;

        // 1. Backfill from aips_schedule.schedule_history_id.
        $from_schedule = $this->wpdb->query(
            "UPDATE {$this->table_name} h
             INNER JOIN {$this->schedule_table} s ON s.schedule_history_id = h.id
             SET h.schedule_id = s.id
             WHERE h.schedule_id IS NULL"
        );
        if ($from_schedule !== false && $from_schedule > 0) {
            $total += (int) $from_schedule;
        }

        // 2. Backfill from aips_history_log JSON details containing "schedule_id".
        $last_log_id = 0;
        do {
            $rows = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT hl.id AS log_id, h.id AS history_id, hl.details
                 FROM {$this->table_name} h
                 INNER JOIN {$this->table_name_log} hl ON hl.history_id = h.id
                 WHERE h.schedule_id IS NULL
                   AND hl.details LIKE %s
                   AND hl.id > %d
                 ORDER BY hl.id ASC
                 LIMIT %d",
                '%"schedule_id":%',
                $last_log_id,
                $batch_size
            ));

            if (empty($rows)) {
                break;
            }

            $updates_by_schedule = array();
            foreach ($rows as $row) {
                $log_id     = (int) $row->log_id;
                $history_id = (int) $row->history_id;
                if ($log_id > $last_log_id) {
                    $last_log_id = $log_id;
                }

                $schedule_id = 0;
                $decoded     = json_decode((string) $row->details, true);
                if (is_array($decoded)) {
                    if (!empty($decoded['input']['schedule_id'])) {
                        $schedule_id = absint($decoded['input']['schedule_id']);
                    } elseif (!empty($decoded['context']['schedule_id'])) {
                        $schedule_id = absint($decoded['context']['schedule_id']);
                    } elseif (!empty($decoded['schedule_id'])) {
                        $schedule_id = absint($decoded['schedule_id']);
                    }
                } elseif (preg_match('/"schedule_id"\s*:\s*(\d+)/', (string) $row->details, $matches)) {
                    $schedule_id = absint($matches[1]);
                }

                if ($schedule_id > 0) {
                    $updates_by_schedule[$schedule_id][$history_id] = $history_id;
                }
            }

            foreach ($updates_by_schedule as $schedule_id => $history_ids) {
                $history_ids  = array_values($history_ids);
                $placeholders = implode(', ', array_fill(0, count($history_ids), '%d'));
                $args         = array_merge(array((int) $schedule_id), $history_ids);
                $updated      = $this->wpdb->query($this->wpdb->prepare(
                    "UPDATE {$this->table_name}
                     SET schedule_id = %d
                     WHERE id IN ({$placeholders})
                       AND schedule_id IS NULL",
                    $args
                ));
                if ($updated !== false && $updated > 0) {
                    $total += (int) $updated;
                }
            }
        } while (count($rows) === $batch_size);

        // 3. Propagate schedule_id across sibling containers sharing a correlation_id.
        $from_correlation = $this->wpdb->query(
            "UPDATE {$this->table_name} h
             INNER JOIN (
                 SELECT correlation_id, MAX(schedule_id) AS resolved_schedule_id
                 FROM {$this->table_name}
                 WHERE correlation_id IS NOT NULL
                   AND correlation_id <> ''
                   AND schedule_id IS NOT NULL
                   AND schedule_id > 0
                 GROUP BY correlation_id
             ) src ON src.correlation_id = h.correlation_id
             SET h.schedule_id = src.resolved_schedule_id
             WHERE h.schedule_id IS NULL"
        );
        if ($from_correlation !== false && $from_correlation > 0) {
            $total += (int) $from_correlation;
        }

        return $this->finish_bulk_status_update($total);
    }

    /**
     * Mark existing generation runs as partial when their post is flagged incomplete.
     *
     * One-time backfill for rows written before generations could be 'partial'.
     * Only the newest generation run of each post is touched.
     *
     * @return int Number of rows changed.
     */
    public function backfill_partial_generation_status() {
        return $this->finish_bulk_status_update(
            $this->set_run_status_by_incomplete_flag('true', 'completed', 'partial')
        );
    }

    /**
     * Move each post's newest generation run between statuses according to its post's
     * incomplete flag.
     *
     * @param string $flag   Value the post's incomplete meta must have ('true' or 'false').
     * @param string $from   Current run status to change.
     * @param string $to     New run status.
     * @return int|false Rows changed, or false when the query failed.
     */
    private function set_run_status_by_incomplete_flag($flag, $from, $to) {
        $latest_run = self::latest_generation_run_subquery();

        return $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_name} h
             INNER JOIN {$latest_run} latest ON latest.latest_history_id = h.id
             INNER JOIN {$this->wpdb->postmeta} pm ON pm.post_id = h.post_id AND pm.meta_key = %s AND pm.meta_value = %s
             SET h.status = %s
             WHERE h.status = %s",
            AIPS_Post_Manager::META_GENERATION_INCOMPLETE,
            $flag,
            $to,
            $from
        ));
    }

    /**
     * Repair statuses written by the sync before it targeted the generation run.
     *
     * The sync and the 3.7.13 backfill picked each post's newest non-event row, which
     * for an indexed post is the content_indexing row. That row was marked 'partial'
     * (indexing never ends partial) while the real run kept its old status. This:
     *
     * 1. resets content_indexing rows that carry a post_id from 'partial' to 'completed'
     *    (only that creation_method: it is the only background job the old sync reached
     *    in practice, and any other 'partial' row may be a real run the allowlist does
     *    not name);
     * 2. marks each post's newest run 'partial' when its post is flagged incomplete;
     * 3. marks it 'completed' again when the post's incomplete flag is 'false'.
     *
     * Idempotent: a second run changes nothing.
     *
     * @return array{jobs_reset:int, marked_partial:int, marked_completed:int, errors:int}
     *         `errors` counts the statements that failed; callers should not treat the
     *         repair as done when it is above zero.
     */
    public function repair_generation_statuses() {
        $jobs_reset = $this->wpdb->query(
            "UPDATE {$this->table_name}
             SET status = 'completed'
             WHERE post_id IS NOT NULL
               AND status = 'partial'
               AND creation_method = 'content_indexing'"
        );
        $marked_partial   = $this->set_run_status_by_incomplete_flag('true', 'completed', 'partial');
        $marked_completed = $this->set_run_status_by_incomplete_flag('false', 'partial', 'completed');

        $errors = count(array_filter(array($jobs_reset, $marked_partial, $marked_completed), function ($result) {
            return $result === false;
        }));

        return array(
            'jobs_reset'       => $this->finish_bulk_status_update($jobs_reset),
            'marked_partial'   => $this->finish_bulk_status_update($marked_partial),
            'marked_completed' => $this->finish_bulk_status_update($marked_completed),
            'errors'           => $errors,
        );
    }

    /**
     * Close event-only containers that were left in 'processing'.
     *
     * One-shot event containers (review-queue actions, topic-generation wrappers,
     * notifications) used to be created without ever being closed. A container is
     * closed as 'failed' when any of its log entries carries a failed event status,
     * and as 'completed' otherwise. completed_at is set to its last log activity.
     *
     * @param string[] $creation_methods creation_method values to repair.
     * @return int Number of rows closed.
     */
    public function close_orphaned_event_rows(array $creation_methods) {
        $creation_methods = array_values(array_filter(array_map('strval', $creation_methods)));

        // Without the indexed event_status column a failed run cannot be told
        // apart from a successful one, so leave the rows untouched.
        if (empty($creation_methods) || !$this->history_log_has_event_columns()) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($creation_methods), '%s'));

        $updated = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_name} h
             SET h.status = CASE
                     WHEN EXISTS (
                         SELECT 1 FROM {$this->table_name_log} l
                         WHERE l.history_id = h.id AND l.event_status = 'failed'
                     ) THEN 'failed'
                     ELSE 'completed'
                 END,
                 h.completed_at = GREATEST(
                     h.created_at,
                     COALESCE((SELECT MAX(l2.timestamp) FROM {$this->table_name_log} l2 WHERE l2.history_id = h.id), 0)
                 )
             WHERE h.status = 'processing'
               AND h.creation_method IN ({$placeholders})",
            $creation_methods
        ));

        return $this->finish_bulk_status_update($updated);
    }

    /**
     * Close 'processing' containers that have shown no activity for a while.
     *
     * Last activity is the newest log entry, falling back to created_at. Matching
     * rows are closed as 'terminated' (never deleted) with an explanatory message,
     * so a genuinely running job keeps its row and a dead one stops showing as
     * in progress.
     *
     * @param int      $stale_seconds   Inactivity, in seconds, after which a row is stale.
     * @param string[] $exclude_methods creation_method values that must never be closed
     *                                  (persistent lifecycle containers).
     * @return int Number of rows closed.
     */
    public function close_stale_processing_rows($stale_seconds, array $exclude_methods = array()) {
        $stale_seconds = max(60, (int) $stale_seconds);
        $cutoff        = AIPS_DateTime::now()->timestamp() - $stale_seconds;
        $last_activity = "GREATEST(h.created_at, COALESCE((SELECT MAX(l.timestamp) FROM {$this->table_name_log} l WHERE l.history_id = h.id), 0))";

        // Placeholders appear in SQL order: message, excluded methods, cutoff.
        $args            = array(__('Closed automatically: no activity was recorded and the run did not finish.', 'ai-post-scheduler'));
        $where_exclude   = '';
        $exclude_methods = array_values(array_filter(array_map('strval', $exclude_methods)));
        if (!empty($exclude_methods)) {
            $where_exclude = " AND COALESCE(h.creation_method, '') NOT IN (" . implode(', ', array_fill(0, count($exclude_methods), '%s')) . ')';
            $args          = array_merge($args, $exclude_methods);
        }
        $args[] = $cutoff;

        $updated = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_name} h
             SET h.completed_at = {$last_activity},
                 h.error_message = CASE WHEN h.error_message IS NULL OR h.error_message = '' THEN %s ELSE h.error_message END,
                 h.status = 'terminated'
             WHERE h.status = 'processing'{$where_exclude}
               AND {$last_activity} < %d",
            $args
        ));

        return $this->finish_bulk_status_update($updated);
    }

    /**
     * Close runs that are currently shown as Stalled.
     *
     * Uses the same idle thresholds as the Stalled status (15 minutes for
     * generation runs, longer for chained jobs), so a run that History still shows
     * as Processing is never closed. Rows are marked 'terminated', not deleted.
     * Scoped by default to generation runs, matching the Clear In-Progress button in the
     * Generation runs overview card. Event rows are never closed.
     *
     * @param string|string[] $kinds           Record kinds to close. Default 'generation'.
     * @param string[]        $exclude_methods Optional creation_method values that must never be closed.
     * @return int Number of rows closed.
     */
    public function close_stalled_processing_rows($kinds = AIPS_History_Record_Kind::GENERATION, array $exclude_methods = array()) {
        // If an array of exclude_methods was passed into first parameter (legacy callers), adapt gracefully.
        if (is_array($kinds) && !empty($kinds) && !AIPS_History_Record_Kind::is_valid(reset($kinds))) {
            $exclude_methods = $kinds;
            $kinds           = AIPS_History_Record_Kind::GENERATION;
        }

        list($stalled_sql, $stalled_args) = $this->stalled_condition('h');
        $kind_sql                          = self::kind_condition($kinds, 'h');

        $args            = array(__('Closed automatically: no activity was recorded and the run did not finish.', 'ai-post-scheduler'));
        $args            = array_merge($args, $stalled_args);
        $where_exclude   = '';
        $exclude_methods = array_values(array_filter(array_map('strval', $exclude_methods)));
        if (!empty($exclude_methods)) {
            $where_exclude = " AND COALESCE(h.creation_method, '') NOT IN (" . implode(', ', array_fill(0, count($exclude_methods), '%s')) . ')';
            $args          = array_merge($args, $exclude_methods);
        }

        $updated = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_name} h
             SET h.completed_at = GREATEST(h.created_at, COALESCE((SELECT MAX(sl.timestamp) FROM {$this->table_name_log} sl WHERE sl.history_id = h.id), 0)),
                 h.error_message = CASE WHEN h.error_message IS NULL OR h.error_message = '' THEN %s ELSE h.error_message END,
                 h.status = 'terminated'
             WHERE {$stalled_sql}
               AND {$kind_sql}{$where_exclude}",
            $args
        ));

        return $this->finish_bulk_status_update($updated);
    }

    /**
     * Normalise a bulk UPDATE result and invalidate History caches when rows changed.
     *
     * @param int|false $updated Result of $wpdb->query().
     * @return int Rows affected.
     */
    private function finish_bulk_status_update($updated) {
        if ($updated === false || $updated < 1) {
            return 0;
        }

        $this->invalidate_cache_domain('history', array(), 'history_mutated');

        return (int) $updated;
    }

    /**
     * Determine whether featured image generation failed for a history run.
     *
     * @param int $history_id History ID.
     * @return bool True when featured image generation was attempted and failed.
     */
    public function did_featured_image_generation_fail($history_id) {
        $history_id = absint($history_id);

        if (!$history_id) {
            return false;
        }

        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$this->table_name_log}
            WHERE history_id = %d
            AND history_type_id = %d
            AND details LIKE %s
            AND details LIKE %s",
            $history_id,
            AIPS_History_Type::METRIC,
            '%"image_attempted":true%',
            '%"image_success":false%'
        ));

        return ((int) $count) > 0;
    }

    /**
     * Get all revisions for a specific post component
     *
     * Retrieves all AI_RESPONSE logs for a given post and component type,
     * ordered by timestamp (newest first). Queries based on component in context.
     *
     * @param int $post_id Post ID
     * @param string $component_type Component type (title, excerpt, content, featured_image)
     * @param int $limit Maximum number of revisions to retrieve (default: 20)
     * @return array Array of revision objects with id, timestamp, value, history_id
     */
    public function get_component_revisions($post_id, $component_type, $limit = 20) {
        $history_log_table = $this->wpdb->prefix . 'aips_history_log';
        $history_table = $this->wpdb->prefix . 'aips_history';

        // Query for AI_RESPONSE logs with matching component in context
        // The context field contains JSON like {"component":"title","post_id":123}
        $sql = $this->wpdb->prepare("
			SELECT
				hl.id,
				hl.timestamp,
				hl.details,
				h.id as history_id,
				h.uuid,
				h.post_id
			FROM {$history_log_table} hl
			INNER JOIN {$history_table} h ON hl.history_id = h.id
			WHERE hl.history_type_id = %d
			AND hl.details LIKE %s
			AND (
				h.post_id = %d
				OR hl.details LIKE %s
			)
			ORDER BY hl.timestamp DESC
			LIMIT %d
		",
            AIPS_History_Type::AI_RESPONSE,
            '%"component":"' . $this->wpdb->esc_like($component_type) . '"%',
            $post_id,
            '%"post_id":' . absint($post_id) . '%',
            $limit
        );

        $results = $this->wpdb->get_results($sql);

        if (empty($results)) {
            return array();
        }

        // Parse and format the results
        $revisions = array();
        foreach ($results as $row) {
            $details = json_decode($row->details, true);
            if (!$details) {
                continue;
            }

            // Extract the output value (the regenerated content)
            $value = '';
            if (isset($details['output'])) {
                if (isset($details['output_encoded']) && $details['output_encoded']) {
                    $value = base64_decode($details['output']);
                } else if (is_array($details['output']) && isset($details['output']['value'])) {
                    $value = $details['output']['value'];
                } else if (is_string($details['output'])) {
                    $value = $details['output'];
                } else {
                    // For complex outputs like featured_image with attachment_id and url
                    $value = $details['output'];
                }
            }

            $revisions[] = array(
                'id' => $row->id,
                'history_id' => $row->history_id,
                'uuid' => $row->uuid,
                'timestamp' => $row->timestamp,
                'component_type' => $component_type,
                'value' => $value,
                'source' => isset($details['context']['source']) ? $details['context']['source'] : 'ai_generated',
                'reason' => isset($details['context']['reason']) ? $details['context']['reason'] : '',
                'context' => isset($details['context']) ? $details['context'] : array(),
            );
        }

        return $revisions;
    }

    /**
     * Return the repository cache group for history reads.
     *
     * @return string
     */
    protected function repository_cache_group(): string {
        return 'aips_history';
    }

    /**
     * Return the explicit repository cache policies for history reads.
     *
     * @return array
     */
    protected function repository_cache_policies(): array {
        return array(
            'history.get_stats'                    => array(
                'tier'        => 'medium',
                'tags'        => array( 'history' ),
                'description' => 'Cache history aggregate stats (total, completed, failed, etc.).',
            ),
            'history.get_schedule_completed_count' => array(
                'tier'        => 'long',
                'tags'        => array( 'history', 'history_schedule:{schedule_id}' ),
                'cache_null'  => false,
                'description' => 'Cache per-schedule completed generation counts.',
            ),
        );
    }
}
