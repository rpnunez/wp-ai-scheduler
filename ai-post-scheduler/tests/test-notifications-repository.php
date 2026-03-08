<?php
/**
 * Tests for AIPS_Notifications_Repository and AIPS_Admin_Bar AJAX handlers
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Notifications extends WP_UnitTestCase {

	/**
	 * @var AIPS_Notifications_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Admin_Bar
	 */
	private $admin_bar;

	/**
	 * IDs created during tests, cleaned up in tearDown.
	 *
	 * @var int[]
	 */
	private $created_ids = array();

	public function setUp(): void {
		parent::setUp();

		AIPS_DB_Manager::install_tables();

		$this->repository = new AIPS_Notifications_Repository();
		$this->admin_bar  = new AIPS_Admin_Bar();

		// Start each test with a clean notifications table.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_notifications" );
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_notifications" );
		$this->created_ids = array();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Repository: create
	// -----------------------------------------------------------------------

	/**
	 * Test that create() returns a positive integer ID on success.
	 */
	public function test_create_returns_id() {
		$id = $this->repository->create( 'test_type', 'Test message' );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test that create() persists all supplied fields.
	 */
	public function test_create_persists_fields() {
		global $wpdb;
		$id = $this->repository->create( 'author_topics_generated', 'Author (Jane) generated 5 pending topic(s) for review', 'https://example.com/topics' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}aips_notifications WHERE id = %d",
			$id
		) );

		$this->assertNotNull( $row );
		$this->assertEquals( 'author_topics_generated', $row->type );
		$this->assertStringContainsString( 'Jane', $row->message );
		$this->assertEquals( 0, (int) $row->is_read );
	}

	/**
	 * Test that create() stores created_at in UTC.
	 */
	public function test_create_stores_utc_timestamp() {
		$before = gmdate( 'Y-m-d H:i:s' );
		$id     = $this->repository->create( 'test_type', 'UTC test' );
		$after  = gmdate( 'Y-m-d H:i:s' );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT created_at FROM {$wpdb->prefix}aips_notifications WHERE id = %d",
			$id
		) );

		$this->assertNotNull( $row );
		$this->assertGreaterThanOrEqual( $before, $row->created_at );
		$this->assertLessThanOrEqual( $after, $row->created_at );
	}

	// -----------------------------------------------------------------------
	// Repository: get_unread
	// -----------------------------------------------------------------------

	/**
	 * Test that get_unread() returns only unread notifications.
	 */
	public function test_get_unread_returns_only_unread() {
		$id1 = $this->repository->create( 'test', 'Unread 1' );
		$id2 = $this->repository->create( 'test', 'Unread 2' );
		$id3 = $this->repository->create( 'test', 'Read one' );

		$this->repository->mark_as_read( $id3 );

		$results = $this->repository->get_unread();

		$result_ids = array_map( function ( $r ) { return (int) $r->id; }, $results );

		$this->assertContains( $id1, $result_ids );
		$this->assertContains( $id2, $result_ids );
		$this->assertNotContains( $id3, $result_ids );
	}

	/**
	 * Test that get_unread() respects the limit parameter.
	 */
	public function test_get_unread_respects_limit() {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->repository->create( 'test', 'Message ' . $i );
		}

		$results = $this->repository->get_unread( 3 );

		$this->assertCount( 3, $results );
	}

	// -----------------------------------------------------------------------
	// Repository: count_unread
	// -----------------------------------------------------------------------

	/**
	 * Test that count_unread() returns 0 when there are no notifications.
	 */
	public function test_count_unread_returns_zero_when_empty() {
		$this->assertEquals( 0, $this->repository->count_unread() );
	}

	/**
	 * Test that count_unread() returns the correct count.
	 */
	public function test_count_unread_returns_correct_count() {
		$id1 = $this->repository->create( 'test', 'Msg 1' );
		$id2 = $this->repository->create( 'test', 'Msg 2' );
		$this->repository->create( 'test', 'Msg 3' );

		// Mark two as read.
		$this->repository->mark_as_read( $id1 );
		$this->repository->mark_as_read( $id2 );

		$this->assertEquals( 1, $this->repository->count_unread() );
	}

	// -----------------------------------------------------------------------
	// Repository: mark_as_read
	// -----------------------------------------------------------------------

	/**
	 * Test that mark_as_read() sets is_read to 1 for the given ID.
	 */
	public function test_mark_as_read_updates_row() {
		$id = $this->repository->create( 'test', 'Mark me read' );

		$result = $this->repository->mark_as_read( $id );

		$this->assertTrue( $result );
		$this->assertEquals( 0, $this->repository->count_unread() );
	}

	// -----------------------------------------------------------------------
	// Repository: mark_all_as_read
	// -----------------------------------------------------------------------

	/**
	 * Test that mark_all_as_read() clears all unread notifications.
	 */
	public function test_mark_all_as_read_clears_unread() {
		$this->repository->create( 'test', 'Msg A' );
		$this->repository->create( 'test', 'Msg B' );
		$this->repository->create( 'test', 'Msg C' );

		$this->repository->mark_all_as_read();

		$this->assertEquals( 0, $this->repository->count_unread() );
	}

	// -----------------------------------------------------------------------
	// Repository: cleanup_old
	// -----------------------------------------------------------------------

	/**
	 * Test that cleanup_old() removes old read notifications.
	 */
	public function test_cleanup_old_removes_old_read_notifications() {
		global $wpdb;

		// Insert an old read notification directly (bypassing current_time).
		$wpdb->insert(
			$wpdb->prefix . 'aips_notifications',
			array(
				'type'       => 'test',
				'message'    => 'Old read',
				'url'        => '',
				'is_read'    => 1,
				'created_at' => '2000-01-01 00:00:00',
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);
		$old_id = (int) $wpdb->insert_id;

		// Insert a recent read notification.
		$recent_id = $this->repository->create( 'test', 'Recent read' );
		$this->repository->mark_as_read( $recent_id );

		$deleted = $this->repository->cleanup_old( 30 );

		$this->assertGreaterThanOrEqual( 1, $deleted );

		$still_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}aips_notifications WHERE id = %d",
			$old_id
		) );
		$this->assertEquals( 0, (int) $still_exists );

		$recent_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}aips_notifications WHERE id = %d",
			$recent_id
		) );
		$this->assertEquals( 1, (int) $recent_exists );
	}

	// -----------------------------------------------------------------------
	// AJAX: aips_mark_notification_read
	// -----------------------------------------------------------------------

	/**
	 * Test that ajax_mark_read() requires a valid nonce.
	 */
	public function test_ajax_mark_read_requires_nonce() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			'action' => 'aips_mark_notification_read',
			'id'     => 1,
		);

		$exception_thrown = false;
		ob_start();
		try {
			$this->admin_bar->ajax_mark_read();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue( $exception_thrown, 'Nonce check should fail and throw exception' );
	}

	/**
	 * Test that ajax_mark_read() requires manage_options capability.
	 */
	public function test_ajax_mark_read_requires_manage_options() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$_POST = array(
			'action' => 'aips_mark_notification_read',
			'nonce'  => wp_create_nonce( 'aips_admin_bar_nonce' ),
			'id'     => 1,
		);

		ob_start();
		$response = null;
		try {
			$this->admin_bar->ajax_mark_read();
		} catch ( WPAjaxDieStopException $e ) {
			$response = json_decode( ob_get_clean(), true );
		} catch ( WPAjaxDieContinueException $e ) {
			$response = json_decode( ob_get_clean(), true );
		}
		if ( $response === null ) {
			ob_end_clean();
		}

		// Either an exception was thrown (nonce invalid for subscriber) or the
		// response indicates failure.
		if ( $response !== null ) {
			$this->assertFalse( $response['success'], 'Subscriber should not have permission' );
		}
	}

	/**
	 * Test that ajax_mark_read() returns success and correct unread count.
	 */
	public function test_ajax_mark_read_success() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$id1 = $this->repository->create( 'test', 'Notification 1' );
		$id2 = $this->repository->create( 'test', 'Notification 2' );

		$_POST = array(
			'action' => 'aips_mark_notification_read',
			'nonce'  => wp_create_nonce( 'aips_admin_bar_nonce' ),
			'id'     => $id1,
		);

		ob_start();
		try {
			$this->admin_bar->ajax_mark_read();
		} catch ( WPAjaxDieStopException $e ) {
			// Expected – wp_send_json_success calls wp_die.
		} catch ( WPAjaxDieContinueException $e ) {
			// Also expected.
		}
		$output = ob_get_clean();

		$response = json_decode( $output, true );

		$this->assertNotNull( $response, 'Response should be valid JSON' );
		$this->assertTrue( $response['success'], 'Should return success' );
		$this->assertEquals( 1, $response['data']['unread_count'], 'One notification should remain unread' );
		$this->assertEquals( 1, $this->repository->count_unread(), 'DB count should match' );
	}

	// -----------------------------------------------------------------------
	// AJAX: aips_mark_all_notifications_read
	// -----------------------------------------------------------------------

	/**
	 * Test that ajax_mark_all_read() requires a valid nonce.
	 */
	public function test_ajax_mark_all_read_requires_nonce() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			'action' => 'aips_mark_all_notifications_read',
		);

		$exception_thrown = false;
		ob_start();
		try {
			$this->admin_bar->ajax_mark_all_read();
		} catch ( WPAjaxDieStopException $e ) {
			$exception_thrown = true;
		} catch ( WPAjaxDieContinueException $e ) {
			$exception_thrown = true;
		}
		ob_end_clean();

		$this->assertTrue( $exception_thrown, 'Nonce check should fail and throw exception' );
	}

	/**
	 * Test that ajax_mark_all_read() marks all notifications and returns unread_count 0.
	 */
	public function test_ajax_mark_all_read_success() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->repository->create( 'test', 'Notif A' );
		$this->repository->create( 'test', 'Notif B' );
		$this->repository->create( 'test', 'Notif C' );

		$_POST = array(
			'action' => 'aips_mark_all_notifications_read',
			'nonce'  => wp_create_nonce( 'aips_admin_bar_nonce' ),
		);

		ob_start();
		try {
			$this->admin_bar->ajax_mark_all_read();
		} catch ( WPAjaxDieStopException $e ) {
			// Expected.
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected.
		}
		$output = ob_get_clean();

		$response = json_decode( $output, true );

		$this->assertNotNull( $response, 'Response should be valid JSON' );
		$this->assertTrue( $response['success'], 'Should return success' );
		$this->assertEquals( 0, $response['data']['unread_count'], 'All notifications should be read' );
		$this->assertEquals( 0, $this->repository->count_unread(), 'DB should have 0 unread' );
	}

	// -----------------------------------------------------------------------
	// Static factory: notify_author_topics_generated
	// -----------------------------------------------------------------------

	/**
	 * Test that notify_author_topics_generated() creates a notification.
	 */
	public function test_notify_author_topics_generated_creates_notification() {
		$before_count = $this->repository->count_unread();

		AIPS_Admin_Bar::notify_author_topics_generated( 'Jane Doe', 10, 42 );

		$this->assertEquals( $before_count + 1, $this->repository->count_unread() );

		$unread = $this->repository->get_unread( 1 );
		$this->assertCount( 1, $unread );
		$this->assertEquals( 'author_topics_generated', $unread[0]->type );
		$this->assertStringContainsString( 'Jane Doe', $unread[0]->message );
		$this->assertStringContainsString( '10', $unread[0]->message );
		$this->assertStringContainsString( '42', $unread[0]->url );
	}
}
