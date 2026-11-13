<?php
/**
 * Tests for AIPS_Cron_Manager — the WP-Cron wrapper layer (issue #2369).
 *
 * Covers scheduling primitives, argument-aware cleanup, registry-driven
 * reconciliation, and full plugin deactivation cleanup.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Cron_Manager extends WP_UnitTestCase {

	/** @var AIPS_Cron_Manager */
	private $manager;

	/** @var AIPS_Cron_Registry */
	private $registry;

	public function setUp(): void {
		parent::setUp();

		// Ensure custom intervals (e.g. fifteen_minutes) are available to
		// wp_schedule_event regardless of boot order in the test runner.
		add_filter( 'cron_schedules', array( $this, 'register_intervals' ) );

		$this->registry = new AIPS_Cron_Registry();
		$this->manager  = new AIPS_Cron_Manager( $this->registry );

		$this->clear_all_registry_hooks();
	}

	public function tearDown(): void {
		$this->clear_all_registry_hooks();
		remove_filter( 'cron_schedules', array( $this, 'register_intervals' ) );
		delete_option( 'aips_auto_prune_enabled' );
		delete_option( 'aips_notif_digest_enabled' );
		delete_option( 'aips_telemetry_prune_interval' );
		parent::tearDown();
	}

	public function register_intervals( $schedules ) {
		return AIPS_Scheduler::instance()->add_cron_intervals( $schedules );
	}

	private function clear_all_registry_hooks() {
		foreach ( $this->registry->get_all_hooks() as $hook ) {
			wp_unschedule_hook( $hook );
		}
	}

	// -------------------------------------------------------------------------
	// Primitives
	// -------------------------------------------------------------------------

	public function test_schedule_recurring_schedules_once_and_dedupes() {
		$this->assertTrue( $this->manager->schedule_recurring( 'aips_generate_scheduled_posts', 'hourly' ) );
		$first = $this->manager->next_scheduled( 'aips_generate_scheduled_posts' );
		$this->assertIsInt( $first );

		// Second call must not create a duplicate recurring event.
		$this->assertTrue( $this->manager->schedule_recurring( 'aips_generate_scheduled_posts', 'hourly' ) );
		$this->assertSame( $first, $this->manager->next_scheduled( 'aips_generate_scheduled_posts' ) );
		$this->assertSame( 1, $this->count_instances( 'aips_generate_scheduled_posts' ) );
	}

	public function test_schedule_single_dedupe_true_skips_existing() {
		$ts = time() + 500;
		$this->assertTrue( $this->manager->schedule_single( 'aips_resume_terminated_batches', $ts ) );
		$this->assertSame( 1, $this->count_instances( 'aips_resume_terminated_batches' ) );

		// Dedupe true: no second event.
		$this->assertTrue( $this->manager->schedule_single( 'aips_resume_terminated_batches', $ts + 1000 ) );
		$this->assertSame( 1, $this->count_instances( 'aips_resume_terminated_batches' ) );
	}

	public function test_next_scheduled_is_argument_aware() {
		$this->manager->schedule_single( 'aips_process_author_post_slice', time() + 300, array( 7, 'corr' ) );

		$this->assertNotFalse( $this->manager->next_scheduled( 'aips_process_author_post_slice', array( 7, 'corr' ) ) );
		$this->assertFalse( $this->manager->next_scheduled( 'aips_process_author_post_slice', array( 99, 'corr' ) ) );
	}

	public function test_unschedule_occurrence_removes_single_timestamp() {
		$ts = time() + 400;
		$this->manager->schedule_single( 'aips_resume_terminated_batches', $ts );
		$this->assertTrue( $this->manager->unschedule_occurrence( $ts, 'aips_resume_terminated_batches' ) );
		$this->assertFalse( $this->manager->next_scheduled( 'aips_resume_terminated_batches' ) );
	}

	public function test_clear_hook_only_matches_given_args() {
		$this->manager->schedule_single( 'aips_process_author_post_slice', time() + 100, array() );
		$this->manager->schedule_single( 'aips_process_author_post_slice', time() + 200, array( 5, 'c' ) );

		// clear_hook with empty args removes only the arg-less event.
		$this->manager->clear_hook( 'aips_process_author_post_slice' );

		$this->assertFalse( $this->manager->next_scheduled( 'aips_process_author_post_slice', array() ) );
		$this->assertNotFalse( $this->manager->next_scheduled( 'aips_process_author_post_slice', array( 5, 'c' ) ) );
	}

	public function test_unschedule_all_for_hook_strips_parameterised_events() {
		$this->manager->schedule_single( 'aips_process_author_post_slice', time() + 100, array( 1, 'a' ) );
		$this->manager->schedule_single( 'aips_process_author_post_slice', time() + 200, array( 2, 'b' ) );
		$this->manager->schedule_single( 'aips_process_author_post_slice', time() + 300, array() );

		$this->manager->unschedule_all_for_hook( 'aips_process_author_post_slice' );

		$this->assertSame( 0, $this->count_instances( 'aips_process_author_post_slice' ) );
	}

	// -------------------------------------------------------------------------
	// sync_recurring_schedules
	// -------------------------------------------------------------------------

	public function test_sync_schedules_unconditional_and_skips_disabled_conditional() {
		update_option( 'aips_auto_prune_enabled', 0 );
		update_option( 'aips_notif_digest_enabled', 0 );

		$this->manager->sync_recurring_schedules();

		$this->assertNotFalse( $this->manager->next_scheduled( 'aips_generate_scheduled_posts' ) );
		$this->assertFalse( $this->manager->next_scheduled( 'aips_database_prune_cleanup' ) );
		$this->assertFalse( $this->manager->next_scheduled( 'aips_notif_digest_cron' ) );
	}

	public function test_sync_schedules_enabled_conditional_events() {
		update_option( 'aips_notif_digest_enabled', 1 );

		$this->manager->sync_recurring_schedules();

		$this->assertNotFalse( $this->manager->next_scheduled( 'aips_notif_digest_cron' ) );
		$event = wp_get_scheduled_event( 'aips_notif_digest_cron' );
		$this->assertSame( 'fifteen_minutes', $event->schedule );
	}

	public function test_sync_clears_an_event_that_became_disabled() {
		update_option( 'aips_notif_digest_enabled', 1 );
		$this->manager->sync_recurring_schedules();
		$this->assertNotFalse( $this->manager->next_scheduled( 'aips_notif_digest_cron' ) );

		update_option( 'aips_notif_digest_enabled', 0 );
		$this->manager->sync_recurring_schedules();
		$this->assertFalse( $this->manager->next_scheduled( 'aips_notif_digest_cron' ) );
	}

	public function test_sync_reschedules_when_recurrence_drifts() {
		update_option( 'aips_auto_prune_enabled', 1 );
		update_option( 'aips_telemetry_prune_interval', 'daily' );
		$this->manager->sync_recurring_schedules();
		$this->assertSame( 'daily', wp_get_scheduled_event( 'aips_database_prune_cleanup' )->schedule );

		update_option( 'aips_telemetry_prune_interval', 'weekly' );
		$this->manager->sync_recurring_schedules();
		$this->assertSame( 'weekly', wp_get_scheduled_event( 'aips_database_prune_cleanup' )->schedule );
		$this->assertSame( 1, $this->count_instances( 'aips_database_prune_cleanup' ) );
	}

	public function test_sync_is_idempotent() {
		$this->manager->sync_recurring_schedules();
		$first = $this->manager->next_scheduled( 'aips_generate_author_topics' );
		$this->manager->sync_recurring_schedules();
		$this->assertSame( $first, $this->manager->next_scheduled( 'aips_generate_author_topics' ) );
		$this->assertSame( 1, $this->count_instances( 'aips_generate_author_topics' ) );
	}

	// -------------------------------------------------------------------------
	// Full deactivation cleanup
	// -------------------------------------------------------------------------

	public function test_unschedule_all_plugin_crons_leaves_zero_hooks() {
		// Schedule a representative mix: recurring, parameterised single/tick,
		// optional leaf-scheduled, and legacy events.
		update_option( 'aips_auto_prune_enabled', 1 );
		update_option( 'aips_notif_digest_enabled', 1 );
		$this->manager->sync_recurring_schedules();

		$this->manager->schedule_single( 'aips_process_author_topics_slice', time() + 100, array( 11, 'corr-a' ) );
		$this->manager->schedule_single( 'aips_process_author_post_slice', time() + 200, array( 22, 'corr-b' ) );
		$this->manager->schedule_single( AIPS_Bulk_Batch_Processor::HOOK, time() + 300, array( 'job-1', 0, 5, 50, 'corr-c' ) );
		$this->manager->schedule_single( AIPS_Managed_Background_Process::TICK_HOOK, time() + 400, array( 'proc-key' ) );
		wp_schedule_event( time(), 'daily', AIPS_Duplicate_Group_Service::CRON_HOOK );
		wp_schedule_event( time(), 'daily', AIPS_GSC_Keywords_Service::CRON_HOOK );
		wp_schedule_single_event( time() + 500, 'aips_daily_rollup_insights' );

		// Sanity: at least some events exist before cleanup.
		$this->assertGreaterThan( 0, $this->total_registry_instances() );

		$this->manager->unschedule_all_plugin_crons();

		$this->assertSame(
			0,
			$this->total_registry_instances(),
			'Deactivation must leave zero plugin cron events, including parameterised single-event hooks.'
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function count_instances( $hook ) {
		$cron  = _get_cron_array();
		$count = 0;
		if ( is_array( $cron ) ) {
			foreach ( $cron as $events ) {
				if ( isset( $events[ $hook ] ) ) {
					$count += count( $events[ $hook ] );
				}
			}
		}
		return $count;
	}

	private function total_registry_instances() {
		$total = 0;
		foreach ( $this->registry->get_all_hooks() as $hook ) {
			$total += $this->count_instances( $hook );
		}
		return $total;
	}
}
