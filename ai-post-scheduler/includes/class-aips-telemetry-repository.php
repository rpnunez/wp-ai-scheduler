<?php
/**
 * Telemetry Repository
 *
 * Handles all database interactions for the aips_telemetry table.
 * Only the repository class may write raw SQL against this table.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Telemetry_Repository
 *
 * Provides insert, paginated-read, and count operations for the
 * aips_telemetry table.
 */
class AIPS_Telemetry_Repository {

	/**
	 * @var AIPS_Telemetry_Repository|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return AIPS_Telemetry_Repository
	 */
	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Insert a new telemetry row.
	 *
	 * @param array $data Associative array of column => value pairs.
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function insert(array $data) {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_telemetry';

		$column_formats = array(
			'page'              => '%s',
			'user_id'           => '%d',
			'request_method'    => '%s',
			'num_queries'       => '%d',
			'peak_memory_bytes' => '%d',
			'elapsed_ms'        => '%f',
			'payload'           => '%s',
			'inserted_at'       => '%s',
		);

		$normalized_data = array();
		$formats         = array();
		foreach ($column_formats as $column => $format) {
			if (!array_key_exists($column, $data)) {
				continue;
			}
			$normalized_data[$column] = $data[$column];
			$formats[]                = $format;
		}

		if (empty($normalized_data)) {
			return false;
		}

		$inserted = $wpdb->insert(
			$table,
			$normalized_data,
			$formats
		);
		if ($inserted === false) {
			return false;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Retrieve a paginated page of telemetry rows, newest first.
	 *
	 * Columns returned: id, page, request_method, user_id, num_queries,
	 * peak_memory_bytes, elapsed_ms, inserted_at.
	 *
	 * @param int $per_page Maximum rows to return.
	 * @param int $offset   Number of rows to skip.
	 * @return array Array of associative-array rows.
	 */
	public function get_page($per_page = 10, $offset = 0) {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_telemetry';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, page, request_method, user_id, num_queries, peak_memory_bytes, elapsed_ms, inserted_at FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
				(int) $per_page,
				(int) $offset
			),
			ARRAY_A
		);
	}

	/**
	 * Count the total number of telemetry rows.
	 *
	 * @return int
	 */
	public function count() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_telemetry';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
	}

	/**
	 * Get the full JSON payload for a single telemetry row.
	 *
	 * @param int $id Row ID.
	 * @return array|null Decoded payload array, or null if not found.
	 */
	public function get_payload($id) {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_telemetry';
		$json  = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT payload FROM {$table} WHERE id = %d",
				(int) $id
			)
		);
		if ($json === null) {
			return null;
		}
		return json_decode($json, true);
	}
}
