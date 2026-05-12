<?php
/**
 * Sources Cron Handler
 *
 * Manages WP-CRON scheduling for the periodic source-fetching loop.
 * Registers a recurring cron hook and dispatches fetch jobs to
 * AIPS_Sources_Fetcher for each source that is due for a refresh.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Sources_Cron
 *
 * Hooks into WordPress cron to periodically fetch content for all
 * sources that have a fetch_interval configured and whose next_fetch_at
 * time has passed.
 */
class AIPS_Sources_Cron {

	/**
	 * WP-CRON action name for the fetch loop.
	 *
	 * @var string
	 */
	const HOOK = 'aips_fetch_sources';

	/**
	 * Default cron recurrence for the dispatcher loop.
	 * The loop itself fires frequently; individual sources are skipped
	 * until their own next_fetch_at threshold is reached.
	 *
	 * @var string
	 */
	const DISPATCHER_RECURRENCE = 'daily';

	/**
	 * Maximum sources processed per cron run to prevent timeouts.
	 *
	 * @var int
	 */
	const MAX_PER_RUN = 10;

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
	 * @var AIPS_Sources_Repository
	 */
	private $sources_repo;

	/**
	 * @var AIPS_Sources_Fetcher
	 */
	private $fetcher;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	/**
	 * @param AIPS_Sources_Repository|null $sources_repo Optional (injectable for tests).
	 * @param AIPS_Sources_Fetcher|null    $fetcher      Optional (injectable for tests).
	 * @param AIPS_Logger|null             $logger       Optional (injectable for tests).
	 */
	public function __construct( $sources_repo = null, $fetcher = null, $logger = null, $history_service = null ) {
		$this->sources_repo = $sources_repo ?: new AIPS_Sources_Repository();
		$this->fetcher      = $fetcher      ?: new AIPS_Sources_Fetcher();
		$this->logger       = $logger       ?: new AIPS_Logger();
		$this->history_service = $history_service ?: new AIPS_History_Service();

		add_action( self::HOOK, array( $this, 'run' ) );

		$this->schedule();
	}

	/**
	 * Ensure the recurring WP-CRON event exists.
	 *
	 * Called during construction so the event is (re-)registered on every
	 * page load where the plugin is active, which mirrors the pattern used
	 * by AIPS_Scheduler.
	 *
	 * @return void
	 */
	public function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( AIPS_DateTime::now()->timestamp(), self::DISPATCHER_RECURRENCE, self::HOOK );
		}
	}

	/**
	 * Cron callback: fetch content for all sources that are due.
	 *
	 * Processes at most `aips_sources_cron_max_per_run` sources per invocation
	 * (default: MAX_PER_RUN = 10) to avoid HTTP timeouts. Sources are ordered
	 * by next_fetch_at ascending so the most overdue ones are handled first.
	 *
	 * @return void
	 */
	public function run() {
		$started_at      = microtime( true );
		$correlation_id  = AIPS_Correlation_ID::generate();
		AIPS_Correlation_ID::set( $correlation_id );

		/**
		 * Filters the maximum number of sources processed per cron run.
		 *
		 * @since 2.6.0
		 * @param int $max Default maximum. Default AIPS_Sources_Cron::MAX_PER_RUN (10).
		 */
		$max_per_run = max(1, (int) apply_filters('aips_sources_cron_max_per_run', self::MAX_PER_RUN));
		$due_sources = $this->sources_repo->get_due_for_fetch( $max_per_run );
		$history     = $this->history_service->create(
			'sources_fetch',
			array(
				'trigger_source' => 'cron',
				'max_per_run'    => $max_per_run,
				'due_count'      => count( $due_sources ),
				'correlation_id' => $correlation_id,
			)
		);

		if ( empty( $due_sources ) ) {
			$history->complete_success(
				array(
					'items_processed' => 0,
					'items_failed'    => 0,
					'duration_ms'     => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
					'trigger_source'  => 'cron',
				)
			);
			AIPS_Correlation_ID::reset();
			return;
		}

		$this->logger->log(
			sprintf( 'AIPS_Sources_Cron: processing %d due source(s).', count( $due_sources ) ),
			'info'
		);

		$success_count = 0;
		$failed_count  = 0;
		foreach ( $due_sources as $source ) {
			$history->record(
				'activity',
				sprintf( __( 'Starting source fetch for source #%d', 'ai-post-scheduler' ), (int) $source->id ),
				array( 'event_type' => 'source_fetch_started', 'event_status' => 'processing' ),
				null,
				array( 'source_id' => (int) $source->id )
			);
			$result = $this->fetcher->fetch( $source );

			if ( ! $result['success'] ) {
				$failed_count++;
				$this->logger->log(
					sprintf(
						'AIPS_Sources_Cron: fetch failed for source #%d — %s',
						(int) $source->id,
						$result['error']
					),
					'warning'
				);
				$history->record(
					'warning',
					sprintf( __( 'Source fetch failed for source #%d', 'ai-post-scheduler' ), (int) $source->id ),
					array( 'event_type' => 'source_fetch_failed', 'event_status' => 'failed' ),
					null,
					array( 'source_id' => (int) $source->id, 'error' => isset( $result['error'] ) ? $result['error'] : '' )
				);
				continue;
			}
			$success_count++;
			$history->record(
				'activity',
				sprintf( __( 'Source fetch completed for source #%d', 'ai-post-scheduler' ), (int) $source->id ),
				array( 'event_type' => 'source_fetch_completed', 'event_status' => 'success' ),
				null,
				array( 'source_id' => (int) $source->id )
			);
		}

		$summary = array(
			'items_processed' => $success_count,
			'items_failed'    => $failed_count,
			'duration_ms'     => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
			'trigger_source'  => 'cron',
		);

		if ( $failed_count > 0 ) {
			$history->complete_failure(
				__( 'Sources fetch run completed with failures.', 'ai-post-scheduler' ),
				$summary
			);
		} else {
			$history->complete_success( $summary );
		}
		AIPS_Correlation_ID::reset();
	}
}
