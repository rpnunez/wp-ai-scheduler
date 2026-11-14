<?php
/**
 * Tests for migrated pure-cron leaf services (issue #2372).
 *
 * Verifies that AIPS_Duplicate_Group_Service, AIPS_GSC_Keywords_Service,
 * AIPS_Publish_Linking_Service, AIPS_Sources_Cron, AIPS_Sources_Maintenance,
 * and AIPS_Schedule_Processor route their scheduling through AIPS_Cron_Manager.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Cron_Leaf_Services extends WP_UnitTestCase {

	/** @var AIPS_Cron_Manager */
	private $cron_manager;

	/** @var AIPS_Cron_Registry */
	private $registry;

	public function setUp(): void {
		parent::setUp();
		$this->registry     = new AIPS_Cron_Registry();
		$this->cron_manager = new AIPS_Cron_Manager( $this->registry );

		wp_unschedule_hook( AIPS_Duplicate_Group_Service::CRON_HOOK );
		wp_unschedule_hook( AIPS_GSC_Keywords_Service::CRON_HOOK );
		wp_unschedule_hook( AIPS_Publish_Linking_Service::CRON_HOOK );
		wp_unschedule_hook( AIPS_Sources_Cron::HOOK );
		wp_unschedule_hook( AIPS_Sources_Cron::CONTINUATION_HOOK );
		wp_unschedule_hook( AIPS_Sources_Maintenance::HOOK );
		wp_unschedule_hook( AIPS_Sources_Maintenance::CONTINUATION_HOOK );
		wp_unschedule_hook( AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK );
	}

	public function tearDown(): void {
		wp_unschedule_hook( AIPS_Duplicate_Group_Service::CRON_HOOK );
		wp_unschedule_hook( AIPS_GSC_Keywords_Service::CRON_HOOK );
		wp_unschedule_hook( AIPS_Publish_Linking_Service::CRON_HOOK );
		wp_unschedule_hook( AIPS_Sources_Cron::HOOK );
		wp_unschedule_hook( AIPS_Sources_Cron::CONTINUATION_HOOK );
		wp_unschedule_hook( AIPS_Sources_Maintenance::HOOK );
		wp_unschedule_hook( AIPS_Sources_Maintenance::CONTINUATION_HOOK );
		wp_unschedule_hook( AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK );
		delete_option( 'aips_duplicate_scan_schedule' );
		delete_option( 'aips_publish_linking_mode' );
		delete_option( 'aips_source_archive_retention_days' );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Constructor & Container Resolution Tests
	// -------------------------------------------------------------------------

	public function test_services_resolve_cron_manager_from_container_or_fallback() {
		// Verify default constructor instantiation without explicit cron_manager argument
		$dup_service = new AIPS_Duplicate_Group_Service();
		$this->assertInstanceOf( AIPS_Duplicate_Group_Service::class, $dup_service );

		$gsc_service = new AIPS_GSC_Keywords_Service();
		$this->assertInstanceOf( AIPS_GSC_Keywords_Service::class, $gsc_service );

		$pub_service = new AIPS_Publish_Linking_Service();
		$this->assertInstanceOf( AIPS_Publish_Linking_Service::class, $pub_service );

		$sources_cron = new AIPS_Sources_Cron();
		$this->assertInstanceOf( AIPS_Sources_Cron::class, $sources_cron );

		$sources_maint = new AIPS_Sources_Maintenance();
		$this->assertInstanceOf( AIPS_Sources_Maintenance::class, $sources_maint );

		$sched_proc = new AIPS_Schedule_Processor();
		$this->assertInstanceOf( AIPS_Schedule_Processor::class, $sched_proc );

		$sched_ctrl = new AIPS_Schedule_Controller();
		$this->assertInstanceOf( AIPS_Schedule_Controller::class, $sched_ctrl );
	}

	// -------------------------------------------------------------------------
	// Duplicate Group Service Tests
	// -------------------------------------------------------------------------

	public function test_duplicate_group_service_schedules_and_clears_via_manager() {
		$service = new AIPS_Duplicate_Group_Service( null, null, null, $this->cron_manager );

		// 1. Off by default -> ensure_schedule clears any existing hook
		update_option( 'aips_duplicate_scan_schedule', 'off' );
		$service->ensure_schedule();
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Duplicate_Group_Service::CRON_HOOK ) );

		// 2. Set to 'daily' -> schedules recurring event
		update_option( 'aips_duplicate_scan_schedule', 'daily' );
		$service->ensure_schedule();
		$next = $this->cron_manager->next_scheduled( AIPS_Duplicate_Group_Service::CRON_HOOK );
		$this->assertIsInt( $next );
		$event = wp_get_scheduled_event( AIPS_Duplicate_Group_Service::CRON_HOOK );
		$this->assertSame( 'daily', $event->schedule );

		// 3. Switch to 'weekly' -> reschedules with 'weekly'
		update_option( 'aips_duplicate_scan_schedule', 'weekly' );
		$service->ensure_schedule();
		$event = wp_get_scheduled_event( AIPS_Duplicate_Group_Service::CRON_HOOK );
		$this->assertSame( 'weekly', $event->schedule );

		// 4. Switch back to 'off' -> clears
		update_option( 'aips_duplicate_scan_schedule', 'off' );
		$service->ensure_schedule();
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Duplicate_Group_Service::CRON_HOOK ) );
	}

	// -------------------------------------------------------------------------
	// GSC Keywords Service Tests
	// -------------------------------------------------------------------------

	public function test_gsc_keywords_service_schedules_and_clears_via_manager() {
		$client_mock = $this->createMock( AIPS_GSC_Client::class );
		$client_mock->method( 'is_configured' )->willReturn( true );

		$service = new AIPS_GSC_Keywords_Service( $client_mock, null, null, $this->cron_manager );

		// When configured -> schedules daily sync
		$service->ensure_schedule();
		$next = $this->cron_manager->next_scheduled( AIPS_GSC_Keywords_Service::CRON_HOOK );
		$this->assertIsInt( $next );

		// Calling again does not duplicate
		$service->ensure_schedule();
		$this->assertSame( $next, $this->cron_manager->next_scheduled( AIPS_GSC_Keywords_Service::CRON_HOOK ) );

		// When unconfigured -> clears
		$unconfigured_client = $this->createMock( AIPS_GSC_Client::class );
		$unconfigured_client->method( 'is_configured' )->willReturn( false );
		$unconfigured_service = new AIPS_GSC_Keywords_Service( $unconfigured_client, null, null, $this->cron_manager );

		$unconfigured_service->ensure_schedule();
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_GSC_Keywords_Service::CRON_HOOK ) );
	}

	// -------------------------------------------------------------------------
	// Publish Linking Service Tests
	// -------------------------------------------------------------------------

	public function test_publish_linking_service_schedules_single_event_via_manager() {
		$post_id = $this->factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		update_option( 'aips_publish_linking_mode', 'review' );
		$service = new AIPS_Publish_Linking_Service( null, $this->cron_manager );

		// Schedule single event
		$scheduled = $service->maybe_schedule( $post_id );
		$this->assertTrue( $scheduled );

		$next = $this->cron_manager->next_scheduled( AIPS_Publish_Linking_Service::CRON_HOOK, array( $post_id ) );
		$this->assertIsInt( $next );

		// Calling again should return false (already scheduled)
		$this->assertFalse( $service->maybe_schedule( $post_id ) );

		// When mode is 'off'
		update_option( 'aips_publish_linking_mode', 'off' );
		$post_id2 = $this->factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		$this->assertFalse( $service->maybe_schedule( $post_id2 ) );
	}

	// -------------------------------------------------------------------------
	// Sources Cron & Maintenance Tests
	// -------------------------------------------------------------------------

	public function test_sources_cron_reconciles_via_cron_manager() {
		$repo_mock = $this->createMock( AIPS_Sources_Repository::class );
		$target_time = time() + 3600;
		$repo_mock->method( 'get_next_fetch_at' )->willReturn( $target_time );

		$cron = new AIPS_Sources_Cron( $repo_mock, null, null, null, $this->cron_manager );
		$cron->reconcile_owned();

		// Should have scheduled single event for target time
		$next = $this->cron_manager->next_scheduled( AIPS_Sources_Cron::HOOK );
		$this->assertSame( $target_time, $next );

		// When get_next_fetch_at returns null -> clears hooks
		$empty_repo = $this->createMock( AIPS_Sources_Repository::class );
		$empty_repo->method( 'get_next_fetch_at' )->willReturn( null );
		$cron_empty = new AIPS_Sources_Cron( $empty_repo, null, null, null, $this->cron_manager );
		$cron_empty->reconcile_owned();

		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Sources_Cron::HOOK ) );
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Sources_Cron::CONTINUATION_HOOK ) );
	}

	public function test_sources_maintenance_schedules_via_cron_manager() {
		update_option( 'aips_source_archive_retention_days', 30 );
		$maint = new AIPS_Sources_Maintenance( null, null, null, $this->cron_manager );

		$next = $this->cron_manager->next_scheduled( AIPS_Sources_Maintenance::HOOK );
		$this->assertIsInt( $next );

		// With retention days = 0 -> clears
		update_option( 'aips_source_archive_retention_days', 0 );
		$maint->schedule();
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Sources_Maintenance::HOOK ) );
		$this->assertFalse( $this->cron_manager->next_scheduled( AIPS_Sources_Maintenance::CONTINUATION_HOOK ) );
	}

	// -------------------------------------------------------------------------
	// Schedule Processor Single Queue Tests
	// -------------------------------------------------------------------------

	public function test_schedule_processor_queues_due_schedules_via_cron_manager() {
		$schedule1 = (object) array( 'schedule_id' => 101 );
		$schedule2 = (object) array( 'schedule_id' => 102 );

		$repo_mock = $this->createMock( AIPS_Schedule_Repository_Interface::class );
		$repo_mock->method( 'get_due_schedules' )->willReturn( array( $schedule1, $schedule2 ) );

		$processor = new AIPS_Schedule_Processor(
			$repo_mock,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$this->cron_manager
		);

		// First schedule runs immediately, second schedule is queued via cron_manager
		$processor->process_due_schedules();

		$queued_next = $this->cron_manager->next_scheduled(
			AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK,
			array( 102 )
		);
		$this->assertIsInt( $queued_next );

		// The first schedule should not be queued as a separate cron event
		$this->assertFalse(
			$this->cron_manager->next_scheduled(
				AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK,
				array( 101 )
			)
		);
	}
}
