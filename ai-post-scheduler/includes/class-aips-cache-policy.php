<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Cache_Policy {

	const SUBSYSTEM_ADMIN_BAR = 'admin_bar';
	const SUBSYSTEM_SCHEDULE_REPOSITORY = 'schedule_repository';
	const SUBSYSTEM_ARTICLE_STRUCTURE_REPOSITORY = 'article_structure_repository';
	const SUBSYSTEM_PROMPT_SECTION_REPOSITORY = 'prompt_section_repository';
	const SUBSYSTEM_POST_SLICES_REPOSITORY = 'post_slices_repository';

	public static function cache_name($subsystem) {
		$map = array(
			self::SUBSYSTEM_SCHEDULE_REPOSITORY => 'aips_schedule_repository',
			self::SUBSYSTEM_ARTICLE_STRUCTURE_REPOSITORY => 'aips_article_structure_repository',
			self::SUBSYSTEM_PROMPT_SECTION_REPOSITORY => 'aips_prompt_section_repository',
			self::SUBSYSTEM_POST_SLICES_REPOSITORY => 'aips_post_slices_repository',
		);

		return isset($map[$subsystem]) ? $map[$subsystem] : '';
	}

	public static function default_ttl($subsystem) {
		$ttls = array(
			self::SUBSYSTEM_ADMIN_BAR => MINUTE_IN_SECONDS,
		);

		return isset($ttls[$subsystem]) ? (int) $ttls[$subsystem] : (int) HOUR_IN_SECONDS;
	}

	public static function key($subsystem, $operation, $context = array()) {
		switch ($subsystem) {
			case self::SUBSYSTEM_ADMIN_BAR:
				$user_id = isset($context['user_id']) ? (int) $context['user_id'] : 0;
				return 'aips_unread_count_' . $user_id;
			case self::SUBSYSTEM_SCHEDULE_REPOSITORY:
				if ('all' === $operation) {
					return 'all:' . (!empty($context['active_only']) ? '1' : '0');
				}
				if ('id' === $operation) {
					return 'id:' . (int) $context['id'];
				}
				if ('due' === $operation) {
					return 'due:' . (int) $context['current_time'] . ':' . (int) $context['limit'];
				}
				break;
			case self::SUBSYSTEM_ARTICLE_STRUCTURE_REPOSITORY:
			case self::SUBSYSTEM_POST_SLICES_REPOSITORY:
				if ('all' === $operation) {
					return 'all:' . (!empty($context['active_only']) ? '1' : '0');
				}
				if ('id' === $operation) {
					return 'id:' . (int) $context['id'];
				}
				break;
			case self::SUBSYSTEM_PROMPT_SECTION_REPOSITORY:
				if ('all' === $operation) {
					return 'all:' . (!empty($context['active_only']) ? '1' : '0');
				}
				if ('id' === $operation) {
					return 'id:' . (int) $context['id'];
				}
				if ('key' === $operation) {
					return 'key:' . (string) $context['section_key'];
				}
				break;
		}

		return $operation . ':' . md5(wp_json_encode($context));
	}

	public static function invalidation_targets($subsystem, $operation) {
		$targets = array(
			self::SUBSYSTEM_ADMIN_BAR => array('aips_admin_bar'),
			self::SUBSYSTEM_SCHEDULE_REPOSITORY => array(self::cache_name(self::SUBSYSTEM_SCHEDULE_REPOSITORY)),
			self::SUBSYSTEM_ARTICLE_STRUCTURE_REPOSITORY => array(self::cache_name(self::SUBSYSTEM_ARTICLE_STRUCTURE_REPOSITORY)),
			self::SUBSYSTEM_PROMPT_SECTION_REPOSITORY => array(self::cache_name(self::SUBSYSTEM_PROMPT_SECTION_REPOSITORY)),
			self::SUBSYSTEM_POST_SLICES_REPOSITORY => array(self::cache_name(self::SUBSYSTEM_POST_SLICES_REPOSITORY)),
		);

		if (in_array($operation, array('create', 'update', 'delete', 'bulk_update', 'set_active'), true) && isset($targets[$subsystem])) {
			return $targets[$subsystem];
		}

		return array();
	}
}
