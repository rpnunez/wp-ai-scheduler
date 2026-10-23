<?php
/**
 * Structure Shortcodes
 *
 * Registers WordPress shortcodes for Classic Editor rendering of article components
 * (FAQs, Pros & Cons, Ratings, Step Lists, Comparison Tables, Callout Boxes).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Structure_Shortcodes
 */
class AIPS_Structure_Shortcodes {

	/**
	 * Register shortcodes.
	 */
	public static function register(): void {
		add_shortcode('aips_faq', array(__CLASS__, 'render_faq'));
		add_shortcode('aips_pros_cons', array(__CLASS__, 'render_pros_cons'));
		add_shortcode('aips_rating', array(__CLASS__, 'render_rating'));
		add_shortcode('aips_step_list', array(__CLASS__, 'render_step_list'));
		add_shortcode('aips_step', array(__CLASS__, 'render_step'));
		add_shortcode('aips_table', array(__CLASS__, 'render_table'));
		add_shortcode('aips_callout', array(__CLASS__, 'render_callout'));
	}

	/**
	 * Render [aips_faq question="..."]...[/aips_faq].
	 */
	public static function render_faq($atts, $content = ''): string {
		$atts = shortcode_atts(array(
			'question' => '',
		), $atts, 'aips_faq');

		$q = sanitize_text_field($atts['question']);
		$a = wp_kses_post($content);

		return '<details class="aips-faq-item aips-faq-shortcode"><summary><strong>' . esc_html($q) . '</strong></summary><div class="aips-faq-answer"><p>' . $a . '</p></div></details>';
	}

	/**
	 * Render [aips_pros_cons pros="..." cons="..."].
	 */
	public static function render_pros_cons($atts): string {
		$atts = shortcode_atts(array(
			'pros' => '',
			'cons' => '',
		), $atts, 'aips_pros_cons');

		$pros = array_filter(array_map('trim', explode('|', $atts['pros'])));
		$cons = array_filter(array_map('trim', explode('|', $atts['cons'])));

		$html = '<div class="aips-pros-cons-grid aips-pros-cons-shortcode">';
		$html .= '<div class="aips-pros-column"><h4 class="aips-column-title aips-pros-title">' . esc_html__('Pros', 'ai-post-scheduler') . '</h4><ul class="aips-pros-list">';

		foreach ($pros as $p) {
			$html .= '<li>' . esc_html($p) . '</li>';
		}

		$html .= '</ul></div>';

		$html .= '<div class="aips-cons-column"><h4 class="aips-column-title aips-cons-title">' . esc_html__('Cons', 'ai-post-scheduler') . '</h4><ul class="aips-cons-list">';
		
		foreach ($cons as $c) {
			$html .= '<li>' . esc_html($c) . '</li>';
		}

		$html .= '</ul></div></div>';

		return $html;
	}

	/**
	 * Render [aips_rating value="..." max="..." verdict="..."]...[/aips_rating].
	 */
	public static function render_rating($atts, $content = ''): string {
		$atts = shortcode_atts(array(
			'value'   => '5',
			'max'     => '5',
			'verdict' => '',
		), $atts, 'aips_rating');

		$val     = esc_html($atts['value']);
		$max     = esc_html($atts['max']);
		$verdict = sanitize_text_field($atts['verdict']);
		$body    = wp_kses_post($content);

		$html = '<div class="aips-rating-box aips-rating-shortcode">';
		$html .= '<div class="aips-rating-badge"><span class="aips-rating-num">' . $val . '</span><span class="aips-rating-scale">/ ' . $max . '</span></div>';

		if (!empty($verdict)) {
			$html .= '<div class="aips-rating-verdict"><strong>' . esc_html__('Verdict:', 'ai-post-scheduler') . '</strong> ' . esc_html($verdict) . '</div>';
		}

		if (!empty($body)) {
			$html .= '<div class="aips-rating-body"><p>' . $body . '</p></div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render [aips_step_list]...[/aips_step_list].
	 */
	public static function render_step_list($atts, $content = ''): string {
		return '<div class="aips-step-list-block aips-step-shortcode"><ol class="aips-step-list">' . do_shortcode($content) . '</ol></div>';
	}

	/**
	 * Render [aips_step title="..."]...[/aips_step].
	 */
	public static function render_step($atts, $content = ''): string {
		$atts = shortcode_atts(array(
			'title' => '',
		), $atts, 'aips_step');

		$title = sanitize_text_field($atts['title']);
		$text  = wp_kses_post($content);

		$html = '<li class="aips-step-item">';

		if (!empty($title)) {
			$html .= '<h4 class="aips-step-title">' . esc_html($title) . '</h4>';
		}
		
		$html .= '<div class="aips-step-content"><p>' . $text . '</p></div></li>';

		return $html;
	}

	/**
	 * Render [aips_table]...[/aips_table].
	 */
	public static function render_table($atts, $content = ''): string {
		return '<figure class="aips-comparison-table aips-table-shortcode">' . wp_kses_post($content) . '</figure>';
	}

	/**
	 * Render [aips_callout type="..."]...[/aips_callout].
	 */
	public static function render_callout($atts, $content = ''): string {
		$atts = shortcode_atts(array(
			'type' => 'tip',
		), $atts, 'aips_callout');

		$type = sanitize_key($atts['type']);
		$body = wp_kses_post($content);

		return '<div class="aips-callout aips-callout-' . esc_attr($type) . '"><p>' . $body . '</p></div>';
	}
}
