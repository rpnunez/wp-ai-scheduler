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
	 * @var array
	 */
	private $call_log = array();

	/**
	 * @param AIPS_Logger_Interface|null $logger Logger dependency.
	 * @param mixed                      $config Config dependency.
	 */
	public function __construct(?AIPS_Logger_Interface $logger = null, $config = null) {
		if ($logger) {
			$this->logger = $logger;
		} else {
			$container = AIPS_Container::get_instance();
			$this->logger = $container->has(AIPS_Logger_Interface::class)
				? $container->make(AIPS_Logger_Interface::class)
				: AIPS_Logger::instance();
		}

		$this->config = $config ?: AIPS_Config::get_instance();
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
			return $error;
		}

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

		$result = $builder->generate_text();

		if (is_wp_error($result)) {
			$this->log_call('text', $prompt, $options, $result);
			return $result;
		}

		if (!is_string($result) || $result === '') {
			$error = new WP_Error('empty_response', __('WordPress AI Client returned an empty response.', 'ai-post-scheduler'));
			$this->log_call('text', $prompt, $options, $error);
			return $error;
		}

		$this->log_call('text', $prompt, $options, null, $result);

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
			return $error;
		}

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

		$result = $builder->generate_text();

		if (is_wp_error($result)) {
			$this->log_call('json', $prompt, $options, $result);
			return $result;
		}

		$data = json_decode((string) $result, true);

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
			return $error;
		}

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

		$result = $builder->generate_image();

		if (is_wp_error($result)) {
			$this->log_call('image', $prompt, $options, $result);
			return $result;
		}

		if (is_object($result) && method_exists($result, 'getDataUri')) {
			$image = $result->getDataUri();
		} elseif (is_string($result)) {
			$image = $result;
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

		return $builder;
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
				'type'          => $type,
				'prompt'        => $prompt,
				'response'      => $response,
				'options'       => $options,
				'error_message' => $error_message,
			)
		);
	}
}
