<?php
/**
 * Job Progress Tracker Service
 *
 * Manages batch progress state for resumable operations.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Job_Progress_Tracker
 *
 * Handles saving, loading, and clearing batch progress cursors to enable
 * resumable generation operations that can survive crashes or interruptions.
 */
class AIPS_Job_Progress_Tracker {

	/**
	 * @var AIPS_Schedule_Repository_Interface Schedule repository for progress storage
	 */
	private $repository;

	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Schedule_Repository_Interface|null $repository Optional repository.
	 * @param AIPS_Logger|null                        $logger     Optional logger.
	 */
	public function __construct(
		?AIPS_Schedule_Repository_Interface $repository = null,
		?AIPS_Logger $logger = null
	) {
		$container = AIPS_Container::get_instance();

		$this->repository = $repository ?: ($container->has(AIPS_Schedule_Repository_Interface::class)
			? $container->make(AIPS_Schedule_Repository_Interface::class)
			: new AIPS_Schedule_Repository());

		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class)
			? $container->make(AIPS_Logger_Interface::class)
			: new AIPS_Logger());
	}

	/**
	 * Save batch progress for a job.
	 *
	 * @param string $job_key     Unique identifier for the job (e.g., 'schedule_123').
	 * @param array  $progress    {
	 *     Progress data to save.
	 *
	 *     @type int   $completed  Number of items completed.
	 *     @type int   $total      Total items in the batch.
	 *     @type int   $last_index Last processed index (0-based).
	 *     @type array $post_ids   Optional. IDs of created posts (for atomicity).
	 * }
	 * @return bool True on success, false on failure.
	 */
	public function save_progress(string $job_key, array $progress): bool {
		// Extract schedule ID from job_key (assumes format: 'schedule_123')
		if (strpos($job_key, 'schedule_') === 0) {
			$schedule_id = (int) substr($job_key, 9);

			if ($schedule_id > 0) {
				return $this->repository->update_batch_progress(
					$schedule_id,
					isset($progress['completed']) ? (int) $progress['completed'] : 0,
					isset($progress['total']) ? (int) $progress['total'] : 0,
					isset($progress['last_index']) ? (int) $progress['last_index'] : 0,
					isset($progress['post_ids']) && is_array($progress['post_ids']) ? $progress['post_ids'] : array()
				);
			}
		}

		// For non-schedule jobs, we'd need a different storage mechanism
		// For now, log and return false
		$this->logger->log(
			sprintf('Cannot save progress for non-schedule job key: %s', $job_key),
			'warning',
			$progress
		);

		return false;
	}

	/**
	 * Load batch progress for a job.
	 *
	 * @param string $job_key Unique identifier for the job.
	 * @return array|null Progress data if found, null otherwise.
	 */
	public function load_progress(string $job_key): ?array {
		// Extract schedule ID from job_key
		if (strpos($job_key, 'schedule_') === 0) {
			$schedule_id = (int) substr($job_key, 9);

			if ($schedule_id > 0) {
				$schedule = $this->repository->get_by_id($schedule_id);

				if ($schedule && !empty($schedule->batch_progress)) {
					$saved = json_decode($schedule->batch_progress, true);

					if (is_array($saved)) {
						return $saved;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Clear batch progress for a job.
	 *
	 * @param string $job_key Unique identifier for the job.
	 * @return bool True on success, false on failure.
	 */
	public function clear_progress(string $job_key): bool {
		// Extract schedule ID from job_key
		if (strpos($job_key, 'schedule_') === 0) {
			$schedule_id = (int) substr($job_key, 9);

			if ($schedule_id > 0) {
				return $this->repository->clear_batch_progress($schedule_id);
			}
		}

		return false;
	}

	/**
	 * Update run state for a job.
	 *
	 * Stores execution state metadata (status, error, completion info, etc.).
	 *
	 * @param string $job_key Unique identifier for the job.
	 * @param array  $state   {
	 *     State data to save.
	 *
	 *     @type string $status         Status string (e.g., 'success', 'failed', 'partial', 'batch_queued').
	 *     @type string $error_code     Optional error code.
	 *     @type string $error_message  Optional error message.
	 *     @type int    $completed      Number of items completed.
	 *     @type int    $total          Total items expected.
	 *     @type string $correlation_id Correlation ID for tracing.
	 *     @type string $timestamp      ISO 8601 timestamp.
	 *     @type int    $dispatched_at  Unix timestamp when batch was dispatched.
	 *     @type int    $num_batches    Number of batches (for batch_queued status).
	 *     @type int    $scheduled_batches Number of successfully scheduled batches.
	 * }
	 * @return bool True on success, false on failure.
	 */
	public function update_run_state(string $job_key, array $state): bool {
		// Extract schedule ID from job_key
		if (strpos($job_key, 'schedule_') === 0) {
			$schedule_id = (int) substr($job_key, 9);

			if ($schedule_id > 0) {
				return $this->repository->update_run_state($schedule_id, $state);
			}
		}

		$this->logger->log(
			sprintf('Cannot update run state for non-schedule job key: %s', $job_key),
			'warning',
			$state
		);

		return false;
	}

	/**
	 * Load run state for a job.
	 *
	 * @param string $job_key Unique identifier for the job.
	 * @return array|null State data if found, null otherwise.
	 */
	public function load_run_state(string $job_key): ?array {
		// Extract schedule ID from job_key
		if (strpos($job_key, 'schedule_') === 0) {
			$schedule_id = (int) substr($job_key, 9);

			if ($schedule_id > 0) {
				$schedule = $this->repository->get_by_id($schedule_id);

				if ($schedule && !empty($schedule->run_state)) {
					$decoded = json_decode($schedule->run_state, true);

					if (is_array($decoded)) {
						return $decoded;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Determine if progress is valid for resumption.
	 *
	 * Validates that the saved progress cursor is internally consistent
	 * and matches the expected total.
	 *
	 * @param array $progress     Loaded progress data.
	 * @param int   $expected_total Expected total items.
	 * @return bool True if progress is valid and resumable.
	 */
	public function is_progress_valid(array $progress, int $expected_total): bool {
		if (!isset($progress['completed'], $progress['total'], $progress['last_index'])) {
			return false;
		}

		$saved_completed = (int) $progress['completed'];
		$saved_total = (int) $progress['total'];
		$saved_last_index = (int) $progress['last_index'];

		// Validate consistency
		if ($saved_total !== $expected_total) {
			return false;
		}

		if ($saved_completed < 0 || $saved_completed >= $expected_total) {
			return false;
		}

		if ($saved_last_index < 0 || $saved_last_index >= $expected_total) {
			return false;
		}

		if ($saved_last_index < ($saved_completed - 1) || $saved_last_index > $saved_completed) {
			return false;
		}

		return true;
	}

	/**
	 * Calculate resume point from saved progress.
	 *
	 * When progress includes post_ids, use count(post_ids) as the authoritative
	 * source to prevent duplicate generation. Otherwise use last_index + 1.
	 *
	 * @param array $progress Loaded progress data.
	 * @return array{start_index: int, prior_completed: int, successful_post_ids: array} Resume information.
	 */
	public function calculate_resume_point(array $progress): array {
		$saved_post_ids = isset($progress['post_ids']) && is_array($progress['post_ids'])
			? array_map('absint', $progress['post_ids'])
			: array();

		if (!empty($saved_post_ids)) {
			// New cursor format: post_ids is authoritative
			return array(
				'start_index'         => count($saved_post_ids),
				'prior_completed'     => count($saved_post_ids),
				'successful_post_ids' => $saved_post_ids,
			);
		}

		// Legacy cursor: use last_index
		$saved_completed = isset($progress['completed']) ? (int) $progress['completed'] : 0;
		$saved_last_index = isset($progress['last_index']) ? (int) $progress['last_index'] : 0;

		return array(
			'start_index'         => $saved_last_index + 1,
			'prior_completed'     => $saved_completed,
			'successful_post_ids' => array(),
		);
	}
}
