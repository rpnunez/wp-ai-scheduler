<?php
/**
 * Cloudflare AI Gateway HTTP client.
 *
 * Owns every HTTP call to the Cloudflare AI Gateway REST API: URL and header
 * construction, the request itself (through the WordPress HTTP API so tests can
 * stub it), and turning transport failures, non-2xx responses and malformed
 * bodies into AIPS_Cloudflare_AI_Gateway_Exception with a normalized AIPS error
 * code.
 *
 * Resilience (retries, circuit breaker) lives in AIPS_AI_Service /
 * AIPS_Resilience_Service, so this client never retries: it sends
 * cf-aig-max-attempts: 1 and surfaces the first failure.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cloudflare_AI_Gateway_Client
 */
class AIPS_Cloudflare_AI_Gateway_Client {

	/**
	 * Default request timeout in seconds. Overridable per call via
	 * $options['timeout'] or the aips_cf_gateway_request_timeout filter.
	 */
	const DEFAULT_TIMEOUT_SECONDS = 90.0;

	/**
	 * Generic retryable code for upstream/5xx/transport failures. Deliberately
	 * absent from AIPS_Resilience_Service's non-retryable sets so the service
	 * retries it and counts it toward the circuit breaker.
	 */
	const CODE_SERVICE_ERROR = 'cf_service_error';

	/**
	 * Transport-level (no HTTP response) failure code. Retryable.
	 */
	const CODE_TRANSPORT_ERROR = 'cf_transport_error';

	/**
	 * Cloudflare numeric error code => canonical AIPS code.
	 *
	 * @var array<int,string>
	 */
	private const CF_CODE_MAP = array(
		10000 => 'invalid_api_key',
		2009  => 'invalid_api_key',
		2044  => 'invalid_api_key',
		6003  => 'invalid_api_key',
		6111  => 'invalid_api_key',
		2052  => 'insufficient_quota',
		2021  => 'billing_not_active',
		2049  => 'billing_not_active',
		2042  => 'billing_not_active',
		2036  => 'billing_not_active',
		2018  => 'rate_limit_exceeded',
		7003  => 'invalid_request_error',
		7000  => 'invalid_request_error',
		2002  => self::CODE_SERVICE_ERROR,
		2005  => self::CODE_SERVICE_ERROR,
		7005  => self::CODE_SERVICE_ERROR,
	);

	/**
	 * @var AIPS_Cloudflare_AI_Gateway_Config
	 */
	private $config;

	/**
	 * @param AIPS_Cloudflare_AI_Gateway_Config|null $config Configuration (defaults to a fresh instance).
	 */
	public function __construct(?AIPS_Cloudflare_AI_Gateway_Config $config = null) {
		$this->config = $config ?: new AIPS_Cloudflare_AI_Gateway_Config();
	}

	/**
	 * @return AIPS_Cloudflare_AI_Gateway_Config
	 */
	public function get_config(): AIPS_Cloudflare_AI_Gateway_Config {
		return $this->config;
	}

	/**
	 * OpenAI-compatible chat completion.
	 *
	 * @param array $payload Request body (model, messages, max_tokens, ...).
	 * @param array $options Transport options (see request()).
	 * @return array Decoded response body.
	 * @throws AIPS_Cloudflare_AI_Gateway_Exception On any failure.
	 */
	public function chat(array $payload, array $options = array()): array {
		return $this->request('POST', '/v1/chat/completions', $payload, $options);
	}

	/**
	 * Run a Workers AI (or third-party, via wrapped input) model through /ai/run.
	 *
	 * Uses the path form POST /ai/run/{model}, which takes the model inputs
	 * directly in the body.
	 *
	 * @param string $model Model id (e.g. '@cf/baai/bge-m3').
	 * @param array  $input Model inputs (e.g. ['text' => [...]] or ['prompt' => '...']).
	 * @param array  $options Transport options (see request()).
	 * @return array Decoded response body.
	 * @throws AIPS_Cloudflare_AI_Gateway_Exception On any failure.
	 */
	public function run(string $model, array $input, array $options = array()): array {
		$model = ltrim(trim($model), '/');

		if ($model === '') {
			throw new AIPS_Cloudflare_AI_Gateway_Exception('invalid_request_error', __('No Cloudflare model was given for the request.', 'ai-post-scheduler'));
		}

		// Preserve @ and / in the model path; rawurlencode() would mangle them.
		$encoded = implode('/', array_map('rawurlencode', explode('/', $model)));

		return $this->request('POST', '/run/' . $encoded, $input, $options);
	}

	/**
	 * Verify the configured API token.
	 *
	 * Calls the account-independent /user/tokens/verify endpoint, so it checks
	 * the token itself, not account access.
	 *
	 * @return array Decoded response body (result.status === 'active' when valid).
	 * @throws AIPS_Cloudflare_AI_Gateway_Exception On any failure, including an invalid token.
	 */
	public function verify_token(): array {
		return $this->request(
			'GET',
			null,
			null,
			array(
				'url'          => AIPS_Cloudflare_AI_Gateway_Config::API_BASE . '/user/tokens/verify',
				'call_type'    => 'verify',
				'skip_gateway' => true,
			)
		);
	}

