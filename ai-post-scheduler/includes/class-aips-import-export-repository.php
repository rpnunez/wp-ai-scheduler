<?php
/**
 * Import & Export Repository
 *
 * Database abstraction layer for starter presets, selective export queries,
 * and selective import persistence with conflict handling.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Import_Export_Repository {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Whitelist of supported entity types to their table base names.
	 *
	 * @var array<string, string>
	 */
	private $table_map = array(
		'voices'             => 'aips_voices',
		'prompt_sections'    => 'aips_prompt_sections',
		'article_structures' => 'aips_article_structures',
		'authors'            => 'aips_authors',
		'author_topics'      => 'aips_author_topics',
		'templates'          => 'aips_templates',
		'schedules'          => 'aips_schedule',
		'post_slices'        => 'aips_post_slices',
		'sources'            => 'aips_sources',
		'sources_data'       => 'aips_sources_data',
		'history'            => 'aips_history',
	);

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $wpdb Optional WordPress database instance.
	 */
	public function __construct($wpdb = null) {
		global $wpdb;
		$this->wpdb = $wpdb ?: $GLOBALS['wpdb'];
	}

	/**
	 * Get full table name with WordPress table prefix.
	 *
	 * @param string $type Entity type key.
	 * @return string|null
	 */
	public function get_table_name(string $type): ?string {
		if (!isset($this->table_map[$type])) {
			return null;
		}
		return $this->wpdb->prefix . $this->table_map[$type];
	}

	/**
	 * Check if an entity table exists in the current database.
	 *
	 * @param string $type Entity type key.
	 * @return bool
	 */
	public function table_exists(string $type): bool {
		$table = $this->get_table_name($type);
		if (!$table) {
			return false;
		}
		return $this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
	}

	/**
	 * Calculate preset usage stats (number of posts generated and schedules executed).
	 *
	 * @param array<int> $template_ids
	 * @param array<int> $author_ids
	 * @param array<int> $schedule_ids
	 * @return array{post_count: int, schedules_run: int}
	 */
	public function get_preset_usage_stats(array $template_ids, array $author_ids, array $schedule_ids): array {
		$stats = array(
			'post_count'    => 0,
			'schedules_run' => 0,
		);

		$clean_templates = array_filter(array_map('absint', $template_ids));
		$clean_authors   = array_filter(array_map('absint', $author_ids));
		$clean_schedules = array_filter(array_map('absint', $schedule_ids));

		// Check active run history or generated posts in aips_history
		if ($this->table_exists('history')) {
			$history_table = $this->get_table_name('history');
			$where_clauses = array();

			if (!empty($clean_templates)) {
				$where_clauses[] = 'template_id IN (' . implode(',', $clean_templates) . ')';
			}
			if (!empty($clean_authors)) {
				$where_clauses[] = 'author_id IN (' . implode(',', $clean_authors) . ')';
			}
			if (!empty($clean_schedules)) {
				$where_clauses[] = 'schedule_id IN (' . implode(',', $clean_schedules) . ')';
			}

			if (!empty($where_clauses)) {
				$where = implode(' OR ', $where_clauses);
				$stats['post_count'] = (int) $this->wpdb->get_var(
					"SELECT COUNT(*) FROM $history_table WHERE ($where) AND post_id IS NOT NULL AND post_id > 0"
				);
			}
		}

		// Check if any preset schedules have run
		if (!empty($clean_schedules) && $this->table_exists('schedules')) {
			$schedule_table = $this->get_table_name('schedules');
			$in_schedules   = implode(',', $clean_schedules);
			$stats['schedules_run'] = (int) $this->wpdb->get_var(
				"SELECT COUNT(*) FROM $schedule_table WHERE id IN ($in_schedules) AND last_run > 0"
			);
		}

		return $stats;
	}

	/**
	 * Export all rows for a given entity type.
	 *
	 * @param string $type     Entity type key.
	 * @param int    $limit    Optional limit (0 for unlimited).
	 * @param string $order_by Order by clause.
	 * @return array<array<string, mixed>>
	 */
	public function export_table_rows(string $type, int $limit = 0, string $order_by = 'id ASC'): array {
		if (!$this->table_exists($type)) {
			return array();
		}

		$table = $this->get_table_name($type);
		$sql   = "SELECT * FROM $table";

		if (!empty($order_by)) {
			// Basic sanitization of order by clause
			$safe_order = preg_match('/^[a-zA-Z0-9_]+ (ASC|DESC)$/i', $order_by) ? $order_by : 'id ASC';
			$sql .= " ORDER BY $safe_order";
		}

		if ($limit > 0) {
			$sql .= " LIMIT " . absint($limit);
		}

		$rows = $this->wpdb->get_results($sql, ARRAY_A);
		return is_array($rows) ? $rows : array();
	}

	/**
	 * Export specific rows by ID for a given entity type.
	 *
	 * @param string     $type Entity type key.
	 * @param array<int> $ids  Row IDs.
	 * @return array<array<string, mixed>>
	 */
	public function export_rows_by_ids(string $type, array $ids): array {
		if (empty($ids) || !$this->table_exists($type)) {
			return array();
		}

		$clean_ids = array_filter(array_map('absint', $ids));
		if (empty($clean_ids)) {
			return array();
		}

		$table = $this->get_table_name($type);
		$in    = implode(',', $clean_ids);
		$rows  = $this->wpdb->get_results("SELECT * FROM $table WHERE id IN ($in)", ARRAY_A);

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Export prompt sections by section key strings.
	 *
	 * @param array<string> $keys Section keys.
	 * @return array<array<string, mixed>>
	 */
	public function export_sections_by_keys(array $keys): array {
		if (empty($keys) || !$this->table_exists('prompt_sections')) {
			return array();
		}

		$table   = $this->get_table_name('prompt_sections');
		$escaped = array();
		foreach ($keys as $k) {
			$escaped[] = $this->wpdb->prepare('%s', sanitize_key($k));
		}

		if (empty($escaped)) {
			return array();
		}

		$in   = implode(',', $escaped);
		$rows = $this->wpdb->get_results("SELECT * FROM $table WHERE section_key IN ($in)", ARRAY_A);

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Find an existing record by a unique field (e.g. name, section_key, url).
	 *
	 * @param string $type  Entity type key.
	 * @param string $field Column name.
	 * @param string $value Match value.
	 * @return object|null
	 */
	public function find_existing(string $type, string $field, string $value): ?object {
		if (!$this->table_exists($type)) {
			return null;
		}

		// Whitelist permitted lookup fields
		$allowed_fields = array('id', 'name', 'section_key', 'url', 'slug');
		if (!in_array($field, $allowed_fields, true)) {
			return null;
		}

		$table = $this->get_table_name($type);
		$row   = $this->wpdb->get_row(
			$this->wpdb->prepare("SELECT * FROM $table WHERE $field = %s LIMIT 1", $value)
		);

		return is_object($row) ? $row : null;
	}

	/**
	 * Insert a new entity record.
	 *
	 * @param string               $type Entity type key.
	 * @param array<string, mixed> $data Row data.
	 * @return int|false New auto-increment ID, or false on error.
	 */
	public function insert_row(string $type, array $data) {
		$table = $this->get_table_name($type);
		if (!$table) {
			return false;
		}

		$inserted = $this->wpdb->insert($table, $data);
		if (false === $inserted) {
			return false;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update an existing entity record by ID.
	 *
	 * @param string               $type Entity type key.
	 * @param int                  $id   Row ID.
	 * @param array<string, mixed> $data Updated data.
	 * @return bool True on success, false on failure.
	 */
	public function update_row(string $type, int $id, array $data): bool {
		$table = $this->get_table_name($type);
		if (!$table || $id <= 0) {
			return false;
		}

		$result = $this->wpdb->update($table, $data, array('id' => $id));
		return false !== $result;
	}
}
