<?php
/**
 * Batch Queue Service
 *
 * Detects "large" scheduled batches and dispatches them as a series of
 * time-spread WordPress single-cron events instead of attempting all posts
 * in one synchronous cron execution.
 *
 * Algorithm
 * ---------
 * A batch is "large" when post_quantity >= aips_large_batch_threshold (default 5).
 *
 * The number of batch jobs is min( aips_batch_max_jobs, ceil(quantity / 2) ),
 * capped so each job generates at least 2 posts where possible.
 *
 * Jobs are spread evenly over aips_batch_queue_window_seconds (default 600 = 10 min).
 * Batch 0 fires immediately; each subsequent job fires approximately
 * (window / (batches - 1)) seconds later.
 *
 * Filters
 * -------
 * aips_large_batch_threshold    int  Minimum post_quantity to trigger splitting. Default 5.
 * aips_batch_max_jobs           int  Maximum number of batch jobs per run. Default 10.
 * aips_batch_queue_window_seconds int  Seconds across which jobs are spread. Default 600.
 *
 * @package AI_Post_Scheduler
 * @since   2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Batch_Queue_Service
 *
 * Responsible for large-batch detection, batch-configuration calculation,
 * and dispatching individual wp_schedule_single_event calls spread across
 * a configurable time window.
 */
class AIPS_Batch_Queue_Service {

	/**
	 * Default minimum post_quantity that triggers the batch queue.
	 *
	 * At this quantity and above, generation is split across multiple cron events
	 * instead of running synchronously in one cron callback.
	 *
	 * @var int
	 */
	const DEFAULT_THRESHOLD = 5;

	/**
	 * Default maximum number of batch jobs created per schedule run.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_BATCHES = 10;

	/**
	 * Default time window (seconds) across which batch jobs are spread.
	 * 600 seconds = 10 minutes.
	 *
	 * @var int
	 */
	const DEFAULT_WINDOW_SECONDS = 600;

	/**
	 * Grace period (seconds) added to the window when checking whether a
	 * previously dispatched batch queue is still considered "in flight".
	 *
	 * This ensures the hourly cron worker does not re-dispatch if the last
	 * batch job is still running just after the window expires.
	 *
	 * @var int
	 */
	const REDISPATCH_GRACE_SECONDS = 120;

	/**
	 * WordPress cron action hook name for individual batch jobs.
	 *
	 * @var string
	 */
	const HOOK = 'aips_process_schedule_batch';

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @var AIPS_Job_Scheduler Centralized job scheduler service
	 */
	private $job_scheduler;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Logger|null        $logger        Optional logger for dispatch diagnostics.
	 * @param AIPS_Job_Scheduler|null $job_scheduler Optional job scheduler service.
	 */
	public function __construct( ?AIPS_Logger $logger = null, ?AIPS_Job_Scheduler $job_scheduler = null ) {
		$this->logger = $logger ?: new AIPS_Logger();
		$this->job_scheduler = $job_scheduler ?: new AIPS_Job_Scheduler(null, null, $this->logger);
	}

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Determine whether a post quantity qualifies as a large batch.
	 *
	 * Returns true when $post_quantity is at or above the configured threshold,
	 * meaning that the caller should dispatch() the work rather than running all
	 * posts synchronously.
	 *
	 * The threshold is enforced to be at least 2 so a single-post schedule is
	 * never accidentally routed through the batch queue.
	 *
	 * @param int $post_quantity Total posts the schedule wants to generate.
	 * @return bool True when batch queue should be used.
	 */
	public function needs_batch_queue(int $post_quantity): bool {
		return $post_quantity >= $this->get_large_batch_threshold();
	}

	/**
	 * Return the configured batch queue window in seconds.
	 *
	 * Centralises the aips_batch_queue_window_seconds filter so callers
	 * do not need to duplicate the apply_filters() call.
	 *
	 * @return int Spread window in seconds (minimum 0).
	 */
	public function get_window_seconds(): int {
		return $this->get_batch_window_seconds();
	}

	/**
	 * Calculate the batch configuration for a given post quantity.
	 *
	 * Returns an array describing how to split $post_quantity into jobs and
	 * how to space those jobs across the configured window.
	 *
	 * @param int $post_quantity Total posts to generate.
	 * @return array{
	 *   num_batches: int,
	 *   posts_per_batch: int,
	 *   window_seconds: int,
	 *   interval_seconds: float
	 * }
	 */
	public function calculate_config(int $post_quantity): array {
		$config = $this->job_scheduler->get_slicer()->calculate_slices($post_quantity, array(
			'context'        => 'schedule',
			'max_slices'     => $this->get_max_batches(),
			'window_seconds' => $this->get_batch_window_seconds(),
		));

		return $config->to_array();
	}

