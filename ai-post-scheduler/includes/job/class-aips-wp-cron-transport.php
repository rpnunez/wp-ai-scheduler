<?php
/**
 * WP-Cron Job Transport
 *
 * Schedules background jobs on WordPress's built-in cron. This is the default,
 * always-available transport and the fallback when Action Scheduler is not
 * present.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_WP_Cron_Transport
 *
 * Wraps wp_schedule_single_event / wp_next_scheduled / wp_unschedule_event.
 * WordPress passes a job's positional argument list straight to the hook
 * callback, so no argument re-wrapping is performed here.
 */
class AIPS_WP_Cron_Transport implements AIPS_Job_Transport_Interface {

	/**
	 * {@inheritDoc}
	 */
	public function schedule(AIPS_Job_Definition $job) {
		// The fourth argument (true) asks WordPress to return a WP_Error on
		// failure instead of a bare false, so the dispatcher can log the reason.
		return wp_schedule_single_event(
			$job->get_fire_at(),
			$job->get_hook(),
			$job->get_args(),
			true
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function next_scheduled(AIPS_Job_Definition $job) {
		return wp_next_scheduled($job->get_hook(), $job->get_args());
	}

	/**
	 * {@inheritDoc}
	 */
	public function unschedule(AIPS_Job_Definition $job): bool {
		$timestamp = wp_next_scheduled($job->get_hook(), $job->get_args());

		if (false === $timestamp) {
			// Nothing to unschedule is treated as success (idempotent).
			return true;
		}

		$result = wp_unschedule_event($timestamp, $job->get_hook(), $job->get_args());

		// wp_unschedule_event() returns true/false in older WP and true|false|WP_Error
		// in newer versions; normalize anything non-error-truthy to a boolean.
		if (is_wp_error($result)) {
			return false;
		}

		return (bool) $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		// WP-Cron is part of WordPress core and is always available as a fallback.
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'wp_cron';
	}
}
