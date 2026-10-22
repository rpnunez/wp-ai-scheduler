<?php
/**
 * Tests for the bulk action endpoint of AIPS_Internal_Links_Controller.
 *
 * @package AI_Post_Scheduler
 */
class Test_AIPS_Internal_Links_Controller extends WP_Ajax_UnitTestCase {

	/** @var AIPS_Internal_Links_Repository */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->repo = new AIPS_Internal_Links_Repository();
		new AIPS_Internal_Links_Controller( null, $this->repo );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array( 'nonce' => wp_create_nonce( 'aips_ajax_nonce' ) );
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'aips_internal_links' );
		$_POST = array();
		parent::tearDown();
	}

	private function call_bulk() {
		try {
			$this->_handleAjax( 'aips_internal_links_bulk_action' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected AJAX exit.
		}
		return json_decode( $this->_last_response, true );
	}

	public function test_bulk_rejects_invalid_nonce() {
		$_POST['nonce']       = 'bad';
		$_POST['bulk_action'] = 'delete';
		$_POST['ids']         = array( 1 );
		$this->assertFalse( $this->call_bulk()['success'] );
	}

	public function test_bulk_requires_capability() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST['nonce']       = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['bulk_action'] = 'delete';
		$_POST['ids']         = array( 1 );
		$this->assertFalse( $this->call_bulk()['success'] );
	}

	public function test_bulk_rejects_unknown_action_and_oversized_batches() {
		$_POST['bulk_action'] = 'explode';
		$_POST['ids']         = array( 1 );
		$this->assertFalse( $this->call_bulk()['success'] );

		$_POST['bulk_action'] = 'delete';
		$_POST['ids']         = range( 1, 201 );
		$this->assertFalse( $this->call_bulk()['success'] );
	}

	public function test_bulk_reject_only_changes_pending_rows() {
		$pending  = $this->repo->insert( 100, 200, 0.9 );
		$inserted = $this->repo->insert( 101, 201, 0.9 );
		$this->repo->update_status( $inserted, 'inserted' );

		$_POST['bulk_action'] = 'reject';
		$_POST['ids']         = array( $pending, $inserted );
		$response             = $this->call_bulk();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $response['data']['count'] );
		$this->assertEquals( 'rejected', $this->repo->get_by_source_post( 100 )[0]->status );
		$this->assertEquals( 'inserted', $this->repo->get_by_source_post( 101 )[0]->status );
	}

	public function test_bulk_delete_removes_rows() {
		$id = $this->repo->insert( 102, 202, 0.9 );

		$_POST['bulk_action'] = 'delete';
		$_POST['ids']         = array( $id );
		$response             = $this->call_bulk();

		$this->assertTrue( $response['success'] );
		$this->assertCount( 0, $this->repo->get_by_source_post( 102 ) );
	}
}
