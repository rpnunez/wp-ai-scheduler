<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Twig_WP_Extension extends \Twig\Extension\AbstractExtension {

	/**
	 * @return array
	 */
	public function getFunctions() {
		return array(
			new \Twig\TwigFunction('t', array($this, 'translate')),
			new \Twig\TwigFunction('tn', array($this, 'translate_plural')),
			new \Twig\TwigFunction('esc_url', 'esc_url', array('is_safe' => array('html'))),
			new \Twig\TwigFunction('nonce_field', array($this, 'nonce_field'), array('is_safe' => array('html'))),
			new \Twig\TwigFunction('admin_url', 'admin_url', array('is_safe' => array('html'))),
			new \Twig\TwigFunction('add_query_arg', 'add_query_arg', array('is_safe' => array('html'))),
			new \Twig\TwigFunction('remove_query_arg', 'remove_query_arg', array('is_safe' => array('html'))),
			new \Twig\TwigFunction('aips_page_url', array($this, 'aips_page_url'), array('is_safe' => array('html'))),
			new \Twig\TwigFunction('dashicon', array($this, 'dashicon'), array('is_safe' => array('html'))),
			new \Twig\TwigFunction('selected', array($this, 'selected'), array('is_safe' => array('html'))),
			new \Twig\TwigFunction('number_fmt', 'number_format_i18n')
		);
	}

	/**
	 * @return array
	 */
	public function getFilters() {
		return array(
			new \Twig\TwigFilter('absint', 'absint'),
			new \Twig\TwigFilter('sanitize_text', 'sanitize_text_field'),
			new \Twig\TwigFilter('esc_attr', 'esc_attr')
		);
	}

	/**
	 * @param string $text
	 * @return string
	 */
	public function translate($text) {
		return __($text, 'ai-post-scheduler');
	}

	/**
	 * @param string $single
	 * @param string $plural
	 * @param int    $count
	 * @return string
	 */
	public function translate_plural($single, $plural, $count) {
		return _n($single, $plural, (int) $count, 'ai-post-scheduler');
	}

	/**
	 * @param string $action
	 * @param string $name
	 * @return string
	 */
	public function nonce_field($action, $name = '_wpnonce') {
		return wp_nonce_field($action, $name, true, false);
	}

	/**
	 * @param string $page
	 * @param array  $args
	 * @return string
	 */
	public function aips_page_url($page, $args = array()) {
		return AIPS_Admin_Menu_Helper::get_page_url($page, $args);
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function dashicon($name) {
		$icon = sanitize_html_class($name);
		return '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span>';
	}

	/**
	 * @param mixed $value
	 * @param mixed $current
	 * @return string
	 */
	public function selected($value, $current) {
		return selected($value, $current, false);
	}
}
