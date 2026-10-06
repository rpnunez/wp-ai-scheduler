<?php
/**
 * Database Prune Repository
 *
 * Handles database operations for table status inspection, batch pruning,
 * orphaned record removal, and table optimization across plugin tables.
 *
 * @package AI_Post_Scheduler
 * @since   3.6.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_DB_Prune_Repository
 */
class AIPS_DB_Prune_Repository {

	/**
	 * @var AIPS_DB_Prune_Repository|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var wpdb WordPress database adapter.
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
	 * Get the singleton instance.
	 *
	 * @return AIPS_DB_Prune_Repository
	 */
	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Retrieve disk space, row count, and overhead statistics for all plugin tables.
	 *
	 * @return array<int, array<string, mixed>> Table status records.
	 */
	public function get_tables_status() {
		$prefix_escaped = $this->wpdb->esc_like($this->wpdb->prefix . 'aips_') . '%';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT TABLE_NAME AS `table_name`,
				        TABLE_ROWS AS `table_rows`,
				        DATA_LENGTH AS `data_length`,
				        INDEX_LENGTH AS `index_length`,
				        DATA_FREE AS `data_free`,
				        ENGINE AS `engine`
				 FROM information_schema.TABLES
				 WHERE TABLE_SCHEMA = DATABASE()
				   AND TABLE_NAME LIKE %s
				 ORDER BY DATA_LENGTH DESC, TABLE_NAME ASC",
				$prefix_escaped
			),
			ARRAY_A
		);

		if (empty($rows)) {
			// Fallback to SHOW TABLE STATUS if information_schema query returns empty.
			$prefix_pattern = $this->wpdb->prefix . 'aips_%';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$status_rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SHOW TABLE STATUS LIKE %s",
					$prefix_pattern
				),
				ARRAY_A
			);

			if (is_array($status_rows)) {
				$rows = array();
				foreach ($status_rows as $s_row) {
					$rows[] = array(
						'table_name'   => isset($s_row['Name']) ? $s_row['Name'] : '',
						'table_rows'   => isset($s_row['Rows']) ? (int) $s_row['Rows'] : 0,
						'data_length'  => isset($s_row['Data_length']) ? (int) $s_row['Data_length'] : 0,
						'index_length' => isset($s_row['Index_length']) ? (int) $s_row['Index_length'] : 0,
						'data_free'    => isset($s_row['Data_free']) ? (int) $s_row['Data_free'] : 0,
						'engine'       => isset($s_row['Engine']) ? $s_row['Engine'] : 'InnoDB',
					);
				}
			}
		}

		$results = array();
		if (!is_array($rows)) {
			return $results;
		}

		$wp_prefix = $this->wpdb->prefix;
		foreach ($rows as $row) {
			$full_name  = (string) $row['table_name'];
			$short_name = str_starts_with($full_name, $wp_prefix) ? substr($full_name, strlen($wp_prefix)) : $full_name;
			$data_len   = isset($row['data_length']) ? (int) $row['data_length'] : 0;
			$idx_len    = isset($row['index_length']) ? (int) $row['index_length'] : 0;
			$overhead   = isset($row['data_free']) ? (int) $row['data_free'] : 0;
			$records    = isset($row['table_rows']) ? (int) $row['table_rows'] : 0;

			$results[] = array(
				'table'                => $full_name,
				'short_name'           => $short_name,
				'records'              => $records,
				'data_size'            => $data_len,
				'index_size'           => $idx_len,
				'total_size'           => $data_len + $idx_len,
				'overhead'             => $overhead,
				'type'                 => !empty($row['engine']) ? (string) $row['engine'] : 'InnoDB',
				'formatted_records'    => number_format_i18n($records),
				'formatted_data_size'  => size_format($data_len, 2),
				'formatted_index_size' => size_format($idx_len, 2),
				'formatted_total_size' => size_format($data_len + $idx_len, 2),
				'formatted_overhead'   => size_format($overhead, 2),
			);
		}

		return $results;
	}

	/**
	 * Verify whether a table name is a valid, whitelisted plugin table.
	 *
	 * @param string $table_name Full table name or short name.
	 * @return string|false Sanitized full table name on success, or false if not allowed.
	 */
	public function validate_plugin_table($table_name) {
		$table_name = sanitize_text_field($table_name);
		$full_names = AIPS_DB_Manager::get_full_table_names();

		if (in_array($table_name, $full_names, true)) {
			return $table_name;
		}

		// Check if passed short name
		$full_from_short = $this->wpdb->prefix . $table_name;
		if (in_array($full_from_short, $full_names, true)) {
			return $full_from_short;
		}

		return false;
	}

	/**
	 * Run OPTIMIZE TABLE on a specific whitelisted plugin table.
	 *
	 * @param string $table_name Table name to optimize.
	 * @return bool True on success, false on failure or disallowed table.
	 */
	public function optimize_table($table_name) {
		$valid_table = $this->validate_plugin_table($table_name);
		if (!$valid_table) {
			return false;
		}

		$escaped_table = str_replace('`', '``', $valid_table);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->wpdb->query("OPTIMIZE TABLE `{$escaped_table}`");

		return false !== $result;
	}

	/**
	 * Run TRUNCATE TABLE on a specific whitelisted plugin table.
	 *
	 * @param string $table_name Table name to truncate.
	 * @return bool True on success, false on failure or disallowed table.
	 */
	public function truncate_table($table_name) {
		$valid_table = $this->validate_plugin_table($table_name);
		if (!$valid_table) {
			return false;
		}

		$escaped_table = str_replace('`', '``', $valid_table);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->wpdb->query("TRUNCATE TABLE `{$escaped_table}`");

		return false !== $result;
	}

	/**
	 * Prune old generation event logs from aips_history_log.
	 *
	 * @param int $cutoff_timestamp Unix timestamp threshold.
	 * @param int $batch_size       Maximum rows to delete per iteration.
	 * @return int Total rows deleted.
	 */
	public function prune_history_logs($cutoff_timestamp, $batch_size = 1000) {
		$table            = $this->wpdb->prefix . 'aips_history_log';
		$cutoff_timestamp = (int) $cutoff_timestamp;
		$batch_size       = max(1, min(5000, (int) $batch_size));
		$total_deleted    = 0;

		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $this->wpdb->query(
				$this->wpdb->prepare(
					"DELETE FROM `{$table}` WHERE `timestamp` < %d LIMIT %d",
					$cutoff_timestamp,
					$batch_size
				)
			);

			if (false === $deleted || 0 === (int) $deleted) {
				break;
			}

			$total_deleted += (int) $deleted;
		} while ((int) $deleted === $batch_size);

		return $total_deleted;
	}

	/**
	 * Purge embedding vectors whose associated posts no longer exist.
	 *
	 * @param int $batch_size Maximum rows to delete per iteration.
	 * @return int Total orphaned rows deleted.
	 */
	public function clean_orphaned_embeddings($batch_size = 1000) {
		$table_embeddings = $this->wpdb->prefix . 'aips_embeddings';
		$table_posts      = $this->wpdb->posts;
		$batch_size       = max(1, min(5000, (int) $batch_size));
		$total_deleted    = 0;

		do {
			// Find up to $batch_size orphaned IDs first to keep the delete query fast and indexed.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$orphaned_ids = $this->wpdb->get_col(
				$this->wpdb->prepare(
					"SELECT e.id FROM `{$table_embeddings}` e
					 LEFT JOIN `{$table_posts}` p ON e.object_id = p.ID
					 WHERE e.object_type = 'post' AND p.ID IS NULL
					 LIMIT %d",
					$batch_size
				)
			);

			if (empty($orphaned_ids)) {
				break;
			}

			$ids_in = implode(',', array_map('intval', $orphaned_ids));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $this->wpdb->query("DELETE FROM `{$table_embeddings}` WHERE id IN ({$ids_in})");

			if (false === $deleted || 0 === (int) $deleted) {
				break;
			}

			$total_deleted += (int) $deleted;
		} while (count($orphaned_ids) === $batch_size);

		return $total_deleted;
	}

	/**
	 * Purge rejected or expired author topics older than the cutoff timestamp.
	 *
	 * @param int $cutoff_timestamp Unix timestamp threshold.
	 * @param int $batch_size       Maximum rows to delete per iteration.
	 * @return int Total rows deleted.
	 */
	public function clean_expired_topics($cutoff_timestamp, $batch_size = 1000) {
		$table            = $this->wpdb->prefix . 'aips_author_topics';
		$cutoff_timestamp = (int) $cutoff_timestamp;
		$batch_size       = max(1, min(5000, (int) $batch_size));
		$total_deleted    = 0;

		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $this->wpdb->query(
				$this->wpdb->prepare(
					"DELETE FROM `{$table}` WHERE status IN ('rejected', 'expired') AND generated_at < %d LIMIT %d",
					$cutoff_timestamp,
					$batch_size
				)
			);

			if (false === $deleted || 0 === (int) $deleted) {
				break;
			}

			$total_deleted += (int) $deleted;
		} while ((int) $deleted === $batch_size);

		return $total_deleted;
	}
}
