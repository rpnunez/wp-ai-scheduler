<?php
/**
 * Tests for schedule history repository and unified schedule history endpoint.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_History extends WP_UnitTestCase {

	/** @var AIPS_History_Repository */
	private $history_repo;

	/** @var AIPS_Schedule_Controller */
	private $controller;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->history_repo = new AIPS_History_Repository();
		$this->controller   = new AIPS_Schedule_Controller();
		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/**
	 * @param callable $callable Endpoint callback.
	 * @return array Decoded JSON response.
	 */
	private function call_ajax( callable $callable ) {
		$_REQUEST = array_merge( $_REQUEST, $_POST );
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected for wp_send_json.
		}

		return json_decode( ob_get_clean(), true );
	}

	public function test_get_logs_by_history_id_returns_array() {
		$result = $this->history_repo->get_logs_by_history_id( 999 );
		$this->assertIsArray( $result );
	}

	public function test_get_logs_by_history_id_with_filter_returns_array() {
		$result = $this->history_repo->get_logs_by_history_id(
			999,
			array( AIPS_History_Type::ACTIVITY, AIPS_History_Type::ERROR )
		);
		$this->assertIsArray( $result );
	}

	public function test_get_logs_by_history_id_returns_empty_for_unknown_id() {
		$logs = $this->history_repo->get_logs_by_history_id( 999999 );
		$this->assertIsArray( $logs );
		$this->assertEmpty( $logs );
	}

	public function test_ajax_get_unified_schedule_history_requires_valid_parameters() {
		wp_set_current_user( $this->admin_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['id']    = 0;
		$_POST['type']  = '';

		$response = $this->call_ajax( array( $this->controller, 'ajax_get_unified_schedule_history' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid parameters.', $response['data']['message'] );
	}

	public function test_ajax_get_unified_schedule_history_permission_denied() {
		wp_set_current_user( $this->subscriber_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['id']    = 1;
		$_POST['type']  = AIPS_Schedule_Service::TYPE_TEMPLATE;

		$response = $this->call_ajax( array( $this->controller, 'ajax_get_unified_schedule_history' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Permission denied.', $response['data']['message'] );
	}

	public function test_ajax_get_unified_schedule_history_unknown_schedule_returns_empty_entries() {
		wp_set_current_user( $this->admin_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['id']    = 999999;
		$_POST['type']  = AIPS_Schedule_Service::TYPE_TEMPLATE;

		$response = $this->call_ajax( array( $this->controller, 'ajax_get_unified_schedule_history' ) );

		$this->assertTrue( $response['success'] );
		$this->assertIsArray( $response['data']['entries'] );
		$this->assertCount( 0, $response['data']['entries'] );
	}
}
