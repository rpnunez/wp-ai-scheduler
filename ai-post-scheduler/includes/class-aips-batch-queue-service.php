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
	 * WordPress cron action hook name for individual batch jobs.
	 *
	 * @var string
	 */
	const HOOK = 'aips_process_schedule_batch';

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
		$threshold = max(2, (int) apply_filters('aips_large_batch_threshold', self::DEFAULT_THRESHOLD));
		return $post_quantity >= $threshold;
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
		$max_batches    = max(1, (int) apply_filters('aips_batch_max_jobs', self::DEFAULT_MAX_BATCHES));
		$window_seconds = max(0, (int) apply_filters('aips_batch_queue_window_seconds', self::DEFAULT_WINDOW_SECONDS));

		// Aim for ~2 posts per job, but never exceed max_batches.
		$num_batches     = min($max_batches, (int) ceil($post_quantity / 2));
		$num_batches     = max(1, $num_batches);
		$posts_per_batch = (int) ceil($post_quantity / $num_batches);

		// Spread jobs evenly across the window.
		// If only 1 batch, no spread is needed.
		$interval_seconds = ($num_batches > 1)
			? (float) ($window_seconds / ($num_batches - 1))
			: 0.0;

		return array(
			'num_batches'      => $num_batches,
			'posts_per_batch'  => $posts_per_batch,
			'window_seconds'   => $window_seconds,
			'interval_seconds' => $interval_seconds,
		);
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
	 * @param int    $post_quantity  Total posts the schedule should generate.
	 * @param int    $base_timestamp Unix timestamp when the schedule was triggered.
	 * @param string $correlation_id Correlation ID for tracing (may be empty string).
	 * @return array{
	 *   num_batches: int,
	 *   posts_per_batch: int,
	 *   window_seconds: int
	 * } Dispatch summary suitable for history logging.
	 */
	public function dispatch(
		int $schedule_id,
		int $post_quantity,
		int $base_timestamp,
		string $correlation_id = ''
	): array {
		$config           = $this->calculate_config($post_quantity);
		$num_batches      = $config['num_batches'];
		$posts_per_batch  = $config['posts_per_batch'];
		$interval_seconds = $config['interval_seconds'];

		for ($batch = 0; $batch < $num_batches; $batch++) {
			$start_index     = $batch * $posts_per_batch;
			$this_batch_size = min($posts_per_batch, $post_quantity - $start_index);

			if ($this_batch_size <= 0) {
				break;
			}

			// Batch 0 fires at base_timestamp (immediately); subsequent batches
			// are staggered by interval_seconds each.
			$delay   = (int) round($batch * $interval_seconds);
			$fire_at = $base_timestamp + $delay;

			wp_schedule_single_event(
				$fire_at,
				self::HOOK,
				array(
					$schedule_id,
					$start_index,
					$this_batch_size,
					$post_quantity,
					$correlation_id,
				)
			);
		}

		return array(
			'num_batches'     => $num_batches,
			'posts_per_batch' => $posts_per_batch,
			'window_seconds'  => $config['window_seconds'],
		);
	}
}
