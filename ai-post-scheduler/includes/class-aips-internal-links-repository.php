<?php
/**
 * Internal Links Repository
 *
 * Handles persistence for suggested internal links between posts.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Internal_Links_Repository
 *
 * Manages CRUD operations for the aips_internal_links table.
 */
class AIPS_Internal_Links_Repository {

	/**
	 * @var wpdb WordPress database object.
	 */
	private $wpdb;

	/**
	 * @var string Table name with prefix.
	 */
	private $table;

	/**
	 * Valid status values for internal links.
	 *
	 * @var string[]
	 */
	const VALID_STATUSES = array( 'pending', 'accepted', 'rejected', 'inserted' );

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_internal_links';
	}

	/**
	 * Get a single suggestion by ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null Row object with source/target post titles, or null if not found.
	 */
	public function get_by_id($id) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT il.*,
					sp.post_title AS source_post_title,
					tp.post_title AS target_post_title
				FROM {$this->table} il
				LEFT JOIN {$this->wpdb->posts} sp ON il.source_post_id = sp.ID
				LEFT JOIN {$this->wpdb->posts} tp ON il.target_post_id = tp.ID
				WHERE il.id = %d",
				absint($id)
			)
		);
	}

	/**
	 * Get all internal link suggestions for a source post.
	 *
	 * @param int    $source_post_id Source post ID.
	 * @param string $status         Optional. Filter by status. Empty string returns all.
	 * @return object[] Array of row objects.
	 */
	public function get_by_source_post($source_post_id, $status = '') {
		$where = $this->wpdb->prepare(
			'WHERE source_post_id = %d',
			absint($source_post_id)
		);

		if ($status && in_array($status, self::VALID_STATUSES, true)) {
			$where .= $this->wpdb->prepare( ' AND status = %s', $status );
		}

		return $this->wpdb->get_results(
			"SELECT * FROM {$this->table} {$where} ORDER BY similarity_score DESC"
		);
	}

	/**
	 * Get paginated internal links across all posts.
	 *
	 * @param int    $per_page Number of results per page.
	 * @param int    $page     1-based page number.
	 * @param string $status   Optional. Filter by status.
	 * @param string $search   Optional. Search term applied to source/target post titles.
	 * @return object[] Array of row objects with extra post title columns.
	 */
	public function get_paginated($per_page = 20, $page = 1, $status = '', $search = '') {
		$per_page = max(1, absint($per_page));
		$offset   = ($page - 1) * $per_page;

		$where_clauses = array('1=1');
		$params        = array();

		if ($status && in_array($status, self::VALID_STATUSES, true)) {
			$where_clauses[] = 'il.status = %s';
			$params[]        = $status;
		}

		if (!empty($search)) {
			$like              = '%' . $this->wpdb->esc_like($search) . '%';
			$where_clauses[]   = '(sp.post_title LIKE %s OR tp.post_title LIKE %s)';
			$params[]          = $like;
			$params[]          = $like;
		}

		$where    = 'WHERE ' . implode(' AND ', $where_clauses);
		$params[] = $per_page;
		$params[] = $offset;

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT il.*,
					sp.post_title AS source_post_title,
					tp.post_title AS target_post_title,
					sp.post_status AS source_post_status,
					tp.post_status AS target_post_status
				FROM {$this->table} il
				LEFT JOIN {$this->wpdb->posts} sp ON il.source_post_id = sp.ID
				LEFT JOIN {$this->wpdb->posts} tp ON il.target_post_id = tp.ID
				{$where}
				ORDER BY il.created_at DESC
				LIMIT %d OFFSET %d",
				...$params
			)
		);
	}

	/**
	 * Get the total count for paginated queries (mirrors get_paginated filters).
	 *
	 * @param string $status Optional. Filter by status.
	 * @param string $search Optional. Search term.
	 * @return int Total count.
	 */
	public function get_paginated_count($status = '', $search = '') {
		$where_clauses = array('1=1');
		$params        = array();

		if ($status && in_array($status, self::VALID_STATUSES, true)) {
			$where_clauses[] = 'il.status = %s';
			$params[]        = $status;
		}

		if (!empty($search)) {
			$like            = '%' . $this->wpdb->esc_like($search) . '%';
			$where_clauses[] = '(sp.post_title LIKE %s OR tp.post_title LIKE %s)';
			$params[]        = $like;
			$params[]        = $like;
		}

		$where = 'WHERE ' . implode(' AND ', $where_clauses);

		if (!empty($params)) {
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$this->table} il
					LEFT JOIN {$this->wpdb->posts} sp ON il.source_post_id = sp.ID
					LEFT JOIN {$this->wpdb->posts} tp ON il.target_post_id = tp.ID
					{$where}",
					...$params
				)
			);
		}

		return (int) $this->wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$this->table} il
			LEFT JOIN {$this->wpdb->posts} sp ON il.source_post_id = sp.ID
			LEFT JOIN {$this->wpdb->posts} tp ON il.target_post_id = tp.ID
			{$where}"
		);
	}

	/**
	 * Check whether a specific source→target pair already exists.
	 *
	 * @param int $source_post_id Source post ID.
	 * @param int $target_post_id Target post ID.
	 * @return bool
	 */
	public function exists($source_post_id, $target_post_id) {
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE source_post_id = %d AND target_post_id = %d",
				absint($source_post_id),
				absint($target_post_id)
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Insert a new internal link suggestion.
	 *
	 * @param int    $source_post_id   Source post ID.
	 * @param int    $target_post_id   Target post ID.
	 * @param float  $similarity_score Cosine similarity score (0–1).
	 * @param string $anchor_text      Optional. Suggested anchor text.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function insert($source_post_id, $target_post_id, $similarity_score, $anchor_text = '') {
		$now = AIPS_DateTime::now()->timestamp();

		$result = $this->wpdb->insert(
			$this->table,
			array(
				'source_post_id'   => absint($source_post_id),
				'target_post_id'   => absint($target_post_id),
				'similarity_score' => (float) $similarity_score,
				'anchor_text'      => sanitize_text_field($anchor_text),
				'status'           => 'pending',
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array('%d', '%d', '%f', '%s', '%s', '%d', '%d')
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update the status of an internal link.
	 *
	 * @param int    $id     Row ID.
	 * @param string $status New status (pending|accepted|rejected|inserted).
	 * @return int|false Number of updated rows or false on failure.
	 */
	public function update_status($id, $status) {
		if (!in_array($status, self::VALID_STATUSES, true)) {
			return false;
		}

		return $this->wpdb->update(
			$this->table,
			array(
				'status'     => $status,
				'updated_at' => AIPS_DateTime::now()->timestamp(),
			),
			array('id' => absint($id)),
			array('%s', '%d'),
			array('%d')
		);
	}

	/**
	 * Update the anchor text of a suggestion.
	 *
	 * @param int    $id          Row ID.
	 * @param string $anchor_text New anchor text.
	 * @return int|false Number of updated rows or false on failure.
	 */
	public function update_anchor_text($id, $anchor_text) {
		return $this->wpdb->update(
			$this->table,
			array(
				'anchor_text' => sanitize_text_field($anchor_text),
				'updated_at'  => AIPS_DateTime::now()->timestamp(),
			),
			array('id' => absint($id)),
			array('%s', '%d'),
			array('%d')
		);
	}

	/**
	 * Delete a specific suggestion by ID.
	 *
	 * @param int $id Row ID.
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete($id) {
		return $this->wpdb->delete(
			$this->table,
			array('id' => absint($id)),
			array('%d')
		);
	}

	/**
	 * Delete all suggestions for a source post.
	 *
	 * @param int $source_post_id Source post ID.
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete_by_source_post($source_post_id) {
		return $this->wpdb->delete(
			$this->table,
			array('source_post_id' => absint($source_post_id)),
			array('%d')
		);
	}

	/**
	 * Delete only PENDING suggestions for a source post.
	 *
	 * Accepted, rejected, and inserted suggestions are preserved so that
	 * editorial decisions are not lost when suggestions are regenerated.
	 *
	 * @param int $source_post_id Source post ID.
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete_pending_by_source_post($source_post_id) {
		return $this->wpdb->delete(
			$this->table,
			array(
				'source_post_id' => absint($source_post_id),
				'status'         => 'pending',
			),
			array('%d', '%s')
		);
	}

	/**
	 * Delete all suggestions for a target post (e.g. when a post is trashed).
	 *
	 * @param int $target_post_id Target post ID.
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete_by_target_post($target_post_id) {
		return $this->wpdb->delete(
			$this->table,
			array('target_post_id' => absint($target_post_id)),
			array('%d')
		);
	}

	/**
	 * Delete all suggestions (both as source or target) for a post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return int Total rows deleted.
	 */
	public function delete_all_for_post($post_id) {
		$post_id = absint($post_id);
		$a       = (int) $this->wpdb->delete( $this->table, array('source_post_id' => $post_id), array('%d') );
		$b       = (int) $this->wpdb->delete( $this->table, array('target_post_id' => $post_id), array('%d') );

		return $a + $b;
	}

	/**
	 * Get per-status summary counts.
	 *
	 * @return array Associative array of status => count.
	 */
	public function get_status_counts() {
		$rows = $this->wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$this->table} GROUP BY status"
		);

		$counts = array_fill_keys(self::VALID_STATUSES, 0);
		foreach ($rows as $row) {
			$counts[$row->status] = (int) $row->cnt;
		}

		return $counts;
	}

	/**
	 * Delete all link suggestions.
	 *
	 * @return int|false Number of rows deleted or false on failure.
	 */
	public function delete_all() {
		return $this->wpdb->query( "DELETE FROM {$this->table}" );
	}
}
