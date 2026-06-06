<?php
if (!defined('ABSPATH')) {
	exit;
}


class AIPS_Cache_Policy {

	const SUBSYSTEM_ADMIN_BAR = 'admin_bar';

	public static function get_subsystems() {
		return array(
			self::SUBSYSTEM_ADMIN_BAR => array(
				'label' => __('Admin Bar', 'ai-post-scheduler'),
				'cache_name' => 'aips_admin_bar',
				'default_ttl' => MINUTE_IN_SECONDS,
			),
		);
	}

	public static function is_valid_subsystem($subsystem) {
		$subsystems = self::get_subsystems();
		return isset($subsystems[$subsystem]);
	}

	public static function cache_name($subsystem) {
		$subsystems = self::get_subsystems();
		return (isset($subsystems[$subsystem]['cache_name']) && $subsystems[$subsystem]['cache_name']) ? (string) $subsystems[$subsystem]['cache_name'] : '';
	}

	public static function default_ttl($subsystem) {
		$subsystems = self::get_subsystems();
		return (isset($subsystems[$subsystem]['default_ttl']) && $subsystems[$subsystem]['default_ttl']) ? (int) $subsystems[$subsystem]['default_ttl'] : (int) HOUR_IN_SECONDS;
	}

	/**
	 * Resolve a legacy cache key for a subsystem operation.
	 *
	 * @deprecated 2.8.4 Repository cache keying should use cache_read() operation IDs.
	 *
	 * @param string $subsystem Cache subsystem.
	 * @param string $operation Operation name.
	 * @param array  $context Optional operation context.
	 * @return string
	 */
	public static function key($subsystem, $operation, $context = array()) {
		switch ($subsystem) {
			case self::SUBSYSTEM_ADMIN_BAR:
				$user_id = isset($context['user_id']) ? (int) $context['user_id'] : 0;
				return 'aips_unread_count_' . $user_id;
		}

		$encoded = wp_json_encode($context);
		if (false === $encoded) {
			$encoded = maybe_serialize($context);
		}

		return $operation . ':' . md5((string) $encoded);
	}

	/**
	 * Resolve legacy invalidation targets for subsystem operations.
	 *
	 * @deprecated 2.8.4 Repository invalidation should use domain/tag invalidation in AIPS_Cacheable_Repository.
	 *
	 * @param string $subsystem Cache subsystem.
	 * @param string $operation Mutation operation.
	 * @return array<int, string>
	 */
	public static function invalidation_targets($subsystem, $operation) {
		if (!in_array($operation, array('create', 'update', 'delete', 'bulk_update', 'set_active'), true)) {
			return array();
		}

		$cache_name = self::cache_name($subsystem);
		return $cache_name ? array($cache_name) : array();
	}
}