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

	public static function get_subsystems() {
		return array(
			self::SUBSYSTEM_ADMIN_BAR => array(
				'label' => __('Admin Bar', 'ai-post-scheduler'),
				'cache_name' => 'aips_admin_bar',
				'default_ttl' => MINUTE_IN_SECONDS,
			),
			self::SUBSYSTEM_SCHEDULE_REPOSITORY => array(
				'label' => __('Schedule Repository', 'ai-post-scheduler'),
				'cache_name' => 'aips_schedule_repository',
			),
			self::SUBSYSTEM_ARTICLE_STRUCTURE_REPOSITORY => array(
				'label' => __('Article Structure Repository', 'ai-post-scheduler'),
				'cache_name' => 'aips_article_structure_repository',
			),
			self::SUBSYSTEM_PROMPT_SECTION_REPOSITORY => array(
				'label' => __('Prompt Section Repository', 'ai-post-scheduler'),
				'cache_name' => 'aips_prompt_section_repository',
			),
			self::SUBSYSTEM_POST_SLICES_REPOSITORY => array(
				'label' => __('Post Slices Repository', 'ai-post-scheduler'),
				'cache_name' => 'aips_post_slices_repository',
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

		$encoded = wp_json_encode($context);
		if (false === $encoded) {
			$encoded = maybe_serialize($context);
		}

		return $operation . ':' . md5((string) $encoded);
	}

	public static function invalidation_targets($subsystem, $operation) {
		if (!in_array($operation, array('create', 'update', 'delete', 'bulk_update', 'set_active'), true)) {
			return array();
		}

		$cache_name = self::cache_name($subsystem);
		return $cache_name ? array($cache_name) : array();
	}
}
