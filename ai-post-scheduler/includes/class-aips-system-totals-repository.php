<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_System_Totals_Repository
 *
 * Read-only aggregate counts across the plugin's tables, used by the
 * "Totals & Counts" section of Diagnostics → System Info. Related counts are
 * grouped into a handful of GROUP BY queries rather than one query per figure,
 * and the whole result is cached briefly because some tables (history logs,
 * embeddings) can grow large.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */
class AIPS_System_Totals_Repository {
	use AIPS_Cacheable_Repository;

	/**
	 * Cache operation id for the full totals payload.
	 */
	const CACHE_OPERATION = 'system_totals.get_totals';

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var string Table prefix including the WordPress prefix (e.g. "wp_aips_").
	 */
	private $prefix;

	public function __construct() {
		global $wpdb;

		$this->wpdb   = $wpdb;
		$this->prefix = $wpdb->prefix . 'aips_';
	}

	/**
	 * Get every total, grouped by area.
	 *
	 * @param bool $bypass_cache Skip the cache and query the database directly.
	 * @return array<string, mixed>
	 */
	public function get_totals($bypass_cache = false) {
		$options = $bypass_cache ? array('bypass_cache' => true) : array();

		return $this->cache_read(
			self::CACHE_OPERATION,
			array(),
			function () {
				return $this->query_totals();
			},
			$options
		);
	}

	/**
	 * Run all aggregate queries.
	 *
	 * @return array<string, mixed>
	 */
	private function query_totals() {
		$history = $this->get_history_totals();

		return array(
			'generated_posts'      => $this->count_generated_posts(),
			'history'              => $history,
			'author_posts'         => $this->count_author_posts(),
			'authors'              => $this->count_with_active('authors'),
			'author_topics'        => $this->count_grouped('author_topics', 'status'),
			'ai_requests'          => $this->count_ai_requests(),
			'ai_assistance'        => $this->count_rows('ai_assistance'),
			'background_ai_calls'  => $this->sum_column('background_processes', 'ai_calls_used'),
			'embeddings'           => $this->count_grouped('embeddings', 'object_type'),
			'relationships'        => $this->count_rows('relationships'),
			'internal_links'       => $this->count_grouped('internal_links', 'status'),
			'link_index'           => $this->get_link_index_totals(),
			'templates'            => $this->count_with_active('templates'),
			'schedules'            => $this->count_with_active('schedule'),
			'campaigns'            => $this->count_with_active('campaigns'),
			'trending_topics'      => $this->count_rows('trending_topics'),
			'sources'              => $this->count_rows('sources'),
			'source_pages'         => $this->get_source_page_totals(),
			'bulk_jobs'            => $this->get_bulk_job_totals(),
		);
	}

	/**
	 * Count posts that still exist and carry the generated-post marker.
	 *
	 * @return int
	 */
	private function count_generated_posts() {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			 FROM {$this->wpdb->posts} p
			 INNER JOIN {$this->wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
			 WHERE p.post_status NOT IN ('trash', 'auto-draft')",
			AIPS_Post_Manager::META_GENERATED_POST
		);

		return (int) $this->wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * History container totals: per record kind, generation outcomes and
	 * generation creation methods.
	 *
	 * @return array{total:int, by_kind:array<string,int>, generation_by_status:array<string,int>, generation_by_method:array<string,int>}
	 */
	private function get_history_totals() {
		$table = $this->table('history');

		$rows = $this->wpdb->get_results(
			"SELECT record_kind, status, COUNT(*) AS total FROM {$table} GROUP BY record_kind, status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$total     = 0;
		$by_kind   = array();
		$by_status = array();

		foreach ((array) $rows as $row) {
			$kind   = (string) $row['record_kind'];
			$status = (string) $row['status'];
			$count  = (int) $row['total'];

			$total += $count;
			$by_kind[$kind] = (isset($by_kind[$kind]) ? $by_kind[$kind] : 0) + $count;

			if ($kind === AIPS_History_Record_Kind::GENERATION) {
				$by_status[$status] = (isset($by_status[$status]) ? $by_status[$status] : 0) + $count;
			}
		}

		$method_rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT creation_method, COUNT(*) AS total FROM {$table} WHERE record_kind = %s GROUP BY creation_method", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				AIPS_History_Record_Kind::GENERATION
			),
			ARRAY_A
		);

		$by_method = array();
		foreach ((array) $method_rows as $row) {
			$method = (string) $row['creation_method'];
			$method = $method === '' ? 'unknown' : $method;
			$by_method[$method] = (isset($by_method[$method]) ? $by_method[$method] : 0) + (int) $row['total'];
		}

		arsort($by_kind);
		arsort($by_status);
		arsort($by_method);

		return array(
			'total'                => $total,
			'by_kind'              => $by_kind,
			'generation_by_status' => $by_status,
			'generation_by_method' => $by_method,
		);
	}

