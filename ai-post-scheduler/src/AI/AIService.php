<?php
/**
 * AI Service Layer
 *
 * Abstracts AI Engine interactions and provides a clean interface for AI operations.
 * Separates AI communication logic from content generation orchestration.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

namespace AIPS\AI;

if (!defined('ABSPATH')) {
	exit;
}

use AIPS\Core\Container;
use AIPS\Core\Config;
use AIPS\Core\DateTime;
use AIPS\Core\LoggerInterface;
use AIPS\Core\Logger;
use AIPS\Services\ResilienceService;
use WP_Error;
use Exception;

/**
 * Class AIService
 *
 * Provides AI content generation capabilities through AI Engine integration.
 * Handles error recovery, logging, and provides a consistent interface for AI operations.
 */
class AIService implements AIServiceInterface {

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @var mixed AI Engine instance
	 */
	private $ai_engine;

	/**
	 * @var LoggerInterface Logger instance
	 */
	private $logger;

	/**
	 * @var array Array to store AI call logs for debugging
	 */
	private $call_log;

	/**
	 * @var Config Configuration manager
	 */
	private $config;

	/**
	 * @var ResilienceService Resilience service
	 */
	private $resilience_service;

	/**
	 * Optional query option keys supported by AI Engine.
	 */
	private const OPTIONAL_QUERY_OPTION_KEYS = array(
		'context',
		'instructions',
		'messages',
		'env_id',
		'embeddings_env_id',
		'max_results',
		'api_key',
	);

	/**
	 * Initialize the AI Service.
	 */
	public function __construct(?LoggerInterface $logger = null, $config = null, $resilience_service = null) {
		if ($logger) {
			$this->logger = $logger;
		} else {
			$container = Container::get_instance();
			if ($container->has(LoggerInterface::class)) {
				$this->logger = $container->make(LoggerInterface::class);
			} else {
				$this->logger = Logger::instance();
			}
		}
		$this->config = $config ?: Config::get_instance();
		$this->resilience_service = $resilience_service ?: new ResilienceService($this->logger, $this->config);

		$this->call_log = array();
	}

	/**
	 * Get the AI Engine instance.
	 *
	 * Lazy-loads the AI Engine and caches it for reuse.
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
	 * Generate text content using AI.
	 *
	 * Sends a text prompt to the AI Engine and returns the generated content.
	 * Includes retry logic, circuit breaker, and rate limiting.
	 *
	 * @param string $prompt  The prompt to send to the AI.
	 * @param array  $options Optional. AI generation options (model, max_tokens, temperature).
	 * @return string|WP_Error The generated content or WP_Error on failure.
	 */
	public function generate_text($prompt, $options = array()) {
		$ai = $this->get_ai_engine();

		if (!$ai) {
			$error = new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
			$this->log_call('text', $prompt, $options, $error);
			$this->emit_integration_error_notification('text', $error, $options);
			return $error;
		}

		$params = $this->prepare_options($options, $prompt);

		$log_context = array(
			'model' => isset($params['model']) ? $params['model'] : '',
			'max_tokens' => isset($params['maxTokens']) ? $params['maxTokens'] : (isset($params['max_tokens']) ? $params['max_tokens'] : ''),
			'temperature' => isset($params['temperature']) ? $params['temperature'] : '',
			'prompt_length' => is_string($prompt) ? strlen($prompt) : 0,
			'has_prompt' => !empty($prompt),
		);

		if (defined('AIPS_AI_DEBUG_LOG_PROMPTS') && AIPS_AI_DEBUG_LOG_PROMPTS) {
			$prompt_preview = is_string($prompt) ? substr($prompt, 0, 500) : '';

			if (is_string($prompt) && strlen($prompt) > 500) {
				$prompt_preview .= '... [truncated]';
			}

			$log_context['prompt_preview'] = $prompt_preview;
			$log_context['options_keys'] = array_keys($options);
			$log_context['params_keys'] = array_keys($params);
		}

		$this->logger->addSeparator('[AIService->generate_text] New AI Text Generation Request');
		$this->logger->log(
			'Calling AI Engine for text generation: ' . wp_json_encode($log_context),
			'info'
		);

		// Execute safely with retry, circuit breaker, and rate limiting.
		$result = $this->resilience_service->execute_safely(function() use ($ai, $prompt, $options, $params) {
			try {
				// Use simpleTextQuery API method
				$result = $ai->simpleTextQuery($prompt, $params);

				$this->logger->log('Received response from simpleTextQuery', 'debug', array(
					'response' => $result,
				));

				if ($result && !empty($result)) {
					$this->log_call('text', $prompt, $options, null, $result);
					return $result;
				}

				$error = new WP_Error('empty_response', __('AI Engine returned an empty response.', 'ai-post-scheduler'));
				$this->log_call('text', $prompt, $options, $error);
				return $error;

			} catch (Exception $e) {
				$provider_code = ResilienceService::extract_error_code_from_message($e->getMessage());
				$error = new WP_Error($provider_code ?: 'generation_failed', $e->getMessage());
				$this->log_call('text', $prompt, $options, $error);
				return $error;
			}
		}, 'text', $prompt, $options);

		// Log resilience failures (circuit breaker, rate limit)
		if (is_wp_error($result)) {
			$code = $result->get_error_code();

			if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
				$this->log_call('text', $prompt, $options, $result);
				$this->emit_quota_alert_notification('text', $result, $options);
			}
		}

