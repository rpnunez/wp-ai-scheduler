<?php
/**
 * Regression tests for the cache, cache-index, and scheduler load-pacing
 * changes: L1 request cache correctness, batch get_multiple(), buffered
 * index writes, staggered due schedules, and timeout yields.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Performance_Hardening extends WP_UnitTestCase {

	/**
	 * Per-test cache group, so rows persisted by the DB driver outside the
	 * test transaction can never leak between tests or runs.
	 *
	 * @var string
	 */
	private $group;

	public function setUp(): void {
		parent::setUp();
		$this->group = 'perf-' . uniqid();
		AIPS_Cache::reset_request_cache();
		wp_unschedule_hook( AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK );
	}

	public function tearDown(): void {
		wp_unschedule_hook( AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK );
		delete_option( 'aips_batch_resume_cooldown_minutes' );
		delete_option( 'aips_generation_delay_seconds' );
		AIPS_Config::get_instance()->flush_option_cache();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// L1 request cache
	// -----------------------------------------------------------------------

	public function test_l1_copy_is_invalidated_by_a_write_through_another_instance() {
		$reader = new AIPS_Cache( new AIPS_Cache_Db_Driver() );
		$writer = new AIPS_Cache( new AIPS_Cache_Db_Driver() );

		$writer->set( 'shared-key', 'v1', 0, $this->group );
		$this->assertSame( 'v1', $reader->get( 'shared-key', $this->group ) );

		$writer->set( 'shared-key', 'v2', 0, $this->group );
		$this->assertSame( 'v2', $reader->get( 'shared-key', $this->group ) );

		$writer->delete( 'shared-key', $this->group );
		$this->assertNull( $reader->get( 'shared-key', $this->group ) );
	}

	public function test_l1_does_not_cache_misses() {
		$driver = new AIPS_Cache_Db_Driver();
		$cache  = new AIPS_Cache( $driver );

		$this->assertNull( $cache->get( 'late-key', $this->group ) );

		// Another process writes straight to the shared backend.
		$driver->set( 'late-key', 'arrived', 0, $this->group );

		$this->assertSame( 'arrived', $cache->get( 'late-key', $this->group ) );
	}

	public function test_reset_request_cache_discards_l1_copies() {
		$driver = new AIPS_Cache_Db_Driver();
		$cache  = new AIPS_Cache( $driver );

		$cache->set( 'epoch-key', 'old', 0, $this->group );
		$this->assertSame( 'old', $cache->get( 'epoch-key', $this->group ) );

		// Out-of-band write the L1 layer cannot see.
		$driver->set( 'epoch-key', 'new', 0, $this->group );
		$this->assertSame( 'old', $cache->get( 'epoch-key', $this->group ) );

		AIPS_Cache::reset_request_cache();
		$this->assertSame( 'new', $cache->get( 'epoch-key', $this->group ) );
	}

	public function test_failed_driver_write_is_not_visible_through_l1() {
		$driver = $this->getMockBuilder( AIPS_Cache_Db_Driver::class )
			->onlyMethods( array( 'set', 'get' ) )
			->getMock();
		$driver->method( 'set' )->willReturn( false );
		$driver->method( 'get' )->willReturn( null );

		$cache = new AIPS_Cache( $driver );
		$cache->set( 'rejected', 'value', 0, $this->group );

		$this->assertNull( $cache->get( 'rejected', $this->group ) );
	}

	public function test_l1_store_is_capped() {
		$cache = new AIPS_Cache( new AIPS_Cache_Db_Driver() );

		for ( $i = 0; $i < AIPS_Cache::L1_MAX_ENTRIES + 25; $i++ ) {
			$cache->set( 'cap-' . $i, $i, 0, $this->group );
		}

		$store = new ReflectionProperty( AIPS_Cache::class, 'l1_store' );
		$store->setAccessible( true );
		$this->assertCount( AIPS_Cache::L1_MAX_ENTRIES, $store->getValue( $cache ) );
	}

	public function test_array_driver_bypasses_l1() {
		$cache = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
		$cache->set( 'array-key', 'value', 0, $this->group );
		$cache->get( 'array-key', $this->group );

		$store = new ReflectionProperty( AIPS_Cache::class, 'l1_store' );
		$store->setAccessible( true );
		$this->assertSame( array(), $store->getValue( $cache ) );
	}

	// -----------------------------------------------------------------------
	// get_multiple()
	// -----------------------------------------------------------------------

	public function test_get_multiple_returns_every_requested_key_for_each_driver() {
		foreach ( array( new AIPS_Cache_Array_Driver(), new AIPS_Cache_Db_Driver(), new AIPS_Cache_Wp_Object_Cache_Driver() ) as $driver ) {
			$driver->set( 'present', 'yes', 0, $this->group );
			$driver->set( 'falsy', 0, 0, $this->group );

			$result = $driver->get_multiple( array( 'present', 'falsy', 'absent' ), $this->group );

			$label = get_class( $driver );
			$this->assertSame( array( 'present', 'falsy', 'absent' ), array_keys( $result ), $label );
			$this->assertSame( 'yes', $result['present'], $label );
			$this->assertEquals( 0, $result['falsy'], $label );
			$this->assertNull( $result['absent'], $label );
		}
	}

	public function test_wp_object_cache_get_multiple_matches_get_for_stored_false() {
		$driver = new AIPS_Cache_Wp_Object_Cache_Driver();
		$driver->set( 'stored-false', false, 0, $this->group );

		$single = $driver->get( 'stored-false', $this->group );
		$multi  = $driver->get_multiple( array( 'stored-false' ), $this->group );

		$this->assertSame( $single, $multi['stored-false'] );
	}

	public function test_db_get_multiple_treats_expired_rows_as_misses_and_removes_them() {
		global $wpdb;

		$driver = new AIPS_Cache_Db_Driver();
		$driver->set( 'stale', 'old', 60, $this->group );
		$wpdb->update( $wpdb->prefix . 'aips_cache', array( 'expires_at' => 1 ), array( 'cache_group' => $this->group ) );

		$result = $driver->get_multiple( array( 'stale' ), $this->group );

		$this->assertNull( $result['stale'] );
		$this->assertSame(
			'0',
			(string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache WHERE cache_group = %s", $this->group ) )
		);
	}

	public function test_tag_versions_resolve_and_bump_in_batch() {
		$cache = new AIPS_Cache( new AIPS_Cache_Db_Driver() );

		$this->assertSame( array( 'a' => 1, 'b' => 1 ), $cache->get_tag_versions( array( 'a', 'b' ), $this->group ) );
		$cache->bump_tag_versions( array( 'a' ), $this->group );
		$this->assertSame( array( 'a' => 2, 'b' => 1 ), $cache->get_tag_versions( array( 'a', 'b' ), $this->group ) );
	}

	// -----------------------------------------------------------------------
	// Cache index buffering
	// -----------------------------------------------------------------------

	public function test_index_writes_are_buffered_until_flush() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_cache_index';
		$wpdb->query( "DELETE FROM {$table}" );

		$index = new AIPS_Cache_Index();
		$index->record_set( 'buffered', 'value', 0, 'idx' );
		$this->assertSame( '0', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );

		$index->flush_buffer();
		$this->assertSame( '1', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
	}

	public function test_index_delete_before_flush_drops_buffered_write() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_cache_index';
		$wpdb->query( "DELETE FROM {$table}" );

		$index = new AIPS_Cache_Index();
		$index->record_set( 'short-lived', 'value', 0, 'idx' );
		$index->record_delete( 'short-lived', 'idx' );
		$index->flush_buffer();

		$this->assertSame( '0', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
	}

	public function test_index_buffer_flushes_early_past_threshold() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_cache_index';
		$wpdb->query( "DELETE FROM {$table}" );

		$index = new AIPS_Cache_Index();
		for ( $i = 0; $i < AIPS_Cache_Index::BUFFER_FLUSH_THRESHOLD; $i++ ) {
			$index->record_set( 'bulk-' . $i, $i, 0, 'idx' );
		}

		$this->assertGreaterThan( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
	}

	// -----------------------------------------------------------------------
	// Scheduler
	// -----------------------------------------------------------------------

	/**
	 * Build a processor wired to mocks.
	 *
	 * @param AIPS_Schedule_Repository_Interface $repository Repository mock.
	 * @param object|null                        $generator  Generator mock.
	 * @return AIPS_Schedule_Processor
	 */
	private function make_processor( $repository, $generator = null ) {
		$generator = $generator ?: $this->getMockBuilder( AIPS_Generator::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'generate_post' ) )
			->getMock();

		$runner = $this->getMockBuilder( AIPS_Generation_Execution_Runner::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'run' ) )
			->getMock();
		$runner->method( 'run' )->willReturnCallback(
			function ( $work ) {
				return $work();
			}
		);

		$batch_service = $this->getMockBuilder( AIPS_Batch_Queue_Service::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_large_batch_threshold', 'needs_batch_queue' ) )
			->getMock();
		$batch_service->method( 'get_large_batch_threshold' )->willReturn( 5 );
		$batch_service->method( 'needs_batch_queue' )->willReturn( false );

		$template_repository = $this->getMockBuilder( AIPS_Template_Repository::class )
			->disableOriginalConstructor()
			->getMock();
		$template_type_selector = $this->getMockBuilder( AIPS_Template_Type_Selector::class )
			->disableOriginalConstructor()
			->getMock();
		$result_handler = $this->getMockBuilder( AIPS_Schedule_Result_Handler::class )
			->disableOriginalConstructor()
			->getMock();

		$processor = new AIPS_Schedule_Processor(
			$repository,
			$template_repository,
			$generator,
			$this->createMock( AIPS_History_Service_Interface::class ),
			$template_type_selector,
			$this->createMock( AIPS_Logger_Interface::class ),
			$runner,
			$result_handler
		);
		$processor->set_batch_queue_service( $batch_service );

		return $processor;
	}

	private function schedule_row( $id, $next_run, array $extra = array() ) {
		return (object) array_merge(
			array(
				'id'            => $id,
				'schedule_id'   => $id,
				'template_id'   => 900 + $id,
				'name'          => 'Schedule ' . $id,
				'frequency'     => 'daily',
				'next_run'      => $next_run,
				'post_quantity' => 1,
				'run_state'     => '',
			),
			$extra
		);
	}

	public function test_due_schedules_after_the_first_are_queued_without_touching_next_run() {
		$now        = AIPS_DateTime::now()->timestamp();
		$repository = $this->createMock( AIPS_Schedule_Repository_Interface::class );
		$repository->method( 'get_due_schedules' )->willReturn(
			array(
				$this->schedule_row( 1, $now - 60 ),
				$this->schedule_row( 2, $now - 30 ),
				$this->schedule_row( 3, $now - 10 ),
			)
		);
		// Only the first schedule is claimed inline; returning false stops it there.
		$repository->expects( $this->once() )
			->method( 'claim_due_schedule' )
			->with( 1, $now - 60, $this->anything() )
			->willReturn( false );
		$repository->expects( $this->never() )->method( 'update' );

		$this->make_processor( $repository )->process_due_schedules();

		$this->assertNotFalse( wp_next_scheduled( AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK, array( 2 ) ) );
		$this->assertNotFalse( wp_next_scheduled( AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK, array( 3 ) ) );
		$this->assertFalse( wp_next_scheduled( AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK, array( 1 ) ) );
	}

	public function test_queued_schedule_is_skipped_when_no_longer_due() {
		$repository = $this->createMock( AIPS_Schedule_Repository_Interface::class );
		$repository->expects( $this->once() )
			->method( 'get_due_schedule_by_id' )
			->with( 7 )
			->willReturn( null );
		$repository->expects( $this->never() )->method( 'claim_due_schedule' );

		$this->make_processor( $repository )->process_queued_due_schedule( 7 );
	}

	public function test_queued_schedule_claims_from_its_own_time_slot() {
		// A past 09:00 slot; the queued event fires later than the slot, and
		// the claim must advance from the slot, not from the firing time.
		$slot       = strtotime( gmdate( 'Y-m-d', AIPS_DateTime::now()->timestamp() - 2 * DAY_IN_SECONDS ) . ' 09:00:00 UTC' );
		$expected   = ( new AIPS_Interval_Calculator() )->calculate_next_run( 'daily', $slot );
		$repository = $this->createMock( AIPS_Schedule_Repository_Interface::class );
		$repository->method( 'get_due_schedule_by_id' )->willReturn( $this->schedule_row( 8, $slot ) );
		$repository->expects( $this->once() )
			->method( 'claim_due_schedule' )
			->with(
				8,
				$slot,
				$this->callback(
					function ( $new_next_run ) use ( $slot, $expected ) {
						return $new_next_run === $expected && 0 === ( $new_next_run - $slot ) % DAY_IN_SECONDS;
					}
				)
			)
			->willReturn( false );

		$this->make_processor( $repository )->process_queued_due_schedule( 8 );
	}

	public function test_resumed_yielded_batch_claims_back_to_the_original_occurrence() {
		$resume_at       = AIPS_DateTime::now()->timestamp() - 5;
		$resume_next_run = $resume_at + 3 * HOUR_IN_SECONDS;
		$repository      = $this->createMock( AIPS_Schedule_Repository_Interface::class );
		$repository->method( 'get_due_schedule_by_id' )->willReturn(
			$this->schedule_row(
				9,
				$resume_at,
				array(
					'run_state' => wp_json_encode(
						array(
							'status'          => AIPS_Schedule_Processor::RUN_STATE_YIELDED,
							'resume_next_run' => $resume_next_run,
						)
					),
				)
			)
		);
		$repository->expects( $this->once() )
			->method( 'claim_due_schedule' )
			->with( 9, $resume_at, $resume_next_run )
			->willReturn( false );

		$this->make_processor( $repository )->process_queued_due_schedule( 9 );
	}

	/**
	 * Force is_time_exhausted() to report an almost-spent budget.
	 */
	private function exhaust_time_budget() {
		$this->previous_max_execution_time = ini_get( 'max_execution_time' );
		$this->previous_request_time       = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? $_SERVER['REQUEST_TIME_FLOAT'] : null;
		ini_set( 'max_execution_time', '30' );
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 25;
	}

	private function restore_time_budget() {
		ini_set( 'max_execution_time', (string) $this->previous_max_execution_time );
		$_SERVER['REQUEST_TIME_FLOAT'] = $this->previous_request_time;
	}

	/** @var mixed */
	private $previous_max_execution_time;

	/** @var mixed */
	private $previous_request_time;

	private function run_batch_progress( $processor, $schedule, $quantity, $is_manual ) {
		$method = new ReflectionMethod( AIPS_Schedule_Processor::class, 'execute_batch_progress' );
		$method->setAccessible( true );
		return $method->invoke( $processor, $schedule, null, $quantity, $is_manual );
	}

	public function test_automated_batch_yields_with_progress_data_when_time_runs_out() {
		update_option( 'aips_generation_delay_seconds', 0 );
		AIPS_Config::get_instance()->flush_option_cache();

		$generator = $this->getMockBuilder( AIPS_Generator::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'generate_post' ) )
			->getMock();
		$generator->expects( $this->once() )->method( 'generate_post' )->willReturn( 501 );

		$repository = $this->createMock( AIPS_Schedule_Repository_Interface::class );
		$processor  = $this->make_processor( $repository, $generator );

		$this->exhaust_time_budget();
		try {
			list( $result, $finished ) = $this->run_batch_progress( $processor, $this->schedule_row( 10, 0 ), 3, false );
		} finally {
			$this->restore_time_budget();
		}

		$this->assertFalse( $finished );
		$this->assertWPError( $result );
		$this->assertSame( 'batch_interrupted_timeout', $result->get_error_code() );
		$this->assertSame( array( 'completed' => 1, 'total' => 3 ), $result->get_error_data() );
	}

	public function test_manual_batch_interrupted_by_time_is_not_reported_as_success() {
		$generator = $this->getMockBuilder( AIPS_Generator::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'generate_post' ) )
			->getMock();
		$generator->expects( $this->once() )->method( 'generate_post' )->willReturn( 502 );

		$repository = $this->createMock( AIPS_Schedule_Repository_Interface::class );
		$processor  = $this->make_processor( $repository, $generator );

		$this->exhaust_time_budget();
		try {
			list( $result ) = $this->run_batch_progress( $processor, $this->schedule_row( 11, 0 ), 3, true );
		} finally {
			$this->restore_time_budget();
		}

		$this->assertWPError( $result );
		$this->assertSame( 'batch_interrupted_timeout', $result->get_error_code() );
	}

	public function test_yielded_batch_waits_for_the_configured_cooldown() {
		update_option( 'aips_batch_resume_cooldown_minutes', 10 );
		AIPS_Config::get_instance()->flush_option_cache();

		$now              = AIPS_DateTime::now()->timestamp();
		$claimed_next_run = $now + DAY_IN_SECONDS;
		$schedule         = $this->schedule_row( 12, $now - 60, array( 'claimed_next_run' => $claimed_next_run ) );

		$repository = $this->createMock( AIPS_Schedule_Repository_Interface::class );
		$repository->expects( $this->once() )
			->method( 'update_run_state' )
			->with(
				12,
				$this->callback(
					function ( $state ) use ( $claimed_next_run ) {
						return AIPS_Schedule_Processor::RUN_STATE_YIELDED === $state['status']
							&& $claimed_next_run === $state['resume_next_run']
							&& 1 === $state['completed']
							&& 3 === $state['total'];
					}
				)
			);
		$repository->expects( $this->once() )
			->method( 'update' )
			->with(
				12,
				$this->callback(
					function ( $data ) use ( $now ) {
						return abs( $data['next_run'] - ( $now + 10 * MINUTE_IN_SECONDS ) ) <= 2;
					}
				)
			);
		$repository->expects( $this->never() )->method( 'delete' );

		$method = new ReflectionMethod( AIPS_Schedule_Processor::class, 'defer_yielded_batch' );
		$method->setAccessible( true );
		$method->invoke(
			$this->make_processor( $repository ),
			$schedule,
			new WP_Error( 'batch_interrupted_timeout', 'paused', array( 'completed' => 1, 'total' => 3 ) ),
			null
		);

		$event = wp_next_scheduled( AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK, array( 12 ) );
		$this->assertNotFalse( $event );
		$this->assertLessThanOrEqual( 2, abs( $event - ( $now + 10 * MINUTE_IN_SECONDS ) ) );
	}

	public function test_yield_leaves_next_run_alone_when_the_next_occurrence_is_sooner_than_the_cooldown() {
		update_option( 'aips_batch_resume_cooldown_minutes', 60 );
		AIPS_Config::get_instance()->flush_option_cache();

		$now      = AIPS_DateTime::now()->timestamp();
		$schedule = $this->schedule_row( 13, $now - 60, array( 'claimed_next_run' => $now + 5 * MINUTE_IN_SECONDS ) );

		$repository = $this->createMock( AIPS_Schedule_Repository_Interface::class );
		$repository->expects( $this->once() )->method( 'update_run_state' );
		$repository->expects( $this->never() )->method( 'update' );

		$method = new ReflectionMethod( AIPS_Schedule_Processor::class, 'defer_yielded_batch' );
		$method->setAccessible( true );
		$method->invoke(
			$this->make_processor( $repository ),
			$schedule,
			new WP_Error( 'batch_interrupted_timeout', 'paused', array( 'completed' => 1, 'total' => 3 ) ),
			null
		);

		$this->assertFalse( wp_next_scheduled( AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK, array( 13 ) ) );
	}

	// -----------------------------------------------------------------------
	// Settings
	// -----------------------------------------------------------------------

	public function test_pacing_settings_are_registered_and_clamped() {
		$settings = AIPS_Settings::get_registered_settings_args( new AIPS_Settings_UI() );

		$this->assertArrayHasKey( 'aips_generation_delay_seconds', $settings );
		$this->assertArrayHasKey( 'aips_batch_resume_cooldown_minutes', $settings );
		$this->assertSame( 30, call_user_func( $settings['aips_generation_delay_seconds']['sanitize_callback'], 999 ) );
		$this->assertSame( 1, call_user_func( $settings['aips_batch_resume_cooldown_minutes']['sanitize_callback'], 0 ) );
	}
}
