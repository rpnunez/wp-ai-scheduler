<?php
/**
 * Tests for AIPS_WP_AI_Client_Provider readiness and capabilities.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

if (!function_exists('wp_ai_client_prompt')) {
    function wp_ai_client_prompt($prompt) {
        global $aips_wp_ai_client_test_builder;

        if ($aips_wp_ai_client_test_builder instanceof WP_Error) {
            return $aips_wp_ai_client_test_builder;
        }

        if (is_object($aips_wp_ai_client_test_builder) && method_exists($aips_wp_ai_client_test_builder, 'set_prompt')) {
            $aips_wp_ai_client_test_builder->set_prompt($prompt);
        }

        return $aips_wp_ai_client_test_builder;
    }
}

/**
 * Override local connector readiness in provider unit tests.
 *
 * @param bool $configured Detected connector readiness.
 * @return bool
 */
if (!function_exists('aips_test_wp_ai_client_connector_configured')) {
    function aips_test_wp_ai_client_connector_configured($configured) {
        global $aips_wp_ai_client_test_configured;

        return isset($aips_wp_ai_client_test_configured)
            ? (bool) $aips_wp_ai_client_test_configured
            : $configured;
    }
}

/**
 * Supply connector definitions for routing tests.
 *
 * @param array $connectors Registered connector definitions.
 * @return array
 */
if (!function_exists('aips_test_wp_ai_client_connectors')) {
	function aips_test_wp_ai_client_connectors($connectors) {
		global $aips_wp_ai_client_test_connectors;

		return isset($aips_wp_ai_client_test_connectors) ? $aips_wp_ai_client_test_connectors : $connectors;
	}
}

/**
 * Inject the test prompt builder on every supported WordPress version.
 *
 * @param mixed  $builder Existing override.
 * @param string $prompt  Prompt text.
 * @return mixed
 */
if (!function_exists('aips_test_wp_ai_client_prompt_builder')) {
	function aips_test_wp_ai_client_prompt_builder($builder, $prompt) {
		global $aips_wp_ai_client_test_builder;

		return isset($aips_wp_ai_client_test_builder) ? $aips_wp_ai_client_test_builder : $builder;
	}
}

class AIPS_Test_WP_AI_Client_Builder {
    public $text_supported = true;
    public $image_supported = true;
    public $text_response = '{"fallback":true}';
    public $image_response = 'data:image/png;base64,abc';
    public $prompt = '';

    // Captured configuration for asserting canonical-parameter mapping.
    public $captured_model_preferences = null;
    public $captured_temperature = null;
    public $captured_max_tokens = null;
    public $captured_json_schema = null;
    public $captured_system_instruction = null;
    public $captured_history = null;
    public $with_history_call_count = 0;
	public $captured_provider = null;
	public $attempted_providers = array();
	public $provider_text_responses = array();

    public function set_prompt($prompt) {
        $this->prompt = $prompt;
        return $this;
    }

    public function is_supported_for_text_generation() {
        return $this->text_supported;
    }

    public function is_supported_for_image_generation() {
        return $this->image_supported;
    }

    public function using_model_preference(...$models) {
        $this->captured_model_preferences = $models;
        return $this;
    }

	public function using_provider($provider_id) {
		$this->captured_provider = $provider_id;
		return $this;
	}

    public function using_temperature($temperature) {
        $this->captured_temperature = $temperature;
        return $this;
    }

    public function using_max_tokens($max_tokens) {
        $this->captured_max_tokens = $max_tokens;
        return $this;
    }

    public function as_json_response($schema) {
        $this->captured_json_schema = $schema;
        return $this;
    }

    public function using_system_instruction($instruction) {
        $this->captured_system_instruction = $instruction;
        return $this;
    }

    public function with_history(...$messages) {
        $this->with_history_call_count++;
        $this->captured_history = $messages;
        return $this;
    }

