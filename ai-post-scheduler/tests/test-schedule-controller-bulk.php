<?php
/**
 * Tests for AIPS_Schedule_Controller bulk AJAX endpoints
 *
 * Covers:
 *   - ajax_bulk_delete_schedules
 *   - ajax_bulk_toggle_schedules
 *   - ajax_bulk_run_now_schedules
 *   - ajax_get_schedules_post_count
 *
 * Each endpoint is tested for: success path, permission denied, and empty/invalid IDs.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Controller_Bulk extends WP_UnitTestCase {

	/** @var AIPS_Schedule_Controller */
	private $controller;

	/** @var AIPS_Scheduler|\PHPUnit\Framework\MockObject\MockObject */
	private $scheduler;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $subscriber_user_id;

	/** @var int */
	private $template_id;

	public function setUp(): void {
		parent::setUp();

		// Create test users
		$this->admin_user_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		// Mock the Scheduler to isolate controller from AI generation
		$this->scheduler = $this->getMockBuilder( 'AIPS_Scheduler' )
			->onlyMethods( array( 'run_schedule_now', 'save_schedule', 'toggle_active' ) )
			->getMock();

		$this->controller = new AIPS_Schedule_Controller( $this->scheduler );

		// Insert a template so schedules can reference it
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'aips_templates',
			array(
				'name'          => 'Bulk Test Template',
				'prompt_template' => 'Write about {{topic}}',
				'is_active'     => 1,
				'post_quantity' => 3,
			)
		);
		$this->template_id = (int) $wpdb->insert_id;
	}

	public function tearDown(): void {
		global $wpdb;

		// Remove all test schedules and the test template
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_schedule WHERE template_id = %d", $this->template_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aips_templates WHERE id = %d", $this->template_id ) );

		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Insert $count test schedules and return their IDs.
	 *
	 * @param int $count
	 * @return int[]
	 */
	private function create_test_schedules( $count = 2 ) {
		global $wpdb;
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$wpdb->insert(
				$wpdb->prefix . 'aips_schedule',
				array(
					'template_id' => $this->template_id,
					'frequency'   => 'once',
					'next_run'    => '2024-01-01 10:00:00',
					'is_active'   => 1,
					'topic'       => 'Bulk Topic ' . ( $i + 1 ),
				)
			);
			$ids[] = (int) $wpdb->insert_id;
		}
		return $ids;
	}

	/**
	 * Call a controller method and capture its JSON output.
	 *
	 * @param callable $callable
	 * @return array Decoded JSON response.
	 */
	private function call_ajax( callable $callable ) {
		// WordPress nonce validation reads from $_REQUEST, not $_POST; keep them in sync.
		$_REQUEST = array_merge( $_REQUEST, $_POST );
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected when wp_send_json_* is called.
		}
		$output = ob_get_clean();
		return json_decode( $output, true );
	}

	// -----------------------------------------------------------------------
	// ajax_bulk_delete_schedules
	// -----------------------------------------------------------------------

	public function test_bulk_delete_success() {
		wp_set_current_user( $this->admin_user_id );
		$ids = $this->create_test_schedules( 3 );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = $ids;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_delete_schedules' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 3, $response['data']['deleted'] );
		$this->assertStringContainsString( '3', $response['data']['message'] );

		// Verify rows are gone from the database
		global $wpdb;
		foreach ( $ids as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}aips_schedule WHERE id = %d", $id ) );
			$this->assertNull( $row, "Schedule $id should have been deleted." );
		}
	}

	public function test_bulk_delete_empty_ids_returns_error() {
		wp_set_current_user( $this->admin_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = array();

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_delete_schedules' ) );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'No schedule IDs provided.', $response['data']['message'] );
	}

	public function test_bulk_delete_permission_denied() {
		wp_set_current_user( $this->subscriber_user_id );
		$ids = $this->create_test_schedules( 2 );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = $ids;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_delete_schedules' ) );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Permission denied.', $response['data']['message'] );

		// Verify rows are still present
		global $wpdb;
		foreach ( $ids as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}aips_schedule WHERE id = %d", $id ) );
			$this->assertNotNull( $row, "Schedule $id should NOT have been deleted." );
		}
	}

	// -----------------------------------------------------------------------
	// ajax_bulk_toggle_schedules
	// -----------------------------------------------------------------------

	public function test_bulk_toggle_activate_success() {
		wp_set_current_user( $this->admin_user_id );

		// Insert schedules that are initially inactive
		global $wpdb;
		$ids = array();
		for ( $i = 0; $i < 2; $i++ ) {
			$wpdb->insert(
				$wpdb->prefix . 'aips_schedule',
				array(
					'template_id' => $this->template_id,
					'frequency'   => 'once',
					'next_run'    => '2024-01-01 10:00:00',
					'is_active'   => 0,
					'topic'       => 'Inactive Topic ' . ( $i + 1 ),
				)
			);
			$ids[] = (int) $wpdb->insert_id;
		}

		$_POST['nonce']     = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']       = $ids;
		$_POST['is_active'] = 1;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_toggle_schedules' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 1, $response['data']['is_active'] );
		$this->assertStringContainsString( 'activated', $response['data']['message'] );

		// Verify rows are now active
		foreach ( $ids as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT is_active FROM {$wpdb->prefix}aips_schedule WHERE id = %d", $id ) );
			$this->assertNotNull( $row );
			$this->assertEquals( 1, (int) $row->is_active );
		}
	}

	public function test_bulk_toggle_pause_success() {
		wp_set_current_user( $this->admin_user_id );
		$ids = $this->create_test_schedules( 2 ); // created as is_active = 1

		$_POST['nonce']     = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']       = $ids;
		$_POST['is_active'] = 0;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_toggle_schedules' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 0, $response['data']['is_active'] );
		$this->assertStringContainsString( 'paused', $response['data']['message'] );

		// Verify rows are now inactive
		global $wpdb;
		foreach ( $ids as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT is_active FROM {$wpdb->prefix}aips_schedule WHERE id = %d", $id ) );
			$this->assertNotNull( $row );
			$this->assertEquals( 0, (int) $row->is_active );
		}
	}

	public function test_bulk_toggle_empty_ids_returns_error() {
		wp_set_current_user( $this->admin_user_id );

		$_POST['nonce']     = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']       = array();
		$_POST['is_active'] = 1;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_toggle_schedules' ) );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'No schedule IDs provided.', $response['data']['message'] );
	}

	public function test_bulk_toggle_permission_denied() {
		wp_set_current_user( $this->subscriber_user_id );
		$ids = $this->create_test_schedules( 2 );

		$_POST['nonce']     = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']       = $ids;
		$_POST['is_active'] = 0;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_toggle_schedules' ) );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Permission denied.', $response['data']['message'] );
	}

	// -----------------------------------------------------------------------
	// ajax_bulk_run_now_schedules
	// -----------------------------------------------------------------------

	public function test_bulk_run_now_success() {
		wp_set_current_user( $this->admin_user_id );
		$ids = $this->create_test_schedules( 2 );

		// Scheduler returns a post ID for each schedule
		$this->scheduler->expects( $this->exactly( 2 ) )
			->method( 'run_schedule_now' )
			->willReturnOnConsecutiveCalls( 101, 102 );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = $ids;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_run_now_schedules' ) );

		$this->assertTrue( $response['success'] );
		$this->assertCount( 2, $response['data']['post_ids'] );
		$this->assertContains( 101, $response['data']['post_ids'] );
		$this->assertContains( 102, $response['data']['post_ids'] );
		$this->assertEmpty( $response['data']['errors'] );
	}

	public function test_bulk_run_now_partial_failure() {
		wp_set_current_user( $this->admin_user_id );
		$ids = $this->create_test_schedules( 2 );

		$this->scheduler->expects( $this->exactly( 2 ) )
			->method( 'run_schedule_now' )
			->willReturnOnConsecutiveCalls(
				201,
				new WP_Error( 'gen_fail', 'AI generation failed' )
			);

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = $ids;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_run_now_schedules' ) );

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, $response['data']['post_ids'] );
		$this->assertCount( 1, $response['data']['errors'] );
		$this->assertStringContainsString( 'failed', $response['data']['message'] );
	}

	public function test_bulk_run_now_all_failures_returns_error() {
		wp_set_current_user( $this->admin_user_id );
		$ids = $this->create_test_schedules( 2 );

		$this->scheduler->expects( $this->exactly( 2 ) )
			->method( 'run_schedule_now' )
			->willReturn( new WP_Error( 'gen_fail', 'AI generation failed' ) );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = $ids;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_run_now_schedules' ) );

		$this->assertFalse( $response['success'] );
		$this->assertNotEmpty( $response['data']['errors'] );
	}

	public function test_bulk_run_now_empty_ids_returns_error() {
		wp_set_current_user( $this->admin_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = array();

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_run_now_schedules' ) );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'No schedule IDs provided.', $response['data']['message'] );
	}

	public function test_bulk_run_now_exceeds_limit_returns_error() {
		wp_set_current_user( $this->admin_user_id );

		// Default limit is 5; create 6 schedules
		$ids = $this->create_test_schedules( 6 );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = $ids;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_run_now_schedules' ) );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Too many schedules selected', $response['data']['message'] );
	}

	public function test_bulk_run_now_permission_denied() {
		wp_set_current_user( $this->subscriber_user_id );
		$ids = $this->create_test_schedules( 2 );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = $ids;

		$response = $this->call_ajax( array( $this->controller, 'ajax_bulk_run_now_schedules' ) );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Permission denied.', $response['data']['message'] );
	}

	// -----------------------------------------------------------------------
	// ajax_get_schedules_post_count
	// -----------------------------------------------------------------------

	public function test_get_schedules_post_count_success() {
		wp_set_current_user( $this->admin_user_id );
		$ids = $this->create_test_schedules( 2 ); // template has post_quantity = 3

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = $ids;

		$response = $this->call_ajax( array( $this->controller, 'ajax_get_schedules_post_count' ) );

		$this->assertTrue( $response['success'] );
		// 2 schedules × 3 post_quantity = 6
		$this->assertEquals( 6, $response['data']['count'] );
	}

	public function test_get_schedules_post_count_empty_ids_returns_zero() {
		wp_set_current_user( $this->admin_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = array();

		$response = $this->call_ajax( array( $this->controller, 'ajax_get_schedules_post_count' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 0, $response['data']['count'] );
	}

	public function test_get_schedules_post_count_permission_denied() {
		wp_set_current_user( $this->subscriber_user_id );
		$ids = $this->create_test_schedules( 1 );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = $ids;

		$response = $this->call_ajax( array( $this->controller, 'ajax_get_schedules_post_count' ) );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Permission denied.', $response['data']['message'] );
	}

	// -----------------------------------------------------------------------
	// Delegation tests: controller -> repository boundary
	// -----------------------------------------------------------------------

	/**
	 * ajax_bulk_delete_schedules must delegate to $schedule_repository->delete_bulk(),
	 * not perform direct DB operations.
	 */
	public function test_bulk_delete_delegates_to_repository() {
		wp_set_current_user( $this->admin_user_id );

		$mock_repo = $this->getMockBuilder( 'AIPS_Schedule_Repository' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'delete_bulk' ) )
			->getMock();

		$mock_repo->expects( $this->once() )
			->method( 'delete_bulk' )
			->with( array( 10, 20 ) )
			->willReturn( 2 );

		$controller = new AIPS_Schedule_Controller( $this->scheduler, $mock_repo );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = array( 10, 20 );

		$response = $this->call_ajax( array( $controller, 'ajax_bulk_delete_schedules' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 2, $response['data']['deleted'] );
	}

	/**
	 * ajax_bulk_toggle_schedules must delegate to $schedule_repository->set_active_bulk(),
	 * not perform direct DB operations.
	 */
	public function test_bulk_toggle_delegates_to_repository() {
		wp_set_current_user( $this->admin_user_id );

		$mock_repo = $this->getMockBuilder( 'AIPS_Schedule_Repository' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'set_active_bulk' ) )
			->getMock();

		$mock_repo->expects( $this->once() )
			->method( 'set_active_bulk' )
			->with( array( 5, 6 ), 1 )
			->willReturn( 2 );

		$controller = new AIPS_Schedule_Controller( $this->scheduler, $mock_repo );

		$_POST['nonce']     = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']       = array( 5, 6 );
		$_POST['is_active'] = 1;

		$response = $this->call_ajax( array( $controller, 'ajax_bulk_toggle_schedules' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 1, $response['data']['is_active'] );
	}

	/**
	 * ajax_get_schedules_post_count must delegate to
	 * $schedule_repository->get_post_count_for_schedules(), not perform direct DB operations.
	 */
	public function test_get_schedules_post_count_delegates_to_repository() {
		wp_set_current_user( $this->admin_user_id );

		$mock_repo = $this->getMockBuilder( 'AIPS_Schedule_Repository' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_post_count_for_schedules' ) )
			->getMock();

		$mock_repo->expects( $this->once() )
			->method( 'get_post_count_for_schedules' )
			->with( array( 1, 2, 3 ) )
			->willReturn( 9 );

		$controller = new AIPS_Schedule_Controller( $this->scheduler, $mock_repo );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['ids']   = array( 1, 2, 3 );

		$response = $this->call_ajax( array( $controller, 'ajax_get_schedules_post_count' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 9, $response['data']['count'] );
	}

	/**
	 * ajax_get_schedule_history must delegate to $schedule_repository->get_by_id() and
	 * $history_repository->get_logs_by_history_id(), not instantiate repositories inline.
	 */
	public function test_get_schedule_history_delegates_to_repositories() {
		wp_set_current_user( $this->admin_user_id );

		$fake_schedule                      = new stdClass();
		$fake_schedule->id                  = 42;
		$fake_schedule->schedule_history_id = 0; // No history yet — returns empty entries.

		$mock_schedule_repo = $this->getMockBuilder( 'AIPS_Schedule_Repository' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_by_id' ) )
			->getMock();

		$mock_schedule_repo->expects( $this->once() )
			->method( 'get_by_id' )
			->with( 42 )
			->willReturn( $fake_schedule );

		$mock_history_repo = $this->getMockBuilder( 'AIPS_History_Repository' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_logs_by_history_id' ) )
			->getMock();

		// get_logs_by_history_id should NOT be called when schedule_history_id is empty.
		$mock_history_repo->expects( $this->never() )
			->method( 'get_logs_by_history_id' );

		$controller = new AIPS_Schedule_Controller( $this->scheduler, $mock_schedule_repo, $mock_history_repo );

		$_POST['nonce']       = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['schedule_id'] = 42;

		$response = $this->call_ajax( array( $controller, 'ajax_get_schedule_history' ) );

		$this->assertTrue( $response['success'] );
		$this->assertIsArray( $response['data']['entries'] );
		$this->assertEmpty( $response['data']['entries'] );
	}
}
