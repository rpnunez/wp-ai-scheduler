<?php
/**
 * Link Index Repository
 *
 * Persistence for the links that actually exist in post content: one row per
 * <a href> found in a source post. Powers inbound/outbound/external link
 * counts, true orphan detection (zero inbound internal links) and the Link
 * Report.
 *
 * Rows with link_type 'internal' and target_post_id 0 are internal URLs that
 * could not be resolved to a post (broken internal links).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Index_Repository
 *
 * Manages CRUD and aggregate queries for the aips_link_index table.
 */
class AIPS_Link_Index_Repository {

	/**
	 * Internal link type.
	 */
	const TYPE_INTERNAL = 'internal';

	/**
	 * External link type.
	 */
	const TYPE_EXTERNAL = 'external';

	/**
	 * Number of rows written per multi-row INSERT statement.
	 */
	const INSERT_CHUNK_SIZE = 100;

	/**
	 * Columns the Link Report may be ordered by, mapped to SQL expressions.
	 *
	 * @var array<string, string>
	 */
	private static $report_orderby = array(
		'inbound'  => 'inbound',
		'outbound' => 'outbound',
		'external' => 'external',
		'title'    => 'p.post_title',
		'date'     => 'p.post_date',
	);

	/**
	 * @var wpdb WordPress database object.
	 */
	private $wpdb;

	/**
	 * @var string Table name with prefix.
	 */
	private $table;