	/**
	 * Perform one request and normalize the outcome.
	 *
	 * @param string      $method HTTP method.
	 * @param string|null $path   Path below the account AI base (ignored when $options['url'] is set).
	 * @param array|null  $body   JSON body, or null for no body.
	 * @param array       $options {
	 *     @type string $url          Absolute URL override (bypasses $path and the AI base).
	 *     @type string $call_type    Value recorded in cf-aig-metadata (text|json|image|embedding|verify).
	 *     @type bool   $skip_cache   Send cf-aig-skip-cache: true.
	 *     @type int    $cache_ttl    Send cf-aig-cache-ttl (seconds); ignored when skip_cache is true.
	 *     @type bool   $skip_gateway Omit the cf-aig-gateway-id header (for non-AI endpoints like token verify).
	 *     @type float  $timeout      Request timeout override (seconds).
	 * }
	 * @return array Decoded response body.
	 * @throws AIPS_Cloudflare_AI_Gateway_Exception On any failure.
	 */
	private function request(string $method, ?string $path, ?array $body, array $options): array {
		$token = $this->config->get_api_token();

		if ($token === '') {
			throw new AIPS_Cloudflare_AI_Gateway_Exception('invalid_api_key', __('No Cloudflare API token is configured, or it could not be decrypted.', 'ai-post-scheduler'));
		}

		if (!empty($options['url'])) {
			$url = (string) $options['url'];
		} else {
			$base = $this->config->get_api_base_url();

			if ($base === '') {
				throw new AIPS_Cloudflare_AI_Gateway_Exception('invalid_request_error', __('No Cloudflare account ID is configured.', 'ai-post-scheduler'));
			}

			$url = $base . $path;
		}

		$timeout = isset($options['timeout'])
			? (float) $options['timeout']
			: (float) apply_filters('aips_cf_gateway_request_timeout', self::DEFAULT_TIMEOUT_SECONDS);

		$args = array(
			'method'  => $method,
			'timeout' => max(1.0, $timeout),
			'headers' => $this->build_headers($token, $options),
		);

		if ($body !== null) {
			$args['body'] = wp_json_encode($body);
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			throw new AIPS_Cloudflare_AI_Gateway_Exception(
				self::CODE_TRANSPORT_ERROR,
				/* translators: %s: transport error detail. */
				sprintf(__('Could not reach Cloudflare: %s', 'ai-post-scheduler'), $response->get_error_message()),
				0,
				null
			);
		}

		$status  = (int) wp_remote_retrieve_response_code($response);
		$raw     = (string) wp_remote_retrieve_body($response);
		$decoded = json_decode($raw, true);

		if ($status < 200 || $status >= 300) {
			throw $this->error_from_response($status, is_array($decoded) ? $decoded : null, $raw);
		}

		if (!is_array($decoded)) {
			throw new AIPS_Cloudflare_AI_Gateway_Exception(
				self::CODE_SERVICE_ERROR,
				__('Cloudflare returned a response that was not valid JSON.', 'ai-post-scheduler'),
				$status,
				null
			);
		}

		// A 2xx can still carry the v4 error envelope (success:false).
		if (array_key_exists('success', $decoded) && $decoded['success'] === false && !empty($decoded['errors'])) {
			throw $this->error_from_response($status, $decoded, $raw);
		}

		return $decoded;
	}

	/**
	 * Build the request headers.
	 *
	 * @param string $token   API token.
	 * @param array  $options Transport options.
	 * @return array<string,string>
	 */
	private function build_headers(string $token, array $options): array {
		$headers = array(
			'Authorization'          => 'Bearer ' . $token,
			'Content-Type'           => 'application/json',
			// Retries are handled by the AIPS resilience layer, not the gateway.
			'cf-aig-max-attempts'    => '1',
		);

		if (empty($options['skip_gateway'])) {
			$headers['cf-aig-gateway-id']        = $this->config->get_gateway_id();
			$headers['cf-aig-collect-log-payload'] = $this->config->should_log_payloads() ? 'true' : 'false';

			$metadata = $this->build_metadata($options);
			if ($metadata !== '') {
				$headers['cf-aig-metadata'] = $metadata;
			}
		}

		if (!empty($options['skip_cache'])) {
			$headers['cf-aig-skip-cache'] = 'true';
		} elseif (isset($options['cache_ttl']) && (int) $options['cache_ttl'] > 0) {
			$headers['cf-aig-cache-ttl'] = (string) (int) $options['cache_ttl'];
		}

		return $headers;
	}

