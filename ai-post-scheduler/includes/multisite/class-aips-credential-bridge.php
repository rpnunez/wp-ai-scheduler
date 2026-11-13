<?php
/**
 * Multisite AI Credential Bridge
 *
 * Bridges AI engine credentials and provider configurations from the main network site
 * to sub-sites when credential sharing is enabled by the Super Admin.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Credential_Bridge {

	/**
	 * @var bool Track whether hooks have been registered.
	 */
	private static $registered = false;

	/**
	 * Register credential bridging filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		if (self::$registered || !function_exists('is_multisite') || !is_multisite()) {
			return;
		}

		self::$registered = true;

		// 1. Meow Apps AI Engine bridge
		add_filter('default_option_mwai_options', array(__CLASS__, 'filter_default_mwai_options'), 10, 3);
		add_filter('pre_option_mwai_options', array(__CLASS__, 'filter_pre_mwai_options'), 10, 3);

		// 2. Generic connector credentials bridge
		add_action('init', array(__CLASS__, 'register_connector_bridges'), 20);
	}

	/**
	 * Bridge default mwai_options from the main site if unset on current site.
	 *
	 * @param mixed  $default
	 * @param string $option
	 * @param mixed  $passed_default
	 * @return mixed
	 */
	public static function filter_default_mwai_options($default, $option = '', $passed_default = false) {
		if (get_current_blog_id() === (int) get_main_site_id()) {
			return $default;
		}

		if (!AIPS_Network_Config::should_share_credentials()) {
			return $default;
		}

		$main_site_options = get_blog_option(get_main_site_id(), 'mwai_options', null);
		if (!empty($main_site_options)) {
			return $main_site_options;
		}

		return $default;
	}

	/**
	 * Force mwai_options from main site if site overrides are locked.
	 *
	 * @param mixed  $pre
	 * @param string $option
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function filter_pre_mwai_options($pre, $option = '', $default = false) {
		if (get_current_blog_id() === (int) get_main_site_id()) {
			return $pre;
		}

		if (!AIPS_Network_Config::should_share_credentials()) {
			return $pre;
		}

		if (!AIPS_Network_Config::is_site_ai_override_allowed()) {
			$main_site_options = get_blog_option(get_main_site_id(), 'mwai_options', null);
			if (!empty($main_site_options)) {
				return $main_site_options;
			}
		}

		return $pre;
	}

	/**
	 * Register option filters for known WordPress AI Client connectors.
	 *
	 * @return void
	 */
	public static function register_connector_bridges(): void {
		if (get_current_blog_id() === (int) get_main_site_id()) {
			return;
		}

		if (!AIPS_Network_Config::should_share_credentials()) {
			return;
		}

		if (!function_exists('wp_get_connectors')) {
			return;
		}

		$connectors = wp_get_connectors();
		if (!is_array($connectors)) {
			return;
		}

		foreach ($connectors as $connector) {
			if (!is_array($connector) || ($connector['type'] ?? '') !== 'ai_provider') {
				continue;
			}

			$auth = $connector['authentication'] ?? array();
			$setting_name = $auth['setting_name'] ?? '';

			if (!empty($setting_name)) {
				add_filter("default_option_{$setting_name}", function($default, $opt = '', $passed = false) use ($setting_name) {
					if (get_current_blog_id() === (int) get_main_site_id()) {
						return $default;
					}
					if (!AIPS_Network_Config::should_share_credentials()) {
						return $default;
					}
					$main_val = get_blog_option(get_main_site_id(), $setting_name, null);
					return ($main_val !== null && $main_val !== '') ? $main_val : $default;
				}, 10, 3);

				add_filter("pre_option_{$setting_name}", function($pre, $opt = '', $def = false) use ($setting_name) {
					if (get_current_blog_id() === (int) get_main_site_id()) {
						return $pre;
					}
					if (!AIPS_Network_Config::should_share_credentials() || AIPS_Network_Config::is_site_ai_override_allowed()) {
						return $pre;
					}
					$main_val = get_blog_option(get_main_site_id(), $setting_name, null);
					return ($main_val !== null && $main_val !== '') ? $main_val : $pre;
				}, 10, 3);
			}
		}
	}
}
