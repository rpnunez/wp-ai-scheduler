<?php
/**
 * Template Repository Interface
 *
 * Defines the contract for template persistence operations.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_Template_Repository_Interface {

	/**
	 * Fetch all templates.
	 *
	 * @param bool $active_only Whether to return active templates only.
	 * @return array
	 */
	public function get_all($active_only = false);

	/**
	 * Fetch a single template by ID.
	 *
	 * @param int $id Template ID.
	 * @return object|null
	 */
	public function get_by_id($id);

	/**
	 * Search templates by name.
	 *
	 * @param string $search_term Search term.
	 * @return array
	 */
	public function search($search_term);

	/**
	 * Create a new template.
	 *
	 * @param array $data Template data.
	 * @return int|false Inserted ID on success, false on failure.
	 */
	public function create($data);

	/**
	 * Update an existing template.
	 *
	 * @param int   $id   Template ID.
	 * @param array $data Update data.
	 * @return bool
	 */
	public function update($id, $data);

	/**
	 * Delete a template by ID.
	 *
	 * @param int $id Template ID.
	 * @return bool
	 */
	public function delete($id);

	/**
	 * Toggle template active status.
	 *
	 * @param int  $id        Template ID.
	 * @param bool $is_active Active status.
	 * @return bool
	 */
	public function set_active($id, $is_active);

	/**
	 * Count templates by active/inactive status.
	 *
	 * @return array{total: int, active: int}
	 */
	public function count_by_status();

	/**
	 * Check whether a template name already exists.
	 *
	 * @param string $name       Template name.
	 * @param int    $exclude_id Optional ID to exclude from the check.
	 * @return bool
	 */
	public function name_exists($name, $exclude_id = 0);
}