	/**
	 * Build the cf-aig-metadata JSON string (scalar values only, kept small).
	 *
	 * @param array $options Transport options.
	 * @return string JSON, or '' when there is nothing to attach.
	 */
	private function build_metadata(array $options): string {
		$metadata = array();

		$correlation_id = class_exists('AIPS_Correlation_ID') ? AIPS_Correlation_ID::get() : null;
		if (is_string($correlation_id) && $correlation_id !== '') {
			$metadata['aips_correlation_id'] = $correlation_id;
		}

		if (!empty($options['call_type']) && is_string($options['call_type'])) {
			$metadata['aips_call_type'] = $options['call_type'];
		}

		if (defined('AIPS_VERSION')) {
			$metadata['aips_version'] = AIPS_VERSION;
		}

		if (empty($metadata)) {
			return '';
		}

		return (string) wp_json_encode($metadata);
	}

	/**
	 * Build the exception for a non-2xx (or 2xx error-envelope) response.
	 *
	 * @param int        $status  HTTP status.
	 * @param array|null $decoded Decoded body, or null when it was not JSON.
	 * @param string     $raw     Raw body.
	 * @return AIPS_Cloudflare_AI_Gateway_Exception
	 */
	private function error_from_response(int $status, ?array $decoded, string $raw): AIPS_Cloudflare_AI_Gateway_Exception {
		list($cf_code, $message) = $this->extract_error($decoded, $raw);
		$code = $this->classify($status, $cf_code, $message);

		if ($message === '') {
			/* translators: %d: HTTP status code. */
			$message = sprintf(__('Cloudflare returned HTTP %d.', 'ai-post-scheduler'), $status);
		}

		return new AIPS_Cloudflare_AI_Gateway_Exception($code, $message, $status, $cf_code);
	}

	/**
	 * Pull the Cloudflare numeric code and message out of an error body, handling
	 * both the v4 envelope ({errors:[{code,message,error_chain}]}) and the
	 * OpenAI-compatible shape ({error:{message,code}}).
	 *
	 * @param array|null $decoded Decoded body.
	 * @param string     $raw     Raw body (fallback message).
	 * @return array{0:?int,1:string} [cf_code|null, message]
	 */
	private function extract_error(?array $decoded, string $raw): array {
		if (!is_array($decoded)) {
			$snippet = trim($raw);
			return array(null, $snippet === '' ? '' : mb_substr($snippet, 0, 300));
		}

		// Cloudflare v4 envelope.
		if (!empty($decoded['errors']) && is_array($decoded['errors'])) {
			$first   = $decoded['errors'][0];
			$cf_code = isset($first['code']) ? (int) $first['code'] : null;
			$message = isset($first['message']) ? (string) $first['message'] : '';

			// A nested, more specific code (e.g. 6111 under 6003) wins when it is
			// one we recognize.
			if (!empty($first['error_chain']) && is_array($first['error_chain'])) {
				$nested = $first['error_chain'][0];
				if (isset($nested['code']) && isset(self::CF_CODE_MAP[(int) $nested['code']])) {
					$cf_code = (int) $nested['code'];
				}
				if (empty($message) && isset($nested['message'])) {
					$message = (string) $nested['message'];
				}
			}

			return array($cf_code, $message);
		}

		// OpenAI-compatible error shape.
		if (!empty($decoded['error'])) {
			if (is_array($decoded['error'])) {
				$cf_code = isset($decoded['error']['code']) && is_numeric($decoded['error']['code']) ? (int) $decoded['error']['code'] : null;
				$message = isset($decoded['error']['message']) ? (string) $decoded['error']['message'] : '';
				return array($cf_code, $message);
			}

			return array(null, (string) $decoded['error']);
		}

		return array(null, '');
	}

	/**
	 * Map a (status, cf_code, message) triple onto a canonical AIPS error code.
	 *
	 * @param int      $status  HTTP status.
	 * @param int|null $cf_code Cloudflare numeric code.
	 * @param string   $message Error message.
	 * @return string Canonical AIPS code.
	 */
	private function classify(int $status, ?int $cf_code, string $message): string {
		if ($cf_code !== null && isset(self::CF_CODE_MAP[$cf_code])) {
			return self::CF_CODE_MAP[$cf_code];
		}

		$lower = strtolower($message);

		if (strpos($lower, 'model not found') !== false || strpos($lower, 'does not exist') !== false) {
			return 'model_not_found';
		}

		if (strpos($lower, 'context') !== false && (strpos($lower, 'length') !== false || strpos($lower, 'too large') !== false || strpos($lower, 'maximum') !== false)) {
			return 'context_length_exceeded';
		}

		switch ($status) {
			case 401:
				return 'invalid_api_key';
			case 402:
				return 'insufficient_quota';
			case 403:
				return 'billing_not_active';
			case 404:
				return 'model_not_found';
			case 413:
				return 'context_length_exceeded';
			case 429:
				return 'rate_limit_exceeded';
			case 400:
				return 'invalid_request_error';
		}

		// 5xx and anything unrecognized: retryable upstream error.
		return self::CODE_SERVICE_ERROR;
	}
}