	/**
	 * Count completed generations attached to an author that produced a post.
	 *
	 * @return int
	 */
	private function count_author_posts() {
		$table = $this->table('history');

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE record_kind = %s AND status IN ('completed', 'partial') AND author_id > 0 AND post_id > 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				AIPS_History_Record_Kind::GENERATION
			)
		);
	}

	/**
	 * Count AI request log entries (all-time, across every provider).
	 *
	 * @return int
	 */
	private function count_ai_requests() {
		$table = $this->table('history_log');

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE history_type_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				AIPS_History_Type::AI_REQUEST
			)
		);
	}

	/**
	 * Link index totals by link type plus links the plugin inserted.
	 *
	 * @return array{total:int, by_type:array<string,int>, inserted_by_aips:int}
	 */
	private function get_link_index_totals() {
		$grouped = $this->count_grouped('link_index', 'link_type');

		return array(
			'total'            => $grouped['total'],
			'by_type'          => $grouped['groups'],
			'inserted_by_aips' => $this->sum_column('link_index', 'inserted_by_aips'),
		);
	}

	/**
	 * Fetched source pages and how many times their content was used.
	 *
	 * @return array{total:int, by_status:array<string,int>, times_used:int}
	 */
	private function get_source_page_totals() {
		$grouped = $this->count_grouped('sources_data', 'fetch_status');

		return array(
			'total'      => $grouped['total'],
			'by_status'  => $grouped['groups'],
			'times_used' => $this->sum_column('sources_data', 'num_used'),
		);
	}

	/**
	 * Bulk batch job count and items processed.
	 *
	 * @return array{total:int, processed:int}
	 */
	private function get_bulk_job_totals() {
		return array(
			'total'     => $this->count_rows('bulk_batch_jobs'),
			'processed' => $this->sum_column('bulk_batch_jobs', 'processed'),
		);
	}

	/**
	 * Count all rows in a plugin table.
	 *
	 * @param string $short_table Table name without the "wp_aips_" prefix.
	 * @return int
	 */
	private function count_rows($short_table) {
		$table = $this->table($short_table);

		return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Count rows and active rows (is_active = 1) in a plugin table.
	 *
	 * @param string $short_table Table name without the "wp_aips_" prefix.
	 * @return array{total:int, active:int}
	 */
	private function count_with_active($short_table) {
		$table = $this->table($short_table);

		$row = $this->wpdb->get_row(
			"SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active FROM {$table}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return array(
			'total'  => isset($row['total']) ? (int) $row['total'] : 0,
			'active' => isset($row['active']) ? (int) $row['active'] : 0,
		);
	}

	/**
	 * Count rows grouped by one column.
	 *
	 * @param string $short_table Table name without the "wp_aips_" prefix.
	 * @param string $column      Column to group by (internal constant, never user input).
	 * @return array{total:int, groups:array<string,int>}
	 */
	private function count_grouped($short_table, $column) {
		$table  = $this->table($short_table);
		$column = preg_replace('/[^a-z_]/', '', (string) $column);

		$rows = $this->wpdb->get_results(
			"SELECT {$column} AS group_key, COUNT(*) AS total FROM {$table} GROUP BY {$column}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$total  = 0;
		$groups = array();

		foreach ((array) $rows as $row) {
			$key   = (string) $row['group_key'];
			$key   = $key === '' ? 'unknown' : $key;
			$count = (int) $row['total'];

			$total += $count;
			$groups[$key] = (isset($groups[$key]) ? $groups[$key] : 0) + $count;
		}

		arsort($groups);

		return array(
			'total'  => $total,
			'groups' => $groups,
		);
	}

	/**
	 * Sum one integer column of a plugin table.
	 *
	 * @param string $short_table Table name without the "wp_aips_" prefix.
	 * @param string $column      Column to sum (internal constant, never user input).
	 * @return int
	 */
	private function sum_column($short_table, $column) {
		$table  = $this->table($short_table);
		$column = preg_replace('/[^a-z_]/', '', (string) $column);

		return (int) $this->wpdb->get_var("SELECT COALESCE(SUM({$column}), 0) FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Full, backtick-quoted table name for a plugin table.
	 *
	 * @param string $short_table Table name without the "wp_aips_" prefix.
	 * @return string
	 */
	private function table($short_table) {
		$name = $this->prefix . preg_replace('/[^a-z_]/', '', (string) $short_table);

		return '`' . str_replace('`', '``', $name) . '`';
	}

	/**
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_system_totals';
	}

	/**
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			self::CACHE_OPERATION => array(
				'tier'        => 'short',
				'ttl'         => 10 * MINUTE_IN_SECONDS,
				'description' => 'Cache plugin-wide totals shown on Diagnostics → System Info.',
			),
		);
	}
}
