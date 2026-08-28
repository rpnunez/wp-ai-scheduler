<?php
/**
 * Action Scheduler Job Transport
 *
 * Schedules background jobs on Action Scheduler when it is available (bundled
 * with WooCommerce and many other plugins). Adds queue-group support, which
 * WP-Cron lacks, while preserving the callback argument contract.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Action_Scheduler_Transport
 *
 * Wraps as_schedule_single_action / as_next_scheduled_action / as_unschedule_action.
 *
 * Argument contract: Action Scheduler invokes the hook via
 * do_action_ref_array( $hook, array_values( $args ) ), so passing the job's
 * positional argument list unmodified delivers the SAME positional arguments to
 * the callback that WP-Cron delivers. No additional wrapping is performed here;
 * any wrapping (e.g. a single associative payload) is the caller's contract and
 * already lives in AIPS_Job_Definition::get_args().
 */
class AIPS_Action_Scheduler_Transport implements AIPS_Job_Transport_Interface {

	/**
	 * {@inheritDoc}
	 */
	public function schedule(AIPS_Job_Definition $job) {
		if (!$this->is_available()) {
			return new WP_Error(
				'action_scheduler_unavailable',
				__('Action Scheduler is not available.', 'ai-post-scheduler')
			);
		}

		$action_id = call_user_func(
			'as_schedule_single_action',
			$job->get_fire_at(),
			$job->get_hook(),
			$job->get_args(),
			$this->group_for($job)
		);

		// as_schedule_single_action() normally returns the (positive) action ID.
		// A truthy ID is an unambiguous success.
		if (!empty($action_id)) {
			return true;
		}

		// Some Action Scheduler versions/paths can return 0/null/void even when
		// the action was stored. Treating that as a failure would make the
		// dispatcher retry and schedule a duplicate (Action Scheduler does not
		// dedup by default), so confirm against the store before reporting an
		// error.
		if (false !== $this->next_scheduled($job)) {
			return true;
		}

		return new WP_Error(
			'action_scheduler_schedule_failed',
			sprintf(
				/* translators: %s: cron hook name. */
				__('Action Scheduler failed to schedule the "%s" job.', 'ai-post-scheduler'),
				$job->get_hook()
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function next_scheduled(AIPS_Job_Definition $job) {
		if (!$this->is_available() || !function_exists('as_next_scheduled_action')) {
			return false;
		}

		$next = call_user_func(
			'as_next_scheduled_action',
			$job->get_hook(),
			$job->get_args(),
			$this->group_for($job)
		);

		// as_next_scheduled_action() returns the timestamp (int) of the next
		// matching action, true for an async/immediate action with no timestamp,
		// or false when none is scheduled. Normalize the boolean-true case to the
		// job's fire time so callers always get an int|false.
		if (true === $next) {
			return $job->get_fire_at();
		}

		return is_int($next) ? $next : false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function unschedule(AIPS_Job_Definition $job): bool {
		if (!$this->is_available() || !function_exists('as_unschedule_action')) {
			return false;
		}

		// as_unschedule_action() cancels the next matching scheduled action and
		// returns its action ID (or null when nothing matched). Either outcome
		// leaves no matching pending action, which is what callers want. A store
		// failure surfaces as a thrown exception, so report that as an error to
		// honor the interface contract (false on error).
		try {
			call_user_func(
				'as_unschedule_action',
				$job->get_hook(),
				$job->get_args(),
				$this->group_for($job)
			);
		} catch (\Throwable $e) {
			return false;
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return function_exists('as_schedule_single_action');
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'action_scheduler';
	}

	/**
	 * Resolve the Action Scheduler group for a job.
	 *
	 * @param AIPS_Job_Definition $job Job definition.
	 * @return string Non-empty group name.
	 */
	private function group_for(AIPS_Job_Definition $job): string {
		return AIPS_Job_Groups::normalize($job->get_group());
	}
}
