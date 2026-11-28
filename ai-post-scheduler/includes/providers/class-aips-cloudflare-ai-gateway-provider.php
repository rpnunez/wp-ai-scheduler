<?php
/**
 * Cloudflare AI Gateway provider.
 *
 * Adapts the Cloudflare AI Gateway REST API to AIPS_AI_Provider_Interface. A
 * thin adapter: it maps the plugin's canonical parameters onto the OpenAI-
 * compatible chat shape, delegates the HTTP call to
 * AIPS_Cloudflare_AI_Gateway_Client, and reads the result. Resilience, logging
 * and token budgeting stay in AIPS_AI_Service.
 *
 * Text and multi-turn conversation are implemented here. Structured JSON falls
 * back to the service's text-based extraction for now (see generate_json()).
 * Image generation and embeddings arrive in later slices and currently raise a
 * clear capability error.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cloudflare_AI_Gateway_Provider
 */
class AIPS_Cloudflare_AI_Gateway_Provider implements AIPS_AI_Provider_Interface {

	const ID = 'cloudflare_ai_gateway';

	/**
	 * @var AIPS_Cloudflare_AI_Gateway_Config
	 */
	private $config;

	/**
	 * @var AIPS_Cloudflare_AI_Gateway_Client
	 */
	private $client;

	/**
	 * @param AIPS_Cloudflare_AI_Gateway_Config|null $config Configuration.
	 * @param AIPS_Cloudflare_AI_Gateway_Client|null  $client HTTP client (defaults to one bound to $config).
	 */
	public function __construct(?AIPS_Cloudflare_AI_Gateway_Config $config = null, ?AIPS_Cloudflare_AI_Gateway_Client $client = null) {
		$this->config = $config ?: new AIPS_Cloudflare_AI_Gateway_Config();
		$this->client = $client ?: new AIPS_Cloudflare_AI_Gateway_Client($this->config);
	}

	/**
	 * @inheritDoc
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * @inheritDoc
	 */
	public function get_label(): string {
		return __('Cloudflare AI Gateway', 'ai-post-scheduler');
	}

	/**
	 * Ready when an account ID, a decryptable token and a text model are set.
	 * Makes no network call.
	 *
	 * @inheritDoc
	 */
	public function is_available(): bool {
		return $this->config->is_configured();
	}

