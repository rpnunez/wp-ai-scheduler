<?php
/**
 * Custom AI Provider
 *
 * Implementation for custom OpenAI-compatible AI APIs.
 * Supports text generation, JSON generation, and image generation through standard endpoints.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Custom_AI_Provider
 *
 * Provides AI functionality through a custom OpenAI-compatible API.
 * Uses wp_remote_post() for HTTP communication.
 */
class AIPS_Custom_AI_Provider implements AIPS_AI_Provider {

	/**
	 * @var string Base URL for the custom API
	 */
	private $api_url;

	/**
	 * @var string API key for authentication
	 */
	private $api_key;

	/**
	 * @var string Default model to use
	 */
	private $model;

	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;

	/**
	 * Initialize the custom AI provider.
	 *
	 * @param string      $api_url API base URL.
	 * @param string      $api_key API key.
	 * @param string      $model   Default model name.
	 * @param AIPS_Logger $logger  Optional. Logger instance.
	 */
	public function __construct($api_url, $api_key, $model = '', $logger = null) {
		$this->api_url = rtrim($api_url, '/');
		$this->api_key = $api_key;
		$this->model = $model;
		$this->logger = $logger ?: new AIPS_Logger();
	}

	/**
	 * Check if the custom API is available.
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public function is_available() {
		return !empty($this->api_url) && !empty($this->api_key);
	}

	/**
	 * Get the provider name.
	 *
	 * @return string The human-readable provider name.
	 */
	public function get_name() {
		return __('Custom AI API', 'ai-post-scheduler');
	}

	/**
	 * Get the provider identifier.
	 *
	 * @return string The provider identifier.
	 */
	public function get_identifier() {
		return 'custom';
	}

	/**
	 * Generate text content using the custom API.
	 *
	 * @param string $prompt  The prompt to send to the AI.
	 * @param array  $options Optional. AI generation options.
	 * @return string|WP_Error The generated content or WP_Error on failure.
	 */
	public function generate_text($prompt, $options = array()) {
		if (!$this->is_available()) {
			return new WP_Error(
				'api_not_configured',
				__('Custom AI API is not properly configured.', 'ai-post-scheduler')
			);
		}

		$endpoint = $this->api_url . '/v1/chat/completions';
		$model = isset($options['model']) ? $options['model'] : $this->model;

		$body = array(
			'model' => $model,
			'messages' => array(
				array(
					'role' => 'user',
					'content' => $prompt,
				),
			),
		);

		// Add optional parameters
		if (isset($options['temperature'])) {
			$body['temperature'] = (float) $options['temperature'];
		}

		if (isset($options['max_tokens']) || isset($options['maxTokens'])) {
			$body['max_tokens'] = isset($options['max_tokens']) ? (int) $options['max_tokens'] : (int) $options['maxTokens'];
		}

		$response = $this->make_request($endpoint, $body);

		if (is_wp_error($response)) {
			return $response;
		}

		// Extract text from OpenAI-compatible response format
		if (isset($response['choices'][0]['message']['content'])) {
			return $response['choices'][0]['message']['content'];
		}

		return new WP_Error(
			'invalid_response',
			__('Invalid response format from custom API.', 'ai-post-scheduler')
		);
	}

	/**
	 * Generate structured JSON data using the custom API.
	 *
	 * @param string $prompt  The prompt to send to the AI.
	 * @param array  $options Optional. AI generation options.
	 * @return array|WP_Error The parsed JSON data or WP_Error on failure.
	 */
	public function generate_json($prompt, $options = array()) {
		if (!$this->is_available()) {
			return new WP_Error(
				'api_not_configured',
				__('Custom AI API is not properly configured.', 'ai-post-scheduler')
			);
		}

		$endpoint = $this->api_url . '/v1/chat/completions';
		$model = isset($options['model']) ? $options['model'] : $this->model;

		$body = array(
			'model' => $model,
			'messages' => array(
				array(
					'role' => 'user',
					'content' => $prompt,
				),
			),
			'response_format' => array('type' => 'json_object'),
		);

		// Add optional parameters
		if (isset($options['temperature'])) {
			$body['temperature'] = (float) $options['temperature'];
		}

		if (isset($options['max_tokens']) || isset($options['maxTokens'])) {
			$body['max_tokens'] = isset($options['max_tokens']) ? (int) $options['max_tokens'] : (int) $options['maxTokens'];
		}

		$response = $this->make_request($endpoint, $body);

		if (is_wp_error($response)) {
			return $response;
		}

		// Extract JSON from response
		if (isset($response['choices'][0]['message']['content'])) {
			$json_str = $response['choices'][0]['message']['content'];

			// Parse JSON
			$data = json_decode($json_str, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				$this->logger->log('JSON parse error: ' . json_last_error_msg(), 'error', array(
					'response_preview' => substr($json_str, 0, 200),
				));

				return new WP_Error(
					'json_parse_error',
					sprintf(
						__('Failed to parse JSON: %s', 'ai-post-scheduler'),
						json_last_error_msg()
					)
				);
			}

			if (!is_array($data)) {
				return new WP_Error(
					'invalid_json_format',
					__('Parsed JSON is not in expected array format.', 'ai-post-scheduler')
				);
			}

			return $data;
		}

		return new WP_Error(
			'invalid_response',
			__('Invalid response format from custom API.', 'ai-post-scheduler')
		);
	}

