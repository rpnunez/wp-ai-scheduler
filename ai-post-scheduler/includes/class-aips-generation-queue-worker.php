<?php
/**
 * Generation Queue Worker
 *
 * Processes a bounded batch of generation jobs from the aips_generation_queue table.
 *
 * When the 'queue_backed_scheduler' feature flag is enabled, the cron callback
 * routes work through this worker instead of executing schedules inline.  The
 * worker:
 *
 *  1. Releases stale locks from a previous crashed invocation.
 *  2. Claims up to $batch_size pending, due jobs using a unique lock token.
 *  3. Executes each job by delegating to AIPS_Schedule_Processor.
 *  4. Marks each job done (on success) or failed (with back-off / dead-lettering).
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generation_Queue_Worker
 *
 * Batch processor for the generation job queue.  Stateless between invocations;
 * all durable state lives in the queue table.
 */
class AIPS_Generation_Queue_Worker {

	/**
	 * @var AIPS_Generation_Queue_Repository
	 */
	private $queue_repository;

	/**
	 * @var AIPS_Schedule_Processor
	 */
	private $processor;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * All parameters are optional to support dependency injection in tests.
	 *
	 * @param AIPS_Generation_Queue_Repository|null $queue_repository
	 * @param AIPS_Schedule_Processor|null          $processor
	 * @param AIPS_Logger|null                      $logger
	 * @param AIPS_Config|null                      $config
	 */
	public function __construct(
		$queue_repository = null,
		$processor = null,
		$logger = null,
		$config = null
	) {
		$this->queue_repository = $queue_repository ?: new AIPS_Generation_Queue_Repository();
		$this->processor        = $processor ?: new AIPS_Schedule_Processor();
		$this->logger           = $logger ?: new AIPS_Logger();
		$this->config           = $config ?: AIPS_Config::get_instance();
	}

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Claim and process a bounded batch of pending, due queue jobs.
	 *
	 * Steps:
	 *  1. Release stale processing locks so previously crashed jobs are eligible.
	 *  2. Claim up to $batch_size jobs using a unique lock token.
	 *  3. Execute each claimed job, marking it done or failed.
	 *
	 * @param int|null $batch_size Maximum jobs to process.  When null the value of
	 *                             the 'aips_queue_batch_size' option is used (default 5).
	 * @return int Number of jobs attempted in this invocation.
	 */
	public function process_batch( $batch_size = null ) {
		if ( $batch_size === null ) {
			$batch_size = (int) $this->config->get_option( 'aips_queue_batch_size', 5 );
		}

		$batch_size   = max( 1, absint( $batch_size ) );
		$lock_timeout = (int) $this->config->get_option( 'aips_queue_lock_timeout', 300 );

		// Free jobs whose processing lock has expired so they can be retried.
		$released = $this->queue_repository->release_stale_locks( $lock_timeout );
		if ( $released > 0 ) {
			$this->logger->log(
				sprintf( 'Released %d stale queue lock(s)', $released ),
				'info'
			);
		}

		$lock_token = $this->generate_lock_token();
		$jobs       = $this->queue_repository->claim_batch( $batch_size, $lock_token );

		if ( empty( $jobs ) ) {
			return 0;
		}

		$this->logger->log(
			sprintf( 'Queue worker claimed %d job(s) (token: %s)', count( $jobs ), $lock_token ),
			'info'
		);

		$attempted = 0;
		foreach ( $jobs as $job ) {
			++$attempted;
			$this->execute_job( $job );
		}

		return $attempted;
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Dispatch a single queue job to the appropriate handler.
	 *
	 * @param object $job Queue row object.
	 */
	private function execute_job( $job ) {
		$payload = json_decode( (string) $job->payload, true );

		if ( ! is_array( $payload ) ) {
			$this->logger->log(
				sprintf( 'Queue job %d has invalid JSON payload — marking failed.', absint( $job->id ) ),
				'error'
			);
			$this->queue_repository->mark_failed(
				$job->id,
				'Payload JSON decode failed',
				$this->get_max_attempts()
			);
			return;
		}

		switch ( $job->job_type ) {
			case 'template_schedule':
				$this->execute_template_schedule_job( $job, $payload );
				break;

			default:
				$this->logger->log(
					sprintf( 'Unknown queue job type "%s" for job %d.', esc_attr( $job->job_type ), absint( $job->id ) ),
					'error'
				);
				$this->queue_repository->mark_failed(
					$job->id,
					'Unknown job type: ' . sanitize_text_field( $job->job_type ),
					$this->get_max_attempts()
				);
		}
	}

	/**
	 * Execute a template_schedule job.
	 *
	 * Delegates to AIPS_Schedule_Processor::process_queued_schedule(), which
	 * contains the existing claim-first locking, generation, and history-logging
	 * logic.  The worker only handles queue bookkeeping.
	 *
	 * @param object $job     Queue row.
	 * @param array  $payload Decoded payload.
	 */
	private function execute_template_schedule_job( $job, $payload ) {
		$schedule_id = isset( $payload['schedule_id'] ) ? absint( $payload['schedule_id'] ) : 0;

		if ( ! $schedule_id ) {
			$this->queue_repository->mark_failed(
				$job->id,
				'Missing or invalid schedule_id in payload',
				$this->get_max_attempts()
			);
			return;
		}

		try {
			$result = $this->processor->process_queued_schedule( $schedule_id );

			if ( is_wp_error( $result ) ) {
				$this->queue_repository->mark_failed(
					$job->id,
					$result->get_error_message(),
					$this->get_max_attempts()
				);
			} else {
				$this->queue_repository->mark_done( $job->id );
			}
		} catch ( \Throwable $e ) {
			$this->logger->log(
				sprintf( 'Queue job %d (schedule %d) threw an exception: %s', absint( $job->id ), $schedule_id, $e->getMessage() ),
				'error'
			);
			$this->queue_repository->mark_failed(
				$job->id,
				$e->getMessage(),
				$this->get_max_attempts()
			);
		}
	}

	/**
	 * Get the configured maximum attempt count before dead-lettering a job.
	 *
	 * @return int
	 */
	private function get_max_attempts() {
		return max( 1, (int) $this->config->get_option( 'aips_queue_max_attempts', 3 ) );
	}

	/**
	 * Generate a unique lock token for this worker invocation.
	 *
	 * @return string UUID-style string.
	 */
	private function generate_lock_token() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}
}
