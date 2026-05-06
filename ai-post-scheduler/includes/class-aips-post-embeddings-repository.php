<?php
/**
 * Post Embeddings Repository
 *
 * Handles persistence for post embeddings used by the Internal Links feature.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Embeddings_Repository
 *
 * Manages CRUD operations for the aips_post_embeddings table.
 */
class AIPS_Post_Embeddings_Repository {

	/**
	 * @var wpdb WordPress database object.
	 */
	private $wpdb;

	/**
	 * @var string Table name with prefix.
	 */
	private $table;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'aips_post_embeddings';
	}

	/**
	 * Get a single post embedding by post ID.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return object|null Row object or null if not found.
	 */
	public function get_by_post_id($post_id) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE post_id = %d LIMIT 1",
				absint($post_id)
			)
		);
	}

	/**
	 * Get multiple post embeddings by post IDs.
	 *
	 * @param int[] $post_ids Array of WordPress post IDs.
	 * @return object[] Array of row objects keyed by post_id.
	 */
	public function get_by_post_ids(array $post_ids) {
		if (empty($post_ids)) {
			return array();
		}

		$post_ids = array_map('absint', $post_ids);
		$placeholders = implode(',', array_fill(0, count($post_ids), '%d'));

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE post_id IN ($placeholders)",
				...$post_ids
			)
		);

		$indexed = array();
		foreach ($rows as $row) {
			$indexed[(int) $row->post_id] = $row;
		}

		return $indexed;
	}

	/**
	 * Get all indexed post IDs.
	 *
	 * @return int[] Array of post IDs that have embeddings.
	 */
	public function get_all_indexed_post_ids() {
		$results = $this->wpdb->get_col(
			"SELECT post_id FROM {$this->table} ORDER BY post_id ASC"
		);

		return array_map('intval', $results);
	}

	/**
	 * Get all embeddings (post_id + embedding only) for similarity comparison.
	 *
	 * @return object[] Array of rows with post_id and embedding columns.
	 */
	public function get_all_for_similarity() {
		return $this->wpdb->get_results(
			"SELECT post_id, embedding FROM {$this->table} ORDER BY post_id ASC"
		);
	}

	/**
	 * Get post embeddings for similarity search, constrained to a specific post type and status.
	 *
	 * JOINs against wp_posts to exclude embeddings for deleted posts or posts of a different
	 * type/status, keeping the candidate set small and relevant for the source post being processed.
	 *
	 * @param string $post_type   Post type to filter (default: 'post').
	 * @param string $post_status Post status to filter (default: 'publish').
	 * @return object[] Array of row objects with post_id and embedding columns.
	 */
	public function get_all_for_similarity_by_type($post_type = 'post', $post_status = 'publish') {
		$post_type   = sanitize_key($post_type);
		$post_status = sanitize_key($post_status);

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT e.post_id, e.embedding
				FROM {$this->table} e
				INNER JOIN {$this->wpdb->posts} p ON e.post_id = p.ID
				WHERE p.post_type = %s
				AND p.post_status = %s
				ORDER BY e.post_id ASC",
				$post_type,
				$post_status
			)
		);
	}

	/**
	 * Get the total number of indexed posts.
	 *
	 * @return int Count of indexed posts.
	 */
	public function count() {
		return (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table}"
		);
	}

	/**
	 * Count indexed posts that actually exist with the given post_type and post_status.
	 *
	 * This gives an accurate count for the indexing-progress bar by joining against
	 * wp_posts and excluding embeddings for deleted posts or posts of a different type.
	 *
	 * @param string $post_type   Post type to filter (default: 'post').
	 * @param string $post_status Post status to filter (default: 'publish').
	 * @return int Count of indexed posts matching the given type/status.
	 */
	public function count_indexed_for_type($post_type = 'post', $post_status = 'publish') {
		$post_type   = sanitize_key($post_type);
		$post_status = sanitize_key($post_status);

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$this->table} e
				INNER JOIN {$this->wpdb->posts} p ON e.post_id = p.ID
				WHERE p.post_type = %s
				AND p.post_status = %s",
				$post_type,
				$post_status
			)
		);
	}

	/**
	 * Upsert a post embedding.
	 *
	 * Inserts a new record or updates an existing one for the given post ID.
	 *
	 * @param int    $post_id   WordPress post ID.
	 * @param array  $embedding Embedding vector (will be JSON-encoded).
	 * @param string $model     Optional. Model used to generate the embedding.
	 * @return int|false Number of affected rows or false on failure.
	 */
	public function upsert($post_id, array $embedding, $model = '') {
		$post_id = absint($post_id);
		$now     = AIPS_DateTime::now()->timestamp();

		$existing = $this->get_by_post_id($post_id);

		if ($existing) {
			return $this->wpdb->update(
				$this->table,
				array(
					'embedding'  => wp_json_encode($embedding),
					'model'      => sanitize_text_field($model),
					'indexed_at' => $now,
				),
				array('post_id' => $post_id),
				array('%s', '%s', '%d'),
				array('%d')
			);
		}

		return $this->wpdb->insert(
			$this->table,
			array(
				'post_id'    => $post_id,
				'embedding'  => wp_json_encode($embedding),
				'model'      => sanitize_text_field($model),
				'indexed_at' => $now,
			),
			array('%d', '%s', '%s', '%d')
		);
	}

	/**
	 * Delete the embedding for a post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return int|false Number of rows deleted or false on failure.
	 */
	public function delete($post_id) {
		return $this->wpdb->delete(
			$this->table,
			array('post_id' => absint($post_id)),
			array('%d')
		);
	}

	/**
	 * Get posts that do not yet have an embedding, filtered by post type and status.
	 *
	 * @param int    $limit           Maximum number of post IDs to return.
	 * @param int    $last_post_id    Optional. Return posts with ID > this value for cursor-based paging.
	 * @param string $post_type       Optional. Post type to index (default: 'post').
	 * @param string $post_status     Optional. Post status to index (default: 'publish').
	 * @return int[] Array of post IDs.
	 */
	public function get_unindexed_post_ids($limit = 20, $last_post_id = 0, $post_type = 'post', $post_status = 'publish') {
		$post_type    = sanitize_key($post_type);
		$post_status  = sanitize_key($post_status);
		$limit        = absint($limit);
		$last_post_id = absint($last_post_id);

		if ($last_post_id > 0) {
			$results = $this->wpdb->get_col(
				$this->wpdb->prepare(
					"SELECT p.ID
					FROM {$this->wpdb->posts} p
					LEFT JOIN {$this->table} e ON p.ID = e.post_id
					WHERE p.post_type = %s
					AND p.post_status = %s
					AND e.post_id IS NULL
					AND p.ID > %d
					ORDER BY p.ID ASC
					LIMIT %d",
					$post_type,
					$post_status,
					$last_post_id,
					$limit
				)
			);
		} else {
			$results = $this->wpdb->get_col(
				$this->wpdb->prepare(
					"SELECT p.ID
					FROM {$this->wpdb->posts} p
					LEFT JOIN {$this->table} e ON p.ID = e.post_id
					WHERE p.post_type = %s
					AND p.post_status = %s
					AND e.post_id IS NULL
					ORDER BY p.ID ASC
					LIMIT %d",
					$post_type,
					$post_status,
					$limit
				)
			);
		}

		return array_map('intval', $results);
	}

	/**
	 * Delete all embeddings.
	 *
	 * @return int|false Number of rows deleted or false on failure.
	 */
	public function delete_all() {
		return $this->wpdb->query( "DELETE FROM {$this->table}" );
	}
}
