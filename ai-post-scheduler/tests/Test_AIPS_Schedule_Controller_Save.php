<?php
/**
 * Tests for AIPS_Schedule_Controller schedule save AJAX endpoint.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Controller_Save extends WP_UnitTestCase {

	/** @var AIPS_Schedule_Controller */
	private $controller;

	/** @var AIPS_Scheduler|\PHPUnit\Framework\MockObject\MockObject */
	private $scheduler;

	/** @var int */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$this->scheduler = $this->getMockBuilder( 'AIPS_Scheduler' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'save_schedule' ) )
			->getMock();

		$this->controller = new AIPS_Schedule_Controller( $this->scheduler );
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Call a controller method and capture its JSON output.
	 *
	 * @param callable $callable Controller callback.
	 * @return array Decoded JSON response.
	 */
	private function call_ajax( callable $callable ) {
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected when wp_send_json_* is called.
		}

		$output = ob_get_clean();

		return json_decode( $output, true );
	}

	public function test_ajax_save_schedule_passes_inactive_value_when_zero_is_posted() {
		wp_set_current_user( $this->admin_user_id );

		$_POST = array(
			'nonce'       => wp_create_nonce( 'aips_ajax_nonce' ),
			'template_id' => 42,
			'frequency'   => 'daily',
			'is_active'   => '0',
			'topic'       => 'Inactive topic',
		);
		$_REQUEST = $_POST;

		$this->scheduler->expects( $this->once() )
			->method( 'save_schedule' )
			->with(
				$this->callback(
					function ( $data ) {
						return isset( $data['is_active'] )
							&& 0 === $data['is_active']
							&& 42 === $data['template_id']
							&& 'daily' === $data['frequency'];
					}
				)
			)
			->willReturn( 99 );

		$response = $this->call_ajax( array( $this->controller, 'ajax_save_schedule' ) );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 99, $response['data']['schedule_id'] );
	}
}