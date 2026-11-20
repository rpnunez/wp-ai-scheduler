<?php
/**
 * AIPS Cron Backend WP-Cron — adapter delegating scheduling to WP-Cron via AIPS_Cron_Manager.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Cron_Backend_WP_Cron implements AIPS_Cron_Backend_Interface {

	/**
	 * Backing Cron Manager.
	 *
	 * @var AIPS_Cron_Manager
	 */
	private $cron_manager;

	/**
	 * @param AIPS_Cron_Manager|null $cron_manager Backing cron manager.
	 */
	public function __construct( AIPS_Cron_Manager $cron_manager = null ) {
		$this->cron_manager = $cron_manager instanceof AIPS_Cron_Manager
			? $cron_manager
			: AIPS_Container::get_instance()->make( AIPS_Cron_Manager::class );
	}

	/**
	 * @inheritDoc
	 */
	public function schedule_single( $timestamp, $hook, array $args = array(), $group = '' ) {
		if ( $this->has_scheduled( $hook, $args, $group ) ) {
			return true;
		}

		$result = $this->cron_manager->schedule_single( $timestamp, $hook, $args );
		return false !== $result;
	}

	/**
	 * @inheritDoc
	 */
	public function schedule_recurring( $timestamp, $interval, $hook, array $args = array(), $group = '' ) {
		$recurrence = is_numeric( $interval ) ? $this->interval_to_recurrence( (int) $interval ) : (string) $interval;
		$result     = $this->cron_manager->schedule_recurring( $timestamp, $recurrence, $hook, $args );
		return false !== $result;
	}

	/**
	 * @inheritDoc
	 */
	public function next_scheduled( $hook, array $args = array() ) {
		return $this->cron_manager->next_scheduled( $hook, $args );
	}

	/**
	 * @inheritDoc
	 */
	public function has_scheduled( $hook, array $args = array(), $group = '' ) {
		return false !== $this->next_scheduled( $hook, $args );
	}

	/**
	 * @inheritDoc
	 */
	public function unschedule( $hook, array $args = array() ) {
		$timestamp = $this->next_scheduled( $hook, $args );
		if ( ! $timestamp ) {
			return true;
		}

		$result = $this->cron_manager->unschedule_occurrence( $timestamp, $hook, $args );
		return false !== $result;
	}

	/**
	 * @inheritDoc
	 */
	public function unschedule_all( $hook, $group = '' ) {
		$result = $this->cron_manager->unschedule_all_for_hook( $hook );
		return false !== $result;
	}

	/**
	 * @inheritDoc
	 */
	public function get_scheduled_actions( $hook = '', $group = '' ) {
		$crons   = _get_cron_array();
		$actions = array();

		if ( ! is_array( $crons ) ) {
			return $actions;
		}

		foreach ( $crons as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}
			foreach ( $hooks as $event_hook => $occurrences ) {
				if ( '' !== $hook && $event_hook !== $hook ) {
					continue;
				}
				if ( ! is_array( $occurrences ) ) {
					continue;
				}
				foreach ( $occurrences as $sig => $data ) {
					$actions[] = array(
						'hook'      => $event_hook,
						'timestamp' => (int) $timestamp,
						'args'      => isset( $data['args'] ) && is_array( $data['args'] ) ? $data['args'] : array(),
						'group'     => $group,
						'backend'   => 'wp_cron',
					);
				}
			}
		}

		return $actions;
	}

	/**
	 * @inheritDoc
	 */
	public function get_backend_name() {
		return 'wp_cron';
	}

	/**
	 * Convert numeric interval in seconds to standard WP recurrence key or fallback.
	 *
	 * @param int $interval Seconds.
	 * @return string Recurrence slug.
	 */
	private function interval_to_recurrence( $interval ) {
		$schedules = wp_get_schedules();
		foreach ( $schedules as $slug => $schedule ) {
			if ( isset( $schedule['interval'] ) && (int) $schedule['interval'] === $interval ) {
				return $slug;
			}
		}

		if ( $interval >= 86400 ) {
			return 'daily';
		} elseif ( $interval >= 43200 ) {
			return 'twicedaily';
		}
		return 'hourly';
	}
}
