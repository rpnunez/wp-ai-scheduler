<?php
/**
 * Tests for AIPS_Notifications_Controller AJAX endpoints.
 *
 * Covers capability checks, nonce enforcement, and expected repository
 * calls/response shapes for all six AJAX handlers.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Notifications_Controller extends WP_UnitTestCase {

	/**
	 * @var AIPS_Notifications_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Notifications_Controller
	 */
	private $controller;

	public function setUp(): void {
		parent::setUp();

		AIPS_DB_Manager::install_tables();

		$this->repository = new AIPS_Notifications_Repository();
		$this->controller = new AIPS_Notifications_Controller( $this->repository );

		// Start each test with a clean notifications table.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_notifications" );

		// Default to admin user.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_notifications" );
		parent::tearDown();
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	/**
	 * Call a controller method and decode the JSON output.
	 *
	 * @param callable $callable Controller method reference.
	 * @return array|null Decoded JSON or null.
	 */
	private function call_ajax( $callable ) {
		ob_start();
		try {
			call_user_func( $callable );
		} catch ( WPAjaxDieStopException $e ) {
			// nonce/die path
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_* path
		}
		$output = ob_get_clean();
		return json_decode( $output, true );
	}

	/**
	 * Build a valid admin nonce for the common aips_ajax_nonce action.
	 *
	 * @return string
	 */
	private function valid_nonce() {
		return wp_create_nonce( 'aips_ajax_nonce' );
	}

	// -----------------------------------------------------------------
	// ajax_get_notifications_list: nonce & capability checks
	// -----------------------------------------------------------------

	/**
	 * Test that ajax_get_notifications_list() rejects missing nonce.
	 */
	public function test_get_list_requires_valid_nonce() {
		$_POST = array( 'action' => 'aips_get_notifications_list' );

		$exception_thrown = false;
		ob_start();
		try {
			$this->controller->ajax_get_notifications_list();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue( $exception_thrown, 'Nonce check should throw an exception' );
	}

	/**
	 * Test that ajax_get_notifications_list() rejects non-admin users.
	 */
	public function test_get_list_requires_manage_options() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$_POST = array(
			'action' => 'aips_get_notifications_list',
			'nonce'  => wp_create_nonce( 'aips_ajax_nonce' ),
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_get_notifications_list' ) );

		if ( $response !== null ) {
			$this->assertFalse( $response['success'], 'Subscriber should be denied' );
		}
	}

	/**
	 * Test that ajax_get_notifications_list() returns paginated items and summary.
	 */
	public function test_get_list_returns_items_and_summary() {
		$this->repository->create( 'test', 'Msg 1' );
		$this->repository->create( 'test', 'Msg 2' );

		$_POST = array(
			'action'   => 'aips_get_notifications_list',
			'nonce'    => $this->valid_nonce(),
			'page'     => 1,
			'per_page' => 20,
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_get_notifications_list' ) );

		$this->assertNotNull( $response, 'Response must be valid JSON' );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'items',   $response['data'] );
		$this->assertArrayHasKey( 'total',   $response['data'] );
		$this->assertArrayHasKey( 'pages',   $response['data'] );
		$this->assertArrayHasKey( 'summary', $response['data'] );
		$this->assertCount( 2, $response['data']['items'] );
		$this->assertEquals( 2, $response['data']['total'] );
	}

	/**
	 * Test that ajax_get_notifications_list() forwards level filter to repository.
	 */
	public function test_get_list_applies_level_filter() {
		$this->repository->create_notification( array( 'type' => 'test', 'message' => 'E', 'level' => 'error' ) );
		$this->repository->create_notification( array( 'type' => 'test', 'message' => 'I', 'level' => 'info' ) );

		$_POST = array(
			'action' => 'aips_get_notifications_list',
			'nonce'  => $this->valid_nonce(),
			'level'  => 'error',
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_get_notifications_list' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 1, $response['data']['total'] );
		$this->assertEquals( 'error', $response['data']['items'][0]->level );
	}

	// -----------------------------------------------------------------
	// ajax_mark_notification_read
	// -----------------------------------------------------------------

	/**
	 * Test that ajax_mark_notification_read() rejects missing nonce.
	 */
	public function test_mark_read_requires_nonce() {
		$_POST = array( 'action' => 'aips_mark_notification_read', 'id' => 1 );

		$exception_thrown = false;
		ob_start();
		try {
			$this->controller->ajax_mark_notification_read();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue( $exception_thrown );
	}

	/**
	 * Test that ajax_mark_notification_read() rejects non-admin.
	 */
	public function test_mark_read_requires_manage_options() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array(
			'action' => 'aips_mark_notification_read',
			'nonce'  => $this->valid_nonce(),
			'id'     => 1,
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_mark_notification_read' ) );

		if ( $response !== null ) {
			$this->assertFalse( $response['success'] );
		}
	}

	/**
	 * Test that ajax_mark_notification_read() returns error for invalid ID.
	 */
	public function test_mark_read_returns_error_for_invalid_id() {
		$_POST = array(
			'action' => 'aips_mark_notification_read',
			'nonce'  => $this->valid_nonce(),
			'id'     => 0,
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_mark_notification_read' ) );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid', $response['data']['message'] );
	}

	/**
	 * Test that ajax_mark_notification_read() marks the notification as read.
	 */
	public function test_mark_read_marks_notification() {
		$id = $this->repository->create( 'test', 'Mark me' );

		$_POST = array(
			'action' => 'aips_mark_notification_read',
			'nonce'  => $this->valid_nonce(),
			'id'     => $id,
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_mark_notification_read' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 0, $this->repository->count_unread() );
	}

	// -----------------------------------------------------------------
	// ajax_mark_notification_unread
	// -----------------------------------------------------------------

	/**
	 * Test that ajax_mark_notification_unread() rejects missing nonce.
	 */
	public function test_mark_unread_requires_nonce() {
		$_POST = array( 'action' => 'aips_mark_notification_unread', 'id' => 1 );

		$exception_thrown = false;
		ob_start();
		try {
			$this->controller->ajax_mark_notification_unread();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue( $exception_thrown );
	}

	/**
	 * Test that ajax_mark_notification_unread() marks notification as unread.
	 */
	public function test_mark_unread_marks_notification() {
		$id = $this->repository->create( 'test', 'Make me unread' );
		$this->repository->mark_as_read( $id );

		$_POST = array(
			'action' => 'aips_mark_notification_unread',
			'nonce'  => $this->valid_nonce(),
			'id'     => $id,
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_mark_notification_unread' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 1, $this->repository->count_unread() );
	}

	// -----------------------------------------------------------------
	// ajax_delete_notification
	// -----------------------------------------------------------------

	/**
	 * Test that ajax_delete_notification() rejects missing nonce.
	 */
	public function test_delete_requires_nonce() {
		$_POST = array( 'action' => 'aips_delete_notification', 'id' => 1 );

		$exception_thrown = false;
		ob_start();
		try {
			$this->controller->ajax_delete_notification();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue( $exception_thrown );
	}

	/**
	 * Test that ajax_delete_notification() rejects non-admin.
	 */
	public function test_delete_requires_manage_options() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array(
			'action' => 'aips_delete_notification',
			'nonce'  => $this->valid_nonce(),
			'id'     => 1,
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_delete_notification' ) );

		if ( $response !== null ) {
			$this->assertFalse( $response['success'] );
		}
	}

	/**
	 * Test that ajax_delete_notification() returns error for invalid ID.
	 */
	public function test_delete_returns_error_for_invalid_id() {
		$_POST = array(
			'action' => 'aips_delete_notification',
			'nonce'  => $this->valid_nonce(),
			'id'     => 0,
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_delete_notification' ) );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid', $response['data']['message'] );
	}

	/**
	 * Test that ajax_delete_notification() deletes the notification.
	 */
	public function test_delete_removes_notification() {
		global $wpdb;
		$id = $this->repository->create( 'test', 'Delete this' );

		$_POST = array(
			'action' => 'aips_delete_notification',
			'nonce'  => $this->valid_nonce(),
			'id'     => $id,
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_delete_notification' ) );

		$this->assertTrue( $response['success'] );
		$exists = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}aips_notifications WHERE id = %d",
			$id
		) );
		$this->assertEquals( 0, $exists );
	}

	// -----------------------------------------------------------------
	// ajax_bulk_notifications_action
	// -----------------------------------------------------------------

	/**
	 * Test that ajax_bulk_notifications_action() rejects missing nonce.
	 */
	public function test_bulk_action_requires_nonce() {
		$_POST = array(
			'action'      => 'aips_bulk_notifications_action',
			'bulk_action' => 'mark_read',
			'ids'         => array( 1 ),
		);

		$exception_thrown = false;
		ob_start();
		try {
			$this->controller->ajax_bulk_notifications_action();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue( $exception_thrown );
	}

	/**
	 * Test that ajax_bulk_notifications_action() returns error for empty IDs.
	 */
	public function test_bulk_action_returns_error_for_empty_ids() {
		$_POST = array(
			'action'      => 'aips_bulk_notifications_action',
			'nonce'       => $this->valid_nonce(),
			'bulk_action' => 'mark_read',
			'ids'         => array(),
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_notifications_action' ) );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'No notifications', $response['data']['message'] );
	}

	/**
	 * Test that ajax_bulk_notifications_action() returns error for invalid action.
	 */
	public function test_bulk_action_returns_error_for_invalid_action() {
		$id = $this->repository->create( 'test', 'Test' );

		$_POST = array(
			'action'      => 'aips_bulk_notifications_action',
			'nonce'       => $this->valid_nonce(),
			'bulk_action' => 'invalid_action',
			'ids'         => array( $id ),
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_notifications_action' ) );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid bulk action', $response['data']['message'] );
	}

	/**
	 * Test that ajax_bulk_notifications_action() bulk-marks as read.
	 */
	public function test_bulk_action_mark_read_succeeds() {
		$id1 = $this->repository->create( 'test', 'Bulk 1' );
		$id2 = $this->repository->create( 'test', 'Bulk 2' );

		$_POST = array(
			'action'      => 'aips_bulk_notifications_action',
			'nonce'       => $this->valid_nonce(),
			'bulk_action' => 'mark_read',
			'ids'         => array( $id1, $id2 ),
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_notifications_action' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 2, $response['data']['count'] );
		$this->assertEquals( 0, $this->repository->count_unread() );
	}

	/**
	 * Test that ajax_bulk_notifications_action() bulk-marks as unread.
	 */
	public function test_bulk_action_mark_unread_succeeds() {
		$id1 = $this->repository->create( 'test', 'BU 1' );
		$id2 = $this->repository->create( 'test', 'BU 2' );
		$this->repository->mark_as_read( $id1 );
		$this->repository->mark_as_read( $id2 );

		$_POST = array(
			'action'      => 'aips_bulk_notifications_action',
			'nonce'       => $this->valid_nonce(),
			'bulk_action' => 'mark_unread',
			'ids'         => array( $id1, $id2 ),
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_notifications_action' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 2, $this->repository->count_unread() );
	}

	/**
	 * Test that ajax_bulk_notifications_action() bulk-deletes.
	 */
	public function test_bulk_action_delete_succeeds() {
		global $wpdb;
		$id1 = $this->repository->create( 'test', 'BD 1' );
		$id2 = $this->repository->create( 'test', 'BD 2' );
		$id3 = $this->repository->create( 'test', 'Keep' );

		$_POST = array(
			'action'      => 'aips_bulk_notifications_action',
			'nonce'       => $this->valid_nonce(),
			'bulk_action' => 'delete',
			'ids'         => array( $id1, $id2 ),
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_notifications_action' ) );

		$this->assertTrue( $response['success'] );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aips_notifications" );
		$this->assertEquals( 1, $count );
	}

	// -----------------------------------------------------------------
	// ajax_mark_all_notifications_read
	// -----------------------------------------------------------------

	/**
	 * Test that ajax_mark_all_notifications_read() rejects missing nonce.
	 */
	public function test_mark_all_read_requires_nonce() {
		$_POST = array( 'action' => 'aips_mark_all_notifications_read' );

		$exception_thrown = false;
		ob_start();
		try {
			$this->controller->ajax_mark_all_notifications_read();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue( $exception_thrown );
	}

	/**
	 * Test that ajax_mark_all_notifications_read() rejects non-admin.
	 */
	public function test_mark_all_read_requires_manage_options() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array(
			'action' => 'aips_mark_all_notifications_read',
			'nonce'  => $this->valid_nonce(),
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_mark_all_notifications_read' ) );

		if ( $response !== null ) {
			$this->assertFalse( $response['success'] );
		}
	}

	/**
	 * Test that ajax_mark_all_notifications_read() marks all as read and returns success.
	 */
	public function test_mark_all_read_marks_all_notifications() {
		$this->repository->create( 'test', 'A' );
		$this->repository->create( 'test', 'B' );
		$this->repository->create( 'test', 'C' );

		$_POST = array(
			'action' => 'aips_mark_all_notifications_read',
			'nonce'  => $this->valid_nonce(),
		);

		$response = $this->call_ajax( array( $this->controller, 'ajax_mark_all_notifications_read' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 0, $this->repository->count_unread() );
	}

	// -----------------------------------------------------------------
	// AJAX hook registration
	// -----------------------------------------------------------------

	/**
	 * Test that all AJAX actions are registered on construction.
	 */
	public function test_ajax_actions_are_registered() {
		$this->assertTrue( (bool) has_action( 'wp_ajax_aips_get_notifications_list' ) );
		$this->assertTrue( (bool) has_action( 'wp_ajax_aips_mark_notification_read' ) );
		$this->assertTrue( (bool) has_action( 'wp_ajax_aips_mark_notification_unread' ) );
		$this->assertTrue( (bool) has_action( 'wp_ajax_aips_delete_notification' ) );
		$this->assertTrue( (bool) has_action( 'wp_ajax_aips_bulk_notifications_action' ) );
		$this->assertTrue( (bool) has_action( 'wp_ajax_aips_mark_all_notifications_read' ) );
	}
}
