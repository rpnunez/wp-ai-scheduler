<?php
/**
 * Multisite Coordinator
 *
 * Coordinates blog-switching safety, lifecycle hooks, inactive-site cron guards,
 * and integration bridges across WordPress Multisite.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Multisite {

	/**
	 * @var bool Track whether coordinator has booted.
	 */
	private static $initialized = false;

	/**
	 * Boot the Multisite subsystem.
	 *
	 * @return void
	 */
	public static function init(): void {
		if (self::$initialized || !function_exists('is_multisite') || !is_multisite()) {
			return;
		}

		self::$initialized = true;

		// 1. Blog switch safety
		add_action('switch_blog', array(__CLASS__, 'on_switch_blog'), 10, 2);

		// 2. Lifecycle hooks
		add_action('wp_initialize_site', array('AIPS_Multisite_Lifecycle', 'on_initialize_site'), 10, 2);
		add_filter('wpmu_drop_tables', array('AIPS_Multisite_Lifecycle', 'filter_wpmu_drop_tables'), 10, 2);
		add_action('wp_uninitialize_site', array('AIPS_Multisite_Lifecycle', 'on_uninitialize_site'), 10, 1);

		// 3. Credential Bridge
		AIPS_Credential_Bridge::register();

		// 4. Network Admin UI
		if (is_multisite()) {
			new AIPS_Network_Admin_Menu();
		}
	}

	/**
	 * Hooked to switch_blog when WordPress switches execution context.
	 *
	 * Flushes blog-specific in-memory caches, rebinds loggers, and resets container
	 * singletons to guarantee complete isolation.
	 *
	 * @param int $new_blog_id
	 * @param int $prev_blog_id
	 * @return void
	 */
	public static function on_switch_blog($new_blog_id, $prev_blog_id): void {
		if ((int) $new_blog_id === (int) $prev_blog_id) {
			return;
		}

		// 1. Flush per-request option cache and registered defaults
		AIPS_Config::get_instance()->flush_option_cache();

		// 2. Reset in-memory Cache Factory singleton & named instances
		AIPS_Cache_Factory::reset();

		// 3. Rebind logger directory & secret to the new site's upload path
		AIPS_Logger::instance()->rebind();

		// 4. Notify container to clear blog-scoped singletons
		AIPS_Container::get_instance()->reset_blog_singletons();

		/**
		 * Action fired after AIPS has re-synchronized its runtime for a switched blog.
		 *
		 * @param int $new_blog_id
		 * @param int $prev_blog_id
		 */
		do_action('aips_switch_blog', (int) $new_blog_id, (int) $prev_blog_id);
	}

	/**
	 * Check whether the current site is active and eligible to run AI crons.
	 *
	 * Blocks execution if the sub-site is archived, marked as spam, or deleted.
	 *
	 * @return bool
	 */
	public static function is_current_site_eligible_for_cron(): bool {
		if (!function_exists('is_multisite') || !is_multisite()) {
			return true;
		}

		if (function_exists('is_archived') && is_archived()) {
			return false;
		}

		if (function_exists('is_spam') && is_spam()) {
			return false;
		}

		$current_blog = get_site();
		if ($current_blog && (int) $current_blog->deleted === 1) {
			return false;
		}

		return true;
	}

	/**
	 * Calculate a deterministic jitter offset (in seconds) for cron scheduling.
	 *
	 * Prevents thundering herds across dozens or hundreds of sub-sites.
	 *
	 * @param int|null $blog_id
	 * @return int Offset between 0 and 1799 seconds (0 - 29.9 minutes).
	 */
	public static function get_cron_jitter(?int $blog_id = null): int {
		if ($blog_id === null && (!function_exists('is_multisite') || !is_multisite())) {
			return 0;
		}

		$id = $blog_id !== null ? $blog_id : (int) get_current_blog_id();
		return ($id * 37) % 1800;
	}
}
