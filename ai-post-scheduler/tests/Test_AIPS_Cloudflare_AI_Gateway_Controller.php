<?php
/**
 * Tests for AIPS_Cloudflare_AI_Gateway_Controller (the Test Connection action).
 *
 * @package AI_Post_Scheduler
 */

/**
 * Fake client so the controller test makes no network call. verify_token() and
 * chat() return canned data or throw a configured exception.
 */
class Test_AIPS_CF_Fake_Client extends AIPS_Cloudflare_AI_Gateway_Client {

	/** @var array */
	public $verify_result = array('success' => true, 'result' => array('status' => 'active'));

	/** @var Throwable|null */
	public $verify_throw = null;

	/** @var array */
	public $chat_result = array('choices' => array(array('message' => array('content' => 'OK'))));

	/** @var Throwable|null */
	public $chat_throw = null;

	// Intentionally skip the parent constructor: the overridden methods never
	// touch the parent's config.
	public function __construct() {}

	public function verify_token(): array {
		if ($this->verify_throw !== null) {
			throw $this->verify_throw;
		}
		return $this->verify_result;
	}

	public function chat(array $payload, array $options = array()): array {
		if ($this->chat_throw !== null) {
			throw $this->chat_throw;
		}
		return $this->chat_result;
	}
}

class Test_AIPS_Cloudflare_AI_Gateway_Controller extends WP_UnitTestCase {

	/** @var int */
	private $admin_user_id;

	/** @var Test_AIPS_CF_Fake_Client */
	private $client;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		$this->client        = new Test_AIPS_CF_Fake_Client();

		update_option('aips_cf_account_id', '0123456789abcdef0123456789abcdef');
		update_option('aips_cf_api_token', AIPS_Secret_Encryption::encrypt('cfut_token'));
		update_option('aips_cf_text_model', 'google/gemini-3-flash');
		AIPS_Config::get_instance()->flush_option_cache();

		$_POST = array();
		$_REQUEST = array();
	}

	public function tearDown(): void {
		foreach (array('aips_cf_account_id', 'aips_cf_api_token', 'aips_cf_text_model') as $key) {
			delete_option($key);
		}
		AIPS_Config::get_instance()->flush_option_cache();
		$_POST = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	private function controller(): AIPS_Cloudflare_AI_Gateway_Controller {
		return new AIPS_Cloudflare_AI_Gateway_Controller($this->client);
	}

	private function set_valid_nonce(): void {
		$nonce = wp_create_nonce('aips_ajax_nonce');
		$_POST['nonce']    = $nonce;
		$_REQUEST['nonce'] = $nonce;
	}

	private function capture(callable $callable): array {
		ob_start();
		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected after wp_send_json_*.
		}
		return (array) json_decode(ob_get_clean(), true);
	}

	// ------------------------------------------------------------------

	public function test_registered_in_ajax_registry() {
		$this->assertSame(
			'AIPS_Cloudflare_AI_Gateway_Controller',
			AIPS_Ajax_Registry::get_controller_for('aips_cf_gateway_test_connection')
		);
	}

	public function test_invalid_nonce_is_rejected() {
		wp_set_current_user($this->admin_user_id);
		// No nonce set.
		$response = $this->capture(array($this->controller(), 'ajax_test_connection'));

		$this->assertFalse($response['success']);
		$this->assertSame('invalid_nonce', $response['data']['code']);
	}

	public function test_non_admin_is_denied() {
		$subscriber = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber);
		$this->set_valid_nonce();

		$response = $this->capture(array($this->controller(), 'ajax_test_connection'));

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Permission denied', $response['data']['message']);
	}

	public function test_missing_credentials_report_not_configured() {
		delete_option('aips_cf_api_token');
		AIPS_Config::get_instance()->flush_option_cache();

		wp_set_current_user($this->admin_user_id);
		$this->set_valid_nonce();

		$response = $this->capture(array($this->controller(), 'ajax_test_connection'));

		$this->assertFalse($response['success']);
		$this->assertSame('not_configured', $response['data']['code']);
	}

	public function test_token_verification_failure_is_surfaced() {
		$this->client->verify_throw = new AIPS_Cloudflare_AI_Gateway_Exception('invalid_api_key', 'Authentication error', 401, 10000);

		wp_set_current_user($this->admin_user_id);
		$this->set_valid_nonce();

		$response = $this->capture(array($this->controller(), 'ajax_test_connection'));

		$this->assertFalse($response['success']);
		$this->assertSame('invalid_api_key', $response['data']['code']);
		$this->assertStringContainsString('Authentication error', $response['data']['message']);
	}

	public function test_inactive_token_is_rejected() {
		$this->client->verify_result = array('success' => true, 'result' => array('status' => 'disabled'));

		wp_set_current_user($this->admin_user_id);
		$this->set_valid_nonce();

		$response = $this->capture(array($this->controller(), 'ajax_test_connection'));

		$this->assertFalse($response['success']);
		$this->assertSame('invalid_api_key', $response['data']['code']);
	}

	public function test_verified_token_without_text_model_succeeds_with_hint() {
		delete_option('aips_cf_text_model');
		AIPS_Config::get_instance()->flush_option_cache();

		wp_set_current_user($this->admin_user_id);
		$this->set_valid_nonce();

		$response = $this->capture(array($this->controller(), 'ajax_test_connection'));

		$this->assertTrue($response['success']);
		$this->assertStringContainsString('text model', strtolower($response['data']['message']));
	}

	public function test_full_success_runs_a_completion() {
		$this->client->chat_result = array('choices' => array(array('message' => array('content' => 'OK'))));

		wp_set_current_user($this->admin_user_id);
		$this->set_valid_nonce();

		$response = $this->capture(array($this->controller(), 'ajax_test_connection'));

		$this->assertTrue($response['success']);
		$this->assertStringContainsString('Connection successful', $response['data']['message']);
		$this->assertStringContainsString('OK', $response['data']['message']);
	}

	public function test_completion_failure_is_surfaced_with_code() {
		$this->client->chat_throw = new AIPS_Cloudflare_AI_Gateway_Exception('rate_limit_exceeded', 'Rate limited', 429, 2018);

		wp_set_current_user($this->admin_user_id);
		$this->set_valid_nonce();

		$response = $this->capture(array($this->controller(), 'ajax_test_connection'));

		$this->assertFalse($response['success']);
		$this->assertSame('rate_limit_exceeded', $response['data']['code']);
	}
}
