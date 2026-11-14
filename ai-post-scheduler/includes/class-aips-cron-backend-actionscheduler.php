<?php
/**
 * AIPS Cron Backend ActionScheduler — adapter delegating scheduling to ActionScheduler.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Cron_Backend_ActionScheduler implements AIPS_Cron_Backend_Interface {

	/**
	 * Default ActionScheduler group if none specified.
	 *
	 * @var string
	 */
	const DEFAULT_GROUP = 'aips-default';

	/**
	 * Normalize arguments for ActionScheduler.
	 *
	 * If arguments are associative, wrap them so that ActionScheduler's
	 * `array_values` spreading passes the payload intact to handlers expecting
	 * a single array argument.
	 *
	 * @param array $args Arguments.
	 * @return array Normalized arguments.
	 */
	private function normalize_args( array $args ) {
		if ( empty( $args ) ) {
			return array();
		}

		if ( array_keys( $args ) !== range( 0, count( $args ) - 1 ) ) {
			return array( $args );
		}

		return $args;
	}

	/**
	 * Resolve group.
	 *
	 * @param string $group Group.
	 * @return string Non-empty group.
	 */
	private function resolve_group( $group ) {
		return ! empty( $group ) ? $group : self::DEFAULT_GROUP;
	}

	/**
	 * @inheritDoc
	 */
	public function schedule_single( $timestamp, $hook, array $args = array(), $group = '' ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return false;
		}

		$normalized_args = $this->normalize_args( $args );
		$resolved_group  = $this->resolve_group( $group );

		if ( $this->has_scheduled( $hook, $normalized_args, $resolved_group ) ) {
			return true;
		}

		$action_id = as_schedule_single_action( $timestamp, $hook, $normalized_args, $resolved_group );
		return (bool) $action_id;
	}

	/**
	 * @inheritDoc
	 */
	public function schedule_recurring( $timestamp, $interval, $hook, array $args = array(), $group = '' ) {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return false;
		}

		$normalized_args = $this->normalize_args( $args );
		$resolved_group  = $this->resolve_group( $group );

		if ( $this->has_scheduled( $hook, $normalized_args, $resolved_group ) ) {
			return true;
		}

		$seconds   = is_numeric( $interval ) ? (int) $interval : $this->recurrence_to_interval( (string) $interval );
		$action_id = as_schedule_recurring_action( $timestamp, $seconds, $hook, $normalized_args, $resolved_group );
		return (bool) $action_id;
	}

	/**
	 * @inheritDoc
	 */
	public function next_scheduled( $hook, array $args = array() ) {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return false;
		}

		$normalized_args = $this->normalize_args( $args );
		$timestamp       = as_next_scheduled_action( $hook, $normalized_args );
		return false !== $timestamp ? (int) $timestamp : false;
	}

	/**
	 * @inheritDoc
	 */
	public function has_scheduled( $hook, array $args = array(), $group = '' ) {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		$normalized_args = $this->normalize_args( $args );
		$resolved_group  = ! empty( $group ) ? $group : null;

		return (bool) as_has_scheduled_action( $hook, $normalized_args, $resolved_group );
	}

	/**
	 * @inheritDoc
	 */
	public function unschedule( $hook, array $args = array() ) {
		if ( ! function_exists( 'as_unschedule_action' ) ) {
			return false;
		}

		$normalized_args = $this->normalize_args( $args );
		as_unschedule_action( $hook, $normalized_args );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function unschedule_all( $hook, $group = '' ) {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return false;
		}

		$resolved_group = ! empty( $group ) ? $group : '';
		as_unschedule_all_actions( $hook, null, $resolved_group );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function get_scheduled_actions( $hook = '', $group = '' ) {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return array();
		}

		$query_args = array(
			'status'   => 'pending',
			'per_page' => 100,
		);
		if ( ! empty( $hook ) ) {
			$query_args['hook'] = $hook;
		}
		if ( ! empty( $group ) ) {
			$query_args['group'] = $group;
		}

		$raw_actions = as_get_scheduled_actions( $query_args );
		$actions     = array();

		if ( ! is_array( $raw_actions ) ) {
			return $actions;
		}

		foreach ( $raw_actions as $action ) {
			if ( ! is_object( $action ) ) {
				continue;
			}
			$schedule  = method_exists( $action, 'get_schedule' ) ? $action->get_schedule() : null;
			$timestamp = ( $schedule && method_exists( $schedule, 'get_date' ) && $schedule->get_date() )
				? $schedule->get_date()->getTimestamp()
				: 0;

			$actions[] = array(
				'hook'      => method_exists( $action, 'get_hook' ) ? $action->get_hook() : '',
				'timestamp' => $timestamp,
				'args'      => method_exists( $action, 'get_args' ) ? $action->get_args() : array(),
				'group'     => method_exists( $action, 'get_group' ) ? $action->get_group() : '',
				'backend'   => 'action_scheduler',
			);
		}

		return $actions;
	}

	/**
	 * @inheritDoc
	 */
	public function get_backend_name() {
		return 'action_scheduler';
	}

	/**
	 * Convert schedule slug to seconds.
	 *
	 * @param string $recurrence Schedule slug.
	 * @return int Seconds.
	 */
	private function recurrence_to_interval( $recurrence ) {
		$schedules = wp_get_schedules();
		if ( isset( $schedules[ $recurrence ]['interval'] ) ) {
			return (int) $schedules[ $recurrence ]['interval'];
		}

		switch ( $recurrence ) {
			case 'hourly':
				return 3600;
			case 'twicedaily':
				return 43200;
			case 'daily':
				return 86400;
			case 'weekly':
				return 604800;
			default:
				return 3600;
		}
	}
}
