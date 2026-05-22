<?php
/**
 * Embeddings Background Worker
 *
 * Registers and implements the background cron action that processes topic
 * embeddings for a single author in small, idempotent batches.  Uses ID-based
 * pagination (id > last_processed_id) to avoid slow OFFSET queries, stores
 * progress in a short-lived transient, and re-schedules itself when more work
 * remains.
 *
 * Action Scheduler (as_schedule_single_action) is used when available; the
 * plugin falls back to wp_schedule_single_event when it is not installed.
 *
 * Hooks fired by this class:
 *   - aips_process_author_embeddings  (in: {author_id, batch_size, last_processed_id})
 *   - aips_author_embeddings_completed (out: author_id)
 *
 * @package AI_Post_Scheduler
 * @since   1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Embeddings_Cron
 *
 * Background worker that processes topic embeddings one batch at a time.
 */
class AIPS_Embeddings_Cron {

	/**
	 * Cron hook used for topic embedding background batches.
	 *
	 * @var string
	 */
	const HOOK = 'aips_process_author_embeddings';

	/**
	 * Completion hook fired after an author finishes embedding processing.
	 *
	 * @var string
	 */
	const COMPLETED_HOOK = 'aips_author_embeddings_completed';

	/**
	 * Default topics-per-batch for background processing.
	 *
	 * @var int
	 */
	const DEFAULT_BATCH_SIZE = 20;

	/**
	 * Maximum topics-per-batch accepted from the UI.
	 *
	 * @var int
	 */
	const MAX_BATCH_SIZE = 200;

	/**
	 * Transient key prefix for per-author progress tracking.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'aips_embeddings_progress_';

	/**
	 * Default number of seconds from now when the next batch is scheduled.
	 *
	 * @var int
	 */
	const RESCHEDULE_DELAY = 5;

	/**
	 * Optional pre-built expansion service (primarily for testing).
	 *
	 * When null, a fresh AIPS_Topic_Expansion_Service is instantiated on demand.
	 *
	 * @var AIPS_Topic_Expansion_Service|null
	 */
	private $expansion_service;

	/**
	 * Register the cron action hook.
	 *
	 * @param AIPS_Topic_Expansion_Service|null $expansion_service Optional service override
	 *                                                             (used in tests via DI).
	 * @param bool|null                         $register_hook     Whether to register the cron hook.
	 */
	public function __construct($expansion_service = null, $register_hook = true) {
		$this->expansion_service = $expansion_service;

		if ($register_hook) {
			add_action(self::HOOK, array($this, 'process_author_embeddings'));
		}
	}

	/**
	 * Normalize a requested background batch size.
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
	 * Process one batch of approved topic embeddings for a single author.
	 *
	 * Accepts the job arguments array (author_id, batch_size, last_processed_id),
	 * delegates to AIPS_Topic_Expansion_Service::process_approved_embeddings_batch(),
	 * persists progress, and re-schedules itself when there is more work to do.
	 *
	 * @param array $args {
	 *     @type int $author_id         Author to process.
	 *     @type int $batch_size        Topics to process per run. Default 20.
	 *     @type int $last_processed_id Cursor: highest topic id already processed. Default 0.
	 * }
	 */
	public function process_author_embeddings($args) {
		if (!is_array($args)) {
			return;
		}

		$author_id         = isset($args['author_id']) ? absint($args['author_id']) : 0;
		$batch_size        = isset($args['batch_size']) ? self::sanitize_batch_size($args['batch_size']) : self::DEFAULT_BATCH_SIZE;
		$last_processed_id = isset($args['last_processed_id']) ? absint($args['last_processed_id']) : 0;

		if (!$author_id) {
			return;
		}

		$service = $this->expansion_service ?: new AIPS_Topic_Expansion_Service();
		$stats   = $service->process_approved_embeddings_batch(
			$author_id,
			$batch_size,
			$last_processed_id
		);

		$transient_key = self::get_progress_transient_key($author_id);

		if (!$stats['done']) {
			// Save cursor so UI can display approximate progress.
			set_transient($transient_key, $stats['last_processed_id'], HOUR_IN_SECONDS);

			// Re-schedule for the next batch.
			$this->schedule_embeddings_job($author_id, $batch_size, $stats['last_processed_id']);
		} else {
			// Processing complete for this author: clean up transient and fire hook.
			delete_transient($transient_key);

			/**
			 * Fires when all approved topic embeddings for an author have been processed.
			 *
			 * @param int $author_id The author whose embeddings were just completed.
			 */
			do_action(self::COMPLETED_HOOK, $author_id);
		}
	}

	/**
	 * Schedule a single background embedding job for one author.
	 *
	 * Uses Action Scheduler (as_schedule_single_action) when available and falls
	 * back to wp_schedule_single_event otherwise.
	 *
	 * @param int $author_id         Author to process.
	 * @param int $batch_size        Topics to process per run.
	 * @param int $last_processed_id ID-based cursor for id > pagination.
	 * @param int $delay             Seconds from now to run the job. Default RESCHEDULE_DELAY.
	 */
	public function queue_author_embeddings($author_id, $batch_size = self::DEFAULT_BATCH_SIZE, $last_processed_id = 0, $delay = self::RESCHEDULE_DELAY) {
		return $this->schedule_embeddings_job($author_id, $batch_size, $last_processed_id, $delay);
	}

	protected function schedule_embeddings_job($author_id, $batch_size, $last_processed_id, $delay = self::RESCHEDULE_DELAY) {
		$args = array(
			'author_id'         => absint($author_id),
			'batch_size'        => self::sanitize_batch_size($batch_size),
			'last_processed_id' => absint($last_processed_id),
		);

		if (function_exists('as_schedule_single_action')) {
			$result = as_schedule_single_action(time() + max(0, absint($delay)), self::HOOK, array($args));
			return !empty($result);
		}

		$result = wp_schedule_single_event(time() + max(0, absint($delay)), self::HOOK, array($args));
		return !is_wp_error($result) && false !== $result;
	}
}
