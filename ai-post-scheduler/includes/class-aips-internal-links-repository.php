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
	const VALID_STATUSES = array( 'pending', 'accepted', 'rejected', 'inserted', 'reverted' );

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
	 * @param int    $source_post_id Source post ID.
	 * @param string $origin         Optional. Only delete suggestions of this
	 *                               origin ('outbound', 'inbound', ...).
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete_pending_by_source_post($source_post_id, $origin = '') {
		$where  = array(
			'source_post_id' => absint($source_post_id),
			'status'         => 'pending',
		);
		$format = array('%d', '%s');

		if ($origin !== '') {
			$where['origin'] = sanitize_key($origin);
			$format[]        = '%s';
		}

		return $this->wpdb->delete($this->table, $where, $format);
	}

	/**
	 * Delete only PENDING suggestions that point at a target post.
	 *
	 * @param int    $target_post_id Target post ID.
	 * @param string $origin         Optional origin filter.
	 * @return int|false Number of deleted rows or false on failure.
	 */
	public function delete_pending_by_target_post($target_post_id, $origin = '') {
		$where  = array(
			'target_post_id' => absint($target_post_id),
			'status'         => 'pending',
		);
		$format = array('%d', '%s');

		if ($origin !== '') {
			$where['origin'] = sanitize_key($origin);
			$format[]        = '%s';
		}

		return $this->wpdb->delete($this->table, $where, $format);
	}

	/**
	 * Create or refresh a suggestion for a source/target pair.
	 *
	 * An existing PENDING row is updated in place; rows with any other status
	 * (accepted, rejected, inserted, reverted) are left untouched so editorial
	 * decisions survive regeneration.
	 *
	 * Keys: source_post_id, target_post_id (required), similarity_score,
	 * confidence, anchor_text, anchor_source, match_context, origin.
	 *
	 * @param array $data Suggestion data.
	 * @return array{id:int, action:string}|false action is inserted, updated or kept.
	 */
	public function save_suggestion(array $data) {
		$source = isset($data['source_post_id']) ? absint($data['source_post_id']) : 0;
		$target = isset($data['target_post_id']) ? absint($data['target_post_id']) : 0;
		if ($source <= 0 || $target <= 0 || $source === $target) {
			return false;
		}

		$fields = array(
			'similarity_score' => isset($data['similarity_score']) ? (float) $data['similarity_score'] : 0.0,
			'confidence'       => isset($data['confidence']) ? max(0.0, min(1.0, (float) $data['confidence'])) : 0.0,
			'anchor_text'      => isset($data['anchor_text']) ? sanitize_text_field($data['anchor_text']) : '',
			'anchor_source'    => isset($data['anchor_source']) ? sanitize_key($data['anchor_source']) : '',
			'match_context'    => isset($data['match_context']) ? sanitize_textarea_field($data['match_context']) : '',
			'origin'           => isset($data['origin']) ? sanitize_key($data['origin']) : 'outbound',
			'updated_at'       => AIPS_DateTime::now()->timestamp(),
		);
		$formats = array('%f', '%f', '%s', '%s', '%s', '%s', '%d');

		$existing = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, status FROM {$this->table} WHERE source_post_id = %d AND target_post_id = %d LIMIT 1",
				$source,
				$target
			)
		);

		if ($existing) {
			if ($existing->status !== 'pending') {
				return array('id' => (int) $existing->id, 'action' => 'kept');
			}

			$this->wpdb->update($this->table, $fields, array('id' => (int) $existing->id), $formats, array('%d'));
			return array('id' => (int) $existing->id, 'action' => 'updated');
		}

		$fields['source_post_id'] = $source;
		$fields['target_post_id'] = $target;
		$fields['status']         = 'pending';
		$fields['created_at']     = $fields['updated_at'];
		$formats                  = array_merge($formats, array('%d', '%d', '%s', '%d'));

		$result = $this->wpdb->insert($this->table, $fields, $formats);

		return $result ? array('id' => (int) $this->wpdb->insert_id, 'action' => 'inserted') : false;
	}

	/**
	 * Suggestions pointing at a target post, with source post titles.
	 *
	 * @param int      $target_post_id Target post ID.
	 * @param string[] $statuses       Optional status filter.
	 * @param string   $origin         Optional origin filter.
	 * @return object[]
	 */
	public function get_by_target_post($target_post_id, array $statuses = array(), $origin = '') {
		$sql  = "SELECT il.*, sp.post_title AS source_post_title
			FROM {$this->table} il
			LEFT JOIN {$this->wpdb->posts} sp ON il.source_post_id = sp.ID
			WHERE il.target_post_id = %d";
		$args = array(absint($target_post_id));

		$statuses = array_values(array_intersect($statuses, self::VALID_STATUSES));
		if (!empty($statuses)) {
			$sql .= ' AND il.status IN (' . implode(', ', array_fill(0, count($statuses), '%s')) . ')';
			$args = array_merge($args, $statuses);
		}

		if ($origin !== '') {
			$sql   .= ' AND il.origin = %s';
			$args[] = sanitize_key($origin);
		}

		$sql .= ' ORDER BY il.confidence DESC, il.similarity_score DESC';

		return (array) $this->wpdb->get_results($this->wpdb->prepare($sql, $args));
	}

	/**
	 * Count pending suggestions per target post.
	 *
	 * @param int[]  $target_post_ids Target post IDs.
	 * @param string $origin          Optional origin filter.
	 * @return array<int, int> target_post_id => pending count.
	 */
	public function count_pending_by_targets(array $target_post_ids, $origin = '') {
		$ids = array_values(array_unique(array_filter(array_map('absint', $target_post_ids))));
		if (empty($ids)) {
			return array();
		}

		$sql  = "SELECT target_post_id, COUNT(*) AS pending FROM {$this->table}
			WHERE status = 'pending' AND target_post_id IN (" . implode(', ', array_fill(0, count($ids), '%d')) . ')';
		$args = $ids;

		if ($origin !== '') {
			$sql   .= ' AND origin = %s';
			$args[] = sanitize_key($origin);
		}

		$sql .= ' GROUP BY target_post_id';

		$counts = array();
		foreach ((array) $this->wpdb->get_results($this->wpdb->prepare($sql, $args)) as $row) {
			$counts[(int) $row->target_post_id] = (int) $row->pending;
		}

		return $counts;
	}

	/**
	 * Record that a suggestion was inserted into its source post.
	 *
	 * @param int    $id             Row ID.
	 * @param string $before_snippet Content snippet before insertion.
	 * @param string $after_snippet  Content snippet after insertion.
	 * @param string $anchor_text    Anchor text actually used.
	 * @param string $batch_id       Optional batch identifier.
	 * @return int|false
	 */
	public function mark_applied($id, $before_snippet, $after_snippet, $anchor_text = '', $batch_id = '') {
		$data   = array(
			'status'         => 'inserted',
			'before_snippet' => (string) $before_snippet,
			'after_snippet'  => (string) $after_snippet,
			'applied_at'     => AIPS_DateTime::now()->timestamp(),
			'updated_at'     => AIPS_DateTime::now()->timestamp(),
		);
		$format = array('%s', '%s', '%s', '%d', '%d');

		if ($anchor_text !== '') {
			$data['anchor_text'] = sanitize_text_field($anchor_text);
			$format[]            = '%s';
		}

		if ($batch_id !== '') {
			$data['batch_id'] = sanitize_text_field($batch_id);
			$format[]         = '%s';
		}

		return $this->wpdb->update($this->table, $data, array('id' => absint($id)), $format, array('%d'));
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
