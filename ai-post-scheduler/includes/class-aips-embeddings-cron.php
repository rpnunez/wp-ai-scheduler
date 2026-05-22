<?php
/**
 * Embeddings Cron Handler
 *
 * Background worker for processing topic embeddings in batches.
 * Processes approved topics incrementally to avoid timeouts.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Embeddings_Cron
 *
 * Handles background processing of topic embeddings for authors.
 */
class AIPS_Embeddings_Cron {

	/**
	 * Cron hook used for background embeddings processing.
	 *
	 * @var string
	 */
	const HOOK = 'aips_process_author_embeddings';

	/**
	 * Completion hook fired when an author's embeddings finish processing.
	 *
	 * @var string
	 */
	const COMPLETED_HOOK = 'aips_author_embeddings_completed';

	/**
	 * Per-author progress transient prefix.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'aips_embeddings_progress_';

	/**
	 * Default topics-per-batch for embeddings processing.
	 *
	 * @var int
	 */
	const DEFAULT_BATCH_SIZE = 20;

	/**
	 * Maximum allowed topics-per-batch for embeddings processing.
	 *
	 * @var int
	 */
	const MAX_BATCH_SIZE = 100;

	/**
	 * Delay, in seconds, before scheduling the next background batch.
	 *
	 * @var int
	 */
	const RESCHEDULE_DELAY = 5;

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
	 * @var AIPS_Topic_Expansion_Service Topic expansion service
	 */
	private $expansion_service;

	/**
	 * @var AIPS_Logger_Interface Logger instance
	 */
	private $logger;

	/**
	 * @var AIPS_History_Service_Interface History service for logging
	 */
	private $history_service;

	/**
	 * @var AIPS_Job_Scheduler Job scheduler service
	 */
	private $job_scheduler;

	/**
	 * Initialize the cron handler.
	 *
	 * @param AIPS_Topic_Expansion_Service|null $expansion_service Topic expansion service.
	 * @param AIPS_Logger_Interface|null          $logger            Logger instance.
	 * @param AIPS_History_Service_Interface|null $history_service   History service.
	 * @param AIPS_Job_Scheduler|null             $job_scheduler     Job scheduler service.
	 */
	public function __construct($expansion_service = null, ?AIPS_Logger_Interface $logger = null, ?AIPS_History_Service_Interface $history_service = null, ?AIPS_Job_Scheduler $job_scheduler = null) {
		$container = AIPS_Container::get_instance();
		$this->expansion_service = $expansion_service ?: new AIPS_Topic_Expansion_Service();
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->history_service = $history_service ?: ($container->has(AIPS_History_Service_Interface::class) ? $container->make(AIPS_History_Service_Interface::class) : new AIPS_History_Service());
		$this->job_scheduler = $job_scheduler ?: new AIPS_Job_Scheduler();
	}

	/**
	 * Normalize a requested embeddings batch size.
	 *
	 * @param int $batch_size Requested topics-per-batch value.
	 * @return int
	 */
	public static function sanitize_batch_size($batch_size) {
		return min(self::MAX_BATCH_SIZE, max(1, absint($batch_size ?: self::DEFAULT_BATCH_SIZE)));
	}

	/**
	 * Return the per-author progress transient key.
	 *
	 * @param int $author_id Author ID.
	 * @return string
	 */
	public static function get_progress_transient_key($author_id) {
		return self::TRANSIENT_PREFIX . absint($author_id);
	}

	/**
	 * Queue embeddings processing for a single author.
	 *
	 * @param int $author_id         Author ID.
	 * @param int $batch_size        Topics per batch.
	 * @param int $last_processed_id Last processed topic ID.
	 * @param int $delay             Delay before execution in seconds.
	 * @return bool
	 */
	public function queue_author_embeddings($author_id, $batch_size = self::DEFAULT_BATCH_SIZE, $last_processed_id = 0, $delay = 2) {
		return $this->schedule_next_batch($author_id, $batch_size, $last_processed_id, $delay);
	}

