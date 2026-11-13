<?php
/**
 * Multisite Lifecycle Manager
 *
 * Handles network activation, deactivation, eager/lazy site provisioning,
 * new site initialization, and cleanup upon site deletion.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Multisite_Lifecycle {

	/**
	 * Maximum number of sub-sites to provision synchronously during Network Activation.
	 */
	const EAGER_PROVISION_LIMIT = 50;

	/**
	 * Handle plugin activation on single-site or network-wide.
	 *
	 * @param bool $network_wide
	 * @return void
	 */
	public static function activate(bool $network_wide = false): void {
		if (!$network_wide || !function_exists('is_multisite') || !is_multisite()) {
			AI_Post_Scheduler::get_instance()->activate_single_site(true);
			return;
		}

		// Network-wide activation:
		// 1. Seed Network configuration defaults if not already present.
		AIPS_Network_Config::get_settings();

		// 2. Fetch active sites (excluding archived, spam, and deleted).
		$sites = get_sites(array(
			'fields'   => 'ids',
			'number'   => self::EAGER_PROVISION_LIMIT + 1,
			'archived' => 0,
			'spam'     => 0,
			'deleted'  => 0,
		));

		if (count($sites) <= self::EAGER_PROVISION_LIMIT) {
			// Eager provisioning for networks up to 50 sites
			foreach ($sites as $site_id) {
				switch_to_blog((int) $site_id);
				AI_Post_Scheduler::get_instance()->activate_single_site(false);
				restore_current_blog();
			}
		} else {
			// Large network (> 50 sites): Provision the main site eagerly;
			// other sub-sites will provision lazily on their first admin/cron load.
			$main_id = (int) get_main_site_id();
			switch_to_blog($main_id);
			AI_Post_Scheduler::get_instance()->activate_single_site(true);
			restore_current_blog();
		}
	}

	/**
	 * Handle plugin deactivation on single-site or network-wide.
	 *
	 * @param bool $network_wide
	 * @return void
	 */
	public static function deactivate(bool $network_wide = false): void {
		if (!$network_wide || !function_exists('is_multisite') || !is_multisite()) {
			AI_Post_Scheduler::get_instance()->deactivate_single_site();
			return;
		}

		$sites = get_sites(array(
			'fields'   => 'ids',
			'number'   => 0,
		));

		foreach ($sites as $site_id) {
			switch_to_blog((int) $site_id);
			AI_Post_Scheduler::get_instance()->deactivate_single_site();
			restore_current_blog();
		}
	}

	/**
	 * Hooked to wp_initialize_site (WP 5.1+) when a new sub-site is created.
	 *
	 * @param WP_Site $site
	 * @param array   $args
	 * @return void
	 */
	public static function on_initialize_site($site, array $args = array()): void {
		if (!function_exists('is_plugin_active_for_network')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if (!is_plugin_active_for_network(AIPS_PLUGIN_BASENAME)) {
			return;
		}

		$site_id = (int) (is_object($site) ? $site->id : $site);
		if ($site_id <= 0) {
			return;
		}

		switch_to_blog($site_id);
		AI_Post_Scheduler::get_instance()->activate_single_site(false);
		restore_current_blog();
	}

	/**
	 * Hooked to wpmu_drop_tables filter when a sub-site is deleted.
	 *
	 * Appends all 35 custom AIPS tables for this blog prefix so WordPress drops them cleanly.
	 *
	 * @param array $tables
	 * @param int   $blog_id
	 * @return array
	 */
	public static function filter_wpmu_drop_tables(array $tables, int $blog_id): array {
		global $wpdb;
		$prefix = (function_exists('is_multisite') && is_multisite())
			? $wpdb->get_blog_prefix($blog_id)
			: "{$wpdb->prefix}{$blog_id}_";

		foreach (AIPS_DB_Manager::get_table_names() as $table) {
			$tables[] = $prefix . $table;
		}

		return array_unique($tables);
	}

	/**
	 * Hooked to wp_uninitialize_site / delete_blog to clean up site log & export folders.
	 *
	 * @param WP_Site|int $site
	 * @return void
	 */
	public static function on_uninitialize_site($site): void {
		$site_id = (int) (is_object($site) ? $site->id : $site);
		if ($site_id <= 0) {
			return;
		}

		switch_to_blog($site_id);
		$upload_dir = wp_upload_dir();
		restore_current_blog();

		if (empty($upload_dir['basedir'])) {
			return;
		}

		$dirs_to_clean = array(
			$upload_dir['basedir'] . '/aips-logs',
			$upload_dir['basedir'] . '/aips-exports',
		);

		foreach ($dirs_to_clean as $dir) {
			if (is_dir($dir)) {
				self::recursive_rmdir($dir);
			}
		}
	}

	/**
	 * Helper to recursively remove a directory and its contents.
	 *
	 * @param string $dir
	 * @return void
	 */
	private static function recursive_rmdir(string $dir): void {
		$items = scandir($dir);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if (is_dir($path)) {
				self::recursive_rmdir($path);
			} else {
				@unlink($path);
			}
		}

		@rmdir($dir);
	}
}
