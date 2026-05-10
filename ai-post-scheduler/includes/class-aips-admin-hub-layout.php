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
		$context           = self::build_context($hub, $active_tab, $active_subtab);
		$context_title     = !empty($context['title']) ? $context['title'] : '';
		$context_description = !empty($context['description']) ? $context['description'] : '';
		$context_actions   = !empty($context['actions']) && is_array($context['actions']) ? $context['actions'] : array();
		$context_metrics   = !empty($context['metrics']) && is_array($context['metrics']) ? $context['metrics'] : array();
		$context_breadcrumbs = !empty($context['breadcrumbs']) && is_array($context['breadcrumbs']) ? $context['breadcrumbs'] : array();
		$context_eyebrow   = !empty($context['eyebrow']) ? $context['eyebrow'] : ( !empty($hub['page_title']) ? $hub['page_title'] : '' );

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
	private static function build_context($hub, $tab, $subtab) {
		$context = array(
			'eyebrow'     => !empty($hub['page_title']) ? $hub['page_title'] : '',
			'title'       => !empty($hub['page_title']) ? $hub['page_title'] : '',
			'description' => !empty($hub['description']) ? $hub['description'] : '',
			'actions'     => array(),
			'metrics'     => array(),
			'breadcrumbs' => array(),
		);

		$context = self::merge_context($context, $hub);
		$context = self::merge_context($context, $tab);
		$context = self::merge_context($context, $subtab);

		$callback = '';
		if (!empty($subtab['context_callback'])) {
			$callback = $subtab['context_callback'];
		} elseif (!empty($tab['context_callback'])) {
			$callback = $tab['context_callback'];
		} elseif (!empty($hub['context_callback'])) {
			$callback = $hub['context_callback'];
		}

		if ($callback && is_callable($callback)) {
			$callback_context = call_user_func($callback, $hub, $tab, $subtab);
			if (is_array($callback_context)) {
				$context = array_merge($context, $callback_context);
			}
		}

		return $context;
	}

	/**
	 * Merge known context keys from a hub/tab/subtab definition.
	 *
	 * @param array<string, mixed> $context Existing context.
	 * @param array<string, mixed> $source  Config source.
	 * @return array<string, mixed>
	 */
	private static function merge_context($context, $source) {
		$keys = array('eyebrow', 'title', 'description', 'actions', 'metrics', 'breadcrumbs');

		foreach ($keys as $key) {
			if (isset($source[ $key ])) {
				$context[ $key ] = $source[ $key ];
			}
		}

		return $context;
	}
}
