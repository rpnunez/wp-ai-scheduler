<?php
/**
 * Job Transport Resolver
 *
 * Selects the background-job transport: Action Scheduler when it is present and
 * healthy, otherwise WP-Cron. This is the single place where backend selection
 * happens — feature controllers and cron handlers must never choose a transport.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Job_Transport_Resolver
 *
 * Resolves the active transport for the current environment.
 */
class AIPS_Job_Transport_Resolver {

	/**
	 * @var AIPS_Action_Scheduler_Transport
	 */
	private $action_scheduler_transport;

	/**
	 * @var AIPS_WP_Cron_Transport
	 */
	private $wp_cron_transport;

	/**
	 * @var AIPS_Job_Transport_Interface|null Cached resolution for this request.
	 */
	private $resolved;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Action_Scheduler_Transport|null $action_scheduler_transport Optional Action Scheduler transport.
	 * @param AIPS_WP_Cron_Transport|null          $wp_cron_transport          Optional WP-Cron transport.
	 */
	public function __construct(
		?AIPS_Action_Scheduler_Transport $action_scheduler_transport = null,
		?AIPS_WP_Cron_Transport $wp_cron_transport = null
	) {
		$this->action_scheduler_transport = $action_scheduler_transport ?: new AIPS_Action_Scheduler_Transport();
		$this->wp_cron_transport = $wp_cron_transport ?: new AIPS_WP_Cron_Transport();
	}

	/**
	 * Resolve the active transport.
	 *
	 * @return AIPS_Job_Transport_Interface
	 */
	public function resolve(): AIPS_Job_Transport_Interface {
		if (null !== $this->resolved) {
			return $this->resolved;
		}

		$prefer_action_scheduler = $this->action_scheduler_transport->is_available();

		/**
		 * Filter whether Action Scheduler should be preferred over WP-Cron.
		 *
		 * Allows forcing the WP-Cron fallback even when Action Scheduler is
		 * installed (e.g. for debugging or environment-specific policy).
		 *
		 * @param bool $prefer_action_scheduler Whether Action Scheduler is preferred.
		 */
		$prefer_action_scheduler = (bool) apply_filters(
			'aips_prefer_action_scheduler',
			$prefer_action_scheduler
		);

		$this->resolved = ($prefer_action_scheduler && $this->action_scheduler_transport->is_available())
			? $this->action_scheduler_transport
			: $this->wp_cron_transport;

		return $this->resolved;
	}

	/**
	 * Reset this resolver's cached resolution.
	 *
	 * Only clears the local cache on this instance; it does not invalidate a
	 * transport already handed out via the container singleton or cached inside
	 * AIPS_Job_Dispatcher. Intended for tests that re-resolve with different
	 * availability, not for changing an already-booted request's transport.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->resolved = null;
	}
}
