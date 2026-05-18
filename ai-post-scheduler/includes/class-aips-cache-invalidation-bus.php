<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Cache_Invalidation_Bus {

	public static function invalidate($subsystem, $operation, $context = array()) {
		$targets = AIPS_Cache_Policy::invalidation_targets($subsystem, $operation);
		foreach ($targets as $cache_name) {
			$cache = AIPS_Cache_Factory::named($cache_name);
			$cache->flush();
		}

		do_action('aips_cache_invalidated', $subsystem, $operation, $context, $targets);
		return $targets;
	}

	public static function rebuild($subsystem = 'all') {
		$subsystems = array(
			AIPS_Cache_Policy::SUBSYSTEM_ADMIN_BAR,
			AIPS_Cache_Policy::SUBSYSTEM_SCHEDULE_REPOSITORY,
			AIPS_Cache_Policy::SUBSYSTEM_ARTICLE_STRUCTURE_REPOSITORY,
			AIPS_Cache_Policy::SUBSYSTEM_PROMPT_SECTION_REPOSITORY,
			AIPS_Cache_Policy::SUBSYSTEM_POST_SLICES_REPOSITORY,
		);
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
