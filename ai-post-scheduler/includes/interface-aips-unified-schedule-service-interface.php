<?php
/**
 * Unified Schedule Service Interface
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Interface AIPS_Unified_Schedule_Service_Interface
 */
interface AIPS_Unified_Schedule_Service_Interface {

	/**
	 * Return all scheduled processes, optionally filtered by type.
	 *
	 * @param string $type_filter   Optional type constant to restrict results.
	 * @param bool   $include_stats Whether to run aggregate stats queries.
	 * @return array Sorted, normalised schedule rows.
	 */
	public function get_all($type_filter = '', $include_stats = true);

	/**
	 * Delete a specific schedule when the schedule type supports deletion.
	 *
	 * @param int    $id   Numeric ID.
	 * @param string $type One of the TYPE_* constants.
	 * @return true|WP_Error
	 */
	public function delete($id, $type);

	/**
	 * Get run-history log entries for a schedule.
	 *
	 * @param int    $id    Numeric ID.
	 * @param string $type  One of the TYPE_* constants.
	 * @param int    $limit Max entries to return; 0 = no limit.
	 * @return array Normalised log entry arrays.
	 */
	public function get_history($id, $type, $limit = 0);

	/**
	 * Toggle the status of a specific schedule using legacy signature.
	 *
	 * @param int    $id        Numeric ID.
	 * @param string $type      One of the TYPE_* constants.
	 * @param int    $is_active 1 to enable, 0 to pause.
	 * @return bool|int False on failure, truthy on success.
	 */
	public function toggle($id, $type, $is_active);

	/**
	 * Run a specific schedule immediately.
	 *
	 * @param int    $id   Numeric ID.
	 * @param string $type One of the TYPE_* constants.
	 * @return mixed
	 */
	public function run_now($id, $type);
}
