<?php
/**
 * Multisite & Single-Site Capabilities Helper
 *
 * Centralizes permission and capability checks across single-site and multisite.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Capabilities {

	/**
	 * Check if current user can manage site-level AIPS features.
	 *
	 * @return bool
	 */
	public static function can_manage_plugin(): bool {
		return current_user_can('manage_options');
	}

	/**
	 * Check if current user can manage network-level AIPS settings.
	 *
	 * @return bool
	 */
	public static function can_manage_network(): bool {
		return is_multisite() && current_user_can('manage_network_options');
	}

	/**
	 * Check if current user can run destructive database operations (wipe, reinstall).
	 *
	 * On multisite, if Super Admin locked DB tools, requires manage_network_options.
	 *
	 * @return bool
	 */
	public static function can_manage_destructive_tools(): bool {
		if (is_multisite() && AIPS_Network_Config::is_db_tools_restricted()) {
			return current_user_can('manage_network_options');
		}

		return current_user_can('manage_options');
	}

	/**
	 * Check if current user can configure or override AI provider and API settings.
	 *
	 * On multisite, if Super Admin locked AI overrides, requires manage_network_options.
	 *
	 * @return bool
	 */
	public static function can_override_ai_settings(): bool {
		if (is_multisite()) {
			if (current_user_can('manage_network_options')) {
				return true;
			}

			if (!AIPS_Network_Config::is_site_ai_override_allowed()) {
				return false;
			}
		}

		return current_user_can('manage_options');
	}
}
