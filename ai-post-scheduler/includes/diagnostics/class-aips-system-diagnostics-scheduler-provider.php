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
	 * Basic per-hook cron schedule check.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function check_cron() {
		$cron_events = AI_Post_Scheduler::get_cron_events();
		$status      = array();

		foreach ( $cron_events as $event_hook => $event_config ) {
			$next_run               = wp_next_scheduled( $event_hook );
			$status[ $event_hook ] = array(
				'label'  => isset( $event_config['label'] ) ? $event_config['label'] : $event_hook,
				'value'  => $next_run ? wp_date( 'Y-m-d H:i:s', $next_run ) : __( 'Not Scheduled', 'ai-post-scheduler' ),
				'status' => $next_run ? 'ok' : 'error',
			);
		}

		return $status;
	}

	/**
	 * Detailed scheduler health: per-hook cron diagnostics, queue-depth
	 * surrogates, and 30-day schedule-run success rate.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function check_scheduler_health() {
		$checks = array();

		// --- Per-hook cron diagnostics ---
		$cron_events     = AI_Post_Scheduler::get_cron_events();
		$expected_total  = count( $cron_events );
		$actual_total    = 0;
		$hooks_missing   = array();
		$hooks_duplicate = array();

		foreach ( $cron_events as $hook => $config ) {
			$label      = isset( $config['label'] ) ? $config['label'] : $hook;
			$schedule   = isset( $config['schedule'] ) ? $config['schedule'] : '';
			$actual     = $this->count_cron_hook_instances( $hook );
			$timestamps = $this->get_cron_hook_timestamps( $hook );
			$actual_total += $actual;

			if ( $actual === 0 ) {
				$hooks_missing[] = $label;
			} elseif ( $actual > 1 ) {
				$hooks_duplicate[] = $label;
			}

			$detail_lines   = array();
			$detail_lines[] = sprintf(
				__( 'Hook: %s | Expected: 1 | Actual: %d | Schedule: %s', 'ai-post-scheduler' ),
				$hook,
				$actual,
				$schedule
			);

			if ( ! empty( $timestamps ) ) {
				$next           = $timestamps[0];
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

			$hook_key = 'cron_hook_' . sanitize_key( $hook );
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
					__( '%d duplicate events — expected 1. Use "Flush WP-Cron Events" to fix.', 'ai-post-scheduler' ),
					$actual
				);
			}

			$checks[ $hook_key ] = array(
				'label'   => sprintf( __( 'Cron: %s', 'ai-post-scheduler' ), $label ),
				'value'   => $hook_value,
				'status'  => $hook_status,
				'details' => $detail_lines,
			);
		}

		// --- On-demand (single-shot) cron checks ---
		// These events are only scheduled while work is actively in progress;
		// 0 instances is normal (idle), not an error.
		$on_demand_hooks = array(
			'aips_index_posts_batch'        => __( 'Internal Links Indexing', 'ai-post-scheduler' ),
			'aips_process_author_embeddings' => __( 'Author Embeddings', 'ai-post-scheduler' ),
		);

		foreach ( $on_demand_hooks as $hook => $label ) {
			$actual     = $this->count_cron_hook_instances( $hook );
			$timestamps = $this->get_cron_hook_timestamps( $hook );
			$hook_key   = 'cron_hook_' . sanitize_key( $hook );

			if ( $actual === 0 ) {
				$hook_status = 'info';
				$hook_value  = __( 'Idle (no active job)', 'ai-post-scheduler' );
				$detail_lines = array(
					sprintf( __( 'Hook: %s | This is an on-demand event; idle when no work is queued.', 'ai-post-scheduler' ), $hook ),
				);
			} else {
				$hook_status  = 'ok';
				$next_ts      = isset( $timestamps[0] ) ? $timestamps[0] : 0;
				$hook_value   = $next_ts
					? sprintf( __( 'Active (%d) — next: %s', 'ai-post-scheduler' ), $actual, wp_date( 'Y-m-d H:i:s', $next_ts ) )
					: sprintf( __( 'Active (%d)', 'ai-post-scheduler' ), $actual );
				$detail_lines = array(
					sprintf( __( 'Hook: %s | Queued batches: %d', 'ai-post-scheduler' ), $hook, $actual ),
				);
				if ( $next_ts ) {
					$detail_lines[] = sprintf(
						__( 'Next run: %s (%s)', 'ai-post-scheduler' ),
						wp_date( 'Y-m-d H:i:s', $next_ts ),
						human_time_diff( $next_ts, time() ) . ( $next_ts > time() ? ' from now' : ' ago' )
					);
				}
			}

			$checks[ $hook_key ] = array(
				'label'   => sprintf( __( 'Cron: %s', 'ai-post-scheduler' ), $label ),
				'value'   => $hook_value,
				'status'  => $hook_status,
				'details' => $detail_lines,
			);
		}

		// --- Cron summary ---
		$summary_status = 'ok';
		if ( ! empty( $hooks_missing ) || ! empty( $hooks_duplicate ) ) {
			$summary_status = 'error';
		}
		$summary_parts = array(
			sprintf( __( 'Expected: %d', 'ai-post-scheduler' ), $expected_total ),
			sprintf( __( 'Actual: %d', 'ai-post-scheduler' ), $actual_total ),
		);
		if ( ! empty( $hooks_missing ) ) {
			$summary_parts[] = sprintf( __( 'Missing: %s', 'ai-post-scheduler' ), implode( ', ', $hooks_missing ) );
		}
		if ( ! empty( $hooks_duplicate ) ) {
			$summary_parts[] = sprintf( __( 'Duplicates: %s', 'ai-post-scheduler' ), implode( ', ', $hooks_duplicate ) );
		}

		$checks['cron_summary'] = array(
			'label'  => __( 'WP-Cron Event Summary', 'ai-post-scheduler' ),
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
