<?php
/**
 * Interface AIPS_Cron_Generation_Handler
 *
 * Defines the contract for classes that process WordPress cron-driven
 * generation work. Cron hook registration is owned by the plugin bootstrap,
 * which invokes process() through the registered callbacks.
 *
 * Having an explicit interface makes the processing contract
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
