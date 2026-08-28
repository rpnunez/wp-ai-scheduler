<?php
/**
 * Job Transport Interface
 *
 * Defines the contract for a background-job transport: the low-level backend
 * (WP-Cron, Action Scheduler, ...) that a job definition is handed to for
 * scheduling. Transports translate an AIPS_Job_Definition into the selected
 * backend and expose uniform scheduling, lookup, and unscheduling operations.
 *
 * Policy — job definitions, correlation IDs, retry decisions, duplicate
 * detection, history events, logging, and dispatch summaries — lives ABOVE
 * this interface (in AIPS_Job_Dispatcher / AIPS_Job_Scheduler). A transport
 * only performs the mechanical translation to its backend; it must not make
 * feature-level decisions.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Job_Transport_Interface
 *
 * Interchangeable scheduling backend for background jobs.
 */
interface AIPS_Job_Transport_Interface {

	/**
	 * Schedule a single job on the backend.
	 *
	 * Implementations MUST deliver the job's argument list to the registered
	 * hook callback with the same positional shape regardless of backend.
	 *
	 * @param AIPS_Job_Definition $job Job to schedule.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function schedule(AIPS_Job_Definition $job);

	/**
	 * Get the timestamp of an already-scheduled matching job, if any.
	 *
	 * Matching is by hook + arguments (+ group, where the backend supports it).
	 *
	 * @param AIPS_Job_Definition $job Job to look up.
	 * @return int|false Unix timestamp of the next matching scheduled job, or false.
	 */
	public function next_scheduled(AIPS_Job_Definition $job);

	/**
	 * Unschedule a matching job.
	 *
	 * @param AIPS_Job_Definition $job Job to unschedule.
	 * @return bool True if an event was unscheduled (or none existed), false on error.
	 */
	public function unschedule(AIPS_Job_Definition $job): bool;

	/**
	 * Whether this transport is available in the current environment.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Machine-readable transport identifier (e.g. 'wp_cron', 'action_scheduler').
	 *
	 * Used for logging and history metadata so behaviour can be attributed to a
	 * backend regardless of which one served the request.
	 *
	 * @return string
	 */
	public function get_name(): string;
}
