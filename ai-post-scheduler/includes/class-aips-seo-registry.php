<?php
/**
 * SEO Provider Registry
 *
 * Centralises the map of provider_id => adapter class name for every
 * SEO provider AIPS knows about. Core adapters are listed in $map; any other
 * SEO plugin can register its own adapter without touching AIPS core by hooking
 * the 'aips_seo_providers_registry' filter:
 *
 *     add_filter('aips_seo_providers_registry', function ($map) {
 *         $map['seopress'] = 'AIPS_SEO_Provider_SEOPress';
 *         return $map;
 *     });
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_SEO_Registry {

	/**
	 * Core-shipped SEO adapters: 'provider_id' => Adapter_Class::class.
	 *
	 * @var array<string, string>
	 */
	private static $map = array(
		'yoast'     => 'AIPS_SEO_Provider_Yoast',
		'rank_math' => 'AIPS_SEO_Provider_RankMath',
		'native'    => 'AIPS_SEO_Provider_Native',
	);

	/**
	 * Full registered map, including third-party adapters added via the
	 * 'aips_seo_providers_registry' filter.
	 *
	 * @return array<string, string>
	 */
	public static function get_registered() {
		/**
		 * Filter the SEO provider adapter registry.
		 *
		 * @param array<string, string> $map provider_id => class name implementing AIPS_SEO_Provider_Interface.
		 */
		return apply_filters('aips_seo_providers_registry', self::$map);
	}

	/**
	 * Resolve and instantiate a single SEO provider by ID.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return AIPS_SEO_Provider_Interface|null Instance, or null if unknown or not instantiable.
	 */
	public static function get($provider_id) {
		$map = self::get_registered();

		if (!isset($map[$provider_id])) {
			return null;
		}

		$class_name = $map[$provider_id];

		if (!class_exists($class_name) || !in_array('AIPS_SEO_Provider_Interface', class_implements($class_name), true)) {
			return null;
		}

		return new $class_name();
	}

	/**
	 * All registered adapters whose target plugin is active on this site,
	 * keyed by provider_id.
	 *
	 * @return array<string, AIPS_SEO_Provider_Interface>
	 */
	public static function get_available() {
		$available = array();

		foreach (array_keys(self::get_registered()) as $provider_id) {
			$adapter = self::get($provider_id);

			if ($adapter instanceof AIPS_SEO_Provider_Interface && $adapter->is_available()) {
				$available[$provider_id] = $adapter;
			}
		}

		return $available;
	}

	/**
	 * Determine the currently active primary SEO provider.
	 *
	 * Prioritizes dedicated third-party SEO plugins (Yoast, Rank Math, etc.)
	 * and falls back to 'native' if no third-party SEO plugin is active.
	 *
	 * @return AIPS_SEO_Provider_Interface
	 */
	public static function get_active_provider() {
		$available = self::get_available();

		// Check for active third-party plugins first (non-native)
		foreach ($available as $id => $adapter) {
			if ($id !== 'native') {
				return $adapter;
			}
		}

		// Fall back to native adapter
		$native = self::get('native');
		return ($native instanceof AIPS_SEO_Provider_Interface) ? $native : new AIPS_SEO_Provider_Native();
	}

	/**
	 * Check whether an SEO provider ID is registered.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return bool
	 */
	public static function has($provider_id) {
		return isset(self::get_registered()[$provider_id]);
	}
}
