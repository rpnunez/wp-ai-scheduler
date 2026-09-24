<?php
/**
 * Plugin Lifecycle Manager
 *
 * Handles activation, deactivation, database upgrades, default option seeding,
 * and cron event declarations.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Lifecycle {

	/**
	 * Get plugin cron definitions.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_cron_events() {
		return array(
			'aips_generate_scheduled_posts' => array(
				'schedule' => 'hourly',
				'label'    => __( 'Post Generation', 'ai-post-scheduler' ),
			),
			'aips_generate_author_topics' => array(
				'schedule' => 'hourly',
				'label'    => __( 'Author Topic Generation', 'ai-post-scheduler' ),
			),
			'aips_generate_author_posts' => array(
				'schedule' => 'hourly',
				'label'    => __( 'Author Post Generation', 'ai-post-scheduler' ),
			),
			'aips_scheduled_research' => array(
				'schedule' => 'daily',
				'label'    => __( 'Automated Research', 'ai-post-scheduler' ),
			),
			'aips_notification_rollups' => array(
				'schedule' => 'daily',
				'label'    => __( 'Notification Rollups', 'ai-post-scheduler' ),
			),
			'aips_cleanup_export_files' => array(
				'schedule' => 'daily',
				'label'    => __( 'Export Cleanup', 'ai-post-scheduler' ),
			),
			'aips_fetch_sources' => array(
				'schedule' => 'daily',
				'label'    => __( 'Sources Fetch', 'ai-post-scheduler' ),
			),
			'aips_cleanup_bulk_batch_jobs' => array(
				'schedule' => 'daily',
				'label'    => __( 'Bulk Batch Job Cleanup', 'ai-post-scheduler' ),
			),
			'aips_cache_monitor_maintenance' => array(
				'schedule' => 'daily',
				'label'    => __( 'Cache Monitor Maintenance', 'ai-post-scheduler' ),
			),
		);
	}

	/**
	 * Ensure autoloaders are registered before executing lifecycle routines.
	 *
	 * @return void
	 */
	private static function ensure_autoload() {
		if ( ! class_exists( 'AIPS_Autoloader' ) ) {
			$autoloader = AIPS_PLUGIN_DIR . 'includes/class-aips-autoloader.php';
			if ( file_exists( $autoloader ) ) {
				require_once $autoloader;
				AIPS_Autoloader::register();
			}
		}
	}

	/**
	 * Handle plugin activation tasks.
	 *
	 * Seeds default options, runs upgrades/table checks, schedules cron events,
	 * and applies onboarding redirect logic for first-time installs.
	 *
	 * @return void
	 */
	public static function activate() {
		self::ensure_autoload();

		// Ensure logger is available
		if (!class_exists('AIPS_Logger')) {
			require_once AIPS_PLUGIN_DIR . 'includes/class-aips-logger.php';
		}

		$logger = new AIPS_Logger();

		$logger->log('Running plugin activation.');

		// Detect a prior installation before set_default_options() writes defaults.
		$previously_installed = AIPS_Config::get_instance()->has_option('aips_db_version');
		$wizard_completed     = (bool) AIPS_Config::get_instance()->get_option('aips_onboarding_completed');

		self::set_default_options();

		if ($previously_installed || $wizard_completed) {
			// Plugin already had data or the wizard was already run — mark it
			// complete so the wizard never resurfaces on future reactivations.
			if (!$wizard_completed) {
				update_option('aips_onboarding_completed', 1, false);
			}
		} else {
			// Fresh install: redirect admins to the onboarding wizard once.
			set_transient('aips_onboarding_redirect', 1, MINUTE_IN_SECONDS * 10);
		}
		self::check_upgrades();

		// Ensure tables exist even if version matches (e.g. re-activation after manual deletion or partial install)
		$install_result = AIPS_DB_Manager::install_tables();

		if (is_wp_error($install_result)) {
			$logger->log('Table installation failed during activation: ' . $install_result->get_error_message(), 'error');

			$notifications = class_exists('AIPS_Notifications') ? new AIPS_Notifications() : null;
			if ($notifications instanceof AIPS_Notifications) {
				$notifications->system_error(array(
					'title'         => __('Database installation failed', 'ai-post-scheduler'),
					'error_code'    => $install_result->get_error_code(),
					'error_message' => $install_result->get_error_message(),
					'url'           => admin_url('admin.php?page=aips-status'),
					'dedupe_key'    => 'db_install_failed_activation',
					'dedupe_window' => 1800,
				));
			}
		}

		$crons = self::get_cron_events();

		foreach ($crons as $hook => $cron_config) {
			$schedule = $cron_config['schedule'];
			$logger->log("Checking cron: $hook");

			if (!wp_next_scheduled($hook)) {
				$logger->log("Cron '$hook' not scheduled. Scheduling now with schedule: '$schedule'.");

				wp_schedule_event(time(), $schedule, $hook);

				$next_run = wp_next_scheduled($hook);

				if ($next_run) {
					$logger->log("Successfully scheduled '$hook'. Next run: " . date('Y-m-d H:i:s', $next_run));
				} else {
					$logger->log("Failed to schedule '$hook'. wp_next_scheduled returned false.", 'error');
				}
			} else {
				$logger->log("Cron '$hook' is already scheduled.");
			}
		}

		flush_rewrite_rules();

		$logger->log('Plugin activation finished.');
	}

	/**
	 * Run versioned upgrade checks.
	 *
	 * Called both on plugin activation (via activate()) and on every page load
	 * via the plugins_loaded hook. WordPress plugin auto-updates skip activation,
	 * so this must be self-contained: AIPS_DB_Migrations::check_and_run() runs
	 * migrations AND install_tables() (dbDelta) when an upgrade is detected,
	 * ensuring the schema is fully applied regardless of the call path.
	 *
	 * Call-order guarantee inside AIPS_DB_Migrations::run_upgrade() (must not change):
	 *   1. Versioned migrations run first — column renames/type changes/index ops
	 *      that dbDelta cannot perform. Schema must be consistent before dbDelta.
	 *   2. AIPS_DB_Manager::install_tables() (dbDelta) runs second — adds any new
	 *      tables/columns introduced in the current plugin version.
	 *
	 * Note: activate() also calls install_tables() directly afterward for the
	 * fresh-install / re-activation edge case. The double invocation is harmless
	 * because dbDelta is fully idempotent.
	 *
	 * @return void
	 */
	public static function check_upgrades() {
		self::ensure_autoload();
		AIPS_DB_Migrations::check_and_run();
	}

	/**
	 * Handle plugin deactivation cleanup.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::ensure_autoload();
		foreach (array_keys(self::get_cron_events()) as $hook) {
			wp_clear_scheduled_hook($hook);
		}
		flush_rewrite_rules();
	}

	/**
	 * Seed plugin options with default values when missing.
	 *
	 * Uses AIPS_Config defaults as the source of truth and adds activation-
	 * specific values where required.
	 *
	 * @return void
	 */
	public static function set_default_options() {
		self::ensure_autoload();
		$defaults = AIPS_Config::get_instance()->get_default_options();

		// Activation-specific fallback: if unset in defaults, use admin email.
		if (empty($defaults['aips_review_notifications_email'])) {
			$defaults['aips_review_notifications_email'] = get_option('admin_email');
		}

		// Keep DB version initialization during activation.
		$defaults['aips_db_version'] = AIPS_VERSION;

		foreach ($defaults as $key => $value) {
			if (!AIPS_Config::get_instance()->has_option($key)) {
				add_option($key, $value);
			}
		}
	}
}
