<?php
/**
 * AI Service Factory
 *
 * Resolves the active AI backend implementation.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Service_Factory {

	public const BACKEND_MEOW = 'meow';
	public const BACKEND_WORDPRESS_AI_CLIENT = 'wordpress_ai_client';

	/**
	 * Return registered backend metadata.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_registered_backends() {
		$backends = array(
			self::BACKEND_MEOW                  => array(
				'label' => __('Meow Apps AI Engine', 'ai-post-scheduler'),
			),
			self::BACKEND_WORDPRESS_AI_CLIENT   => array(
				'label' => __('WordPress AI Client', 'ai-post-scheduler'),
			),
		);

		return apply_filters('aips_ai_backends', $backends);
	}

	/**
	 * Return the human-readable label for a backend.
	 *
	 * @param string $backend_id Backend identifier.
	 * @return string
	 */
	public static function get_backend_label($backend_id) {
		$backends = self::get_registered_backends();

		return isset($backends[ $backend_id ]['label'])
			? $backends[ $backend_id ]['label']
			: (string) $backend_id;
	}

	/**
	 * Resolve the default backend for the current environment.
	 *
	 * Prefer a backend that is already ready to generate. If neither backend is
	 * ready yet, prefer the WordPress AI Client when its runtime is present.
	 *
	 * @return string
	 */
	public static function get_default_backend_id() {
		if (self::is_backend_available(self::BACKEND_WORDPRESS_AI_CLIENT)) {
			return self::BACKEND_WORDPRESS_AI_CLIENT;
		}

		if (self::is_backend_available(self::BACKEND_MEOW)) {
			return self::BACKEND_MEOW;
		}

		if (self::is_backend_supported(self::BACKEND_WORDPRESS_AI_CLIENT)) {
			return self::BACKEND_WORDPRESS_AI_CLIENT;
		}

		return self::BACKEND_MEOW;
	}

	/**
	 * Resolve the active backend identifier.
	 *
	 * @return string
	 */
	public static function get_backend_id() {
		$backend_id = '';

		if (class_exists('AIPS_Config')) {
			$backend_id = AIPS_Config::get_instance()->get_option('aips_ai_backend', '');
		}

		$backend_id = is_string($backend_id) ? sanitize_key($backend_id) : '';
		$backend_id = apply_filters('aips_ai_backend', $backend_id);
		$backend_id = is_string($backend_id) ? sanitize_key($backend_id) : '';

		if (isset(self::get_registered_backends()[ $backend_id ])) {
			return $backend_id;
		}

		return self::get_default_backend_id();
	}

	/**
	 * Check whether the runtime integration for a backend is present.
	 *
	 * This does not guarantee provider credentials are configured yet.
	 *
	 * @param string $backend_id Backend identifier.
	 * @return bool
	 */
	public static function is_backend_supported($backend_id) {
		switch ($backend_id) {
			case self::BACKEND_WORDPRESS_AI_CLIENT:
				return function_exists('wp_ai_client_prompt')
					|| class_exists('WordPress\\AI_Client\\AI_Client');

			case self::BACKEND_MEOW:
				return class_exists('Meow_MWAI_Core');
		}

		return false;
	}

	/**
	 * Check whether the backend is ready for content generation.
	 *
	 * @param string $backend_id Backend identifier.
	 * @return bool
	 */
	public static function is_backend_available($backend_id) {
		if (!self::is_backend_supported($backend_id)) {
			return false;
		}

		$backend = self::build_backend($backend_id, array());

		return $backend->is_available();
	}

	/**
	 * Create the backend service instance.
	 *
	 * @param array $args Optional constructor dependencies.
	 * @return AIPS_AI_Service_Interface
	 */
	public static function create($args = array()) {
		$backend_id = self::get_backend_id();
		$backend    = apply_filters('aips_ai_backend_instance', null, $backend_id, $args);

		if (!$backend instanceof AIPS_AI_Service_Interface) {
			$backend = self::build_backend($backend_id, $args);
		}

		return $backend;
	}

	/**
	 * Build a backend instance for the provided identifier.
	 *
	 * @param string $backend_id Backend identifier.
	 * @param array  $args       Optional constructor dependencies.
	 * @return AIPS_AI_Service_Interface
	 */
	private static function build_backend($backend_id, $args) {
		switch ($backend_id) {
			case self::BACKEND_WORDPRESS_AI_CLIENT:
				return new AIPS_WordPress_AI_Client_Service(
					isset($args['logger']) ? $args['logger'] : null,
					isset($args['config']) ? $args['config'] : null,
					isset($args['resilience_service']) ? $args['resilience_service'] : null
				);

			case self::BACKEND_MEOW:
			default:
				return new AIPS_Meow_Apps_AI_Service(
					isset($args['logger']) ? $args['logger'] : null,
					isset($args['config']) ? $args['config'] : null,
					isset($args['resilience_service']) ? $args['resilience_service'] : null
				);
		}
	}
}
