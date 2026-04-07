<?php
/**
 * AI Engine Provider
 *
 * Wrapper for Meow Apps AI Engine plugin.
 * Implements the AIPS_AI_Provider interface using the existing AI Engine integration.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_AI_Engine_Provider
 *
 * Provides AI functionality through Meow Apps AI Engine plugin.
 * This is the "legacy" provider that maintains backward compatibility.
 */
class AIPS_AI_Engine_Provider implements AIPS_AI_Provider {

	/**
	 * @var mixed AI Engine instance
	 */
	private $ai_engine;

	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;

	/**
	 * Initialize the AI Engine provider.
	 *
	 * @param mixed       $ai_engine Optional. AI Engine instance (defaults to global $mwai).
	 * @param AIPS_Logger $logger    Optional. Logger instance.
	 */
	public function __construct($ai_engine = null, $logger = null) {
		$this->logger = $logger ?: new AIPS_Logger();

		if ($ai_engine !== null) {
			$this->ai_engine = $ai_engine;
		}
	}

	/**
	 * Get the AI Engine instance.
	 *
	 * Lazy-loads the AI Engine from global scope if not injected.
	 *
	 * @return mixed|null The AI Engine instance or null if not available.
	 */
	private function get_ai_engine() {
		if ($this->ai_engine === null) {
			global $mwai;
			$this->ai_engine = $mwai;
		}

		return $this->ai_engine;
	}

	/**
	 * Check if AI Engine is available and ready to use.
	 *
	 * @return bool True if AI Engine is available, false otherwise.
	 */
	public function is_available() {
		return $this->get_ai_engine() !== null;
	}

	/**
	 * Get the provider name.
	 *
	 * @return string The human-readable provider name.
	 */
	public function get_name() {
		return __('Meow Apps AI Engine', 'ai-post-scheduler');
	}

	/**
	 * Get the provider identifier.
	 *
	 * @return string The provider identifier.
	 */
	public function get_identifier() {
		return 'ai-engine';
	}

	/**
	 * Generate text content using AI Engine.
	 *
	 * @param string $prompt  The prompt to send to the AI.
	 * @param array  $options Optional. AI generation options.
	 * @return string|WP_Error The generated content or WP_Error on failure.
	 */
	public function generate_text($prompt, $options = array()) {
		$ai = $this->get_ai_engine();

		if (!$ai) {
			return new WP_Error(
				'ai_unavailable',
				__('AI Engine plugin is not available.', 'ai-post-scheduler')
			);
		}

		try {
			$params = $this->prepare_params($options);
			$result = $ai->simpleTextQuery($prompt, $params);

			if ($result && !empty($result)) {
				return $result;
			}

			return new WP_Error(
				'empty_response',
				__('AI Engine returned an empty response.', 'ai-post-scheduler')
			);

		} catch (Exception $e) {
			$this->logger->log('AI Engine text generation failed: ' . $e->getMessage(), 'error');
			return new WP_Error('generation_failed', $e->getMessage());
		}
	}

	/**
	 * Generate structured JSON data using AI Engine.
	 *
	 * @param string $prompt  The prompt to send to the AI.
	 * @param array  $options Optional. AI generation options.
	 * @return array|WP_Error The parsed JSON data or WP_Error on failure.
	 */
	public function generate_json($prompt, $options = array()) {
		$ai = $this->get_ai_engine();

		if (!$ai) {
			return new WP_Error(
				'ai_unavailable',
				__('AI Engine plugin is not available.', 'ai-post-scheduler')
			);
		}

		// Try simpleJsonQuery if available
		if (method_exists($ai, 'simpleJsonQuery')) {
			try {
				$params = $this->prepare_json_params($options);
				$result = $ai->simpleJsonQuery($prompt, $params);

				if (empty($result)) {
					return new WP_Error(
						'empty_response',
						__('AI Engine returned an empty JSON response.', 'ai-post-scheduler')
					);
				}

				if (!is_array($result)) {
					return new WP_Error(
						'invalid_json',
						__('AI Engine did not return valid JSON data.', 'ai-post-scheduler')
					);
				}

				return $result;

			} catch (Exception $e) {
				$this->logger->log('AI Engine simpleJsonQuery failed, falling back to text: ' . $e->getMessage(), 'info');
				// Fall through to text-based fallback
			}
		}

		// Fallback to text-based JSON generation
		return $this->fallback_json_generation($prompt, $options);
	}

