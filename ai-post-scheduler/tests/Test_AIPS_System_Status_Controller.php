<?php
/**
 * Tests for AIPS_System_Status_Controller.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_System_Status_Controller extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_user_id);

		$_POST = array();
		$_GET = array();
		$_REQUEST = array();
	}

	public function tearDown(): void {
		$_POST = array();
		$_GET = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Build a controller and optionally swap only its diagnostics service.
	 *
	 * Tests covering other controller dependencies should instantiate and wire
	 * those collaborators independently.
	 *
	 * @param AIPS_System_Diagnostics_Service|null $diagnostics_service Optional diagnostics service override.
	 * @return AIPS_System_Status_Controller
	 */
	private function make_controller($diagnostics_service = null) {
		$controller = new AIPS_System_Status_Controller();

		if (null !== $diagnostics_service) {
			$reflection = new ReflectionClass($controller);
			$property = $reflection->getProperty('diagnostics_service');
			$property->setAccessible(true);
			$property->setValue($controller, $diagnostics_service);
		}

		return $controller;
	}

	public function test_ajax_repair_campaign_data_runs_history_repair_and_returns_success() {
		$history_repository = $this->getMockBuilder('AIPS_History_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('repair_missing_campaign_ids'))
			->getMock();

		$history_repository->expects($this->once())
			->method('repair_missing_campaign_ids');

		$diagnostics_service = new AIPS_System_Diagnostics_Service($history_repository);

		$controller = $this->make_controller($diagnostics_service);

		$_POST = array(
			'nonce' => wp_create_nonce('aips_status_repair_campaign_data'),
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_repair_campaign_data'));

		$this->assertTrue($response['success']);
		$this->assertStringContainsString('Campaign data repair completed.', $response['data']['message']);
	}

	public function test_ajax_registry_includes_campaign_repair_action() {
		$this->assertSame('AIPS_System_Status_Controller', AIPS_Ajax_Registry::get_controller_for('aips_status_repair_campaign_data'));
	}

	public function test_ajax_registry_includes_new_maintenance_actions() {
		$actions = array(
			'aips_status_refresh_system',
			'aips_status_cache_maintenance',
			'aips_status_cleanup_notifications',
			'aips_status_reset_resilience',
			'aips_status_repair_datetime',
		);

		foreach ($actions as $action) {
			$this->assertSame('AIPS_System_Status_Controller', AIPS_Ajax_Registry::get_controller_for($action), $action);
		}
	}

	public function test_ajax_refresh_system_rejects_invalid_nonce() {
		$controller = $this->make_controller();

		$_POST = array(
			'nonce' => 'invalid-nonce',
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_refresh_system'));

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid nonce.', $response['data']['message']);
	}

	public function test_ajax_refresh_system_rejects_empty_task_selection() {
		$controller = $this->make_controller();

		$_POST = array(
			'nonce' => wp_create_nonce('aips_status_refresh_system'),
			'tasks' => array(),
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_refresh_system'));

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Select at least one maintenance task', $response['data']['message']);
	}

	public function test_ajax_refresh_system_passes_selected_tasks_to_service() {
		$diagnostics_service = $this->getMockBuilder('AIPS_System_Diagnostics_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('refresh_system'))
			->getMock();
		$diagnostics_service->expects($this->once())
			->method('refresh_system')
			->with(array('cache_maintenance', 'repair_datetime'))
			->willReturn(array(
				'success'   => true,
				'steps'     => array(),
				'succeeded' => 2,
				'failed'    => 0,
				'message'   => 'ok',
			));

		$controller = $this->make_controller($diagnostics_service);

		$_POST = array(
			'nonce' => wp_create_nonce('aips_status_refresh_system'),
			'tasks' => array('cache_maintenance', 'repair_datetime'),
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_refresh_system'));

		$this->assertTrue($response['success']);
		$this->assertSame('ok', $response['data']['message']);
	}

	/**
	 * Capture the JSON response emitted by an AJAX controller method.
	 *
	 * @param callable $callable AJAX callback under test.
	 * @return array<string, mixed>|null
	 */
	private function capture_ajax(callable $callable) {
		ob_start();

		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		} catch (WPAjaxDieStopException $e) {
			// Expected.
		}

		return json_decode(ob_get_clean(), true);
	}
}
