<?php
/** Opt-in, bounded source archive cleanup. */
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Sources_Maintenance {
	const HOOK = 'aips_sources_archive_maintenance';
	const CONTINUATION_HOOK = 'aips_sources_archive_maintenance_continue';
	private static $instance = null;
	private $repository;
	private $lease;
	private $logger;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct( $repository = null, $lease = null, $logger = null ) {
		$this->repository = $repository ?: new AIPS_Sources_Data_Repository();
		$this->lease = $lease ?: new AIPS_Sources_Fetch_Lease( 'aips_sources_maintenance_lease' );
		$this->logger = $logger ?: new AIPS_Logger();
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( self::CONTINUATION_HOOK, array( $this, 'run' ) );
		add_action( 'update_option_aips_source_archive_retention_days', array( $this, 'schedule' ), 10, 0 );
		add_action( 'add_option_aips_source_archive_retention_days', array( $this, 'schedule' ), 10, 0 );
		$this->schedule();
	}

	private function retention_days(): int {
		// Read live state: a settings change must not use a cached previous value.
		$defaults = AIPS_Config::get_instance()->get_default_options();
		return max( 0, min( 3650, (int) get_option( 'aips_source_archive_retention_days', $defaults['aips_source_archive_retention_days'] ) ) );
	}

	public function schedule(): void {
		if ( 0 === $this->retention_days() ) {
			wp_clear_scheduled_hook( self::HOOK );
			wp_clear_scheduled_hook( self::CONTINUATION_HOOK );
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( AIPS_DateTime::now()->timestamp() + DAY_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	private function continue_later(): void {
		if ( ! wp_next_scheduled( self::CONTINUATION_HOOK ) ) {
			wp_schedule_single_event( AIPS_DateTime::now()->timestamp() + 60, self::CONTINUATION_HOOK );
		}
	}

	public function run(): void {
		$days = $this->retention_days();
		if ( 0 === $days ) {
			$this->schedule();
			return;
		}
		$owner = $this->lease->acquire();
		if ( false === $owner ) {
			$this->continue_later();
			return;
		}
		try {
			$cutoff = AIPS_DateTime::now()->timestamp() - $days * DAY_IN_SECONDS;
			$removed = $this->repository->prune_archive( $cutoff, 100 );
			if ( false === $removed ) {
				$this->logger->log( 'Source archive maintenance failed to delete expired snapshots.', 'error' );
				return;
			}
			$this->logger->log( sprintf( 'Source archive maintenance removed %d expired snapshots.', $removed ), 'info' );
			if ( $this->repository->count_prunable_archive( $cutoff ) > 0 ) {
				$this->continue_later();
			}
		} catch ( Throwable $error ) {
			$this->logger->log( 'Source archive maintenance failed: ' . $error->getMessage(), 'error' );
		} finally {
			$this->lease->release( $owner );
		}
	}
}