	/**
	 * Generate an image using AI Engine.
	 *
	 * @param string $prompt  The image generation prompt.
	 * @param array  $options Optional. AI generation options.
	 * @return string|WP_Error The image URL or WP_Error on failure.
	 */
	public function generate_image($prompt, $options = array()) {
		$ai = $this->get_ai_engine();

		if (!$ai) {
			return new WP_Error(
				'ai_unavailable',
				__('AI Engine plugin is not available.', 'ai-post-scheduler')
			);
		}

		try {
			$params = !empty($options) ? $options : array();
			$image_url = $ai->simpleImageQuery($prompt, $params);

			if (!$image_url || empty($image_url)) {
				return new WP_Error(
					'empty_response',
					__('AI Engine returned an empty response for image generation.', 'ai-post-scheduler')
				);
			}

			// Handle array response (some AI engines return arrays)
			if (is_array($image_url) && !empty($image_url[0])) {
				$image_url = $image_url[0];
			}

			if (empty($image_url)) {
				return new WP_Error(
					'no_image_url',
					__('No image URL in AI response.', 'ai-post-scheduler')
				);
			}

			return $image_url;

		} catch (Exception $e) {
			$this->logger->log('AI Engine image generation failed: ' . $e->getMessage(), 'error');
			return new WP_Error('generation_failed', $e->getMessage());
		}
	}

	/**
	 * Generate an embedding vector for text using AI Engine.
	 *
	 * @param string $text    The text to generate an embedding for.
	 * @param array  $options Optional. Embedding generation options.
	 * @return array|WP_Error The embedding vector or WP_Error on failure.
	 */
	public function generate_embedding($text, $options = array()) {
		$ai = $this->get_ai_engine();

		if (!$ai) {
			return new WP_Error(
				'ai_unavailable',
				__('AI Engine plugin is not available.', 'ai-post-scheduler')
			);
		}

		if (!class_exists('Meow_MWAI_Query_Embed')) {
			return new WP_Error(
				'embeddings_not_supported',
				__('Embeddings are not supported by the current AI Engine version.', 'ai-post-scheduler')
			);
		}

		try {
			$query = new Meow_MWAI_Query_Embed($text);

			// Set embeddings environment if specified
			if (!empty($options['embeddings_env_id']) && method_exists($query, 'set_embeddings_env_id')) {
				$query->set_embeddings_env_id($options['embeddings_env_id']);
			}

			$response = $ai->run_query($query);

			if ($response && !empty($response->result)) {
				return $response->result;
			}

			return new WP_Error(
				'empty_response',
				__('AI Engine returned an empty embedding response.', 'ai-post-scheduler')
			);

		} catch (Exception $e) {
			$this->logger->log('AI Engine embedding generation failed: ' . $e->getMessage(), 'error');
			return new WP_Error('embedding_failed', $e->getMessage());
		}
	}

	/**
	 * Prepare parameters for AI Engine text queries.
	 *
	 * @param array $options User-provided options.
	 * @return array Normalized parameters.
	 */
	private function prepare_params($options) {
		$params = array();

		if (!empty($options['model'])) {
			$params['model'] = $options['model'];
		}

		if (!empty($options['envId'])) {
			$params['envId'] = $options['envId'];
		} elseif (!empty($options['env_id'])) {
			$params['envId'] = $options['env_id'];
		}

		if (isset($options['maxTokens'])) {
			$params['maxTokens'] = $options['maxTokens'];
		} elseif (isset($options['max_tokens'])) {
			$params['maxTokens'] = $options['max_tokens'];
		}

		if (isset($options['temperature'])) {
			$params['temperature'] = $options['temperature'];
		}

		// Forward optional advanced options
		$optional_keys = array('context', 'instructions', 'messages', 'api_key');
		foreach ($optional_keys as $key) {
			if (isset($options[$key])) {
				$params[$key] = $options[$key];
			}
		}

		return $params;
	}

	/**
	 * Prepare parameters for AI Engine JSON queries.
	 *
	 * simpleJsonQuery has a limited parameter set, so we filter accordingly.
	 *
	 * @param array $options User-provided options.
	 * @return array Normalized parameters.
	 */
	private function prepare_json_params($options) {
		$params = array();

		if (!empty($options['model'])) {
			$params['model'] = $options['model'];
		}

		if (!empty($options['envId'])) {
			$params['env_id'] = $options['envId'];
		} elseif (!empty($options['env_id'])) {
			$params['env_id'] = $options['env_id'];
		}

		return $params;
	}

	/**
	 * Fallback JSON generation using text query with JSON parsing.
	 *
	 * @param string $prompt  The prompt to send to the AI.
	 * @param array  $options Optional. AI generation options.
	 * @return array|WP_Error The parsed JSON data or WP_Error on failure.
	 */
	private function fallback_json_generation($prompt, $options = array()) {
		$this->logger->log('Using fallback JSON generation', 'info');

		// Generate text response
		$text_response = $this->generate_text($prompt, $options);

		if (is_wp_error($text_response)) {
			return $text_response;
		}

		// Clean and parse JSON
		$json_str = trim($text_response);

		// Remove potential markdown code blocks
		$json_str = preg_replace('/^```json\s*/m', '', $json_str);
		$json_str = preg_replace('/^```\s*/m', '', $json_str);
		$json_str = preg_replace('/```$/m', '', $json_str);
		$json_str = trim($json_str);

		// Decode JSON
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
}
