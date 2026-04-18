<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * AIPS_System_Diagnostics_Scheduler_Provider
 */
class AIPS_System_Diagnostics_Scheduler_Provider implements AIPS_System_Diagnostic_Provider_Interface {

	/**
	 * Minimum success rate (%) considered healthy for scheduler runs and
	 * post-generation outcomes.  At or above this threshold → 'ok'.
	 */
	const METRIC_OK_THRESHOLD = 90;

	/**
	 * Minimum success rate (%) considered a warning level.
	 * Between METRIC_WARN_THRESHOLD and METRIC_OK_THRESHOLD → 'warning'.
	 * Below METRIC_WARN_THRESHOLD → 'error'.
	 */
	const METRIC_WARN_THRESHOLD = 70;

	/**
	 * @return array<string, mixed>
	 */
	public function get_diagnostics(): array {
		return array(
			'cron'             => $this->check_cron(),
			'scheduler health' => $this->check_scheduler_health(),
		);
	}

	/**
	 * Per-hook queue schedule check.
	 *
	 * Delegates to AIPS_Queue_Manager so the status is accurate regardless of
	 * whether WP-Cron or Action Scheduler is the active driver.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function check_cron() {
		$queue_manager = new AIPS_Queue_Manager();
		return $queue_manager->get_hook_status();
	}

	/**
	 * Detailed scheduler health: per-hook queue diagnostics (driver-aware),
	 * queue-depth surrogates, and 30-day schedule-run success rate.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function check_scheduler_health() {
		$checks        = array();
		$queue_manager = new AIPS_Queue_Manager();
		$driver        = $queue_manager->get_driver();
		$driver_label  = $queue_manager->get_driver_label();

		// --- Active driver info ---
		$checks['queue_driver'] = array(
			'label'  => __( 'Queue Driver', 'ai-post-scheduler' ),
			'value'  => $driver_label,
			'status' => 'info',
		);

		// --- Per-hook diagnostics ---
		$cron_events     = AI_Post_Scheduler::get_cron_events();
		$expected_total  = count( $cron_events );
		$hooks_missing   = array();
		$hooks_duplicate = array();
		$actual_total    = 0;

		foreach ( $cron_events as $hook => $config ) {
			$label    = isset( $config['label'] )    ? $config['label']    : $hook;
			$schedule = isset( $config['schedule'] ) ? $config['schedule'] : '';

			$next_ts = $queue_manager->next_scheduled( $hook );

			$detail_lines   = array();
			$detail_lines[] = sprintf(
				__( 'Hook: %s | Driver: %s | Schedule: %s', 'ai-post-scheduler' ),
				$hook,
				$driver_label,
				$schedule
			);

			if ( $driver === AIPS_Queue_Manager::DRIVER_WPCRON ) {
				// For WP-Cron we can count exact instances and detect duplicates.
				$actual     = $this->count_cron_hook_instances( $hook );
				$timestamps = $this->get_cron_hook_timestamps( $hook );
				$actual_total += $actual;

				if ( $actual === 0 ) {
					$hooks_missing[] = $label;
				} elseif ( $actual > 1 ) {
					$hooks_duplicate[] = $label;
				}

				$detail_lines[0] = sprintf(
					__( 'Hook: %s | Expected: 1 | Actual: %d | Schedule: %s', 'ai-post-scheduler' ),
					$hook,
					$actual,
					$schedule
				);

				if ( ! empty( $timestamps ) ) {
					$next = $timestamps[0];
					$detail_lines[] = sprintf(
						__( 'Next run: %s (%s)', 'ai-post-scheduler' ),
						wp_date( 'Y-m-d H:i:s', $next ),
						human_time_diff( $next, time() ) . ( $next > time() ? ' from now' : ' ago' )
					);
					if ( count( $timestamps ) > 1 ) {
						$all_times      = array_map( function ( $ts ) {
							return wp_date( 'Y-m-d H:i:s', $ts );
						}, $timestamps );
						$detail_lines[] = sprintf(
							__( 'All scheduled times (%d): %s', 'ai-post-scheduler' ),
							count( $timestamps ),
							implode( ', ', $all_times )
						);
					}
				} else {
					$detail_lines[] = __( 'Not currently scheduled in WP-Cron.', 'ai-post-scheduler' );
				}

				if ( $actual === 0 ) {
					$hook_status = 'error';
					$hook_value  = __( 'Not scheduled', 'ai-post-scheduler' );
				} elseif ( $actual === 1 ) {
					$hook_status = 'ok';
					$hook_value  = empty( $timestamps )
						? __( 'Scheduled (1)', 'ai-post-scheduler' )
						: sprintf( __( 'Scheduled (1) — next: %s', 'ai-post-scheduler' ), wp_date( 'Y-m-d H:i:s', $timestamps[0] ) );
				} else {
					$hook_status = 'error';
					$hook_value  = sprintf(
						__( '%d duplicate events — expected 1. Use "Flush Queue Events" to fix.', 'ai-post-scheduler' ),
						$actual
					);
				}
			} else {
				// Action Scheduler: presence check only (AS deduplicates internally).
				$actual_total += $next_ts ? 1 : 0;
				if ( ! $next_ts ) {
					$hooks_missing[] = $label;
				}

				if ( $next_ts ) {
					$detail_lines[] = sprintf(
						__( 'Next run: %s (%s)', 'ai-post-scheduler' ),
						wp_date( 'Y-m-d H:i:s', (int) $next_ts ),
						human_time_diff( (int) $next_ts, time() ) . ( $next_ts > time() ? ' from now' : ' ago' )
					);
					$hook_status = 'ok';
					$hook_value  = sprintf( __( 'Scheduled — next: %s', 'ai-post-scheduler' ), wp_date( 'Y-m-d H:i:s', (int) $next_ts ) );
				} else {
					$detail_lines[] = __( 'Not currently scheduled in Action Scheduler.', 'ai-post-scheduler' );
					$hook_status    = 'error';
					$hook_value     = __( 'Not scheduled', 'ai-post-scheduler' );
				}
			}

			$hook_key            = 'queue_hook_' . sanitize_key( $hook );
			$checks[ $hook_key ] = array(
				'label'   => sprintf( __( 'Queue Hook: %s', 'ai-post-scheduler' ), $label ),
				'value'   => $hook_value,
				'status'  => $hook_status,
				'details' => $detail_lines,
			);
		}

		// --- Summary ---
		$summary_status = ( empty( $hooks_missing ) && empty( $hooks_duplicate ) ) ? 'ok' : 'error';
		$summary_parts  = array(
			sprintf( __( 'Driver: %s', 'ai-post-scheduler' ), $driver_label ),
			sprintf( __( 'Expected: %d', 'ai-post-scheduler' ), $expected_total ),
			sprintf( __( 'Scheduled: %d', 'ai-post-scheduler' ), $actual_total ),
		);
		if ( ! empty( $hooks_missing ) ) {
			$summary_parts[] = sprintf( __( 'Missing: %s', 'ai-post-scheduler' ), implode( ', ', $hooks_missing ) );
		}
		if ( ! empty( $hooks_duplicate ) ) {
			$summary_parts[] = sprintf( __( 'Duplicates: %s', 'ai-post-scheduler' ), implode( ', ', $hooks_duplicate ) );
		}

		$checks['cron_summary'] = array(
			'label'  => __( 'Queue Event Summary', 'ai-post-scheduler' ),
			'value'  => implode( ' | ', $summary_parts ),
			'status' => $summary_status,
		);

		// --- Metrics from repository ---
		if ( ! class_exists( 'AIPS_Metrics_Repository' ) ) {
			return $checks;
		}

		$metrics_repo = new AIPS_Metrics_Repository();
		$queue        = $metrics_repo->get_queue_depth_metrics();
		$generation   = $metrics_repo->get_generation_metrics( 30 );

		$checks['active_schedules'] = array(
			'label'  => __( 'Active Schedules', 'ai-post-scheduler' ),
			'value'  => (string) $queue['active_schedules'],
			'status' => $queue['active_schedules'] > 0 ? 'ok' : 'info',
		);

		$checks['approved_topics'] = array(
			'label'  => __( 'Approved Topics in Queue', 'ai-post-scheduler' ),
			'value'  => (string) $queue['approved_topics'],
			'status' => $queue['approved_topics'] > 0 ? 'info' : 'ok',
		);

		if ( $generation['schedule_success_rate'] >= 0 ) {
			$schedule_rate   = $generation['schedule_success_rate'];
			$schedule_status = $schedule_rate >= self::METRIC_OK_THRESHOLD ? 'ok' : ( $schedule_rate >= self::METRIC_WARN_THRESHOLD ? 'warning' : 'error' );
			$checks['schedule_success_rate'] = array(
				'label'  => __( 'Schedule Run Success Rate (30d)', 'ai-post-scheduler' ),
				'value'  => $schedule_rate . '%',
				'status' => $schedule_status,
			);
		} else {
			$checks['schedule_success_rate'] = array(
				'label'  => __( 'Schedule Run Success Rate (30d)', 'ai-post-scheduler' ),
				'value'  => __( 'No scheduled runs recorded', 'ai-post-scheduler' ),
				'status' => 'info',
			);
		}

		return $checks;
	}

	/**
	 * Count how many times a given cron hook appears across all scheduled
	 * timestamps in the WP cron table (including all arg variants).
	 *
	 * @param string $hook The cron hook name.
	 * @return int
	 */
	private function count_cron_hook_instances( $hook ) {
		$cron_array = _get_cron_array();
		if ( ! is_array( $cron_array ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $cron_array as $timestamp => $hooks ) {
			if ( isset( $hooks[ $hook ] ) && is_array( $hooks[ $hook ] ) ) {
				$count += count( $hooks[ $hook ] );
			}
		}

		return $count;
	}

	/**
	 * Get all scheduled timestamps for a given cron hook.
	 *
	 * @param string $hook The cron hook name.
	 * @return int[] Array of Unix timestamps.
	 */
	private function get_cron_hook_timestamps( $hook ) {
		$cron_array = _get_cron_array();
		if ( ! is_array( $cron_array ) ) {
			return array();
		}

		$timestamps = array();
		foreach ( $cron_array as $timestamp => $hooks ) {
			if ( isset( $hooks[ $hook ] ) ) {
				$arg_count = is_array( $hooks[ $hook ] ) ? count( $hooks[ $hook ] ) : 1;
				for ( $i = 0; $i < $arg_count; $i++ ) {
					$timestamps[] = (int) $timestamp;
				}
			}
		}

		sort( $timestamps );

		return $timestamps;
	}
}
