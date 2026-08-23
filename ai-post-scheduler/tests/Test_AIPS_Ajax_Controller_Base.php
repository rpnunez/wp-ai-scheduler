<?php
/**
 * Tests for AIPS_Ajax_Guard and AIPS_Ajax_Controller_Base (Issue #1943).
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Concrete stub controller for testing AIPS_Ajax_Controller_Base.
 */
class AIPS_Test_Stub_Controller extends AIPS_Ajax_Controller_Base {

	protected array $actions = array(
		'aips_stub_action_one' => 'ajax_action_one',
		'aips_stub_action_two' => 'ajax_action_two',
	);

	public function ajax_action_one() {
		$this->verify_request('aips_stub_nonce', 'manage_options');
		AIPS_Ajax_Response::success(array('tested' => true));
	}

	public function ajax_action_two() {
		$this->verify_request('aips_custom_nonce', 'edit_posts');
		AIPS_Ajax_Response::success(array('custom' => true));
	}

	public function exposed_verify_request($nonce = 'aips_ajax_nonce', $capability = 'manage_options', $query_arg = 'nonce') {
		$this->verify_request($nonce, $capability, $query_arg);
	}
}

class Test_AIPS_Ajax_Controller_Base extends WP_UnitTestCase {

	private $admin_user_id;
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array(
			'role' => 'administrator',
		));

		$this->subscriber_user_id = $this->factory->user->create(array(
			'role' => 'subscriber',
		));
	}

	public function tearDown(): void {
		wp_set_current_user(0);
		$_POST = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/**
	 * Helper to capture JSON output sent by AIPS_Ajax_Response.
	 */
	private function capture_ajax_response(callable $callable): ?array {
		ob_start();
		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected in WP unit test AJAX runner.
		} catch (WPAjaxDieStopException $e) {
			// Expected when execution halts.
		}
		$output = ob_get_clean();
		if ('' === $output) {
			return null;
		}
		return json_decode($output, true);
	}

	/**
	 * Test that constructing a controller automatically registers declared actions.
	 */
	public function test_actions_registered_on_construct() {
		$controller = new AIPS_Test_Stub_Controller();

		$this->assertNotFalse(has_action('wp_ajax_aips_stub_action_one', array($controller, 'ajax_action_one')));
		$this->assertNotFalse(has_action('wp_ajax_aips_stub_action_two', array($controller, 'ajax_action_two')));
		$this->assertEquals(array('aips_stub_action_one', 'aips_stub_action_two'), $controller->get_actions());
	}

	/**
	 * Test that verify_request rejects invalid nonce with standard error shape.
	 */
	public function test_verify_request_invalid_nonce_returns_error() {
		wp_set_current_user($this->admin_user_id);
		$_POST['nonce'] = 'invalid_nonce_value';
		$_REQUEST['nonce'] = 'invalid_nonce_value';

		$controller = new AIPS_Test_Stub_Controller();

		$response = $this->capture_ajax_response(function() use ($controller) {
			$controller->exposed_verify_request('aips_ajax_nonce', 'manage_options');
		});

		$this->assertIsArray($response);
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid nonce', $response['data']['message']);
	}

	/**
	 * Test that verify_request rejects missing capability with permission_denied.
	 */
	public function test_verify_request_missing_capability_returns_permission_denied() {
		wp_set_current_user($this->subscriber_user_id);
		$valid_nonce = wp_create_nonce('aips_ajax_nonce');
		$_POST['nonce'] = $valid_nonce;
		$_REQUEST['nonce'] = $valid_nonce;

		$controller = new AIPS_Test_Stub_Controller();

		$response = $this->capture_ajax_response(function() use ($controller) {
			$controller->exposed_verify_request('aips_ajax_nonce', 'manage_options');
		});

		$this->assertIsArray($response);
		$this->assertFalse($response['success']);
		$this->assertEquals('permission_denied', $response['data']['code']);
		$this->assertStringContainsString('Permission denied', $response['data']['message']);
	}

	/**
	 * Test that verify_request succeeds for valid nonce and capability without outputting error.
	 */
	public function test_verify_request_valid_credentials_succeeds() {
		wp_set_current_user($this->admin_user_id);
		$valid_nonce = wp_create_nonce('aips_ajax_nonce');
		$_POST['nonce'] = $valid_nonce;
		$_REQUEST['nonce'] = $valid_nonce;

		$controller = new AIPS_Test_Stub_Controller();

		// Should not throw or output any error JSON
		ob_start();
		$controller->exposed_verify_request('aips_ajax_nonce', 'manage_options');
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * Test that verify_request respects custom query arg for nonce parameter.
	 */
	public function test_verify_request_custom_query_arg() {
		wp_set_current_user($this->admin_user_id);
		$valid_nonce = wp_create_nonce('aips_custom_nonce');
		$_POST['custom_token'] = $valid_nonce;
		$_REQUEST['custom_token'] = $valid_nonce;

		$controller = new AIPS_Test_Stub_Controller();

		ob_start();
		$controller->exposed_verify_request('aips_custom_nonce', 'manage_options', 'custom_token');
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * Test that verify_request with empty capability string skips capability check.
	 */
	public function test_verify_request_empty_capability_skips_check() {
		wp_set_current_user($this->subscriber_user_id);
		$valid_nonce = wp_create_nonce('aips_open_nonce');
		$_POST['nonce'] = $valid_nonce;
		$_REQUEST['nonce'] = $valid_nonce;

		$controller = new AIPS_Test_Stub_Controller();

		ob_start();
		$controller->exposed_verify_request('aips_open_nonce', '');
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * Test that migrated Phase 1 controllers inherit from AIPS_Ajax_Controller_Base.
	 */
	public function test_migrated_controllers_inherit_base() {
		$controllers = array(
			'AIPS_Post_Slices_Controller',
			'AIPS_Campaigns_Controller',
			'AIPS_Affiliate_Links_Controller',
			'AIPS_System_Status_Controller',
			'AIPS_Stress_Test_Controller',
			'AIPS_Cache_Monitor_Controller',
			'AIPS_Settings_AJAX',
		);

		foreach ($controllers as $class) {
			$this->assertTrue(
				is_subclass_of($class, 'AIPS_Ajax_Controller_Base'),
				"Controller {$class} must extend AIPS_Ajax_Controller_Base"
			);
		}
	}

	/**
	 * Test that each migrated Phase 1 controller registers its actions upon instantiation.
	 */
	public function test_migrated_controllers_register_action_hooks() {
		$controllers = array(
			new AIPS_Post_Slices_Controller(),
			new AIPS_Campaigns_Controller(),
			new AIPS_Affiliate_Links_Controller(),
			new AIPS_System_Status_Controller(),
			new AIPS_Stress_Test_Controller(),
			new AIPS_Cache_Monitor_Controller(),
			new AIPS_Settings_AJAX(),
		);

		foreach ($controllers as $controller) {
			$actions = $controller->get_actions();
			$this->assertNotEmpty($actions, get_class($controller) . ' should declare actions');

			foreach ($actions as $action) {
				$this->assertNotFalse(
					has_action('wp_ajax_' . $action),
					"wp_ajax_{$action} should be registered for " . get_class($controller)
				);
			}
		}
	}

	/**
	 * Test that AIPS_Campaigns_Controller::ajax_ai_generate_campaign() returns error (not permission_denied) on nonce miss.
	 */
	public function test_campaigns_controller_ai_generate_nonce_miss_returns_error() {
		wp_set_current_user($this->admin_user_id);
		$_POST['nonce'] = 'bad_nonce';
		$_REQUEST['nonce'] = 'bad_nonce';

		$controller = new AIPS_Campaigns_Controller();

		$response = $this->capture_ajax_response(function() use ($controller) {
			$controller->ajax_ai_generate_campaign();
		});

		$this->assertIsArray($response);
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid nonce', $response['data']['message']);
		$this->assertNotEquals('permission_denied', $response['data']['code']);
	}
}
