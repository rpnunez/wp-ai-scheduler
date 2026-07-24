<?php
/**
 * Tests for AIPS_Settings_AJAX.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Settings_Ajax extends WP_UnitTestCase {

	/**
	 * @var AIPS_Container
	 */
	private $container;

	/**
	 * @var int
	 */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->container = AIPS_Container::get_instance();
		$this->container->clear();
		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));

		$_POST = array();
		$_REQUEST = array();
	}

	public function tearDown(): void {
		$this->container->clear();
		$_POST = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Capture JSON output produced by an AJAX handler.
	 *
	 * @param callable $callable AJAX handler to invoke.
	 * @return array
	 */
	private function capture_ajax(callable $callable) {
		ob_start();

		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected after wp_send_json_*.
		}

		return json_decode(ob_get_clean(), true);
	}

	public function test_ajax_test_connection_uses_container_services_and_records_history() {
		$ai_service = new Test_AIPS_Settings_Ajax_Fake_AI_Service('Hello World');
		$history_service = new Test_AIPS_Settings_Ajax_Fake_History_Service();

		$this->container->singleton(AIPS_AI_Service_Interface::class, function() use ($ai_service) {
			return $ai_service;
		});
		$this->container->singleton(AIPS_History_Service_Interface::class, function() use ($history_service) {
			return $history_service;
		});

		wp_set_current_user($this->admin_user_id);
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];

		$controller = new AIPS_Settings_AJAX();
		$response = $this->capture_ajax(array($controller, 'ajax_test_connection'));

		$this->assertTrue($response['success']);
		$this->assertStringContainsString('Hello World', $response['data']['message']);

		$this->assertCount(1, $ai_service->calls);
		$this->assertSame('Say "Hello World" in 2 words.', $ai_service->calls[0]['prompt']);
		$this->assertSame(array('maxTokens' => 20), $ai_service->calls[0]['options']);

		$this->assertCount(1, $history_service->created);
		$this->assertSame('settings_connection_test', $history_service->created[0]['type']);
		$this->assertSame($this->admin_user_id, $history_service->created[0]['metadata']['user_id']);

		$container = $history_service->created[0]['container'];
		$this->assertSame(array('activity', 'ai_request', 'ai_response'), array_column($container->records, 'type'));
		$this->assertSame(array('status' => 'success'), $container->success_data);
	}

	public function test_ajax_test_connection_records_failure_history_for_wp_error() {
		$history_service = new Test_AIPS_Settings_Ajax_Fake_History_Service();
		$controller = new AIPS_Settings_AJAX(
			new Test_AIPS_Settings_Ajax_Fake_AI_Service(new WP_Error('ai_failed', 'Connection failed')),
			$history_service
		);

		wp_set_current_user($this->admin_user_id);
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];

		$response = $this->capture_ajax(array($controller, 'ajax_test_connection'));

		$this->assertFalse($response['success']);
		$this->assertSame('Connection failed', $response['data']['message']);

		$container = $history_service->created[0]['container'];
		$this->assertCount(1, $container->errors);
		$this->assertSame('Connection failed', $container->failure_message);
		$this->assertSame(array('error_code' => 'ai_failed'), $container->failure_data);
	}

	public function test_ajax_notifications_data_hygiene_records_summary_history() {
		$history_service = new Test_AIPS_Settings_Ajax_Fake_History_Service();
		$controller = new AIPS_Settings_AJAX(
			new Test_AIPS_Settings_Ajax_Fake_AI_Service('unused'),
			$history_service
		);

		wp_set_current_user($this->admin_user_id);
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];

		update_option('aips_review_notifications_enabled', 1);

		$response = $this->capture_ajax(array($controller, 'ajax_notifications_data_hygiene'));

		$this->assertTrue($response['success']);
		$this->assertSame(1, $response['data']['details']['removed_options']);
		$this->assertContains($response['data']['details']['rollup_scheduled'], array(0, 1));

		$this->assertCount(1, $history_service->created);
		$this->assertSame('settings_notifications_hygiene', $history_service->created[0]['type']);

		$container = $history_service->created[0]['container'];
		$record_types = array_column($container->records, 'type');
		$this->assertSame('activity', $record_types[0]);
		$this->assertSame('activity', $record_types[count($record_types) - 1]);

		if (0 === (int) $response['data']['details']['rollup_scheduled']) {
			$this->assertContains('warning', $record_types);
		}

		$this->assertSame($response['data']['details'], $container->success_data);
	}

	public function test_ajax_save_settings_sanitizes_and_updates_registered_options() {
		$settings = new AIPS_Settings();
		$settings->register_settings();

		wp_set_current_user($this->admin_user_id);
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['settings'] = array(
			'aips_ai_model' => '  GPT-5  ',
			'aips_enable_retry' => '1',
			'aips_notification_preferences' => array(
				'author_topics_generated' => 'invalid-mode',
			),
			'aips_topic_similarity_threshold' => '4.5',
		);

		$controller = new AIPS_Settings_AJAX();
		$response = $this->capture_ajax(array($controller, 'ajax_save_settings'));

		$this->assertTrue($response['success']);
		$this->assertSame('GPT-5', get_option('aips_ai_model'));
		$this->assertSame(1, (int) get_option('aips_enable_retry'));
		$this->assertSame(1.0, (float) get_option('aips_topic_similarity_threshold'));

		$stored_preferences = get_option('aips_notification_preferences');
		$registry = AIPS_Notifications::get_notification_type_registry();
		$expected_mode = isset($registry['author_topics_generated']['default_mode']) ? $registry['author_topics_generated']['default_mode'] : AIPS_Notifications::MODE_BOTH;
		$this->assertSame($expected_mode, $stored_preferences['author_topics_generated']);
	}

	public function test_ajax_save_settings_rejects_empty_or_unknown_payloads() {
		wp_set_current_user($this->admin_user_id);
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['settings'] = array(
			'not_a_real_setting' => 'value',
		);

		$controller = new AIPS_Settings_AJAX();
		$response = $this->capture_ajax(array($controller, 'ajax_save_settings'));

		$this->assertFalse($response['success']);
		$this->assertSame('invalid_request', $response['data']['code']);
	}

	public function test_ajax_save_settings_ignores_array_payload_for_scalar_option() {
		update_option('aips_ai_model', 'existing-model');

		wp_set_current_user($this->admin_user_id);
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['settings'] = array(
			'aips_ai_model' => array('bad' => 'value'),
			'aips_enable_retry' => '1',
		);

		$controller = new AIPS_Settings_AJAX();
		$response = $this->capture_ajax(array($controller, 'ajax_save_settings'));

		$this->assertTrue($response['success']);
		$this->assertSame('existing-model', get_option('aips_ai_model'));
		$this->assertNotContains('aips_ai_model', $response['data']['updated']);
		$this->assertContains('aips_enable_retry', $response['data']['updated']);
		$this->assertSame(1, (int) get_option('aips_enable_retry'));
	}
}

