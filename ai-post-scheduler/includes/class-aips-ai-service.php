<?php
/**
 * Backward-compatible AI service facade.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Service implements AIPS_AI_Service_Interface {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $delegate;

	/**
	 * @param AIPS_Logger_Interface|null $logger Logger dependency.
	 * @param mixed                      $config Config dependency.
	 * @param mixed                      $resilience_service Resilience dependency.
	 */
	public function __construct(?AIPS_Logger_Interface $logger = null, $config = null, $resilience_service = null) {
		$this->delegate = AIPS_AI_Service_Factory::create_service($logger, $config, $resilience_service);
	}

	/**
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @return bool
	 */
	public function is_available() {
		return $this->delegate->is_available();
	}

	/**
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_text($prompt, $options = array()) {
		return $this->delegate->generate_text($prompt, $options);
	}

	/**
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return array|WP_Error
	 */
	public function generate_json($prompt, $options = array()) {
		return $this->delegate->generate_json($prompt, $options);
	}

	/**
	 * @param string $prompt Prompt text.
	 * @param array  $options Request options.
	 * @return string|WP_Error
	 */
	public function generate_image($prompt, $options = array()) {
		return $this->delegate->generate_image($prompt, $options);
	}

	/**
	 * @return array
	 */
	public function get_call_log() {
		return $this->delegate->get_call_log();
	}

	/**
	 * @return void
	 */
	public function clear_call_log() {
		if (method_exists($this->delegate, 'clear_call_log')) {
			$this->delegate->clear_call_log();
		}
	}

	/**
	 * @return array
	 */
	public function get_call_statistics() {
		if (method_exists($this->delegate, 'get_call_statistics')) {
			return $this->delegate->get_call_statistics();
		}

		return array(
			'total'     => count($this->delegate->get_call_log()),
			'successes' => 0,
			'failures'  => 0,
			'by_type'   => array(),
		);
	}

	/**
	 * @return bool
	 */
	public function reset_circuit_breaker() {
		return method_exists($this->delegate, 'reset_circuit_breaker')
			? $this->delegate->reset_circuit_breaker()
			: false;
	}

	/**
	 * @return array
	 */
	public function get_circuit_breaker_status() {
		return method_exists($this->delegate, 'get_circuit_breaker_status')
			? $this->delegate->get_circuit_breaker_status()
			: array('enabled' => false, 'is_open' => false);
	}

	/**
	 * @return array
	 */
	public function get_rate_limiter_status() {
		return method_exists($this->delegate, 'get_rate_limiter_status')
			? $this->delegate->get_rate_limiter_status()
			: array('enabled' => false);
	}

	/**
	 * @return bool
	 */
	public function reset_rate_limiter() {
		return method_exists($this->delegate, 'reset_rate_limiter')
			? $this->delegate->reset_rate_limiter()
			: false;
	}
}
