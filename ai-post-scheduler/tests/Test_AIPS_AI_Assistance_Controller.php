<?php
/**
 * Tests for AIPS_AI_Assistance_Controller
 *
 * Covers nonce/capability enforcement and happy-path flows for both
 * ajax_field_assist() and ajax_get_field_assist_history().
 *
 * @package AI_Post_Scheduler
 * @since 2.5.1
 */

class Test_AIPS_AI_Assistance_Controller extends WP_UnitTestCase {

	/** @var AIPS_AI_Assistance_Repository */
	private $repository;

	/** @var int Admin user ID. */
	private $admin_user_id;

	/** @var int Subscriber user ID. */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->repository        = new AIPS_AI_Assistance_Repository();
		$this->admin_user_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $this->admin_user_id );
	}

	public function tearDown(): void {
		$_POST = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	private function sync_request_from_post() {
		$_REQUEST = $_POST;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a controller with a stubbed service that returns $ai_response.
	 *
	 * @param string|WP_Error $ai_response Value returned by the mock service.
	 * @return AIPS_AI_Assistance_Controller
	 */
	private function make_controller_with_mock_service( $ai_response ) {
		$mock_ai = $this->getMockBuilder( 'AIPS_AI_Service_Interface' )
			->getMock();
		$mock_ai->method( 'generate_text' )->willReturn( $ai_response );

		$service = new AIPS_AI_Assistance_Service( $mock_ai, $this->repository );
		return new AIPS_AI_Assistance_Controller( $service, $this->repository );
	}

	/**
	 * Run an AJAX handler and return the decoded JSON response.
	 *
	 * @param callable $callable Handler to invoke.
	 * @return array|null Decoded response array, or null on parse failure.
	 */
	private function run_ajax( callable $callable ) {
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_* called.
		} catch ( WPAjaxDieStopException $e ) {
			// Expected: wp_die called (e.g. nonce failure).
		}
		$output = ob_get_clean();
		return json_decode( $output, true );
	}

	// -------------------------------------------------------------------------
	// ajax_field_assist — nonce enforcement
	// -------------------------------------------------------------------------

	public function test_field_assist_rejects_missing_nonce() {
		$controller = new AIPS_AI_Assistance_Controller();
		$_POST      = array(
			'action'       => 'aips_ai_field_assist',
			'form_context' => 'authors',
			'field_key'    => 'author_name',
			'field_name'   => 'Name',
			'session_id'   => 'test-session-1',
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax( array( $controller, 'ajax_field_assist' ) );

		$this->assertNotNull( $response );
		$this->assertFalse( $response['success'] );
	}

	// -------------------------------------------------------------------------
	// ajax_field_assist — capability enforcement
	// -------------------------------------------------------------------------

	public function test_field_assist_rejects_non_admin() {
		wp_set_current_user( $this->subscriber_user_id );
		$controller = new AIPS_AI_Assistance_Controller();
		$_POST      = array(
			'action'       => 'aips_ai_field_assist',
			'nonce'        => wp_create_nonce( 'aips_ajax_nonce' ),
			'form_context' => 'authors',
			'field_key'    => 'author_name',
			'field_name'   => 'Name',
			'session_id'   => 'test-session-1',
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax( array( $controller, 'ajax_field_assist' ) );

		$this->assertNotNull( $response );
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'ermission', $response['data']['message'] );
	}

	// -------------------------------------------------------------------------
	// ajax_field_assist — parameter validation
	// -------------------------------------------------------------------------

	public function test_field_assist_rejects_empty_session_id() {
		$controller = new AIPS_AI_Assistance_Controller();
		$_POST      = array(
			'action'       => 'aips_ai_field_assist',
			'nonce'        => wp_create_nonce( 'aips_ajax_nonce' ),
			'form_context' => 'authors',
			'field_key'    => 'author_name',
			'field_name'   => 'Name',
			'session_id'   => '',
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax( array( $controller, 'ajax_field_assist' ) );

		$this->assertNotNull( $response );
		$this->assertFalse( $response['success'] );
	}

	public function test_field_assist_rejects_missing_required_params() {
		$controller = new AIPS_AI_Assistance_Controller();
		$_POST      = array(
			'action'     => 'aips_ai_field_assist',
			'nonce'      => wp_create_nonce( 'aips_ajax_nonce' ),
			'session_id' => 'test-session-1',
			// form_context, field_key, field_name intentionally omitted
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax( array( $controller, 'ajax_field_assist' ) );

		$this->assertNotNull( $response );
		$this->assertFalse( $response['success'] );
	}

	// -------------------------------------------------------------------------
	// ajax_field_assist — happy path
	// -------------------------------------------------------------------------

	public function test_field_assist_happy_path_returns_response_and_persists_record() {
		$controller = $this->make_controller_with_mock_service( 'John Doe' );

		$session_id = 'test-session-happy';
		$_POST      = array(
			'action'           => 'aips_ai_field_assist',
			'nonce'            => wp_create_nonce( 'aips_ajax_nonce' ),
			'form_context'     => 'authors',
			'field_key'        => 'author_name',
			'field_name'       => 'Name',
			'session_id'       => $session_id,
			'description'      => 'The display name',
			'influence'        => 'Author byline',
			'expected_response'=> 'A short pen name',
			'current_value'    => '',
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax( array( $controller, 'ajax_field_assist' ) );

		$this->assertNotNull( $response );
		$this->assertTrue( $response['success'], 'Expected success response' );
		$this->assertEquals( 'John Doe', $response['data']['response'] );
		$this->assertNotEmpty( $response['data']['record_id'] );

		// Verify record was persisted to the DB.
		$records = $this->repository->get_by_session_and_field( $session_id, 'authors', 'author_name' );
		$this->assertCount( 1, $records );
		$this->assertEquals( 'John Doe', $records[0]->response );
	}

	public function test_field_assist_propagates_ai_error() {
		$wp_error   = new WP_Error( 'ai_failed', 'AI service unavailable.' );
		$controller = $this->make_controller_with_mock_service( $wp_error );

		$_POST = array(
			'action'       => 'aips_ai_field_assist',
			'nonce'        => wp_create_nonce( 'aips_ajax_nonce' ),
			'form_context' => 'authors',
			'field_key'    => 'author_name',
			'field_name'   => 'Name',
			'session_id'   => 'test-session-error',
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax( array( $controller, 'ajax_field_assist' ) );

		$this->assertNotNull( $response );
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'AI service unavailable', $response['data']['message'] );
	}

	// -------------------------------------------------------------------------
	// ajax_get_field_assist_history — nonce enforcement
	// -------------------------------------------------------------------------

	public function test_get_history_rejects_missing_nonce() {
		$controller = new AIPS_AI_Assistance_Controller();
		$_POST      = array(
			'action'       => 'aips_get_field_assist_history',
			'form_context' => 'authors',
			'field_key'    => 'author_name',
			'session_id'   => 'test-session-1',
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax( array( $controller, 'ajax_get_field_assist_history' ) );

		$this->assertNotNull( $response );
		$this->assertFalse( $response['success'] );
	}

	// -------------------------------------------------------------------------
	// ajax_get_field_assist_history — capability enforcement
	// -------------------------------------------------------------------------

	public function test_get_history_rejects_non_admin() {
		wp_set_current_user( $this->subscriber_user_id );
		$controller = new AIPS_AI_Assistance_Controller();
		$_POST      = array(
			'action'       => 'aips_get_field_assist_history',
			'nonce'        => wp_create_nonce( 'aips_ajax_nonce' ),
			'form_context' => 'authors',
			'field_key'    => 'author_name',
			'session_id'   => 'test-session-1',
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax( array( $controller, 'ajax_get_field_assist_history' ) );

		$this->assertNotNull( $response );
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'ermission', $response['data']['message'] );
	}

	// -------------------------------------------------------------------------
	// ajax_get_field_assist_history — parameter validation
	// -------------------------------------------------------------------------

	public function test_get_history_rejects_empty_session_id() {
		$controller = new AIPS_AI_Assistance_Controller();
		$_POST      = array(
			'action'       => 'aips_get_field_assist_history',
			'nonce'        => wp_create_nonce( 'aips_ajax_nonce' ),
			'form_context' => 'authors',
			'field_key'    => 'author_name',
			'session_id'   => '',
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax( array( $controller, 'ajax_get_field_assist_history' ) );

		$this->assertNotNull( $response );
		$this->assertFalse( $response['success'] );
	}

	// -------------------------------------------------------------------------
	// ajax_get_field_assist_history — happy path
	// -------------------------------------------------------------------------

	public function test_get_history_returns_session_and_alltime_records() {
		$session_id = 'test-session-history';

		// Seed two records for this session and one for a different session.
		$this->repository->create( array(
			'session_id'     => $session_id,
			'user_id'        => $this->admin_user_id,
			'form_context'   => 'authors',
			'field_key'      => 'author_name',
			'request_object' => '{}',
			'prompt'         => 'Prompt A',
			'response'       => 'Alice Writer',
		) );
		$this->repository->create( array(
			'session_id'     => $session_id,
			'user_id'        => $this->admin_user_id,
			'form_context'   => 'authors',
			'field_key'      => 'author_name',
			'request_object' => '{}',
			'prompt'         => 'Prompt B',
			'response'       => 'Bob Blogger',
		) );
		$this->repository->create( array(
			'session_id'     => 'other-session',
			'user_id'        => $this->admin_user_id,
			'form_context'   => 'authors',
			'field_key'      => 'author_name',
			'request_object' => '{}',
			'prompt'         => 'Prompt C',
			'response'       => 'Carol Author',
		) );

		$controller = new AIPS_AI_Assistance_Controller( null, $this->repository );
		$_POST      = array(
			'action'       => 'aips_get_field_assist_history',
			'nonce'        => wp_create_nonce( 'aips_ajax_nonce' ),
			'form_context' => 'authors',
			'field_key'    => 'author_name',
			'session_id'   => $session_id,
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax( array( $controller, 'ajax_get_field_assist_history' ) );

		$this->assertNotNull( $response );
		$this->assertTrue( $response['success'], 'Expected success response' );

		// Session tab: only the two records from this session.
		$this->assertCount( 2, $response['data']['session'] );

		// All-time tab: all three records.
		$this->assertCount( 3, $response['data']['alltime'] );
	}

	// -------------------------------------------------------------------------
	// Misc
	// -------------------------------------------------------------------------

	public function test_ajax_actions_registered() {
		new AIPS_AI_Assistance_Controller();
		$this->assertTrue( (bool) has_action( 'wp_ajax_aips_ai_field_assist' ) );
		$this->assertTrue( (bool) has_action( 'wp_ajax_aips_get_field_assist_history' ) );
	}
}
