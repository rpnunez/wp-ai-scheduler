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
		$total     = count($this->call_log);
		$successes = 0;
		$failures  = 0;
		$by_type   = array();

		foreach ($this->call_log as $call) {
			$type = isset($call['type']) ? (string) $call['type'] : 'unknown';
			if (!isset($by_type[ $type ])) {
				$by_type[ $type ] = 0;
			}
			$by_type[ $type ]++;

			if (isset($call['error_code'])) {
				$failures++;
			} else {
				$successes++;
			}
		}

		return array(
			'total'     => $total,
			'successes' => $successes,
			'failures'  => $failures,
			'by_type'   => $by_type,
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
		$json = preg_replace('/^```(?:json)?\s*/i', '', $json);
		$json = preg_replace('/\s*```$/', '', $json);
		$json = trim((string) $json);

		$data = json_decode($json, true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
			return $data;
		}

		$fragment = $this->extract_json_fragment($json);
		if ($fragment === null) {
			return null;
		}

		$data = json_decode($fragment, true);

		return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : null;
	}

	/**
	 * @param string $text Raw model response text.
	 * @return string|null Balanced JSON object/array fragment or null.
	 */
	private function extract_json_fragment($text) {
		$start_pos_obj = strpos($text, '{');
		$start_pos_arr = strpos($text, '[');

		if ($start_pos_obj === false && $start_pos_arr === false) {
			return null;
		}

		if ($start_pos_obj === false) {
			$start_pos = $start_pos_arr;
		} elseif ($start_pos_arr === false) {
			$start_pos = $start_pos_obj;
		} else {
			$start_pos = min($start_pos_obj, $start_pos_arr);
		}

		$slice = substr($text, $start_pos);
		if ($slice === false) {
			return null;
		}

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
					return null;
				}

				$open = array_pop($stack);
				if (($open === '{' && $ch !== '}') || ($open === '[' && $ch !== ']')) {
					return null;
				}

				if (empty($stack)) {
					return $this->sanitize_json_candidate(substr($slice, 0, $i + 1));
				}
			}
		}

		return null;
	}

	/**
	 * @param string $candidate Candidate JSON fragment.
	 * @return string
	 */
	private function sanitize_json_candidate($candidate) {
		$sanitized = trim((string) $candidate);
		$sanitized = preg_replace('/\x{FEFF}/u', '', $sanitized);

		return trim((string) $sanitized);
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