	/**
	 * Initialize repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_link_index';
	}

	/**
	 * Check if the link index table exists in the database.
	 *
	 * @return bool
	 */
	public function table_exists(): bool {
		static $exists = null;
		if ($exists === null) {
			$found  = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $this->table));
			$exists = ($found === $this->table);
		}
		return (bool) $exists;
	}

	/**
	 * Replace all indexed links for a source post.
	 *
	 * Each link is an array with keys: target_url (required), target_post_id,
	 * anchor_text, link_type ('internal'|'external'), rel, is_nofollow,
	 * inserted_by_aips, position.
	 *
	 * @param int   $source_post_id Source post ID.
	 * @param array $links          Links found in the source post content.
	 * @return int Number of rows written.
	 */
	public function sync_for_source(int $source_post_id, array $links): int {
		if (!$this->table_exists() || $source_post_id <= 0) {
			return 0;
		}

		$this->delete_for_source($source_post_id);

		$now  = AIPS_DateTime::now()->timestamp();
		$rows = array();

		foreach (array_values($links) as $index => $link) {
			$row = $this->normalize_link($link, $index);
			if ($row === null) {
				continue;
			}
			$rows[] = $row;
		}

		$written = 0;
		foreach (array_chunk($rows, self::INSERT_CHUNK_SIZE) as $chunk) {
			$placeholders = array();
			$values       = array();

			foreach ($chunk as $row) {
				$placeholders[] = '(%d, %d, %s, %s, %s, %s, %s, %d, %d, %d, %d)';
				array_push(
					$values,
					$source_post_id,
					$row['target_post_id'],
					$row['target_url'],
					$row['url_hash'],
					$row['anchor_text'],
					$row['link_type'],
					$row['rel'],
					$row['is_nofollow'],
					$row['inserted_by_aips'],
					$row['position'],
					$now
				);
			}

			$sql = "INSERT INTO {$this->table}
				(source_post_id, target_post_id, target_url, url_hash, anchor_text, link_type, rel, is_nofollow, inserted_by_aips, position, created_at)
				VALUES " . implode(', ', $placeholders);

			$result = $this->wpdb->query($this->wpdb->prepare($sql, $values));
			if ($result !== false) {
				$written += (int) $result;
			}
		}

		return $written;
	}

	/**
	 * Delete all indexed links whose source is the given post.
	 *
	 * @param int $source_post_id Source post ID.
	 * @return int|false Rows deleted, or false on failure.
	 */
	public function delete_for_source(int $source_post_id) {
		if (!$this->table_exists()) {
			return false;
		}

		return $this->wpdb->delete(
			$this->table,
			array('source_post_id' => absint($source_post_id)),
			array('%d')
		);
	}

	/**
	 * Detach links that point at a deleted post.
	 *
	 * The rows stay as internal links with target_post_id 0 so they surface as
	 * broken internal links in the source posts.
	 *
	 * @param int $target_post_id Deleted post ID.
	 * @return int|false Rows updated, or false on failure.
	 */
	public function clear_target(int $target_post_id) {
		if (!$this->table_exists() || $target_post_id <= 0) {
			return false;
		}

		return $this->wpdb->update(
			$this->table,
			array('target_post_id' => 0),
			array('target_post_id' => $target_post_id),
			array('%d'),
			array('%d')
		);
	}

	/**
	 * Delete every row in the link index.
	 *
	 * @return int|false Rows deleted, or false on failure.
	 */
	public function delete_all() {
		if (!$this->table_exists()) {
			return false;
		}

		return $this->wpdb->query("DELETE FROM {$this->table}");
	}

	/**
	 * Get links found in a source post.
	 *
	 * @param int         $source_post_id Source post ID.
	 * @param string|null $link_type      Optional 'internal' or 'external' filter.
	 * @return object[]
	 */
	public function get_outbound(int $source_post_id, ?string $link_type = null): array {
		if (!$this->table_exists()) {
			return array();
		}

		$sql  = "SELECT * FROM {$this->table} WHERE source_post_id = %d";
		$args = array($source_post_id);

		if ($link_type !== null && $this->is_valid_type($link_type)) {
			$sql   .= ' AND link_type = %s';
			$args[] = $link_type;
		}

		$sql .= ' ORDER BY position ASC, id ASC';

		return (array) $this->wpdb->get_results($this->wpdb->prepare($sql, $args));
	}

	/**
	 * Get internal links pointing at a target post (self-links excluded).
	 *
	 * @param int $target_post_id Target post ID.
	 * @return object[]
	 */
	public function get_inbound(int $target_post_id): array {
		if (!$this->table_exists() || $target_post_id <= 0) {
			return array();
		}

		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE target_post_id = %d AND link_type = %s AND source_post_id <> target_post_id
				ORDER BY source_post_id ASC, position ASC",
				$target_post_id,
				self::TYPE_INTERNAL
			)
		);
	}

	/**
	 * Get the distinct source post IDs that link to a target post.
	 *
	 * @param int $target_post_id Target post ID.
	 * @return int[]
	 */
	public function get_source_ids_linking_to(int $target_post_id): array {
		if (!$this->table_exists() || $target_post_id <= 0) {
			return array();
		}

		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT DISTINCT source_post_id FROM {$this->table}
				WHERE target_post_id = %d AND link_type = %s AND source_post_id <> target_post_id",
				$target_post_id,
				self::TYPE_INTERNAL
			)
		);

		return array_map('intval', (array) $ids);
	}

	/**
	 * Check whether a source post already links to a target post.
	 *
	 * @param int $source_post_id Source post ID.
	 * @param int $target_post_id Target post ID.
	 * @return bool
	 */
	public function source_links_to(int $source_post_id, int $target_post_id): bool {
		if (!$this->table_exists() || $source_post_id <= 0 || $target_post_id <= 0) {
			return false;
		}

		$found = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table}
				WHERE source_post_id = %d AND target_post_id = %d AND link_type = %s
				LIMIT 1",
				$source_post_id,
				$target_post_id,
				self::TYPE_INTERNAL
			)
		);

		return !empty($found);
	}

	/**
	 * Get inbound, outbound and external link counts for a set of posts.
	 *
	 * Inbound counts distinct linking source posts; outbound and external
	 * count individual links. Posts with no rows get zeros.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return array<int, array{inbound:int, outbound:int, external:int, broken:int}>
	 */
	public function get_counts_for_posts(array $post_ids): array {
		$post_ids = array_values(array_unique(array_filter(array_map('absint', $post_ids))));
		if (empty($post_ids)) {
			return array();
		}

		$counts = array();
		foreach ($post_ids as $id) {
			$counts[$id] = array(
				'inbound'  => 0,
				'outbound' => 0,
				'external' => 0,
				'broken'   => 0,
			);
		}

		if (!$this->table_exists()) {
			return $counts;
		}

		$in = implode(', ', array_fill(0, count($post_ids), '%d'));

		$outbound_rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT source_post_id AS post_id,
					SUM(CASE WHEN link_type = %s AND target_post_id > 0 THEN 1 ELSE 0 END) AS outbound,
					SUM(CASE WHEN link_type = %s THEN 1 ELSE 0 END) AS external,
					SUM(CASE WHEN link_type = %s AND target_post_id = 0 THEN 1 ELSE 0 END) AS broken
				FROM {$this->table}
				WHERE source_post_id IN ($in)
				GROUP BY source_post_id",
				array_merge(array(self::TYPE_INTERNAL, self::TYPE_EXTERNAL, self::TYPE_INTERNAL), $post_ids)
			)
		);

		foreach ((array) $outbound_rows as $row) {
			$id = (int) $row->post_id;
			$counts[$id]['outbound'] = (int) $row->outbound;
			$counts[$id]['external'] = (int) $row->external;
			$counts[$id]['broken']   = (int) $row->broken;
		}

		$inbound_rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT target_post_id AS post_id, COUNT(DISTINCT source_post_id) AS inbound
				FROM {$this->table}
				WHERE link_type = %s AND target_post_id IN ($in) AND source_post_id <> target_post_id
				GROUP BY target_post_id",
				array_merge(array(self::TYPE_INTERNAL), $post_ids)
			)
		);

		foreach ((array) $inbound_rows as $row) {
			$counts[(int) $row->post_id]['inbound'] = (int) $row->inbound;
		}

		return $counts;
	}

	/**
	 * Get one page of the Link Report: published posts with link counts.
	 *
	 * Supported args:
	 * - post_types   string[] Post types to include. Default array('post').
	 * - orphans_only bool     Only posts with zero inbound internal links.
	 * - search       string   Case-insensitive title search.
	 * - orderby      string   inbound|outbound|external|title|date. Default inbound.
	 * - order        string   ASC|DESC. Default ASC.
	 * - per_page     int      Default 20, max 200.
	 * - page         int      1-based. Default 1.
	 *
	 * @param array $args Report arguments.
	 * @return object[] Rows with ID, post_title, post_type, post_date, inbound, outbound, external, broken.
	 */
	public function get_report_page(array $args = array()): array {
		if (!$this->table_exists()) {
			return array();
		}

		$args = $this->normalize_report_args($args);
		list($where_sql, $where_args) = $this->build_report_where($args);

		$orderby = self::$report_orderby[$args['orderby']];
		$order   = $args['order'];
		$offset  = ($args['page'] - 1) * $args['per_page'];

		$sql = "SELECT p.ID, p.post_title, p.post_type, p.post_date,
				COALESCE(inb.inbound, 0) AS inbound,
				COALESCE(outb.outbound, 0) AS outbound,
				COALESCE(outb.external, 0) AS external,
				COALESCE(outb.broken, 0) AS broken
			FROM {$this->wpdb->posts} p
			{$this->report_joins()}
			WHERE {$where_sql}
			ORDER BY {$orderby} {$order}, p.ID ASC
			LIMIT %d OFFSET %d";

		$query_args = array_merge(
			$this->report_join_args(),
			$where_args,
			array($args['per_page'], $offset)
		);

		$rows = (array) $this->wpdb->get_results($this->wpdb->prepare($sql, $query_args));

		foreach ($rows as $row) {
			$row->ID       = (int) $row->ID;
			$row->inbound  = (int) $row->inbound;
			$row->outbound = (int) $row->outbound;
			$row->external = (int) $row->external;
			$row->broken   = (int) $row->broken;
		}

		return $rows;
	}

	/**
	 * Count the rows the Link Report would return for the given filters.
	 *
	 * @param array $args Same filters as get_report_page() (paging ignored).
	 * @return int
	 */
	public function get_report_count(array $args = array()): int {
		if (!$this->table_exists()) {
			return 0;
		}

		$args = $this->normalize_report_args($args);
		list($where_sql, $where_args) = $this->build_report_where($args);

		$sql = "SELECT COUNT(*)
			FROM {$this->wpdb->posts} p
			{$this->report_joins()}
			WHERE {$where_sql}";

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare($sql, array_merge($this->report_join_args(), $where_args))
		);
	}

	/**
	 * Published posts with fewer than $below distinct inbound internal links.
	 *
	 * @param string[] $post_types Post types to include.
	 * @param int      $below      Inbound-link threshold (1 = orphans only).
	 * @return int[] Post IDs, fewest inbound links first.
	 */
	public function get_post_ids_with_inbound_below(array $post_types, int $below): array {
		$post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));
		if (empty($post_types) || !$this->table_exists()) {
			return array();
		}

		$type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));

		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT p.ID
				FROM {$this->wpdb->posts} p
				LEFT JOIN (
					SELECT target_post_id, COUNT(DISTINCT source_post_id) AS inbound
					FROM {$this->table}
					WHERE link_type = %s AND target_post_id > 0 AND source_post_id <> target_post_id
					GROUP BY target_post_id
				) inb ON inb.target_post_id = p.ID
				WHERE p.post_status = 'publish'
				AND p.post_type IN ($type_placeholders)
				AND COALESCE(inb.inbound, 0) < %d
				ORDER BY COALESCE(inb.inbound, 0) ASC, p.ID ASC",
				array_merge(array(self::TYPE_INTERNAL), $post_types, array(max(1, $below)))
			)
		);

		return array_map('intval', (array) $ids);
	}

	/**
	 * Get site-wide link index totals.
	 *
	 * @return array{total:int, internal:int, external:int, broken:int, sources:int}
	 */
	public function get_summary(): array {
		$summary = array(
			'total'    => 0,
			'internal' => 0,
			'external' => 0,
			'broken'   => 0,
			'sources'  => 0,
		);

		if (!$this->table_exists()) {
			return $summary;
		}

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT COUNT(*) AS total,
					SUM(CASE WHEN link_type = %s THEN 1 ELSE 0 END) AS internal,
					SUM(CASE WHEN link_type = %s THEN 1 ELSE 0 END) AS external,
					SUM(CASE WHEN link_type = %s AND target_post_id = 0 THEN 1 ELSE 0 END) AS broken,
					COUNT(DISTINCT source_post_id) AS sources
				FROM {$this->table}",
				self::TYPE_INTERNAL,
				self::TYPE_EXTERNAL,
				self::TYPE_INTERNAL
			)
		);

		if ($row) {
			foreach (array_keys($summary) as $key) {
				$summary[$key] = (int) $row->$key;
			}
		}

		return $summary;
	}

	/**
	 * Normalize and validate a single link row.
	 *
	 * @param mixed $link  Raw link array.
	 * @param int   $index Fallback position.
	 * @return array|null Normalized row, or null when the link is unusable.
	 */
	private function normalize_link($link, int $index): ?array {
		if (!is_array($link) || empty($link['target_url'])) {
			return null;
		}

		$url       = (string) $link['target_url'];
		$link_type = isset($link['link_type']) && $this->is_valid_type((string) $link['link_type'])
			? (string) $link['link_type']
			: self::TYPE_INTERNAL;

		$target_post_id = ($link_type === self::TYPE_INTERNAL && isset($link['target_post_id']))
			? absint($link['target_post_id'])
			: 0;

		return array(
			'target_post_id'   => $target_post_id,
			'target_url'       => $url,
			'url_hash'         => md5($url),
			'anchor_text'      => isset($link['anchor_text']) ? mb_substr(sanitize_text_field((string) $link['anchor_text']), 0, 500) : '',
			'link_type'        => $link_type,
			'rel'              => isset($link['rel']) ? mb_substr(sanitize_text_field((string) $link['rel']), 0, 100) : '',
			'is_nofollow'      => !empty($link['is_nofollow']) ? 1 : 0,
			'inserted_by_aips' => !empty($link['inserted_by_aips']) ? 1 : 0,
			'position'         => isset($link['position']) ? (int) $link['position'] : $index,
		);
	}

	/**
	 * Whether a link type is supported.
	 *
	 * @param string $link_type Link type.
	 * @return bool
	 */
	private function is_valid_type(string $link_type): bool {
		return in_array($link_type, array(self::TYPE_INTERNAL, self::TYPE_EXTERNAL), true);
	}

	/**
	 * Apply defaults and whitelists to Link Report arguments.
	 *
	 * @param array $args Raw arguments.
	 * @return array
	 */
	private function normalize_report_args(array $args): array {
		$post_types = isset($args['post_types']) ? (array) $args['post_types'] : array('post');
		$post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));
		if (empty($post_types)) {
			$post_types = array('post');
		}

		$orderby = isset($args['orderby']) ? sanitize_key((string) $args['orderby']) : 'inbound';
		if (!isset(self::$report_orderby[$orderby])) {
			$orderby = 'inbound';
		}

		$order = isset($args['order']) && strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';

		return array(
			'post_types'   => $post_types,
			'orphans_only' => !empty($args['orphans_only']),
			'search'       => isset($args['search']) ? trim((string) $args['search']) : '',
			'orderby'      => $orderby,
			'order'        => $order,
			'per_page'     => isset($args['per_page']) ? max(1, min(200, (int) $args['per_page'])) : 20,
			'page'         => isset($args['page']) ? max(1, (int) $args['page']) : 1,
		);
	}

	/**
	 * Build the WHERE clause shared by the report page and count queries.
	 *
	 * @param array $args Normalized report arguments.
	 * @return array{0:string, 1:array} SQL fragment and its prepare() arguments.
	 */
	private function build_report_where(array $args): array {
		$type_placeholders = implode(', ', array_fill(0, count($args['post_types']), '%s'));

		$clauses = array(
			"p.post_status = 'publish'",
			"p.post_type IN ($type_placeholders)",
		);
		$values  = $args['post_types'];

		if ($args['orphans_only']) {
			$clauses[] = 'COALESCE(inb.inbound, 0) = 0';
		}

		if ($args['search'] !== '') {
			$clauses[] = 'p.post_title LIKE %s';
			$values[]  = '%' . $this->wpdb->esc_like($args['search']) . '%';
		}

		return array(implode(' AND ', $clauses), $values);
	}

	/**
	 * LEFT JOINs that attach aggregated inbound and outbound counts to posts.
	 *
	 * @return string
	 */
	private function report_joins(): string {
		return "LEFT JOIN (
				SELECT target_post_id, COUNT(DISTINCT source_post_id) AS inbound
				FROM {$this->table}
				WHERE link_type = %s AND target_post_id > 0 AND source_post_id <> target_post_id
				GROUP BY target_post_id
			) inb ON inb.target_post_id = p.ID
			LEFT JOIN (
				SELECT source_post_id,
					SUM(CASE WHEN link_type = %s AND target_post_id > 0 THEN 1 ELSE 0 END) AS outbound,
					SUM(CASE WHEN link_type = %s THEN 1 ELSE 0 END) AS external,
					SUM(CASE WHEN link_type = %s AND target_post_id = 0 THEN 1 ELSE 0 END) AS broken
				FROM {$this->table}
				GROUP BY source_post_id
			) outb ON outb.source_post_id = p.ID";
	}

	/**
	 * prepare() arguments for report_joins(), in placeholder order.
	 *
	 * @return string[]
	 */
	private function report_join_args(): array {
		return array(self::TYPE_INTERNAL, self::TYPE_INTERNAL, self::TYPE_EXTERNAL, self::TYPE_INTERNAL);
	}
}
