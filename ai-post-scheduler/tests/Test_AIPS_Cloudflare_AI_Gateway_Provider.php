<?php
/**
 * Tests for AIPS_Cloudflare_AI_Gateway_Provider.
 *
 * The provider is exercised end-to-end through the real HTTP client with the
 * network stubbed via pre_http_request, so the built chat payload is asserted
 * from the captured request body.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Cloudflare_AI_Gateway_Provider extends WP_UnitTestCase {

	const ACCOUNT_ID = '0123456789abcdef0123456789abcdef';
	const TOKEN      = 'cfut_TestToken-123';

	/**
	 * @var array|null
	 */
	private $last_request = null;

	/**
	 * @var array|WP_Error
	 */
	private $next_response;

	public function setUp(): void {
		parent::setUp();

		update_option('aips_cf_account_id', self::ACCOUNT_ID);
		update_option('aips_cf_api_token', AIPS_Secret_Encryption::encrypt(self::TOKEN));
		update_option('aips_cf_gateway_id', 'default');
		update_option('aips_cf_text_model', 'google/gemini-3-flash');
		AIPS_Config::get_instance()->flush_option_cache();

		$this->last_request  = null;
		$this->next_response = $this->http_ok(array('choices' => array(array('message' => array('content' => 'ok')))));

		add_filter('pre_http_request', array($this, 'intercept_http'), 10, 3);
	}

	public function tearDown(): void {
		remove_filter('pre_http_request', array($this, 'intercept_http'), 10);

		foreach (array('aips_cf_account_id', 'aips_cf_api_token', 'aips_cf_gateway_id', 'aips_cf_text_model', 'aips_ai_provider') as $key) {
			delete_option($key);
		}
		AIPS_Config::get_instance()->flush_option_cache();
		AIPS_AI_Provider_Factory::reset_cache();

		parent::tearDown();
	}

	public function intercept_http($preempt, $args, $url) {
		$this->last_request = array('url' => $url, 'args' => $args);
		return $this->next_response;
	}

	private function http_ok(array $body, int $status = 200): array {
		return array(
			'response' => array('code' => $status, 'message' => 'OK'),
			'headers'  => array('content-type' => 'application/json'),
			'body'     => wp_json_encode($body),
		);
	}

	private function http_error(int $status, int $cf_code, string $message): array {
		return array(
			'response' => array('code' => $status, 'message' => ''),
			'headers'  => array('content-type' => 'application/json'),
			'body'     => wp_json_encode(array('success' => false, 'errors' => array(array('code' => $cf_code, 'message' => $message)), 'result' => null)),
		);
	}

	private function provider(): AIPS_Cloudflare_AI_Gateway_Provider {
		return new AIPS_Cloudflare_AI_Gateway_Provider();
	}

	private function sent_body(): array {
		return json_decode((string) $this->last_request['args']['body'], true);
	}

	// ------------------------------------------------------------------
	// Identity / availability
	// ------------------------------------------------------------------

	public function test_identity() {
		$provider = $this->provider();
		$this->assertSame('cloudflare_ai_gateway', $provider->get_id());
		$this->assertNotEmpty($provider->get_label());
		$this->assertTrue($provider->supports_conversation());
		$this->assertFalse($provider->supports_native_json());
		$this->assertFalse($provider->supports_embeddings());
	}

	public function test_is_available_when_configured_without_network() {
		$this->assertTrue($this->provider()->is_available());
		$this->assertSame('', $this->provider()->get_unavailable_reason());
		$this->assertNull($this->last_request, 'Availability must not call the network');
	}

	public function test_unavailable_when_text_model_missing() {
		delete_option('aips_cf_text_model');
		AIPS_Config::get_instance()->flush_option_cache();

		$provider = $this->provider();
		$this->assertFalse($provider->is_available());
		$this->assertStringContainsString('text model', strtolower($provider->get_unavailable_reason()));
	}

	// ------------------------------------------------------------------
	// Factory registration
	// ------------------------------------------------------------------

	public function test_registered_in_factory() {
		AIPS_AI_Provider_Factory::reset_cache();
		$all = AIPS_AI_Provider_Factory::all_providers();
		$this->assertArrayHasKey('cloudflare_ai_gateway', $all);
	}

	public function test_factory_creates_provider_for_explicit_id() {
		AIPS_AI_Provider_Factory::reset_cache();
		$provider = AIPS_AI_Provider_Factory::create('cloudflare_ai_gateway');
		$this->assertInstanceOf(AIPS_Cloudflare_AI_Gateway_Provider::class, $provider);
	}

	public function test_factory_autodetect_does_not_jump_cloudflare_ahead_of_earlier_providers() {
		// Cloudflare is configured and available, but registered last. Auto-detect
		// picks the first available backend in REGISTRY order (meow, wp_ai_client,
		// then cloudflare), so it must never pick cloudflare while an earlier
		// provider is available.
		AIPS_AI_Provider_Factory::reset_cache();
		delete_option('aips_ai_provider');
		AIPS_Config::get_instance()->flush_option_cache();

		$meow_available   = (new AIPS_Meow_AI_Provider())->is_available();
		$wpaic_available  = (new AIPS_WP_AI_Client_Provider())->is_available();

		$provider = AIPS_AI_Provider_Factory::create();

		if (!$meow_available && !$wpaic_available) {
			$this->assertSame('cloudflare_ai_gateway', $provider->get_id());
		} else {
			$this->assertNotSame('cloudflare_ai_gateway', $provider->get_id());
		}
	}

	// ------------------------------------------------------------------
	// Text generation + payload shape
	// ------------------------------------------------------------------

	public function test_generate_text_returns_content() {
		$this->next_response = $this->http_ok(array('choices' => array(array('message' => array('content' => 'Hello world')))));

		$text = $this->provider()->generate_text('Say hello', array());

		$this->assertSame('Hello world', $text);
		$this->assertSame(
			'https://api.cloudflare.com/client/v4/accounts/' . self::ACCOUNT_ID . '/ai/v1/chat/completions',
			$this->last_request['url']
		);
		$this->assertSame('true', $this->last_request['args']['headers']['cf-aig-skip-cache']);
	}

	public function test_generate_text_uses_configured_model_and_single_user_message() {
		$this->provider()->generate_text('What is tea?', array());

		$body = $this->sent_body();
		$this->assertSame('google/gemini-3-flash', $body['model']);
		$this->assertCount(1, $body['messages']);
		$this->assertSame('user', $body['messages'][0]['role']);
		$this->assertSame('What is tea?', $body['messages'][0]['content']);
	}

	public function test_explicit_model_param_overrides_config() {
		$this->provider()->generate_text('hi', array('model' => 'openai/gpt-4.1'));
		$this->assertSame('openai/gpt-4.1', $this->sent_body()['model']);
	}

	public function test_context_and_instructions_become_a_system_message() {
		$this->provider()->generate_text('Write it', array('context' => 'Brand voice.', 'instructions' => 'Be concise.'));

		$body = $this->sent_body();
		$this->assertSame('system', $body['messages'][0]['role']);
		$this->assertSame("Brand voice.\n\nBe concise.", $body['messages'][0]['content']);
		$this->assertSame('user', $body['messages'][1]['role']);
	}

	public function test_conversation_history_is_mapped_to_openai_roles() {
		$params = array(
			'messages' => array(
				array('role' => AIPS_AI_Conversation::ROLE_USER, 'text' => 'First question'),
				array('role' => AIPS_AI_Conversation::ROLE_MODEL, 'text' => 'First answer'),
			),
		);

		$this->provider()->generate_text('Follow up', $params);

		$body = $this->sent_body();
		$this->assertSame(
			array('user', 'assistant', 'user'),
			array_column($body['messages'], 'role')
		);
		$this->assertSame('First answer', $body['messages'][1]['content']);
		$this->assertSame('Follow up', $body['messages'][2]['content']);
	}

	public function test_malformed_history_turn_is_rejected() {
		$this->expectException(Exception::class);
		$this->expectExceptionMessageMatches('/invalid_request_error/');
		$this->provider()->generate_text('x', array('messages' => array(array('role' => 'user', 'text' => '   '))));
	}

	public function test_max_tokens_and_temperature_are_forwarded() {
		$this->provider()->generate_text('hi', array('max_tokens' => 256, 'temperature' => 0.3));

		$body = $this->sent_body();
		$this->assertSame(256, $body['max_tokens']);
		$this->assertSame(0.3, $body['temperature']);
	}

	public function test_content_as_parts_array_is_concatenated() {
		$this->next_response = $this->http_ok(array('choices' => array(array('message' => array('content' => array(
			array('type' => 'text', 'text' => 'Part 1 '),
			array('type' => 'text', 'text' => 'Part 2'),
		))))));

		$this->assertSame('Part 1 Part 2', $this->provider()->generate_text('hi', array()));
	}

	public function test_empty_content_throws_service_error() {
		$this->next_response = $this->http_ok(array('choices' => array(array('message' => array('content' => '')))));

		try {
			$this->provider()->generate_text('hi', array());
			$this->fail('Expected exception');
		} catch (Exception $e) {
			$this->assertSame('cf_service_error', $this->provider()->extract_error_code($e->getMessage()));
		}
	}

	// ------------------------------------------------------------------
	// JSON / image / embedding behaviour for this slice
	// ------------------------------------------------------------------

	public function test_generate_json_returns_null_for_text_fallback() {
		$this->assertNull($this->provider()->generate_json('prompt', array()));
		$this->assertNull($this->last_request, 'generate_json must not call the network yet');
	}

	public function test_generate_image_reports_capability_error() {
		try {
			$this->provider()->generate_image('a cat', array());
			$this->fail('Expected exception');
		} catch (Exception $e) {
			$this->assertSame('image_generation_not_supported', $this->provider()->extract_error_code($e->getMessage()));
		}
	}

	public function test_generate_embedding_reports_capability_error() {
		try {
			$this->provider()->generate_embedding('text', array());
			$this->fail('Expected exception');
		} catch (Exception $e) {
			$this->assertSame('embeddings_not_supported', $this->provider()->extract_error_code($e->getMessage()));
		}
	}

	// ------------------------------------------------------------------
	// Error propagation
	// ------------------------------------------------------------------

	public function test_api_error_is_rethrown_with_recoverable_code() {
		$this->next_response = $this->http_error(401, 10000, 'Authentication error');

		try {
			$this->provider()->generate_text('hi', array());
			$this->fail('Expected exception');
		} catch (Exception $e) {
			$this->assertSame('invalid_api_key', $this->provider()->extract_error_code($e->getMessage()));
			$this->assertStringContainsString('Authentication error', $e->getMessage());
		}
	}

	public function test_extract_error_code_falls_back_to_message_patterns() {
		$provider = $this->provider();
		// No "code:" prefix: classified by the resilience message patterns.
		$this->assertSame('insufficient_quota', $provider->extract_error_code('You have exceeded your quota for today.'));
	}

	public function test_rate_limit_error_maps_to_retryable_code() {
		$this->next_response = $this->http_error(429, 2018, 'Rate limited');

		try {
			$this->provider()->generate_text('hi', array());
			$this->fail('Expected exception');
		} catch (Exception $e) {
			$this->assertSame('rate_limit_exceeded', $this->provider()->extract_error_code($e->getMessage()));
		}
	}
}
