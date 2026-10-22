<?php
/**
 * Integration Registry
 *
 * Centralises the map of integration_id => adapter class name for every
 * "AIPS-compatible plugin" bridge AIPS knows about. Core adapters are listed
 * in $map; any other plugin can register its own adapter without touching
 * AIPS core by hooking the 'aips_integrations_registry' filter:
 *
 *     add_filter('aips_integrations_registry', function ($map) {
 *         $map['my_plugin'] = 'My_Plugin_AIPS_Integration';
 *         return $map;
 *     });
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Integration_Registry {

	/**
	 * Core-shipped adapters: 'integration_id' => Adapter_Class::class.
	 *
	 * @var array<string, string>
	 */
	private static $map = array(
		'acf'         => 'AIPS_Integration_ACF',
		'native_meta' => 'AIPS_Integration_Native_Meta',
	);

	/**
	 * Full registered map, including third-party adapters added via the
	 * 'aips_integrations_registry' filter. Does not check availability.
	 *
	 * @return array<string, string>
	 */
	public static function get_registered() {
		/**
		 * Filter the integration adapter registry.
		 *
		 * @param array<string, string> $map integration_id => class name implementing AIPS_Integration_Interface.
		 */
		return apply_filters('aips_integrations_registry', self::$map);
	}

	/**
	 * Resolve and instantiate a single adapter by id.
	 *
	 * @param string $integration_id Integration identifier.
	 * @return AIPS_Integration_Interface|null Instance, or null if unknown or not instantiable.
	 */
	public static function get($integration_id) {
		$map = self::get_registered();

		if (!isset($map[$integration_id])) {
			return null;
		}

		$class_name = $map[$integration_id];

		if (!class_exists($class_name) || !in_array('AIPS_Integration_Interface', class_implements($class_name), true)) {
			return null;
		}

		return new $class_name();
	}

	/**
	 * All registered adapters whose target plugin is actually active on this
	 * WordPress instance, keyed by integration_id.
	 *
	 * @return array<string, AIPS_Integration_Interface>
	 */
	public static function get_available() {
		$available = array();

		foreach (array_keys(self::get_registered()) as $integration_id) {
			$adapter = self::get($integration_id);

			if ($adapter instanceof AIPS_Integration_Interface && $adapter->is_available()) {
				$available[$integration_id] = $adapter;
			}
		}

		return $available;
	}

	/**
	 * Check whether an integration id is registered (regardless of availability).
	 *
	 * @param string $integration_id Integration identifier.
	 * @return bool
	 */
	public static function has($integration_id) {
		return isset(self::get_registered()[$integration_id]);
	}
}
