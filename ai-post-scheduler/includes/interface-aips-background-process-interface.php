<?php
/**
 * Background Process Interface
 *
 * Contract every long-running background process exposes so it can be listed,
 * started, paused, resumed and stopped from one place (the Diagnostics
 * "Background Processes" tab, the admin bar and the Heartbeat updates).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_Background_Process_Interface {

	/**
	 * @return string Stable process key (lowercase letters, digits, underscores).
	 */
	public function get_key(): string;

	/**
	 * @return string Human-readable name.
	 */
	public function get_label(): string;

	/**
	 * @return string One-sentence description of what the process does.
	 */
	public function get_description(): string;

	/**
	 * @return bool Whether the process makes (billable) AI calls.
	 */
	public function uses_ai(): bool;

	/**
	 * Current state, normalized across processes.
	 *
	 * @return array See AIPS_Background_Process_Base::make_snapshot() for the keys.
	 */
	public function get_snapshot(): array;

	/**
	 * Start a run.
	 *
	 * @param array $options Process-specific options (for example `ai_budget`).
	 * @return array|WP_Error The new snapshot, or an error when the process cannot start.
	 */
	public function start(array $options = array());

	/**
	 * Pause the running process; it can be resumed later.
	 *
	 * @return bool True when a running process was paused.
	 */
	public function pause(): bool;

	/**
	 * Resume a paused process.
	 *
	 * @return bool True when a paused process was resumed.
	 */
	public function resume(): bool;

	/**
	 * Stop the process for good (work already done is kept).
	 *
	 * @return bool True when a running or paused process was stopped.
	 */
	public function cancel(): bool;

	/**
	 * Estimate the cost of a run started now.
	 *
	 * @param array $options The options the run would start with (for example `mode`).
	 * @return array Keys: items, ai_calls, days, daily_rate, message. Empty when not applicable.
	 */
	public function get_estimate(array $options = array()): array;
}