		return $result;
	}

	/**
	 * Generate structured JSON data using AI.
	 *
	 * Uses AI Engine's simpleJsonQuery method for structured data generation.
	 * This is particularly useful for generating lists, topics, or any structured data
	 * that needs to be reliably parsed as JSON.
	 *
	 * @param string $prompt  The prompt to send to the AI.
	 * @param array  $options Optional. AI generation options (model, max_tokens, temperature).
	 * @return array|WP_Error The parsed JSON data as an array, or WP_Error on failure.
	 */
	public function generate_json($prompt, $options = array()) {
		$ai = $this->get_ai_engine();

		$this->logger->log(
			sprintf(
				'Attempting to generate JSON with AI Engine. AI available: %s',
				$ai ? 'Yes' : 'No'
			),
			'info'
		);

		if (!$ai) {
			$this->logger->log('AI Engine is not available.', 'error');
		}

		if (!$ai) {
			$error = new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));

			$this->log_call('json', $prompt, $options, $error);
			$this->emit_integration_error_notification('json', $error, $options);

			return $error;
		}

		// If $ai doesn't have simpleJsonQuery, fall back to text-based JSON
		if (!method_exists($ai, 'simpleJsonQuery')) {
			$this->logger->log('Using fallback JSON generation (simpleJsonQuery not available)', 'info');

			return $this->fallback_json_generation($prompt, $options);
		}

		$params = $this->prepare_options($options, $prompt);

		// Execute safely with retry, circuit breaker, and rate limiting.
		$result = $this->resilience_service->execute_safely(function() use ($ai, $prompt, $options, $params) {
			try {
				$json_query_params = array();

				if (!empty($params['model'])) {
					$json_query_params['model'] = $params['model'];
				}

				if (isset($params['env_id'])) {
					$json_query_params['env_id'] = $params['env_id'];
				} elseif (isset($params['envId'])) {
					$json_query_params['env_id'] = $params['envId'];
				}

				$this->logger->log('Calling simpleJsonQuery with params: ' . wp_json_encode(array_keys($json_query_params)), 'debug');

				$result = $ai->simpleJsonQuery($prompt, $json_query_params);

				$this->logger->log('AI Engine simpleJsonQuery response: ' . print_r($result, true), 'debug');

				if (empty($result)) {
					$error = new WP_Error('empty_response', __('AI Engine returned an empty JSON response.', 'ai-post-scheduler'));

					$this->logger->log('AI Engine returned empty response for simpleJsonQuery.', 'error');

					$this->log_call('json', $prompt, $options, $error);

					return $error;
				}

				if (!is_array($result)) {
					$error = new WP_Error('invalid_json', __('AI Engine did not return valid JSON data.', 'ai-post-scheduler'));

					$this->logger->log('AI Engine returned invalid JSON data for simpleJsonQuery.', 'error', array(
						'response_preview' => substr(print_r($result, true), 0, 200),
					));

					$this->log_call('json', $prompt, $options, $error);

					return $error;
				}

				$this->log_call('json', $prompt, $options, null, wp_json_encode($result));

				return $result;
			} catch (Exception $e) {
				$provider_code = ResilienceService::extract_error_code_from_message($e->getMessage());

				if ($provider_code !== '') {
					$error = new WP_Error($provider_code, $e->getMessage());
					$this->log_call('json', $prompt, $options, $error);
					return $error;
				}

				$this->logger->log('simpleJsonQuery failed with non-provider error, will try fallback: ' . $e->getMessage(), 'warning');

				return new WP_Error('json_query_unavailable', $e->getMessage());
			}
		}, 'json', $prompt, $options);

		if (is_wp_error($result) && $result->get_error_code() === 'json_query_unavailable') {
			$this->logger->log('Falling back to text-based JSON generation after simpleJsonQuery failure', 'info');
			return $this->fallback_json_generation($prompt, $options);
		}

		// Log resilience failures (circuit breaker, rate limit)
		if (is_wp_error($result)) {
			$code = $result->get_error_code();

			if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
				$this->log_call('json', $prompt, $options, $result);
				$this->emit_quota_alert_notification('json', $result, $options);
			}
		}

		return $result;
	}

	/**
	 * Fallback JSON generation using text query with JSON parsing.
	 *
	 * Used when simpleJsonQuery is not available. Generates text and parses as JSON.
	 *
	 * @param string $prompt  The prompt to send to the AI.
	 * @param array  $options Optional. AI generation options.
	 * @return array|WP_Error The parsed JSON data or WP_Error on failure.
	 */
	private function fallback_json_generation($prompt, $options = array()) {
		$this->logger->log('Using fallback JSON generation (simpleJsonQuery not available)', 'info');

		return $this->generate_json_from_text($prompt, $options);
	}

	/**
	 * Generate JSON via text completion with robust extraction.
	 *
	 * This path intentionally does not rely on simpleJsonQuery(). Retries,
	 * rate limiting, and circuit-breaker behavior are delegated to
	 * ResilienceService::execute_safely().
	 *
	 * @param string $prompt  Prompt instructing JSON output.
	 * @param array  $options Optional generation options.
	 * @return array|WP_Error
	 */
	public function generate_json_from_text($prompt, $options = array()) {
		$ai = $this->get_ai_engine();

		if (!$ai) {
			$error = new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));
			$this->log_call('json', $prompt, $options, $error);
			$this->emit_integration_error_notification('json', $error, $options);
			return $error;
		}

		$params = $this->prepare_options($options, $prompt);

		$log_context = array(
			'model'         => isset($params['model']) ? $params['model'] : '',
			'max_tokens'    => isset($params['maxTokens']) ? $params['maxTokens'] : (isset($params['max_tokens']) ? $params['max_tokens'] : ''),
			'temperature'   => isset($params['temperature']) ? $params['temperature'] : '',
			'prompt_length' => is_string($prompt) ? strlen($prompt) : 0,
			'has_prompt'    => !empty($prompt),
		);

		if (defined('AIPS_AI_DEBUG_LOG_PROMPTS') && AIPS_AI_DEBUG_LOG_PROMPTS) {
			$prompt_preview = is_string($prompt) ? substr($prompt, 0, 500) : '';

			if (is_string($prompt) && strlen($prompt) > 500) {
				$prompt_preview .= '... [truncated]';
			}

			$log_context['prompt_preview'] = $prompt_preview;
			$log_context['params_keys']    = array_keys($params);
		}

		$this->logger->log('Calling AI Engine for text-based JSON generation: ' . wp_json_encode($log_context), 'info');

		$result = $this->resilience_service->execute_safely(function() use ($ai, $prompt, $options, $params) {
			try {
				$text_response = $ai->simpleTextQuery($prompt, $params);

				if (!$text_response || empty($text_response)) {
					$error = new WP_Error('empty_response', __('AI Engine returned an empty response.', 'ai-post-scheduler'));
					$this->log_call('json', $prompt, $options, $error);
					return $error;
				}

				$extract_result = $this->extract_json_fragment((string) $text_response);

				if (is_wp_error($extract_result)) {
					$error = new WP_Error('json_parse_error', $extract_result->get_error_message());

					$this->logger->log('JSON extraction failed for text-based JSON generation.', 'error', array(
						'response_preview' => substr((string) $text_response, 0, 220),
						'response_full' => (string) $text_response,
					));

					$this->log_call('json', $prompt, $options, $error);

					return $error;
				}

				$data = json_decode($extract_result, true);

				if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
					$error = new WP_Error(
						'json_parse_error',
						sprintf(
							__('Failed to parse JSON: %s', 'ai-post-scheduler'),
							json_last_error_msg()
						)
					);

					$this->logger->log('JSON decode failed for text-based JSON generation.', 'error', array(
						'response_preview' => substr((string) $text_response, 0, 220),
						'response_full' => (string) $text_response,
					));

					$this->log_call('json', $prompt, $options, $error);

					return $error;
				}

				$this->log_call('json', $prompt, $options, null, wp_json_encode($data));

				return $data;
			} catch (Exception $e) {
				$provider_code = ResilienceService::extract_error_code_from_message($e->getMessage());
				$error = new WP_Error($provider_code ?: 'generation_failed', $e->getMessage());
				$this->log_call('json', $prompt, $options, $error);
				return $error;
			}
		}, 'json', $prompt, $options);

		if (is_wp_error($result)) {
			$code = $result->get_error_code();

			if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
				$this->log_call('json', $prompt, $options, $result);
				$this->emit_quota_alert_notification('json', $result, $options);
			}
		}

		return $result;
	}

	/**
	 * Extract the first balanced JSON object/array from text.
	 *
	 * @param string $text Raw AI text response.
	 * @return string|WP_Error Balanced JSON fragment or WP_Error.
	 */
	private function extract_json_fragment($text) {
		$text = trim((string) $text);

		// Remove common markdown wrappers.
		$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
		$text = preg_replace('/```\s*$/', '', $text);
		$text = trim((string) $text);

		$start_pos_obj = strpos($text, '{');
		$start_pos_arr = strpos($text, '[');

		if ($start_pos_obj === false && $start_pos_arr === false) {
			return new WP_Error('json_extract_failed', __('No JSON start token found in AI response.', 'ai-post-scheduler'));
		}

		if ($start_pos_obj === false) {
			$start_pos = $start_pos_arr;
		} elseif ($start_pos_arr === false) {
			$start_pos = $start_pos_obj;
		} else {
			$start_pos = min($start_pos_obj, $start_pos_arr);
		}

		$slice = substr($text, $start_pos);

		$in_string = false;
		$escape    = false;
		$stack     = array();
		$length    = strlen($slice);

		for ($i = 0; $i < $length; $i++) {
			$ch = $slice[$i];

			if ($in_string) {
				if ($escape) {
					$escape = false;
				} elseif ($ch === '\\') {
					$escape = true;
				} elseif ($ch === '"') {
					$in_string = false;
				}

				continue;
			}

			if ($ch === '"') {
				$in_string = true;
				continue;
			}

			if ($ch === '{' || $ch === '[') {
				$stack[] = $ch;
				continue;
			}

			if ($ch === '}' || $ch === ']') {
				if (empty($stack)) {
					return new WP_Error('json_extract_failed', __('JSON appears malformed (unexpected closing token).', 'ai-post-scheduler'));
				}

				$open = array_pop($stack);
				if (($open === '{' && $ch !== '}') || ($open === '[' && $ch !== ']')) {
					return new WP_Error('json_extract_failed', __('JSON appears malformed (mismatched tokens).', 'ai-post-scheduler'));
				}

				if (empty($stack)) {
					$candidate = substr($slice, 0, $i + 1);
					return $this->sanitize_json_candidate($candidate);
				}
			}
		}

		return new WP_Error('json_extract_failed', __('JSON appears truncated before closing token.', 'ai-post-scheduler'));
	}

	/**
	 * Normalize control characters in a candidate JSON fragment.
	 *
	 * @param string $candidate Candidate JSON fragment.
	 * @return string
	 */
	private function sanitize_json_candidate($candidate) {
		return preg_replace_callback(
			'/"((?:[^"\\\\]|\\\\.)*)"/',
			function ($m) {
				$inner = $m[1];
				$inner = str_replace("\r", '\\r', $inner);
				$inner = str_replace("\n", '\\n', $inner);
				$inner = str_replace("\t", '\\t', $inner);
				$inner = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $inner);

				return '"' . $inner . '"';
			},
			(string) $candidate
		);
	}

	/**
	 * Generate an image using AI.
	 *
	 * Sends an image prompt to the AI Engine and returns the generated image URL.
	 * Includes retry logic, circuit breaker, and rate limiting.
	 *
	 * @param string $prompt  The image generation prompt.
	 * @param array  $options Optional. AI generation options.
	 * @return string|WP_Error The image URL or WP_Error on failure.
	 */
	public function generate_image($prompt, $options = array()) {
		$ai = $this->get_ai_engine();

		if (!$ai) {
			$error = new WP_Error('ai_unavailable', __('AI Engine plugin is not available.', 'ai-post-scheduler'));

			$this->log_call('image', $prompt, $options, $error);
			$this->emit_integration_error_notification('image', $error, $options);

			return $error;
		}

		// Execute safely with retry, circuit breaker, and rate limiting.
		$result = $this->resilience_service->execute_safely(function() use ($ai, $prompt, $options) {
			try {
				$params = array();

				if (!empty($options)) {
					$params = $options;
				}

				$image_url = $ai->simpleImageQuery($prompt, $params);

				if (!$image_url || empty($image_url)) {
					$error = new WP_Error('empty_response', __('AI Engine returned an empty response for image generation.', 'ai-post-scheduler'));

					$this->log_call('image', $prompt, $options, $error);

					return $error;
				}

				if (is_array($image_url) && !empty($image_url[0])) {
					$image_url = $image_url[0];
				}

				if (empty($image_url)) {
					$error = new WP_Error('no_image_url', __('No image URL in AI response.', 'ai-post-scheduler'));

					$this->log_call('image', $prompt, $options, $error);

					return $error;
				}

				$this->log_call('image', $prompt, $options, null, $image_url);

				return $image_url;
			} catch (Exception $e) {
				$provider_code = ResilienceService::extract_error_code_from_message($e->getMessage());
				$error = new WP_Error($provider_code ?: 'generation_failed', $e->getMessage());

				$this->log_call('image', $prompt, $options, $error);

				return $error;
			}
		}, 'image', $prompt, $options);

		// Log resilience failures (circuit breaker, rate limit)
		if (is_wp_error($result)) {
			$code = $result->get_error_code();

			if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
				$this->log_call('image', $prompt, $options, $result);
				$this->emit_quota_alert_notification('image', $result, $options);
			}
		}

		return $result;
	}

	/**
	 * Calculate the appropriate maxTokens for an AI request.
	 *
	 * Combines the estimated input (prompt) token cost with the expected output
	 * size for the given request type, applies a 25% safety buffer, and caps the
	 * result at the configured aips_max_tokens_limit setting to prevent
	 * unexpectedly large or costly requests.
	 *
	 * Token estimation uses the standard approximation of 1 token ≈ 4 characters.
	 *
	 * @param string     $prompt The prompt that will be sent to the AI. Its length
	 *                           is used to estimate the input token cost.
	 * @param string|int $type   Request type: 'title', 'excerpt', 'content', or a
	 *                           custom integer expected-output token count. Unknown
	 *                           string types fall back to 'content' sizing.
	 * @return int The calculated maxTokens value (always ≥ 1).
	 */
	private function calculate_max_tokens($prompt, $type = 'content') {
		if (is_int($type) && $type > 0) {
			$output_tokens = $type;
		} else {
			$config = Config::get_instance();
			switch ($type) {
				case 'title':
					$output_tokens = (int) $config->get_option('aips_max_tokens_title');
					break;
				case 'excerpt':
					$output_tokens = (int) $config->get_option('aips_max_tokens_excerpt');
					break;
				case 'content':
				default:
					$output_tokens = (int) $config->get_option('aips_max_tokens_content');
					break;
			}

			$output_tokens = max(1, $output_tokens);
		}

		return TokenBudget::calculate(
			$prompt,
			$output_tokens,
			array(
				'buffer_ratio' => 0.25,
				'minimum_tokens' => 1,
				'respect_config_limit' => true,
			)
		);
	}

	/**
	 * Prepare and normalize AI generation options.
	 *
	 * Merges user-provided options with defaults from plugin settings.
	 * When the caller has not explicitly set maxTokens, the value is calculated
	 * dynamically via calculate_max_tokens() based on the prompt and request type.
	 *
	 * @param array  $options User-provided options.
	 * @param string $prompt  The prompt that will be sent to the AI (used for dynamic token calculation).
	 * @return array Normalized options array.
	 */
	private function prepare_options($options, $prompt = '') {
		$ai_config = Config::get_instance()->get_ai_config();

		$default_options = array(
			'model'       => $ai_config['model'],
			'envId'       => $ai_config['env_id'],
			'temperature' => $ai_config['temperature'],
		);

		if (isset($options['env_id'])) {
			$default_options['envId'] = $options['env_id'];
		} elseif (isset($options['envId'])) {
			$default_options['envId'] = $options['envId'];
		}

		$options = wp_parse_args($options, $default_options);
		$params  = array();

		if (!empty($options['model'])) {
			$params['model'] = $options['model'];
		}

		if (!empty($options['envId'])) {
			$params['envId'] = $options['envId'];
		}

		if (isset($options['maxTokens'])) {
			$params['maxTokens'] = $options['maxTokens'];
		} elseif (isset($options['max_tokens'])) {
			$params['maxTokens'] = $options['max_tokens'];
		} else {
			$type               = isset($options['request_type']) ? $options['request_type'] : 'content';
			$params['maxTokens'] = $this->calculate_max_tokens($prompt, $type);
		}

		if (isset($options['temperature'])) {
			$params['temperature'] = $options['temperature'];
		}

		foreach (self::OPTIONAL_QUERY_OPTION_KEYS as $key) {
			if ('env_id' === $key) {
				continue;
			}

			if (isset($options[$key])) {
				$params[$key] = $options[$key];
			}
		}
		return $params;
	}

	/**
	 * Apply optional AI Engine query settings when available.
	 *
	 * @param object $query   The AI Engine query object.
	 * @param array  $options Options passed to the AI request.
	 * @return void
	 */
	private function apply_optional_query_settings($query, $options) {
		foreach (self::OPTIONAL_QUERY_OPTION_KEYS as $key) {
			if (!isset($options[$key])) {
				continue;
			}

			switch ($key) {
				case 'context':
					if (method_exists($query, 'set_context')) {
						$query->set_context($options[$key]);
					}
					break;
				case 'instructions':
					if (method_exists($query, 'set_instructions')) {
						$query->set_instructions($options[$key]);
					}
					break;
				case 'messages':
					if (method_exists($query, 'set_messages')) {
						$query->set_messages($options[$key]);
					}
					break;
				case 'env_id':
					if (method_exists($query, 'set_env_id')) {
						$query->set_env_id($options[$key]);
					}
					break;
				case 'embeddings_env_id':
					if (method_exists($query, 'set_embeddings_env_id')) {
						$query->set_embeddings_env_id($options[$key]);
					}
					break;
				case 'max_results':
					if (method_exists($query, 'set_max_results')) {
						$query->set_max_results($options[$key]);
					}
					break;
				case 'api_key':
					if (method_exists($query, 'set_api_key')) {
						$query->set_api_key($options[$key]);
					}
					break;
			}
		}
	}

	/**
	 * Emit an integration error notification payload.
	 *
	 * @param string   $request_type Request type.
	 * @param WP_Error $error        Error object.
	 * @param array    $options      Request options.
	 * @return void
	 */
	private function emit_integration_error_notification($request_type, WP_Error $error, $options = array()) {
		do_action('aips_integration_error', array(
			'request_type'   => $request_type,
			'error_code'     => $error->get_error_code(),
			'error_message'  => $error->get_error_message(),
			'dedupe_key'     => 'integration_error_' . sanitize_key($request_type) . '_' . sanitize_key($error->get_error_code()),
			'dedupe_window'  => 1800,
			'url'            => admin_url('admin.php?page=aips-settings'),
			'ai_model'       => isset($options['model']) ? $options['model'] : Config::get_instance()->get_option('aips_ai_model'),
		));
	}

	/**
	 * Emit a quota alert notification payload.
	 *
	 * @param string   $request_type Request type.
	 * @param WP_Error $error        Error object.
	 * @param array    $options      Request options.
	 * @return void
	 */
	private function emit_quota_alert_notification($request_type, WP_Error $error, $options = array()) {
		do_action('aips_quota_alert', array(
			'request_type'   => $request_type,
			'error_code'     => $error->get_error_code(),
			'error_message'  => $error->get_error_message(),
			'dedupe_key'     => 'quota_alert_' . sanitize_key($request_type) . '_' . sanitize_key($error->get_error_code()),
			'dedupe_window'  => 1800,
			'url'            => admin_url('admin.php?page=aips-settings'),
			'ai_model'       => isset($options['model']) ? $options['model'] : Config::get_instance()->get_option('aips_ai_model'),
		));
	}

	/**
	 * Log an AI call for debugging and auditing.
	 *
	 * Stores call information in memory and writes to the system logger.
	 *
	 * @param string                         $type     The type of AI call ('text' or 'image').
	 * @param string                         $prompt   The prompt sent to AI.
	 * @param array                          $options  The options used for the call.
	 * @param WP_Error|Exception|string|null $error    Error object or message, if call failed.
	 * @param string|null                    $response The AI response, if successful.
	 */
	private function log_call($type, $prompt, $options, $error = null, $response = null) {
		$prompt_for_length   = (string) $prompt;
		$response_for_length = (string) $response;

		if ($error instanceof WP_Error) {
			$error_message = $error->get_error_message();
		} elseif ($error instanceof Exception) {
			$error_message = $error->getMessage();
		} else {
			$error_message = $error;
		}

		$call_data = array(
			'type' => $type,
			'timestamp' => DateTime::now()->toIso8601(),
			'request' => array(
				'prompt' => $prompt,
				'options' => $options,
			),
			'response' => array(
				'success' => $error_message === null,
				'content' => $response,
				'error' => $error_message,
			),
		);

		$this->call_log[] = $call_data;

		$level   = $error_message ? 'error' : 'info';
		$message = $error_message ? "AI {$type} generation failed: {$error_message}" : "AI {$type} generation successful";

		$this->logger->log($message, $level, array(
			'type' => $type,
			'prompt_length' => strlen($prompt_for_length),
			'prompt' => $prompt,
			'response_length' => strlen($response_for_length),
			'response' => $response,
			'options' => $options,
			'error_message' => $error_message,
		));
	}

	/**
	 * Get all AI call logs from this session.
	 *
	 * Useful for debugging and displaying generation history.
	 *
	 * @return array Array of call log entries.
	 */
	public function get_call_log() {
		return $this->call_log;
	}

	/**
	 * Clear the call log.
	 *
	 * Resets the in-memory call log. Useful when starting a new generation task.
	 */
	public function clear_call_log() {
		$this->call_log = array();
	}

	/**
	 * Get statistics about AI calls in this session.
	 *
	 * @return array Statistics including total calls, successes, failures.
	 */
	public function get_call_statistics() {
		$total = count($this->call_log);
		$successes = 0;
		$failures = 0;
		$types = array();

		foreach ($this->call_log as $call) {
			if ($call['response']['success']) {
				$successes++;
			} else {
				$failures++;
			}

			$type = $call['type'];
			if (!isset($types[$type])) {
				$types[$type] = 0;
			}
			$types[$type]++;
		}

		return array(
			'total' => $total,
			'successes' => $successes,
			'failures' => $failures,
			'by_type' => $types,
		);
	}

	/**
	 * Reset circuit breaker manually.
	 *
	 * @return bool True on success.
	 */
	public function reset_circuit_breaker() {
		return $this->resilience_service->reset_circuit_breaker();
	}

	/**
	 * Get circuit breaker status.
	 *
	 * @return array Circuit breaker status.
	 */
	public function get_circuit_breaker_status() {
		return $this->resilience_service->get_circuit_breaker_status();
	}

	/**
	 * Get rate limiter status.
	 *
	 * @return array Rate limiter status.
	 */
	public function get_rate_limiter_status() {
		return $this->resilience_service->get_rate_limiter_status();
	}

	/**
	 * Reset rate limiter manually.
	 *
	 * @return bool True on success.
	 */
	public function reset_rate_limiter() {
		return $this->resilience_service->reset_rate_limiter();
	}
}
