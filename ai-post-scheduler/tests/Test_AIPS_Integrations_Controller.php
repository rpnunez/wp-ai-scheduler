<?php
/**
 * Tests for AIPS_Integrations_Controller
 *
 * Covers nonce/capability enforcement and the field-mapping CRUD happy path.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Integrations_Controller extends WP_UnitTestCase {

	/** @var AIPS_Integration_Mappings_Repository */
	private $repo;

	/** @var int Admin user ID. */
	private $admin_user_id;

	/** @var int Subscriber user ID. */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->repo = new AIPS_Integration_Mappings_Repository();
		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));

		wp_set_current_user($this->admin_user_id);
	}

	public function tearDown(): void {
		$_POST = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	private function sync_request_from_post() {
		$_REQUEST = $_POST;
	}

	private function run_ajax(callable $callable) {
		ob_start();
		try {
			$callable();
		} catch (WPAjaxDieContinueException $e) {
			// Expected: wp_send_json_* called.
		} catch (WPAjaxDieStopException $e) {
			// Expected: wp_die called (e.g. nonce failure).
		}
		$output = ob_get_clean();
		return json_decode($output, true);
	}

	public function test_get_field_mappings_rejects_missing_nonce() {
		$controller = new AIPS_Integrations_Controller($this->repo);
		$_POST = array('action' => 'aips_get_field_mappings', 'template_id' => 1);
		$this->sync_request_from_post();

		$response = $this->run_ajax(array($controller, 'ajax_get_field_mappings'));

		$this->assertNotNull($response);
		$this->assertFalse($response['success']);
	}

	public function test_get_field_mappings_rejects_non_admin() {
		wp_set_current_user($this->subscriber_user_id);
		$controller = new AIPS_Integrations_Controller($this->repo);
		$_POST = array(
			'action'      => 'aips_get_field_mappings',
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
			'template_id' => 1,
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax(array($controller, 'ajax_get_field_mappings'));

		$this->assertNotNull($response);
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('ermission', $response['data']['message']);
	}

	public function test_save_and_get_field_mappings_round_trip() {
		$controller = new AIPS_Integrations_Controller($this->repo);
		$mappings = array(
			array(
				'integration_id' => 'acf',
				'source_key'     => 'group_1',
				'field_key'      => 'field_headline',
				'field_label'    => 'Headline',
				'field_type'     => 'text',
				'custom_prompt'  => 'Write a headline.',
				'is_active'      => true,
			),
		);

		$_POST = array(
			'action'      => 'aips_save_field_mappings',
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
			'template_id' => 7,
			'mappings'    => wp_json_encode($mappings),
		);
		$this->sync_request_from_post();

		$save_response = $this->run_ajax(array($controller, 'ajax_save_field_mappings'));
		$this->assertTrue($save_response['success']);
		$this->assertCount(1, $save_response['data']['mappings']);

		$_POST = array(
			'action'      => 'aips_get_field_mappings',
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
			'template_id' => 7,
		);
		$this->sync_request_from_post();

		$get_response = $this->run_ajax(array($controller, 'ajax_get_field_mappings'));
		$this->assertTrue($get_response['success']);
		$this->assertCount(1, $get_response['data']['mappings']);
		$this->assertSame('field_headline', $get_response['data']['mappings'][0]['field_key']);
	}

	public function test_save_field_mappings_rejects_unknown_integration() {
		$controller = new AIPS_Integrations_Controller($this->repo);
		$mappings = array(
			array(
				'integration_id' => 'no_such_integration',
				'source_key'     => 'g',
				'field_key'      => 'field_x',
				'field_type'     => 'text',
				'is_active'      => true,
			),
		);

		$_POST = array(
			'action'      => 'aips_save_field_mappings',
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
			'template_id' => 21,
			'mappings'    => wp_json_encode($mappings),
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax(array($controller, 'ajax_save_field_mappings'));

		$this->assertFalse($response['success']);
		$this->assertCount(0, $this->repo->get_by_template(21, false), 'A row for an unknown integration must not be persisted.');
	}

	public function test_switching_field_group_retires_previous_group_mappings() {
		$controller = new AIPS_Integrations_Controller($this->repo);

		$group_a_mappings = array(
			array(
				'integration_id' => 'acf',
				'source_key'     => 'group_a',
				'field_key'      => 'field_a1',
				'field_type'     => 'text',
				'is_active'      => true,
			),
		);
		$_POST = array(
			'action'      => 'aips_save_field_mappings',
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
			'template_id' => 11,
			'mappings'    => wp_json_encode($group_a_mappings),
		);
		$this->sync_request_from_post();
		$this->run_ajax(array($controller, 'ajax_save_field_mappings'));

		$this->assertCount(1, $this->repo->get_by_template(11, false));

		// Switch to a different field group for the same integration and save.
		$group_b_mappings = array(
			array(
				'integration_id' => 'acf',
				'source_key'     => 'group_b',
				'field_key'      => 'field_b1',
				'field_type'     => 'text',
				'is_active'      => true,
			),
		);
		$_POST = array(
			'action'      => 'aips_save_field_mappings',
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
			'template_id' => 11,
			'mappings'    => wp_json_encode($group_b_mappings),
		);
		$this->sync_request_from_post();
		$save_response = $this->run_ajax(array($controller, 'ajax_save_field_mappings'));

		$this->assertTrue($save_response['success']);
		$mappings = $this->repo->get_by_template(11, false);
		$this->assertCount(1, $mappings, 'Group A\'s mapping should have been retired when Group B was saved.');
		$this->assertSame('field_b1', $mappings[0]->field_key);
		$this->assertSame('group_b', $mappings[0]->source_key);
	}

	public function test_save_field_mappings_allows_protected_meta_key_for_native_meta() {
		// Protected/internal keys are hidden from discovery by default but,
		// once explicitly selected via the "Show Advanced Custom Meta
		// Fields" toggle, must be saveable like any other field.
		$controller = new AIPS_Integrations_Controller($this->repo);
		$mappings = array(
			array(
				'integration_id' => 'native_meta',
				'source_key'     => 'post',
				'field_key'      => '_protected_key',
				'field_type'     => 'freeform_short_text',
				'is_active'      => true,
			),
		);

		$_POST = array(
			'action'      => 'aips_save_field_mappings',
			'nonce'       => wp_create_nonce('aips_ajax_nonce'),
			'template_id' => 12,
			'mappings'    => wp_json_encode($mappings),
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax(array($controller, 'ajax_save_field_mappings'));

		$this->assertTrue($response['success']);
		$mappings_saved = $this->repo->get_by_template(12, false);
		$this->assertCount(1, $mappings_saved);
		$this->assertSame('_protected_key', $mappings_saved[0]->field_key);
	}

	public function test_get_field_mappings_rejects_missing_template_id() {
		$controller = new AIPS_Integrations_Controller($this->repo);
		$_POST = array(
			'action' => 'aips_get_field_mappings',
			'nonce'  => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax(array($controller, 'ajax_get_field_mappings'));

		$this->assertNotNull($response);
		$this->assertFalse($response['success']);
	}

	public function test_delete_field_mapping() {
		$controller = new AIPS_Integrations_Controller($this->repo);
		$mapping_id = $this->repo->save_mapping(array(
			'template_id'    => 8,
			'integration_id' => 'acf',
			'source_key'     => 'group_1',
			'field_key'      => 'field_headline',
			'field_type'     => 'text',
			'is_active'      => true,
		));

		$_POST = array(
			'action'     => 'aips_delete_field_mapping',
			'nonce'      => wp_create_nonce('aips_ajax_nonce'),
			'mapping_id' => $mapping_id,
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax(array($controller, 'ajax_delete_field_mapping'));

		$this->assertTrue($response['success']);
		$this->assertCount(0, $this->repo->get_by_template(8, false));
	}

	public function test_get_available_integrations_returns_success_shape() {
		$controller = new AIPS_Integrations_Controller($this->repo);
		$_POST = array(
			'action' => 'aips_get_available_integrations',
			'nonce'  => wp_create_nonce('aips_ajax_nonce'),
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax(array($controller, 'ajax_get_available_integrations'));

		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('integrations', $response['data']);
		$this->assertIsArray($response['data']['integrations']);
	}

	public function test_get_integration_schema_handles_boolean_include_protected() {
		$controller = new AIPS_Integrations_Controller($this->repo);

		// Register a protected meta key
		register_post_meta('post', '_test_secret_key', array(
			'type'         => 'string',
			'description'  => 'Secret Key',
			'show_in_rest' => true,
			'single'       => true,
			'auth_callback' => '__return_true',
		));

		// Test include_protected='false' as sent by jQuery $.post
		$_POST = array(
			'action'            => 'aips_get_integration_schema',
			'nonce'             => wp_create_nonce('aips_ajax_nonce'),
			'integration_id'    => 'native_meta',
			'group_id'          => 'post',
			'include_protected' => 'false',
		);
		$this->sync_request_from_post();

		$response = $this->run_ajax(array($controller, 'ajax_get_integration_schema'));
		$this->assertTrue($response['success']);
		$keys = wp_list_pluck($response['data']['fields'], 'key');
		$this->assertNotContains('_test_secret_key', $keys, 'Protected key must not be returned when include_protected is string "false".');

		// Test include_protected='true'
		$_POST['include_protected'] = 'true';
		$this->sync_request_from_post();

		$response_adv = $this->run_ajax(array($controller, 'ajax_get_integration_schema'));
		$this->assertTrue($response_adv['success']);
		$keys_adv = wp_list_pluck($response_adv['data']['fields'], 'key');
		$this->assertContains('_test_secret_key', $keys_adv, 'Protected key must be returned when include_protected is string "true".');
	}

	public function test_save_field_mappings_removes_deleted_fields_within_same_group() {
		$controller = new AIPS_Integrations_Controller($this->repo);

		// Initially save 2 fields in native_meta / post group
		$mappings = array(
			array(
				'integration_id' => 'native_meta',
				'source_key'     => 'post',
				'field_key'      => 'field_one',
				'field_type'     => 'freeform_short_text',
				'is_active'      => true,
			),
			array(
				'integration_id' => 'native_meta',
				'source_key'     => 'post',
				'field_key'      => 'field_two',
				'field_type'     => 'freeform_short_text',
				'is_active'      => true,
			),
		);

		$_POST = array(
			'action'         => 'aips_save_field_mappings',
			'nonce'          => wp_create_nonce('aips_ajax_nonce'),
			'template_id'    => 33,
			'integration_id' => 'native_meta',
			'source_key'     => 'post',
			'mappings'       => wp_json_encode($mappings),
		);
		$this->sync_request_from_post();
		$this->run_ajax(array($controller, 'ajax_save_field_mappings'));

		$saved = $this->repo->get_by_template(33, false);
		$this->assertCount(2, $saved);

		// Now remove field_two in the UI and re-save with only field_one
		$mappings_updated = array(
			array(
				'integration_id' => 'native_meta',
				'source_key'     => 'post',
				'field_key'      => 'field_one',
				'field_type'     => 'freeform_short_text',
				'is_active'      => true,
			),
		);

		$_POST['mappings'] = wp_json_encode($mappings_updated);
		$this->sync_request_from_post();
		$res = $this->run_ajax(array($controller, 'ajax_save_field_mappings'));

		$this->assertTrue($res['success']);
		$saved_after = $this->repo->get_by_template(33, false);
		$this->assertCount(1, $saved_after, 'field_two should have been removed by group sync.');
		$this->assertSame('field_one', $saved_after[0]->field_key);
	}

	public function test_save_field_mappings_clears_all_when_empty_mappings_sent() {
		$controller = new AIPS_Integrations_Controller($this->repo);

		// Pre-populate a mapping
		$this->repo->save_mapping(array(
			'template_id'    => 44,
			'integration_id' => 'native_meta',
			'source_key'     => 'post',
			'field_key'      => 'field_to_clear',
			'field_type'     => 'freeform_short_text',
			'is_active'      => true,
		));

		$this->assertCount(1, $this->repo->get_by_template(44, false));

		// Send empty mappings with integration_id and source_key
		$_POST = array(
			'action'         => 'aips_save_field_mappings',
			'nonce'          => wp_create_nonce('aips_ajax_nonce'),
			'template_id'    => 44,
			'integration_id' => 'native_meta',
			'source_key'     => 'post',
			'mappings'       => wp_json_encode(array()),
		);
		$this->sync_request_from_post();

		$res = $this->run_ajax(array($controller, 'ajax_save_field_mappings'));
		$this->assertTrue($res['success']);
		$this->assertCount(0, $this->repo->get_by_template(44, false), 'All mappings should be cleared when empty array submitted.');
	}
}
