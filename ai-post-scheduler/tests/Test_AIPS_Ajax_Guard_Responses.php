<?php
/**
 * HTTP 200 nonce and capability responses for endpoints guarded by AIPS_Ajax_Guard.
 *
 * Admin JavaScript only handles the jQuery success callback, so these failures
 * must stay on HTTP 200 and use the error codes from the pre-decomposition controllers.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_AIPS_Ajax_Guard_Responses extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * @var int
	 */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
		$this->subscriber_user_id = $this->factory->user->create( array(
			'role' => 'subscriber',
		) );

		$_POST    = array();
		$_REQUEST = array();
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	public function test_content_indexer_nonce_and_capability_match_main() {
		wp_set_current_user( $this->admin_user_id );
		$_POST = array( 'nonce' => 'invalid' );
		$_REQUEST = $_POST;

		$controller = new AIPS_Content_Indexer_Controller();
		foreach ( array( 'ajax_get_status', 'ajax_clear_index', 'ajax_process_batch' ) as $method ) {
			$response = $this->capture_ajax( array( $controller, $method ) );
			$this->assert_http_200_error( $response, 'invalid_nonce', 'Security check failed. Please refresh the page.', $method );
		}

		wp_set_current_user( $this->subscriber_user_id );
		$_POST = array( 'nonce' => wp_create_nonce( 'aips_ajax_nonce' ) );
		$_REQUEST = $_POST;

		foreach ( array( 'ajax_get_status', 'ajax_clear_index', 'ajax_process_batch' ) as $method ) {
			$response = $this->capture_ajax( array( $controller, $method ) );
			$this->assert_http_200_error( $response, 'forbidden', 'Permission denied.', $method );
		}
	}

	public function test_link_report_nonce_and_capability_match_main() {
		wp_set_current_user( $this->admin_user_id );
		$_POST = array( 'nonce' => 'invalid' );
		$_REQUEST = $_POST;

		$controller = new AIPS_Link_Report_Controller();
		$response = $this->capture_ajax( array( $controller, 'ajax_get_report' ) );
		$this->assert_http_200_error( $response, 'invalid_nonce', 'Security check failed. Please refresh the page.' );

		wp_set_current_user( $this->subscriber_user_id );
		$_POST = array( 'nonce' => wp_create_nonce( 'aips_ajax_nonce' ) );
		$_REQUEST = $_POST;

		$response = $this->capture_ajax( array( $controller, 'ajax_apply_suggestion' ) );
		$this->assert_http_200_error( $response, 'permission_denied', 'Permission denied.' );
	}

	public function test_schedule_status_nonce_and_capability_match_main() {
		wp_set_current_user( $this->admin_user_id );
		$_POST = array( 'nonce' => 'invalid' );
		$_REQUEST = $_POST;

		$handler = new AIPS_Schedule_Query_Handler();
		$response = $this->capture_ajax( array( $handler, 'get_schedule_status_read_model' ) );
		$this->assert_http_200_error( $response, 'error', 'Invalid nonce.' );

		wp_set_current_user( $this->subscriber_user_id );
		$_POST = array( 'nonce' => wp_create_nonce( 'aips_ajax_nonce' ) );
		$_REQUEST = $_POST;

		$response = $this->capture_ajax( array( $handler, 'get_schedule_status_read_model' ) );
		$this->assert_http_200_error( $response, 'error', 'Unauthorized.' );
	}

	public function test_default_guard_profile_returns_http_200() {
		wp_set_current_user( $this->admin_user_id );
		$_POST = array( 'nonce' => 'invalid' );
		$_REQUEST = $_POST;

		$handler = new AIPS_Author_Topics_Mutation_Handler();
		$response = $this->capture_ajax( array( $handler, 'edit_topic' ) );
		$this->assert_http_200_error( $response, 'error', 'Invalid nonce.' );

		wp_set_current_user( $this->subscriber_user_id );
		$_POST = array(
			'nonce' => wp_create_nonce( 'aips_ajax_nonce' ),
			'ids'   => array( 1 ),
		);
		$_REQUEST = $_POST;

		$schedules = new AIPS_Schedule_Query_Handler();
		$response = $this->capture_ajax( array( $schedules, 'get_schedules_post_count' ) );
		$this->assert_http_200_error( $response, 'permission_denied', 'Permission denied.' );
	}

	/**
	 * Assert an auth failure uses HTTP 200 and the endpoint's original error code.
	 *
	 * @param array<string, mixed> $response Captured AJAX payload.
	 * @param string               $code     Expected error code.
	 * @param string               $message  Expected error message.
	 * @param string               $context  Assertion context.
	 * @return void
	 */
	private function assert_http_200_error( array $response, $code, $message, $context = '' ) {
		$this->assertFalse( $response['success'], $context );
		$this->assertSame( $code, $response['data']['code'], $context );
		$this->assertSame( $message, $response['data']['message'], $context );
		$this->assertSame( 200, $response['http_status'], $context );
	}

	/**
	 * @param callable $callable AJAX callback.
	 * @return array<string, mixed>
	 */
	private function capture_ajax( $callable ) {
		$statuses = array();
		$filter = function ( $http_status ) use ( &$statuses ) {
			$statuses[] = (int) $http_status;
		};
		add_action( 'aips_ajax_error_response', $filter, 10, 1 );

		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected when wp_send_json_* is called.
		} catch ( WPAjaxDieStopException $e ) {
			// Expected for wp_die()-style early exits.
		}
		$output = ob_get_clean();
		remove_action( 'aips_ajax_error_response', $filter, 10 );

		$decoded = json_decode( strtok( trim( (string) $output ), "\r\n" ), true );
		if ( ! is_array( $decoded ) ) {
			$decoded = array( 'raw' => $output );
		}
		$decoded['http_status'] = empty( $statuses ) ? null : (int) end( $statuses );

		return $decoded;
	}
}