	/**
	 * Dispatch batch jobs for a large schedule run.
	 *
	 * Registers one wp_schedule_single_event per batch, spread evenly across
	 * the configured time window starting from $base_timestamp.
	 *
	 * Each event fires the HOOK action with args:
	 *   [ schedule_id, start_index, batch_size, total_quantity, correlation_id ]
	 *
	 * @param int    $schedule_id    Schedule ID.
	 * @param int    $post_quantity  Posts to dispatch in this call. When resuming an
	 *                               interrupted batch this is the remaining count, not
	 *                               the schedule's original total.
	 * @param int    $base_timestamp Unix timestamp when the schedule was triggered.
	 * @param string $correlation_id Correlation ID for tracing (may be empty string).
	 * @param array  $options {
	 *     Optional resume positioning.
	 *
	 *     @type int $index_offset   Absolute index the first slice starts at (default 0).
	 *     @type int $total_override Total reported to the hook instead of $post_quantity.
	 * }
	 * @return array|WP_Error{
	 *   num_batches: int,
	 *   posts_per_batch: int,
	 *   window_seconds: int,
	 *   scheduled_batches: int
	 * } Dispatch summary suitable for history logging.
	 */
	public function dispatch(
		int $schedule_id,
		int $post_quantity,
		int $base_timestamp,
		string $correlation_id = '',
		array $options = array()
	) {
		$batch_options = array(
			'prefix_args'     => array( $schedule_id ),
			'base_timestamp'  => $this->normalize_base_timestamp( $base_timestamp ),
			'context'         => 'schedule',
			'correlation_id'  => $correlation_id,
		);

		if ( isset( $options['index_offset'] ) ) {
			$batch_options['index_offset'] = max( 0, (int) $options['index_offset'] );
		}

		if ( isset( $options['total_override'] ) ) {
			$batch_options['total_override'] = max( 0, (int) $options['total_override'] );
		}

		$result = $this->job_scheduler->schedule_batched(
			self::HOOK,
			$post_quantity,
			$batch_options
		);

		if (is_wp_error($result)) {
			return $result;
		}

		// When dispatching schedule-specific batches, create a canonical batch_run
		// row so the resume path can rely on a first-class DB record instead of
		// ad-hoc JSON stored in the schedule row.
		try {
			$schedule_id_for_row = isset($batch_options['prefix_args'][0]) ? (int) $batch_options['prefix_args'][0] : 0;
			$index_offset = isset($batch_options['index_offset']) ? (int) $batch_options['index_offset'] : 0;
			$total_override = isset($batch_options['total_override']) ? $batch_options['total_override'] : null;

			if ($schedule_id_for_row > 0) {
				$total = $total_override !== null ? (int) $total_override : ($post_quantity + $index_offset);
				$completed = $index_offset;

				if ($total > 0 && $completed < $total) {
					// Use WordPress UUID when available; fallback to a unique md5 id.
					$batch_uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : md5(uniqid((string) $schedule_id_for_row, true));
					$repo = null;
					if (class_exists('AIPS_Schedule_Repository')) {
						$repo = AIPS_Schedule_Repository::instance();
					}

					if ($repo && method_exists($repo, 'create_batch_run')) {
						$created = $repo->create_batch_run($schedule_id_for_row, $batch_uuid, $total, $completed, $completed, array(), 'pending');
						if ($created) {
							$this->logger->log(
								sprintf('Created batch_run %s for schedule %d (total=%d, completed=%d)', $batch_uuid, $schedule_id_for_row, $total, $completed),
								'info',
								array('schedule_id' => $schedule_id_for_row, 'batch_uuid' => $batch_uuid)
							);
						}
					}
				}
			}
		} catch (Exception $e) {
			// Don't let batch-run bookkeeping break dispatch; log and continue.
			$this->logger->log('Batch queue: failed to create batch_run row — ' . $e->getMessage(), 'warning');
		}

		// Convert to legacy format
		return $result->to_array();
	}

