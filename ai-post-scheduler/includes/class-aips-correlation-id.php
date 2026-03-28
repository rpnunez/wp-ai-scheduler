<?php
/**
 * Correlation ID Manager
 *
 * Provides a lightweight, in-process correlation ID for tracing a single
 * scheduler run, generation session, or notification chain end-to-end.
 *
 * Usage:
 *   // At the start of a run:
 *   $correlation_id = AIPS_Correlation_ID::generate();
 *
 *   // Anywhere in the same PHP process:
 *   $id = AIPS_Correlation_ID::get();
 *
 *   // After the run completes, reset to avoid bleed-over:
 *   AIPS_Correlation_ID::reset();
 *
 * @package AI_Post_Scheduler
 * @since 1.7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Correlation_ID
 *
 * Static utility for generating and propagating a per-run correlation ID.
 * Because PHP runs each cron callback in a single process, a static variable
 * is sufficient to share the ID across all classes involved in one run.
 */
class AIPS_Correlation_ID {

	/**
	 * The active correlation ID for the current execution context.
	 *
	 * @var string|null
	 */
	private static $current_id = null;

	/**
	 * Generate a new correlation ID and make it the active one.
	 *
	 * Calling this at the start of a scheduler run, manual generation, or any
	 * top-level entry point establishes a fresh ID that all child operations
	 * (generation, history, notifications) can inherit via get().
	 *
	 * @return string The newly generated UUID-based correlation ID.
	 */
	public static function generate() {
		self::$current_id = self::create_uuid();
		return self::$current_id;
	}

	/**
	 * Return the currently active correlation ID, or null if none has been set.
	 *
	 * @return string|null
	 */
	public static function get() {
		return self::$current_id;
	}

	/**
	 * Explicitly set the active correlation ID.
	 *
	 * Useful when resuming or continuing a traced run from a stored ID.
	 *
	 * @param string $id A valid UUID or opaque string identifier.
	 * @return void
	 */
	public static function set($id) {
		self::$current_id = sanitize_text_field((string) $id);
	}

	/**
	 * Clear the active correlation ID.
	 *
	 * Call this after a run completes to prevent the ID from bleeding into
	 * any subsequent operations in the same process.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$current_id = null;
	}

	/**
	 * Generate a UUID v4 string.
	 *
	 * Delegates to WordPress's wp_generate_uuid4() when available, and falls
	 * back to a custom implementation otherwise.
	 *
	 * @return string UUID v4 string.
	 */
	private static function create_uuid() {
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
}
