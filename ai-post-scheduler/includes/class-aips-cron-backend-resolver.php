<?php
/**
 * AIPS Cron Backend Resolver — selects the active scheduling backend adapter.
 *
 * Picks ActionScheduler when available (e.g. WooCommerce installed),
 * falling back to WP-Cron via AIPS_Cron_Manager.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Cron_Backend_Resolver {

	/**
	 * Cached backend instance.
	 *
	 * @var AIPS_Cron_Backend_Interface|null
	 */
	private static $backend = null;

	/**
	 * Check whether ActionScheduler is available.
	 *
	 * @return bool True if ActionScheduler functions exist.
	 */
	public static function is_action_scheduler_available() {
		return function_exists( 'as_schedule_single_action' )
			&& function_exists( 'as_has_scheduled_action' )
			&& function_exists( 'as_unschedule_all_actions' );
	}

	/**
	 * Resolve the active backend adapter.
	 *
	 * @param AIPS_Cron_Manager|null $cron_manager Optional Cron Manager for WP-Cron adapter.
	 * @param bool                   $force_fresh  Whether to bypass cached instance (useful for tests).
	 * @return AIPS_Cron_Backend_Interface
	 */
	public static function resolve( AIPS_Cron_Manager $cron_manager = null, $force_fresh = false ) {
		if ( ! $force_fresh && null !== self::$backend ) {
			return self::$backend;
		}

		if ( self::is_action_scheduler_available() ) {
			$backend = new AIPS_Cron_Backend_ActionScheduler();
		} else {
			$backend = new AIPS_Cron_Backend_WP_Cron( $cron_manager );
		}

		if ( ! $force_fresh ) {
			self::$backend = $backend;
		}

		return $backend;
	}

	/**
	 * Reset cached backend instance (for testing).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$backend = null;
	}
}
