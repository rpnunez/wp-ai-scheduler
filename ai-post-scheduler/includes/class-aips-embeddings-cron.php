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
	 * @var AIPS_Topic_Expansion_Service Topic expansion service
	 */
	private $expansion_service;

	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;

	/**
	 * Initialize the cron handler.
	 *
	 * @param AIPS_Topic_Expansion_Service|null $expansion_service Topic expansion service.
	 * @param AIPS_Logger|null                  $logger            Logger instance.
	 */
	public function __construct($expansion_service = null, $logger = null) {
		$this->expansion_service = $expansion_service ?: new AIPS_Topic_Expansion_Service();
		$this->logger = $logger ?: new AIPS_Logger();
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
		$batch_size = isset($args['batch_size']) ? absint($args['batch_size']) : 20;
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

			// Delete progress transient on error
			delete_transient("aips_embeddings_progress_{$author_id}");
			return;
		}

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

		set_transient("aips_embeddings_progress_{$author_id}", $progress_data, HOUR_IN_SECONDS);

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

			$this->schedule_next_batch($author_id, $batch_size, $result['last_processed_id']);
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

			// Delete progress transient on completion
			delete_transient("aips_embeddings_progress_{$author_id}");

			// Fire completion action hook
			do_action('aips_author_embeddings_completed', $author_id, $result);
		}
	}

	/**
	 * Schedule the next batch for an author.
	 *
	 * @param int $author_id         Author ID.
	 * @param int $batch_size        Batch size.
	 * @param int $last_processed_id Last processed topic ID.
	 * @return void
	 */
	private function schedule_next_batch($author_id, $batch_size, $last_processed_id) {
		$args = array(
			'author_id'         => $author_id,
			'batch_size'        => $batch_size,
			'last_processed_id' => $last_processed_id,
		);

		// Schedule to run in a few seconds
		$timestamp = time() + 5;

		// Prefer Action Scheduler if available, otherwise use wp_schedule_single_event
		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action($timestamp, 'aips_process_author_embeddings', $args, 'aips-embeddings');
		} else {
			wp_schedule_single_event($timestamp, 'aips_process_author_embeddings', array($args));
		}
	}
}
