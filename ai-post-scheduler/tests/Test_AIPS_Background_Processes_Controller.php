<?php
/**
 * Tests for AIPS_Background_Processes_Controller.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Background_Processes_Controller extends WP_UnitTestCase {

	/** @var AIPS_Background_Processes_Controller */
	private $controller;

	/** @var AIPS_Job_Manager */
	private $job_manager;

	/** @var AIPS_Job_Repository */
	private $job_repo;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_jobs' );

		$container          = AIPS_Container::get_instance();
		$this->job_manager  = $container->get_job_manager();
		$this->job_repo     = $container->get_job_repository();
		$this->controller   = new AIPS_Background_Processes_Controller( $this->job_manager, $this->job_repo );
	}

	public function tearDown(): void {
		$this->controller->flush_summary();
		parent::tearDown();
	}

	public function test_snapshots_cover_all_six_processes() {
		$snapshots = $this->controller->get_snapshots();

		$this->assertCount( 6, $snapshots );
		$keys = wp_list_pluck( $snapshots, 'key' );

		$this->assertContains( 'internal_links_indexing', $keys );
		$this->assertContains( 'content_indexer_queue', $keys );
		$this->assertContains( 'link_index_scan', $keys );
		$this->assertContains( 'relationships_recompute', $keys );
		$this->assertContains( 'author_topic_embeddings', $keys );
		$this->assertContains( 'bulk_generation', $keys );
	}

	public function test_filter_active_and_status_labels() {
		$labels = AIPS_Background_Processes_Controller::get_status_labels();
		$this->assertArrayHasKey( 'idle', $labels );
		$this->assertArrayHasKey( 'running', $labels );
		$this->assertArrayHasKey( 'paused', $labels );

		$this->assertTrue( AIPS_Background_Processes_Controller::is_active_status( 'running' ) );
		$this->assertTrue( AIPS_Background_Processes_Controller::is_active_status( 'cooldown' ) );
		$this->assertTrue( AIPS_Background_Processes_Controller::is_active_status( 'waiting_quota' ) );
		$this->assertFalse( AIPS_Background_Processes_Controller::is_active_status( 'idle' ) );
		$this->assertFalse( AIPS_Background_Processes_Controller::is_active_status( 'paused' ) );

		$filtered = AIPS_Background_Processes_Controller::filter_active( array(
			array( 'key' => 'a', 'is_active' => true ),
			array( 'key' => 'b', 'is_active' => false ),
		) );
		$this->assertCount( 1, $filtered );
		$this->assertSame( 'a', $filtered[0]['key'] );
	}

	public function test_heartbeat_payload_structure() {
		$payload = $this->controller->get_heartbeat_payload();

		$this->assertArrayHasKey( 'processes', $payload );
		$this->assertArrayHasKey( 'jobs', $payload );
		$this->assertArrayHasKey( 'active_count', $payload );
		$this->assertArrayHasKey( 'server_time', $payload );
	}

	public function test_control_pause_resume_cancel_flow() {
		$job_id = $this->job_manager->dispatch(
			AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE,
			array( 'mode' => 'missing' ),
			array( 'total' => 5 )
		);

		$snap = $this->controller->get_snapshot( 'relationships_recompute' );
		$this->assertSame( 'running', $snap['status'] );
		$this->assertTrue( $snap['can_pause'] );

		// Pause
		$paused_snap = $this->controller->control( 'relationships_recompute', AIPS_Background_Processes_Controller::ACTION_PAUSE );
		$this->assertIsArray( $paused_snap );
		$this->assertSame( 'paused', $paused_snap['status'] );
		$this->assertTrue( $paused_snap['can_resume'] );

		// Resume
		$resumed_snap = $this->controller->control( 'relationships_recompute', AIPS_Background_Processes_Controller::ACTION_RESUME );
		$this->assertIsArray( $resumed_snap );
		$this->assertSame( 'running', $resumed_snap['status'] );

		// Cancel
		$cancelled_snap = $this->controller->control( 'relationships_recompute', AIPS_Background_Processes_Controller::ACTION_CANCEL );
		$this->assertIsArray( $cancelled_snap );
		$this->assertSame( 'idle', $cancelled_snap['status'] );
	}

	public function test_bulk_generation_snapshot_and_controls() {
		$job_id = $this->job_manager->dispatch_batch(
			'bulk_post_generation',
			array( 1, 2, 3, 4 ),
			2,
			60
		);

		$snap = $this->controller->get_snapshot( 'bulk_generation' );
		$this->assertSame( 'running', $snap['status'] );
		$this->assertSame( 4, $snap['total'] );
		$this->assertTrue( $snap['can_pause'] );

		$this->controller->control( 'bulk_generation', AIPS_Background_Processes_Controller::ACTION_PAUSE );
		$paused_snap = $this->controller->get_snapshot( 'bulk_generation' );
		$this->assertSame( 'paused', $paused_snap['status'] );

		$this->controller->control( 'bulk_generation', AIPS_Background_Processes_Controller::ACTION_CANCEL );
		$idle_snap = $this->controller->get_snapshot( 'bulk_generation' );
		$this->assertSame( 'idle', $idle_snap['status'] );
	}
}
