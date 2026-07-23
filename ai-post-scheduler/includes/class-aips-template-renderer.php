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
		$template_relative_path = ltrim((string) $template_relative_path, '/\\');
		$template_path = AIPS_PLUGIN_DIR . $template_relative_path;

		if (!file_exists($template_path)) {
			return;
		}

		extract($view_model, EXTR_SKIP);
		include $template_path;
	}
}
