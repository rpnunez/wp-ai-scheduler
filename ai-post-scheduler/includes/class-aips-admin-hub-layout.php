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
		$tabs           = isset($hub['tabs']) && is_array($hub['tabs']) ? $hub['tabs'] : array();
		$active_tab_key = self::get_active_tab_key($tabs);

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
}
