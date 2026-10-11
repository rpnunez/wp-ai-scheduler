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
	 * @param AIPS_Sources_Repository|null $sources_repo Optional (injectable for tests).
	 * @param AIPS_Sources_Fetcher|null    $fetcher      Optional (injectable for tests).
	 * @param AIPS_Logger|null             $logger       Optional (injectable for tests).
	 */
	public function __construct( $sources_repo = null, $fetcher = null, $logger = null ) {
		$this->sources_repo = $sources_repo ?: new AIPS_Sources_Repository();
		$this->fetcher      = $fetcher      ?: new AIPS_Sources_Fetcher();
		$this->logger       = $logger       ?: new AIPS_Logger();

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
		/**
		 * Filters the maximum number of sources processed per cron run.
		 *
		 * @since 2.6.0
		 * @param int $max Default maximum. Default AIPS_Sources_Cron::MAX_PER_RUN (10).
		 */
		$max_per_run = max(1, (int) apply_filters('aips_sources_cron_max_per_run', self::MAX_PER_RUN));
		$due_sources = $this->sources_repo->get_due_for_fetch( $max_per_run );

		if ( empty( $due_sources ) ) {
			return;
		}

		$this->logger->log(
			sprintf( 'AIPS_Sources_Cron: processing %d due source(s).', count( $due_sources ) ),
			'info'
		);

		$fetched_ids = array();
		$failed      = array();

		foreach ( $due_sources as $source ) {
			$result = $this->fetcher->fetch( $source );

			if ( ! empty( $result['success'] ) ) {
				$fetched_ids[] = (int) $source->id;
			} else {
				$failed[ (int) $source->id ] = (string) ( $result['error'] ?? '' );
			}

			if ( ! $result['success'] ) {
				$this->logger->log(
					sprintf(
						'AIPS_Sources_Cron: fetch failed for source #%d — %s',
						(int) $source->id,
						$result['error']
					),
					'warning'
				);
			}
		}

		// One History container per cron run (not per source) to keep the log small.
		AIPS_History_Event_Recorder::instance()->record_simple(
			AIPS_History_Event_Type::SOURCE_FETCHED,
			empty( $failed ),
			sprintf(
				/* translators: 1: sources fetched, 2: sources due, 3: sources that failed */
				__( 'Fetched %1$d of %2$d due sources (%3$d failed)', 'ai-post-scheduler' ),
				count( $fetched_ids ),
				count( $due_sources ),
				count( $failed )
			),
			'source_fetch',
			array( 'event' => __( 'Source fetch', 'ai-post-scheduler' ), 'detail' => __( 'Scheduled run', 'ai-post-scheduler' ) ),
			'scheduled',
			array( 'fetched_source_ids' => $fetched_ids, 'failed' => $failed )
		);
	}
}
