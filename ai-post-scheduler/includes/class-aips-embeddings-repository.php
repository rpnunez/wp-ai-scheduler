<?php
/**
 * Embeddings Repository
 *
 * Polymorphic persistence for vectors across posts, custom post types, topics,
 * and other content objects.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Embeddings_Repository
 *
 * Manages CRUD operations for the aips_embeddings table.
 */
class AIPS_Embeddings_Repository {

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
		$this->table = $wpdb->prefix . 'aips_embeddings';
	}

	/**
	 * Get a single embedding record by object type and ID.
	 *
	 * @param string $object_type Entity type ('post', 'topic', etc.).
	 * @param int    $object_id   Object ID.
	 * @return object|null Row object or null if not found.
	 */
	public function get_by_object($object_type, $object_id) {
		$object_type = sanitize_key($object_type);
		$object_id   = absint($object_id);

		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE object_type = %s AND object_id = %d LIMIT 1",
				$object_type,
				$object_id
			)
		);
	}

	/**
	 * Convenience helper to get embedding for a WordPress post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return object|null Row object or null if not found.
	 */
	public function get_by_post_id($post_id) {
		return $this->get_by_object('post', $post_id);
	}

	/**
	 * Get multiple embeddings for a list of post IDs.
	 *
	 * @param int[] $post_ids Array of WordPress post IDs.
	 * @return array<int, object> Array of row objects keyed by post_id.
	 */
	public function get_by_post_ids(array $post_ids) {
		if (empty($post_ids)) {
			return array();
		}

		$post_ids     = array_map('absint', $post_ids);
		$placeholders = implode(',', array_fill(0, count($post_ids), '%d'));

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE object_type = 'post' AND object_id IN ($placeholders)",
				...$post_ids
			)
		);

		$indexed = array();
		foreach ($rows as $row) {
			$indexed[(int) $row->object_id] = $row;
		}

		return $indexed;
	}

	/**
	 * Get all embeddings for similarity comparison, optionally filtered by post types and status.
	 *
	 * @param string          $object_type Entity type ('post', 'topic').
	 * @param string[]|string $post_types  WordPress post types to include (when object_type is 'post').
	 * @param string          $post_status WordPress post status filter (default: 'publish').
	 * @return object[] Array of row objects with object_id, object_post_type, model, dimensions, and embedding.
	 */
	public function get_all_for_similarity($object_type = 'post', $post_types = array('post'), $post_status = 'publish') {
		$object_type = sanitize_key($object_type);

		if ('post' === $object_type) {
			$post_types  = (array) $post_types;
			$post_types  = array_map('sanitize_key', $post_types);
			$post_status = sanitize_key($post_status);

			if (empty($post_types)) {
				$post_types = array('post');
			}

			$placeholders = implode(',', array_fill(0, count($post_types), '%s'));

			$sql = $this->wpdb->prepare(
				"SELECT e.object_id, e.object_post_type, e.embedding, e.dimensions, e.model
				FROM {$this->table} e
				INNER JOIN {$this->wpdb->posts} p ON e.object_id = p.ID
				WHERE e.object_type = 'post'
				AND p.post_type IN ($placeholders)
				AND p.post_status = %s
				ORDER BY e.object_id ASC",
				...array_merge($post_types, array($post_status))
			);

			return $this->wpdb->get_results($sql);
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT object_id, object_post_type, embedding, dimensions, model
				FROM {$this->table}
				WHERE object_type = %s
				ORDER BY object_id ASC",
				$object_type
			)
		);
	}

	/**
	 * Upsert an embedding record.
	 *
	 * @param string $object_type      Entity type ('post', 'topic', etc.).
	 * @param int    $object_id        Object ID.
	 * @param array  $embedding        Embedding vector array of floats.
	 * @param string $model            Model name used to generate embedding.
	 * @param int    $dimensions       Vector dimensions count.
	 * @param string $content_hash     MD5/SHA256 hash of the content embedded.
	 * @param string $object_post_type WordPress post type if object_type is 'post'.
	 * @return int|false Number of affected rows or false on failure.
	 */
	public function upsert($object_type, $object_id, array $embedding, $model = '', $dimensions = 0, $content_hash = '', $object_post_type = '') {
		$object_type      = sanitize_key($object_type);
		$object_id        = absint($object_id);
		$dimensions       = $dimensions > 0 ? absint($dimensions) : count($embedding);
		$now              = AIPS_DateTime::now()->timestamp();
		$object_post_type = sanitize_key($object_post_type);

		if ('post' === $object_type && empty($object_post_type)) {
			$post = get_post($object_id);
			if ($post) {
				$object_post_type = $post->post_type;
			}
		}

		$existing = $this->get_by_object($object_type, $object_id);

		$data = array(
			'embedding'        => wp_json_encode($embedding),
			'model'            => sanitize_text_field($model),
			'dimensions'       => $dimensions,
			'content_hash'     => sanitize_text_field($content_hash),
			'object_post_type' => $object_post_type,
			'indexed_at'       => $now,
		);

		if ($existing) {
			return $this->wpdb->update(
				$this->table,
				$data,
				array(
					'object_type' => $object_type,
					'object_id'   => $object_id,
				),
				array('%s', '%s', '%d', '%s', '%s', '%d'),
				array('%s', '%d')
			);
		}

		$data['object_type'] = $object_type;
		$data['object_id']   = $object_id;

		return $this->wpdb->insert(
			$this->table,
			$data,
			array('%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d')
		);
	}

	/**
	 * Delete embedding for an object.
	 *
	 * @param string $object_type Entity type.
	 * @param int    $object_id   Object ID.
	 * @return int|false
	 */
	public function delete($object_type, $object_id) {
		return $this->wpdb->delete(
			$this->table,
			array(
				'object_type' => sanitize_key($object_type),
				'object_id'   => absint($object_id),
			),
			array('%s', '%d')
		);
	}

	/**
	 * Delete embedding for a post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return int|false
	 */
	public function delete_by_post_id($post_id) {
		return $this->delete('post', $post_id);
	}

	/**
	 * Clear all embeddings, optionally for a specific object type.
	 *
	 * @param string $object_type Optional. If specified, clear only this type.
	 * @return int|false
	 */
	public function clear_all($object_type = '') {
		if (!empty($object_type)) {
			return $this->wpdb->delete(
				$this->table,
				array('object_type' => sanitize_key($object_type)),
				array('%s')
			);
		}

		return $this->wpdb->query("TRUNCATE TABLE {$this->table}");
	}

	/**
	 * Get post IDs that do not yet have an embedding, filtered by post types and status.
	 *
	 * @param int             $limit        Batch limit.
	 * @param int             $last_post_id Cursor pagination: return IDs > this value.
	 * @param string[]|string $post_types   Post types to index.
	 * @param string          $post_status  Post status to index.
	 * @return int[] Array of unindexed post IDs.
	 */
	public function get_unindexed_post_ids($limit = 20, $last_post_id = 0, $post_types = array('post'), $post_status = 'publish') {
		$post_types   = (array) $post_types;
		$post_types   = array_map('sanitize_key', $post_types);
		$post_status  = sanitize_key($post_status);
		$limit        = absint($limit);
		$last_post_id = absint($last_post_id);

		if (empty($post_types)) {
			$post_types = array('post');
		}

		$placeholders = implode(',', array_fill(0, count($post_types), '%s'));

		if ($last_post_id > 0) {
			$sql = $this->wpdb->prepare(
				"SELECT p.ID
				FROM {$this->wpdb->posts} p
				LEFT JOIN {$this->table} e ON p.ID = e.object_id AND e.object_type = 'post'
				WHERE p.post_type IN ($placeholders)
				AND p.post_status = %s
				AND p.ID > %d
				AND e.id IS NULL
				ORDER BY p.ID ASC
				LIMIT %d",
				...array_merge($post_types, array($post_status, $last_post_id, $limit))
			);
		} else {
			$sql = $this->wpdb->prepare(
				"SELECT p.ID
				FROM {$this->wpdb->posts} p
				LEFT JOIN {$this->table} e ON p.ID = e.object_id AND e.object_type = 'post'
				WHERE p.post_type IN ($placeholders)
				AND p.post_status = %s
				AND e.id IS NULL
				ORDER BY p.ID ASC
				LIMIT %d",
				...array_merge($post_types, array($post_status, $limit))
			);
		}

		$results = $this->wpdb->get_col($sql);
		return array_map('intval', (array) $results);
	}

	/**
	 * Count total indexed objects matching post types and status.
	 *
	 * @param string[]|string $post_types  Post types.
	 * @param string          $post_status Post status.
	 * @return int Count of indexed records.
	 */
	public function count_indexed_for_types($post_types = array('post'), $post_status = 'publish') {
		$post_types  = (array) $post_types;
		$post_types  = array_map('sanitize_key', $post_types);
		$post_status = sanitize_key($post_status);

		if (empty($post_types)) {
			$post_types = array('post');
		}

		$placeholders = implode(',', array_fill(0, count($post_types), '%s'));

		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$this->table} e
			INNER JOIN {$this->wpdb->posts} p ON e.object_id = p.ID AND e.object_type = 'post'
			WHERE p.post_type IN ($placeholders)
			AND p.post_status = %s",
			...array_merge($post_types, array($post_status))
		);

		return (int) $this->wpdb->get_var($sql);
	}

	/**
	 * Count total rows in the embeddings table.
	 *
	 * @param string $object_type      Optional entity filter.
	 * @param string $object_post_type Optional post type filter.
	 * @return int
	 */
	public function count($object_type = '', $object_post_type = '') {
		$where = array();
		$args  = array();

		if (!empty($object_type)) {
			$where[] = 'object_type = %s';
			$args[]  = sanitize_key($object_type);
		}

		if (!empty($object_post_type)) {
			$where[] = 'object_post_type = %s';
			$args[]  = sanitize_key($object_post_type);
		}

		$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
		$sql          = "SELECT COUNT(*) FROM {$this->table} {$where_clause}";

		if (!empty($args)) {
			$sql = $this->wpdb->prepare($sql, ...$args);
		}

		return (int) $this->wpdb->get_var($sql);
	}

	/**
	 * Get overall index summary statistics (counts by object_type and object_post_type, active models, dimensions).
	 *
	 * @return array Index statistics breakdown.
	 */
	public function get_stats() {
		$total_embeddings = $this->count();
		$post_embeddings  = $this->count('post');
		$topic_embeddings = $this->count('topic');

		$models = $this->wpdb->get_results(
			"SELECT model, dimensions, COUNT(*) as total_count 
			FROM {$this->table} 
			WHERE model != '' 
			GROUP BY model, dimensions"
		);

		$by_post_type = $this->wpdb->get_results(
			"SELECT object_post_type, COUNT(*) as count 
			FROM {$this->table} 
			WHERE object_type = 'post' 
			GROUP BY object_post_type"
		);

		$post_type_map = array();
		foreach ($by_post_type as $row) {
			$type = !empty($row->object_post_type) ? $row->object_post_type : 'post';
			$post_type_map[$type] = (int) $row->count;
		}

		return array(
			'total'        => $total_embeddings,
			'posts'        => $post_embeddings,
			'topics'       => $topic_embeddings,
			'by_post_type' => $post_type_map,
			'models'       => $models,
		);
	}
}
