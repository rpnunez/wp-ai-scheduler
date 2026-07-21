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

	public function test_ajax_repair_campaign_data_runs_history_repair_and_returns_success() {
		$history_repository = $this->getMockBuilder('AIPS_History_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('repair_missing_campaign_ids'))
			->getMock();

		$history_repository->expects($this->once())
			->method('repair_missing_campaign_ids');

		$refresh_service = new AIPS_System_Refresh_Service($history_repository);

		$controller = new AIPS_System_Status_Controller();
		$reflection = new ReflectionClass($controller);
		$property = $reflection->getProperty('refresh_service');
		$property->setAccessible(true);
		$property->setValue($controller, $refresh_service);

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
		$controller = new AIPS_System_Status_Controller();

		$_POST = array(
			'nonce' => 'invalid-nonce',
		);
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($controller, 'ajax_refresh_system'));

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid nonce.', $response['data']['message']);
	}

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
