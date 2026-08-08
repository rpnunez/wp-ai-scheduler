<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Lightweight template renderer for explicit view-model rendering.
 */
class AIPS_Template_Renderer {

	/**
	 * Render a plugin template using explicit view-model variables.
	 *
	 * @param string $template_relative_path Relative path from plugin root.
	 * @param array  $view_model             Variables extracted for template scope.
	 * @return void
	 */
	public static function render($template_relative_path, array $view_model = array()) {
		$template_relative_path = wp_normalize_path(ltrim((string) $template_relative_path, '/\\'));

		// Prevent directory traversal (e.g. "../") and keep includes within the plugin directory.
		if (preg_match('#(^|/)\\.\\.(/|$)#', $template_relative_path)) {
			return;
		}

		$plugin_root   = realpath(AIPS_PLUGIN_DIR);
		$template_path = realpath(AIPS_PLUGIN_DIR . $template_relative_path);

		if (!$plugin_root || !$template_path) {
			return;
		}

		$plugin_root   = wp_normalize_path($plugin_root);
		$template_path = wp_normalize_path($template_path);

		if (0 !== strpos($template_path, rtrim($plugin_root, '/') . '/')) {
			return;
		}

		extract($view_model, EXTR_SKIP);
		include $template_path;
	}
}
