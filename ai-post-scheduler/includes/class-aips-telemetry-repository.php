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
	 * @var wpdb WordPress database adapter.
	 */
	private $wpdb;

	/**
	 * @var string Fully qualified telemetry table name.
	 */
	private $table;

	/**
	 * Initialize repository dependencies.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_telemetry';
	}

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

		$inserted = $this->wpdb->insert(
			$this->table,
			$normalized_data,
			$formats
		);
		if ($inserted === false) {
			return false;
		}
		return (int) $this->wpdb->insert_id;
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, page, request_method, user_id, num_queries, peak_memory_bytes, elapsed_ms, inserted_at FROM {$this->table} ORDER BY id DESC LIMIT %d OFFSET %d",
				(int) $per_page,
				(int) $offset
			),
			ARRAY_A
		);
	}

	/**
	 * Retrieve a filtered page of telemetry rows, newest first.
	 *
	 * @param string $start_date Inclusive start date in Y-m-d format.
	 * @param string $end_date   Inclusive end date in Y-m-d format.
	 * @param int    $per_page   Maximum rows to return.
	 * @param int    $offset     Number of rows to skip.
	 * @return array Array of associative-array rows.
	 */
	public function get_filtered_page($start_date, $end_date, $per_page = 25, $offset = 0) {
		$start_datetime  = $start_date . ' 00:00:00';
		$end_datetime    = date('Y-m-d 00:00:00', strtotime($end_date . ' +1 day'));

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, page, request_method, user_id, num_queries, peak_memory_bytes, elapsed_ms, inserted_at FROM {$this->table} WHERE inserted_at >= %s AND inserted_at < %s ORDER BY id DESC LIMIT %d OFFSET %d",
				$start_datetime,
				$end_datetime,
				(int) $per_page,
				(int) $offset
			),
			ARRAY_A
		);
	}

	/**
	 * Retrieve a single telemetry row by ID.
	 *
	 * Returns the full persisted row including the raw payload column.
	 *
	 * @param int $id Row ID.
	 * @return array|null Full row data, or null when no row exists.
	 */
	public function get_row($id) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, page, request_method, user_id, num_queries, peak_memory_bytes, elapsed_ms, payload, inserted_at FROM {$this->table} WHERE id = %d LIMIT 1",
				(int) $id
			),
			ARRAY_A
		);

		return is_array($row) ? $row : null;
	}

	/**
	 * Count the total number of telemetry rows.
	 *
	 * @return int
	 */
	public function count() {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
	}

	/**
	 * Count telemetry rows within an inclusive date range.
	 *
	 * @param string $start_date Inclusive start date in Y-m-d format.
	 * @param string $end_date   Inclusive end date in Y-m-d format.
	 * @return int
	 */
	public function count_filtered($start_date, $end_date) {
		$start_datetime = $start_date . ' 00:00:00';
		$end_datetime   = date('Y-m-d 00:00:00', strtotime($end_date . ' +1 day'));

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->table} WHERE inserted_at >= %s AND inserted_at < %s",
				$start_datetime,
				$end_datetime
			)
		);
	}

	/**
	 * Return daily telemetry aggregates for charting.
	 *
	 * @param string $start_date Inclusive start date in Y-m-d format.
	 * @param string $end_date   Inclusive end date in Y-m-d format.
	 * @return array<int, array<string, string|int|float>>
	 */
	public function get_daily_rollup($start_date, $end_date) {
		$start_datetime = $start_date . ' 00:00:00';
		$end_datetime   = date('Y-m-d 00:00:00', strtotime($end_date . ' +1 day'));

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT DATE(inserted_at) AS metric_date, COUNT(*) AS request_count, SUM(num_queries) AS total_queries, MAX(peak_memory_bytes) AS peak_memory_bytes_max, AVG(elapsed_ms) AS avg_elapsed_ms FROM {$this->table} WHERE inserted_at >= %s AND inserted_at < %s GROUP BY DATE(inserted_at) ORDER BY metric_date ASC",
				$start_datetime,
				$end_datetime
			),
			ARRAY_A
		);
	}

	/**
	 * Get the full JSON payload for a single telemetry row.
	 *
	 * @param int $id Row ID.
	 * @return array|null Decoded payload array, or null if not found.
	 */
	public function get_payload($id) {
		$json = $this->wpdb->get_var(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT payload FROM {$this->table} WHERE id = %d",
				(int) $id
			)
		);
		if ($json === null) {
			return null;
		}
		return json_decode($json, true);
	}
}
