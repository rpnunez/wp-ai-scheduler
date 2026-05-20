<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_View {

	/**
	 * @var \Twig\Environment
	 */
	private $twig;

	public function __construct() {
		$template_dir = AIPS_PLUGIN_DIR . 'templates/admin/twig';
		$cache_dir = $this->get_cache_path('twig');
		$cache = false;

		if (!defined('WP_DEBUG') || !WP_DEBUG) {
			if (!empty($cache_dir)) {
				if (function_exists('wp_mkdir_p')) {
					wp_mkdir_p($cache_dir);
				}

				if (is_dir($cache_dir) && is_writable($cache_dir)) {
					$cache = $cache_dir;
				}
			}
		}

		$loader = new \Twig\Loader\FilesystemLoader($template_dir);

		$this->twig = new \Twig\Environment(
			$loader,
			array(
				'autoescape' => 'html',
				'cache' => $cache,
				'debug' => (defined('WP_DEBUG') && WP_DEBUG),
			)
		);

		$this->twig->addExtension(new AIPS_Twig_WP_Extension());
	}

	/**
	 * Render a Twig template directly to output.
	 *
	 * @param string $template
	 * @param array  $context
	 * @return void
	 */
	public function render($template, $context = array()) {
		try {
			echo $this->twig->render($template, $context);
		} catch (\Twig\Error\Error $e) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Unable to render this admin view.', 'ai-post-scheduler') . '</p></div>';
		}
	}

	/**
	 * Render and return template markup as a string.
	 *
	 * @param string $template
	 * @param array  $context
	 * @return string
	 */
	public function capture($template, $context = array()) {
		try {
			return $this->twig->render($template, $context);
		} catch (\Twig\Error\Error $e) {
			return '';
		}
	}

	/**
	 * Expose the underlying Twig environment for advanced use cases.
	 *
	 * @return \Twig\Environment
	 */
	public function get_engine() {
		return $this->twig;
	}

	/**
	 * Resolve cache paths by cache subsystem key.
	 *
	 * @param string $cache_name
	 * @return string
	 */
	private function get_cache_path($cache_name) {
		$cache_paths = array(
			'twig' => 'aips-cache/twig',
		);

		if (!isset($cache_paths[$cache_name])) {
			return '';
		}

		if (function_exists('wp_upload_dir')) {
			$upload_dir = wp_upload_dir();
			if (empty($upload_dir['error']) && !empty($upload_dir['basedir'])) {
				return trailingslashit($upload_dir['basedir']) . $cache_paths[$cache_name];
			}
		}

		if (defined('WP_CONTENT_DIR')) {
			return trailingslashit(WP_CONTENT_DIR) . 'uploads/' . $cache_paths[$cache_name];
		}

		return '';
	}
}
