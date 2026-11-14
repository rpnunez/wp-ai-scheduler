<?php
/**
 * Tests for AIPS_Cron_Registry — the declarative single source of truth for
 * plugin cron hooks (issue #2369).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Cron_Registry extends WP_UnitTestCase {

	/** @var AIPS_Cron_Registry */
	private $registry;

	public function setUp(): void {
		parent::setUp();
		$this->registry = new AIPS_Cron_Registry();
	}

	public function tearDown(): void {
		delete_option( 'aips_auto_prune_enabled' );
		delete_option( 'aips_notif_digest_enabled' );
		delete_option( 'aips_telemetry_prune_interval' );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Recurring events
	// -------------------------------------------------------------------------

	public function test_recurring_events_include_every_unconditional_hook() {
		$hooks = array_keys( $this->registry->get_recurring_events() );

		foreach ( array(
			'aips_generate_scheduled_posts',
			'aips_generate_author_topics',
			'aips_generate_author_posts',
			'aips_scheduled_research',
			'aips_notification_rollups',
			'aips_cleanup_export_files',
			'aips_cleanup_bulk_batch_jobs',
			'aips_cache_monitor_maintenance',
			'aips_database_prune_cleanup',
			'aips_notif_digest_cron',
		) as $expected ) {
			$this->assertContains( $expected, $hooks, "Recurring registry must declare {$expected}" );
		}
	}

	public function test_recurring_hooks_are_always_listed_regardless_of_flags() {
		// Full list must include the conditional hooks even when disabled, so
		// deactivation/diagnostics never miss them.
		update_option( 'aips_auto_prune_enabled', 0 );
		update_option( 'aips_notif_digest_enabled', 0 );

		$hooks = $this->registry->get_recurring_hooks();

		$this->assertContains( 'aips_database_prune_cleanup', $hooks );
		$this->assertContains( 'aips_notif_digest_cron', $hooks );
	}

	public function test_active_recurring_events_exclude_disabled_conditional_hooks() {
		update_option( 'aips_auto_prune_enabled', 0 );
		update_option( 'aips_notif_digest_enabled', 0 );

		$active = $this->registry->get_active_recurring_events();

		$this->assertArrayNotHasKey( 'aips_database_prune_cleanup', $active );
		$this->assertArrayNotHasKey( 'aips_notif_digest_cron', $active );
		// Unconditional hooks remain present.
		$this->assertArrayHasKey( 'aips_generate_scheduled_posts', $active );
		$this->assertSame( 'hourly', $active['aips_generate_scheduled_posts']['schedule'] );
	}

	public function test_active_recurring_events_include_enabled_conditional_hooks() {
		update_option( 'aips_auto_prune_enabled', 1 );
		update_option( 'aips_telemetry_prune_interval', 'weekly' );
		update_option( 'aips_notif_digest_enabled', 1 );

		$active = $this->registry->get_active_recurring_events();

		$this->assertArrayHasKey( 'aips_database_prune_cleanup', $active );
		$this->assertSame( 'weekly', $active['aips_database_prune_cleanup']['schedule'] );
		$this->assertArrayHasKey( 'aips_notif_digest_cron', $active );
		$this->assertSame( 'fifteen_minutes', $active['aips_notif_digest_cron']['schedule'] );
	}

	public function test_prune_recurrence_falls_back_to_daily_for_invalid_interval() {
		update_option( 'aips_auto_prune_enabled', 1 );
		update_option( 'aips_telemetry_prune_interval', 'nonsense-interval' );

		$active = $this->registry->get_active_recurring_events();

		$this->assertSame( 'daily', $active['aips_database_prune_cleanup']['schedule'] );
	}

	// -------------------------------------------------------------------------
	// Single / tick + aggregate views
	// -------------------------------------------------------------------------

	public function test_single_event_hooks_cover_parameterised_slices() {
		$hooks = array_keys( $this->registry->get_single_event_hooks() );

		foreach ( array(
			AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK,
			AIPS_Batch_Queue_Service::HOOK,
			'aips_resume_terminated_batches',
			'aips_process_author_topics_slice',
			'aips_retry_failed_author_slices_topics',
			'aips_process_author_post_slice',
			'aips_retry_failed_author_slices_posts',
			'aips_process_author_embeddings',
			AIPS_Publish_Linking_Service::CRON_HOOK,
			AIPS_Link_Index_Service::SCAN_TICK_HOOK,
			AIPS_Autolink_Run_Service::TICK_HOOK,
			AIPS_Managed_Background_Process::TICK_HOOK,
			AIPS_Bulk_Batch_Processor::HOOK,
			AIPS_Job_Registry::DEFAULT_TICK_HOOK,
			'aips_index_posts_batch',
			'aips_process_pending_indexer_queue',
		) as $expected ) {
			$this->assertContains( $expected, $hooks, "Single-event registry must declare {$expected}" );
		}
	}

	public function test_all_hooks_is_union_of_every_category() {
		update_option( 'aips_auto_prune_enabled', 0 );
		update_option( 'aips_notif_digest_enabled', 0 );

		$all = $this->registry->get_all_hooks();

		// Recurring (even disabled conditional ones).
		$this->assertContains( 'aips_database_prune_cleanup', $all );
		$this->assertContains( 'aips_notif_digest_cron', $all );
		// Optional leaf-scheduled.
		$this->assertContains( AIPS_Duplicate_Group_Service::CRON_HOOK, $all );
		$this->assertContains( AIPS_GSC_Keywords_Service::CRON_HOOK, $all );
		$this->assertContains( AIPS_Sources_Cron::HOOK, $all );
		$this->assertContains( AIPS_Sources_Maintenance::HOOK, $all );
		// Parameterised single/tick.
		$this->assertContains( AIPS_Bulk_Batch_Processor::HOOK, $all );
		$this->assertContains( 'aips_process_author_topics_slice', $all );
		// Legacy clear-only.
		$this->assertContains( 'aips_daily_rollup_insights', $all );
	}

	public function test_all_hooks_has_no_duplicates() {
		$all = $this->registry->get_all_hooks();
		$this->assertSame( array_values( array_unique( $all ) ), $all );
	}

	// -------------------------------------------------------------------------
	// Dispatch handlers
	// -------------------------------------------------------------------------

	public function test_cron_dispatch_handlers_are_callable_with_declared_arg_counts() {
		$handlers = $this->registry->get_dispatch_handlers( 'cron' );

		$this->assertArrayHasKey( 'aips_generate_scheduled_posts', $handlers );
		foreach ( $handlers as $hook => $handler ) {
			$this->assertIsCallable( $handler['callback'], "Handler for {$hook} must be callable" );
			$this->assertGreaterThanOrEqual( 1, $handler['accepted_args'], "Handler for {$hook} must accept >= 1 arg" );
		}

		// Multi-arg slices must declare their full argument count.
		$this->assertSame( 5, $handlers[ AIPS_Batch_Queue_Service::HOOK ]['accepted_args'] );
		$this->assertSame( 5, $handlers[ AIPS_Bulk_Batch_Processor::HOOK ]['accepted_args'] );
		$this->assertSame( 2, $handlers['aips_process_author_topics_slice']['accepted_args'] );
		$this->assertSame( 2, $handlers['aips_retry_failed_author_slices_posts']['accepted_args'] );
	}

	public function test_common_dispatch_handlers_cover_cross_context_hooks() {
		$handlers = $this->registry->get_dispatch_handlers( 'common' );

		$this->assertArrayHasKey( 'aips_process_pending_indexer_queue', $handlers );
		$this->assertArrayHasKey( AIPS_Managed_Background_Process::TICK_HOOK, $handlers );
		$this->assertArrayHasKey( AIPS_Job_Registry::DEFAULT_TICK_HOOK, $handlers );
	}

	public function test_cron_and_common_dispatch_handlers_do_not_overlap() {
		$cron   = array_keys( $this->registry->get_dispatch_handlers( 'cron' ) );
		$common = array_keys( $this->registry->get_dispatch_handlers( 'common' ) );

		$this->assertEmpty( array_intersect( $cron, $common ), 'A hook must be dispatched in exactly one context' );
	}

	public function test_externally_owned_recurring_hooks_are_not_self_dispatched() {
		// aips_scheduled_research (AIPS_Research_Controller) and
		// aips_notification_rollups (AIPS_Notifications_Event_Handler) register
		// their own callbacks, so the registry must not double-dispatch them.
		$cron = array_keys( $this->registry->get_dispatch_handlers( 'cron' ) );

		$this->assertNotContains( 'aips_scheduled_research', $cron );
		$this->assertNotContains( 'aips_notification_rollups', $cron );
	}

	public function test_unknown_dispatch_context_returns_empty() {
		$this->assertSame( array(), $this->registry->get_dispatch_handlers( 'frontend' ) );
	}
}
