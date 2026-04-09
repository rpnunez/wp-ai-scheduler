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
		$options = AIPS_Settings::get_content_strategy_options();

		foreach ($options as $option_name => $meta) {
			if (!isset($meta['key'])) {
				continue;
			}
			$result[ $meta['key'] ] = $config->get_option($option_name);
		}

		return $result;
	}

	/**
	 * Return a single site-wide setting value.
	 *
	 * @param string $key     Short key as defined in the settings registry (e.g. 'niche').
	 * @param mixed  $default Default value if the setting has not been configured.
	 * @return mixed
	 */
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
		$options = AIPS_Settings::get_content_strategy_options();
		$config  = AIPS_Config::get_instance();

		foreach ($options as $option_name => $meta) {
			if ($meta['key'] === $key) {
				return $config->get_option($option_name, $default);
			}
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
