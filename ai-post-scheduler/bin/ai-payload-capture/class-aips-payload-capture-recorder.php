<?php
/**
 * AI payload capture: recorder.
 *
 * Holds everything captured during a run and owns the three capture points:
 *
 *  1. Service layer  - what a caller (the generator) handed AIPS_AI_Service:
 *                      the prompt exactly as the prompt builders produced it,
 *                      plus the options (system context, conversation, ...).
 *  2. Provider layer - the canonical parameters AIPS_AI_Service handed the
 *                      provider after prepare_options() (model, max_tokens, ...).
 *  3. Wire layer     - the HTTP request the provider's backend actually sent and
 *                      the HTTP response it got back, byte for byte.
 *
 * Dev tooling only: loaded by bin/ai-payload-capture.php, never by the plugin.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Payload_Capture_Recorder {

	/** Hosts whose requests are treated as AI traffic and can be simulated. */
	const GOOGLE_AI_HOST = 'generativelanguage.googleapis.com';

	/** Strings longer than this that look like base64 are elided from reports. */
	const BASE64_ELIDE_MIN_CHARS = 2000;

	/** 1x1 transparent PNG returned for simulated image generations. */
	const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	/** @var bool Simulate every AI HTTP request (no network at all). */
	private $offline;

	/** @var bool Simulate image-generation HTTP requests only. */
	private $simulate_images;

	/** @var array<string,string> Secret value => label, replaced in all output. */
	private $secrets = array();

	/** @var string|null Key of the scenario currently being recorded. */
	private $scenario = null;

	/** @var array<string,array> Captured data per scenario. */
	private $scenarios = array();

	/** @var int[] Indexes (into the scenario's calls) of open service calls. */
	private $call_stack = array();

	/** @var array<int,array{0:int,1:int}> Open provider calls as [call index, provider call index]. */
	private $provider_stack = array();

	/** @var float|null Start time of the HTTP request currently in flight. */
	private $http_started = null;

	/** @var bool Whether the WordPress hooks are installed. */
	private $installed = false;

	/**
	 * @param bool $offline         Simulate every AI request.
	 * @param bool $simulate_images Simulate image-generation requests.
	 */
	public function __construct($offline, $simulate_images) {
		$this->offline         = (bool) $offline;
		$this->simulate_images = (bool) $simulate_images;
	}

	public function is_offline() {
		return $this->offline;
	}

	public function simulates_images() {
		return $this->offline || $this->simulate_images;
	}

	/* --------------------------------------------------------------------
	 * Secrets
	 * ----------------------------------------------------------------- */

	/**
	 * Register a secret so it never reaches a report.
	 *
	 * @param string $value Secret value.
	 * @param string $label Name shown in its place.
	 * @return void
	 */
	public function add_secret($value, $label) {
		$value = is_string($value) ? trim($value) : '';

		// Short values would turn the redaction into a find/replace on ordinary text.
		if (strlen($value) >= 8) {
			$this->secrets[$value] = (string) $label;
		}
	}

	/**
	 * Replace every registered secret in a block of output.
	 *
	 * Applied to each file right before it is written, so it also covers a key
	 * echoed back inside an error message or a URL.
	 *
	 * @param string $text Output text.
	 * @return string
	 */
	public function redact($text) {
		foreach ($this->secrets as $value => $label) {
			$text = str_replace($value, '[redacted:' . $label . ']', $text);
		}

		return $text;
	}

	/* --------------------------------------------------------------------
	 * Scenario lifecycle
	 * ----------------------------------------------------------------- */

	public function begin_scenario($key) {
		$this->scenario        = $key;
		$this->call_stack      = array();
		$this->provider_stack  = array();
		$this->scenarios[$key] = array(
			'calls'             => array(),
			'unattributed_http' => array(),
		);
	}

	public function end_scenario() {
		$this->scenario       = null;
		$this->call_stack     = array();
		$this->provider_stack = array();
	}

	/**
	 * @param string $key Scenario key.
	 * @return array{calls:array,unattributed_http:array}
	 */
	public function get_scenario_capture($key) {
		return isset($this->scenarios[$key]) ? $this->scenarios[$key] : array('calls' => array(), 'unattributed_http' => array());
	}

	/* --------------------------------------------------------------------
	 * Layer 1: service calls
	 * ----------------------------------------------------------------- */

	/**
	 * Record the start of an AIPS_AI_Service call.
	 *
	 * @param string $method  Service method (generate_text, generate_json, ...).
	 * @param mixed  $prompt  Prompt exactly as passed by the caller.
	 * @param mixed  $options Options exactly as passed by the caller.
	 * @return int|null Handle for close_service_call(), or null when not recording.
	 */
	public function open_service_call($method, $prompt, $options) {
		if ($this->scenario === null) {
			return null;
		}

		$origin  = $this->describe_origin();
		$options = is_array($options) ? $options : array();
		$parent  = empty($this->call_stack) ? null : end($this->call_stack);

		$calls = &$this->scenarios[$this->scenario]['calls'];
		$index = count($calls);

		$calls[$index] = array(
			'seq'            => $index + 1,
			'parent_seq'     => $parent === null ? null : $parent + 1,
			'component'      => $this->resolve_component($method, $options, $origin),
			'method'         => $method,
			'caller'         => $origin['caller'],
			'started_at'     => gmdate('Y-m-d\TH:i:s\Z'),
			'duration_ms'    => null,
			'request'        => array(
				'prompt'  => $prompt,
				'options' => $this->export_options($options),
			),
			'provider_calls' => array(),
			'result'         => null,
			'_started'       => microtime(true),
		);

		$this->call_stack[] = $index;

		return $index;
	}

	/**
	 * Record the outcome of an AIPS_AI_Service call.
	 *
	 * @param int|null $handle Handle from open_service_call().
	 * @param mixed    $result Value the service returned to its caller.
	 * @return void
	 */
	public function close_service_call($handle, $result) {
		if ($handle === null || $this->scenario === null || !isset($this->scenarios[$this->scenario]['calls'][$handle])) {
			return;
		}

		$call = &$this->scenarios[$this->scenario]['calls'][$handle];

		$call['duration_ms'] = (int) round((microtime(true) - $call['_started']) * 1000);
		$call['result']      = $this->export_result($result);
		unset($call['_started']);

		$position = array_search($handle, $this->call_stack, true);
		if ($position !== false) {
			array_splice($this->call_stack, $position);
		}
	}

	/* --------------------------------------------------------------------
	 * Layer 2: provider calls
	 * ----------------------------------------------------------------- */

	/**
	 * Record the start of a provider (transport adapter) call.
	 *
	 * One service call can produce several of these: retries, and the native
	 * JSON attempt followed by its text-based fallback.
	 *
	 * @param string $method Provider method.
	 * @param mixed  $prompt Prompt handed to the provider.
	 * @param array  $params Canonical parameters handed to the provider.
	 * @return array|null Handle for close_provider_call().
	 */
	public function open_provider_call($method, $prompt, $params) {
		if ($this->scenario === null) {
			return null;
		}

		// A provider call with no service call around it means some code built its
		// own AIPS_AI_Service; give it a home so the payload is still reported.
		if (empty($this->call_stack)) {
			$this->open_service_call('(direct provider call)', $prompt, is_array($params) ? $params : array());
			$implicit = true;
		} else {
			$implicit = false;
		}

		$call_index = end($this->call_stack);
		$provider   = &$this->scenarios[$this->scenario]['calls'][$call_index]['provider_calls'];
		$index      = count($provider);

		$provider[$index] = array(
			'attempt'     => $index + 1,
			'method'      => $method,
			'prompt'      => $prompt,
			'params'      => $this->export_value(is_array($params) ? $params : array()),
			'duration_ms' => null,
			'result'      => null,
			'http'        => array(),
			'_started'    => microtime(true),
		);

		$handle                 = array($call_index, $index, $implicit);
		$this->provider_stack[] = $handle;

		return $handle;
	}

	/**
	 * Record the outcome of a provider call.
	 *
	 * @param array|null     $handle Handle from open_provider_call().
	 * @param mixed          $result Provider return value (ignored when $error is set).
	 * @param Throwable|null $error  Exception the provider threw, if any.
	 * @return void
	 */
	public function close_provider_call($handle, $result, $error = null) {
		if ($handle === null || $this->scenario === null) {
			return;
		}

		list($call_index, $index, $implicit) = $handle;

		if (isset($this->scenarios[$this->scenario]['calls'][$call_index]['provider_calls'][$index])) {
			$provider = &$this->scenarios[$this->scenario]['calls'][$call_index]['provider_calls'][$index];

			$provider['duration_ms'] = (int) round((microtime(true) - $provider['_started']) * 1000);
			$provider['result']      = $error
				? array(
					'ok'            => false,
					'error_code'    => 'exception',
					'error_message' => $error->getMessage(),
				)
				: $this->export_result($result);
			unset($provider['_started']);
		}

		array_pop($this->provider_stack);

		if ($implicit) {
			$this->close_service_call($call_index, $error ? new WP_Error('exception', $error->getMessage()) : $result);
		}
	}

	/* --------------------------------------------------------------------
	 * Layer 3: HTTP
	 * ----------------------------------------------------------------- */

	/**
	 * Install the WordPress HTTP API hooks.
	 *
	 * Runs last on pre_http_request so a simulated response is never replaced by
	 * another plugin's filter.
	 *
	 * @return void
	 */
	public function install() {
		if ($this->installed) {
			return;
		}

		add_filter('pre_http_request', array($this, 'filter_pre_http_request'), PHP_INT_MAX, 3);
		add_action('http_api_debug', array($this, 'on_http_api_debug'), 10, 5);

		// The AI Client caches the model catalog through the object cache. Keep a
		// simulated catalog out of any persistent cache backend.
		if ($this->offline && function_exists('wp_cache_add_non_persistent_groups')) {
			wp_cache_add_non_persistent_groups(array((string) apply_filters('wp_ai_client_cache_group', 'wp_ai_client')));
		}

		$this->installed = true;
	}

	public function uninstall() {
		if (!$this->installed) {
			return;
		}

		remove_filter('pre_http_request', array($this, 'filter_pre_http_request'), PHP_INT_MAX);
		remove_action('http_api_debug', array($this, 'on_http_api_debug'), 10);

		$this->installed = false;
	}

	/**
	 * pre_http_request: time live requests, answer simulated ones.
	 *
	 * @param false|array|WP_Error $pre  Short-circuit value.
	 * @param array                $args Parsed request arguments.
	 * @param string               $url  Request URL.
	 * @return false|array|WP_Error
	 */
	public function filter_pre_http_request($pre, $args, $url) {
		if ($this->scenario === null) {
			return $pre;
		}

		$this->http_started = microtime(true);

		$decision = $this->decide($url);

		if ($decision === 'pass') {
			// Another filter already answered: WordPress will not fire
			// http_api_debug, so record the exchange here.
			if ($pre !== false) {
				$this->record_http($pre, $args, $url, 'short-circuited by another pre_http_request filter');
			}

			return $pre;
		}

		if ($decision === 'simulate') {
			$response = $this->build_simulated_response($args, $url);
			$this->record_http($response, $args, $url, 'simulated (no network request was made)');

			return $response;
		}

		$response = new WP_Error(
			'aips_capture_blocked',
			sprintf('Blocked by ai-payload-capture (%s): %s', $this->offline ? '--offline allows no network requests' : 'image generation is simulated and this host cannot be simulated; use --live-images', $this->redact_url($url))
		);
		$this->record_http($response, $args, $url, 'blocked (no network request was made)');

		return $response;
	}

	/**
	 * http_api_debug: record a request that really went over the network.
	 *
	 * @param array|WP_Error $response  HTTP response or error.
	 * @param string         $context   Always 'response'.
	 * @param string         $transport Transport class.
	 * @param array          $args      Parsed request arguments.
	 * @param string         $url       Request URL.
	 * @return void
	 */
	public function on_http_api_debug($response, $context, $transport, $args, $url) {
		if ($this->scenario === null || $context !== 'response') {
			return;
		}

		$this->record_http($response, $args, $url, null);
	}

	/**
	 * Decide what happens to an outgoing request.
	 *
	 * @param string $url Request URL.
	 * @return string 'pass', 'simulate' or 'block'.
	 */
	private function decide($url) {
		$host      = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
		$is_google = ($host === self::GOOGLE_AI_HOST);

		if ($this->offline) {
			return $is_google ? 'simulate' : 'block';
		}

		if ($this->simulate_images && $this->is_inside_image_call()) {
			// The model catalog lookup is free; only the generation itself is simulated.
			if ($is_google) {
				return preg_match('/:(predict|generateContent)\b/', $url) ? 'simulate' : 'pass';
			}

			return $this->is_inside_provider_call() ? 'block' : 'pass';
		}

		return 'pass';
	}

	private function is_inside_provider_call() {
		return !empty($this->provider_stack);
	}

	private function is_inside_image_call() {
		if ($this->scenario === null) {
			return false;
		}

		foreach ($this->call_stack as $index) {
			if ($this->scenarios[$this->scenario]['calls'][$index]['method'] === 'generate_image') {
				return true;
			}
		}

		return false;
	}

	/**
	 * Store one HTTP exchange under the provider call that caused it.
	 *
	 * @param array|WP_Error $response HTTP response or error.
	 * @param array          $args     Parsed request arguments.
	 * @param string         $url      Request URL.
	 * @param string|null    $note     How the response was produced when not live.
	 * @return void
	 */
	private function record_http($response, $args, $url, $note) {
		$started            = $this->http_started;
		$this->http_started = null;

		$exchange = array(
			'note'        => $note,
			'duration_ms' => $started === null ? null : (int) round((microtime(true) - $started) * 1000),
			'request'     => array(
				'method'  => isset($args['method']) ? (string) $args['method'] : 'GET',
				'url'     => $this->redact_url($url),
				'headers' => $this->redact_headers(isset($args['headers']) ? $args['headers'] : array()),
				'timeout' => isset($args['timeout']) ? $args['timeout'] : null,
				'body'    => $this->export_body(isset($args['body']) ? $args['body'] : null),
			),
			'response'    => $this->export_http_response($response),
		);

		if (!empty($this->provider_stack)) {
			list($call_index, $index) = end($this->provider_stack);
			$this->scenarios[$this->scenario]['calls'][$call_index]['provider_calls'][$index]['http'][] = $exchange;

			return;
		}

		$this->scenarios[$this->scenario]['unattributed_http'][] = $exchange;
	}

	/**
	 * @param array|WP_Error $response HTTP response or error.
	 * @return array
	 */
	private function export_http_response($response) {
		if (is_wp_error($response)) {
			return array(
				'error_code'    => $response->get_error_code(),
				'error_message' => $response->get_error_message(),
			);
		}

		$headers = wp_remote_retrieve_headers($response);
		if (is_object($headers) && method_exists($headers, 'getAll')) {
			$headers = $headers->getAll();
		}

		return array(
			'status'  => (int) wp_remote_retrieve_response_code($response),
			'headers' => $this->redact_headers(is_array($headers) ? $headers : array()),
			'body'    => $this->export_body(wp_remote_retrieve_body($response)),
		);
	}

	/**
	 * Export an HTTP body verbatim, with a decoded copy when it is JSON.
	 *
	 * 'raw' is the exact string sent or received. 'json' is the same payload
	 * decoded for readability; only there are oversized base64 blobs elided.
	 *
	 * @param mixed $body Request or response body.
	 * @return array|null
	 */
	private function export_body($body) {
		if ($body === null || $body === '') {
			return null;
		}

		if (!is_string($body)) {
			// Form-encoded bodies are passed to the HTTP API as arrays.
			return array('form' => $this->export_value($body));
		}

		if (!wp_check_invalid_utf8($body, true) && $body !== '') {
			return array('binary' => sprintf('[binary body, %d bytes, sha256 %s]', strlen($body), hash('sha256', $body)));
		}

		$decoded = json_decode($body, true);

		if (is_array($decoded)) {
			$elided = $this->elide_blobs($decoded);

			// Megabytes of base64 help nobody; keep the raw string only when it is
			// identical in substance to the decoded copy.
			return array(
				'raw'  => ($elided === $decoded) ? $body : '[raw body omitted: contains large base64 data; see "json"]',
				'json' => $elided,
			);
		}

		return array('raw' => $body);
	}

	/**
	 * Replace large base64 strings (generated images) with a short marker.
	 *
	 * @param mixed $value Decoded JSON value.
	 * @return mixed
	 */
	private function elide_blobs($value) {
		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[$key] = $this->elide_blobs($item);
			}

			return $value;
		}

		if (is_string($value) && strlen($value) >= self::BASE64_ELIDE_MIN_CHARS && preg_match('#^[A-Za-z0-9+/=_-]+$#', $value)) {
			return sprintf('[base64 elided: %d chars, sha256 %s]', strlen($value), hash('sha256', $value));
		}

		return $value;
	}

	/**
	 * @param mixed $headers Header map.
	 * @return array
	 */
	private function redact_headers($headers) {
		$out = array();

		foreach ((array) $headers as $name => $value) {
			if (preg_match('/key|authorization|token|secret|cookie|password/i', (string) $name)) {
				$out[$name] = '[redacted]';
				continue;
			}

			$out[$name] = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
		}

		return $out;
	}

	/**
	 * @param string $url Request URL.
	 * @return string
	 */
	private function redact_url($url) {
		return (string) preg_replace('/([?&](?:key|api_key|apikey|access_token|token|client_id)=)[^&#]+/i', '$1[redacted]', (string) $url);
	}

	/* --------------------------------------------------------------------
	 * Simulated Gemini responses
	 * ----------------------------------------------------------------- */

	/**
	 * Build a response shaped like the Google Generative Language API.
	 *
	 * @param array  $args Parsed request arguments.
	 * @param string $url  Request URL.
	 * @return array WordPress HTTP API response array.
	 */
	private function build_simulated_response($args, $url) {
		$path    = (string) wp_parse_url($url, PHP_URL_PATH);
		$request = (isset($args['body']) && is_string($args['body'])) ? json_decode($args['body'], true) : null;
		$request = is_array($request) ? $request : array();

		if (preg_match('#/models/?$#', $path)) {
			$payload = array('models' => $this->simulated_model_catalog());
		} elseif (strpos($path, ':predict') !== false) {
			$payload = array(
				'predictions' => array(
					array(
						'mimeType'           => 'image/png',
						'bytesBase64Encoded' => self::TINY_PNG_BASE64,
					),
				),
			);
		} elseif (strpos($path, ':embedContent') !== false) {
			$payload = array('embedding' => array('values' => array_fill(0, 8, 0.125)));
		} elseif ($this->is_inside_image_call()) {
			$payload = $this->simulated_candidate(array(
				array(
					'inlineData' => array(
						'mimeType' => 'image/png',
						'data'     => self::TINY_PNG_BASE64,
					),
				),
			), $request);
		} else {
			$payload = $this->simulated_candidate(array(array('text' => $this->simulated_text($request))), $request);
		}

		return array(
			'headers'  => array(
				'content-type'     => 'application/json; charset=UTF-8',
				'x-aips-simulated' => '1',
			),
			'body'     => wp_json_encode($payload),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * @param array $parts   Candidate content parts.
	 * @param array $request Decoded request body.
	 * @return array
	 */
	private function simulated_candidate(array $parts, array $request) {
		$prompt_chars = strlen((string) wp_json_encode(isset($request['contents']) ? $request['contents'] : array()));
		$output_chars = strlen((string) wp_json_encode($parts));

		return array(
			'candidates'    => array(
				array(
					'content'      => array(
						'role'  => 'model',
						'parts' => $parts,
					),
					'finishReason' => 'STOP',
					'index'        => 0,
				),
			),
			'usageMetadata' => array(
				'promptTokenCount'     => (int) ceil($prompt_chars / 4),
				'candidatesTokenCount' => (int) ceil($output_chars / 4),
				'totalTokenCount'      => (int) ceil(($prompt_chars + $output_chars) / 4),
			),
			'modelVersion'  => 'aips-simulated',
		);
	}

	/**
	 * Model catalog covering the configured model plus generic text/image models.
	 *
	 * @return array
	 */
	private function simulated_model_catalog() {
		$text_models = array('gemini-2.5-flash');

		foreach (explode(',', (string) AIPS_Config::get_instance()->get_option('aips_ai_model')) as $configured) {
			$configured = trim($configured);
			if ($configured !== '' && !in_array($configured, $text_models, true)) {
				array_unshift($text_models, $configured);
			}
		}

		$catalog = array();

		foreach ($text_models as $model) {
			$catalog[] = array(
				'name'                       => 'models/' . $model,
				'baseModelId'                => $model,
				'displayName'                => $model . ' (simulated)',
				'supportedGenerationMethods' => array('generateContent', 'countTokens'),
			);
		}

		$catalog[] = array(
			'name'                       => 'models/imagen-4.0-generate-001',
			'baseModelId'                => 'imagen-4.0-generate-001',
			'displayName'                => 'Imagen 4 (simulated)',
			'supportedGenerationMethods' => array('predict'),
		);

		return $catalog;
	}

	/**
	 * Pick canned text that lets the pipeline carry on past this call.
	 *
	 * @param array $request Decoded request body.
	 * @return string
	 */
	private function simulated_text(array $request) {
		if (isset($request['generationConfig']['responseSchema']) && is_array($request['generationConfig']['responseSchema'])) {
			return (string) wp_json_encode($this->sample_from_schema($request['generationConfig']['responseSchema'], 'value'));
		}

		$call      = $this->current_service_call();
		$component = $call ? (string) $call['component'] : '';
		$prompt    = $call && is_string($call['request']['prompt']) ? $call['request']['prompt'] : '';

		if (strpos($component, 'ai_variables') === 0) {
			$values = array();

			if (preg_match('/values for these variables:\s*(.+)/', $prompt, $matches)) {
				foreach (array_filter(array_map('trim', explode(',', $matches[1]))) as $name) {
					$values[$name] = 'Simulated ' . $name;
				}
			}

			return (string) wp_json_encode($values ? $values : array('Variable' => 'Simulated value'));
		}

		switch ($component) {
			case 'title':
				return 'Simulated Title for the Captured Post';
			case 'slug':
				return 'simulated-captured-post-slug';
			case 'excerpt':
				return 'This is a simulated excerpt returned by ai-payload-capture so the pipeline can continue without calling the model.';
			case 'content':
			case 'content_preview':
				return $this->simulated_article($prompt);
		}

		return 'Simulated response from ai-payload-capture.';
	}

	/**
	 * @param string $prompt Content prompt (searched for image placeholders).
	 * @return string
	 */
	private function simulated_article($prompt) {
		$article  = "<h2>Simulated Section One</h2>\n";
		$article .= "<p>This article body is simulated by ai-payload-capture. It exists so the title, slug, excerpt and image requests that follow have something to work from.</p>\n";

		// A prompt that demands inline image placeholders fails the post when they are missing.
		if (preg_match_all('/\[post_image index="(\d+)"\]/', $prompt, $matches)) {
			foreach (array_unique($matches[1]) as $index) {
				$article .= "\n[post_image index=\"{$index}\"]\n\n";
			}
		}

		$article .= "<h2>Simulated Section Two</h2>\n";
		$article .= '<p>No request was sent to the model for this text.</p>';

		return $article;
	}

	/**
	 * Build a value satisfying a JSON schema (Gemini or JSON Schema spelling).
	 *
	 * @param array  $schema Schema node.
	 * @param string $name   Property name, used to make samples recognisable.
	 * @return mixed
	 */
	private function sample_from_schema(array $schema, $name) {
		$type = isset($schema['type']) ? strtolower((string) (is_array($schema['type']) ? reset($schema['type']) : $schema['type'])) : 'string';

		if (!empty($schema['enum']) && is_array($schema['enum'])) {
			return reset($schema['enum']);
		}

		switch ($type) {
			case 'object':
				$object = array();
				foreach ((isset($schema['properties']) && is_array($schema['properties'])) ? $schema['properties'] : array() as $key => $child) {
					$object[$key] = $this->sample_from_schema(is_array($child) ? $child : array(), (string) $key);
				}
				return $object ? $object : new stdClass();
			case 'array':
				return array($this->sample_from_schema((isset($schema['items']) && is_array($schema['items'])) ? $schema['items'] : array(), $name));
			case 'integer':
			case 'number':
				return 1;
			case 'boolean':
				return true;
		}

		if ($name === 'slug') {
			return 'simulated-captured-post-slug';
		}

		return 'Simulated ' . str_replace('_', ' ', $name);
	}

	/**
	 * @return array|null Innermost open service call.
	 */
	private function current_service_call() {
		if ($this->scenario === null || empty($this->call_stack)) {
			return null;
		}

		return $this->scenarios[$this->scenario]['calls'][end($this->call_stack)];
	}

	/* --------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------- */

	/**
	 * Function names, in priority order, that unambiguously identify which
	 * generation component an image/text call belongs to. Checked against
	 * every frame in the backtrace rather than just the nearest one, because
	 * AIPS_Generator::resolve_post_image() and set_featured_image_from_context()
	 * both delegate the actual call to the same AIPS_Image_Service helper, so
	 * the nearest frame alone cannot tell content images apart from the
	 * featured image.
	 *
	 * @var array<string,string>
	 */
	private const COMPONENT_MARKER_FUNCTIONS = array(
		'generate_metadata_turn'          => 'metadata',
		'resolve_post_image'              => 'post_image',
		'set_featured_image_from_context' => 'featured_image',
		'process_featured_image_prompt'   => 'featured_image',
	);

	/**
	 * Find out which plugin code started the current AI call.
	 *
	 * AIPS_Generator::generate_content() carries the component label as its third
	 * argument, but only forwards it to the service for some request types, so it
	 * is read from the stack instead.
	 *
	 * @return array{caller:string,component:string}
	 */
	private function describe_origin() {
		$caller    = '';
		$component = '';
		$marker    = '';

		foreach (debug_backtrace(0, 40) as $frame) {
			$class    = isset($frame['class']) ? $frame['class'] : '';
			$function = isset($frame['function']) ? $frame['function'] : '';

			if ($marker === '' && isset(self::COMPONENT_MARKER_FUNCTIONS[$function])) {
				$marker = self::COMPONENT_MARKER_FUNCTIONS[$function];
			}

			if ($class === '' || strpos($class, 'AIPS_Payload_Capture_') === 0 || is_a($class, 'AIPS_AI_Service', true)) {
				continue;
			}

			if ($class === 'AIPS_Generator' && $function === 'generate_content') {
				if (isset($frame['args'][2]) && is_string($frame['args'][2])) {
					$component = $frame['args'][2];
				}
				continue;
			}

			if ($caller === '') {
				$caller = $class . '::' . $function . '()';
			}
		}

		return array(
			'caller'    => $caller,
			'component' => $component,
			'marker'    => $marker,
		);
	}

	/**
	 * @param string $method  Service method.
	 * @param array  $options Caller options.
	 * @param array  $origin  Result of describe_origin().
	 * @return string
	 */
	private function resolve_component($method, array $options, array $origin) {
		if ($origin['component'] !== '') {
			return $origin['component'];
		}

		if (!empty($options['request_type']) && is_string($options['request_type'])) {
			return $options['request_type'];
		}

		if ($origin['marker'] !== '') {
			return $origin['marker'];
		}

		$fallback = array(
			'generate_image'     => 'image',
			'generate_embedding' => 'embedding',
			'generate_json'      => 'json',
		);

		return isset($fallback[$method]) ? $fallback[$method] : 'text';
	}

	/**
	 * Export caller options, expanding the conversation transcript.
	 *
	 * AIPS_AI_Service logs the transcript as a one-line summary; here every turn
	 * is kept because the turns are part of what the model receives.
	 *
	 * @param array $options Caller options.
	 * @return array
	 */
	private function export_options(array $options) {
		return $this->export_value($options);
	}

	/**
	 * Convert a value into plain JSON-serializable data.
	 *
	 * @param mixed $value Any value.
	 * @param int   $depth Recursion guard.
	 * @return mixed
	 */
	public function export_value($value, $depth = 0) {
		if ($depth > 12) {
			return '[max depth reached]';
		}

		if ($value instanceof AIPS_AI_Conversation) {
			return array(
				'__type' => 'AIPS_AI_Conversation',
				'turns'  => $value->to_array(),
			);
		}

		if ($value instanceof Closure) {
			return '[closure]';
		}

		if (is_wp_error($value)) {
			return array(
				'__type'        => 'WP_Error',
				'error_code'    => $value->get_error_code(),
				'error_message' => $value->get_error_message(),
			);
		}

		if (is_object($value)) {
			return array(
				'__type' => get_class($value),
				'data'   => $this->export_value(get_object_vars($value), $depth + 1),
			);
		}

		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[$key] = $this->export_value($item, $depth + 1);
			}

			return $value;
		}

		if (is_string($value) && strpos($value, 'data:') === 0 && strlen($value) > 200) {
			return sprintf('[data URI elided: %d chars, sha256 %s]', strlen($value), hash('sha256', $value));
		}

		return $value;
	}

	/**
	 * @param mixed $result Service or provider return value.
	 * @return array
	 */
	private function export_result($result) {
		if (is_wp_error($result)) {
			return array(
				'ok'            => false,
				'error_code'    => $result->get_error_code(),
				'error_message' => $result->get_error_message(),
			);
		}

		return array(
			'ok'    => true,
			'value' => $this->export_value($result),
		);
	}
}
