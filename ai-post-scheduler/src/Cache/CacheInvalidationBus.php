<?php
namespace AIPS\Cache;

if (!defined('ABSPATH')) {
	exit;
}

use AIPS\Cache\CachePolicy;
use AIPS\Cache\CacheFactory;

class CacheInvalidationBus {

	public static function invalidate($subsystem, $operation, $context = array()) {
		$targets = CachePolicy::invalidation_targets($subsystem, $operation);
		foreach ($targets as $cache_name) {
			$cache = CacheFactory::named($cache_name);
			$cache->flush();
		}

		do_action('aips_cache_invalidated', $subsystem, $operation, $context, $targets);
		return $targets;
	}

	public static function rebuild($subsystem = 'all') {
		if ('all' !== $subsystem && !CachePolicy::is_valid_subsystem($subsystem)) {
			$subsystem = 'all';
		}

		$subsystems = array_keys(CachePolicy::get_subsystems());
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
