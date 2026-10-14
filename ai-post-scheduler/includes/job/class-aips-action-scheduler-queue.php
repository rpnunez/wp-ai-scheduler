<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Action_Scheduler_Queue
 *
 * Provides a clean adapter for Action Scheduler when available.
 * Action Scheduler offloads background jobs from standard WP-Cron
 * into dedicated, concurrent queue tables with automatic retries and logging.
 *
 * @package AI_Post_Scheduler
 * @since   3.6.7
 */
class AIPS_Action_Scheduler_Queue {

	/**
	 * Default Action Scheduler queue group for AIPS.
	 */
	const GROUP = 'aips_generation';

	/**
	 * Check if Action Scheduler is available in the current WordPress environment.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'as_schedule_single_action' )
			&& function_exists( 'as_has_scheduled_action' );
	}

	/**
	 * Schedule a single action if not already queued.
	 *
	 * @param int    $timestamp Unix timestamp when the action should run.
	 * @param string $hook      Action hook name.
	 * @param array  $args      Arguments to pass to the hook.
	 * @param string $group     Action Scheduler group name.
	 * @return int|false Action ID (or timestamp) on success, false if already scheduled or failed.
	 */
	public static function schedule_single_action( int $timestamp, string $hook, array $args = array(), string $group = self::GROUP ) {
		if ( self::has_scheduled_action( $hook, $args, $group ) ) {
			return false;
		}

		if ( self::is_available() ) {
			try {
				return as_schedule_single_action( $timestamp, $hook, $args, $group );
			} catch ( \Throwable $e ) {
				return false;
			}
		}

		// Fallback to WP-Cron when Action Scheduler is not installed.
		$result = wp_schedule_single_event( $timestamp, $hook, $args );
		return false !== $result ? $timestamp : false;
	}

	/**
	 * Check if an action is currently pending or running.
	 *
	 * @param string $hook  Hook name.
	 * @param array  $args  Hook arguments.
	 * @param string $group Group name.
	 * @return bool
	 */
	public static function has_scheduled_action( string $hook, array $args = array(), string $group = self::GROUP ): bool {
		if ( self::is_available() ) {
			try {
				return (bool) as_has_scheduled_action( $hook, $args, $group );
			} catch ( \Throwable $e ) {
				return false;
			}
		}

		return (bool) wp_next_scheduled( $hook, $args );
	}

	/**
	 * Cancel all pending actions matching the hook and args.
	 *
	 * @param string $hook  Hook name.
	 * @param array  $args  Hook arguments.
	 * @param string $group Group name.
	 * @return void
	 */
	public static function unschedule_action( string $hook, array $args = array(), string $group = self::GROUP ): void {
		if ( self::is_available() ) {
			try {
				if ( function_exists( 'as_unschedule_action' ) ) {
					as_unschedule_action( $hook, $args, $group );
				}
			} catch ( \Throwable $e ) {
				// Swallow.
			}
			return;
		}

		wp_unschedule_hook( $hook );
	}
}
