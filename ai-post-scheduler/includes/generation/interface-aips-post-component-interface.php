<?php
/**
 * Post Component Interface
 *
 * Extends the universal generation component contract with WordPress post
 * lifecycle stages, combined metadata turn participation, and typed post
 * persistence.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Post_Component_Interface
 */
interface AIPS_Post_Component_Interface extends AIPS_Generation_Component_Interface {

	/** Pipeline stage constants for Post Generation. */
	const STAGE_CONTENT      = 'content';
	const STAGE_METADATA     = 'metadata';
	const STAGE_PERSISTENCE  = 'persistence';
	const STAGE_MEDIA        = 'media';
	const STAGE_INTEGRATIONS = 'integrations';
	const STAGE_FINALIZATION = 'finalization';

	/**
	 * Return the post-generation stage this component executes in.
	 *
	 * One of the STAGE_* constants.
	 *
	 * @return string
	 */
	public function get_stage(): string;

	/**
	 * Whether this component can be fulfilled by the combined metadata turn.
	 *
	 * @return bool
	 */
	public function participates_in_metadata_turn(): bool;

	/**
	 * Return the JSON schema fragment contributed by this component to the
	 * combined metadata turn (empty array when not applicable).
	 *
	 * @return array
	 */
	public function get_metadata_turn_schema(): array;

	/**
	 * Apply a generated or regenerated value directly to a WordPress post.
	 *
	 * @param int   $post_id WordPress post ID.
	 * @param mixed $value   Sanitized component value.
	 * @return bool True on success, false on failure.
	 */
	public function apply_to_post(int $post_id, $value): bool;
}
