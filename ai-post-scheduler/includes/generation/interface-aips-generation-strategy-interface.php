<?php
/**
 * Generation Strategy Interface
 *
 * Defines the contract for a domain-specific AI generation strategy
 * (e.g. posts, author_topics, campaigns, research).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Generation_Strategy_Interface
 */
interface AIPS_Generation_Strategy_Interface {

	/**
	 * Unique machine key for this strategy (e.g. 'posts', 'author_topics').
	 *
	 * @return string
	 */
	public function get_key(): string;

	/**
	 * Human-readable label for admin/diagnostic displays.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Ordered list of pipeline stage keys executed by this strategy.
	 *
	 * @return string[]
	 */
	public function get_stages(): array;

	/**
	 * Return the ordered components applicable to the given context.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return AIPS_Generation_Component_Interface[]
	 */
	public function get_components(AIPS_Generation_Context $context): array;

	/**
	 * Hook executed immediately before the pipeline begins stage execution.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return void
	 */
	public function before_pipeline(AIPS_Generation_Context $context): void;

	/**
	 * Hook executed after the pipeline completes to finalize or enrich the result.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @param AIPS_Generation_Result  $result  Pipeline execution result.
	 * @return AIPS_Generation_Result
	 */
	public function after_pipeline(AIPS_Generation_Context $context, AIPS_Generation_Result $result): AIPS_Generation_Result;
}
