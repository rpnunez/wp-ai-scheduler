<?php
/**
 * Multisite Site Quota & Kill-Switch Service
 *
 * Tracks per-site AI call counts against daily and monthly quotas configured by the Network Admin.
 * Also enforces the network-wide AI generation kill-switch.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Site_Quota_Service {

	const OPTION_USAGE = 'aips_site_quota_usage';

	/**
	 * Check if current site is allowed to make an AI call.
	 *
	 * @param int|null $blog_id
	 * @param bool     $force_check Whether to force quota checking (e.g. in tests).
	 * @return true|WP_Error
	 */
	public static function check_quota(?int $blog_id = null, bool $force_check = false) {
		// 1. Network Kill-switch check
		if (AIPS_Network_Config::is_network_ai_generation_prevented()) {
			return new WP_Error(
				'network_ai_prevented',
				__('AI generation is paused network-wide by the Network Administrator.', 'ai-post-scheduler')
			);
		}

		if (!$force_check && (!function_exists('is_multisite') || !is_multisite())) {
			return true;
		}

		$blog_id = $blog_id ?: (function_exists('get_current_blog_id') ? get_current_blog_id() : 1);

		// If this site has its own local API key, network quotas do not apply
		if (!$force_check && !AIPS_Network_Config::is_site_using_network_credentials($blog_id)) {
			return true;
		}

		$limits = self::get_site_limits($blog_id);
		$usage  = self::get_usage($blog_id);

		// Daily limit check
		if ($limits['daily'] > 0 && $usage['daily_calls'] >= $limits['daily']) {
			return new WP_Error(
				'site_daily_quota_exceeded',
				sprintf(
					/* translators: 1: current calls, 2: limit */
					__('This site has reached its daily AI generation quota (%1$d/%2$d calls). Contact the Network Administrator.', 'ai-post-scheduler'),
					$usage['daily_calls'],
					$limits['daily']
				)
			);
		}

		// Monthly limit check
		if ($limits['monthly'] > 0 && $usage['monthly_calls'] >= $limits['monthly']) {
			return new WP_Error(
				'site_monthly_quota_exceeded',
				sprintf(
					/* translators: 1: current calls, 2: limit */
					__('This site has reached its monthly AI generation quota (%1$d/%2$d calls). Contact the Network Administrator.', 'ai-post-scheduler'),
					$usage['monthly_calls'],
					$limits['monthly']
				)
			);
		}

		return true;
	}

	/**
	 * Record a successful AI call against the site's quota.
	 *
	 * @param int|null $blog_id
	 * @return void
	 */
	public static function record_call(?int $blog_id = null): void {
		$blog_id = $blog_id ?: (function_exists('get_current_blog_id') ? get_current_blog_id() : 1);
		$today   = AIPS_DateTime::now()->toDisplay('Y-m-d');
		$month   = AIPS_DateTime::now()->toDisplay('Y-m');

		$usage = function_exists('get_blog_option')
			? get_blog_option($blog_id, self::OPTION_USAGE, array())
			: get_option(self::OPTION_USAGE, array());
		if (!is_array($usage)) {
			$usage = array();
		}

		$daily_calls   = (isset($usage['day']) && $usage['day'] === $today) ? (int) ($usage['daily_calls'] ?? 0) : 0;
		$monthly_calls = (isset($usage['month']) && $usage['month'] === $month) ? (int) ($usage['monthly_calls'] ?? 0) : 0;

		$usage = array(
			'day'           => $today,
			'daily_calls'   => $daily_calls + 1,
			'month'         => $month,
			'monthly_calls' => $monthly_calls + 1,
			'last_call_at'  => AIPS_DateTime::now()->timestamp(),
		);

		if (function_exists('update_blog_option')) {
			update_blog_option($blog_id, self::OPTION_USAGE, $usage);
		} else {
			update_option(self::OPTION_USAGE, $usage);
		}
	}

	/**
	 * Get the daily and monthly limits configured for a site.
	 *
	 * @param int $blog_id
	 * @return array{daily: int, monthly: int}
	 */
	public static function get_site_limits(int $blog_id): array {
		$default_daily   = absint(AIPS_Network_Config::get_option('daily_quota_per_site', 0));
		$default_monthly = absint(AIPS_Network_Config::get_option('monthly_quota_per_site', 0));

		$overrides = AIPS_Network_Config::get_option('site_quota_overrides', array());
		if (is_array($overrides) && isset($overrides[$blog_id])) {
			$site_override = $overrides[$blog_id];
			if (isset($site_override['daily']) && $site_override['daily'] !== '') {
				$default_daily = absint($site_override['daily']);
			}
			if (isset($site_override['monthly']) && $site_override['monthly'] !== '') {
				$default_monthly = absint($site_override['monthly']);
			}
		}

		return array(
			'daily'   => $default_daily,
			'monthly' => $default_monthly,
		);
	}

	/**
	 * Get the current usage statistics for a site.
	 *
	 * @param int $blog_id
	 * @return array{daily_calls: int, monthly_calls: int, last_call_at: int}
	 */
	public static function get_usage(int $blog_id): array {
		$today = AIPS_DateTime::now()->toDisplay('Y-m-d');
		$month = AIPS_DateTime::now()->toDisplay('Y-m');

		$usage = function_exists('get_blog_option')
			? get_blog_option($blog_id, self::OPTION_USAGE, array())
			: get_option(self::OPTION_USAGE, array());
		if (!is_array($usage)) {
			$usage = array();
		}

		$daily_calls   = (isset($usage['day']) && $usage['day'] === $today) ? (int) ($usage['daily_calls'] ?? 0) : 0;
		$monthly_calls = (isset($usage['month']) && $usage['month'] === $month) ? (int) ($usage['monthly_calls'] ?? 0) : 0;
		$last_call_at  = (int) ($usage['last_call_at'] ?? 0);

		return array(
			'daily_calls'   => $daily_calls,
			'monthly_calls' => $monthly_calls,
			'last_call_at'  => $last_call_at,
		);
	}

	/**
	 * Reset a site's quota usage.
	 *
	 * @param int $blog_id
	 * @return void
	 */
	public static function reset_usage(int $blog_id): void {
		if (function_exists('delete_blog_option')) {
			delete_blog_option($blog_id, self::OPTION_USAGE);
		} else {
			delete_option(self::OPTION_USAGE);
		}
	}
}
