<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Repository for data management import/export persistence.
 */
class AIPS_Data_Management_Repository {

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Fetch all rows for a validated plugin table.
	 *
	 * @param string $full_table_name Table name including prefix.
	 * @return array|null Rows, or null when the actual read failed.
	 */
	public function get_table_rows($full_table_name) {
		$rows = $this->wpdb->get_results('SELECT * FROM `' . esc_sql($full_table_name) . '`', ARRAY_A);
		return $this->wpdb->last_error ? null : $rows;
	}

	/**
	 * Fetch the CREATE TABLE statement for a validated plugin table.
	 *
	 * @param string $full_table_name Table name including prefix.
	 * @return string|null
	 */
	public function get_create_table_statement($full_table_name) {
		$create_table = $this->wpdb->get_row('SHOW CREATE TABLE `' . esc_sql($full_table_name) . '`', ARRAY_N);

		if (!is_array($create_table) || !isset($create_table[1])) {
			return null;
		}

		return (string) $create_table[1];
	}

	/**
	 * Disable foreign key checks for bulk imports.
	 *
	 * @return void
	 */
	public function disable_foreign_key_checks() {
		$this->wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
	}

	/**
	 * Re-enable foreign key checks after bulk imports.
	 *
	 * @return void
	 */
	public function enable_foreign_key_checks() {
		$this->wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	/**
	 * Truncate a validated plugin table.
	 *
	 * @param string $full_table_name Table name including prefix.
	 * @return void
	 */
	public function truncate_table($full_table_name) {
		$this->wpdb->query('TRUNCATE TABLE `' . esc_sql($full_table_name) . '`');
	}

	/**
	 * Insert one row into a validated plugin table.
	 *
	 * @param string $full_table_name Table name including prefix.
	 * @param array  $row             Row payload.
	 * @return bool
	 */
	public function insert_row($full_table_name, $row) {
		return false !== $this->wpdb->insert($full_table_name, $row);
	}
}
