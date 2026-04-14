<?php
/**
 * Tests for run-now endpoints in AIPS_Schedule_Controller.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Controller_Run_Now extends WP_UnitTestCase {

	/** @var AIPS_Schedule_Controller */
	private $controller;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->controller = new AIPS_Schedule_Controller();
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

	public function test_ajax_run_now_requires_template_id() {
		wp_set_current_user( $this->admin_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['template_id'] = 0;

		$response = $this->call_ajax( array( $this->controller, 'ajax_run_now' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid template ID.', $response['data']['message'] );
	}

	public function test_ajax_schedule_run_now_requires_valid_parameters() {
		wp_set_current_user( $this->admin_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['id']    = 0;
		$_POST['type']  = '';

		$response = $this->call_ajax( array( $this->controller, 'ajax_schedule_run_now' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid parameters.', $response['data']['message'] );
	}

	public function test_ajax_schedule_run_now_permission_denied_for_non_admin() {
		wp_set_current_user( $this->subscriber_user_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['id']    = 1;
		$_POST['type']  = AIPS_Schedule_Service::TYPE_TEMPLATE;

		$response = $this->call_ajax( array( $this->controller, 'ajax_schedule_run_now' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Permission denied.', $response['data']['message'] );
	}
}
