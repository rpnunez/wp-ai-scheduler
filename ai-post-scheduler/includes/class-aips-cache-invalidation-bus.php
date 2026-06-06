<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Cache_Invalidation_Bus {

	/**
	 * Legacy cache invalidation bridge kept for compatibility.
	 *
	 * @deprecated 2.8.4 Repository cache invalidation should use AIPS_Cacheable_Repository domain/tag invalidation.
	 *
	 * @param string $subsystem Legacy cache subsystem.
	 * @param string $operation Legacy invalidation operation.
	 * @param array  $context Optional operation context.
	 * @return array<int, string>
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
	 * Rebuild legacy cache subsystems.
	 *
	 * @deprecated 2.8.4 Repository cache rebuild should rely on repository domain/tag invalidation.
	 *
	 * @param string $subsystem Optional subsystem. Use 'all' for every subsystem.
	 * @return array<int, string>
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