<?php
/**
 * Pipeline Executable Strategy Interface
 *
 * Extension of AIPS_Generation_Strategy_Interface for strategies that own
 * specialized multi-stage execution and preview flows (e.g. Posts, Author
 * Topics, Author Personas, Campaigns, Research), allowing
 * AIPS_Generation_Pipeline to dispatch them polymorphically without
 * strategy-specific instanceof chains.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Pipeline_Executable_Strategy_Interface
 */
interface AIPS_Pipeline_Executable_Strategy_Interface extends AIPS_Generation_Strategy_Interface {

	/**
	 * Execute the full generation run for this strategy.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return AIPS_Generation_Result
	 */
	public function execute_run(AIPS_Generation_Context $context): AIPS_Generation_Result;

	/**
	 * Execute a non-persistent preview run for this strategy.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return array|WP_Error
	 */
	public function execute_preview_run(AIPS_Generation_Context $context);
}
