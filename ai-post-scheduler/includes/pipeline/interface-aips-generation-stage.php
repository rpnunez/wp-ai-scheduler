<?php
/**
 * Generation Stage Interface
 *
 * Defines the contract for an individual middleware stage in the post generation pipeline.
 * Each stage inspects and enriches the Generation Pipeline Payload before passing control
 * to the next stage callable.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_Generation_Stage_Interface {

	/**
	 * Unique identifier for this stage (e.g. 'knowledge_retrieval', 'drafting', 'seo').
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable label for debugging and telemetry.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Priority weight determining stage order (lower numbers run earlier).
	 *
	 * @return int
	 */
	public function get_priority(): int;

	/**
	 * Determine if this stage should execute for the given context and current payload state.
	 *
	 * @param AIPS_Generation_Context          $context Generation context.
	 * @param AIPS_Generation_Pipeline_Payload $payload Mutable payload state.
	 * @return bool True if stage should run.
	 */
	public function should_run(AIPS_Generation_Context $context, AIPS_Generation_Pipeline_Payload $payload): bool;

	/**
	 * Process the pipeline payload and invoke next stage.
	 *
	 * @param AIPS_Generation_Context          $context Generation context.
	 * @param AIPS_Generation_Pipeline_Payload $payload Mutable payload state.
	 * @param callable                         $next    Next stage callable ($payload): AIPS_Generation_Pipeline_Payload.
	 * @return AIPS_Generation_Pipeline_Payload
	 */
	public function process(
		AIPS_Generation_Context $context,
		AIPS_Generation_Pipeline_Payload $payload,
		callable $next
	): AIPS_Generation_Pipeline_Payload;
}
