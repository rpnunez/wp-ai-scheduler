<?php
/**
 * Tests for AIPS_Integration_Manager
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Integration_Manager extends WP_UnitTestCase {

	/** @var AIPS_Integration_Mappings_Repository */
	private $repo;

	/** @var AIPS_Test_Stub_AI_Service */
	private $ai_service;

	/** @var AIPS_Integration_Manager */
	private $manager;

	/** @var array Captured payload from the last 'aips_integration_fields_applied' action. */
	private $last_applied = null;

	public function setUp(): void {
		parent::setUp();

		$this->repo = new AIPS_Integration_Mappings_Repository();
		$this->ai_service = new AIPS_Test_Stub_AI_Service();
		$this->manager = new AIPS_Integration_Manager($this->repo, $this->ai_service);

		add_filter('aips_integrations_registry', array($this, 'register_stub_adapter'));

		$this->last_applied = null;
		add_action('aips_integration_fields_applied', array($this, 'capture_applied'), 10, 3);

		AIPS_Test_Stub_Manager_Integration::$fields_by_group = array();
	}

	public function tearDown(): void {
		remove_filter('aips_integrations_registry', array($this, 'register_stub_adapter'));
		remove_action('aips_integration_fields_applied', array($this, 'capture_applied'), 10);
		parent::tearDown();
	}

	public function register_stub_adapter($map) {
		$map['stub'] = 'AIPS_Test_Stub_Manager_Integration';
		return $map;
	}

	public function capture_applied($post_id, $integration_id, $results) {
		$this->last_applied = array('post_id' => $post_id, 'integration_id' => $integration_id, 'results' => $results);
	}

	private function make_context($template_id) {
		$template = new stdClass();
		$template->id = $template_id;
		return new AIPS_Template_Context($template);
	}

	public function test_ignores_non_template_contexts() {
		$context = $this->getMockBuilder(AIPS_Generation_Context::class)->getMock();
		$context->method('get_type')->willReturn('topic');

		$this->manager->handle_post_generated(1, null, null, $context);

		$this->assertSame(0, AIPS_Test_Stub_Manager_Integration::$write_calls);
	}

	public function test_no_op_when_no_mappings_saved() {
		$context = $this->make_context(999999);
		$this->manager->handle_post_generated(1, null, null, $context);
		$this->assertSame(0, AIPS_Test_Stub_Manager_Integration::$write_calls);
	}

	public function test_batch_call_generates_and_writes_all_fields_in_one_ai_call() {
		AIPS_Test_Stub_Manager_Integration::$write_calls = 0;
		AIPS_Test_Stub_Manager_Integration::$last_write = null;

		$template_id = 42;
		$this->repo->save_mapping(array(
			'template_id'    => $template_id,
			'integration_id' => 'stub',
			'source_key'     => 'group_1',
			'field_key'      => 'field_headline',
			'field_label'    => 'Headline',
			'field_type'     => 'text',
			'custom_prompt'  => 'Write a headline.',
			'is_active'      => true,
		));
		$this->repo->save_mapping(array(
			'template_id'    => $template_id,
			'integration_id' => 'stub',
			'source_key'     => 'group_1',
			'field_key'      => 'field_summary',
			'field_label'    => 'Summary',
			'field_type'     => 'textarea',
			'custom_prompt'  => 'Write a summary.',
			'is_active'      => true,
		));

		$this->ai_service->next_json_response = array(
			'field_headline' => 'Generated Headline',
			'field_summary'  => 'Generated Summary',
		);

		$context = $this->make_context($template_id);
		$this->manager->handle_post_generated(555, null, null, $context);

		// One AI call for both fields, not one per field.
		$this->assertSame(1, $this->ai_service->json_call_count);
		$this->assertSame(0, $this->ai_service->text_call_count);
		$this->assertSame(2, AIPS_Test_Stub_Manager_Integration::$write_calls);
		$this->assertTrue($this->last_applied['results']['field_headline']);
		$this->assertTrue($this->last_applied['results']['field_summary']);
	}

	public function test_falls_back_to_per_field_calls_when_batch_call_fails() {
		AIPS_Test_Stub_Manager_Integration::$write_calls = 0;
		AIPS_Test_Stub_Manager_Integration::$last_write = null;

		$template_id = 46;
		$this->repo->save_mapping(array(
			'template_id'    => $template_id,
			'integration_id' => 'stub',
			'source_key'     => 'group_1',
			'field_key'      => 'field_headline',
			'field_type'     => 'text',
			'custom_prompt'  => 'Write a headline.',
			'is_active'      => true,
		));

		$this->ai_service->next_json_response = new WP_Error('ai_unavailable', 'Batch call failed');
		$this->ai_service->next_response = 'Fallback Headline';

		$context = $this->make_context($template_id);
		$this->manager->handle_post_generated(558, null, null, $context);

		$this->assertSame(1, $this->ai_service->json_call_count);
		$this->assertSame(1, $this->ai_service->text_call_count);
		$this->assertSame(1, AIPS_Test_Stub_Manager_Integration::$write_calls);
		$this->assertSame(array(558, 'field_headline', 'Fallback Headline'), AIPS_Test_Stub_Manager_Integration::$last_write);
	}

	public function test_skips_field_missing_from_batch_response() {
		AIPS_Test_Stub_Manager_Integration::$write_calls = 0;

		$template_id = 47;
		$this->repo->save_mapping(array(
			'template_id'    => $template_id,
			'integration_id' => 'stub',
			'source_key'     => 'group_1',
			'field_key'      => 'field_headline',
			'field_type'     => 'text',
			'is_active'      => true,
		));

		$this->ai_service->next_json_response = array(); // response missing the field entirely

		$context = $this->make_context($template_id);
		$this->manager->handle_post_generated(559, null, null, $context);

		$this->assertSame(0, AIPS_Test_Stub_Manager_Integration::$write_calls);
		$this->assertInstanceOf('WP_Error', $this->last_applied['results']['field_headline']);
		$this->assertSame('missing_batch_value', $this->last_applied['results']['field_headline']->get_error_code());
	}

	public function test_uses_live_field_instructions_when_custom_prompt_is_empty() {
		$template_id = 48;
		$this->repo->save_mapping(array(
			'template_id'    => $template_id,
			'integration_id' => 'stub',
			'source_key'     => 'group_1',
			'field_key'      => 'field_headline',
			'field_type'     => 'text',
			'custom_prompt'  => '', // no override — should fall back to the field's live ACF instructions.
			'is_active'      => true,
		));

		AIPS_Test_Stub_Manager_Integration::$fields_by_group['group_1'] = array(
			array('key' => 'field_headline', 'label' => 'Headline', 'native_type' => 'text', 'instructions' => 'Keep it under 8 words.'),
		);

		$this->ai_service->next_json_response = array('field_headline' => 'A Short Headline');

		$context = $this->make_context($template_id);
		$this->manager->handle_post_generated(560, null, null, $context);

		$this->assertStringContainsString('Keep it under 8 words.', $this->ai_service->last_json_prompt);
	}

	public function test_skips_inactive_mapping() {
		AIPS_Test_Stub_Manager_Integration::$write_calls = 0;

		$template_id = 43;
		$this->repo->save_mapping(array(
			'template_id'    => $template_id,
			'integration_id' => 'stub',
			'source_key'     => 'group_1',
			'field_key'      => 'field_inactive',
			'field_type'     => 'text',
			'is_active'      => false,
		));

		$context = $this->make_context($template_id);
		$this->manager->handle_post_generated(556, null, null, $context);

		$this->assertSame(0, AIPS_Test_Stub_Manager_Integration::$write_calls);
	}

	public function test_skips_unsupported_shape() {
		AIPS_Test_Stub_Manager_Integration::$write_calls = 0;

		$template_id = 44;
		$this->repo->save_mapping(array(
			'template_id'    => $template_id,
			'integration_id' => 'stub',
			'source_key'     => 'group_1',
			'field_key'      => 'field_repeater',
			'field_type'     => 'repeater', // maps to SHAPE_STRUCTURED_LIST — not yet generatable.
			'is_active'      => true,
		));

		$context = $this->make_context($template_id);
		$this->manager->handle_post_generated(557, null, null, $context);

		$this->assertSame(0, AIPS_Test_Stub_Manager_Integration::$write_calls);
	}

	public function test_skips_choice_shape_pending_option_aware_generation() {
		AIPS_Test_Stub_Manager_Integration::$write_calls = 0;

		$template_id = 49;
		$this->repo->save_mapping(array(
			'template_id'    => $template_id,
			'integration_id' => 'stub',
			'source_key'     => 'group_1',
			'field_key'      => 'field_select',
			'field_type'     => 'select', // maps to SHAPE_CHOICE — deferred, not generatable.
			'is_active'      => true,
		));

		$context = $this->make_context($template_id);
		$this->manager->handle_post_generated(561, null, null, $context);

		$this->assertSame(0, AIPS_Test_Stub_Manager_Integration::$write_calls);
		$this->assertInstanceOf('WP_Error', $this->last_applied['results']['field_select']);
	}

	public function test_handle_template_deleted_removes_mappings() {
		$template_id = 45;
		$this->repo->save_mapping(array(
			'template_id'    => $template_id,
			'integration_id' => 'stub',
			'source_key'     => 'group_1',
			'field_key'      => 'field_headline',
			'field_type'     => 'text',
			'is_active'      => true,
		));

		$this->assertCount(1, $this->repo->get_by_template($template_id, false));

		$this->manager->handle_template_deleted(array('action' => 'deleted', 'template_id' => $template_id));

		$this->assertCount(0, $this->repo->get_by_template($template_id, false));
	}
}

