<?php
/**
 * Tests for AIPS_Cloudflare_AI_Gateway_Client.
 *
 * Every request is intercepted through the pre_http_request filter: no real
 * network call is made. The harness records the outgoing request so the test
 * can assert on URL and headers, and replays a configured response (inline or
 * from a tests/fixtures/cloudflare-ai-gateway/*.json fixture).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Cloudflare_AI_Gateway_Client extends WP_UnitTestCase {

	const ACCOUNT_ID = '0123456789abcdef0123456789abcdef';
	const TOKEN      = 'cfut_TestToken-123';

	/**
	 * @var array|null Last request captured by the HTTP stub: ['url' => ..., 'args' => ...].
	 */
	private $last_request = null;

	/**
	 * @var array|WP_Error Response the stub returns next.
	 */
	private $next_response;

	public function setUp(): void {
		parent::setUp();

		update_option('aips_cf_account_id', self::ACCOUNT_ID);
		update_option('aips_cf_api_token', AIPS_Secret_Encryption::encrypt(self::TOKEN));
		update_option('aips_cf_gateway_id', 'default');
		update_option('aips_cf_text_model', 'google/gemini-3-flash');
		update_option('aips_cf_log_payloads', 0);
		AIPS_Config::get_instance()->flush_option_cache();

		$this->last_request = null;
		$this->next_response = $this->http_ok(array('ok' => true));

		add_filter('pre_http_request', array($this, 'intercept_http'), 10, 3);
	}

	public function tearDown(): void {
		remove_filter('pre_http_request', array($this, 'intercept_http'), 10);

		foreach (array('aips_cf_account_id', 'aips_cf_api_token', 'aips_cf_gateway_id', 'aips_cf_text_model', 'aips_cf_log_payloads') as $key) {
			delete_option($key);
		}
		AIPS_Config::get_instance()->flush_option_cache();
		AIPS_Correlation_ID::reset();

		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// HTTP stub
	// ------------------------------------------------------------------

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

	private function http_raw(string $body, int $status, array $headers = array('content-type' => 'application/json')): array {
		return array(
			'response' => array('code' => $status, 'message' => ''),
			'headers'  => $headers,
			'body'     => $body,
		);
	}

	private function response_from_fixture(string $name): array {
		$path = __DIR__ . '/fixtures/cloudflare-ai-gateway/' . $name . '.json';
		$this->assertFileExists($path);
		$fixture = json_decode((string) file_get_contents($path), true);

		return array(
			'response' => array('code' => (int) $fixture['response']['status'], 'message' => ''),
			'headers'  => isset($fixture['response']['headers']) ? (array) $fixture['response']['headers'] : array(),
			'body'     => wp_json_encode($fixture['response']['body']),
		);
	}

	private function client(): AIPS_Cloudflare_AI_Gateway_Client {
		return new AIPS_Cloudflare_AI_Gateway_Client(new AIPS_Cloudflare_AI_Gateway_Config());
	}

	private function header(string $name): ?string {
		$headers = $this->last_request['args']['headers'] ?? array();
		return $headers[$name] ?? null;
	}

	// ------------------------------------------------------------------
	// URL and header construction
	// ------------------------------------------------------------------

	public function test_chat_targets_the_openai_compatible_endpoint() {
		$this->next_response = $this->http_ok(array('choices' => array()));

		$this->client()->chat(array('model' => 'google/gemini-3-flash', 'messages' => array()));

		$this->assertSame(
			'https://api.cloudflare.com/client/v4/accounts/' . self::ACCOUNT_ID . '/ai/v1/chat/completions',
			$this->last_request['url']
		);
		$this->assertSame('POST', $this->last_request['args']['method']);
	}

	public function test_chat_sends_the_expected_headers() {
		AIPS_Correlation_ID::set('corr-123');
		$this->client()->chat(array('model' => 'm', 'messages' => array()), array('call_type' => 'text', 'skip_cache' => true));

		$this->assertSame('Bearer ' . self::TOKEN, $this->header('Authorization'));
		$this->assertSame('application/json', $this->header('Content-Type'));
		$this->assertSame('default', $this->header('cf-aig-gateway-id'));
		$this->assertSame('1', $this->header('cf-aig-max-attempts'));
		$this->assertSame('true', $this->header('cf-aig-skip-cache'));
		$this->assertSame('false', $this->header('cf-aig-collect-log-payload'));

		$metadata = json_decode((string) $this->header('cf-aig-metadata'), true);
		$this->assertSame('corr-123', $metadata['aips_correlation_id']);
		$this->assertSame('text', $metadata['aips_call_type']);
		$this->assertSame(AIPS_VERSION, $metadata['aips_version']);
	}

	public function test_body_is_json_encoded() {
		$this->client()->chat(array('model' => 'm', 'messages' => array(array('role' => 'user', 'content' => 'hi'))));

		$sent = json_decode((string) $this->last_request['args']['body'], true);
		$this->assertSame('m', $sent['model']);
		$this->assertSame('hi', $sent['messages'][0]['content']);
	}

	public function test_log_payloads_toggle_is_reflected() {
		update_option('aips_cf_log_payloads', 1);
		AIPS_Config::get_instance()->flush_option_cache();

		$this->client()->chat(array('model' => 'm', 'messages' => array()));

		$this->assertSame('true', $this->header('cf-aig-collect-log-payload'));
	}

	public function test_cache_ttl_is_sent_when_not_skipping_cache() {
		$this->client()->run('@cf/baai/bge-m3', array('text' => array('x')), array('cache_ttl' => 600));

		$this->assertSame('600', $this->header('cf-aig-cache-ttl'));
		$this->assertNull($this->header('cf-aig-skip-cache'));
	}

	public function test_skip_cache_wins_over_cache_ttl() {
		$this->client()->run('@cf/baai/bge-m3', array('text' => array('x')), array('cache_ttl' => 600, 'skip_cache' => true));

		$this->assertSame('true', $this->header('cf-aig-skip-cache'));
		$this->assertNull($this->header('cf-aig-cache-ttl'));
	}

	public function test_metadata_omits_correlation_id_when_unset() {
		AIPS_Correlation_ID::reset();
		$this->client()->chat(array('model' => 'm', 'messages' => array()), array('call_type' => 'json'));

		$metadata = json_decode((string) $this->header('cf-aig-metadata'), true);
		$this->assertArrayNotHasKey('aips_correlation_id', $metadata);
		$this->assertSame('json', $metadata['aips_call_type']);
	}

	// ------------------------------------------------------------------
	// run(): model path encoding
	// ------------------------------------------------------------------

	public function test_run_uses_the_path_form_and_preserves_model_slashes() {
		$this->next_response = $this->http_ok(array('result' => array()));

		$this->client()->run('@cf/baai/bge-m3', array('text' => array('hello')));

		$this->assertSame(
			'https://api.cloudflare.com/client/v4/accounts/' . self::ACCOUNT_ID . '/ai/run/%40cf/baai/bge-m3',
			$this->last_request['url']
		);
		$sent = json_decode((string) $this->last_request['args']['body'], true);
		$this->assertSame(array('hello'), $sent['text']);
	}

	public function test_run_rejects_an_empty_model() {
		$this->expectException(AIPS_Cloudflare_AI_Gateway_Exception::class);
		$this->client()->run('   ', array('text' => array('x')));
	}

	// ------------------------------------------------------------------
	// verify_token()
	// ------------------------------------------------------------------

	public function test_verify_token_hits_the_user_scope_without_gateway_headers() {
		$this->next_response = $this->http_ok(array('success' => true, 'result' => array('status' => 'active')));

		$result = $this->client()->verify_token();

		$this->assertSame('https://api.cloudflare.com/client/v4/user/tokens/verify', $this->last_request['url']);
		$this->assertSame('GET', $this->last_request['args']['method']);
		$this->assertNull($this->header('cf-aig-gateway-id'));
		$this->assertNull($this->header('cf-aig-metadata'));
		$this->assertSame('active', $result['result']['status']);
	}

	// ------------------------------------------------------------------
	// Pre-flight configuration guards
	// ------------------------------------------------------------------

	public function test_missing_token_throws_invalid_api_key_without_calling_http() {
		delete_option('aips_cf_api_token');
		AIPS_Config::get_instance()->flush_option_cache();

		try {
			$this->client()->chat(array('model' => 'm', 'messages' => array()));
			$this->fail('Expected exception');
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			$this->assertSame('invalid_api_key', $e->get_error_code());
			$this->assertNull($this->last_request, 'No HTTP request should be made');
		}
	}

	public function test_missing_account_id_throws_invalid_request() {
		delete_option('aips_cf_account_id');
		AIPS_Config::get_instance()->flush_option_cache();

		$this->expectException(AIPS_Cloudflare_AI_Gateway_Exception::class);
		$this->expectExceptionMessageMatches('/account ID/i');
		$this->client()->chat(array('model' => 'm', 'messages' => array()));
	}

	// ------------------------------------------------------------------
	// Error normalization
	// ------------------------------------------------------------------

	public function test_transport_failure_is_retryable_transport_error() {
		$this->next_response = new WP_Error('http_request_failed', 'cURL error 28: timed out');

		try {
			$this->client()->chat(array('model' => 'm', 'messages' => array()));
			$this->fail('Expected exception');
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			$this->assertSame(AIPS_Cloudflare_AI_Gateway_Client::CODE_TRANSPORT_ERROR, $e->get_error_code());
			$this->assertSame(0, $e->get_http_status());
			$this->assertStringContainsString('timed out', $e->getMessage());
		}
	}

	public function test_invalid_token_fixture_maps_to_invalid_api_key() {
		$this->next_response = $this->response_from_fixture('error-invalid-token');

		try {
			$this->client()->chat(array('model' => 'm', 'messages' => array()));
			$this->fail('Expected exception');
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			$this->assertSame('invalid_api_key', $e->get_error_code());
			$this->assertSame(401, $e->get_http_status());
			$this->assertSame(10000, $e->get_cf_code());
			$this->assertSame('invalid_api_key: Authentication error', $e->to_prefixed_message());
		}
	}

	public function test_malformed_token_fixture_uses_nested_error_chain_code() {
		$this->next_response = $this->response_from_fixture('error-malformed-token');

		try {
			$this->client()->verify_token();
			$this->fail('Expected exception');
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			$this->assertSame('invalid_api_key', $e->get_error_code());
			$this->assertSame(400, $e->get_http_status());
			$this->assertSame(6111, $e->get_cf_code(), 'Nested error_chain code should win');
		}
	}

	/**
	 * @dataProvider cf_code_cases
	 */
	public function test_cf_error_codes_map_to_canonical_codes(int $status, int $cf_code, string $expected) {
		$this->next_response = $this->http_raw(
			wp_json_encode(array('success' => false, 'errors' => array(array('code' => $cf_code, 'message' => 'x')), 'result' => null)),
			$status
		);

		try {
			$this->client()->chat(array('model' => 'm', 'messages' => array()));
			$this->fail('Expected exception');
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			$this->assertSame($expected, $e->get_error_code());
			$this->assertSame($cf_code, $e->get_cf_code());
		}
	}

	public function cf_code_cases(): array {
		return array(
			'auth 10000'            => array(401, 10000, 'invalid_api_key'),
			'provider creds 2044'   => array(400, 2044, 'invalid_api_key'),
			'no credits 2052'       => array(402, 2052, 'insufficient_quota'),
			'payment 2021'          => array(402, 2021, 'billing_not_active'),
			'not enabled 2042'      => array(403, 2042, 'billing_not_active'),
			'ub rate limit 2018'    => array(429, 2018, 'rate_limit_exceeded'),
			'bad input 7003'        => array(400, 7003, 'invalid_request_error'),
			'exec failed 2002'      => array(500, 2002, 'cf_service_error'),
		);
	}

	/**
	 * @dataProvider status_cases
	 */
	public function test_status_fallback_without_a_known_cf_code(int $status, string $expected) {
		// No errors[] array, so classification falls back to HTTP status.
		$this->next_response = $this->http_raw(wp_json_encode(array('message' => 'boom')), $status);

		try {
			$this->client()->chat(array('model' => 'm', 'messages' => array()));
			$this->fail('Expected exception');
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			$this->assertSame($expected, $e->get_error_code());
		}
	}

	public function status_cases(): array {
		return array(
			'401' => array(401, 'invalid_api_key'),
			'402' => array(402, 'insufficient_quota'),
			'403' => array(403, 'billing_not_active'),
			'404' => array(404, 'model_not_found'),
			'413' => array(413, 'context_length_exceeded'),
			'429' => array(429, 'rate_limit_exceeded'),
			'400' => array(400, 'invalid_request_error'),
			'500' => array(500, 'cf_service_error'),
			'418' => array(418, 'cf_service_error'),
		);
	}

	public function test_model_not_found_message_wins_over_a_400_status() {
		$this->next_response = $this->http_raw(
			wp_json_encode(array('errors' => array(array('code' => 5000, 'message' => 'Model not found: openai/nope')))),
			400
		);

		try {
			$this->client()->chat(array('model' => 'openai/nope', 'messages' => array()));
			$this->fail('Expected exception');
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			$this->assertSame('model_not_found', $e->get_error_code());
		}
	}

	public function test_2xx_with_error_envelope_is_treated_as_an_error() {
		$this->next_response = $this->http_raw(
			wp_json_encode(array('success' => false, 'errors' => array(array('code' => 10000, 'message' => 'Authentication error')), 'result' => null)),
			200
		);

		$this->expectException(AIPS_Cloudflare_AI_Gateway_Exception::class);
		$this->client()->chat(array('model' => 'm', 'messages' => array()));
	}

	public function test_non_json_2xx_body_is_a_service_error() {
		$this->next_response = $this->http_raw('<html>502 Bad Gateway</html>', 200, array('content-type' => 'text/html'));

		try {
			$this->client()->chat(array('model' => 'm', 'messages' => array()));
			$this->fail('Expected exception');
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			$this->assertSame(AIPS_Cloudflare_AI_Gateway_Client::CODE_SERVICE_ERROR, $e->get_error_code());
		}
	}

	public function test_successful_body_is_returned_decoded() {
		$this->next_response = $this->http_ok(array('choices' => array(array('message' => array('content' => 'pong'))), 'usage' => array('total_tokens' => 3)));

		$result = $this->client()->chat(array('model' => 'm', 'messages' => array()));

		$this->assertSame('pong', $result['choices'][0]['message']['content']);
		$this->assertSame(3, $result['usage']['total_tokens']);
	}

	public function test_timeout_option_overrides_default() {
		$this->client()->chat(array('model' => 'm', 'messages' => array()), array('timeout' => 12));
		$this->assertSame(12.0, (float) $this->last_request['args']['timeout']);
	}

	public function test_request_timeout_filter_is_applied() {
		$filter = static function () {
			return 7.0;
		};
		add_filter('aips_cf_gateway_request_timeout', $filter);

		$this->client()->chat(array('model' => 'm', 'messages' => array()));

		remove_filter('aips_cf_gateway_request_timeout', $filter);
		$this->assertSame(7.0, (float) $this->last_request['args']['timeout']);
	}
}