    public function generate_text() {
		if ($this->captured_provider !== null) {
			$this->attempted_providers[] = $this->captured_provider;
			if (array_key_exists($this->captured_provider, $this->provider_text_responses)) {
				return $this->provider_text_responses[$this->captured_provider];
			}
		}

        return $this->text_response;
    }

    public function generate_image() {
        return $this->image_response;
    }
}

/**
 * Builder whose chainable configuration methods return WP_Error, mimicking the
 * real core builder's __call converting SDK exceptions to WP_Error mid-chain.
 */
class AIPS_Test_WP_AI_Client_Builder_Erroring_Chain extends AIPS_Test_WP_AI_Client_Builder {
    public $failing_method = 'using_temperature';
    public $chain_error_code = 'provider_down';

    public function using_temperature($temperature) {
        if ($this->failing_method === 'using_temperature') {
            return new WP_Error($this->chain_error_code, 'boom');
        }
        return parent::using_temperature($temperature);
    }

    public function as_json_response($schema) {
        if ($this->failing_method === 'as_json_response') {
            return new WP_Error($this->chain_error_code, 'boom');
        }
        return parent::as_json_response($schema);
    }
}

/**
 * File-object stand-in for the AI Client's generated-image result.
 */
class AIPS_Test_WP_AI_Client_Image_File {
    private $data_uri;

    public function __construct($data_uri) {
        $this->data_uri = $data_uri;
    }

    public function getDataUri() {
        return $this->data_uri;
    }
}

class AIPS_Test_WP_AI_Client_Builder_Without_JSON {
    public $text_supported = true;
    public $text_response = '{"fallback":true}';

    public function is_supported_for_text_generation() {
        return $this->text_supported;
    }

    public function is_supported_for_image_generation() {
        return true;
    }

    public function generate_text() {
        return $this->text_response;
    }

    public function generate_image() {
        return 'data:image/png;base64,abc';
    }
}

class Test_AIPS_WP_AI_Client_Provider extends WP_UnitTestCase {

    public function setUp(): void {
		global $aips_wp_ai_client_test_configured, $aips_wp_ai_client_test_connectors;

        parent::setUp();
        $aips_wp_ai_client_test_configured = true;
		$aips_wp_ai_client_test_connectors = null;
        add_filter('aips_wp_ai_client_has_configured_connector', 'aips_test_wp_ai_client_connector_configured');
		add_filter('aips_wp_ai_client_connectors', 'aips_test_wp_ai_client_connectors');
		add_filter('aips_wp_ai_client_prompt_builder', 'aips_test_wp_ai_client_prompt_builder', 10, 2);
        AIPS_AI_Provider_Factory::reset_cache();
    }

    public function tearDown(): void {
		global $aips_wp_ai_client_test_builder, $aips_wp_ai_client_test_configured, $aips_wp_ai_client_test_connectors, $mwai;

        $aips_wp_ai_client_test_builder = null;
        $aips_wp_ai_client_test_configured = null;
		$aips_wp_ai_client_test_connectors = null;
        $mwai = null;
        remove_filter('aips_wp_ai_client_has_configured_connector', 'aips_test_wp_ai_client_connector_configured');
		remove_filter('aips_wp_ai_client_connectors', 'aips_test_wp_ai_client_connectors');
		remove_filter('aips_wp_ai_client_prompt_builder', 'aips_test_wp_ai_client_prompt_builder', 10);
        delete_option('aips_ai_provider');
		$config = AIPS_Config::get_instance();
		$config->set_option('aips_wp_ai_connector_mode', 'all');
		$config->set_option('aips_wp_ai_connector_ids', array());
		$config->set_option('aips_wp_ai_connector_failover', true);
		foreach (array('google', 'openai', 'anthropic') as $connector_id) {
			delete_transient('aips_wp_ai_health_' . md5($connector_id));
		}
        AIPS_AI_Provider_Factory::reset_cache();
        parent::tearDown();
    }

    public function test_is_available_does_not_require_live_text_generation_probe() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->text_supported = false;
        $aips_wp_ai_client_test_builder = $builder;

