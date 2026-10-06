<?php
/**
 * Redirection Plugin Provider
 *
 * Stores redirects in the Redirection plugin (by John Godley) through its
 * Red_Item API, in the plugin's first redirect group.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Redirect_Provider_Redirection
 */
class AIPS_Redirect_Provider_Redirection implements AIPS_Redirect_Provider {

	const KEY = 'redirection';

	public function get_key(): string {
		return self::KEY;
	}

	public function get_label(): string {
		return __('Redirection plugin', 'ai-post-scheduler');
	}

	public function is_available(): bool {
		return class_exists('Red_Item') && class_exists('Red_Group') && method_exists('Red_Item', 'create') && $this->get_group_id() > 0;
	}

	public function create(string $source_path, string $target_url, int $status_code) {
		$is_gone = ($status_code === 410);

		$item = Red_Item::create(array(
			'url'         => $source_path,
			'match_type'  => 'url',
			'action_type' => $is_gone ? 'error' : 'url',
			'action_code' => $status_code,
			'action_data' => $is_gone ? array() : array('url' => $target_url),
			'group_id'    => $this->get_group_id(),
			'regex'       => 0,
			'title'       => __('Created by AI Post Scheduler', 'ai-post-scheduler'),
		));

		if (is_wp_error($item)) {
			return $item;
		}

		return (string) $item->get_id();
	}

	public function delete(string $provider_ref, string $source_path): bool {
		$id = (int) $provider_ref;
		if ($id <= 0 || !class_exists('Red_Item')) {
			return $id <= 0;
		}

		$item = Red_Item::get_by_id($id);
		if (!$item) {
			return true;
		}

		return (bool) $item->delete() || !Red_Item::get_by_id($id);
	}

	/**
	 * Group to add redirects to: the first group of the WordPress module.
	 *
	 * @return int
	 */
	private function get_group_id(): int {
		if (!class_exists('Red_Group') || !method_exists('Red_Group', 'get_all')) {
			return 0;
		}

		/**
		 * Filters the Redirection group AIPS adds redirects to.
		 *
		 * @param int $group_id Default 0 = first group of the WordPress module.
		 */
		$group_id = (int) apply_filters('aips_redirection_group_id', 0);
		if ($group_id > 0) {
			return $group_id;
		}

		$groups = Red_Group::get_all();
		foreach ((array) $groups as $group) {
			if ((int) ($group['module_id'] ?? 0) === 1) {
				return (int) $group['id'];
			}
		}

		return !empty($groups[0]['id']) ? (int) $groups[0]['id'] : 0;
	}
}
