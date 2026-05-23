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

	/**
	 * Resolve the active backend identifier.
	 *
	 * @return string
	 */
	public static function get_backend_id() {
		$backend_id = apply_filters('aips_ai_backend', self::BACKEND_MEOW);
		$backend_id = is_string($backend_id) ? sanitize_key($backend_id) : '';

		return '' !== $backend_id ? $backend_id : self::BACKEND_MEOW;
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
			case self::BACKEND_MEOW:
			default:
				return new AIPS_Meow_AI_Service(
					isset($args['logger']) ? $args['logger'] : null,
					isset($args['config']) ? $args['config'] : null,
					isset($args['resilience_service']) ? $args['resilience_service'] : null
				);
		}
	}
}
