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

    public function generate_text(string $prompt, array $params) {
        $this->last_prompt = $prompt;
        $this->last_params = $params;
        if ($this->text_throw !== null) {
            throw new Exception($this->text_throw);
        }
        return $this->text_return;
    }

    public function generate_json(?string $prompt, array $params) {
        $this->last_prompt = $prompt;
        $this->last_params = $params;
        return $this->json_return;
    }

    public function generate_image(string $prompt, array $params) {
        $this->last_prompt = $prompt;
        $this->last_params = $params;
        return $this->image_return;
    }

    public function generate_embedding(string $text, array $params) {
        $this->last_prompt = $text;
        $this->last_params = $params;
        return $this->embedding_return;
    }

    public function supports_native_json(): bool { return $this->native_json; }
    public function supports_embeddings(): bool { return $this->embeddings; }

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
}
