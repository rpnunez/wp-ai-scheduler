<?php
/**
 * AI service factory and backend selection helpers.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Service_Factory {

	const BACKEND_WORDPRESS_AI_CLIENT = 'wordpress_ai_client';
	const BACKEND_MEOW_AI_ENGINE      = 'meow_ai_engine';

	/**
	 * Return the supported backend identifiers.
	 *
	 * @return array
	 */
	public static function get_backend_options() {
		return array(
			self::BACKEND_WORDPRESS_AI_CLIENT,
			self::BACKEND_MEOW_AI_ENGINE,
		);
	}

	/**
	 * Check whether the WordPress 7 AI Client backend is available.
	 *
	 * @return bool
	 */
	public static function is_wordpress_ai_client_available() {
		$version = function_exists('wp_get_wp_version') ? wp_get_wp_version() : get_bloginfo('version');
		$available = function_exists('wp_ai_client_prompt') && version_compare((string) $version, '7.0', '>=');

		return (bool) apply_filters('aips_wordpress_ai_client_available', $available);
	}

	/**
	 * Check whether the Meow Apps AI Engine backend is available.
	 *
	 * @return bool
	 */
	public static function is_meow_ai_engine_available() {
		$available = class_exists('Meow_MWAI_Core');

		return (bool) apply_filters('aips_meow_ai_engine_available', $available);
	}

	/**
	 * Get the preferred default backend for the current site.
	 *
	 * @return string
	 */
	public static function get_default_backend() {
		if (self::is_wordpress_ai_client_available()) {
			return self::BACKEND_WORDPRESS_AI_CLIENT;
		}

		return self::BACKEND_MEOW_AI_ENGINE;
	}

	/**
	 * Sanitize a backend value and fall back to an available default when needed.
	 *
	 * @param mixed $backend Raw backend value.
	 * @return string
	 */
	public static function sanitize_backend($backend) {
		$backend = sanitize_key((string) $backend);
		$allowed = self::get_backend_options();

		if (!in_array($backend, $allowed, true)) {
			return self::get_default_backend();
		}

		if ($backend === self::BACKEND_MEOW_AI_ENGINE && !self::is_meow_ai_engine_available()) {
			return self::is_wordpress_ai_client_available()
				? self::BACKEND_WORDPRESS_AI_CLIENT
				: self::get_default_backend();
		}

		if ($backend === self::BACKEND_WORDPRESS_AI_CLIENT && !self::is_wordpress_ai_client_available()) {
			return self::is_meow_ai_engine_available()
				? self::BACKEND_MEOW_AI_ENGINE
				: self::get_default_backend();
		}

		return $backend;
	}

	/**
	 * Resolve the currently selected backend.
	 *
	 * @return string
	 */
	public static function get_selected_backend() {
		$config = AIPS_Config::get_instance();

		if (!$config->has_option('aips_ai_backend')) {
			return self::get_default_backend();
		}

		return self::sanitize_backend($config->get_option('aips_ai_backend'));
	}

	/**
	 * Create the active service implementation.
	 *
	 * @param AIPS_Logger_Interface|null $logger             Logger dependency.
	 * @param mixed                      $config             Config dependency.
	 * @param mixed                      $resilience_service Resilience dependency.
	 * @return AIPS_AI_Service_Interface
	 */
	public static function create_service(?AIPS_Logger_Interface $logger = null, $config = null, $resilience_service = null) {
		$backend = self::get_selected_backend();

		if ($backend === self::BACKEND_WORDPRESS_AI_CLIENT) {
			return new AIPS_WordPress_AI_Client_Service($logger, $config);
		}

		return new AIPS_Meow_Apps_AI_Service($logger, $config, $resilience_service);
	}
}
