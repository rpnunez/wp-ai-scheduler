<?php
/**
 * AIPS Cron Backend Interface — uniform contract for background scheduling
 * backends (ActionScheduler and WP-Cron).
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AIPS_Cron_Backend_Interface {

	/**
	 * Schedule a single action/event.
	 *
	 * @param int    $timestamp Unix timestamp when the action should run.
	 * @param string $hook      Action hook to trigger.
	 * @param array  $args      Arguments to pass to the hook handler.
	 * @param string $group     Logical group/namespace (e.g. ActionScheduler group).
	 * @return bool True if successfully scheduled or already exists.
	 */
	public function schedule_single( $timestamp, $hook, array $args = array(), $group = '' );

	/**
	 * Schedule a recurring action/event.
	 *
	 * @param int        $timestamp Unix timestamp of the first occurrence.
	 * @param int|string $interval  Interval in seconds or recurring schedule slug (e.g. 'hourly').
	 * @param string     $hook      Action hook to trigger.
	 * @param array      $args      Arguments to pass to the hook handler.
	 * @param string     $group     Logical group/namespace.
	 * @return bool True if successfully scheduled or already exists.
	 */
	public function schedule_recurring( $timestamp, $interval, $hook, array $args = array(), $group = '' );

	/**
	 * Get the next scheduled timestamp for a hook and args.
	 *
	 * @param string $hook Action hook.
	 * @param array  $args Hook arguments.
	 * @return int|false Next timestamp or false if not scheduled.
	 */
	public function next_scheduled( $hook, array $args = array() );

	/**
	 * Check if an action is scheduled.
	 *
	 * @param string $hook  Action hook.
	 * @param array  $args  Hook arguments.
	 * @param string $group Logical group.
	 * @return bool True if scheduled.
	 */
	public function has_scheduled( $hook, array $args = array(), $group = '' );

	/**
	 * Unschedule a single matching action.
	 *
	 * @param string $hook Action hook.
	 * @param array  $args Hook arguments.
	 * @return bool True if unscheduled or not present.
	 */
	public function unschedule( $hook, array $args = array() );

	/**
	 * Unschedule all occurrences of a hook or group.
	 *
	 * @param string $hook  Action hook.
	 * @param string $group Optional group name.
	 * @return bool|int True/count if cleared.
	 */
	public function unschedule_all( $hook, $group = '' );

	/**
	 * Get scheduled actions/events for diagnostics or monitoring.
	 *
	 * @param string $hook  Optional hook filter.
	 * @param string $group Optional group filter.
	 * @return array List of normalized action items:
	 *               array(
	 *                   array(
	 *                       'hook'      => string,
	 *                       'timestamp' => int,
	 *                       'args'      => array,
	 *                       'group'     => string,
	 *                       'backend'   => string,
	 *                   )
	 *               )
	 */
	public function get_scheduled_actions( $hook = '', $group = '' );

	/**
	 * Get the identifier name of the active backend.
	 *
	 * @return string 'action_scheduler' or 'wp_cron'
	 */
	public function get_backend_name();
}