	/**
	 * @inheritDoc
	 */
	public function get_unavailable_reason(): string {
		$missing = $this->config->get_missing_requirements();

		if (empty($missing)) {
			return '';
		}

		return sprintf(
			/* translators: %s: list of missing configuration items. */
			__('Cloudflare AI Gateway is not ready: %s', 'ai-post-scheduler'),
			implode(' ', $missing)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function generate_text(string $prompt, array $params): string {
		$payload = $this->build_chat_payload($prompt, $params);

		try {
			$response = $this->client->chat($payload, array('call_type' => 'text', 'skip_cache' => true));
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			throw new Exception($e->to_prefixed_message());
		}

		return $this->extract_chat_text($response);
	}

	/**
	 * No native structured-JSON path yet.
	 *
	 * Cloudflare's OpenAI-compatible endpoint documents response_format with
	 * json_schema, but the exact chat-completions response shape is not yet
	 * confirmed against a live gateway (Slice 0). Returning null makes
	 * AIPS_AI_Service fall back to its text-based JSON generation + extraction,
	 * which routes through generate_text() above and works today. Native JSON
	 * can be switched on once the live response shape is verified.
	 *
	 * @inheritDoc
	 */
	public function generate_json(?string $prompt, array $params): ?array {
		return null;
	}

	/**
	 * Generate an image through Workers AI (or a third-party image model) via
	 * /ai/run, returning a data URI the existing image pipeline can sideload.
	 *
	 * Image generation uses the configured image model, not the text model: the
	 * service forwards no 'model' for image calls, so params['model'] is honoured
	 * only when a caller sets it explicitly.
	 *
	 * @inheritDoc
	 */
	public function generate_image(string $prompt, array $params): string {
		$model = !empty($params['model']) ? (string) $params['model'] : $this->config->get_image_model();

		if ($model === '') {
			throw new Exception('invalid_request_error: ' . __('No Cloudflare image model is configured.', 'ai-post-scheduler'));
		}

		$input = array('prompt' => $prompt);

		// Forward the common optional image inputs when a caller provides them.
		foreach (array('steps', 'width', 'height', 'seed', 'negative_prompt', 'guidance') as $key) {
			if (isset($params[$key]) && is_scalar($params[$key])) {
				$input[$key] = $params[$key];
			}
		}

		try {
			$response = $this->client->run($model, $input, array('call_type' => 'image', 'skip_cache' => true));
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			throw new Exception($e->to_prefixed_message());
		}

		return $this->extract_image($response);
	}

	/**
	 * Generate an embedding vector through Workers AI via /ai/run.
	 *
	 * Uses the configured Cloudflare embedding model. The generic
	 * 'aips_embeddings_model' option (default 'text-embedding-3-small') is an
	 * OpenAI-style name that is not a Cloudflare model, so params['model'] is
	 * ignored here on purpose.
	 *
	 * @inheritDoc
	 */
	public function generate_embedding(string $text, array $params): array {
		$model = $this->config->get_embedding_model();

		if ($model === '') {
			throw new Exception('embeddings_not_supported: ' . __('No Cloudflare embedding model is configured.', 'ai-post-scheduler'));
		}

		try {
			$response = $this->client->run($model, array('text' => $text), array('call_type' => 'embedding'));
		} catch (AIPS_Cloudflare_AI_Gateway_Exception $e) {
			throw new Exception($e->to_prefixed_message());
		}

		return $this->extract_embedding($response);
	}

	/**
	 * @inheritDoc
	 */
	public function supports_native_json(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function supports_embeddings(): bool {
		return $this->config->get_embedding_model() !== '';
	}

	/**
	 * @inheritDoc
	 */
	public function supports_conversation(): bool {
		return true;
	}

	/**
	 * Recover the canonical AIPS code from a thrown message.
	 *
	 * generate_*() re-throw as "code: message" (via
	 * AIPS_Cloudflare_AI_Gateway_Exception::to_prefixed_message()); recover that
	 * prefix when present, otherwise fall back to free-text classification.
	 *
	 * @inheritDoc
	 */
	public function extract_error_code(string $message): string {
		if (preg_match('/^([a-z0-9_\-]+):\s/i', $message, $matches)) {
			return $matches[1];
		}

		return AIPS_Resilience_Service::extract_error_code_from_message($message);
	}

	// ---------------------------------------------------------------------
	// Internals
	// ---------------------------------------------------------------------

	/**
	 * Build the OpenAI-compatible chat request body from canonical parameters.
	 *
	 * @param string $prompt Prompt text.
	 * @param array  $params Canonical parameters.
	 * @return array
	 */
	private function build_chat_payload(string $prompt, array $params): array {
		$model = !empty($params['model']) ? (string) $params['model'] : $this->config->get_text_model();

		$messages = array();

		$system = $this->build_system_instruction($params);
		if ($system !== '') {
			$messages[] = array('role' => 'system', 'content' => $system);
		}

		// Prior turns, so a follow-up prompt can refer back to earlier output.
		if (!empty($params['messages']) && is_array($params['messages'])) {
			foreach ($params['messages'] as $turn) {
				if (!is_array($turn) || !isset($turn['role'], $turn['text']) || trim((string) $turn['text']) === '') {
					throw new Exception('invalid_request_error: ' . __('Conversation history contains a malformed turn.', 'ai-post-scheduler'));
				}

				$role       = ($turn['role'] === AIPS_AI_Conversation::ROLE_MODEL) ? 'assistant' : 'user';
				$messages[] = array('role' => $role, 'content' => (string) $turn['text']);
			}
		}

		$messages[] = array('role' => 'user', 'content' => $prompt);

		$payload = array(
			'model'    => $model,
			'messages' => $messages,
		);

		$max_tokens = isset($params['max_tokens']) ? $params['max_tokens'] : (isset($params['maxTokens']) ? $params['maxTokens'] : null);
		if ($max_tokens !== null && (int) $max_tokens > 0) {
			$payload['max_tokens'] = (int) $max_tokens;
		}

		if (isset($params['temperature']) && $params['temperature'] !== '') {
			$payload['temperature'] = (float) $params['temperature'];
		}

		return $payload;
	}

	/**
	 * Join the canonical 'context' and 'instructions' into a system message.
	 *
	 * @param array $params Canonical parameters.
	 * @return string
	 */
	private function build_system_instruction(array $params): string {
		$parts = array();

		foreach (array('context', 'instructions') as $key) {
			if (!isset($params[$key]) || !is_string($params[$key])) {
				continue;
			}

			$value = trim($params[$key]);
			if ($value !== '') {
				$parts[] = $value;
			}
		}

		return implode("\n\n", $parts);
	}

	/**
	 * Pull the assistant text out of a chat-completions response.
	 *
	 * Handles content as a plain string or as an array of parts (some providers
	 * return the latter through the OpenAI-compatible surface).
	 *
	 * @param array $response Decoded chat response.
	 * @return string
	 * @throws Exception When no text can be found.
	 */
	private function extract_chat_text(array $response): string {
		$content = $response['choices'][0]['message']['content'] ?? null;

		if (is_string($content) && $content !== '') {
			return $content;
		}

		if (is_array($content)) {
			$text = '';
			foreach ($content as $part) {
				if (is_string($part)) {
					$text .= $part;
				} elseif (is_array($part) && isset($part['text']) && is_string($part['text'])) {
					$text .= $part['text'];
				}
			}

			if (trim($text) !== '') {
				return $text;
			}
		}

		throw new Exception('cf_service_error: ' . __('Cloudflare returned a response with no text content.', 'ai-post-scheduler'));
	}

	/**
	 * Turn an /ai/run image response into a usable image string.
	 *
	 * Workers AI image models (e.g. flux-1-schnell) return base64 in an `image`
	 * field, usually nested under `result`. Some models return an http URL or a
	 * `b64_json` field instead; all of those are handled.
	 *
	 * @param array $response Decoded /ai/run response.
	 * @return string Data URI, or an http(s) URL.
	 * @throws Exception When no image can be found.
	 */
	private function extract_image(array $response): string {
		$result = (isset($response['result']) && is_array($response['result'])) ? $response['result'] : $response;

		// A direct http(s) URL (some providers return one instead of bytes).
		foreach (array($result['url'] ?? null, $result['data'][0]['url'] ?? null, $response['url'] ?? null) as $maybe_url) {
			if (is_string($maybe_url) && preg_match('#^https?://#i', $maybe_url)) {
				return $maybe_url;
			}
		}

		// Base64 payload, across the shapes different models use.
		$candidates = array(
			$result['image'] ?? null,
			$result['images'][0] ?? null,
			$result['data'][0]['b64_json'] ?? null,
			$result['b64_json'] ?? null,
			$response['image'] ?? null,
		);

		foreach ($candidates as $candidate) {
			if (!is_string($candidate) || $candidate === '') {
				continue;
			}

			// Already a data URI.
			if (strpos($candidate, 'data:') === 0) {
				return $candidate;
			}

			$data_uri = $this->base64_to_data_uri($candidate);
			if ($data_uri !== '') {
				return $data_uri;
			}
		}

		throw new Exception('cf_service_error: ' . __('Cloudflare returned a response with no image data.', 'ai-post-scheduler'));
	}

	/**
	 * Wrap a base64 image payload in a data URI, detecting the MIME type from the
	 * decoded magic bytes (defaulting to JPEG, which is what Cloudflare's flux
	 * models return).
	 *
	 * @param string $base64 Base64 string (may contain whitespace).
	 * @return string Data URI, or '' when the payload is not valid base64.
	 */
	private function base64_to_data_uri(string $base64): string {
		$clean = preg_replace('/\s+/', '', $base64);
		if ($clean === '' || !preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $clean)) {
			return '';
		}

		$bytes = base64_decode($clean, true);
		if ($bytes === false || $bytes === '') {
			return '';
		}

		$mime = 'image/jpeg';
		if (strncmp($bytes, "\x89PNG", 4) === 0) {
			$mime = 'image/png';
		} elseif (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) {
			$mime = 'image/jpeg';
		} elseif (strncmp($bytes, 'GIF8', 4) === 0) {
			$mime = 'image/gif';
		} elseif (strncmp($bytes, 'RIFF', 4) === 0 && strpos(substr($bytes, 8, 4), 'WEBP') !== false) {
			$mime = 'image/webp';
		}

		return 'data:' . $mime . ';base64,' . $clean;
	}

	/**
	 * Pull a single embedding vector out of an /ai/run embeddings response.
	 *
	 * Handles the Workers AI shape ({result:{shape,data:[[...]]}}) and the
	 * OpenAI-compatible shape ({data:[{embedding:[...]}]}). A single text in
	 * means a single vector out, so the first vector is returned.
	 *
	 * @param array $response Decoded /ai/run response.
	 * @return float[] Embedding vector.
	 * @throws Exception When no vector can be found.
	 */
	private function extract_embedding(array $response): array {
		$result = (isset($response['result']) && is_array($response['result'])) ? $response['result'] : $response;

		$candidates = array(
			$result['data'][0] ?? null,
			$result['data'][0]['embedding'] ?? null,
			$response['data'][0]['embedding'] ?? null,
			$result['embedding'] ?? null,
		);

		foreach ($candidates as $vector) {
			if ($this->is_numeric_vector($vector)) {
				return array_map('floatval', $vector);
			}
		}

		throw new Exception('cf_service_error: ' . __('Cloudflare returned a response with no embedding vector.', 'ai-post-scheduler'));
	}

	/**
	 * Whether a value is a non-empty list of numbers.
	 *
	 * @param mixed $value Value to test.
	 * @return bool
	 */
	private function is_numeric_vector($value): bool {
		if (!is_array($value) || $value === array()) {
			return false;
		}

		foreach ($value as $item) {
			if (!is_int($item) && !is_float($item)) {
				return false;
			}
		}

		return true;
	}
}
