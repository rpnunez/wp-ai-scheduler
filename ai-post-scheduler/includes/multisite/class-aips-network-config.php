<?php
/**
 * Multisite Network Configuration Manager
 *
 * Manages network-wide settings, AI policies, and quotas stored in wp_sitemeta.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Network_Config {

	const OPTION_KEY = 'aips_network_settings';

	/**
	 * @var array|null Cached network settings.
	 */
	private static $cached_settings = null;

	/**
	 * Get default network settings.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return array(
			// AI Provider & Model defaults
			'ai_provider'                 => '',
			'ai_model'                    => '',
			'ai_env_id'                   => '',
			'wp_ai_connector_mode'        => 'all',
			'wp_ai_connector_ids'         => array(),
			'wp_ai_connector_failover'    => true,
			'temperature'                 => 0.7,
			'max_tokens_limit'            => 16000,
			// Governance & Policies
			'allow_site_ai_override'      => true,
			'share_network_ai_credentials'=> true,
			'restrict_db_tools'           => true,
			'prevent_ai_generation'       => false,
			// Network Resilience & Quotas
			'daily_quota_per_site'        => 0, // 0 = unlimited
			'monthly_quota_per_site'      => 0, // 0 = unlimited
			'network_rate_limit_enabled'  => false,
			'network_rate_limit_requests' => 60,
			'network_rate_limit_period'   => 60,
			'site_quota_overrides'        => array(), // blog_id => array('daily' => int, 'monthly' => int)
		);
	}

	/**
	 * Get all network settings merged with defaults.
	 *
	 * @param bool $force_refresh
	 * @return array
	 */
	public static function get_settings(bool $force_refresh = false): array {
		if (self::$cached_settings === null || $force_refresh) {
			$stored = get_site_option(self::OPTION_KEY, array());
			if (!is_array($stored)) {
				$stored = array();
			}

			self::$cached_settings = array_merge(self::get_defaults(), $stored);
		}

		return self::$cached_settings;
	}

	/**
	 * Get a specific network option.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get_option(string $key, $default = null) {
		$settings = self::get_settings();
		if (array_key_exists($key, $settings)) {
			return $settings[$key];
		}

		$defaults = self::get_defaults();
		if (array_key_exists($key, $defaults)) {
			return $defaults[$key];
		}

		return $default;
	}

	/**
	 * Update network settings.
	 *
	 * @param array $new_settings
	 * @return bool
	 */
	public static function update_settings(array $new_settings): bool {
		$current = self::get_settings(true);
		$merged  = array_merge($current, $new_settings);

		// Sanitize values
		$clean = array(
			'ai_provider'                 => sanitize_key((string) ($merged['ai_provider'] ?? '')),
			'ai_model'                    => sanitize_text_field((string) ($merged['ai_model'] ?? '')),
			'ai_env_id'                   => sanitize_text_field((string) ($merged['ai_env_id'] ?? '')),
			'wp_ai_connector_mode'        => sanitize_key((string) ($merged['wp_ai_connector_mode'] ?? 'all')),
			'wp_ai_connector_ids'         => is_array($merged['wp_ai_connector_ids'] ?? null) ? array_map('sanitize_text_field', $merged['wp_ai_connector_ids']) : array(),
			'wp_ai_connector_failover'    => (bool) ($merged['wp_ai_connector_failover'] ?? true),
			'temperature'                 => floatval($merged['temperature'] ?? 0.7),
			'max_tokens_limit'            => absint($merged['max_tokens_limit'] ?? 16000),
			'allow_site_ai_override'      => (bool) ($merged['allow_site_ai_override'] ?? true),
			'share_network_ai_credentials'=> (bool) ($merged['share_network_ai_credentials'] ?? true),
			'restrict_db_tools'           => (bool) ($merged['restrict_db_tools'] ?? true),
			'prevent_ai_generation'       => (bool) ($merged['prevent_ai_generation'] ?? false),
			'daily_quota_per_site'        => absint($merged['daily_quota_per_site'] ?? 0),
			'monthly_quota_per_site'      => absint($merged['monthly_quota_per_site'] ?? 0),
			'network_rate_limit_enabled'  => (bool) ($merged['network_rate_limit_enabled'] ?? false),
			'network_rate_limit_requests' => absint($merged['network_rate_limit_requests'] ?? 60),
			'network_rate_limit_period'   => absint($merged['network_rate_limit_period'] ?? 60),
			'site_quota_overrides'        => is_array($merged['site_quota_overrides'] ?? null) ? $merged['site_quota_overrides'] : array(),
		);

		$result = update_site_option(self::OPTION_KEY, $clean);
		self::$cached_settings = $clean;
		return $result;
	}

	/**
	 * Check whether sub-sites are allowed to override AI configuration.
	 *
	 * @return bool
	 */
	public static function is_site_ai_override_allowed(): bool {
		if (!function_exists('is_multisite') || !is_multisite()) {
			return true;
		}

		return (bool) self::get_option('allow_site_ai_override', true);
	}

	/**
	 * Check whether network AI credentials should be shared with sub-sites.
	 *
	 * @return bool
	 */
	public static function should_share_credentials(): bool {
		if (!function_exists('is_multisite') || !is_multisite()) {
			return false;
		}

		return (bool) self::get_option('share_network_ai_credentials', true);
	}

	/**
	 * Check whether destructive DB tools are restricted to Super Admins.
	 *
	 * @return bool
	 */
	public static function is_db_tools_restricted(): bool {
		if (!function_exists('is_multisite') || !is_multisite()) {
			return false;
		}

		return (bool) self::get_option('restrict_db_tools', true);
	}

	/**
	 * Check whether the network-wide AI generation kill-switch is active.
	 *
	 * @return bool
	 */
	public static function is_network_ai_generation_prevented(): bool {
		return (bool) self::get_option('prevent_ai_generation', false);
	}

	/**
	 * Check whether a sub-site is using the shared network AI credentials.
	 *
	 * @param int|null $blog_id
	 * @return bool
	 */
	public static function is_site_using_network_credentials(?int $blog_id = null): bool {
		if (!function_exists('is_multisite') || !is_multisite()) {
			return false;
		}

		if (!self::should_share_credentials()) {
			return false;
		}

		if (!self::is_site_ai_override_allowed()) {
			return true;
		}

		$blog_id = $blog_id ?: get_current_blog_id();

		// Check if site explicitly configured its own provider/key
		$site_provider = get_blog_option($blog_id, 'aips_ai_provider', '');
		if (!empty($site_provider)) {
			// Sub-site has configured an explicit provider override
			return false;
		}

		return true;
	}

	/**
	 * Clear in-memory cache.
	 *
	 * @return void
	 */
	public static function reset_cache(): void {
		self::$cached_settings = null;
	}
}
