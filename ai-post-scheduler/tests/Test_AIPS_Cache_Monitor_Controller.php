<?php
/**
 * AJAX contract tests for AIPS_Cache_Monitor_Controller.
 *
 * Verifies authentication, nonces, input validation, response shape,
 * disabled states, and state-changing cache mutations and maintenance.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Cache_Monitor_Controller extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * @var AIPS_Cache_Monitor_Controller
	 */
	private $controller;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));

		wp_set_current_user($this->admin_user_id);
		update_option('aips_cache_monitor_enabled', 1);

		$_POST = array();
		$_GET = array();
		$_REQUEST = array();

		$this->controller = new AIPS_Cache_Monitor_Controller();
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
	 * Test that endpoints fail on invalid nonce.
	 */
	public function test_endpoints_fail_on_invalid_nonce() {
		$_POST = array('nonce' => 'invalid_nonce_value');
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_summary'));
		$this->assertFalse($response['success']);
		$this->assertSame('invalid_nonce', $response['data']['code']);

		$response = $this->capture_ajax(array($this->controller, 'ajax_flush_all'));
		$this->assertFalse($response['success']);
		$this->assertSame('invalid_nonce', $response['data']['code']);
	}

	/**
	 * Test that endpoints fail on unauthorized user.
	 */
	public function test_endpoints_fail_on_unauthorized_user() {
		wp_set_current_user($this->subscriber_user_id);

		// Read endpoint with subscriber nonce
		$_POST = array('nonce' => wp_create_nonce('aips_cache_monitor'));
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_summary'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);

		// Mutation endpoint with subscriber action nonce
		$_POST = array('nonce' => wp_create_nonce('aips_cache_monitor_action'));
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_flush_all'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	/**
	 * Test that endpoints fail when Cache Monitor is disabled in settings.
	 */
	public function test_endpoints_fail_when_cache_monitor_disabled() {
		update_option('aips_cache_monitor_enabled', 0);

		$_POST = array('nonce' => wp_create_nonce('aips_cache_monitor'));
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_summary'));
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('disabled', $response['data']['message']);

		update_option('aips_cache_monitor_enabled', 1);
	}

	/**
	 * Test ajax_summary response shape.
	 */
	public function test_ajax_summary_response_shape() {
		$_POST = array('nonce' => wp_create_nonce('aips_cache_monitor'));
		$_REQUEST = $_POST;

		$response = $this->capture_ajax(array($this->controller, 'ajax_summary'));
		$this->assertTrue($response['success']);
		$data = $response['data'];
		$this->assertArrayHasKey('cache_enabled', $data);
		$this->assertArrayHasKey('driver_name', $data);
		$this->assertArrayHasKey('driver_label', $data);
		$this->assertArrayHasKey('capabilities', $data);
		$this->assertArrayHasKey('index', $data);
	}

	/**
	 * Test input validation on inspect and mutations.
	 */
	public function test_input_validation_on_mutations() {
		// 1. Inspect without key_hash (uses aips_cache_monitor)
		$_POST = array('nonce' => wp_create_nonce('aips_cache_monitor'));
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_inspect'));
		$this->assertFalse($response['success']);
		$this->assertSame('key_hash is required.', $response['data']['message']);

		// Mutation nonces (uses aips_cache_monitor_action)
		$action_nonce = wp_create_nonce('aips_cache_monitor_action');

		// 2. Delete entry without key_hash
		$_POST = array('nonce' => $action_nonce);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_delete_entry'));
		$this->assertFalse($response['success']);
		$this->assertSame('key_hash is required.', $response['data']['message']);

		// 3. Delete bulk without key_hashes
		$_POST = array('nonce' => $action_nonce);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_delete_bulk'));
		$this->assertFalse($response['success']);
		$this->assertSame('No key hashes provided.', $response['data']['message']);

		// 4. Flush group without cache_group
		$_POST = array('nonce' => $action_nonce);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_flush_group'));
		$this->assertFalse($response['success']);
		$this->assertSame('cache_group is required.', $response['data']['message']);

		// 5. Invalidate tag without tag
		$_POST = array('nonce' => $action_nonce);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_invalidate_tag'));
		$this->assertFalse($response['success']);
		$this->assertSame('tag is required.', $response['data']['message']);

		// 6. Invalidate domain without domain
		$_POST = array('nonce' => $action_nonce);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_invalidate_domain'));
		$this->assertFalse($response['success']);
		$this->assertSame('domain is required.', $response['data']['message']);

		// 7. Flush all without confirmed
		$_POST = array('nonce' => $action_nonce);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_flush_all'));
		$this->assertFalse($response['success']);
		$this->assertSame('confirmation_required', $response['data']['code']);

		// 8. Unknown maintenance action
		$_POST = array(
			'nonce'              => $action_nonce,
			'maintenance_action' => 'non_existent_maintenance',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($this->controller, 'ajax_maintenance'));
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Unknown maintenance action', $response['data']['message']);
	}

	/**
	 * Test state-changing cache mutations with service double.
	 */
	public function test_cache_mutations_and_maintenance() {
		$mock_service = $this->getMockBuilder('AIPS_Cache_Monitor_Service')
			->disableOriginalConstructor()
			->onlyMethods(array(
				'delete_entry',
				'delete_entries_bulk',
				'flush_group',
				'flush_expired',
				'flush_all_plugin_cache',
				'invalidate_tag',
				'invalidate_domain',
				'rebuild_index',
				'run_maintenance',
				'export_diagnostics',
			))
			->getMock();

		$mock_service->method('delete_entry')->willReturn(1);
		$mock_service->method('delete_entries_bulk')->willReturn(2);
		$mock_service->method('flush_group')->willReturn(5);
		$mock_service->method('flush_expired')->willReturn(3);
		$mock_service->method('invalidate_tag')->willReturn(4);
		$mock_service->method('invalidate_domain')->willReturn(array('tag1', 'tag2'));
		$mock_service->method('run_maintenance')->willReturn(array('status' => 'ok'));
		$mock_service->method('rebuild_index')->willReturn(15);
		$mock_service->method('export_diagnostics')->willReturn(array('version' => '1.0'));

		$ctrl = new AIPS_Cache_Monitor_Controller($mock_service);
		$action_nonce = wp_create_nonce('aips_cache_monitor_action');

		// 1. Delete single entry
		$_POST = array(
			'nonce'    => $action_nonce,
			'key_hash' => 'hash_abc',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_delete_entry'));
		$this->assertTrue($response['success']);
		$this->assertSame(1, $response['data']['affected']);

		// 2. Delete bulk entries
		$_POST = array(
			'nonce'      => $action_nonce,
			'key_hashes' => array('hash1', 'hash2'),
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_delete_bulk'));
		$this->assertTrue($response['success']);
		$this->assertSame(2, $response['data']['affected']);

		// 3. Flush group
		$_POST = array(
			'nonce'       => $action_nonce,
			'cache_group' => 'test_group',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_flush_group'));
		$this->assertTrue($response['success']);
		$this->assertSame(5, $response['data']['affected']);
		$this->assertSame('test_group', $response['data']['group']);

		// 4. Flush expired
		$_POST = array('nonce' => $action_nonce);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_flush_expired'));
		$this->assertTrue($response['success']);
		$this->assertSame(3, $response['data']['affected']);

		// 5. Flush all (with confirmed)
		$_POST = array(
			'nonce'     => $action_nonce,
			'confirmed' => 1,
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_flush_all'));
		$this->assertTrue($response['success']);
		$this->assertSame('All plugin-owned cache flushed.', $response['data']['message']);

		// 6. Invalidate tag
		$_POST = array(
			'nonce' => $action_nonce,
			'tag'   => 'post_tag',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_invalidate_tag'));
		$this->assertTrue($response['success']);
		$this->assertSame('post_tag', $response['data']['tag']);
		$this->assertSame(4, $response['data']['new_version']);

		// 7. Invalidate domain
		$_POST = array(
			'nonce'  => $action_nonce,
			'domain' => 'templates',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_invalidate_domain'));
		$this->assertTrue($response['success']);
		$this->assertSame('templates', $response['data']['domain']);
		$this->assertCount(2, $response['data']['bumped_tags']);

		// 8. Maintenance actions
		$_POST = array(
			'nonce'              => $action_nonce,
			'maintenance_action' => 'run_all',
		);
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_maintenance'));
		$this->assertTrue($response['success']);
		$this->assertSame('Maintenance complete.', $response['data']['message']);

		$_POST['maintenance_action'] = 'rebuild_index';
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_maintenance'));
		$this->assertTrue($response['success']);
		$this->assertSame(15, $response['data']['inserted']);

		$_POST['maintenance_action'] = 'export_diagnostics';
		$_REQUEST = $_POST;
		$response = $this->capture_ajax(array($ctrl, 'ajax_maintenance'));
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('diagnostics', $response['data']);
	}
}
