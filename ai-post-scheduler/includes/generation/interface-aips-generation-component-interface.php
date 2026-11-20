<?php
/**
 * Generation Component Interface
 *
 * Universal contract representing an atomic unit of AI generation
 * (e.g. post title, post content, post slug, featured image, topic prompt).
 *
 * Each component owns both initial generation and single-component regeneration
 * so there is a single source of truth across pipeline creation and AI Edit.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Generation_Component_Interface
 */
interface AIPS_Generation_Component_Interface {

	/**
	 * Unique machine key for this component (e.g. 'post_title', 'post_slug').
	 *
	 * @return string
	 */
	public function get_key(): string;

	/**
	 * Human-readable label for UI, history logs, and diagnostics.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Strategy key this component belongs to (e.g. 'posts', 'author_topics').
	 *
	 * @return string
	 */
	public function get_strategy_key(): string;

	/**
	 * Whether this component is enabled for the given generation context.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return bool
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool;

	/**
	 * Whether failure of this component causes the generation to be incomplete or failed.
	 *
	 * @return bool
	 */
	public function is_required(): bool;

	/**
	 * Build the AI prompt for this component.
	 *
	 * @param AIPS_Generation_Context   $context Generation context.
	 * @param AIPS_AI_Conversation|null $session Optional active conversation transcript.
	 * @return string
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string;

	/**
	 * Execute initial generation of this component.
	 *
	 * @param AIPS_Generation_Context   $context Generation context.
	 * @param AIPS_AI_Conversation|null $session Optional active conversation transcript.
	 * @param array                     $options Optional runtime parameters and upstream values.
	 * @return mixed Generated value or WP_Error on failure.
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array());

	/**
	 * Execute single-component regeneration for an existing target entity.
	 *
	 * @param int|string                $target_id Target entity identifier (e.g. post ID).
	 * @param AIPS_Generation_Context   $context   Generation context.
	 * @param AIPS_AI_Conversation|null $session   Optional active conversation transcript.
	 * @param string                    $directive Optional custom AI Edit directive.
	 * @param array                     $options   Optional runtime parameters (history_container, current_title, etc.).
	 * @return mixed Regenerated value or WP_Error on failure.
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array());

	/**
	 * Generate multiple candidate variants for editor/admin selection.
	 *
	 * @param int|string              $target_id Target entity identifier.
	 * @param AIPS_Generation_Context $context   Generation context.
	 * @param int                     $count     Number of variants to produce.
	 * @param array                   $options   Optional runtime parameters.
	 * @return array|WP_Error List of sanitized candidate values or WP_Error.
	 */
	public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array());

	/**
	 * Persist or apply a generated value onto a target entity.
	 *
	 * @param mixed $target Target entity or identifier.
	 * @param mixed $value  Generated or regenerated value.
	 * @return bool True on success, false on failure.
	 */
	public function apply($target, $value): bool;

	/**
	 * Reconcile the persisted completion status of this component on the target entity.
	 *
	 * @param int|string $target_id Target entity identifier.
	 * @return bool True if the component is present/valid on the target entity.
	 */
	public function reconcile_status($target_id): bool;

	/**
	 * Whether this component can be revised via AI Edit.
	 *
	 * @return bool
	 */
	public function supports_ai_edit(): bool;

	/**
	 * Sanitize a raw value for this component.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed Sanitized value.
	 */
	public function sanitize_value($value);
}