	/**
	 * Generate an image using the custom API.
	 *
	 * @param string $prompt  The image generation prompt.
	 * @param array  $options Optional. AI generation options.
	 * @return string|WP_Error The image URL or WP_Error on failure.
	 */
	public function generate_image($prompt, $options = array()) {
		if (!$this->is_available()) {
			return new WP_Error(
				'api_not_configured',
				__('Custom AI API is not properly configured.', 'ai-post-scheduler')
			);
		}

		$endpoint = $this->api_url . '/v1/images/generations';
		$model = isset($options['model']) ? $options['model'] : $this->model;

		$body = array(
			'prompt' => $prompt,
		);

		// Add model if specified
		if (!empty($model)) {
			$body['model'] = $model;
		}

		// Add optional parameters
		if (isset($options['size'])) {
			$body['size'] = $options['size'];
		}

		if (isset($options['quality'])) {
			$body['quality'] = $options['quality'];
		}

		if (isset($options['n'])) {
			$body['n'] = (int) $options['n'];
		}

		$response = $this->make_request($endpoint, $body);

		if (is_wp_error($response)) {
			return $response;
		}

		// Extract image URL from OpenAI-compatible response format
		if (isset($response['data'][0]['url'])) {
			return $response['data'][0]['url'];
		}

		return new WP_Error(
			'invalid_response',
			__('Invalid response format from custom API.', 'ai-post-scheduler')
		);
	}

	/**
	 * Generate an embedding vector for text using the custom API.
	 *
	 * @param string $text    The text to generate an embedding for.
	 * @param array  $options Optional. Embedding generation options.
	 * @return array|WP_Error The embedding vector or WP_Error on failure.
	 */
	public function generate_embedding($text, $options = array()) {
		if (!$this->is_available()) {
			return new WP_Error(
				'api_not_configured',
				__('Custom AI API is not properly configured.', 'ai-post-scheduler')
			);
		}

		$endpoint = $this->api_url . '/v1/embeddings';
		$model = isset($options['model']) ? $options['model'] : $this->model;

		$body = array(
			'input' => $text,
			'model' => $model,
		);

		$response = $this->make_request($endpoint, $body);

		if (is_wp_error($response)) {
			return $response;
		}

		// Extract embedding from OpenAI-compatible response format
		if (isset($response['data'][0]['embedding'])) {
			return $response['data'][0]['embedding'];
		}

		return new WP_Error(
			'invalid_response',
			__('Invalid response format from custom API.', 'ai-post-scheduler')
		);
	}

	/**
	 * Make an HTTP request to the custom API.
	 *
	 * @param string $endpoint API endpoint URL.
	 * @param array  $body     Request body.
	 * @return array|WP_Error Decoded response or WP_Error on failure.
	 */
	private function make_request($endpoint, $body) {
		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'body' => wp_json_encode($body),
			'timeout' => 60,
			'method' => 'POST',
		);

		$this->logger->log('Making request to custom API', 'debug', array(
			'endpoint' => $endpoint,
			'body' => $body,
		));

		$response = wp_remote_post($endpoint, $args);

		if (is_wp_error($response)) {
			$this->logger->log('Custom API request failed: ' . $response->get_error_message(), 'error');
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		if ($status_code < 200 || $status_code >= 300) {
			$this->logger->log('Custom API returned error status: ' . $status_code, 'error', array(
				'response_body' => $response_body,
			));

			// Try to extract error message from response
			$error_data = json_decode($response_body, true);
			$error_message = isset($error_data['error']['message'])
				? $error_data['error']['message']
				: sprintf(__('API request failed with status code %d', 'ai-post-scheduler'), $status_code);

			return new WP_Error('api_error', $error_message);
		}

		$data = json_decode($response_body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->logger->log('Failed to parse API response: ' . json_last_error_msg(), 'error');
			return new WP_Error(
				'invalid_response',
				__('Failed to parse API response.', 'ai-post-scheduler')
			);
		}

		return $data;
	}
}
