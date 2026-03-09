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
	 * Register the cron action hook.
	 */
	public function __construct() {
		add_action('aips_process_author_embeddings', array($this, 'process_author_embeddings'));
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
		$batch_size        = isset($args['batch_size']) ? max(1, absint($args['batch_size'])) : 20;
		$last_processed_id = isset($args['last_processed_id']) ? absint($args['last_processed_id']) : 0;

		if (!$author_id) {
			return;
		}

		$expansion_service = new AIPS_Topic_Expansion_Service();
		$stats             = $expansion_service->process_approved_embeddings_batch(
			$author_id,
			$batch_size,
			$last_processed_id
		);

		$transient_key = self::TRANSIENT_PREFIX . $author_id;

		if (!$stats['done']) {
			// Save cursor so UI can display approximate progress.
			set_transient($transient_key, $stats['last_processed_id'], HOUR_IN_SECONDS);

			// Re-schedule for the next batch.
			$next_args = array(
				'author_id'         => $author_id,
				'batch_size'        => $batch_size,
				'last_processed_id' => $stats['last_processed_id'],
			);

			if (function_exists('as_schedule_single_action')) {
				as_schedule_single_action(time() + self::RESCHEDULE_DELAY, 'aips_process_author_embeddings', array($next_args));
			} else {
				wp_schedule_single_event(time() + self::RESCHEDULE_DELAY, 'aips_process_author_embeddings', array($next_args));
			}
		} else {
			// Processing complete for this author: clean up transient and fire hook.
			delete_transient($transient_key);

			/**
			 * Fires when all approved topic embeddings for an author have been processed.
			 *
			 * @param int $author_id The author whose embeddings were just completed.
			 */
			do_action('aips_author_embeddings_completed', $author_id);
		}
	}
}
