<?php
/**
 * Metrics Repository
 *
 * Collects and exposes baseline reliability and performance metrics for
 * scheduler and generation workflows.  All queries read from existing plugin
 * tables; no schema changes are required.
 *
 * Metrics exposed:
 *  - Generation success/failure rates and counts (windowed and all-time)
 *  - Average and percentile generation durations (created_at → completed_at)
 *  - Average AI-request count per completed post generation
 *  - Image generation failure rate (from `metric_generation_result` history log entries)
 *  - Schedule run success rate (scheduled creation-method history records)
 *  - Queue-depth surrogates (active schedules, approved topics)
 *  - Recent generation outcomes (last N history records)
 *
 * @package AI_Post_Scheduler
 * @since   2.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIPS_Metrics_Repository
 *
 * Repository that aggregates baseline observability metrics from existing
 * plugin tables.  Results are cached in WordPress transients for a
 * configurable TTL to keep collection overhead low.
 */
class AIPS_Metrics_Repository {

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * @var string History table name (with prefix).
	 */
	private $table_history;

	/**
	 * @var string History log table name (with prefix).
	 */
	private $table_history_log;

	/**
	 * @var string Schedule table name (with prefix).
	 */
	private $table_schedule;

	/**
	 * @var string Author topics table name (with prefix).
	 */
	private $table_author_topics;

	/**
	 * Default lookback window in days for windowed metrics.
	 */
	const DEFAULT_WINDOW_DAYS = 30;

	/**
	 * Transient key for cached generation metrics.
	 */
	const TRANSIENT_GENERATION = 'aips_metrics_generation';

	/**
	 * Transient key for cached queue-depth metrics.
	 */
	const TRANSIENT_QUEUE = 'aips_metrics_queue';

	/**
	 * Transient key for cached queue-health metrics.
	 */
	const TRANSIENT_QUEUE_HEALTH = 'aips_metrics_queue_health';

	/**
	 * Age threshold in minutes after which a pending/partial job is considered stuck.
	 */
	const STUCK_JOB_THRESHOLD_MINUTES = 30;

	/**
	 * Lookback window in hours for recent-failure and retry-saturation signals.
	 */
	const RETRY_WINDOW_HOURS = 24;

