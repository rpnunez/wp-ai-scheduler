<?php
/**
 * Site Context Service
 *
 * Provides a centralised access layer for site-wide content strategy settings.
 * The authoritative list of option names and their defaults is owned by
 * AIPS_Settings::get_content_strategy_options(), so adding a new site-wide
 * option only requires a change in that one method.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Site_Context
 *
 * Static utility that reads site-wide content strategy options.
 * The option registry lives in AIPS_Settings::get_content_strategy_options()
 * to avoid maintaining a duplicate list here.
 */
class AIPS_Site_Context {

	/**
	 * Map of short keys (e.g. 'niche') to WordPress option names (e.g. 'aips_site_niche').
	 *
	 * @var array<string, string>|null
	 */
	private static $key_map = null;

	/**
	 * Return all site-wide content settings as an associative array.
	 *
	 * Keys are the short 'key' values defined in the settings registry
	 * (e.g. 'niche', 'target_audience') — not the full option names.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$result  = array();
		$config  = AIPS_Config::get_instance();
		$key_map = self::get_key_map();

		foreach ($key_map as $key => $option_name) {
			$result[ $key ] = $config->get_option($option_name);
		}

		return $result;
	}

	/**
	 * Get the map of short setting keys to full WordPress option names.
	 *
	 * Caches the inverted mapping from AIPS_Settings::get_content_strategy_options()
	 * for O(1) lookups.
	 *
	 * @return array<string, string>
	 */
	public static function get_key_map() {
		if (self::$key_map === null) {
			$options = AIPS_Settings::get_content_strategy_options();
			self::$key_map = array();
			foreach ($options as $option_name => $meta) {
				if (isset($meta['key'])) {
					self::$key_map[ $meta['key'] ] = $option_name;
				}
			}
		}

		return self::$key_map;
	}

	/**
	 * Reset the internal key map cache.
	 *
	 * Useful for testing when settings definitions may be altered.
	 *
	 * @return void
	 */
	public static function reset_key_map() {
		self::$key_map = null;
	}

	/**
	 * Return a single site-wide setting value.
	 *
	 * @param string     $key     Short key as defined in the settings registry (e.g. 'niche').
	 * @param mixed|null $default Optional. Explicit fallback value when the option is not set.
	 *                            When omitted (null) the AIPS_Config registered default is used,
	 *                            which is an empty string for all site content strategy keys.
	 *                            Passing null is therefore equivalent to omitting the argument.
	 * @return mixed Stored option value, the caller's $default, or '' when $default is null.
	 */
	public static function get_setting($key, $default = null) {
		if (!is_string($key)) {
			return $default !== null ? $default : '';
		}

		$key_map = self::get_key_map();

		if (isset($key_map[$key])) {
			return AIPS_Config::get_instance()->get_option($key_map[$key], $default);
		}

		return $default !== null ? $default : '';
	}

	/**
	 * Check whether the site context has been configured (at minimum the niche).
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return !empty(self::get_setting('niche'));
	}
}
