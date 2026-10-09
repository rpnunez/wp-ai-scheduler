<?php
/**
 * Tests for AIPS_Background_Process_Manager and the content-indexer queue adapter.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Background_Process_Manager extends WP_UnitTestCase {

	/** @var AIPS_Background_Process_Manager */
	private $manager;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->manager = new AIPS_Background_Process_Manager();
		$this->manager->flush_summary();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_background_processes' );
		delete_option( 'aips_pending_index_queue' );
		delete_option( 'aips_pending_topic_index_queue' );
		delete_option( AIPS_Content_Indexer_Service::QUEUE_PAUSED_OPTION );
		wp_clear_scheduled_hook( 'aips_process_pending_indexer_queue' );
		$this->manager->flush_summary();
		parent::tearDown();
	}

	public function test_registers_the_built_in_processes() {
		$keys = array_keys( $this->manager->all() );

		$this->assertContains( 'internal_links_indexing', $keys );
		$this->assertContains( 'content_indexer_queue', $keys );
		$this->assertContains( 'link_index_scan', $keys );
	}

	public function test_snapshots_share_one_shape() {
		foreach ( $this->manager->get_snapshots() as $snapshot ) {
			foreach ( array( 'key', 'label', 'status', 'status_label', 'is_active', 'can_start', 'can_pause', 'can_resume', 'can_cancel', 'processed', 'total', 'percent', 'message' ) as $field ) {
				$this->assertArrayHasKey( $field, $snapshot, "{$snapshot['key']} is missing {$field}" );
			}
		}
	}

	public function test_control_rejects_unknown_process_and_action() {
		$result = $this->manager->control( 'does_not_exist', 'start' );
		$this->assertWPError( $result );
		$this->assertSame( 'aips_bg_unknown_process', $result->get_error_code() );

		$result = $this->manager->control( 'content_indexer_queue', 'explode' );
		$this->assertWPError( $result );
		$this->assertSame( 'aips_bg_unknown_action', $result->get_error_code() );
	}

	public function test_content_queue_can_be_paused_resumed_and_stopped() {
		update_option( 'aips_pending_index_queue', array( 11, 12, 13 ), false );

		$snapshot = $this->manager->get( 'content_indexer_queue' )->get_snapshot();
		$this->assertSame( 3, $snapshot['total'] );
		$this->assertTrue( $snapshot['can_pause'] );

		$indexer = AIPS_Container::get_instance()->make( AIPS_Content_Indexer_Service::class );

		$snapshot = $this->manager->control( 'content_indexer_queue', 'pause' );
		$this->assertSame( 'paused', $snapshot['status'] );
		$this->assertFalse( $indexer->is_queue_worker_scheduled() );

		// Queued items are kept while paused, and no worker is scheduled.
		$indexer->enqueue_post_for_indexing( 14 );
		$this->assertSame( array( 11, 12, 13, 14 ), get_option( 'aips_pending_index_queue' ) );
		$this->assertFalse( $indexer->is_queue_worker_scheduled() );
		$this->assertSame( 'paused', $indexer->process_pending_indexer_queue()['status'] );

		$snapshot = $this->manager->control( 'content_indexer_queue', 'resume' );
		$this->assertNotSame( 'paused', $snapshot['status'] );
		$this->assertTrue( $indexer->is_queue_worker_scheduled() );

		$snapshot = $this->manager->control( 'content_indexer_queue', 'cancel' );
		$this->assertSame( 'idle', $snapshot['status'] );
		$this->assertEmpty( get_option( 'aips_pending_index_queue' ) );
	}

	public function test_pause_all_pauses_running_processes_only() {
		update_option( 'aips_pending_index_queue', array( 21 ), false );

		$paused = $this->manager->pause_all();

		$this->assertContains( 'content_indexer_queue', $paused );
		$this->assertNotContains( 'internal_links_indexing', $paused, 'An idle process has nothing to pause.' );
	}

	public function test_heartbeat_answers_only_admins_who_ask() {
		$response = $this->manager->on_heartbeat_received( array(), array() );
		$this->assertArrayNotHasKey( 'aips_bg', $response );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = $this->manager->on_heartbeat_received( array(), array( 'aips_bg' => 1 ) );
		$this->assertArrayNotHasKey( 'aips_bg', $response );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$response = $this->manager->on_heartbeat_received( array(), array( 'aips_bg' => 1 ) );
		$this->assertArrayHasKey( 'aips_bg', $response );
		$this->assertArrayHasKey( 'processes', $response['aips_bg'] );
		$this->assertArrayHasKey( 'active_count', $response['aips_bg'] );
	}

	public function test_filter_active_keeps_running_and_waiting_snapshots() {
		$active = AIPS_Background_Process_Manager::filter_active( array(
			array( 'key' => 'a', 'is_active' => true ),
			array( 'key' => 'b', 'is_active' => false ),
		) );

		$this->assertSame( array( 'a' ), wp_list_pluck( $active, 'key' ) );
	}

	public function test_ajax_registry_routes_the_background_actions() {
		foreach ( array( 'aips_bg_list', 'aips_bg_estimate', 'aips_bg_start', 'aips_bg_pause', 'aips_bg_resume', 'aips_bg_cancel', 'aips_bg_pause_all' ) as $action ) {
			$this->assertSame( 'AIPS_Background_Processes_Controller', AIPS_Ajax_Registry::get_controller_for( $action ), $action );
		}
	}

	public function test_diagnostics_exposes_the_background_processes_tab() {
		$controller = new AIPS_Diagnostics_Controller();

		$this->assertArrayHasKey( 'background-processes', $controller->get_tabs() );
		$this->assertTrue( AIPS_Diagnostics_Controller::is_tab_available( 'background-processes' ) );
	}
}
