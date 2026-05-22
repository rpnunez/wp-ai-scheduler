<?php
/**
 * WordPress AI Client-backed implementation of the AI service contract.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_WordPress_AI_Client_Service implements AIPS_AI_Service_Interface {

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Resilience_Service
	 */
	private $resilience_service;

	/**
	 * @var array
	 */
	private $call_log = array();

	/**
	 * @param AIPS_Logger_Interface|null $logger             Logger dependency.
	 * @param mixed                      $config             Config dependency.
	 * @param mixed                      $resilience_service Resilience dependency.
	 */
	public function __construct(?AIPS_Logger_Interface $logger = null, $config = null, $resilience_service = null) {
		if ($logger) {
			$this->logger = $logger;
		} else {
			$container = AIPS_Container::get_instance();
			$this->logger = $container->has(AIPS_Logger_Interface::class)
				? $container->make(AIPS_Logger_Interface::class)
				: AIPS_Logger::instance();
		}

		$this->config = $config ?: AIPS_Config::get_instance();
		$this->resilience_service = $resilience_service ?: new AIPS_Resilience_Service($this->logger, $this->config);
	}

	/**
	 * @return bool
	 */
	public function is_available() {
		if (!AIPS_AI_Service_Factory::is_wordpress_ai_client_available()) {
			return false;
		}

		$builder = wp_ai_client_prompt();

		return is_object($builder)
			&& method_exists($builder, 'is_supported_for_text_generation')
			&& $builder->is_supported_for_text_generation();
	}

	/**
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_text($prompt, $options = array()) {
		if (!AIPS_AI_Service_Factory::is_wordpress_ai_client_available()) {
			$error = new WP_Error('ai_unavailable', __('WordPress AI Client is not available.', 'ai-post-scheduler'));
			$this->log_call('text', $prompt, $options, $error);
			$this->emit_integration_error_notification('text', $error, $options);
			return $error;
		}

		$result = $this->resilience_service->execute_safely(function() use ($prompt, $options) {
			$builder = $this->prepare_builder($prompt, $options);

			if (!is_object($builder) || !method_exists($builder, 'generate_text')) {
				$error = new WP_Error('ai_unavailable', __('WordPress AI Client prompt builder is not available.', 'ai-post-scheduler'));
				$this->log_call('text', $prompt, $options, $error);
				return $error;
			}

			if (method_exists($builder, 'is_supported_for_text_generation') && !$builder->is_supported_for_text_generation()) {
				$error = new WP_Error('ai_unavailable', __('No configured WordPress AI provider supports text generation.', 'ai-post-scheduler'));
				$this->log_call('text', $prompt, $options, $error);
				return $error;
			}

			try {
				$generated = $builder->generate_text();

				if (is_wp_error($generated)) {
					$this->log_call('text', $prompt, $options, $generated);
					return $generated;
				}

				if (!is_string($generated) || $generated === '') {
					$error = new WP_Error('empty_response', __('WordPress AI Client returned an empty response.', 'ai-post-scheduler'));
					$this->log_call('text', $prompt, $options, $error);
					return $error;
				}

				$this->log_call('text', $prompt, $options, null, $generated);

				return $generated;
			} catch (Exception $e) {
				$provider_code = AIPS_Resilience_Service::extract_error_code_from_message($e->getMessage());
				$error = new WP_Error($provider_code ?: 'generation_failed', $e->getMessage());
				$this->log_call('text', $prompt, $options, $error);
				return $error;
			}
		}, 'text', $prompt, $options);

		if (is_wp_error($result)) {
			$code = $result->get_error_code();

			if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
				$this->emit_quota_alert_notification('text', $result, $options);
			} else {
				$this->emit_integration_error_notification('text', $result, $options);
			}
		}

		return $result;
	}

	/**
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return array|WP_Error
	 */
	public function generate_json($prompt, $options = array()) {
		if (!AIPS_AI_Service_Factory::is_wordpress_ai_client_available()) {
			$error = new WP_Error('ai_unavailable', __('WordPress AI Client is not available.', 'ai-post-scheduler'));
			$this->log_call('json', $prompt, $options, $error);
			$this->emit_integration_error_notification('json', $error, $options);
			return $error;
		}

		$result = $this->resilience_service->execute_safely(function() use ($prompt, $options) {
			$builder = $this->prepare_builder($prompt, $options);
			$schema  = isset($options['json_schema']) && is_array($options['json_schema'])
				? $options['json_schema']
				: array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				);

			if (!is_object($builder) || !method_exists($builder, 'generate_text')) {
				$error = new WP_Error('ai_unavailable', __('WordPress AI Client prompt builder is not available.', 'ai-post-scheduler'));
				$this->log_call('json', $prompt, $options, $error);
				return $error;
			}

			if (method_exists($builder, 'as_json_response')) {
				$builder = $builder->as_json_response($schema);
			}

			try {
				$generated = $builder->generate_text();

				if (is_wp_error($generated)) {
					$this->log_call('json', $prompt, $options, $generated);
					return $generated;
				}

				$extract_result = $this->extract_json_fragment((string) $generated);

				if (is_wp_error($extract_result)) {
					$error = new WP_Error('json_parse_error', $extract_result->get_error_message());
					$this->log_call('json', $prompt, $options, $error);
					return $error;
				}

				$data = json_decode($extract_result, true);

				if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
					$error = new WP_Error(
						'json_parse_error',
						sprintf(__('Failed to parse JSON: %s', 'ai-post-scheduler'), json_last_error_msg())
					);
					$this->log_call('json', $prompt, $options, $error);
					return $error;
				}

				$this->log_call('json', $prompt, $options, null, wp_json_encode($data));

				return $data;
			} catch (Exception $e) {
				$provider_code = AIPS_Resilience_Service::extract_error_code_from_message($e->getMessage());
				$error = new WP_Error($provider_code ?: 'generation_failed', $e->getMessage());
				$this->log_call('json', $prompt, $options, $error);
				return $error;
			}
		}, 'json', $prompt, $options);

		if (is_wp_error($result)) {
			$code = $result->get_error_code();

			if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
				$this->emit_quota_alert_notification('json', $result, $options);
			} else {
				$this->emit_integration_error_notification('json', $result, $options);
			}
		}

		return $result;
	}

	/**
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_image($prompt, $options = array()) {
		if (!AIPS_AI_Service_Factory::is_wordpress_ai_client_available()) {
			$error = new WP_Error('ai_unavailable', __('WordPress AI Client is not available.', 'ai-post-scheduler'));
			$this->log_call('image', $prompt, $options, $error);
			$this->emit_integration_error_notification('image', $error, $options);
			return $error;
		}

		$result = $this->resilience_service->execute_safely(function() use ($prompt, $options) {
			$builder = $this->prepare_builder($prompt, $options);

			if (!is_object($builder) || !method_exists($builder, 'generate_image')) {
				$error = new WP_Error('ai_unavailable', __('WordPress AI Client prompt builder is not available.', 'ai-post-scheduler'));
				$this->log_call('image', $prompt, $options, $error);
				return $error;
			}

			if (method_exists($builder, 'is_supported_for_image_generation') && !$builder->is_supported_for_image_generation()) {
				$error = new WP_Error('image_not_supported', __('No configured WordPress AI provider supports image generation.', 'ai-post-scheduler'));
				$this->log_call('image', $prompt, $options, $error);
				return $error;
			}

			try {
				$generated = $builder->generate_image();

				if (is_wp_error($generated)) {
					$this->log_call('image', $prompt, $options, $generated);
					return $generated;
				}

				if (is_object($generated) && method_exists($generated, 'getDataUri')) {
					$image = $generated->getDataUri();
				} elseif (is_string($generated)) {
					$image = $generated;
				} else {
					$image = '';
				}

				if ($image === '') {
					$error = new WP_Error('no_image_url', __('WordPress AI Client did not return an image payload.', 'ai-post-scheduler'));
					$this->log_call('image', $prompt, $options, $error);
					return $error;
				}

				$this->log_call('image', $prompt, $options, null, $image);

				return $image;
			} catch (Exception $e) {
				$provider_code = AIPS_Resilience_Service::extract_error_code_from_message($e->getMessage());
				$error = new WP_Error($provider_code ?: 'generation_failed', $e->getMessage());
				$this->log_call('image', $prompt, $options, $error);
				return $error;
			}
		}, 'image', $prompt, $options);

		if (is_wp_error($result)) {
			$code = $result->get_error_code();

			if (in_array($code, array('circuit_breaker_open', 'rate_limit_exceeded'), true)) {
				$this->emit_quota_alert_notification('image', $result, $options);
			} else {
				$this->emit_integration_error_notification('image', $result, $options);
			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	public function get_call_log() {
		return $this->call_log;
	}

	/**
	 * @return void
	 */
	public function clear_call_log() {
		$this->call_log = array();
	}

	/**
	 * @return array
	 */
	public function get_call_statistics() {
		$total     = count($this->call_log);
		$successes = 0;
		$failures  = 0;
		$types     = array();

		foreach ($this->call_log as $call) {
			if (!empty($call['response']['success'])) {
				$successes++;
			} else {
				$failures++;
			}

			if (!isset($types[$call['type']])) {
				$types[$call['type']] = 0;
			}

			$types[$call['type']]++;
		}

		return array(
			'total'     => $total,
			'successes' => $successes,
			'failures'  => $failures,
			'by_type'   => $types,
		);
	}

	/**
	 * Compatibility no-op for non-Meow backends.
	 *
	 * @return bool
	 */
	public function reset_circuit_breaker() {
		return false;
	}

	/**
	 * Compatibility no-op for non-Meow backends.
	 *
	 * @return array
	 */
	public function get_circuit_breaker_status() {
		return array(
			'enabled' => false,
			'is_open' => false,
		);
	}

	/**
	 * Compatibility no-op for non-Meow backends.
	 *
	 * @return array
	 */
	public function get_rate_limiter_status() {
		return array(
			'enabled' => false,
		);
	}

	/**
	 * Compatibility no-op for non-Meow backends.
	 *
	 * @return bool
	 */
	public function reset_rate_limiter() {
		return false;
	}

	/**
	 * Prepare a WordPress AI Client builder from the prompt and options.
	 *
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return object
	 */
	private function prepare_builder($prompt, $options) {
		$builder = wp_ai_client_prompt($this->build_prompt((string) $prompt, $options));

		if (!is_object($builder)) {
			return $builder;
		}

		if (isset($options['temperature']) && method_exists($builder, 'using_temperature')) {
			$builder = $builder->using_temperature((float) $options['temperature']);
		}

		$model_preferences = array();
		if (!empty($options['model_preference']) && is_array($options['model_preference'])) {
			$model_preferences = $options['model_preference'];
		} elseif (!empty($options['model'])) {
			$model_preferences = array((string) $options['model']);
		}

		if (!empty($model_preferences) && method_exists($builder, 'using_model_preference')) {
			$builder = $builder->using_model_preference(...$model_preferences);
		}

		if (method_exists($builder, 'using_max_output_tokens') || method_exists($builder, 'using_max_tokens')) {
			$max_tokens = $this->resolve_max_tokens($options, (string) $prompt);

			if (method_exists($builder, 'using_max_output_tokens')) {
				$builder = $builder->using_max_output_tokens($max_tokens);
			} elseif (method_exists($builder, 'using_max_tokens')) {
				$builder = $builder->using_max_tokens($max_tokens);
			}
		}

		return $builder;
	}

	/**
	 * Calculate max tokens from explicit options or dynamic token budget.
	 *
	 * @param array  $options Request options.
	 * @param string $prompt  Prompt text.
	 * @return int
	 */
	private function resolve_max_tokens($options, $prompt) {
		if (isset($options['max_tokens']) && is_numeric($options['max_tokens'])) {
			return max(1, (int) $options['max_tokens']);
		}

		if (isset($options['maxTokens']) && is_numeric($options['maxTokens'])) {
			return max(1, (int) $options['maxTokens']);
		}

		if (isset($options['request_type']) && is_int($options['request_type']) && $options['request_type'] > 0) {
			$output_tokens = $options['request_type'];
		} else {
			$request_type = isset($options['request_type']) ? (string) $options['request_type'] : 'content';

			switch ($request_type) {
				case 'title':
					$output_tokens = (int) $this->config->get_option('aips_max_tokens_title');
					break;
				case 'excerpt':
					$output_tokens = (int) $this->config->get_option('aips_max_tokens_excerpt');
					break;
				case 'content':
				default:
					$output_tokens = (int) $this->config->get_option('aips_max_tokens_content');
					break;
			}
		}

		return AIPS_Token_Budget::calculate(
			$prompt,
			max(1, (int) $output_tokens),
			array(
				'buffer_ratio' => 0.25,
				'minimum_tokens' => 1,
				'respect_config_limit' => true,
			)
		);
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
			'request_type'  => $request_type,
			'error_code'    => $error->get_error_code(),
			'error_message' => $error->get_error_message(),
			'dedupe_key'    => 'integration_error_' . sanitize_key($request_type) . '_' . sanitize_key($error->get_error_code()),
			'dedupe_window' => 1800,
			'url'           => admin_url('admin.php?page=aips-settings'),
			'ai_model'      => isset($options['model']) ? $options['model'] : $this->config->get_option('aips_ai_model'),
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
			'request_type'  => $request_type,
			'error_code'    => $error->get_error_code(),
			'error_message' => $error->get_error_message(),
			'dedupe_key'    => 'quota_alert_' . sanitize_key($request_type) . '_' . sanitize_key($error->get_error_code()),
			'dedupe_window' => 1800,
			'url'           => admin_url('admin.php?page=aips-settings'),
			'ai_model'      => isset($options['model']) ? $options['model'] : $this->config->get_option('aips_ai_model'),
		));
	}

	/**
	 * Extract the first balanced JSON object/array from text.
	 *
	 * @param string $text Raw AI text response.
	 * @return string|WP_Error
	 */
	private function extract_json_fragment($text) {
		$text = trim((string) $text);

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
	 * Merge context/instructions into a plain-text prompt for WordPress AI Client.
	 *
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return string
	 */
	private function build_prompt($prompt, $options) {
		$parts = array($prompt);

		if (!empty($options['instructions'])) {
			$parts[] = "Instructions:\n" . (string) $options['instructions'];
		}

		if (!empty($options['context'])) {
			$parts[] = "Context:\n" . (string) $options['context'];
		}

		return trim(implode("\n\n", array_filter($parts)));
	}

	/**
	 * Record the call in the in-memory log and the plugin logger.
	 *
	 * @param string                         $type Request type.
	 * @param string                         $prompt Prompt text.
	 * @param array                          $options Request options.
	 * @param WP_Error|Exception|string|null $error Error payload.
	 * @param string|null                    $response Response payload.
	 * @return void
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
			'type'      => $type,
			'timestamp' => current_time('mysql'),
			'request'   => array(
				'prompt'  => $prompt,
				'options' => $options,
			),
			'response'  => array(
				'success' => $error_message === null,
				'content' => $response,
				'error'   => $error_message,
			),
		);

		$this->call_log[] = $call_data;

		$this->logger->log(
			$error_message === null ? "AI {$type} generation successful" : "AI {$type} generation failed: {$error_message}",
			$error_message === null ? 'info' : 'error',
			array(
				'type'            => $type,
				'prompt_length'   => strlen($prompt_for_length),
				'prompt'          => $prompt,
				'response_length' => strlen($response_for_length),
				'response'        => $response,
				'options'         => $options,
				'error_message'   => $error_message,
			)
		);
	}
}
