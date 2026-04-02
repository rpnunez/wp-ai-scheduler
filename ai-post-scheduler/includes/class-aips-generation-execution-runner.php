<?php
/**
 * AIPS_Generation_Execution_Runner
 *
 * Shared execution harness for cron-driven post-generation flows.
 *
 * Both the template-schedule pipeline (AIPS_Schedule_Processor) and the
 * author-topic pipeline (AIPS_Author_Post_Generator) share an identical
 * structural pattern for each unit of work:
 *
 *   1. Generate a fresh correlation ID.
 *   2. Execute the domain-specific work callable.
 *   3. On unexpected Throwable: log to history and optionally invoke a
 *      caller-supplied error callback (e.g. to fire aips_system_error).
 *   4. Always reset the correlation ID in a finally block so it cannot
 *      bleed into a subsequent job in the same cron batch.
 *
 * This class centralises that harness so each caller only needs to supply
 * the work callable, a history-type label, metadata for error records, and
 * an optional domain-specific exception callback.
 *
 * Domain concerns that differ between callers — claim-first locking,
 * schedule-advancement timing, and domain-specific error actions — remain
 * in the respective calling classes.
 *
 * @package AI_Post_Scheduler
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Generation_Execution_Runner {

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param AIPS_History_Service|null $history_service History service instance.
	 * @param AIPS_Logger|null          $logger          Logger instance.
	 */
	public function __construct($history_service = null, $logger = null) {
		$this->history_service = $history_service ?: new AIPS_History_Service();
		$this->logger = $logger ?: new AIPS_Logger();
	}

	/**
	 * Run a generation callable inside the standard execution harness.
	 *
	 * Handles correlation ID lifecycle and Throwable safety net with history
	 * logging.  Domain-specific concerns (locking, schedule advancement, and
	 * domain-scoped error actions) remain with the calling class.
	 *
	 * Usage:
	 *
	 *   $this->runner->run(
	 *       function() use ($author) {
	 *           $this->generate_post_for_author($author);
	 *       },
	 *       'author_post_generation',
	 *       array('author_id' => $author->id)
	 *   );
	 *
	 * @param callable      $work          Callable containing the generation logic.
	 * @param string        $history_type  History-type label used when recording an
	 *                                     unexpected Throwable in the history service.
	 * @param array         $history_meta  Metadata array attached to the Throwable
	 *                                     history record (e.g. author_id, schedule_id).
	 * @param callable|null $on_exception  Optional callback invoked after the Throwable
	 *                                     has been recorded:
	 *                                     function( Throwable $e, string $correlation_id ): void
	 *                                     Use this to fire domain-specific error actions
	 *                                     (e.g. aips_system_error) without duplicating
	 *                                     the harness boilerplate.
	 * @return mixed Whatever the work callable returns, or WP_Error on caught Throwable.
	 */
	public function run(callable $work, $history_type, array $history_meta, $on_exception = null) {
		$correlation_id = AIPS_Correlation_ID::generate();
		$result = null;

		try {
			$result = $work();
		} catch (\Throwable $e) {
			$this->logger->log(
				'Unexpected error during generation run: ' . $e->getMessage(),
				'error',
				array(
					'history_type' => $history_type,
					'meta'         => $history_meta,
					'trace'        => $e->getTraceAsString(),
				)
			);

			$history = $this->history_service->create($history_type, $history_meta);

			if ($history) {
				$history->record(
					'error',
					sprintf(
						/* translators: %s: error message */
						__('Generation run failed with unexpected error: %s', 'ai-post-scheduler'),
						$e->getMessage()
					),
					array(
						'event_type'   => 'generation_exception',
						'event_status' => 'failed',
					),
					null,
					array_merge($history_meta, array('error' => $e->getMessage()))
				);
			}

			if ($on_exception !== null) {
				$on_exception($e, $correlation_id);
			}

			$result = new WP_Error('generation_exception', $e->getMessage());
		} finally {
			// Always reset to prevent correlation ID bleed into subsequent jobs
			// in the same cron batch.
			AIPS_Correlation_ID::reset();
		}

		return $result;
	}
}
