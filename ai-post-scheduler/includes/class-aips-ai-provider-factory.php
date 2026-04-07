<?php
/**
 * AI Provider Factory
 *
 * Factory class for instantiating AI providers based on plugin configuration.
 * Centralizes provider instantiation logic and configuration retrieval.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_AI_Provider_Factory
 *
 * Factory for creating AI provider instances based on plugin settings.
 */
class AIPS_AI_Provider_Factory {

	/**
	 * @var AIPS_Config Configuration manager
	 */
	private $config;

	/**
	 * @var AIPS_Logger Logger instance
	 */
	private $logger;

	/**
	 * @var AIPS_AI_Provider Cached provider instance
	 */
	private static $provider_instance = null;

	/**
	 * Initialize the factory.
	 *
	 * @param AIPS_Config $config Optional. Configuration manager.
	 * @param AIPS_Logger $logger Optional. Logger instance.
	 */
	public function __construct($config = null, $logger = null) {
		$this->config = $config ?: AIPS_Config::get_instance();
		$this->logger = $logger ?: new AIPS_Logger();
	}

	/**
	 * Create an AI provider instance based on plugin configuration.
	 *
	 * @param bool $force_new Optional. Force creation of a new instance. Default false.
	 * @return AIPS_AI_Provider The configured AI provider.
	 */
	public function create_provider($force_new = false) {
		// Return cached instance unless forced to create new
		if (self::$provider_instance !== null && !$force_new) {
			return self::$provider_instance;
		}

		$provider_type = $this->config->get_option('aips_ai_provider', 'ai-engine');

		$this->logger->log('Creating AI provider: ' . $provider_type, 'debug');

		switch ($provider_type) {
			case 'custom':
				$provider = $this->create_custom_provider();
				break;

			case 'ai-engine':
			default:
				$provider = $this->create_ai_engine_provider();
				break;
		}

		// Cache the provider instance
		if (!$force_new) {
			self::$provider_instance = $provider;
		}

		return $provider;
	}

	/**
	 * Create an AI Engine provider instance.
	 *
	 * @return AIPS_AI_Engine_Provider The AI Engine provider.
	 */
	private function create_ai_engine_provider() {
		return new AIPS_AI_Engine_Provider(null, $this->logger);
	}

	/**
	 * Create a custom AI provider instance.
	 *
	 * @return AIPS_Custom_AI_Provider The custom AI provider.
	 */
	private function create_custom_provider() {
		$api_url = $this->config->get_option('aips_custom_ai_url', '');
		$api_key = $this->config->get_option('aips_custom_ai_key', '');
		$model = $this->config->get_option('aips_custom_ai_model', '');

		return new AIPS_Custom_AI_Provider($api_url, $api_key, $model, $this->logger);
	}

	/**
	 * Clear the cached provider instance.
	 *
	 * Useful when settings are changed and a fresh provider is needed.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$provider_instance = null;
	}

	/**
	 * Get the currently configured provider type.
	 *
	 * @return string The provider identifier ('ai-engine' or 'custom').
	 */
	public function get_provider_type() {
		return $this->config->get_option('aips_ai_provider', 'ai-engine');
	}

	/**
	 * Check if the currently configured provider is available.
	 *
	 * @return bool True if the provider is available, false otherwise.
	 */
	public function is_provider_available() {
		$provider = $this->create_provider();
		return $provider->is_available();
	}

	/**
	 * Get provider configuration status for system diagnostics.
	 *
	 * @return array Configuration status information.
	 */
	public function get_provider_status() {
		$provider_type = $this->get_provider_type();
		$provider = $this->create_provider();

		$status = array(
			'provider_type' => $provider_type,
			'provider_name' => $provider->get_name(),
			'is_available' => $provider->is_available(),
		);

		if ($provider_type === 'custom') {
			$status['api_url'] = $this->config->get_option('aips_custom_ai_url', '');
			$status['model'] = $this->config->get_option('aips_custom_ai_model', '');
			$status['has_api_key'] = !empty($this->config->get_option('aips_custom_ai_key', ''));
		} elseif ($provider_type === 'ai-engine') {
			$status['ai_engine_installed'] = class_exists('Meow_MWAI_Core');
			$status['model'] = $this->config->get_option('aips_ai_model', '');
			$status['env_id'] = $this->config->get_option('aips_ai_env_id', '');
		}

		return $status;
	}
}
