<?php
/**
 * Integration tests for Unified Background Job Infrastructure (issue #2376 Slice 3).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Job_Integration extends WP_UnitTestCase {

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
		remove_all_filters( 'aips_job_definitions' );
	}

	public function test_end_to_end_multislice_job_lifecycle() {
		$call_count = 0;
		add_filter(
			'aips_job_definitions',
			function ( $defs ) use ( &$call_count ) {
				$defs['multislice_integration_test'] = array(
					'label'             => 'Multislice Test',
					'hook'              => AIPS_Job_Registry::DEFAULT_TICK_HOOK,
					'group'             => 'aips-default',
					'max_retries'       => 3,
					'retry_backoff_sec' => 60,
					'batch_size'        => 5,
					'handler'           => function ( $context, $payload, $record ) use ( &$call_count ) {
						$call_count++;
						$cursor = (int) $context->get_cursor_id();
						if ( $call_count === 1 ) {
							// Process first slice (5 items), cursor becomes 5, not done
							return new AIPS_Job_Slice_Result( 5, 0, 5, false, array( 'slice' => 1 ) );
						}
						// Process second slice (5 items), cursor becomes 10, complete
						return new AIPS_Job_Slice_Result( 5, 0, 10, true, array( 'slice' => 2, 'all_done' => true ) );
					},
				);
				return $defs;
			}
		);

		$registry = new AIPS_Job_Registry();
		$manager  = new AIPS_Job_Manager( $registry, $this->repository, $this->cron_manager );

		$job_id = $manager->dispatch(
			'multislice_integration_test',
			array( 'data' => 'sample' ),
			array( 'total' => 10 )
		);

		$record = $manager->get_job( $job_id );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $record->get_status() );
		$this->assertSame( 0, $record->get_processed() );

		// Tick 1
		$manager->handle_tick( $job_id );
		$record1 = $manager->get_job( $job_id );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $record1->get_status() );
		$this->assertSame( 5, $record1->get_processed() );
		$this->assertSame( 5, $record1->get_cursor_id() );
		$this->assertFalse( $record1->is_completed() );

		// Tick 2
		$manager->handle_tick( $job_id );
		$record2 = $manager->get_job( $job_id );
		$this->assertSame( AIPS_Job_Record::STATUS_COMPLETED, $record2->get_status() );
		$this->assertSame( 10, $record2->get_processed() );
		$this->assertSame( 10, $record2->get_cursor_id() );
		$this->assertTrue( $record2->is_completed() );
		$this->assertGreaterThan( 0, $record2->get_finished_at() );
		$this->assertSame( array( 'slice' => 2, 'all_done' => true ), $record2->get_result() );
	}

	public function test_sync_execution_completes_in_single_invocation() {
		add_filter(
			'aips_job_definitions',
			function ( $defs ) {
				$defs['sync_integration_test'] = array(
					'label'             => 'Sync Test',
					'hook'              => AIPS_Job_Registry::DEFAULT_TICK_HOOK,
					'group'             => 'aips-default',
					'max_retries'       => 2,
					'retry_backoff_sec' => 30,
					'batch_size'        => 10,
					'handler'           => function ( $context, $payload ) {
						return new AIPS_Job_Slice_Result( 3, 0, 3, true, array( 'source' => 'sync' ) );
					},
				);
				return $defs;
			}
		);

		$registry = new AIPS_Job_Registry();
		$manager  = new AIPS_Job_Manager( $registry, $this->repository, $this->cron_manager );

		$final_record = $manager->dispatch_sync(
			'sync_integration_test',
			array( 'test' => true ),
			array( 'total' => 3 )
		);

		$this->assertTrue( $final_record->is_completed() );
		$this->assertSame( 3, $final_record->get_processed() );
		$this->assertSame( array( 'source' => 'sync' ), $final_record->get_result() );

		// Verify no lingering cron events
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $final_record->get_job_id() ) ) );
	}

	public function test_error_retry_and_eventual_completion() {
		$attempts = 0;
		add_filter(
			'aips_job_definitions',
			function ( $defs ) use ( &$attempts ) {
				$defs['retry_integration_test'] = array(
					'label'             => 'Retry Test',
					'hook'              => AIPS_Job_Registry::DEFAULT_TICK_HOOK,
					'group'             => 'aips-default',
					'max_retries'       => 3,
					'retry_backoff_sec' => 10,
					'batch_size'        => 5,
					'handler'           => function () use ( &$attempts ) {
						$attempts++;
						if ( $attempts === 1 ) {
							throw new RuntimeException( 'Transient network failure' );
						}
						return new AIPS_Job_Slice_Result( 5, 0, 5, true );
					},
				);
				return $defs;
			}
		);

		$registry = new AIPS_Job_Registry();
		$manager  = new AIPS_Job_Manager( $registry, $this->repository, $this->cron_manager );

		$job_id = $manager->dispatch( 'retry_integration_test', array(), array( 'total' => 5 ) );

		// First tick triggers exception
		$manager->handle_tick( $job_id );
		$record1 = $manager->get_job( $job_id );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $record1->get_status() );
		$this->assertSame( 1, $record1->get_attempts() );
		$this->assertStringContainsString( 'Transient network failure', (string) $record1->get_error_message() );

		// Second tick succeeds
		$manager->handle_tick( $job_id );
		$record2 = $manager->get_job( $job_id );
		$this->assertSame( AIPS_Job_Record::STATUS_COMPLETED, $record2->get_status() );
		$this->assertTrue( $record2->is_completed() );
	}

	public function test_heartbeat_payload_reflects_active_unified_jobs() {
		$job_id = $this->manager->dispatch(
			AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_SLICE,
			array( 'items' => array( 1, 2, 3 ) ),
			array( 'total' => 3 )
		);

		$bg_manager = new AIPS_Background_Process_Manager();
		$payload    = $bg_manager->get_heartbeat_payload();

		$this->assertArrayHasKey( 'processes', $payload );
		$this->assertArrayHasKey( 'jobs', $payload );
		$this->assertArrayHasKey( 'active_count', $payload );

		$this->assertGreaterThanOrEqual( 1, $payload['active_count'] );

		$found = false;
		foreach ( $payload['jobs'] as $job_data ) {
			if ( isset( $job_data['job_id'] ) && $job_data['job_id'] === $job_id ) {
				$found = true;
				$this->assertSame( AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_SLICE, $job_data['job_type'] );
				$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $job_data['status'] );
				break;
			}
		}

		$this->assertTrue( $found, 'Dispatched job must appear in heartbeat jobs payload' );
	}

	public function test_plugin_deactivation_clears_job_tick_hooks() {
		$job_id = $this->manager->dispatch(
			AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_SLICE,
			array( 'items' => array( 1 ) ),
			array( 'total' => 1 )
		);

		$this->assertNotFalse( $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $job_id ) ) );

		// Run deactivation sweep
		$this->cron_manager->unschedule_all_plugin_crons();

		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $job_id ) ) );
	}

	public function test_lifecycle_pause_resume_cancel_and_retry() {
		$job_id = $this->manager->dispatch(
			AIPS_Job_Registry::TYPE_BULK_BATCH_SLICE,
			array( 'items' => array( 1, 2, 3, 4 ) ),
			array( 'total' => 4 )
		);

		// Pause
		$this->assertTrue( $this->manager->pause( $job_id ) );
		$this->assertSame( AIPS_Job_Record::STATUS_PAUSED, $this->manager->get_status( $job_id ) );
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $job_id ) ) );

		// Resume
		$this->assertTrue( $this->manager->resume( $job_id ) );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $this->manager->get_status( $job_id ) );
		$this->assertNotFalse( $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $job_id ) ) );

		// Cancel
		$this->assertTrue( $this->manager->cancel( $job_id ) );
		$this->assertSame( AIPS_Job_Record::STATUS_CANCELLED, $this->manager->get_status( $job_id ) );
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Job_Registry::DEFAULT_TICK_HOOK, array( $job_id ) ) );

		// Transition manually to failed to test retry
		$this->repository->update( $job_id, array( 'status' => AIPS_Job_Record::STATUS_FAILED, 'attempts' => 3 ) );
		$this->assertTrue( $this->manager->retry( $job_id ) );
		$record = $this->manager->get_job( $job_id );
		$this->assertSame( AIPS_Job_Record::STATUS_PENDING, $record->get_status() );
		$this->assertSame( 0, $record->get_attempts() );
	}

	public function test_actionscheduler_backend_receives_declared_group() {
		$scheduled_calls = array();

		// Create a test double implementing AIPS_Cron_Backend_Interface
		$backend_double = new class( $scheduled_calls ) implements AIPS_Cron_Backend_Interface {
			public $calls;
			public function __construct( &$calls ) {
				$this->calls = &$calls;
			}
			public function get_backend_name(): string {
				return 'spy';
			}
			public function schedule_single( $timestamp, $hook, array $args = array(), $group = '' ) {
				$this->calls[] = array(
					'timestamp' => $timestamp,
					'hook'      => $hook,
					'args'      => $args,
					'group'     => $group,
				);
				return true;
			}
			public function schedule_recurring( $timestamp, $interval, $hook, array $args = array(), $group = '' ) {
				return true;
			}
			public function next_scheduled( $hook, array $args = array() ) {
				return false;
			}
			public function has_scheduled( $hook, array $args = array(), $group = '' ) {
				return false;
			}
			public function unschedule( $hook, array $args = array() ) {
				return true;
			}
			public function unschedule_all( $hook, $group = '' ) {
				return true;
			}
			public function get_scheduled_actions( $hook = '', $group = '' ) {
				return array();
			}
		};

		$manager = new AIPS_Job_Manager(
			$this->registry,
			$this->repository,
			$this->cron_manager,
			null,
			$backend_double
		);

		$job_id = $manager->dispatch(
			AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_SLICE,
			array( 'test' => 1 )
		);

		$this->assertNotEmpty( $scheduled_calls );
		$this->assertSame( AIPS_Job_Registry::GROUP_AUTHOR_TOPICS, $scheduled_calls[0]['group'] );
		$this->assertSame( AIPS_Job_Registry::DEFAULT_TICK_HOOK, $scheduled_calls[0]['hook'] );
		$this->assertSame( array( $job_id ), $scheduled_calls[0]['args'] );
	}

	public function test_job_record_percent_and_progress_percentage_aliases() {
		$record = new AIPS_Job_Record(
			array(
				'job_id'    => 'test-percent',
				'job_type'  => 'sample',
				'total'     => 20,
				'processed' => 10,
				'failed'    => 0,
			)
		);

		$this->assertSame( 50.0, $record->get_progress_percentage() );
		$this->assertSame( 50.0, $record->get_percent() );
	}
}