class Test_AIPS_Settings_Ajax_Fake_AI_Service implements AIPS_AI_Service_Interface {

	/**
	 * @var string|WP_Error
	 */
	private $result;

	/**
	 * @var array<int,array<string,mixed>>
	 */
	public $calls = array();

	/**
	 * @param string|WP_Error $result Result to return for generate_text().
	 */
	public function __construct($result) {
		$this->result = $result;
	}

	public function is_available() {
		return true;
	}

	public function generate_text($prompt, $options = array()) {
		$this->calls[] = array(
			'prompt'  => $prompt,
			'options' => $options,
		);

		return $this->result;
	}

	public function generate_json($prompt, $options = array()) {
		return array();
	}

	public function generate_image($prompt, $options = array()) {
		return '';
	}

	public function generate_embedding($text, $options = array()) {
		return array();
	}

	public function supports_embeddings() {
		return false;
	}

	public function supports_conversation() {
		return false;
	}

	public function get_call_log() {
		return array();
	}
}

class Test_AIPS_Settings_Ajax_Fake_History_Service implements AIPS_History_Service_Interface {

	/**
	 * @var array<int,array<string,mixed>>
	 */
	public $created = array();

	public function create($type, $metadata = array()) {
		$container = new Test_AIPS_Settings_Ajax_Fake_History_Container();
		$this->created[] = array(
			'type'      => $type,
			'metadata'  => $metadata,
			'container' => $container,
		);

		return $container;
	}

	public function get_activity_feed($limit = 50, $offset = 0, $filters = array()) {
		return array();
	}

	public function post_has_history_and_completed($post_id) {
		return false;
	}

	public function get_by_id($history_id) {
		return null;
	}

	public function update_history_record($history_id, $data) {
		return false;
	}

	public function find_incomplete($type, $metadata = array()) {
		return null;
	}
}

class Test_AIPS_Settings_Ajax_Fake_History_Container {

	/**
	 * @var array<int,array<string,mixed>>
	 */
	public $records = array();

	/**
	 * @var array<int,array<string,mixed>>
	 */
	public $errors = array();

	/**
	 * @var array<string,mixed>
	 */
	public $success_data = array();

	/**
	 * @var string
	 */
	public $failure_message = '';

	/**
	 * @var array<string,mixed>
	 */
	public $failure_data = array();

	public function record($type, $message, $input = null, $output = null, $context = array()) {
		$this->records[] = array(
			'type'    => $type,
			'message' => $message,
			'input'   => $input,
			'output'  => $output,
			'context' => $context,
		);

		return count($this->records);
	}

	public function record_error($message, $error_details = array(), $wp_error = null) {
		$this->errors[] = array(
			'message'       => $message,
			'error_details' => $error_details,
			'wp_error'      => $wp_error,
		);

		return count($this->errors);
	}

	public function complete_success($result_data = array()) {
		$this->success_data = $result_data;
		return true;
	}

	public function complete_failure($error_message, $error_data = array()) {
		$this->failure_message = $error_message;
		$this->failure_data = $error_data;
		return true;
	}
}
