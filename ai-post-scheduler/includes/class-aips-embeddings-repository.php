<?php
/**
 * Embeddings Repository
 *
 * Persistence layer for the aips_post_embeddings table.
 * Tracks per-post Pinecone indexing status.
 * All SQL lives here; no business logic.
 *
 * @package AI_Post_Scheduler
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Embeddings_Repository
 *
 * CRUD operations for the aips_post_embeddings table.
 */
class AIPS_Embeddings_Repository {

	/**
	 * @var string Table name (with prefix).
	 */
	private $table;

	/**
	 * @var wpdb WordPress database abstraction.
	 */
	private $wpdb;

	/**
	 * Valid index status values.
	 */
	const STATUS_PENDING = 'pending';
	const STATUS_INDEXED = 'indexed';
	const STATUS_ERROR   = 'error';

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_post_embeddings';
	}

	/**
	 * Insert or update the index status for a post.
	 *
	 * Uses INSERT … ON DUPLICATE KEY UPDATE so a single call handles both
	 * first-time indexing and subsequent updates.
	 *
	 * @param int         $post_id   WordPress post ID.
	 * @param string      $status    One of the STATUS_* constants.
	 * @param string|null $error_msg Error message when status = 'error'.
	 * @return bool True on success.
	 */
	public function upsert_status($post_id, $status, $error_msg = null) {
		$post_id   = absint($post_id);
		$vector_id = 'post-' . $post_id;
		$status    = sanitize_key($status);

		$indexed_at = ($status === self::STATUS_INDEXED) ? current_time('mysql') : null;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE post_id = %d",
				$post_id
			)
		);

		if ($existing) {
			$data   = array(
				'index_status' => $status,
				'error_msg'    => $error_msg,
				'updated_at'   => current_time('mysql'),
			);
			$format = array('%s', '%s', '%s');

			if ($indexed_at) {
				$data['indexed_at'] = $indexed_at;
				$format[]           = '%s';
			}

			$result = $this->wpdb->update(
				$this->table,
				$data,
				array('post_id' => $post_id),
				$format,
				array('%d')
			);
		} else {
			$data = array(
				'post_id'      => $post_id,
				'vector_id'    => $vector_id,
				'index_status' => $status,
				'indexed_at'   => $indexed_at,
				'error_msg'    => $error_msg,
				'created_at'   => current_time('mysql'),
				'updated_at'   => current_time('mysql'),
			);

			$result = $this->wpdb->insert(
				$this->table,
				$data,
				array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
			);
		}
		// phpcs:enable

		return $result !== false;
	}

	/**
	 * Retrieve the index record for a single post.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null Row object or null if not found.
	 */
	public function get_by_post_id($post_id) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE post_id = %d",
				absint($post_id)
			)
		);
		// phpcs:enable
	}

	/**
	 * Get rows matching a given status, up to a configurable limit.
	 *
	 * @param string $status One of the STATUS_* constants.
	 * @param int    $limit  Maximum number of rows to return.
	 * @return object[] Array of row objects.
	 */
	public function get_by_status($status, $limit = 50) {
		$status = sanitize_key($status);
		$limit  = absint($limit);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE index_status = %s ORDER BY updated_at ASC LIMIT %d",
				$status,
				$limit
			)
		);
		// phpcs:enable
	}

	/**
	 * Return an array of post IDs that are currently indexed.
	 *
	 * @return int[]
	 */
	public function get_all_indexed_post_ids() {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$results = $this->wpdb->get_col(
			"SELECT post_id FROM {$this->table} WHERE index_status = 'indexed'"
		);
		// phpcs:enable

		return array_map('absint', $results);
	}

	/**
	 * Mark a set of post IDs as pending (re-index).
	 *
	 * Existing rows are updated; missing rows are inserted via upsert_status.
	 *
	 * @param int[] $post_ids Array of post IDs to mark pending.
	 * @return int Number of affected rows.
	 */
	public function mark_pending_bulk(array $post_ids) {
		if (empty($post_ids)) {
			return 0;
		}

		$affected = 0;
		foreach ($post_ids as $post_id) {
			if ($this->upsert_status(absint($post_id), self::STATUS_PENDING)) {
				$affected++;
			}
		}

		return $affected;
	}

	/**
	 * Remove the embedding record for a post (e.g. when the post is deleted).
	 *
	 * @param int $post_id Post ID.
	 * @return bool True on success.
	 */
	public function delete_by_post_id($post_id) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$result = $this->wpdb->delete(
			$this->table,
			array('post_id' => absint($post_id)),
			array('%d')
		);
		// phpcs:enable

		return $result !== false;
	}

	/**
	 * Count rows by status.
	 *
	 * @param string $status Status slug, or empty string for total.
	 * @return int
	 */
	public function count_by_status($status = '') {
		if ($status !== '') {
			$status = sanitize_key($status);
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE index_status = %s",
					$status
				)
			);
			// phpcs:enable
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
		// phpcs:enable
	}

	/**
	 * Get paginated index records with optional search and status filter.
	 *
	 * @param array $args {
	 *     @type int    $page     Current page (default 1).
	 *     @type int    $per_page Rows per page (default 20).
	 *     @type string $status   Status filter (default '').
	 *     @type string $search   Post title search term (default '').
	 * }
	 * @return array { items: object[], total: int }
	 */
	public function get_paginated($args = array()) {
		global $wpdb;

		$defaults = array(
			'page'     => 1,
			'per_page' => 20,
			'status'   => '',
			'search'   => '',
		);

		$args     = wp_parse_args($args, $defaults);
		$page     = max(1, absint($args['page']));
		$per_page = max(1, absint($args['per_page']));
		$offset   = ($page - 1) * $per_page;

		$posts_table = $wpdb->posts;
		$table       = $this->table;

		$where  = array('1=1');
		$params = array();

		if (!empty($args['status'])) {
			$where[]  = 'e.index_status = %s';
			$params[] = sanitize_key($args['status']);
		}

		if (!empty($args['search'])) {
			$where[]  = 'p.post_title LIKE %s';
			$params[] = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
		}

		$where_sql = implode(' AND ', $where);

		// Fetch total
		$count_sql = "SELECT COUNT(*) FROM {$table} e LEFT JOIN {$posts_table} p ON p.ID = e.post_id WHERE {$where_sql}";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) (empty($params)
			? $wpdb->get_var($count_sql)
			: $wpdb->get_var($wpdb->prepare($count_sql, $params)));

		// Fetch items
		$items_sql = "SELECT e.*, p.post_title, p.post_type FROM {$table} e LEFT JOIN {$posts_table} p ON p.ID = e.post_id WHERE {$where_sql} ORDER BY e.updated_at DESC LIMIT %d OFFSET %d";

		$item_params   = array_merge($params, array($per_page, $offset));
		$items         = $wpdb->get_results($wpdb->prepare($items_sql, $item_params));
		// phpcs:enable

		return array(
			'items' => is_array($items) ? $items : array(),
			'total' => $total,
		);
	}
}
