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
	 * Test that create_notification() persists rich notification fields.
	 */
	public function test_create_notification_persists_rich_fields() {
		global $wpdb;

		$id = $this->repository->create_notification( array(
			'type'       => 'generation_failed',
			'title'      => 'Generation failed',
			'message'    => 'Template Alpha failed',
			'url'        => 'https://example.com/history',
			'level'      => 'error',
			'meta'       => array( 'history_id' => 99 ),
			'dedupe_key' => 'generation_failed_alpha',
		) );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}aips_notifications WHERE id = %d",
			$id
		) );

		$this->assertSame( 'Generation failed', $row->title );
		$this->assertSame( 'error', $row->level );
		$this->assertSame( 'generation_failed_alpha', $row->dedupe_key );
		$this->assertStringContainsString( 'history_id', $row->meta );
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

		global $wpdb;
		$read_at = $wpdb->get_var( $wpdb->prepare(
			"SELECT read_at FROM {$wpdb->prefix}aips_notifications WHERE id = %d",
			$id
		) );

		$this->assertNotEmpty( $read_at );
	}

	/**
	 * Test dedupe helper against a recent notification.
	 */
	public function test_was_recently_sent_returns_true_for_recent_dedupe_key() {
		$this->repository->create_notification( array(
			'type'       => 'quota_alert',
			'message'    => 'Rate limit hit',
			'dedupe_key' => 'quota_alert_text_rate_limit_exceeded',
		) );

		$this->assertTrue( $this->repository->was_recently_sent( 'quota_alert_text_rate_limit_exceeded', 3600 ) );
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
	// AIPS_Notifications: author_topics_generated convenience method
	// -----------------------------------------------------------------------

	/**
	 * Test that AIPS_Notifications::author_topics_generated() creates a DB notification.
	 */
	public function test_notify_author_topics_generated_creates_notification() {
		$before_count = $this->repository->count_unread();

		( new AIPS_Notifications( $this->repository ) )->author_topics_generated( 'Jane Doe', 10, 42 );

		$this->assertEquals( $before_count + 1, $this->repository->count_unread() );

		$unread = $this->repository->get_unread( 1 );
		$this->assertCount( 1, $unread );
		$this->assertEquals( 'author_topics_generated', $unread[0]->type );
		$this->assertStringContainsString( 'Jane Doe', $unread[0]->message );
		$this->assertStringContainsString( '10', $unread[0]->message );
		$this->assertStringContainsString( '42', $unread[0]->url );
	}

	// -----------------------------------------------------------------------
	// Repository: get_paginated
	// -----------------------------------------------------------------------

	/**
	 * Test that get_paginated() returns all notifications with default args.
	 */
	public function test_get_paginated_returns_all_by_default() {
		$this->repository->create( 'test', 'Msg 1' );
		$this->repository->create( 'test', 'Msg 2' );
		$this->repository->create( 'test', 'Msg 3' );

		$result = $this->repository->get_paginated();

		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'pages', $result );
		$this->assertCount( 3, $result['items'] );
		$this->assertEquals( 3, $result['total'] );
		$this->assertEquals( 1, $result['pages'] );
	}

	/**
	 * Test that get_paginated() filters by level.
	 */
	public function test_get_paginated_filters_by_level() {
		$this->repository->create_notification( array(
			'type'    => 'test',
			'message' => 'Error msg',
			'level'   => 'error',
		) );
		$this->repository->create_notification( array(
			'type'    => 'test',
			'message' => 'Info msg',
			'level'   => 'info',
		) );

		$result = $this->repository->get_paginated( array( 'level' => 'error' ) );

		$this->assertEquals( 1, $result['total'] );
		$this->assertEquals( 'error', $result['items'][0]->level );
	}

	/**
	 * Test that get_paginated() filters by type.
	 */
	public function test_get_paginated_filters_by_type() {
		$this->repository->create( 'type_a', 'Msg A1' );
		$this->repository->create( 'type_a', 'Msg A2' );
		$this->repository->create( 'type_b', 'Msg B1' );

		$result = $this->repository->get_paginated( array( 'type' => 'type_a' ) );

		$this->assertEquals( 2, $result['total'] );
		foreach ( $result['items'] as $item ) {
			$this->assertEquals( 'type_a', $item->type );
		}
	}

	/**
	 * Test that get_paginated() filters by read status (unread only).
	 */
	public function test_get_paginated_filters_by_is_read_unread() {
		$id1 = $this->repository->create( 'test', 'Unread' );
		$id2 = $this->repository->create( 'test', 'Read' );
		$this->repository->mark_as_read( $id2 );

		$result = $this->repository->get_paginated( array( 'is_read' => 0 ) );

		$this->assertEquals( 1, $result['total'] );
		$this->assertEquals( $id1, (int) $result['items'][0]->id );
	}

	/**
	 * Test that get_paginated() filters by read status (read only).
	 */
	public function test_get_paginated_filters_by_is_read_read() {
		$id1 = $this->repository->create( 'test', 'Unread' );
		$id2 = $this->repository->create( 'test', 'Read' );
		$this->repository->mark_as_read( $id2 );

		$result = $this->repository->get_paginated( array( 'is_read' => 1 ) );

		$this->assertEquals( 1, $result['total'] );
		$this->assertEquals( $id2, (int) $result['items'][0]->id );
	}

	/**
	 * Test that get_paginated() searches across title, message, and type.
	 */
	public function test_get_paginated_searches_message() {
		$this->repository->create_notification( array(
			'type'    => 'test',
			'message' => 'Contains the keyword needle',
		) );
		$this->repository->create_notification( array(
			'type'    => 'test',
			'message' => 'No match here',
		) );

		$result = $this->repository->get_paginated( array( 'search' => 'needle' ) );

		$this->assertEquals( 1, $result['total'] );
		$this->assertStringContainsString( 'needle', $result['items'][0]->message );
	}

	/**
	 * Test that get_paginated() paginates correctly.
	 */
	public function test_get_paginated_pagination() {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->repository->create( 'test', 'Msg ' . $i );
		}

		$page1 = $this->repository->get_paginated( array( 'page' => 1, 'per_page' => 2 ) );
		$page2 = $this->repository->get_paginated( array( 'page' => 2, 'per_page' => 2 ) );
		$page3 = $this->repository->get_paginated( array( 'page' => 3, 'per_page' => 2 ) );

		$this->assertEquals( 5, $page1['total'] );
		$this->assertEquals( 3, $page1['pages'] );
		$this->assertCount( 2, $page1['items'] );
		$this->assertCount( 2, $page2['items'] );
		$this->assertCount( 1, $page3['items'] );
	}

	/**
	 * Test that get_paginated() returns empty result when no notifications match.
	 */
	public function test_get_paginated_returns_empty_when_no_match() {
		$result = $this->repository->get_paginated( array( 'level' => 'error' ) );

		$this->assertEquals( 0, $result['total'] );
		$this->assertEmpty( $result['items'] );
	}

	// -----------------------------------------------------------------------
	// Repository: get_summary_counts
	// -----------------------------------------------------------------------

	/**
	 * Test that get_summary_counts() returns correct totals.
	 */
	public function test_get_summary_counts_returns_correct_totals() {
		$id1 = $this->repository->create_notification( array(
			'type'    => 'test',
			'message' => 'Error 1',
			'level'   => 'error',
		) );
		$this->repository->create_notification( array(
			'type'    => 'test',
			'message' => 'Warning 1',
			'level'   => 'warning',
		) );
		$this->repository->create_notification( array(
			'type'    => 'test',
			'message' => 'Info 1',
			'level'   => 'info',
		) );
		$this->repository->mark_as_read( $id1 );

		$counts = $this->repository->get_summary_counts();

		$this->assertArrayHasKey( 'total', $counts );
		$this->assertArrayHasKey( 'unread', $counts );
		$this->assertArrayHasKey( 'errors', $counts );
		$this->assertArrayHasKey( 'warnings', $counts );
		$this->assertEquals( 3, $counts['total'] );
		$this->assertEquals( 2, $counts['unread'] );
		$this->assertEquals( 1, $counts['errors'] );
		$this->assertEquals( 1, $counts['warnings'] );
	}

	/**
	 * Test that get_summary_counts() returns all zeros when table is empty.
	 */
	public function test_get_summary_counts_returns_zeros_when_empty() {
		$counts = $this->repository->get_summary_counts();

		$this->assertEquals( 0, $counts['total'] );
		$this->assertEquals( 0, $counts['unread'] );
		$this->assertEquals( 0, $counts['errors'] );
		$this->assertEquals( 0, $counts['warnings'] );
	}

	// -----------------------------------------------------------------------
	// Repository: mark_as_unread
	// -----------------------------------------------------------------------

	/**
	 * Test that mark_as_unread() sets is_read to 0 and clears read_at.
	 */
	public function test_mark_as_unread_clears_read_flag() {
		global $wpdb;
		$id = $this->repository->create( 'test', 'Mark me unread' );
		$this->repository->mark_as_read( $id );

		$result = $this->repository->mark_as_unread( $id );

		$this->assertTrue( $result );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT is_read, read_at FROM {$wpdb->prefix}aips_notifications WHERE id = %d",
			$id
		) );
		$this->assertEquals( 0, (int) $row->is_read );
		$this->assertNull( $row->read_at );
	}

	/**
	 * Test that mark_as_unread() returns true for an already-unread notification.
	 */
	public function test_mark_as_unread_on_unread_notification_returns_true() {
		$id = $this->repository->create( 'test', 'Already unread' );

		$result = $this->repository->mark_as_unread( $id );

		$this->assertTrue( $result );
		$this->assertEquals( 1, $this->repository->count_unread() );
	}

	// -----------------------------------------------------------------------
	// Repository: delete_notification
	// -----------------------------------------------------------------------

	/**
	 * Test that delete_notification() removes the row from the database.
	 */
	public function test_delete_notification_removes_row() {
		global $wpdb;
		$id = $this->repository->create( 'test', 'Delete me' );

		$result = $this->repository->delete_notification( $id );

		$this->assertTrue( $result );
		$exists = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}aips_notifications WHERE id = %d",
			$id
		) );
		$this->assertEquals( 0, $exists );
	}

	/**
	 * Test that delete_notification() does not affect other rows.
	 */
	public function test_delete_notification_leaves_others_intact() {
		$id1 = $this->repository->create( 'test', 'Keep me' );
		$id2 = $this->repository->create( 'test', 'Delete me' );

		$this->repository->delete_notification( $id2 );

		$result = $this->repository->get_paginated();
		$ids    = array_map( function ( $r ) { return (int) $r->id; }, $result['items'] );
		$this->assertContains( $id1, $ids );
		$this->assertNotContains( $id2, $ids );
	}

	// -----------------------------------------------------------------------
	// Repository: bulk_mark_as_read
	// -----------------------------------------------------------------------

	/**
	 * Test that bulk_mark_as_read() marks multiple notifications as read.
	 */
	public function test_bulk_mark_as_read_marks_multiple() {
		$id1 = $this->repository->create( 'test', 'Bulk 1' );
		$id2 = $this->repository->create( 'test', 'Bulk 2' );
		$id3 = $this->repository->create( 'test', 'Bulk 3' );

		$result = $this->repository->bulk_mark_as_read( array( $id1, $id2 ) );

		$this->assertTrue( $result );
		$this->assertEquals( 1, $this->repository->count_unread() );
	}

	/**
	 * Test that bulk_mark_as_read() returns false for empty array.
	 */
	public function test_bulk_mark_as_read_returns_false_for_empty_array() {
		$this->assertFalse( $this->repository->bulk_mark_as_read( array() ) );
	}

	// -----------------------------------------------------------------------
	// Repository: bulk_mark_as_unread
	// -----------------------------------------------------------------------

	/**
	 * Test that bulk_mark_as_unread() marks multiple notifications as unread.
	 */
	public function test_bulk_mark_as_unread_marks_multiple() {
		global $wpdb;
		$id1 = $this->repository->create( 'test', 'BU 1' );
		$id2 = $this->repository->create( 'test', 'BU 2' );
		$this->repository->mark_as_read( $id1 );
		$this->repository->mark_as_read( $id2 );

		$result = $this->repository->bulk_mark_as_unread( array( $id1, $id2 ) );

		$this->assertTrue( $result );
		$this->assertEquals( 2, $this->repository->count_unread() );

		foreach ( array( $id1, $id2 ) as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT is_read, read_at FROM {$wpdb->prefix}aips_notifications WHERE id = %d",
				$id
			) );
			$this->assertEquals( 0, (int) $row->is_read );
			$this->assertNull( $row->read_at );
		}
	}

	/**
	 * Test that bulk_mark_as_unread() returns false for empty array.
	 */
	public function test_bulk_mark_as_unread_returns_false_for_empty_array() {
		$this->assertFalse( $this->repository->bulk_mark_as_unread( array() ) );
	}

	// -----------------------------------------------------------------------
	// Repository: bulk_delete
	// -----------------------------------------------------------------------

	/**
	 * Test that bulk_delete() removes multiple rows.
	 */
	public function test_bulk_delete_removes_multiple_rows() {
		global $wpdb;
		$id1 = $this->repository->create( 'test', 'BD 1' );
		$id2 = $this->repository->create( 'test', 'BD 2' );
		$id3 = $this->repository->create( 'test', 'Keep this' );

		$result = $this->repository->bulk_delete( array( $id1, $id2 ) );

		$this->assertTrue( $result );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aips_notifications" );
		$this->assertEquals( 1, $count );
		$remaining = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}aips_notifications" );
		$this->assertEquals( $id3, (int) $remaining );
	}

	/**
	 * Test that bulk_delete() returns false for empty array.
	 */
	public function test_bulk_delete_returns_false_for_empty_array() {
		$this->assertFalse( $this->repository->bulk_delete( array() ) );
	}
}
