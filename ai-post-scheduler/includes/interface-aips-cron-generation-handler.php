<?php
/**
 * Interface AIPS_Cron_Generation_Handler
 *
 * Defines the contract for classes that own a WordPress cron-driven
 * generation pipeline.  Each handler is responsible for registering its
 * own cron hook and calling process() from that hook.
 *
 * Having an explicit interface makes the cron registration pattern
 * discoverable and testable, and opens the door to a future dispatcher
 * that can iterate registered handlers without knowing their concrete types.
 *
 * @package AI_Post_Scheduler
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_Cron_Generation_Handler {

	/**
	 * Process any pending generation work for this handler.
	 *
	 * Implementations must be idempotent and resilient: a failure for one
	 * unit of work (schedule, author, etc.) must not prevent the remaining
	 * units from being processed.
	 */
	public function process(): void;
}
