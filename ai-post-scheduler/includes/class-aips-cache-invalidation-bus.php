<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Coarse-grained cache invalidation bus (subsystem-level flush).
 *
 * @deprecated since 2.6.0 Repository invalidation now uses AIPS_Cacheable_Repository::invalidate_cache_domain().
 *   Only remaining caller: AIPS_System_Status_Controller (manual admin rebuild).
 *   Will be removed when Admin_Bar and System_Status_Controller are migrated.
 */
class AIPS_Cache_Invalidation_Bus {

	/**
	 * Flush all cache stores registered for a given subsystem.
	 *
	 * @deprecated since 2.6.0 Use AIPS_Cacheable_Repository::invalidate_cache_domain() in repositories instead.
	 */
	public static function invalidate($subsystem, $operation, $context = array()) {
		$targets = AIPS_Cache_Policy::invalidation_targets($subsystem, $operation);
		foreach ($targets as $cache_name) {
			$cache = AIPS_Cache_Factory::named($cache_name);
			$cache->flush();
		}

		do_action('aips_cache_invalidated', $subsystem, $operation, $context, $targets);
		return $targets;
	}

	/**
	 * Flush all known subsystem caches (admin rebuild).
	 *
	 * @deprecated since 2.6.0 Use AIPS_Cacheable_Repository::invalidate_cache_domain() in repositories instead.
	 */
	public static function rebuild($subsystem = 'all') {
		if ('all' !== $subsystem && !AIPS_Cache_Policy::is_valid_subsystem($subsystem)) {
			$subsystem = 'all';
		}

		$subsystems = array_keys(AIPS_Cache_Policy::get_subsystems());
		if ('all' !== $subsystem) {
			$subsystems = array($subsystem);
		}

		$affected = array();
		foreach ($subsystems as $item) {
			$affected = array_merge($affected, self::invalidate($item, 'update'));
		}
		return array_values(array_unique($affected));
	}
}
