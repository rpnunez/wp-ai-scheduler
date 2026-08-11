<?php
/**
 * Generation Result Interface
 *
 * Shared contract for structured generation-result value objects returned by
 * author topic and post generation flows. Result objects replace the ambiguous
 * `int[]|WP_Error` return values so callers receive complete, accurate
 * information about what a generation run produced.
 *
 * @package AI_Post_Scheduler
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Generation_Result_Interface
 */
interface AIPS_Generation_Result_Interface {

	/**
	 * Overall run status.
	 *
	 * One of: success, partial, failed, no_work, already_running.
	 *
	 * @return string
	 */
	public function get_status(): string;

	/**
	 * Whether the run produced at least one successful item.
	 *
	 * @return bool
	 */
	public function is_success(): bool;

	/**
	 * Whether the run produced fewer items than requested.
	 *
	 * @return bool
	 */
	public function is_partial(): bool;

	/**
	 * Correlation ID associated with this run.
	 *
	 * @return string
	 */
	public function get_correlation_id(): string;

	/**
	 * Serialise the result to a plain associative array for logging,
	 * notifications and AJAX responses.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array;
}
