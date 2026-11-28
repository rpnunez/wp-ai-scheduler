<?php
/**
 * Email Renderer
 *
 * Turns an AIPS_Email_View into a full HTML email document (and a plain-text
 * alternative) by composing the shared layout with the region partials in
 * `templates/email/`.
 *
 * Client-compatibility rules enforced here and in the partials:
 *
 *   - Table-based layout only. No flexbox, CSS grid, or `aspect-ratio`: the
 *     Outlook Word rendering engine supports none of them.
 *   - Every style is inline. Gmail strips `<style>` blocks entirely, so the one
 *     in the layout is progressive enhancement (mobile stacking, dark mode) and
 *     never the sole source of a style.
 *   - No gradients or overflow clipping. The design's fade-out content preview is
 *     reproduced by truncating server-side instead.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Email_Renderer
 *
 * Usage:
 *
 *   $renderer = new AIPS_Email_Renderer();
 *   $html     = $renderer->render($view);
 *   $text     = $renderer->render_text($view);
 */
class AIPS_Email_Renderer {

	/**
	 * Directory holding the email templates, relative to the plugin root.
	 *
	 * @var string
	 */
	const TEMPLATE_DIR = 'templates/email/';

	/**
	 * Layout template filename.
	 *
	 * @var string
	 */
	const LAYOUT = 'layout.php';

	/**
	 * Render the full HTML email document for a view.
	 *
	 * @param AIPS_Email_View $view View to render.
	 * @return string Complete HTML document, or an empty string when the layout is missing.
	 */
	public function render(AIPS_Email_View $view) {
		$html = $this->template(self::LAYOUT, array(
			'view'     => $view,
			'theme'    => AIPS_Email_Theme::tokens(),
			'renderer' => $this,
		));

		/**
		 * Filter: aips_email_html
		 *
		 * Final chance to alter a rendered notification email.
		 *
		 * @since 3.8.0
		 * @param string          $html Rendered HTML document.
		 * @param AIPS_Email_View $view The view it was rendered from.
		 */
		return (string) apply_filters('aips_email_html', $html, $view);
	}

	/**
	 * Render one region partial.
	 *
	 * Partials live in `templates/email/partials/` and receive `$view`, `$theme`,
	 * and `$renderer` in scope, plus anything passed in `$args`.
	 *
	 * @param string          $name View-region partial name, without extension (e.g. 'meta-cells').
	 * @param AIPS_Email_View $view View being rendered.
	 * @param array           $args Extra variables for the partial scope.
	 * @return string
	 */
	public function partial($name, AIPS_Email_View $view, array $args = array()) {
		$name = str_replace(array('..', '/', '\\'), '', (string) $name);

		return $this->template('partials/' . $name . '.php', array_merge(
			array(
				'view'     => $view,
				'theme'    => AIPS_Email_Theme::tokens(),
				'renderer' => $this,
			),
			$args
		));
	}

