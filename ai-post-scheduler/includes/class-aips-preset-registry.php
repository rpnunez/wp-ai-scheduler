<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Preset_Registry {
	const OVERRIDES_OPTION = 'aips_preset_overrides';

	public static function get_all() {
		$presets = self::get_defaults();
		$overrides = get_option(self::OVERRIDES_OPTION, array());
		if (is_array($overrides)) {
			foreach ($overrides as $id => $preset) {
				$safe_id = sanitize_key($id);
				if (empty($safe_id) || !is_array($preset)) {
					continue;
				}
				$presets[$safe_id] = self::sanitize_preset($preset, $safe_id);
			}
		}
		return $presets;
	}

	public static function get($preset_id) {
		$presets = self::get_all();
		$preset_id = sanitize_key($preset_id);
		return isset($presets[$preset_id]) ? $presets[$preset_id] : null;
	}

	public static function save_override_from_template($name, $config) {
		if (!current_user_can('manage_options')) {
			return new WP_Error('forbidden', __('Insufficient permissions.', 'ai-post-scheduler'));
		}
		$key = sanitize_title($name);
		if ('' === $key) {
			$key = 'custom-' . time();
		}
		$preset = self::sanitize_preset($config, $key);
		$preset['name'] = sanitize_text_field($name);
		$preset['source'] = 'custom';
		$overrides = get_option(self::OVERRIDES_OPTION, array());
		if (!is_array($overrides)) {
			$overrides = array();
		}
		$overrides[$key] = $preset;
		update_option(self::OVERRIDES_OPTION, $overrides, false);
		return $key;
	}

	public static function get_defaults() {
		return array(
			'balanced-weekly' => array('name' => __('Balanced Weekly', 'ai-post-scheduler'), 'prompt_tone' => 'professional', 'prompt_length' => 'medium', 'frequency' => 'weekly', 'review_mode' => 'manual', 'default_category' => (int) AIPS_Config::get_instance()->get_option('aips_default_category'), 'default_taxonomy' => array(), 'source_research_mode' => 'optional', 'include_sources' => 0, 'source_group_ids' => array(), 'source' => 'core'),
			'rapid-daily' => array('name' => __('Rapid Daily', 'ai-post-scheduler'), 'prompt_tone' => 'concise', 'prompt_length' => 'short', 'frequency' => 'daily', 'review_mode' => 'manual', 'default_category' => (int) AIPS_Config::get_instance()->get_option('aips_default_category'), 'default_taxonomy' => array(), 'source_research_mode' => 'aggressive', 'include_sources' => 1, 'source_group_ids' => array(), 'source' => 'core'),
		);
	}

	private static function sanitize_preset($preset, $id) {
		return array(
			'id' => sanitize_key($id),
			'name' => isset($preset['name']) ? sanitize_text_field($preset['name']) : sanitize_text_field($id),
			'prompt_tone' => isset($preset['prompt_tone']) ? sanitize_text_field($preset['prompt_tone']) : 'professional',
			'prompt_length' => isset($preset['prompt_length']) ? sanitize_text_field($preset['prompt_length']) : 'medium',
			'frequency' => isset($preset['frequency']) ? sanitize_text_field($preset['frequency']) : 'weekly',
			'review_mode' => isset($preset['review_mode']) ? sanitize_text_field($preset['review_mode']) : 'manual',
			'default_category' => isset($preset['default_category']) ? absint($preset['default_category']) : 0,
			'default_taxonomy' => isset($preset['default_taxonomy']) && is_array($preset['default_taxonomy']) ? $preset['default_taxonomy'] : array(),
			'source_research_mode' => isset($preset['source_research_mode']) ? sanitize_text_field($preset['source_research_mode']) : 'optional',
			'include_sources' => !empty($preset['include_sources']) ? 1 : 0,
			'source_group_ids' => isset($preset['source_group_ids']) && is_array($preset['source_group_ids']) ? array_map('absint', $preset['source_group_ids']) : array(),
			'source' => isset($preset['source']) ? sanitize_text_field($preset['source']) : 'core',
		);
	}
}
