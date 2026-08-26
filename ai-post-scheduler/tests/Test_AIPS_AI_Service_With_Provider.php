<?php
/**
 * Tests AIPS_AI_Service orchestration against an injected stub provider.
 *
 * This exercises the provider-agnostic behavior (canonical params, native-JSON
 * vs text fallback, embeddings, error classification) without depending on any
 * real AI backend.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

/**
 * Configurable stub implementing the low-level provider contract.
 */
class AIPS_Stub_AI_Provider implements AIPS_AI_Provider_Interface {

    public $available = true;
    public $native_json = true;
    public $embeddings = true;
    public $conversation = true;

    /** @var array Captured params from the last call. */
    public $last_params = array();
    public $last_prompt = null;

    /** @var mixed Value returned by generate_text. */
    public $text_return = 'stub text';
    /** @var mixed Value returned by generate_json (null triggers fallback). */
    public $json_return = array('ok' => true);
    public $image_return = 'https://example.com/image.png';
    public $embedding_return = array(0.1, 0.2, 0.3);

    /** @var string|null When set, generate_text throws with this message. */
    public $text_throw = null;

    public function get_id(): string { return 'stub'; }
    public function get_label(): string { return 'Stub'; }
    public function is_available(): bool { return $this->available; }

    public function get_unavailable_reason(): string { return $this->available ? '' : 'Stub provider disabled.'; }

    public function generate_text(string $prompt, array $params): string {
        $this->last_prompt = $prompt;
        $this->last_params = $params;
        if ($this->text_throw !== null) {
            throw new Exception($this->text_throw);
        }
        return $this->text_return;
    }

    public function generate_json(?string $prompt, array $params): ?array {
        $this->last_prompt = $prompt;
        $this->last_params = $params;
        return $this->json_return;
    }

    public function generate_image(string $prompt, array $params): string {
        $this->last_prompt = $prompt;
        $this->last_params = $params;
        return $this->image_return;
    }

    public function generate_embedding(string $text, array $params): array {
        $this->last_prompt = $text;
        $this->last_params = $params;
        return $this->embedding_return;
    }

    public function supports_native_json(): bool { return $this->native_json; }
    public function supports_embeddings(): bool { return $this->embeddings; }
    public function supports_conversation(): bool { return $this->conversation; }

    public function extract_error_code(string $message): string {
        return strpos($message, 'invalid_api_key') !== false ? 'invalid_api_key' : '';
    }
}

class Test_AIPS_AI_Service_With_Provider extends WP_UnitTestCase {

    private function make_service(AIPS_Stub_AI_Provider $provider) {
        return new AIPS_AI_Service(null, null, null, $provider);
    }

    public function test_generate_text_delegates_to_provider() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->text_return = 'hello world';
        $service = $this->make_service($stub);

        $result = $service->generate_text('Prompt');