	/**
	 * Render the plain-text alternative for a view.
	 *
	 * Mail clients that cannot (or will not) display HTML get this part, and spam
	 * filters score a multipart message better than an HTML-only one.
	 *
	 * @param AIPS_Email_View $view View to render.
	 * @return string
	 */
	public function render_text(AIPS_Email_View $view) {
		$lines = array();

		if ($view->has('header_title')) {
			$lines[] = $this->plain($view->get('header_title', ''));
			if ($view->has('header_subtitle')) {
				$lines[] = $this->plain($view->get('header_subtitle', ''));
			}
			$lines[] = '';
		}

		if ($view->has('eyebrow')) {
			$lines[] = strtoupper($this->plain($view->get('eyebrow', '')));
		}

		if ($view->has('headline')) {
			$lines[] = $this->plain($view->get('headline', ''));
			$lines[] = str_repeat('=', min(60, max(3, strlen($this->plain($view->get('headline', ''))))));
			$lines[] = '';
		}

		if ($view->has('callout')) {
			$callout = $view->get('callout', array());
			if (!empty($callout['title'])) {
				$lines[] = '! ' . $this->plain($callout['title']);
			}
			if (!empty($callout['body'])) {
				$lines[] = '  ' . $this->plain($callout['body']);
			}
			$lines[] = '';
		}

		if ($view->has('body_text')) {
			$lines[] = $this->plain($view->get('body_text', ''));
			$lines[] = '';
		}

		if ($view->has('stat_tiles')) {
			foreach ($view->get('stat_tiles', array()) as $tile) {
				$lines[] = sprintf('%s: %s', $this->plain($tile['label']), $this->plain($tile['value']));
			}
			$lines[] = '';
		}

		if ($view->has('meta_cells')) {
			foreach ($view->meta_cells() as $cell) {
				$lines[] = sprintf('%s: %s', $this->plain($cell['label']), $this->plain($cell['value']));
			}
			$lines[] = '';
		}

		if ($view->has('excerpt')) {
			$lines[] = $this->plain($view->get('excerpt', ''));
			$lines[] = '';
		}

		foreach ($view->get('preview_blocks', array()) as $block) {
			$lines[] = $this->plain($block['text']);
			$lines[] = '';
		}

		if ($view->has('item_caption')) {
			$lines[] = strtoupper($this->plain($view->get('item_caption', '')));
		}

		foreach ($view->get('item_rows', array()) as $row) {
			$lines[] = trim('- ' . $this->plain($row['title']) . ('' !== $row['meta'] ? ' (' . $this->plain($row['meta']) . ')' : ''));
		}

		foreach ($view->get('item_groups', array()) as $group) {
			$lines[] = '';
			$lines[] = strtoupper($this->plain($group['label']));
			foreach ($group['rows'] as $row) {
				$lines[] = trim('- ' . $this->plain($row['title']) . ('' !== $row['meta'] ? ' (' . $this->plain($row['meta']) . ')' : ''));
			}
		}

		if ($view->has('preview_link')) {
			$link    = $view->get('preview_link', array());
			$lines[] = '';
			$lines[] = $this->plain($link['label']) . ': ' . $link['url'];
		}

		if ($view->has('actions')) {
			$lines[] = '';
			foreach ($view->actions() as $action) {
				$lines[] = $this->plain($action['label']) . ': ' . $action['url'];
			}
		}

		$lines[] = '';
		$lines[] = '---';
		$lines[] = sprintf(
			/* translators: %s: site name */
			__('Sent by AI Post Scheduler on %s', 'ai-post-scheduler'),
			$this->plain($view->get('site_name', ''))
		);

		if ($view->has('footer_link')) {
			$footer  = $view->get('footer_link', array());
			$lines[] = $this->plain($footer['label']) . ': ' . $footer['url'];
		}

		// Collapse runs of blank lines so optional regions do not leave gaps.
		$text = implode("\n", $lines);
		$text = preg_replace("/\n{3,}/", "\n\n", $text);

		return trim((string) $text) . "\n";
	}

	/**
	 * Include a template file and capture its output.
	 *
	 * @param string $relative_path Path under `templates/email/`.
	 * @param array  $args          Variables exposed to the template scope.
	 * @return string Rendered output, or empty string when the file is absent.
	 */
	private function template($relative_path, array $args = array()) {
		$path = AIPS_PLUGIN_DIR . self::TEMPLATE_DIR . $relative_path;

		if (!file_exists($path)) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template scope, keys are internal.
		extract($args, EXTR_SKIP);

		ob_start();
		include $path;

		return (string) ob_get_clean();
	}

	/**
	 * Flatten a value for the plain-text part.
	 *
	 * @param string $value Source string.
	 * @return string
	 */
	private function plain($value) {
		$value = wp_strip_all_tags((string) $value);
		$value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

		return trim(preg_replace('/\s+/', ' ', $value));
	}
}
