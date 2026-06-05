<?php
/**
 * AI Service Facade
 *
 * Stable public entry point that delegates to the active backend.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Service implements AIPS_AI_Service_Interface {

	/**
	 * @var self|null Shared singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var AIPS_AI_Service_Interface Active backend implementation.
	 */
	private $backend;

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
	 * Initialize the AI service facade.
	 *
	 * @param AIPS_Logger_Interface|null $logger             Optional logger dependency.
	 * @param mixed                      $config             Optional config dependency.
	 * @param mixed                      $resilience_service Optional resilience dependency.
	 * @param AIPS_AI_Service_Interface|null $backend        Optional backend override.
	 */
	public function __construct(?AIPS_Logger_Interface $logger = null, $config = null, $resilience_service = null, ?AIPS_AI_Service_Interface $backend = null) {
		$this->backend = $backend ?: AIPS_AI_Service_Factory::create(
			array(
				'logger'             => $logger,
				'config'             => $config,
				'resilience_service' => $resilience_service,
			)
		);
	}

	/**
	 * Check AI availability.
	 *
	 * @return bool
	 */
	public function is_available() {
		return $this->backend->is_available();
	}

	/**
	 * Generate text content.
	 *
	 * @param string $prompt  Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_text($prompt, $options = array()) {
		return $this->backend->generate_text($prompt, $options);
	}

	/**
	 * Generate JSON content.
	 *
	 * @param string $prompt  Prompt text.
	 * @param array  $options Request options.
	 * @return array|WP_Error
	 */
	public function generate_json($prompt, $options = array()) {
		return $this->backend->generate_json($prompt, $options);
	}

	/**
	 * Generate an image.
	 *
	 * @param string $prompt  Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_image($prompt, $options = array()) {
		return $this->backend->generate_image($prompt, $options);
	}

	/**
	 * Return captured AI call logs.
	 *
	 * @return array
	 */
	public function get_call_log() {
		return method_exists($this->backend, 'get_call_log') ? $this->backend->get_call_log() : array();
	}

	/**
	 * Clear the call log when supported by the backend.
	 *
	 * @return void
	 */
	public function clear_call_log() {
		if (method_exists($this->backend, 'clear_call_log')) {
			$this->backend->clear_call_log();
		}
	}

	/**
	 * Return call statistics when supported by the backend.
	 *
	 * @return array
	 */
	public function get_call_statistics() {
		return method_exists($this->backend, 'get_call_statistics') ? $this->backend->get_call_statistics() : array();
	}

	/**
	 * Reset the circuit breaker when supported by the backend.
	 *
	 * @return bool
	 */
	public function reset_circuit_breaker() {
		return method_exists($this->backend, 'reset_circuit_breaker') ? $this->backend->reset_circuit_breaker() : false;
	}

	/**
	 * Get the circuit breaker status when supported by the backend.
	 *
	 * @return array
	 */
	public function get_circuit_breaker_status() {
		return method_exists($this->backend, 'get_circuit_breaker_status') ? $this->backend->get_circuit_breaker_status() : array();
	}

	/**
	 * Get the rate limiter status when supported by the backend.
	 *
	 * @return array
	 */
	public function get_rate_limiter_status() {
		return method_exists($this->backend, 'get_rate_limiter_status') ? $this->backend->get_rate_limiter_status() : array();
	}

	/**
	 * Reset the rate limiter when supported by the backend.
	 *
	 * @return bool
	 */
	public function reset_rate_limiter() {
		return method_exists($this->backend, 'reset_rate_limiter') ? $this->backend->reset_rate_limiter() : false;
	}
}
