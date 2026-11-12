<?php
/** Source dispatcher scheduling and bounded worker tests. */
class Test_AIPS_Sources_Cron extends WP_UnitTestCase {
	private $repo;
	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->repo = new AIPS_Sources_Repository();
		wp_clear_scheduled_hook( AIPS_Sources_Cron::HOOK );
		wp_clear_scheduled_hook( AIPS_Sources_Cron::CONTINUATION_HOOK );
		delete_option( AIPS_Sources_Fetch_Lease::OPTION );
	}
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_sources" );
		wp_clear_scheduled_hook( AIPS_Sources_Cron::HOOK );
		wp_clear_scheduled_hook( AIPS_Sources_Cron::CONTINUATION_HOOK );
		delete_option( AIPS_Sources_Fetch_Lease::OPTION );
		parent::tearDown();
	}
	private function source( $interval = 'hourly', $due = null ) {
		global $wpdb;
		$id = $this->repo->create( array( 'url' => 'https://example.com/' . wp_generate_uuid4() ) );
		$this->repo->set_fetch_schedule( $id, $interval );
		if ( null !== $due ) {
			$wpdb->update( $wpdb->prefix . 'aips_sources', array( 'next_fetch_at' => $due ), array( 'id' => $id ) );
		}
		return $id;
	}
	public function test_legacy_event_migrates_to_earliest_single_event() {
		$due = time() + 500;
		$this->source( 'daily', $due + 100 );
		$this->source( 'hourly', $due );
		wp_schedule_event( time(), 'daily', AIPS_Sources_Cron::HOOK );
		$cron = new AIPS_Sources_Cron( $this->repo );
		$cron->schedule();
		$event = wp_get_scheduled_event( AIPS_Sources_Cron::HOOK );
		$this->assertSame( $due, $event->timestamp );
		$this->assertFalse( $event->schedule );
	}
	public function test_no_eligible_sources_clears_event() {
		$id = $this->source();
		$cron = new AIPS_Sources_Cron( $this->repo );
		$this->repo->set_active( $id, false );
		$cron->schedule();
		$this->assertFalse( wp_next_scheduled( AIPS_Sources_Cron::HOOK ) );
	}
	public function test_disable_reactivate_and_delete_reconcile() {
		$id = $this->source();
		$cron = new AIPS_Sources_Cron( $this->repo );
		$this->assertTrue( $this->repo->set_fetch_schedule( $id, null ) );
		$cron->schedule();
		$this->assertNull( $this->repo->get_by_id( $id )->next_fetch_at );
		$this->assertFalse( wp_next_scheduled( AIPS_Sources_Cron::HOOK ) );
		$this->repo->set_fetch_schedule( $id, 'daily' );
		$this->repo->set_active( $id, true );
		$cron->schedule();
		$this->assertNotFalse( wp_next_scheduled( AIPS_Sources_Cron::HOOK ) );
		$this->repo->delete( $id );
		$cron->schedule();
		$this->assertFalse( wp_next_scheduled( AIPS_Sources_Cron::HOOK ) );
	}
	public function test_equal_due_times_are_ordered_by_id() {
		$due = time() - 10;
		$first = $this->source( 'hourly', $due );
		$second = $this->source( 'every_6_hours', $due );
		$this->assertSame( array( $first, $second ), array_map( static function( $row ) { return (int) $row->id; }, $this->repo->get_due_for_fetch() ) );
	}
	public function test_worker_exception_advances_attempt_and_releases_ownership() {
		$id = $this->source( 'hourly', time() - 10 );
		$fetcher = new class {
			public function fetch( $source ) { throw new RuntimeException( 'fixture failure' ); }
		};
		$cron = new AIPS_Sources_Cron( $this->repo, $fetcher );
		$cron->run();
		$this->assertGreaterThan( time(), (int) $this->repo->get_by_id( $id )->next_fetch_at );
		$lease = new AIPS_Sources_Fetch_Lease();
		$owner = $lease->acquire();
		$this->assertIsString( $owner );
		$lease->release( $owner );
	}
	public function test_busy_worker_does_not_fetch_and_has_one_retry_event() {
		$this->source( 'hourly', time() - 10 );
		$cron = new AIPS_Sources_Cron( $this->repo );
		$lease = new AIPS_Sources_Fetch_Lease();
		$owner = $lease->acquire();
		$cron->run();
		$retry = wp_next_scheduled( AIPS_Sources_Cron::CONTINUATION_HOOK );
		$cron->run();
		$this->assertSame( $retry, wp_next_scheduled( AIPS_Sources_Cron::CONTINUATION_HOOK ) );
		$this->assertCount( 1, $this->repo->get_due_for_fetch() );
		$lease->release( $owner );
	}
	public function test_unchanged_interval_preserves_due_time_and_other_source() {
		$due = time() - 100;
		$id = $this->source( 'hourly', $due );
		$other = $this->source( 'daily', $due + 50 );
		$this->assertTrue( $this->repo->set_fetch_schedule( $id, 'hourly' ) );
		$this->assertSame( $due, (int) $this->repo->get_by_id( $id )->next_fetch_at );
		$this->repo->set_fetch_schedule( $id, 'every_6_hours' );
		$this->assertSame( $due + 50, (int) $this->repo->get_by_id( $other )->next_fetch_at );
	}
	public function test_eleven_due_sources_drain_with_one_continuation() {
		for ( $i = 0; $i < 11; $i++ ) { $this->source( 'hourly', time() - 100 ); }
		$this->source( 'daily', time() + 1000 );
		$fetcher = new class( $this->repo ) {
			private $repo;
			public $ids = array();
			public function __construct( $repo ) { $this->repo = $repo; }
			public function fetch( $source ) {
				$this->ids[] = (int) $source->id;
				$this->repo->update_after_fetch( $source->id, true );
				return array( 'success' => true );
			}
		};
		$cron = new AIPS_Sources_Cron( $this->repo, $fetcher );
		$cron->run();
		$this->assertCount( 10, $fetcher->ids );
		$this->assertNotFalse( wp_next_scheduled( AIPS_Sources_Cron::CONTINUATION_HOOK ) );
		$cron->run();
		$this->assertCount( 11, array_unique( $fetcher->ids ) );
		$this->assertFalse( wp_next_scheduled( AIPS_Sources_Cron::CONTINUATION_HOOK ) );
	}
}
