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

class AIPS_Test_WP_AI_Client_Builder {
    public $text_supported = true;
    public $image_supported = true;
    public $text_response = '{"fallback":true}';
    public $image_response = 'data:image/png;base64,abc';
    public $prompt = '';

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
        return $this;
    }

    public function using_temperature($temperature) {
        return $this;
    }

    public function using_max_tokens($max_tokens) {
        return $this;
    }

    public function as_json_response($schema) {
        return $this;
    }

    public function generate_text() {
        return $this->text_response;
    }

    public function generate_image() {
        return $this->image_response;
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

    public function tearDown(): void {
        global $aips_wp_ai_client_test_builder, $mwai;

        $aips_wp_ai_client_test_builder = null;
        $mwai = null;
        delete_option('aips_ai_provider');
        parent::tearDown();
    }

    public function test_is_available_requires_text_generation_support() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->text_supported = false;
        $aips_wp_ai_client_test_builder = $builder;

        $provider = new AIPS_WP_AI_Client_Provider();

        $this->assertFalse($provider->is_available());
        $this->assertStringContainsString('text generation', $provider->get_unavailable_reason());
    }

    public function test_is_available_returns_false_when_builder_creation_fails() {
        global $aips_wp_ai_client_test_builder;

        $aips_wp_ai_client_test_builder = new WP_Error('no_connector', 'No connector configured.');

        $provider = new AIPS_WP_AI_Client_Provider();

        $this->assertFalse($provider->is_available());
    }

    public function test_factory_autodetect_does_not_select_wp_ai_client_without_text_support() {
        global $aips_wp_ai_client_test_builder, $mwai;

        $mwai = null;
        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->text_supported = false;
        $aips_wp_ai_client_test_builder = $builder;

        $provider = AIPS_AI_Provider_Factory::create();

        $this->assertInstanceOf('AIPS_Null_AI_Provider', $provider);
        $this->assertArrayNotHasKey('wp_ai_client', AIPS_AI_Provider_Factory::available_providers());
    }

    public function test_factory_available_providers_includes_wp_ai_client_when_text_ready() {
        global $aips_wp_ai_client_test_builder, $mwai;

        $mwai = null;
        $aips_wp_ai_client_test_builder = new AIPS_Test_WP_AI_Client_Builder();

        $available = AIPS_AI_Provider_Factory::available_providers();

        $this->assertArrayHasKey('wp_ai_client', $available);
    }

    public function test_supports_native_json_requires_json_api_and_text_support() {
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
        $this->assertFalse($provider->supports_native_json());
    }

    public function test_generate_image_throws_when_image_generation_unsupported() {
        global $aips_wp_ai_client_test_builder;

        $builder = new AIPS_Test_WP_AI_Client_Builder();
        $builder->image_supported = false;
        $aips_wp_ai_client_test_builder = $builder;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('image_generation_not_supported');

        (new AIPS_WP_AI_Client_Provider())->generate_image('Image prompt', array());
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
}