	/**
	 * Process a batch of topic embeddings for an author.
	 *
	 * Called by the 'aips_process_author_embeddings' action hook.
	 * Re-schedules itself if more work remains for the author.
	 *
	 * @param array $args Arguments array with keys: author_id, batch_size, last_processed_id.
	 * @return void
	 */
	public function process_author_embeddings($args) {
		$author_id = isset($args['author_id']) ? absint($args['author_id']) : 0;
		$batch_size = isset($args['batch_size']) ? self::sanitize_batch_size($args['batch_size']) : self::DEFAULT_BATCH_SIZE;
		$last_processed_id = isset($args['last_processed_id']) ? absint($args['last_processed_id']) : 0;

		if (!$author_id) {
			$this->logger->log('Embeddings cron: Invalid author_id', 'error');
			return;
		}

		$this->logger->log(
			sprintf(
				'Processing embeddings batch for author %d (batch_size: %d, last_processed_id: %d)',
				$author_id,
				$batch_size,
				$last_processed_id
			),
			'info'
		);

		// Get or create history container for this author's embeddings processing
		$history = $this->get_or_create_history_container($author_id);

		// Record batch start
		$history->record(
			'activity',
			sprintf(
				__('Processing embeddings batch: batch_size=%d, last_processed_id=%d', 'ai-post-scheduler'),
				$batch_size,
				$last_processed_id
			),
			array(
				'event_type' => 'embeddings_batch_start',
				'event_status' => 'processing',
			),
			null,
			array(
				'author_id'         => $author_id,
				'batch_size'        => $batch_size,
				'last_processed_id' => $last_processed_id,
			)
		);

		// Process the batch
		$result = $this->expansion_service->process_approved_embeddings_batch(
			$author_id,
			$batch_size,
			$last_processed_id
		);

		if (is_wp_error($result)) {
			$this->logger->log(
				sprintf(
					'Embeddings batch failed for author %d: %s',
					$author_id,
					$result->get_error_message()
				),
				'error'
			);

			// Log error to history
			$history->record_error(
				sprintf(
					__('Embeddings batch processing failed: %s', 'ai-post-scheduler'),
					$result->get_error_message()
				),
				array(
					'author_id' => $author_id,
					'error_code' => 'EMBEDDINGS_BATCH_FAILED',
				),
				$result
			);

			// Delete progress transient on error
			delete_transient(self::get_progress_transient_key($author_id));
			return;
		}

		// Record batch completion
		$history->record(
			'activity',
			sprintf(
				__('Batch completed: %d success, %d failed, %d skipped', 'ai-post-scheduler'),
				$result['success'],
				$result['failed'],
				$result['skipped']
			),
			array(
				'event_type' => 'embeddings_batch_complete',
				'event_status' => 'success',
			),
			null,
			array(
				'author_id'         => $author_id,
				'success'           => $result['success'],
				'failed'            => $result['failed'],
				'skipped'           => $result['skipped'],
				'last_processed_id' => $result['last_processed_id'],
				'done'              => $result['done'],
				'processed_count'   => $result['processed_count'],
			)
		);

		// Store progress in transient for UI tracking
		$progress_data = array(
			'success'           => $result['success'],
			'failed'            => $result['failed'],
			'skipped'           => $result['skipped'],
			'last_processed_id' => $result['last_processed_id'],
			'done'              => $result['done'],
			'processed_count'   => $result['processed_count'],
			'timestamp'         => current_time('timestamp'),
		);

		set_transient(self::get_progress_transient_key($author_id), $progress_data, HOUR_IN_SECONDS);

		// If not done, re-schedule the job
		if (!$result['done']) {
			$this->logger->log(
				sprintf(
					'More work remains for author %d (last_processed_id: %d). Re-scheduling...',
					$author_id,
					$result['last_processed_id']
				),
				'info'
			);

			if (!$this->queue_author_embeddings($author_id, $batch_size, $result['last_processed_id'], self::RESCHEDULE_DELAY)) {
				$this->logger->log(
					sprintf(
						'Failed to re-schedule embeddings batch for author %d.',
						$author_id
					),
					'error'
				);

				$history->record_error(
					__('Failed to queue the next embeddings batch.', 'ai-post-scheduler'),
					array(
						'author_id' => $author_id,
						'error_code' => 'EMBEDDINGS_RESCHEDULE_FAILED',
					)
				);

				delete_transient(self::get_progress_transient_key($author_id));
			}
		} else {
			$this->logger->log(
				sprintf(
					'Embeddings processing complete for author %d. Total: %d success, %d failed, %d skipped.',
					$author_id,
					$result['success'],
					$result['failed'],
					$result['skipped']
				),
				'info'
			);

			// Complete the history container
			$history->complete_success(array(
				'author_id' => $author_id,
				'total_success' => $result['success'],
				'total_failed' => $result['failed'],
				'total_skipped' => $result['skipped'],
			));

			// Delete progress transient on completion
			delete_transient(self::get_progress_transient_key($author_id));

			// Fire completion action hook
			do_action(self::COMPLETED_HOOK, $author_id, $result);
		}
	}

	/**
	 * Get or create a history container for author embeddings processing.
	 *
	 * Looks for an existing incomplete container for this author, or creates a new one.
	 *
	 * @param int $author_id Author ID.
	 * @return AIPS_History_Container History container instance.
	 */
	private function get_or_create_history_container($author_id) {
		// Try to find existing incomplete container for this author
		$existing = $this->history_service->find_incomplete('author_embeddings', array(
			'author_id' => $author_id,
		));

		if ($existing) {
			return $existing;
		}

		// Create new history container
		return $this->history_service->create('author_embeddings', array(
			'author_id' => $author_id,
		));
	}

	/**
	 * Schedule the next batch for an author.
	 *
	 * @param int $author_id         Author ID.
	 * @param int $batch_size        Batch size.
	 * @param int $last_processed_id Last processed topic ID.
	 * @param int $delay             Delay before execution in seconds.
	 * @return bool
	 */
	private function schedule_next_batch($author_id, $batch_size, $last_processed_id, $delay = self::RESCHEDULE_DELAY) {
		$args = array(
			'author_id'         => absint($author_id),
			'batch_size'        => self::sanitize_batch_size($batch_size),
			'last_processed_id' => absint($last_processed_id),
		);

		// Schedule to run in a few seconds
		$timestamp = AIPS_DateTime::now()->advance(max(0, absint($delay)))->timestamp();

		// Prefer Action Scheduler if available, otherwise use centralized job scheduler
		if (function_exists('as_schedule_single_action')) {
			return false !== call_user_func('as_schedule_single_action', $timestamp, self::HOOK, $args, 'aips-embeddings');
		}

		return $this->job_scheduler->schedule_simple(
			self::HOOK,
			$timestamp,
			array($args),
			array(
				'job_type'      => 'author_embeddings',
				'retry_options' => array(
					'max_attempts' => 3,
				),
			)
		);
	}
}
