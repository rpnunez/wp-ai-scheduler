<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shared renderer for tabbed admin hub pages.
 */
class AIPS_Admin_Hub_Layout {

	/**
	 * Render a hub page.
	 *
	 * @param array<string, mixed> $hub Hub definition.
	 * @return void
	 */
	public static function render($hub) {
		$tabs              = isset($hub['tabs']) && is_array($hub['tabs']) ? $hub['tabs'] : array();
		$active_tab_key    = self::get_active_tab_key($tabs);
		$active_tab        = self::get_tab_by_key($tabs, $active_tab_key);
		$active_subtab     = self::get_active_subtab($active_tab);
		$active_subtab_key = isset($active_subtab['key']) ? $active_subtab['key'] : '';
		$context_title     = self::get_context_value($hub, $active_tab, $active_subtab, 'title', 'page_title');
		$context_description = self::get_context_value($hub, $active_tab, $active_subtab, 'description', 'description');
		$context_actions   = self::get_context_actions($active_tab, $active_subtab);

		include AIPS_PLUGIN_DIR . 'templates/admin/hub/layout.php';
	}

	/**
	 * Resolve the current active tab key from the request.
	 *
	 * @param array<int, array<string, string>> $tabs Hub tabs.
	 * @return string
	 */
	private static function get_active_tab_key($tabs) {
		$requested = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

		foreach ($tabs as $tab) {
			if (isset($tab['key']) && $tab['key'] === $requested) {
				return $requested;
			}
		}

		if (!empty($tabs[0]['key'])) {
			return $tabs[0]['key'];
		}

		return '';
	}

	/**
	 * Fetch one tab definition by key.
	 *
	 * @param array<int, array<string, mixed>> $tabs Hub tabs.
	 * @param string                            $tab_key Active tab key.
	 * @return array<string, mixed>
	 */
	private static function get_tab_by_key($tabs, $tab_key) {
		foreach ($tabs as $tab) {
			if (isset($tab['key']) && $tab['key'] === $tab_key) {
				return $tab;
			}
		}

		return array();
	}

	/**
	 * Resolve the active subtab definition for a tab.
	 *
	 * @param array<string, mixed> $tab Active tab definition.
	 * @return array<string, mixed>
	 */
	private static function get_active_subtab($tab) {
		$subtabs = isset($tab['subtabs']) && is_array($tab['subtabs']) ? $tab['subtabs'] : array();
		$requested = isset($_GET['subtab']) ? sanitize_key(wp_unslash($_GET['subtab'])) : '';

		foreach ($subtabs as $subtab) {
			if (isset($subtab['key']) && $subtab['key'] === $requested) {
				return $subtab;
			}
		}

		if (!empty($subtabs[0]) && is_array($subtabs[0])) {
			return $subtabs[0];
		}

		return array();
	}

	/**
	 * Resolve the header copy for the current tab context.
	 *
	 * @param array<string, mixed> $hub Hub definition.
	 * @param array<string, mixed> $tab Active tab definition.
	 * @param array<string, mixed> $subtab Active subtab definition.
	 * @param string               $context_key Preferred context key.
	 * @param string               $hub_fallback_key Hub fallback key.
	 * @return string
	 */
	private static function get_context_value($hub, $tab, $subtab, $context_key, $hub_fallback_key) {
		if (!empty($subtab[ $context_key ])) {
			return $subtab[ $context_key ];
		}

		if (!empty($tab[ $context_key ])) {
			return $tab[ $context_key ];
		}

		return !empty($hub[ $hub_fallback_key ]) ? $hub[ $hub_fallback_key ] : '';
	}

	/**
	 * Resolve header actions for the current tab context.
	 *
	 * @param array<string, mixed> $tab Active tab definition.
	 * @param array<string, mixed> $subtab Active subtab definition.
	 * @return array<int, array<string, string>>
	 */
	private static function get_context_actions($tab, $subtab) {
		if (!empty($subtab['actions']) && is_array($subtab['actions'])) {
			return $subtab['actions'];
		}

		if (!empty($tab['actions']) && is_array($tab['actions'])) {
			return $tab['actions'];
		}

		return array();
	}
}
