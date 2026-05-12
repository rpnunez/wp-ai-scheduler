<?php
/**
 * Tests for explainability payload assembly and redaction.
 *
 * @package AI_Post_Scheduler
 */

class Test_Explainability_Payload extends WP_UnitTestCase {

	/**
	 * Controller under test.
	 *
	 * @var AIPS_Generated_Posts_Controller
	 */
	private $controller;

	public function setUp(): void {
		parent::setUp();
		$this->controller = new AIPS_Generated_Posts_Controller();
	}

	/**
	 * Ensure sensitive keys and token-like values are redacted recursively.
	 */
	public function test_redact_sensitive_data_redacts_sensitive_keys() {
		$method = new ReflectionMethod($this->controller, 'redact_sensitive_data');
		$method->setAccessible(true);

		$input = array(
			'api_key' => 'abc123',
			'nested' => array(
				'token' => 'token-value',
				'notes' => 'Bearer supersecrettokenvalue1234567890',
			),
			'safe' => 'visible text',
		);
		$redaction_count = 0;

		$result = $method->invokeArgs($this->controller, array($input, &$redaction_count, ''));

		$this->assertSame('[REDACTED]', $result['api_key']);
		$this->assertSame('[REDACTED]', $result['nested']['token']);
		$this->assertSame('[REDACTED]', $result['nested']['notes']);
		$this->assertSame('visible text', $result['safe']);
		$this->assertGreaterThan(0, $redaction_count);
	}

	/**
	 * Ensure explainability payload includes the expected top-level schema sections.
	 */
	public function test_build_explainability_payload_includes_expected_sections() {
		$method = new ReflectionMethod($this->controller, 'build_explainability_payload');
		$method->setAccessible(true);

		$history_item = (object) array(
			'id' => 10,
			'uuid' => 'run-123',
			'status' => 'completed',
			'post_id' => 200,
			'created_at' => 1778407200,
			'completed_at' => 1778407290,
			'creation_method' => 'scheduled',
			'template_id' => 4,
			'author_id' => 0,
			'topic_id' => 0,
			'generated_title' => 'Sample',
			'error_message' => '',
		);

		$entries = array(
			array(
				'type_id' => AIPS_History_Type::AI_REQUEST,
				'log_type' => 'content_prompt',
				'timestamp' => 1778407210,
				'details' => array(
					'message' => 'Prompt assembled',
					'context' => array(
						'component' => 'content',
					),
				),
			),
			array(
				'type_id' => AIPS_History_Type::LOG,
				'log_type' => 'source_used',
				'timestamp' => 1778407215,
				'details' => array(
					'used' => true,
					'url' => 'https://example.com/article',
					'title' => 'Example Source',
				),
			),
			array(
				'type_id' => AIPS_History_Type::WARNING,
				'log_type' => 'validation_check',
				'timestamp' => 1778407250,
				'details' => array(
					'check_name' => 'length_check',
					'status' => 'warning',
					'message' => 'Length near lower bound',
				),
			),
		);

		$ai_calls = array(
			array(
				'type' => 'content',
				'label' => 'Content',
				'request' => array(
					'input' => array(
						'prompt' => 'Write content for topic.',
						'options' => array(
							'request_type' => 'content',
							'context' => 'Site context details: example.',
						),
					),
					'context' => array(
						'component' => 'content',
						'context_type' => 'template',
						'context_id' => 4,
						'template_id' => 4,
						'creation_method' => 'scheduled',
						'token_budget' => array(
							'request_type' => 'content',
							'prompt_tokens_estimate' => 10,
							'output_tokens' => 100,
							'max_tokens' => 200,
							'was_clamped' => false,
						),
						'ai_variables' => array(
							'names' => array('productcategory'),
							'values' => array('productcategory' => 'Widgets'),
						),
					),
					'api_key' => 'secret',
				),
				'response' => array(
					'output' => 'Generated post body',
				),
			),
		);

		$component_revisions = array(
			'content' => array(
				array(
					'timestamp' => 1778407260,
					'value' => 'Updated content',
				),
			),
		);

		$result = $method->invokeArgs($this->controller, array($history_item, $entries, $ai_calls, $component_revisions));

		$this->assertIsArray($result);
		$this->assertSame(AIPS_Explainability_Builder::SCHEMA_VERSION, $result['schema_version']);
		$this->assertArrayHasKey('generation', $result);
		$this->assertArrayHasKey('trigger', $result);
		$this->assertArrayHasKey('context_snapshot', $result);
		$this->assertArrayHasKey('prompt_components', $result);
		$this->assertArrayHasKey('sources', $result);
		$this->assertArrayHasKey('model_runs', $result);
		$this->assertArrayHasKey('validation_checks', $result);
		$this->assertArrayHasKey('transformations', $result);
		$this->assertArrayHasKey('attempts', $result);
		$this->assertArrayHasKey('final_outcome', $result);
		$this->assertArrayHasKey('redactions', $result);
		$this->assertArrayHasKey('warnings', $result);
		$this->assertArrayHasKey('timeline', $result);
		$this->assertArrayHasKey('why', $result);
		$this->assertArrayHasKey('summary', $result['why']);
		$this->assertNotEmpty($result['why']['summary']);

		$this->assertNotEmpty($result['sources']['used']);
		$this->assertGreaterThan(0, $result['redactions']['count']);

		$this->assertNotEmpty($result['validation_checks']);
		$this->assertArrayHasKey('severity', $result['validation_checks'][0]);
		$this->assertArrayHasKey('stage', $result['validation_checks'][0]);
		$this->assertArrayHasKey('suggested_fix', $result['validation_checks'][0]);

		$this->assertNotEmpty($result['prompt_components']);
		$this->assertArrayHasKey('context', $result['prompt_components'][0]);
		$this->assertArrayHasKey('token_budget', $result['prompt_components'][0]);
		$this->assertArrayHasKey('site_context', $result['prompt_components'][0]);
	}
}