        $this->assertSame('hello world', $result);
        // Canonical params are passed through to the provider.
        $this->assertArrayHasKey('max_tokens', $stub->last_params);
        $this->assertIsInt($stub->last_params['max_tokens']);
    }

    public function test_generate_text_classifies_provider_error_code() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->text_throw = 'invalid_api_key: bad key';
        $service = $this->make_service($stub);

        $result = $service->generate_text('Prompt');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('invalid_api_key', $result->get_error_code());
    }

    public function test_generate_text_unavailable_provider_returns_error() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->available = false;
        $service = $this->make_service($stub);

        $result = $service->generate_text('Prompt');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('ai_unavailable', $result->get_error_code());
    }

    public function test_generate_json_native_path() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->json_return = array('items' => array(1, 2, 3));
        $service = $this->make_service($stub);

        $result = $service->generate_json('Prompt');

        $this->assertIsArray($result);
        $this->assertSame(array(1, 2, 3), $result['items']);
    }

    public function test_generate_json_falls_back_to_text_when_native_unsupported() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->native_json = false;
        // The text fallback parses JSON out of the text response.
        $stub->text_return = 'Here is your data: {"a": 1, "b": 2} thanks';
        $service = $this->make_service($stub);

        $result = $service->generate_json('Prompt');

        $this->assertIsArray($result);
        $this->assertSame(1, $result['a']);
        $this->assertSame(2, $result['b']);
    }

    public function test_generate_json_falls_back_when_provider_returns_null() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->native_json = true;
        $stub->json_return = null; // provider requests fallback at call time
        $stub->text_return = '{"x": 42}';
        $service = $this->make_service($stub);

        $result = $service->generate_json('Prompt');

        $this->assertIsArray($result);
        $this->assertSame(42, $result['x']);
    }

    public function test_generate_image_delegates_to_provider() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->image_return = 'https://cdn.example/x.png';
        $service = $this->make_service($stub);

        $result = $service->generate_image('A cat');

        $this->assertSame('https://cdn.example/x.png', $result);
    }

    public function test_generate_image_applies_configured_image_model_when_not_overridden() {
        $config = AIPS_Config::get_instance();
        $config->set_option('aips_ai_image_model', 'gemini-image-model, image-fallback');
        $stub = new AIPS_Stub_AI_Provider();
        $service = $this->make_service($stub);

        $service->generate_image('A cat');

        $this->assertSame('gemini-image-model, image-fallback', $stub->last_params['model']);
        $config->set_option('aips_ai_image_model', '');
    }

    public function test_generate_image_option_model_overrides_configured_image_model() {
        $config = AIPS_Config::get_instance();
        $config->set_option('aips_ai_image_model', 'configured-image-model');
        $stub = new AIPS_Stub_AI_Provider();
        $service = $this->make_service($stub);

        $service->generate_image('A cat', array('model' => 'override-image-model'));

        $this->assertSame('override-image-model', $stub->last_params['model']);
        $config->set_option('aips_ai_image_model', '');
    }

    public function test_generate_text_uses_request_type_model_override() {
        $config = AIPS_Config::get_instance();
        $config->set_option('aips_ai_model', 'global-text-model');
        $config->set_option('aips_ai_model_title', 'title-model, title-fallback');
        $stub = new AIPS_Stub_AI_Provider();
        $service = $this->make_service($stub);

        $service->generate_text('Title prompt', array('request_type' => 'title'));

        $this->assertSame('title-model, title-fallback', $stub->last_params['model']);
        $config->set_option('aips_ai_model', '');
        $config->set_option('aips_ai_model_title', '');
    }

    public function test_template_routing_override_takes_precedence_for_text() {
        $config = AIPS_Config::get_instance();
        $config->set_option('aips_ai_model', 'global-text-model');
        $config->set_option('aips_ai_routing_profiles', array(
            'budget' => array('model' => 'profile-text-model'),
        ));

        try {
            $stub = new AIPS_Stub_AI_Provider();
            $service = $this->make_service($stub);

            $service->generate_text('Title prompt', array(
                'request_type' => 'title',
                'routing_policy' => array(
                    'profile' => 'budget',
                    'overrides' => array('title_model' => 'template-title-model'),
                ),
            ));

            $this->assertSame('template-title-model', $stub->last_params['model']);
        } finally {
            $config->set_option('aips_ai_model', '');
            $config->set_option('aips_ai_routing_profiles', array());
        }
    }

    public function test_template_routing_profile_applies_to_images() {
        $config = AIPS_Config::get_instance();
        $config->set_option('aips_ai_routing_profiles', array(
            'image_quality' => array('image_model' => 'profile-image-model'),
        ));

        try {
            $stub = new AIPS_Stub_AI_Provider();
            $service = $this->make_service($stub);

            $service->generate_image('A cat', array(
                'routing_policy' => array('profile' => 'image_quality'),
            ));

            $this->assertSame('profile-image-model', $stub->last_params['model']);
        } finally {
            $config->set_option('aips_ai_routing_profiles', array());
        }
    }

    public function test_template_routing_profile_passes_connector_to_provider() {
        $config = AIPS_Config::get_instance();
        $config->set_option('aips_ai_routing_profiles', array(
            'google_budget' => array('connector' => 'google', 'model' => 'gemini-3.1-flash-lite'),
        ));

        try {
            $stub = new AIPS_Stub_AI_Provider();
            $service = $this->make_service($stub);

            $service->generate_text('Prompt', array(
                'routing_policy' => array('profile' => 'google_budget'),
            ));

            $this->assertSame('google', $stub->last_params['connector_id']);
        } finally {
            $config->set_option('aips_ai_routing_profiles', array());
        }
    }

    public function test_call_log_contains_resolved_model_and_provider() {
        $stub = new AIPS_Stub_AI_Provider();
        $service = $this->make_service($stub);

        $service->generate_text('Prompt', array('model' => 'explicit-model'));

        $call_log = $service->get_call_log();
        $this->assertNotEmpty($call_log);
        $this->assertSame('explicit-model', $call_log[0]['request']['options']['resolved_model']);
        $this->assertSame('stub', $call_log[0]['request']['options']['resolved_provider']);
    }

    public function test_call_log_contains_estimated_usage_statistics() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->text_return = 'response text';
        $service = $this->make_service($stub);

        $service->generate_text('A prompt with enough text to estimate usage.');

        $call_log = $service->get_call_log();
        $this->assertTrue($call_log[0]['usage']['estimated']);
        $this->assertGreaterThan(0, $call_log[0]['usage']['prompt_tokens']);
        $this->assertGreaterThan(0, $call_log[0]['usage']['completion_tokens']);
        $this->assertSame($call_log[0]['usage']['prompt_tokens'] + $call_log[0]['usage']['completion_tokens'], $call_log[0]['usage']['total_tokens']);
        $this->assertSame($call_log[0]['usage']['total_tokens'], $service->get_call_statistics()['estimated_usage']['total_tokens']);
    }

    public function test_provider_reported_usage_and_configured_pricing_are_recorded() {
        $config = AIPS_Config::get_instance();
        $previous_pricing = $config->get_option('aips_ai_model_pricing');
        $config->set_option('aips_ai_model_pricing', array(
            'priced-model' => array('input' => 2.00, 'output' => 8.00),
        ));
        $usage_filter = function($usage, $type, $prompt, $response, $options, $provider) {
            return array('prompt_tokens' => 1000, 'completion_tokens' => 250);
        };
        add_filter('aips_ai_call_usage', $usage_filter, 10, 6);

        try {
            $stub = new AIPS_Stub_AI_Provider();
            $service = $this->make_service($stub);
            $service->generate_text('Prompt', array('model' => 'priced-model'));

            $usage = $service->get_call_log()[0]['usage'];
            $this->assertFalse($usage['estimated']);
            $this->assertSame(1000, $usage['prompt_tokens']);
            $this->assertSame(250, $usage['completion_tokens']);
            $this->assertSame(0.004, $usage['estimated_cost_usd']);
            $this->assertSame(0.004, $service->get_call_statistics()['estimated_usage']['estimated_cost_usd']);
        } finally {
            remove_filter('aips_ai_call_usage', $usage_filter, 10);
            $config->set_option('aips_ai_model_pricing', $previous_pricing);
        }
    }

    public function test_fallback_setting_is_forwarded_to_provider() {
        $stub = new AIPS_Stub_AI_Provider();
        $service = $this->make_service($stub);

        $service->generate_text('Prompt', array(
            'model' => 'primary-model,backup-model',
            'routing_fallback_enabled' => false,
        ));

        $this->assertFalse($stub->last_params['routing_fallback_enabled']);
    }

    public function test_routing_profile_can_disable_fallback() {
        $config = AIPS_Config::get_instance();
        $previous_profiles = $config->get_option('aips_ai_routing_profiles');
        $config->set_option('aips_ai_routing_profiles', array(
            'single_model' => array(
                'model' => 'primary-model,backup-model',
                'fallback_enabled' => false,
            ),
        ));

        try {
            $stub = new AIPS_Stub_AI_Provider();
            $service = $this->make_service($stub);
            $service->generate_text('Prompt', array(
                'routing_policy' => array('profile' => 'single_model'),
            ));

            $this->assertSame('primary-model,backup-model', $stub->last_params['model']);
            $this->assertFalse($stub->last_params['routing_fallback_enabled']);
        } finally {
            $config->set_option('aips_ai_routing_profiles', $previous_profiles);
        }
    }

    public function test_strict_model_validation_rejects_unknown_catalog_model() {
        $config = AIPS_Config::get_instance();
        $config->set_option('aips_ai_model_validation', 'strict');
        delete_transient('aips_ai_model_catalog_text');
        $catalog_filter = function($models, $capability) {
            return array(array('id' => 'known-model', 'provider' => 'google', 'provider_label' => 'Google', 'label' => 'Known model', 'capability' => $capability));
        };
        add_filter('aips_ai_model_catalog', $catalog_filter, 10, 2);

        try {
            $result = AIPS_AI_Model_Validator::validate('unknown-model', 'text', 'google');

            $this->assertFalse($result['valid']);
            $this->assertStringContainsString('not found', $result['message']);
        } finally {
            remove_filter('aips_ai_model_catalog', $catalog_filter, 10);
            delete_transient('aips_ai_model_catalog_text');
            $config->set_option('aips_ai_model_validation', 'warn');
        }
    }

    public function test_generate_embedding_delegates_to_provider() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->embedding_return = array(0.5, 0.6);
        $service = $this->make_service($stub);

        $result = $service->generate_embedding('text');

        $this->assertSame(array(0.5, 0.6), $result);
    }

    public function test_generate_embedding_unsupported_returns_error() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->embeddings = false;
        $service = $this->make_service($stub);

        $result = $service->generate_embedding('text');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('embeddings_not_supported', $result->get_error_code());
    }

    public function test_supports_embeddings_reflects_provider() {
        $stub = new AIPS_Stub_AI_Provider();
        $stub->embeddings = true;
        $this->assertTrue($this->make_service($stub)->supports_embeddings());

        $stub2 = new AIPS_Stub_AI_Provider();
        $stub2->embeddings = false;
        $this->assertFalse($this->make_service($stub2)->supports_embeddings());
    }

    public function test_env_id_setting_propagates_to_provider_params() {
        update_option('aips_ai_env_id', 'env-123');

        try {
            $stub = new AIPS_Stub_AI_Provider();
            $service = $this->make_service($stub);

            $service->generate_text('Prompt');

            $this->assertArrayHasKey('env_id', $stub->last_params);
            $this->assertSame('env-123', $stub->last_params['env_id']);
        } finally {
            delete_option('aips_ai_env_id');
        }
    }

    public function test_json_schema_option_propagates_to_provider_params() {
        $stub = new AIPS_Stub_AI_Provider();
        $service = $this->make_service($stub);
        $schema = array('type' => 'object');

        $service->generate_json('Prompt', array('json_schema' => $schema));

        $this->assertArrayHasKey('json_schema', $stub->last_params);
        $this->assertSame($schema, $stub->last_params['json_schema']);
    }
}
