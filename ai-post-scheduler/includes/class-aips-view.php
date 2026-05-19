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
		$cache_dir = AIPS_PLUGIN_DIR . 'cache/twig';

		if (!defined('WP_DEBUG') || !WP_DEBUG) {
			if (function_exists('wp_mkdir_p')) {
				wp_mkdir_p($cache_dir);
			}
		}

		$loader = new \Twig\Loader\FilesystemLoader($template_dir);

		$this->twig = new \Twig\Environment(
			$loader,
			array(
				'autoescape' => 'html',
				'cache' => (defined('WP_DEBUG') && WP_DEBUG) ? false : $cache_dir,
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
		echo $this->twig->render($template, $context);
	}

	/**
	 * Render and return template markup as a string.
	 *
	 * @param string $template
	 * @param array  $context
	 * @return string
	 */
	public function capture($template, $context = array()) {
		return $this->twig->render($template, $context);
	}

	/**
	 * Expose the underlying Twig environment for advanced use cases.
	 *
	 * @return \Twig\Environment
	 */
	public function get_engine() {
		return $this->twig;
	}
}