if (!class_exists('AIPS_Test_Stub_AI_Service', false)) {
	class AIPS_Test_Stub_AI_Service implements AIPS_AI_Service_Interface {
		public $next_response = 'stub response';
		public $next_json_response = array();
		public $last_json_prompt = null;
		public $json_call_count = 0;
		public $text_call_count = 0;

		public function is_available() {
			return true;
		}
		public function generate_text($prompt, $options = array()) {
			$this->text_call_count++;
			return $this->next_response;
		}
		public function generate_json($prompt, $options = array()) {
			$this->json_call_count++;
			$this->last_json_prompt = $prompt;
			return $this->next_json_response;
		}
		public function generate_image($prompt, $options = array()) {
			return '';
		}
		public function generate_embedding($text, $options = array()) {
			return new WP_Error('embedding_not_expected', 'Embedding generation should not be called.');
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
}

if (!class_exists('AIPS_Test_Stub_Manager_Integration', false)) {
	class AIPS_Test_Stub_Manager_Integration implements AIPS_Integration_Interface {
		public static $write_calls = 0;
		public static $last_write = null;
		public static $fields_by_group = array();

		public function get_id() {
			return 'stub';
		}
		public function get_label() {
			return 'Stub';
		}
		public function is_available() {
			return true;
		}
		public function get_field_groups($post_type = null) {
			return array();
		}
		public function get_fields($group_id, $args = array()) {
			return isset(self::$fields_by_group[$group_id]) ? self::$fields_by_group[$group_id] : array();
		}
		public function get_supported_field_types() {
			return array(
				'text'     => AIPS_Integration_Interface::SHAPE_SHORT_TEXT,
				'textarea' => AIPS_Integration_Interface::SHAPE_LONG_TEXT,
				'select'   => AIPS_Integration_Interface::SHAPE_CHOICE,
				'repeater' => AIPS_Integration_Interface::SHAPE_STRUCTURED_LIST,
			);
		}
		public function write_field_value($post_id, $field_key, $value) {
			self::$write_calls++;
			self::$last_write = array($post_id, $field_key, $value);
			return true;
		}
		public function supports_custom_field_keys() {
			return false;
		}
		public function validate_field_key($field_key) {
			return true;
		}
	}
}
