<?php
/**
 * Schedule Repository Interface
 *
 * Defines the contract for schedule persistence operations.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_Schedule_Repository_Interface {

	/**
	 * Fetch all schedules.
	 *
	 * @param bool $active_only Whether to return active schedules only.
	 * @return array
	 */
	public function get_all($active_only = false);

	/**
	 * Fetch a schedule by ID.
	 *
	 * @param int $id Schedule ID.
	 * @return object|null
	 */
	public function get_by_id($id);

	/**
	 * Fetch due schedules.
	 *
	 * @param string|null $current_time Current time in MySQL format.
	 * @param int         $limit Max results.
	 * @return array
	 */
	public function get_due_schedules($current_time = null, $limit = 5);

	/**
	 * Create a schedule.
	 *
	 * @param array $data Schedule data.
	 * @return int|false
	 */
	public function create($data);

	/**
	 * Update a schedule row.
	 *
	 * @param int   $id Schedule ID.
	 * @param array $data Update data.
	 * @return bool
	 */
	public function update($id, $data);

	/**
	 * Delete a schedule.
	 *
	 * @param int $id Schedule ID.
	 * @return bool
	 */
	public function delete($id);

	/**
	 * Update last-run timestamp.
	 *
	 * @param int         $id Schedule ID.
	 * @param string|null $timestamp MySQL timestamp.
	 * @return bool
	 */
	public function update_last_run($id, $timestamp = null);

	/**
	 * Set active/inactive state.
	 *
	 * @param int  $id Schedule ID.
	 * @param bool|int $is_active Active flag.
	 * @return bool
	 */
	public function set_active($id, $is_active);

	/**
	 * Update batch progress state.
	 *
	 * @param int   $id Schedule ID.
	 * @param int   $completed Completed count.
	 * @param int   $total Total count.
	 * @param int   $last_index Last processed index.
	 * @param array $post_ids Generated post IDs.
	 * @return bool
	 */
	public function update_batch_progress($id, $completed, $total, $last_index, $post_ids = array());

	/**
	 * Clear batch progress state.
	 *
	 * @param int $id Schedule ID.
	 * @return bool
	 */
	public function clear_batch_progress($id);

	/**
	 * Update run-state payload.
	 *
	 * @param int   $id Schedule ID.
	 * @param array $state State payload.
	 * @return bool
	 */
	public function update_run_state($id, array $state);

	/**
	 * Bulk delete schedules.
	 *
	 * @param array $ids Schedule IDs.
	 * @return int|false
	 */
	public function delete_bulk(array $ids);

	/**
	 * Bulk activate/deactivate schedules.
	 *
	 * @param array    $ids Schedule IDs.
	 * @param bool|int $is_active Active flag.
	 * @return int|false
	 */
	public function set_active_bulk(array $ids, $is_active);

	/**
	 * Get total generated post count for schedule IDs.
	 *
	 * @param array $ids Schedule IDs.
	 * @return int
	 */
	public function get_post_count_for_schedules(array $ids);
}
