<?php
/**
 * AJAX contract tests for AIPS_Link_Report_Controller.
 *
 * Verifies authentication, nonces, input validation, response shape,
 * and long-running job state transitions (backfill and autolink).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Link_Report_Controller extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * @var AIPS_Link_Report_Controller
	 */
	private $controller;

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $service;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));

		wp_set_current_user($this->admin_user_id);

		$_POST = array();
		$_GET = array();
		$_REQUEST = array();

		$this->service = new AIPS_Link_Index_Service();
		$this->controller = new AIPS_Link_Report_Controller($this->service);
	}

	public function tearDown(): void {
		$_POST = array();
		$_GET = array();
		$_REQUEST = array();

		AIPS_Container::get_instance()->clear();
		AI_Post_Scheduler::get_instance()->register_container_bindings();

		parent::tearDown();
	}

	/**
	 * Helper to capture JSON response from an AJAX action.
	 */
	private function capture_ajax(callable $callable): array {
		ob_start();

		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		} catch (WPAjaxDieStopException $e) {
			// Expected.
		}

		$output = ob_get_clean();
		$decoded = json_decode($output, true);

		return is_array($decoded) ? $decoded : array('raw' => $output);
	}

	/**
	 * Test that endpoints fail when nonce is missing or invalid.
	 */
	public function test_endpoints_fail_on_invalid_nonce() {
		$_POST = array('nonce' => 'invalid_nonce_value');
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_report'));
		$this->assertFalse($response['success']);
		$this->assertSame('invalid_nonce', $response['data']['code']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_start_backfill'));
		$this->assertFalse($response['success']);
		$this->assertSame('invalid_nonce', $response['data']['code']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_autolink_start'));
		$this->assertFalse($response['success']);
		$this->assertSame('invalid_nonce', $response['data']['code']);
	}

	/**
	 * Test that endpoints fail when user lacks manage_options capability.
	 */
	public function test_endpoints_fail_on_unauthorized_user() {
		wp_set_current_user($this->subscriber_user_id);

		$_POST = array('nonce' => wp_create_nonce('aips_ajax_nonce'));
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_report'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_start_backfill'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_autolink_start'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	/**
	 * Test ajax_get_report returns expected response shape.
	 */
	public function test_ajax_get_report_response_shape() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Sample Report Post',
			'post_content' => '<p>Content with <a href="https://example.com">link</a></p>',
			'post_status'  => 'publish',
		));

		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'paged' => 1,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_report'));

		$this->assertTrue($response['success']);
		$data = $response['data'];
		$this->assertArrayHasKey('rows', $data);
		$this->assertArrayHasKey('total', $data);
		$this->assertArrayHasKey('page', $data);
		$this->assertArrayHasKey('total_pages', $data);
		$this->assertArrayHasKey('summary', $data);
		$this->assertIsArray($data['rows']);
		$this->assertIsArray($data['summary']);
	}

	/**
	 * Test ajax_get_post_links input validation and response shape.
	 */
	public function test_ajax_get_post_links_validation_and_shape() {
		// Test missing/invalid post ID
		$_POST = array(
			'nonce'   => wp_create_nonce('aips_ajax_nonce'),
			'post_id' => 999999,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_post_links'));
		$this->assertFalse($response['success']);
		$this->assertSame('not_found', $response['data']['code']);

		// Test existing post ID
		$post_id = $this->factory->post->create(array('post_title' => 'Drilldown Post'));
		$_POST['post_id'] = $post_id;
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_post_links'));
		$this->assertTrue($response['success']);
		$data = $response['data'];
		$this->assertSame($post_id, $data['post_id']);
		$this->assertSame('Drilldown Post', $data['title']);
		$this->assertArrayHasKey('outbound', $data);
		$this->assertArrayHasKey('inbound', $data);
		$this->assertIsArray($data['outbound']);
		$this->assertIsArray($data['inbound']);
	}

	/**
	 * Test ajax_start_backfill fails when link indexing is disabled.
	 */
	public function test_ajax_start_backfill_fails_when_disabled() {
		update_option('aips_link_index_enabled', 0);

		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'mode'  => 'missing',
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_start_backfill'));
		$this->assertFalse($response['success']);
		$this->assertSame('disabled', $response['data']['code']);

		update_option('aips_link_index_enabled', 1);
	}

	/**
	 * Test backfill job-state transitions: status, pause, resume, cancel.
	 */
	public function test_ajax_backfill_state_transitions() {
		$_POST = array('nonce' => wp_create_nonce('aips_ajax_nonce'));
		$_REQUEST = $_POST;

		// 1. Backfill status
		$response = $this->capture_ajax(array($this->controller, 'ajax_backfill_status'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('backfill', $response['data']);
		$this->assertArrayHasKey('summary', $response['data']);

		// 2. Pause when not running returns error
		$response = $this->capture_ajax(array($this->controller, 'ajax_pause_backfill'));
		$this->assertFalse($response['success']);
		$this->assertSame('not_running', $response['data']['code']);

		// 3. Resume when not paused returns error
		$response = $this->capture_ajax(array($this->controller, 'ajax_resume_backfill'));
		$this->assertFalse($response['success']);
		$this->assertSame('not_paused', $response['data']['code']);

		// 4. Cancel when not running returns error
		$response = $this->capture_ajax(array($this->controller, 'ajax_cancel_backfill'));
		$this->assertFalse($response['success']);
		$this->assertSame('not_running', $response['data']['code']);

		// 5. Test with mock service handling transitions
		$mock_service = $this->getMockBuilder('AIPS_Link_Index_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('start_backfill', 'pause_backfill', 'resume_backfill', 'cancel_backfill', 'get_backfill_status', 'is_enabled', 'get_post_types', 'get_repository'))
			->getMock();

		$mock_service->method('is_enabled')->willReturn(true);
		$mock_service->method('get_post_types')->willReturn(array('post'));
		$mock_service->method('get_repository')->willReturn(new AIPS_Link_Index_Repository());
		$mock_service->method('start_backfill')->willReturn(array('total' => 5, 'job_id' => 'job_123'));
		$mock_service->method('pause_backfill')->willReturn(true);
		$mock_service->method('resume_backfill')->willReturn(true);
		$mock_service->method('cancel_backfill')->willReturn(true);
		$mock_service->method('get_backfill_status')->willReturn(array('state' => 'processing', 'progress' => 50));

		$ctrl = new AIPS_Link_Report_Controller($mock_service);

		// Start backfill
		$_POST = array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
			'mode'  => 'missing',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_start_backfill'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('backfill', $response['data']);

		// Pause backfill
		$response = $this->capture_ajax(array($ctrl, 'ajax_pause_backfill'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('message', $response['data']);

		// Resume backfill
		$response = $this->capture_ajax(array($ctrl, 'ajax_resume_backfill'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('message', $response['data']);

		// Cancel backfill
		$response = $this->capture_ajax(array($ctrl, 'ajax_cancel_backfill'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('message', $response['data']);
	}

	/**
	 * Test ajax_get_post_panel input validation and response shape.
	 */
	public function test_ajax_get_post_panel_validation_and_shape() {
		// Non-existent post
		$_POST = array(
			'nonce'   => wp_create_nonce('aips_ajax_nonce'),
			'post_id' => 999999,
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_post_panel'));
		$this->assertFalse($response['success']);
		$this->assertSame('not_found', $response['data']['code']);

		// Existing post
		$post_id = $this->factory->post->create(array('post_title' => 'Panel Post'));
		$_POST['post_id'] = $post_id;
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_get_post_panel'));
		$this->assertTrue($response['success']);
		$data = $response['data'];
		$this->assertSame($post_id, $data['post_id']);
		$this->assertArrayHasKey('in_scope', $data);
		$this->assertArrayHasKey('indexed', $data);
		$this->assertArrayHasKey('counts', $data);
		$this->assertArrayHasKey('is_orphan', $data);
		$this->assertArrayHasKey('can_suggest', $data);
		$this->assertArrayHasKey('sources', $data);
		$this->assertArrayHasKey('suggestions', $data);
		$this->assertArrayHasKey('report_url', $data);
	}

	/**
	 * Test autolink state transitions: start, status, pause, resume, cancel, undo.
	 */
	public function test_ajax_autolink_lifecycle_state_transitions() {
		$_POST = array('nonce' => wp_create_nonce('aips_ajax_nonce'));
		$_REQUEST = $_POST;

		$mock_runs = $this->getMockBuilder('AIPS_Autolink_Run_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('start', 'pause', 'resume', 'cancel', 'undo_run', 'ensure_scheduled', 'get_current', 'get_history'))
			->getMock();

		$mock_runs->method('start')->willReturn(array('total' => 10, 'job_id' => 'autolink_001'));
		$mock_runs->method('pause')->willReturn(true);
		$mock_runs->method('resume')->willReturn(true);
		$mock_runs->method('cancel')->willReturn(true);
		$mock_runs->method('undo_run')->willReturn(array('reverted' => 5, 'conflicts' => 0));
		$mock_runs->method('get_current')->willReturn(null);
		$mock_runs->method('get_history')->willReturn(array());

		AIPS_Container::get_instance()->singleton(AIPS_Autolink_Run_Service::class, function () use ($mock_runs) {
			return $mock_runs;
		});

		$ctrl = new AIPS_Link_Report_Controller($this->service);

		// 1. Start autolink
		$_POST = array(
			'nonce'   => wp_create_nonce('aips_ajax_nonce'),
			'scope'   => 'orphans',
			'dry_run' => 'false',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_autolink_start'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('message', $response['data']);
		$this->assertArrayHasKey('autolink', $response['data']);

		// 2. Status
		$response = $this->capture_ajax(array($ctrl, 'ajax_autolink_status'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('autolink', $response['data']);
		$this->assertArrayHasKey('summary', $response['data']);

		// 3. Pause
		$response = $this->capture_ajax(array($ctrl, 'ajax_autolink_pause'));
		$this->assertTrue($response['success']);
		$this->assertSame('Auto-link run paused.', $response['data']['message']);

		// 4. Resume
		$response = $this->capture_ajax(array($ctrl, 'ajax_autolink_resume'));
		$this->assertTrue($response['success']);
		$this->assertSame('Auto-link run resumed.', $response['data']['message']);

		// 5. Cancel
		$response = $this->capture_ajax(array($ctrl, 'ajax_autolink_cancel'));
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('cancelled', $response['data']['message']);

		// 6. Undo run
		$_POST['job_id'] = 'autolink_001';
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_autolink_undo_run'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('message', $response['data']);
	}

	/**
	 * Test autolink pause/resume/cancel failure paths.
	 */
	public function test_ajax_autolink_failures_when_invalid_state() {
		$mock_runs = $this->getMockBuilder('AIPS_Autolink_Run_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('pause', 'resume', 'cancel', 'undo_run', 'get_current', 'get_history'))
			->getMock();

		$mock_runs->method('pause')->willReturn(false);
		$mock_runs->method('resume')->willReturn(false);
		$mock_runs->method('cancel')->willReturn(false);
		$mock_runs->method('undo_run')->willReturn(new WP_Error('not_found', 'Run not found'));
		$mock_runs->method('get_current')->willReturn(null);
		$mock_runs->method('get_history')->willReturn(array());

		AIPS_Container::get_instance()->singleton(AIPS_Autolink_Run_Service::class, function () use ($mock_runs) {
			return $mock_runs;
		});

		$ctrl = new AIPS_Link_Report_Controller($this->service);

		$_POST = array('nonce' => wp_create_nonce('aips_ajax_nonce'));
		$_REQUEST = $_POST;

		// Pause fails
		$response = $this->capture_ajax(array($ctrl, 'ajax_autolink_pause'));
		$this->assertFalse($response['success']);
		$this->assertSame('invalid_state', $response['data']['code']);

		// Resume fails
		$response = $this->capture_ajax(array($ctrl, 'ajax_autolink_resume'));
		$this->assertFalse($response['success']);
		$this->assertSame('invalid_state', $response['data']['code']);

		// Cancel fails
		$response = $this->capture_ajax(array($ctrl, 'ajax_autolink_cancel'));
		$this->assertFalse($response['success']);
		$this->assertSame('invalid_state', $response['data']['code']);

		// Undo fails
		$response = $this->capture_ajax(array($ctrl, 'ajax_autolink_undo_run'));
		$this->assertFalse($response['success']);
		$this->assertSame('not_found', $response['data']['code']);
	}
}
