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
	 * Transient key used to briefly cache the full table-status listing.
	 */
	const TABLES_STATUS_CACHE_KEY = 'aips_db_prune_tables_status';

	/**
	 * Retrieve disk space, row count, and overhead statistics for all plugin tables.
	 *
	 * Cached for a few seconds since this reads information_schema and is polled
	 * from the System Status page alongside destructive maintenance actions.
	 *
	 * @param bool $use_cache Whether to use/populate the short-lived cache. Pass
	 *                        false to force a fresh read (e.g. right after a
	 *                        mutating action so the UI reflects the new state).
	 * @return array<int, array<string, mixed>> Table status records.
	 */
	public function get_tables_status($use_cache = true) {
		if ($use_cache) {
			$cached = get_transient(self::TABLES_STATUS_CACHE_KEY);
			if (is_array($cached)) {
				return $cached;
			}
		}

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

		foreach ($rows as $row) {
			$results[] = $this->format_table_status_row($row);
		}

		if ($use_cache) {
			set_transient(self::TABLES_STATUS_CACHE_KEY, $results, 5);
		}

		return $results;
	}

	/**
	 * Retrieve disk space, row count, and overhead statistics for a single whitelisted plugin table.
	 *
	 * Always reads fresh (bypasses the listing cache) so callers can report
	 * up-to-date status immediately after a mutating operation.
	 *
	 * @param string $table_name Full or short table name.
	 * @return array<string, mixed>|null Table status record, or null if the table is not a valid plugin table.
	 */
	public function get_table_status($table_name) {
		$valid_table = $this->validate_plugin_table($table_name);
		if (!$valid_table) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT TABLE_NAME AS `table_name`,
				        TABLE_ROWS AS `table_rows`,
				        DATA_LENGTH AS `data_length`,
				        INDEX_LENGTH AS `index_length`,
				        DATA_FREE AS `data_free`,
				        ENGINE AS `engine`
				 FROM information_schema.TABLES
				 WHERE TABLE_SCHEMA = DATABASE()
				   AND TABLE_NAME = %s",
				$valid_table
			),
			ARRAY_A
		);

		if (!is_array($row)) {
			return null;
		}

		return $this->format_table_status_row($row);
	}

	/**
	 * Shape a raw information_schema/SHOW TABLE STATUS row into the public table-status format.
	 *
	 * @param array<string, mixed> $row Raw row with table_name/table_rows/data_length/index_length/data_free/engine keys.
	 * @return array<string, mixed>
	 */
	private function format_table_status_row(array $row) {
		$wp_prefix  = $this->wpdb->prefix;
		$full_name  = (string) $row['table_name'];
		$short_name = str_starts_with($full_name, $wp_prefix) ? substr($full_name, strlen($wp_prefix)) : $full_name;
		$data_len   = isset($row['data_length']) ? (int) $row['data_length'] : 0;
		$idx_len    = isset($row['index_length']) ? (int) $row['index_length'] : 0;
		$overhead   = isset($row['data_free']) ? (int) $row['data_free'] : 0;
		$records    = isset($row['table_rows']) ? (int) $row['table_rows'] : 0;

		return array(
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

	/**
	 * Clear the short-lived table-status listing cache.
	 *
	 * Called after any operation that changes row counts or table size so the
	 * next status read (cached or not) reflects current state.
	 *
	 * @return void
	 */
	public static function invalidate_tables_status_cache() {
		delete_transient(self::TABLES_STATUS_CACHE_KEY);
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

		if (false === $result) {
			$this->log_db_error('optimize_table: OPTIMIZE TABLE failed', array('table' => $valid_table));
		}

		self::invalidate_tables_status_cache();

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

		if (false === $result) {
			$this->log_db_error('truncate_table: TRUNCATE TABLE failed', array('table' => $valid_table));
		}

		self::invalidate_tables_status_cache();

		return false !== $result;
	}

	/**
	 * Log a database operation failure, including the last wpdb error.
	 *
	 * @param string $message Log message describing the failed operation.
	 * @param array  $context Additional context to attach to the log entry.
	 * @return void
	 */
	private function log_db_error($message, array $context = array()) {
		$context['db_error'] = $this->wpdb->last_error;
		(new AIPS_Logger())->error($message, $context);
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

			if (false === $deleted) {
				$this->log_db_error('prune_history_logs: failed to delete history log batch', array(
					'table' => $table,
				));
				break;
			}

			if (0 === (int) $deleted) {
				break;
			}

			$total_deleted += (int) $deleted;
		} while ((int) $deleted === $batch_size);

		if ($total_deleted > 0) {
			self::invalidate_tables_status_cache();
		}

		return $total_deleted;
	}

	/**
	 * Purge embedding vectors whose associated posts no longer exist, and clean related graph entries.
	 *
	 * @param int $batch_size Maximum rows to delete per iteration.
	 * @return int Total orphaned rows deleted.
	 */
	public function clean_orphaned_embeddings($batch_size = 1000) {
		$table_embeddings = $this->wpdb->prefix . 'aips_embeddings';
		// Check if embeddings table exists.
		if ($this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table_embeddings)) !== $table_embeddings) {
			return 0;
		}

		$table_relationships = $this->wpdb->prefix . 'aips_relationships';
		$has_relationships   = ($this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table_relationships)) === $table_relationships);
		$table_posts         = $this->wpdb->posts;
		$batch_size          = max(1, min(5000, (int) $batch_size));
		$total_deleted       = 0;

		do {
			// Find up to $batch_size orphaned rows first to obtain both embedding ID and post object_id.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$orphaned_rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT e.id, e.object_id FROM `{$table_embeddings}` e
					 LEFT JOIN `{$table_posts}` p ON e.object_id = p.ID
					 WHERE e.object_type = 'post' AND p.ID IS NULL
					 LIMIT %d",
					$batch_size
				),
				ARRAY_A
			);

			if (empty($orphaned_rows)) {
				break;
			}

			$orphaned_ids = array();
			$post_ids     = array();
			foreach ($orphaned_rows as $row) {
				$orphaned_ids[] = (int) $row['id'];
				if (!empty($row['object_id'])) {
					$post_ids[] = (int) $row['object_id'];
				}
			}

			$this->wpdb->query('START TRANSACTION');

			// Cascade cleanup: remove orphaned post entries from relationships table if present.
			$relationships_failed = false;
			if ($has_relationships && !empty($post_ids)) {
				$post_ids_in = implode(',', array_unique($post_ids));
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$relationships_result = $this->wpdb->query(
					"DELETE FROM `{$table_relationships}`
					 WHERE (source_type = 'post' AND source_id IN ({$post_ids_in}))
					    OR (target_type = 'post' AND target_id IN ({$post_ids_in}))"
				);

				if (false === $relationships_result) {
					$relationships_failed = true;
					$this->log_db_error('clean_orphaned_embeddings: failed to delete orphaned relationships', array(
						'table' => $table_relationships,
					));
				}
			}

			if ($relationships_failed) {
				$this->wpdb->query('ROLLBACK');
				break;
			}

			$ids_in = implode(',', $orphaned_ids);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $this->wpdb->query("DELETE FROM `{$table_embeddings}` WHERE id IN ({$ids_in})");

			if (false === $deleted) {
				$this->log_db_error('clean_orphaned_embeddings: failed to delete orphaned embeddings', array(
					'table' => $table_embeddings,
				));
				$this->wpdb->query('ROLLBACK');
				break;
			}

			$this->wpdb->query('COMMIT');

			if (0 === (int) $deleted) {
				break;
			}

			$total_deleted += (int) $deleted;
		} while (count($orphaned_rows) === $batch_size);

		if ($total_deleted > 0) {
			self::invalidate_tables_status_cache();
		}

		return $total_deleted;
	}

	/**
	 * Purge rejected or expired author topics older than the cutoff timestamp,
	 * cascading deletions to dependent author topic logs.
	 *
	 * @param int $cutoff_timestamp Unix timestamp threshold.
	 * @param int $batch_size       Maximum rows to delete per iteration.
	 * @return int Total rows deleted.
	 */
	public function clean_expired_topics($cutoff_timestamp, $batch_size = 1000) {
		$table_topics     = $this->wpdb->prefix . 'aips_author_topics';
		$table_topic_logs = $this->wpdb->prefix . 'aips_author_topic_logs';
		$cutoff_timestamp = (int) $cutoff_timestamp;
		$batch_size       = max(1, min(5000, (int) $batch_size));
		$total_deleted    = 0;

		do {
			// Fetch up to $batch_size expired topic IDs to delete.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$topic_ids = $this->wpdb->get_col(
				$this->wpdb->prepare(
					"SELECT id FROM `{$table_topics}`
					 WHERE status IN ('rejected', 'expired') AND generated_at < %d
					 LIMIT %d",
					$cutoff_timestamp,
					$batch_size
				)
			);

			if (empty($topic_ids)) {
				break;
			}

			$ids_in = implode(',', array_map('intval', $topic_ids));

			$this->wpdb->query('START TRANSACTION');

			// Cascade cleanup: delete dependent logs in author topic logs first.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$logs_result = $this->wpdb->query("DELETE FROM `{$table_topic_logs}` WHERE author_topic_id IN ({$ids_in})");

			if (false === $logs_result) {
				$this->log_db_error('clean_expired_topics: failed to delete dependent author topic logs', array(
					'table' => $table_topic_logs,
				));
				$this->wpdb->query('ROLLBACK');
				break;
			}

			// Delete the parent topic rows.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $this->wpdb->query("DELETE FROM `{$table_topics}` WHERE id IN ({$ids_in})");

			if (false === $deleted) {
				$this->log_db_error('clean_expired_topics: failed to delete expired topics', array(
					'table' => $table_topics,
				));
				$this->wpdb->query('ROLLBACK');
				break;
			}

			$this->wpdb->query('COMMIT');

			if (0 === (int) $deleted) {
				break;
			}

			$total_deleted += (int) $deleted;
		} while (count($topic_ids) === $batch_size);

		if ($total_deleted > 0) {
			self::invalidate_tables_status_cache();
		}

		return $total_deleted;
	}
}
