<?php
/**
 * General Utilities
 *
 * Provides shared, stateless helper methods used across multiple plugin classes.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Utilities
 *
 * Static utility helpers. Methods may optionally leverage WordPress functions when
 * available but always provide standalone fallbacks, so they can be called at any
 * point during the plugin lifecycle.
 */
class AIPS_Utilities {

	/**
	 * Generate a UUID v4 string.
	 *
	 * Delegates to WordPress's wp_generate_uuid4() when available, and falls
	 * back to a PHP 7+ cryptographically-secure implementation otherwise.
	 * The plugin targets PHP 8.2+, so random_int() is always available in the
	 * fallback path.
	 *
	 * @return string UUID v4 string in the form xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx.
	 */
	public static function generate_uuid() {
		if (function_exists('wp_generate_uuid4')) {
			return wp_generate_uuid4();
		}

		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			random_int(0, 0xffff), random_int(0, 0xffff),
			random_int(0, 0xffff),
			random_int(0, 0x0fff) | 0x4000,
			random_int(0, 0x3fff) | 0x8000,
			random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
		);
	}

	/**
	 * Safely sanitizes an array of strings, preventing fatal TypeErrors in PHP 8+
	 * when non-scalar values (like nested arrays) are passed.
	 *
	 * @param array $input The raw array to sanitize.
	 * @return array The sanitized array of strings.
	 */
	public static function sanitize_string_array($input) {
		if (!is_array($input)) {
			return array();
		}

		$sanitized = array();
		foreach ($input as $key => $item) {
			if (is_scalar($item)) {
				$sanitized[$key] = sanitize_text_field((string) $item);
			}
		}

		return $sanitized;
	}

	/**
	 * List public, non-attachment post types selectable in admin UIs (native
	 * WordPress types plus any custom post type registered by a plugin, e.g.
	 * ACF-based CPTs), with taxonomy-support flags for the built-in
	 * category/tag taxonomies.
	 *
	 * @return array<string, array{label: string, supports_category: bool, supports_post_tag: bool}>
	 */
	public static function get_selectable_post_types() {
		$post_types = get_post_types(array('public' => true), 'objects');
		unset($post_types['attachment']);

		$result = array();
		foreach ($post_types as $post_type => $post_type_obj) {
			$result[$post_type] = array(
				'label'              => $post_type_obj->labels->singular_name,
				'supports_category'  => post_type_supports($post_type, 'category'),
				'supports_post_tag'  => post_type_supports($post_type, 'post_tag'),
			);
		}

		return $result;
	}

	/**
	 * Clean HTML tags and decode entities from text intended for AI prompt context.
	 *
	 * Converts block-level closing and line-break HTML tags into newlines to ensure text
	 * across structural boundaries does not merge together, strips all remaining HTML
	 * tags, decodes HTML entities, and normalizes blank lines.
	 *
	 * @param string $content Raw HTML or text content.
	 * @return string Cleaned plain-text string suitable for prompt inclusion.
	 */
	public static function clean_html_for_prompt( $content ) {
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return '';
		}

		// Convert block-level closing tags and break tags into newlines.
		$pattern     = '/<\/(p|div|h[1-6]|li|tr|blockquote|section|article)>\s*|<(br|hr)\s*\/?>/i';
		$with_spaces = preg_replace( $pattern, "\n", $content );

		// Strip remaining HTML tags (using wp_strip_all_tags when available, strip_tags fallback).
		$stripped = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $with_spaces ) : strip_tags( $with_spaces );

		// Decode HTML entities (e.g. &amp; -> &, &quot; -> ", &#039; -> ').
		$decoded = html_entity_decode( $stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Normalize whitespace on each line, then normalize multiple blank lines to clean double newlines.
		$lines         = explode( "\n", $decoded );
		$cleaned_lines = array();
		foreach ( $lines as $line ) {
			$cleaned_lines[] = trim( preg_replace( '/[ \t]+/', ' ', $line ) );
		}

		$result = implode( "\n", $cleaned_lines );
		$result = preg_replace( "/\n{3,}/", "\n\n", $result );

		return trim( $result );
	}

	/**
	 * Whether a post type is a valid, selectable target (public, registered,
	 * not the internal 'attachment' type).
	 *
	 * @param string $post_type Post type key.
	 * @return bool
	 */
	public static function is_selectable_post_type($post_type) {
		$post_type_obj = get_post_type_object($post_type);
		return $post_type_obj && !empty($post_type_obj->public) && 'attachment' !== $post_type;
	}
}
