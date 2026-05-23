<?php
/**
 * WordPress AI Client backend service.
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
	 * @param AIPS_Logger_Interface|null $logger             Optional logger.
	 * @param mixed                      $config             Optional config.
	 * @param mixed                      $resilience_service Unused; reserved for signature parity.
	 */
	public function __construct(?AIPS_Logger_Interface $logger = null, $config = null, $resilience_service = null) {
		$this->logger = $logger ?: new AIPS_Logger();
		$this->config = $config instanceof AIPS_Config ? $config : AIPS_Config::get_instance();
	}

	/**
	 * @return bool
	 */
	public function is_available() {
		if (!$this->supports_runtime()) {
			return false;
		}

		$builder = $this->create_prompt_builder('Availability check.');
		if (is_wp_error($builder)) {
			return false;
		}

		return method_exists($builder, 'is_supported_for_text_generation')
			? (bool) $builder->is_supported_for_text_generation()
			: true;
	}

	/**
	 * @param string $prompt  Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_text($prompt, $options = array()) {
		$builder = $this->create_configured_builder($prompt, $options);
		if (is_wp_error($builder)) {
			return $builder;
		}

		if (method_exists($builder, 'is_supported_for_text_generation') && !$builder->is_supported_for_text_generation()) {
			return new WP_Error('ai_unavailable', __('WordPress AI Client is not configured for text generation.', 'ai-post-scheduler'));
		}

		$result = $builder->generate_text();
		if (is_wp_error($result)) {
			$this->record_call('text', $prompt, $options, $result);
			return $result;
		}

		$result = is_string($result) ? trim($result) : '';
		if ('' === $result) {
			$error = new WP_Error('empty_response', __('WordPress AI Client returned an empty response.', 'ai-post-scheduler'));
			$this->record_call('text', $prompt, $options, $error);
			return $error;
		}

		$this->record_call('text', $prompt, $options, $result);

		return $result;
	}

	/**
	 * @param string $prompt  Prompt text.
	 * @param array  $options Request options.
	 * @return array|WP_Error
	 */
	public function generate_json($prompt, $options = array()) {
		$builder = $this->create_configured_builder($prompt, $options);
		if (is_wp_error($builder)) {
			return $builder;
		}

		if (!empty($options['json_schema']) && is_array($options['json_schema']) && method_exists($builder, 'as_json_response')) {
			$builder = $builder->as_json_response($options['json_schema']);
		}

		$result = $builder->generate_text();
		if (is_wp_error($result)) {
			$this->record_call('json', $prompt, $options, $result);
			return $result;
		}

		$data = $this->decode_json_response($result);
		if (!is_array($data)) {
			$error = new WP_Error('invalid_json', __('WordPress AI Client did not return valid JSON data.', 'ai-post-scheduler'));
			$this->record_call('json', $prompt, $options, $error);
			return $error;
		}

		$this->record_call('json', $prompt, $options, $data);

		return $data;
	}

	/**
	 * @param string $prompt  Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_image($prompt, $options = array()) {
		$builder = $this->create_configured_builder($prompt, $options);
		if (is_wp_error($builder)) {
			return $builder;
		}

		if (method_exists($builder, 'is_supported_for_image_generation') && !$builder->is_supported_for_image_generation()) {
			return new WP_Error('ai_unavailable', __('WordPress AI Client is not configured for image generation.', 'ai-post-scheduler'));
		}

		$result = $builder->generate_image();
		if (is_wp_error($result)) {
			$this->record_call('image', $prompt, $options, $result);
			return $result;
		}

		if (is_object($result) && method_exists($result, 'getDataUri')) {
			$result = $result->getDataUri();
		}

		$result = is_string($result) ? trim($result) : '';
		if ('' === $result) {
			$error = new WP_Error('empty_response', __('WordPress AI Client returned an empty response for image generation.', 'ai-post-scheduler'));
			$this->record_call('image', $prompt, $options, $error);
			return $error;
		}

		$this->record_call('image', $prompt, $options, $result);

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
		return array(
			'total_calls' => count($this->call_log),
		);
	}

	/**
	 * @return bool
	 */
	private function supports_runtime() {
		return function_exists('wp_ai_client_prompt')
			|| class_exists('WordPress\\AI_Client\\AI_Client');
	}

	/**
	 * @param string $prompt Prompt text.
	 * @return object|WP_Error
	 */
	private function create_prompt_builder($prompt) {
		if (function_exists('wp_ai_client_prompt')) {
			return wp_ai_client_prompt($prompt);
		}

		if (!class_exists('WordPress\\AI_Client\\AI_Client')) {
			return new WP_Error('ai_unavailable', __('WordPress AI Client is not available.', 'ai-post-scheduler'));
		}

		if (method_exists('WordPress\\AI_Client\\AI_Client', 'prompt_with_wp_error')) {
			return \WordPress\AI_Client\AI_Client::prompt_with_wp_error($prompt);
		}

		try {
			return \WordPress\AI_Client\AI_Client::prompt($prompt);
		} catch (\Exception $e) {
			return new WP_Error('ai_unavailable', $e->getMessage());
		}
	}

	/**
	 * @param string $prompt  Prompt text.
	 * @param array  $options Request options.
	 * @return object|WP_Error
	 */
	private function create_configured_builder($prompt, $options) {
		$builder = $this->create_prompt_builder($prompt);
		if (is_wp_error($builder)) {
			return $builder;
		}

		$temperature = isset($options['temperature']) ? (float) $options['temperature'] : null;
		$max_tokens  = isset($options['maxTokens']) ? (int) $options['maxTokens'] : (isset($options['max_tokens']) ? (int) $options['max_tokens'] : 0);
		$model       = isset($options['model']) ? (string) $options['model'] : (string) $this->config->get_option('aips_ai_model');

		if ($temperature !== null && method_exists($builder, 'using_temperature')) {
			$builder = $builder->using_temperature($temperature);
		}

		if ($max_tokens > 0 && method_exists($builder, 'using_max_tokens')) {
			$builder = $builder->using_max_tokens($max_tokens);
		}

		if ('' !== trim($model) && method_exists($builder, 'using_model_preference')) {
			$builder = $builder->using_model_preference(trim($model));
		}

		return $builder;
	}

	/**
	 * @param mixed $response Raw JSON response.
	 * @return array|null
	 */
	private function decode_json_response($response) {
		$json = is_string($response) ? trim($response) : '';
		$json = preg_replace('/^```json\s*/', '', $json);
		$json = preg_replace('/^```\s*/', '', $json);
		$json = preg_replace('/\s*```$/', '', $json);
		$json = trim($json);

		$data = json_decode($json, true);

		return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : null;
	}

	/**
	 * @param string          $type    Call type.
	 * @param string          $prompt  Prompt text.
	 * @param array           $options Request options.
	 * @param string|array|WP_Error $result Result payload.
	 * @return void
	 */
	private function record_call($type, $prompt, $options, $result) {
		$entry = array(
			'type'    => $type,
			'prompt'  => $prompt,
			'options' => $options,
			'time'    => current_time('mysql'),
		);

		if (is_wp_error($result)) {
			$entry['error_code']    = $result->get_error_code();
			$entry['error_message'] = $result->get_error_message();
			$this->logger->log('WordPress AI Client request failed: ' . $result->get_error_message(), 'error');
		} else {
			$entry['result'] = $result;
		}

		$this->call_log[] = $entry;
	}
}
