<?php
/**
 * Regression and unit tests for advanced performance enhancements:
 * Action Scheduler queue abstraction, prompt caching, and automated system
 * log retention cleaner.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Advanced_Performance extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		delete_option( 'aips_cache_driver' );
		delete_option( 'aips_queue_driver' );
		delete_option( 'aips_enable_prompt_caching' );
		delete_option( 'aips_log_retention_days' );
		AIPS_Config::get_instance()->flush_option_cache();
		AIPS_Cache_Factory::reset();
	}

	public function tearDown(): void {
		delete_option( 'aips_cache_driver' );
		delete_option( 'aips_queue_driver' );
		delete_option( 'aips_enable_prompt_caching' );
		delete_option( 'aips_log_retention_days' );
		AIPS_Config::get_instance()->flush_option_cache();
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// 2. Action Scheduler Queue Abstraction
	// -----------------------------------------------------------------------

	public function test_action_scheduler_queue_availability() {
		$queue = new AIPS_Action_Scheduler_Queue();
		$expected = function_exists( 'as_schedule_single_action' );
		$this->assertSame( $expected, $queue->is_available() );
	}

	public function test_action_scheduler_queue_scheduling_fallback() {
		$queue = new AIPS_Action_Scheduler_Queue();
		$hook  = 'aips_test_queue_hook_' . uniqid();
		$args  = array( 'schedule_id' => 999 );

		wp_unschedule_hook( $hook );

		$action_id = $queue->schedule_single_action( time() + 300, $hook, $args, 'aips-test' );
		$this->assertNotEmpty( $action_id );

		// If Action Scheduler is not installed, it falls back to wp_next_scheduled.
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->assertNotFalse( wp_next_scheduled( $hook, $args ) );
			wp_unschedule_hook( $hook );
		}
	}

	// -----------------------------------------------------------------------
	// 3. Log Retention Cleaner
	// -----------------------------------------------------------------------

	public function test_log_cleaner_prunes_old_files() {
		$upload_dir = wp_upload_dir();
		$log_dir    = path_join( $upload_dir['basedir'], 'aips-logs' );

		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$old_file = path_join( $log_dir, 'test-old-' . uniqid() . '.log' );
		$new_file = path_join( $log_dir, 'test-new-' . uniqid() . '.log' );

		file_put_contents( $old_file, 'Old log line' );
		file_put_contents( $new_file, 'New log line' );

		// Artificially age the old file by 40 days.
		touch( $old_file, time() - ( 40 * DAY_IN_SECONDS ) );
		// Ensure new file is recent.
		touch( $new_file, time() );

		// Run pruning with 30-day retention.
		$result = AIPS_Log_Cleaner::prune_logs( 30 );

		$this->assertArrayHasKey( 'deleted_files_count', $result );
		$this->assertArrayHasKey( 'deleted_db_rows', $result );
		$this->assertArrayHasKey( 'cutoff_date', $result );

		$this->assertFileDoesNotExist( $old_file );
		$this->assertFileExists( $new_file );

		// Cleanup.
		if ( file_exists( $new_file ) ) {
			@unlink( $new_file );
		}
	}

	// -----------------------------------------------------------------------
	// 4. Prompt Caching
	// -----------------------------------------------------------------------

	public function test_prompt_builder_caches_site_context_when_enabled() {
		update_option( 'aips_enable_prompt_caching', 1 );
		update_option( 'aips_site_niche', 'Performance AI Testing' );
		AIPS_Config::get_instance()->flush_option_cache();
		AIPS_Cache_Factory::reset();

		$cache = AIPS_Cache_Factory::instance();
		$hash  = md5( serialize( AIPS_Site_Context::get() ) );
		$cache->delete( 'site_context_' . $hash, 'prompt_cache' );

		$builder = new AIPS_Prompt_Builder();
		$context = $builder->build_site_context_block();

		$this->assertStringContainsString( 'Performance AI Testing', $context );

		// Verify cached copy exists.
		$cached = $cache->get( 'site_context_' . $hash, 'prompt_cache' );
		$this->assertSame( $context, $cached );
	}

	// -----------------------------------------------------------------------
	// 5. Settings Registration & Sanitization
	// -----------------------------------------------------------------------

	public function test_due_schedule_hook_has_a_listener_outside_wp_cron() {
		// Action Scheduler can fire the queued due-schedule event from its async
		// admin-ajax runner or WP-CLI, not only WP-Cron. The listener must exist
		// in those contexts too, or the action is marked complete and the
		// schedule is silently skipped. Tests do not run under DOING_CRON.
		$this->assertFalse( wp_doing_cron() );
		$this->assertNotFalse(
			has_action( AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK ),
			'The due-schedule hook must be listened to in every request context.'
		);
	}

	public function test_registered_performance_settings() {
		$ui = new AIPS_Settings_UI();
		$settings = AIPS_Settings::get_registered_settings_args( $ui );

		$this->assertArrayHasKey( 'aips_queue_driver', $settings );
		$this->assertArrayHasKey( 'aips_enable_prompt_caching', $settings );
		$this->assertArrayHasKey( 'aips_log_retention_days', $settings );

		$this->assertSame( 'auto', $ui->sanitize_queue_driver( 'invalid_driver' ) );
		$this->assertSame( 'action_scheduler', $ui->sanitize_queue_driver( 'action_scheduler' ) );
		$this->assertSame( 'wp_cron', $ui->sanitize_queue_driver( 'wp_cron' ) );
	}
}