	/**
	 * Transient TTL in seconds.
	 */
	const CACHE_TTL = 900; // 15 minutes

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb                = $wpdb;
		$this->table_history       = $wpdb->prefix . 'aips_history';
		$this->table_history_log   = $wpdb->prefix . 'aips_history_log';
		$this->table_schedule      = $wpdb->prefix . 'aips_schedule';
		$this->table_author_topics = $wpdb->prefix . 'aips_author_topics';
	}

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Return all baseline metrics in a single call.
	 *
	 * @param int $window_days Number of days to look back for windowed metrics.
	 * @return array {
	 *     @type array  $generation   Generation reliability metrics.
	 *     @type array  $queue_depth  Queue-depth surrogate indicators.
	 *     @type array  $queue_health Queue-health indicators (backlog, stuck jobs, retry saturation, circuit breaker).
	 *     @type string $collected_at ISO-8601 timestamp of when these metrics were computed.
	 * }
	 */
	public function get_baseline_metrics( $window_days = self::DEFAULT_WINDOW_DAYS ) {
		return array(
			'generation'   => $this->get_generation_metrics( $window_days ),
			'queue_depth'  => $this->get_queue_depth_metrics(),
			'queue_health' => $this->get_queue_health_metrics(),
			'collected_at' => AIPS_DateTime::now()->toIso8601(),
		);
	}

	/**
	 * Generation reliability and performance metrics.
	 *
	 * @param int $window_days Number of days for the lookback window.
	 * @return array {
	 *     @type int   $window_days                Lookback window used.
	 *     @type int   $total                       Total generation attempts.
	 *     @type int   $successful                  Completed (success) count.
	 *     @type int   $failed                      Failed count.
	 *     @type int   $partial                     Partial (incomplete) count.
	 *     @type float $success_rate                Success rate percentage (0–100).
	 *     @type float $failure_rate                Failure rate percentage (0–100).
	 *     @type int   $avg_duration_seconds        Average generation duration (seconds).
	 *     @type int   $p50_duration_seconds        Median generation duration (seconds).
	 *     @type int   $p95_duration_seconds        95th-percentile generation duration (seconds).
	 *     @type float $avg_ai_calls_per_post       Average AI log entries per completed post.
	 *     @type float $image_failure_rate          Image-generation failure rate (0–100).
	 *     @type float $schedule_success_rate       Scheduled-run success rate (0–100).
	 *     @type array $recent_outcomes             Last 10 generation records (summary).
	 * }
	 */
	public function get_generation_metrics( $window_days = self::DEFAULT_WINDOW_DAYS ) {
		$window_days = max( 1, (int) $window_days );
		$cache_key   = self::TRANSIENT_GENERATION . '_' . $window_days;
		$cached      = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		// Pass the window in days to each helper; the SQL boundary is derived
		// via DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL %d DAY) to match the
		// timezone used by the DB DEFAULT CURRENT_TIMESTAMP on created_at.
		$counts = $this->get_generation_counts( $window_days );
		$durations = $this->get_duration_percentiles( $window_days );
		$ai_calls  = $this->get_avg_ai_calls_per_post( $window_days );
		$image_failure_rate    = $this->get_image_failure_rate( $window_days );
		$schedule_success_rate = $this->get_schedule_success_rate( $window_days );
		$recent_outcomes       = $this->get_recent_outcomes( 10 );

		$total = $counts['total'];
		$metrics = array(
			'window_days'             => $window_days,
			'total'                   => $total,
			'successful'              => $counts['completed'],
			'failed'                  => $counts['failed'],
			'partial'                 => $counts['partial'],
			'success_rate'            => $total > 0 ? round( ( $counts['completed'] / $total ) * 100, 1 ) : 0.0,
			'failure_rate'            => $total > 0 ? round( ( $counts['failed'] / $total ) * 100, 1 ) : 0.0,
			'avg_duration_seconds'    => $durations['avg'],
			'p50_duration_seconds'    => $durations['p50'],
			'p95_duration_seconds'    => $durations['p95'],
			'avg_ai_calls_per_post'   => $ai_calls,
			'image_failure_rate'      => $image_failure_rate,
			'schedule_success_rate'   => $schedule_success_rate,
			'recent_outcomes'         => $recent_outcomes,
		);

		set_transient( $cache_key, $metrics, self::CACHE_TTL );

		return $metrics;
	}

	/**
	 * Queue-depth surrogate metrics.
	 *
	 * @return array {
	 *     @type int $active_schedules  Number of active (enabled) schedule records.
	 *     @type int $approved_topics   Number of author topics in approved status.
	 * }
	 */
	public function get_queue_depth_metrics() {
		$cached = get_transient( self::TRANSIENT_QUEUE );
		if ( $cached !== false ) {
			return $cached;
		}

		$active_schedules = (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_schedule} WHERE is_active = 1"
		);

		$approved_topics = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_author_topics} WHERE status = %s",
				'approved'
			)
		);

		$metrics = array(
			'active_schedules' => $active_schedules,
			'approved_topics'  => $approved_topics,
		);

		set_transient( self::TRANSIENT_QUEUE, $metrics, self::CACHE_TTL );

		return $metrics;
	}

	/**
	 * Queue-health indicators: backlog, stuck jobs, retry saturation, and
	 * circuit-breaker state.
	 *
	 * These signals are designed to help operators identify queued work that
	 * is failing to make progress.  All queries are read-only against existing
	 * tables; no schema changes are required.
	 *
	 * @return array {
	 *     @type int        $pending_count              Jobs in 'pending' status right now.
	 *     @type int        $partial_count              Jobs in 'partial' status right now.
	 *     @type int        $stuck_count                Jobs in 'pending' or 'partial' status
	 *                                                  created more than STUCK_JOB_THRESHOLD_MINUTES ago.
	 *     @type int|null   $oldest_stuck_age_minutes   Age (minutes) of the oldest stuck job, or null if none.
	 *     @type int        $failed_24h                 Jobs that transitioned to 'failed' in the last 24 hours
	 *                                                  (window based on completed_at).
	 *     @type float      $retry_saturation_pct       Percentage of jobs that completed or failed in the last
	 *                                                  24 hours (window based on completed_at) that failed
	 *                                                  (proxy for retry pressure; 0–100 or -1 if no data).
	 *     @type array      $circuit_breaker            State dict from AIPS_Resilience_Service, or
	 *                                                  array('state'=>'unknown') when unavailable.
	 * }
	 */
	public function get_queue_health_metrics() {
				$now_ts = AIPS_DateTime::now()->timestamp();

		$cached = get_transient( self::TRANSIENT_QUEUE_HEALTH );
		if ( $cached !== false ) {
			return $cached;
		}

		// --- Pending / partial backlog ---
		$pending_count = (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_history} WHERE status = 'pending'"
		);

		$partial_count = (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_history} WHERE status = 'partial'"
		);

		// --- Stuck jobs (pending or partial, older than threshold) ---
		$stuck_count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_history}
				WHERE status IN ('pending','partial')
				  AND created_at <= %d",
				$now_ts - ( self::STUCK_JOB_THRESHOLD_MINUTES * MINUTE_IN_SECONDS )
			)
		);

		$oldest_stuck_age_minutes = null;
		if ( $stuck_count > 0 ) {
			$stuck_cutoff = $now_ts - ( self::STUCK_JOB_THRESHOLD_MINUTES * MINUTE_IN_SECONDS );
			$age_raw = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT MIN(created_at)
					FROM {$this->table_history}
					WHERE status IN ('pending','partial')
					  AND created_at <= %d",
					$stuck_cutoff
				)
			);
			if ( $age_raw !== null ) {
				$oldest_stuck_age_minutes = (int) floor( ( $now_ts - (int) $age_raw ) / MINUTE_IN_SECONDS );
			}
		}

		// --- Recent failures (last 24 h) ---
		// Use completed_at so we capture jobs that started before the window but
		// failed within it, matching the docblock wording ("transitioned to failed").
		$failed_24h = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_history}
				WHERE status = 'failed'
				  AND completed_at IS NOT NULL
				  AND completed_at >= %d",
				$now_ts - ( self::RETRY_WINDOW_HOURS * HOUR_IN_SECONDS )
			)
		);

		// Retry saturation = failed / (completed + failed) over the same 24-h window.
		// We intentionally exclude 'partial' from the denominator: partial jobs are
		// still in-flight or abandoned, not cleanly completed or failed.
		// Use completed_at (not created_at) so long-running jobs that finish within
		// the window are counted correctly.
		$window_row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
					SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed
				FROM {$this->table_history}
				WHERE status IN ('completed','failed')
				  AND completed_at IS NOT NULL
				  AND completed_at >= %d",
				$now_ts - ( self::RETRY_WINDOW_HOURS * HOUR_IN_SECONDS )
			)
		);

		$retry_saturation_pct = -1.0;
		if ( $window_row ) {
			$w_completed = (int) ( $window_row->completed ?? 0 );
			$w_failed    = (int) ( $window_row->failed    ?? 0 );
			$w_total     = $w_completed + $w_failed;
			if ( $w_total > 0 ) {
				$retry_saturation_pct = round( ( $w_failed / $w_total ) * 100, 1 );
			}
		}

		// --- Circuit breaker state ---
		$circuit_breaker = array( 'state' => 'unknown' );
		if ( class_exists( 'AIPS_Resilience_Service' ) ) {
			try {
				$resilience      = new AIPS_Resilience_Service();
				$circuit_breaker = $resilience->get_circuit_breaker_status();
			} catch ( \Throwable $e ) {
				// Non-fatal — leave as unknown.
			}
		}

		$metrics = array(
			'pending_count'             => $pending_count,
			'partial_count'             => $partial_count,
			'stuck_count'               => $stuck_count,
			'oldest_stuck_age_minutes'  => $oldest_stuck_age_minutes,
			'failed_24h'                => $failed_24h,
			'retry_saturation_pct'      => $retry_saturation_pct,
			'circuit_breaker'           => $circuit_breaker,
		);

		set_transient( self::TRANSIENT_QUEUE_HEALTH, $metrics, self::CACHE_TTL );

		return $metrics;
	}

	/**
	 * Invalidate all cached metrics.
	 *
	 * Removes every `aips_metrics_generation_*` transient stored by this class
	 * regardless of window size, plus the queue-depth and queue-health transients.
	 *
	 * When WordPress is using an external object cache (e.g. Redis/Memcached),
	 * transients do not live in the options table, so `delete_transient()` is
	 * used for all known keys plus the standard common windows.  When no
	 * external cache is present the options table is queried to sweep every
	 * window suffix — both the value row and the paired timeout row — so no
	 * orphaned entries are left behind.
	 *
	 * Call this when history records are bulk-deleted or after schema upgrades
	 * so stale summaries are not presented.
	 *
	 * @return void
	 */
	public function invalidate_cache() {
		global $wpdb;

		// Common window values used by callers (covers the typical System Status view).
		$common_windows = array( 1, 7, 14, 30, 45, 60, 90 );

		if ( wp_using_ext_object_cache() ) {
			// External object cache: delete_transient() is the only reliable path.
			foreach ( $common_windows as $days ) {
				delete_transient( self::TRANSIENT_GENERATION . '_' . $days );
			}
		} elseif ( ! empty( $wpdb->options ) ) {
			// No external cache: sweep via SQL so arbitrary window suffixes
			// (e.g. window=45) are also removed.  Delete both the value row and
			// the paired timeout row to avoid orphaned options accumulating.
			$value_prefix   = $wpdb->esc_like( '_transient_' . self::TRANSIENT_GENERATION . '_' );
			$timeout_prefix = $wpdb->esc_like( '_transient_timeout_' . self::TRANSIENT_GENERATION . '_' );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options}
					WHERE option_name LIKE %s
					   OR option_name LIKE %s",
					$value_prefix . '%',
					$timeout_prefix . '%'
				)
			);
		} else {
			// Fallback for test environments where $wpdb->options is absent.
			foreach ( $common_windows as $days ) {
				delete_transient( self::TRANSIENT_GENERATION . '_' . $days );
			}
		}

		delete_transient( self::TRANSIENT_QUEUE );
		delete_transient( self::TRANSIENT_QUEUE_HEALTH );
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Count history records by status within a time window.
	 *
	 * The window boundary is computed in MySQL using UTC_TIMESTAMP() to match
	 * the timezone used by CURRENT_TIMESTAMP (DEFAULT) on the history table.
	 *
	 * @param int $window_days Number of days to look back.
	 * @return array {
	 *     @type int $total     Total records.
	 *     @type int $completed Completed count.
	 *     @type int $failed    Failed count.
	 *     @type int $partial   Partial count.
	 * }
	 */
	private function get_generation_counts( $window_days ) {
		$cutoff = AIPS_DateTime::now()->timestamp() - ( (int) $window_days * DAY_IN_SECONDS );

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					COUNT(*) AS total,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
					SUM(CASE WHEN status = 'failed'    THEN 1 ELSE 0 END) AS failed,
					SUM(CASE WHEN status = 'partial'   THEN 1 ELSE 0 END) AS partial
				FROM {$this->table_history}
				WHERE created_at >= %d",
				$cutoff
			)
		);

		if ( ! $row ) {
			return array( 'total' => 0, 'completed' => 0, 'failed' => 0, 'partial' => 0 );
		}

		return array(
			'total'     => isset( $row->total )     ? (int) $row->total     : 0,
			'completed' => isset( $row->completed ) ? (int) $row->completed : 0,
			'failed'    => isset( $row->failed )    ? (int) $row->failed    : 0,
			'partial'   => isset( $row->partial )   ? (int) $row->partial   : 0,
		);
	}

	/**
	 * Compute average, median (p50), and 95th-percentile generation durations
	 * for completed records within the given window.
	 *
	 * Duration is measured from created_at to completed_at.  Records with a
	 * NULL completed_at are excluded.  The window boundary uses
	 * CURRENT_TIMESTAMP() to match how `created_at` is written by the DB.
	 *
	 * @param int $window_days Number of days to look back.
	 * @return array {
	 *     @type int $avg Average duration in seconds (0 if no data).
	 *     @type int $p50 Median duration in seconds (0 if no data).
	 *     @type int $p95 95th-percentile duration in seconds (0 if no data).
	 * }
	 */
	private function get_duration_percentiles( $window_days ) {
		$cutoff = AIPS_DateTime::now()->timestamp() - ( (int) $window_days * DAY_IN_SECONDS );

		// Fetch all durations so we can compute percentiles in PHP without
		// relying on ROW_NUMBER() / NTILE() which require MySQL 8+.
		$rows = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT (completed_at - created_at) AS duration
				FROM {$this->table_history}
				WHERE status = 'completed'
				  AND completed_at IS NOT NULL
				  AND created_at >= %d
				ORDER BY duration ASC",
				$cutoff
			)
		);

		if ( empty( $rows ) ) {
			return array( 'avg' => 0, 'p50' => 0, 'p95' => 0 );
		}

		$values = array_map( 'intval', $rows );
		$count  = count( $values );

		$avg = (int) round( array_sum( $values ) / $count );
		$p50 = $this->percentile( $values, 50 );
		$p95 = $this->percentile( $values, 95 );

		return array( 'avg' => $avg, 'p50' => $p50, 'p95' => $p95 );
	}

	/**
	 * Compute a percentile value from a sorted array of integers.
	 *
	 * Uses the nearest-rank method: index = floor( (pct/100) * (count - 1) ).
	 *
	 * @param int[] $sorted_values Values in ascending order.
	 * @param int   $pct           Percentile to compute (0–100).
	 * @return int Percentile value.
	 */
	private function percentile( array $sorted_values, $pct ) {
		$count = count( $sorted_values );
		if ( $count === 0 ) {
			return 0;
		}
		$index = (int) floor( ( $pct / 100 ) * ( $count - 1 ) );
		$index = max( 0, min( $index, $count - 1 ) );
		return (int) $sorted_values[ $index ];
	}

	/**
	 * Average number of AI requests per completed history record.
	 *
	 * Uses a LEFT JOIN from completed history records so posts with zero
	 * matching AI request entries are counted as 0 rather than excluded,
	 * giving an accurate population average.  The window boundary uses
	 * CURRENT_TIMESTAMP() to stay in the same timezone as the DB default.
	 *
	 * AI requests are identified by `log_type = 'ai_request'` — the value the
	 * generator writes via `record('ai_request', ...)` for every AI call.
	 *
	 * @param int $window_days Number of days to look back.
	 * @return float Average count (0.0 if no data).
	 */
	private function get_avg_ai_calls_per_post( $window_days ) {
		$cutoff = AIPS_DateTime::now()->timestamp() - ( (int) $window_days * DAY_IN_SECONDS );

		// Compute average AI request entries per completed history record,
		// including posts with zero AI requests (counted as 0).
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					CASE
						WHEN COALESCE(stats.total_completed, 0) > 0
							THEN COALESCE(stats.total_ai_calls, 0) / stats.total_completed
						ELSE 0
					END AS avg_calls
				FROM (
					SELECT
						COUNT(DISTINCT h.id) AS total_completed,
						COUNT(hl.id) AS total_ai_calls
					FROM {$this->table_history} h
					LEFT JOIN {$this->table_history_log} hl
						ON hl.history_id = h.id
						AND hl.log_type = 'ai_request'
					WHERE h.status = 'completed'
					  AND h.created_at >= %d
				) AS stats",
				$cutoff
			)
		);

		if ( ! $row || ! isset( $row->avg_calls ) || $row->avg_calls === null ) {
			return 0.0;
		}

		return round( (float) $row->avg_calls, 2 );
	}

	/**
	 * Image generation failure rate, derived from `metric_generation_result`
	 * log entries written by the generator into `aips_history_log`.
	 *
	 * Only posts where image generation was actually attempted are counted
	 * (i.e. entries where `image_attempted` is true in the stored JSON).
	 * This replaces the old post-meta approach which could balloon on installs
	 * with thousands of posts.
	 *
	 * The window boundary uses CURRENT_TIMESTAMP() to match how `created_at`
	 * is written by the DB.
	 *
	 * @param int $window_days Number of days to look back.
	 * @return float Failure rate as a percentage (0–100), or -1.0 if no data.
	 */
	private function get_image_failure_rate( $window_days ) {
		$cutoff = AIPS_DateTime::now()->timestamp() - ( (int) $window_days * DAY_IN_SECONDS );

		// Total image-generation attempts within the window.
		// The generator records "image_attempted":true only when
		// context->should_generate_featured_image() is true.
		$total = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$this->table_history_log} hl
				INNER JOIN {$this->table_history} h ON hl.history_id = h.id
				WHERE hl.log_type = %s
				  AND hl.details LIKE %s
				  AND h.created_at >= %d",
				'metric_generation_result',
				'%"image_attempted":true%',
				$cutoff
			)
		);

		if ( $total === 0 ) {
			return -1.0; // No image-generation data available yet.
		}

		// Subset where image generation failed (image_success:false).
		$failed = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$this->table_history_log} hl
				INNER JOIN {$this->table_history} h ON hl.history_id = h.id
				WHERE hl.log_type = %s
				  AND hl.details LIKE %s
				  AND hl.details LIKE %s
				  AND h.created_at >= %d",
				'metric_generation_result',
				'%"image_attempted":true%',
				'%"image_success":false%',
				$cutoff
			)
		);

		return round( ( $failed / $total ) * 100, 1 );
	}

	/**
	 * Success rate for scheduled generation runs.
	 *
	 * Uses history records with creation_method='scheduled' to determine what
	 * fraction of automated runs resulted in a completed post.  The window
	 * boundary uses CURRENT_TIMESTAMP() to match how `created_at` is written.
	 *
	 * @param int $window_days Number of days to look back.
	 * @return float Success rate as a percentage (0–100), or -1 if no data.
	 */
	private function get_schedule_success_rate( $window_days ) {
		$cutoff = AIPS_DateTime::now()->timestamp() - ( (int) $window_days * DAY_IN_SECONDS );

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT
					COUNT(*) AS total,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
				FROM {$this->table_history}
				WHERE creation_method = %s
				  AND created_at >= %d",
				'scheduled',
				$cutoff
			)
		);

		if ( ! $row || ! isset( $row->total ) || (int) $row->total === 0 ) {
			return -1.0; // No scheduled-run data available.
		}

		return round( ( (int) $row->completed / (int) $row->total ) * 100, 1 );
	}

	/**
	 * Most-recent N generation outcomes.
	 *
	 * @param int $limit Number of records to return.
	 * @return array Each element: { id, status, creation_method, created_at, error_message }.
	 */
	private function get_recent_outcomes( $limit = 10 ) {
		$limit = max( 1, (int) $limit );
		$rows  = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, status, creation_method, created_at,
				        (completed_at - created_at) AS duration_seconds,
				        error_message
				FROM {$this->table_history}
				ORDER BY created_at DESC
				LIMIT %d",
				$limit
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$outcomes = array();
		foreach ( $rows as $row ) {
			$outcomes[] = array(
				'id'              => (int) $row->id,
				'status'          => (string) $row->status,
				'creation_method' => (string) $row->creation_method,
				'created_at'      => (string) $row->created_at,
				'duration_seconds'=> $row->duration_seconds !== null ? (int) $row->duration_seconds : null,
				'error_message'   => ( $row->error_message !== null && $row->error_message !== '' )
					? (string) $row->error_message
					: null,
			);
		}

		return $outcomes;
	}
}