        $provider = new AIPS_WP_AI_Client_Provider();

        $this->assertTrue($provider->is_available());
        $this->assertSame('', $provider->get_unavailable_reason());
    }

    public function test_is_available_returns_false_when_no_connector_is_configured() {
        global $aips_wp_ai_client_test_builder, $aips_wp_ai_client_test_configured;

        $aips_wp_ai_client_test_builder = new WP_Error('no_connector', 'No connector configured.');
        $aips_wp_ai_client_test_configured = false;

        $provider = new AIPS_WP_AI_Client_Provider();

        $this->assertFalse($provider->is_available());
    }

    public function test_factory_autodetect_selects_configured_wp_ai_client_when_live_probe_is_offline() {
        global $aips_wp_ai_client_test_builder, $mwai;

        $mwai = null;
        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->text_supported = false;
        $aips_wp_ai_client_test_builder = $builder;

        $provider = AIPS_AI_Provider_Factory::create();

        $this->assertInstanceOf('AIPS_WP_AI_Client_Provider', $provider);
        $this->assertArrayHasKey('wp_ai_client', AIPS_AI_Provider_Factory::available_providers());
    }

    public function test_factory_available_providers_includes_wp_ai_client_when_text_ready() {
        global $aips_wp_ai_client_test_builder, $mwai;

        $mwai = null;
        $aips_wp_ai_client_test_builder = new AIPS_Test_WP_AI_Client_Builder();

        $available = AIPS_AI_Provider_Factory::available_providers();

        $this->assertArrayHasKey('wp_ai_client', $available);
    }

    public function test_supports_native_json_requires_json_api_without_live_probe() {
        global $aips_wp_ai_client_test_builder;

        $aips_wp_ai_client_test_builder = new AIPS_Test_WP_AI_Client_Builder();
        $provider = new AIPS_WP_AI_Client_Provider();
        $this->assertTrue($provider->supports_native_json());

        $aips_wp_ai_client_test_builder = new AIPS_Test_WP_AI_Client_Builder_Without_JSON();
        $provider = new AIPS_WP_AI_Client_Provider();
        $this->assertFalse($provider->supports_native_json());

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->text_supported = false;
        $aips_wp_ai_client_test_builder = $builder;
        $provider = new AIPS_WP_AI_Client_Provider();
        $this->assertTrue($provider->supports_native_json());
    }

    public function test_generate_image_surfaces_client_capability_error() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->image_supported = false;
        $builder->image_response = new WP_Error('prompt_builder_error', 'No image-capable model was found.');
        $aips_wp_ai_client_test_builder = $builder;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('prompt_builder_error');

        (new AIPS_WP_AI_Client_Provider())->generate_image('Image prompt', array());
    }

    public function test_generate_text_surfaces_network_error_instead_of_unsupported_error() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->text_supported = false;
        $builder->text_response = new WP_Error('prompt_network_error', 'Could not reach the connector.');
        $aips_wp_ai_client_test_builder = $builder;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('prompt_network_error');

        (new AIPS_WP_AI_Client_Provider())->generate_text('Prompt', array());
    }

    public function test_service_falls_back_directly_when_wp_provider_native_json_unsupported() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder_Without_JSON();
        $builder->text_response = '{"fallback":true}';
        $aips_wp_ai_client_test_builder = $builder;

        $service = new AIPS_AI_Service(null, null, null, new AIPS_WP_AI_Client_Provider());
        $result = $service->generate_json('Return JSON');

        $this->assertIsArray($result);
        $this->assertTrue($result['fallback']);
    }

    public function test_generate_text_forwards_temperature_max_tokens_and_model_preferences() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->text_response = 'Generated text';
        $aips_wp_ai_client_test_builder = $builder;

        $result = (new AIPS_WP_AI_Client_Provider())->generate_text('Prompt', array(
            'model'       => 'model-a, model-b',
            'temperature' => 0.4,
            'max_tokens'  => 512,
        ));

        $this->assertSame('Generated text', $result);
        $this->assertSame(array('model-a', 'model-b'), $builder->captured_model_preferences);
        $this->assertSame(0.4, $builder->captured_temperature);
        $this->assertSame(512, $builder->captured_max_tokens);
    }

    public function test_generate_image_forwards_model_preferences() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $aips_wp_ai_client_test_builder = $builder;

        (new AIPS_WP_AI_Client_Provider())->generate_image('Image prompt', array(
            'model' => 'image-model, image-fallback',
        ));

        $this->assertSame(array('image-model', 'image-fallback'), $builder->captured_model_preferences);
    }

	public function test_selected_connectors_are_attempted_in_saved_order_with_failover() {
		global $aips_wp_ai_client_test_builder, $aips_wp_ai_client_test_connectors;

		$aips_wp_ai_client_test_connectors = $this->get_routing_test_connectors();
		$config = AIPS_Config::get_instance();
		$config->set_option('aips_wp_ai_connector_mode', 'selected');
		$config->set_option('aips_wp_ai_connector_ids', array('google', 'openai'));
		$config->set_option('aips_wp_ai_connector_failover', true);

		$builder = new AIPS_Test_WP_AI_Client_Builder();
		$builder->provider_text_responses = array(
			'google' => new WP_Error('prompt_network_error', 'Google is temporarily unavailable.'),
			'openai' => 'Generated by OpenAI',
		);
		$aips_wp_ai_client_test_builder = $builder;

		$result = (new AIPS_WP_AI_Client_Provider())->generate_text('Prompt', array());

		$this->assertSame('Generated by OpenAI', $result);
		$this->assertSame(array('google', 'openai'), $builder->attempted_providers);
		$this->assertNotContains('anthropic', $builder->attempted_providers);
	}

	public function test_content_policy_failure_does_not_try_another_connector() {
		global $aips_wp_ai_client_test_builder, $aips_wp_ai_client_test_connectors;

		$aips_wp_ai_client_test_connectors = $this->get_routing_test_connectors();
		$config = AIPS_Config::get_instance();
		$config->set_option('aips_wp_ai_connector_mode', 'selected');
		$config->set_option('aips_wp_ai_connector_ids', array('google', 'openai'));
		$config->set_option('aips_wp_ai_connector_failover', true);

		$builder = new AIPS_Test_WP_AI_Client_Builder();
		$builder->provider_text_responses = array(
			'google' => new WP_Error('content_policy_violation', 'Prompt rejected.'),
			'openai' => 'Should not be used',
		);
		$aips_wp_ai_client_test_builder = $builder;

		try {
			(new AIPS_WP_AI_Client_Provider())->generate_text('Prompt', array());
			$this->fail('Expected the policy error to be surfaced.');
		} catch (Exception $e) {
			$this->assertStringStartsWith('content_policy_violation:', $e->getMessage());
		}

		$this->assertSame(array('google'), $builder->attempted_providers);
	}

	public function test_disabled_failover_does_not_try_the_next_connector() {
		global $aips_wp_ai_client_test_builder, $aips_wp_ai_client_test_connectors;

		$aips_wp_ai_client_test_connectors = $this->get_routing_test_connectors();
		$config = AIPS_Config::get_instance();
		$config->set_option('aips_wp_ai_connector_mode', 'selected');
		$config->set_option('aips_wp_ai_connector_ids', array('google', 'openai'));
		$config->set_option('aips_wp_ai_connector_failover', false);

		$builder = new AIPS_Test_WP_AI_Client_Builder();
		$builder->provider_text_responses = array(
			'google' => new WP_Error('prompt_network_error', 'Unavailable.'),
			'openai' => 'Should not be used',
		);
		$aips_wp_ai_client_test_builder = $builder;

		try {
			(new AIPS_WP_AI_Client_Provider())->generate_text('Prompt', array());
			$this->fail('Expected the first connector error to be surfaced.');
		} catch (Exception $e) {
			$this->assertStringStartsWith('prompt_network_error:', $e->getMessage());
		}

		$this->assertSame(array('google'), $builder->attempted_providers);
	}

	public function test_all_mode_uses_every_configured_connector_without_saved_selection() {
		global $aips_wp_ai_client_test_builder, $aips_wp_ai_client_test_connectors;

		$aips_wp_ai_client_test_connectors = $this->get_routing_test_connectors();
		$config = AIPS_Config::get_instance();
		$config->set_option('aips_wp_ai_connector_mode', 'all');
		$config->set_option('aips_wp_ai_connector_ids', array('anthropic'));
		$config->set_option('aips_wp_ai_connector_failover', true);

		$builder = new AIPS_Test_WP_AI_Client_Builder();
		$builder->provider_text_responses = array(
			'google'    => new WP_Error('prompt_network_error', 'Unavailable.'),
			'openai'    => 'Generated by OpenAI',
			'anthropic' => 'Generated by Anthropic',
		);
		$aips_wp_ai_client_test_builder = $builder;

		$result = (new AIPS_WP_AI_Client_Provider())->generate_text('Prompt', array());

		$this->assertSame('Generated by OpenAI', $result);
		$this->assertSame(array('google', 'openai'), $builder->attempted_providers);
	}

	/**
	 * Connector registry entries used by routing tests.
	 *
	 * @return array
	 */
	private function get_routing_test_connectors(): array {
		$connector = static function($name) {
			return array(
				'name'           => $name,
				'type'           => 'ai_provider',
				'authentication' => array('method' => 'none'),
			);
		};

		return array(
			'google'    => $connector('Google'),
			'openai'    => $connector('OpenAI'),
			'anthropic' => $connector('Anthropic'),
		);
	}

    public function test_generate_text_forwards_context_and_instructions_as_system_instruction() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $aips_wp_ai_client_test_builder = $builder;

        (new AIPS_WP_AI_Client_Provider())->generate_text('Prompt', array(
            'context'      => 'Voice guidance.',
            'instructions' => 'Output HTML only.',
        ));

        $this->assertSame("Voice guidance.\n\nOutput HTML only.", $builder->captured_system_instruction);
    }

    public function test_generate_text_omits_system_instruction_when_context_absent() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $aips_wp_ai_client_test_builder = $builder;

        (new AIPS_WP_AI_Client_Provider())->generate_text('Prompt', array('context' => '   '));

        $this->assertNull($builder->captured_system_instruction);
    }

    public function test_generate_text_forwards_conversation_history_once_in_order() {
        global $aips_wp_ai_client_test_builder;

        if (!class_exists('WordPress\\AiClient\\Messages\\DTO\\UserMessage')) {
            $this->markTestSkipped('WordPress AI Client message DTOs are not available.');
        }

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $aips_wp_ai_client_test_builder = $builder;

        $conversation = new AIPS_AI_Conversation();
        $conversation->add_exchange('Write the article.', 'The article body.');

        (new AIPS_WP_AI_Client_Provider())->generate_text('Now the title.', array(
            'messages' => $conversation->to_array(),
        ));

        // withHistory() prepends, so it must be called exactly once with the whole
        // transcript rather than once per turn.
        $this->assertSame(1, $builder->with_history_call_count);
        $this->assertCount(2, $builder->captured_history);
        $this->assertTrue($builder->captured_history[0]->getRole()->isUser());
        $this->assertFalse($builder->captured_history[1]->getRole()->isUser());
    }

    public function test_generate_text_skips_history_when_message_dtos_missing() {
        global $aips_wp_ai_client_test_builder;

        if (class_exists('WordPress\\AiClient\\Messages\\DTO\\UserMessage')) {
            $this->markTestSkipped('Message DTOs are present; the missing-DTO path cannot be exercised.');
        }

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $aips_wp_ai_client_test_builder = $builder;

        $result = (new AIPS_WP_AI_Client_Provider())->generate_text('Now the title.', array(
            'messages' => array(
                array('role' => 'user', 'text' => 'Write the article.'),
                array('role' => 'model', 'text' => 'The article body.'),
            ),
        ));

        $this->assertSame(0, $builder->with_history_call_count);
        $this->assertNotEmpty($result);
        $this->assertFalse((new AIPS_WP_AI_Client_Provider())->supports_conversation());
    }

    public function test_generate_json_forwards_schema_to_as_json_response() {
        global $aips_wp_ai_client_test_builder;

        $schema = array(
            'type'       => 'object',
            'properties' => array('topic' => array('type' => 'string')),
        );

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->text_response = '{"topic":"Example"}';
        $aips_wp_ai_client_test_builder = $builder;

        $result = (new AIPS_WP_AI_Client_Provider())->generate_json('Prompt', array(
            'json_schema' => $schema,
        ));

        $this->assertSame($schema, $builder->captured_json_schema);
        $this->assertSame(array('topic' => 'Example'), $result);
    }

    public function test_mid_chain_wp_error_throws_exception_with_code_prefix() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder_Erroring_Chain();
        $aips_wp_ai_client_test_builder = $builder;

        $provider = new AIPS_WP_AI_Client_Provider();

        try {
            $provider->generate_text('Prompt', array('temperature' => 0.5));
            $this->fail('Expected an exception for a mid-chain WP_Error.');
        } catch (Exception $e) {
            $this->assertStringStartsWith('provider_down: ', $e->getMessage());
            $this->assertSame('provider_down', $provider->extract_error_code($e->getMessage()));
        }
    }

    public function test_generate_json_mid_chain_wp_error_throws_not_null() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder_Erroring_Chain();
        $builder->failing_method = 'as_json_response';
        $aips_wp_ai_client_test_builder = $builder;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('provider_down');

        (new AIPS_WP_AI_Client_Provider())->generate_json('Prompt', array(
            'json_schema' => array('type' => 'object'),
        ));
    }

    public function test_extract_error_code_recovers_prefixed_code() {
        $provider = new AIPS_WP_AI_Client_Provider();

        $this->assertSame('no_connector', $provider->extract_error_code('no_connector: nothing configured'));
    }

    public function test_generate_image_unwraps_file_object_data_uri() {
        global $aips_wp_ai_client_test_builder;

        $data_uri = 'data:image/png;base64,dGVzdA==';
        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->image_response = new AIPS_Test_WP_AI_Client_Image_File($data_uri);
        $aips_wp_ai_client_test_builder = $builder;

        $result = (new AIPS_WP_AI_Client_Provider())->generate_image('Prompt', array());

        $this->assertSame($data_uri, $result);
    }

    public function test_probe_call_passes_empty_string_not_null_to_wp_ai_client_prompt() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $aips_wp_ai_client_test_builder = $builder;

        $provider = new AIPS_WP_AI_Client_Provider();
        $provider->supports_text_generation(); // Triggers create_prompt_builder().

        // The test stub captures the argument via set_prompt(). A null would cause
        // a PHP 8 TypeError on any non-nullable string parameter in the real function.
        $this->assertSame('', $builder->prompt, 'Probe must pass empty string, not null.');
    }

    public function test_unavailable_null_is_cached_and_not_re_probed_on_subsequent_calls() {
        global $aips_wp_ai_client_test_builder;

        // First probe: builder creation fails → null stored in WeakMap.
        $aips_wp_ai_client_test_builder = new WP_Error('no_connector', 'No connector.');
        $provider = new AIPS_WP_AI_Client_Provider();
        $this->assertFalse($provider->supports_text_generation());

        // Swap to a working builder. Without the offsetExists fix (i.e. using isset),
        // the null would not be seen as a cache hit and the provider would re-probe,
        // find the new builder, and incorrectly return true.
        $aips_wp_ai_client_test_builder = new AIPS_Test_WP_AI_Client_Builder();
        $this->assertFalse($provider->supports_text_generation(), 'Cached null must be honoured; provider must not re-probe.');
    }
}
