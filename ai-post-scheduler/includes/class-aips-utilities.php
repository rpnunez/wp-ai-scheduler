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
}
