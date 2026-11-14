<?php
/**
 * Tests for AIPS_Job_Manager, AIPS_Job_Context, and AIPS_Job_Slice_Result (issue #2376 Slice 2).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Job_Manager extends WP_UnitTestCase {

	/** @var AIPS_Job_Registry */
	private $registry;

	/** @var AIPS_Job_Repository */
	private $repository;

	/** @var AIPS_Cron_Manager */
	private $cron_manager;

	/** @var AIPS_Job_Manager */
	private $manager;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		$this->registry     = new AIPS_Job_Registry();
		$this->repository   = new AIPS_Job_Repository();
		$this->cron_manager = new AIPS_Cron_Manager();
		$this->manager      = new AIPS_Job_Manager(
			$this->registry,
			$this->repository,
			$this->cron_manager
		);

		$this->clear_tables_and_hooks();
	}

	public function tearDown(): void {
		$this->clear_tables_and_hooks();
		parent::tearDown();
	}

	private function clear_tables_and_hooks() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}aips_jobs" );
		wp_unschedule_hook( AIPS_Job_Registry::DEFAULT_TICK_HOOK );
	}

	// -------------------------------------------------------------------------
	// Dispatching Tests
	// -------------------------------------------------------------------------

	public function test_dispatch_creates_pending_job_and_schedules_cron() {
		$payload = array( 'items' => array( 10, 20, 30 ) );
		$job_id  = $this->manager->dispatch(
			AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_SLICE,
			$payload,
			array( 'delay' => 60, 'total' => 3 )
		);

		$this->assertIsString( $job_id );
		$this->assertNotEmpty( $job_id );

		$record = $this->manager->get_job( $job_id );
		$this->assertInstanceOf( AIPS_Job_Record::class, $record );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $record->get_status() );
		$this->assertSame( AIPS_Job_Registry::GROUP_AUTHOR_TOPICS, $record->get_group_name() );
		$this->assertSame( 3, $record->get_total() );

		// Verify single event scheduled in cron_manager
		$next = $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $job_id ) );
		$this->assertIsInt( $next );
	}

	public function test_dispatch_batch_slices_items() {
		$items  = range( 1, 55 );
		$job_id = $this->manager->dispatch_batch(
			AIPS_Job_Registry::TYPE_BULK_BATCH_SLICE,
			$items,
			array( 'batch_size' => 25 )
		);

		$record = $this->manager->get_job( $job_id );
		$this->assertSame( 55, $record->get_total() );
		$payload = $record->get_payload();
		$this->assertCount( 55, $payload['items'] );
		$this->assertSame( 25, $payload['batch_size'] );
	}

	public function test_dispatch_staggered_creates_multiple_spaced_jobs() {
		$items = array(
			array( 'id' => 1 ),
			array( 'id' => 2 ),
			array( 'id' => 3 ),
		);

		$job_ids = $this->manager->dispatch_staggered(
			AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_SLICE,
			$items,
			array( 'interval_seconds' => 45 )
		);

		$this->assertCount( 3, $job_ids );

		$record1 = $this->manager->get_job( $job_ids[0] );
		$record2 = $this->manager->get_job( $job_ids[1] );
		$record3 = $this->manager->get_job( $job_ids[2] );

		$this->assertSame( $record1->get_next_run_at() + 45, $record2->get_next_run_at() );
		$this->assertSame( $record2->get_next_run_at() + 45, $record3->get_next_run_at() );
	}

	// -------------------------------------------------------------------------
	// Lifecycle State Control Tests
	// -------------------------------------------------------------------------

	public function test_pause_and_resume_job() {
		$job_id = $this->manager->dispatch( AIPS_Job_Registry::TYPE_LINK_INDEX_SCAN );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $this->manager->get_status( $job_id ) );

		// Pause
		$paused = $this->manager->pause( $job_id );
		$this->assertTrue( $paused );
		$this->assertSame( AIPS_Job_Record::STATUS_PAUSED, $this->manager->get_status( $job_id ) );
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $job_id ) ) );

		// Resume
		$resumed = $this->manager->resume( $job_id );
		$this->assertTrue( $resumed );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $this->manager->get_status( $job_id ) );
		$this->assertIsInt( $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $job_id ) ) );
	}

	public function test_cancel_job() {
		$job_id = $this->manager->dispatch( AIPS_Job_Registry::TYPE_LINK_INDEX_SCAN );
		$this->assertTrue( $this->manager->cancel( $job_id ) );

		$record = $this->manager->get_job( $job_id );
		$this->assertTrue( $record->is_cancelled() );
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $job_id ) ) );
	}

	public function test_retry_failed_job() {
		$job_id = $this->manager->dispatch( AIPS_Job_Registry::TYPE_LINK_INDEX_SCAN );
		$this->repository->update( $job_id, array( 'status' => AIPS_Job_Record::STATUS_FAILED, 'error_message' => 'Test error' ) );

		$this->assertTrue( $this->manager->retry( $job_id ) );
		$record = $this->manager->get_job( $job_id );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $record->get_status() );
		$this->assertSame( 0, $record->get_attempts() );
		$this->assertNull( $record->get_error_message() );
	}

	// -------------------------------------------------------------------------
	// Runner Tick Execution & Slicing Tests
	// -------------------------------------------------------------------------

	public function test_handle_tick_completes_job_with_slice_result() {
		add_filter(
			'aips_registered_jobs',
			function( $defs ) {
				$defs['mock_success_job'] = array(
					'label'   => 'Mock Success Job',
					'hook'    => AIPS_Job_Registry::DEFAULT_TICK_HOOK,
					'handler' => function( AIPS_Job_Context $context ) {
						return AIPS_Job_Slice_Result::complete( 10, 0, array( 'done' => true ) );
					},
				);
				return $defs;
			}
		);

		// Re-initialize manager with mock type
		$this->manager = new AIPS_Job_Manager( new AIPS_Job_Registry(), $this->repository, $this->cron_manager );
		$job_id        = $this->manager->dispatch( 'mock_success_job', array(), array( 'total' => 10 ) );

		// Execute tick
		$this->manager->handle_tick( $job_id );

		$record = $this->manager->get_job( $job_id );
		$this->assertTrue( $record->is_completed() );
		$this->assertSame( 10, $record->get_processed() );
		$this->assertSame( array( 'done' => true ), $record->get_result() );
	}

	public function test_handle_tick_schedules_continuation_when_not_complete() {
		add_filter(
			'aips_registered_jobs',
			function( $defs ) {
				$defs['mock_continuation_job'] = array(
					'label'   => 'Mock Continuation Job',
					'hook'    => AIPS_Job_Registry::DEFAULT_TICK_HOOK,
					'handler' => function( AIPS_Job_Context $context ) {
						// Process 5 items, cursor 5, not complete
						return AIPS_Job_Slice_Result::continue( 5, 5, 0 );
					},
				);
				return $defs;
			}
		);

		$this->manager = new AIPS_Job_Manager( new AIPS_Job_Registry(), $this->repository, $this->cron_manager );
		$job_id        = $this->manager->dispatch( 'mock_continuation_job', array(), array( 'total' => 20 ) );

		// Execute first tick
		$this->manager->handle_tick( $job_id );

		$record = $this->manager->get_job( $job_id );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $record->get_status() );
		$this->assertSame( 5, $record->get_processed() );
		$this->assertSame( 5, $record->get_cursor_id() );

		// Continuation tick should be scheduled
		$next = $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $job_id ) );
		$this->assertIsInt( $next );
	}

	public function test_handle_tick_handles_exception_and_retries_with_backoff() {
		add_filter(
			'aips_registered_jobs',
			function( $defs ) {
				$defs['mock_fail_job'] = array(
					'label'             => 'Mock Fail Job',
					'hook'              => AIPS_Job_Registry::DEFAULT_TICK_HOOK,
					'max_retries'       => 2,
					'retry_backoff_sec' => 30,
					'handler'           => function( AIPS_Job_Context $context ) {
						throw new RuntimeException( 'Simulated failure' );
					},
				);
				return $defs;
			}
		);

		$this->manager = new AIPS_Job_Manager( new AIPS_Job_Registry(), $this->repository, $this->cron_manager );
		$job_id        = $this->manager->dispatch( 'mock_fail_job' );

		// Attempt 1 fails -> retries
		$this->manager->handle_tick( $job_id );
		$record = $this->manager->get_job( $job_id );
		$this->assertSame( 1, $record->get_attempts() );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $record->get_status() );
		$this->assertSame( 'Simulated failure', $record->get_error_message() );

		// Attempt 2 fails -> reaches max_retries, marks failed
		$this->manager->handle_tick( $job_id );
		$record = $this->manager->get_job( $job_id );
		$this->assertSame( 2, $record->get_attempts() );
		$this->assertTrue( $record->is_failed() );
	}

	public function test_dispatch_sync_executes_immediately() {
		add_filter(
			'aips_registered_jobs',
			function( $defs ) {
				$defs['mock_sync_job'] = array(
					'label'   => 'Mock Sync Job',
					'handler' => function( AIPS_Job_Context $context ) {
						return AIPS_Job_Slice_Result::complete( 1, 0, array( 'synced' => true ) );
					},
				);
				return $defs;
			}
		);

		$this->manager = new AIPS_Job_Manager( new AIPS_Job_Registry(), $this->repository, $this->cron_manager );
		$record        = $this->manager->dispatch_sync( 'mock_sync_job' );

		$this->assertInstanceOf( AIPS_Job_Record::class, $record );
		$this->assertTrue( $record->is_completed() );
		$this->assertSame( array( 'synced' => true ), $record->get_result() );
	}
}
