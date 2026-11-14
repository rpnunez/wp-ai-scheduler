<?php
/**
 * Tests for migrated pure-cron leaf services (issue #2372).
 *
 * Verifies that AIPS_Duplicate_Group_Service, AIPS_GSC_Keywords_Service,
 * and AIPS_Publish_Linking_Service route their scheduling through AIPS_Cron_Manager.
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
	}

	public function tearDown(): void {
		wp_unschedule_hook( AIPS_Duplicate_Group_Service::CRON_HOOK );
		wp_unschedule_hook( AIPS_GSC_Keywords_Service::CRON_HOOK );
		wp_unschedule_hook( AIPS_Publish_Linking_Service::CRON_HOOK );
		delete_option( 'aips_duplicate_scan_schedule' );
		delete_option( 'aips_publish_linking_mode' );
		parent::tearDown();
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
}