	/**
	 * Cancel every queued batch slice belonging to one schedule.
	 *
	 * A terminated batch leaves its remaining slices sitting in the cron table.
	 * They no-op only for as long as the schedule's run_state stays terminal, so
	 * they must be cleared before a resumed run puts that run_state back to
	 * 'batch_processing' — otherwise the stale slices and the resumed slices
	 * would both generate posts.
	 *
	 * @param int $schedule_id Schedule ID whose queued slices should be cancelled.
	 * @return int Number of events unscheduled.
	 */
	public function clear_pending_slices( int $schedule_id ): int {
		$cron = _get_cron_array();

		if ( ! is_array( $cron ) ) {
			return 0;
		}

		$cleared = 0;

		foreach ( $cron as $timestamp => $hooks ) {
			if ( ! isset( $hooks[ self::HOOK ] ) || ! is_array( $hooks[ self::HOOK ] ) ) {
				continue;
			}

			foreach ( $hooks[ self::HOOK ] as $event ) {
				$args = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();

				// Args layout is [ schedule_id, start_index, batch_size, total, correlation_id ].
				if ( empty( $args ) || (int) $args[0] !== $schedule_id ) {
					continue;
				}

				if ( wp_unschedule_event( (int) $timestamp, self::HOOK, $args ) !== false ) {
					$cleared++;
				}
			}
		}

		if ( $cleared > 0 ) {
			$this->logger->log(
				sprintf( 'Cleared %d queued batch slice(s) for schedule %d.', $cleared, $schedule_id ),
				'info'
			);
		}

		return $cleared;
	}

	/**
	 * Generic batch dispatcher usable by any generation type.
	 *
	 * Splits $item_count into batches, spreads them across the configured time
	 * window, and registers a wp_schedule_single_event per batch.
	 *
	 * The args array passed to each cron event is:
	 *   [ ...$prefix_args, start_index, batch_size, total_quantity, correlation_id ]
	 *
	 * This layout mirrors the existing schedule-batch convention so that hook
	 * callbacks can always find start_index, batch_size, and total_quantity at
	 * predictable offsets after any caller-specific prefix arguments.
	 *
	 * @param string   $hook           WordPress cron hook to schedule.
	 * @param int      $item_count     Total items to process.
	 * @param int      $base_timestamp Unix timestamp for the first batch.
	 * @param array    $prefix_args    Caller-specific args prepended to each event's args array.
	 *                                 Must be serialisable (no closures).
	 * @param string   $correlation_id Correlation ID for tracing (may be empty string).
	 * @return array|WP_Error{
	 *   num_batches: int,
	 *   posts_per_batch: int,
	 *   window_seconds: int,
	 *   scheduled_batches: int
	 * } Dispatch summary suitable for history logging.
	 */
	public function dispatch_generic(
		string $hook,
		int $item_count,
		int $base_timestamp,
		array $prefix_args = array(),
		string $correlation_id = ''
	) {
		$result = $this->job_scheduler->schedule_batched(
			$hook,
			$item_count,
			array(
				'prefix_args'     => $prefix_args,
				'base_timestamp'  => $this->normalize_base_timestamp( $base_timestamp ),
				'context'         => 'default',
				'correlation_id'  => $correlation_id,
			)
		);

		if (is_wp_error($result)) {
			return $result;
		}

		// Convert to legacy format
		return $result->to_array();
	}

	/**
	 * Normalise a batch base timestamp so dispatch is not anchored in the past.
	 *
	 * When cron is delayed, callers may pass a stale timestamp. Anchoring queued
	 * slices to "now" avoids scheduling immediately-overdue events in the past.
	 *
	 * @param int $base_timestamp Requested base timestamp.
	 * @return int Normalized timestamp at or after current time.
	 */
	private function normalize_base_timestamp( int $base_timestamp ): int {
		$now = AIPS_DateTime::now()->timestamp();

		if ( $base_timestamp >= $now ) {
			return $base_timestamp;
		}

		$this->logger->log(
			sprintf(
				'Batch queue dispatch requested with past base timestamp %d; normalizing to %d.',
				$base_timestamp,
				$now
			),
			'warning'
		);

		return $now;
	}

	/**
	 * Resolve large-batch threshold with backward-compatible filter support.
	 *
	 * @return int
	 */
	public function get_large_batch_threshold(): int {
		$threshold = (int) apply_filters(
			'aips_large_batch_threshold',
			apply_filters(
				'aips_batch_threshold_schedule',
				apply_filters('aips_batch_threshold', self::DEFAULT_THRESHOLD)
			)
		);

		return max(2, $threshold);
	}

	/**
	 * Resolve maximum batches with backward-compatible filter support.
	 *
	 * @return int
	 */
	private function get_max_batches(): int {
		$max_batches = (int) apply_filters(
			'aips_batch_max_jobs',
			apply_filters(
				'aips_batch_max_slices_schedule',
				apply_filters('aips_batch_max_slices', self::DEFAULT_MAX_BATCHES)
			)
		);

		return max(1, $max_batches);
	}

	/**
	 * Resolve batch window with backward-compatible filter support.
	 *
	 * @return int
	 */
	private function get_batch_window_seconds(): int {
		$window_seconds = (int) apply_filters(
			'aips_batch_queue_window_seconds',
			apply_filters(
				'aips_batch_window_seconds_schedule',
				apply_filters('aips_batch_window_seconds', self::DEFAULT_WINDOW_SECONDS)
			)
		);

		return max(0, $window_seconds);
	}
}
