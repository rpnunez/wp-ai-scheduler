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

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

if (!trait_exists('AIPS_Repository_Tables')) {
	require_once __DIR__ . '/trait-aips-repository-tables.php';
}

/**
 * Class AIPS_Embeddings_Repository
 *
 * Manages CRUD operations for the aips_embeddings table.
 *
 * Caching model (see #2053):
 *
 * - Reads over the embeddings table alone are cached under the broad
 *   `embeddings` tag, which every write (upsert / delete / clear_all) bumps.
 * - Reads that JOIN wp_posts on post_status / post_type additionally carry the
 *   `embeddings_posts` tag. Native WordPress flows (trash, publish, delete)
 *   never call this repository, so AIPS_Embeddings_Cache_Invalidator bumps
 *   `embeddings_posts` from transition_post_status / deleted_post.
 * - get_all_for_similarity() / get_all_for_similarity_by_type() are left
 *   uncached: they return every stored vector as JSON, so the serialized
 *   payload grows to megabytes on real sites and a cache miss would cost more
 *   than the query it replaces.
 * - get_unindexed_post_ids() is left uncached: it is the cursor that drives
 *   the Content Indexer backfill queue, and a stale page would hand the same
 *   batch to two ticks.
 */
class AIPS_Embeddings_Repository {

	use AIPS_Cacheable_Repository;
	use AIPS_Repository_Tables;

	/**
	 * Broad cache tag carried by every cached read and bumped by every write.
	 */
	const CACHE_TAG = 'embeddings';

	/**
	 * Cache tag carried by reads that join wp_posts; bumped on post transitions.
	 */
	const CACHE_TAG_POSTS = 'embeddings_posts';

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
		// table() is the AIPS_Repository_Tables trait method; $this->table is the
		// cached prefixed name used inline in SQL below.
		$this->table = $this->table('aips_embeddings');
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

		return $this->cache_read(
			'embeddings.get_by_object',
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
			),
			function() use ($object_type, $object_id) {
				return $this->wpdb->get_row(
					$this->wpdb->prepare(
						"SELECT * FROM {$this->table} WHERE object_type = %s AND object_id = %d LIMIT 1",
						$object_type,
						$object_id
					)
				);
			}
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

		$post_ids = array_map('absint', $post_ids);
		$post_ids = array_values(array_unique($post_ids));
		sort($post_ids); // Order-independent result; sort so equal ID sets share a cache key.

		return $this->cache_read(
			'embeddings.get_by_post_ids',
			array(
				'post_ids' => $post_ids,
			),
			function() use ($post_ids) {
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
		);
	}

	/**
	 * Get all embeddings for similarity comparison, optionally filtered by post types and status.
	 *
	 * Deliberately uncached: returns every stored vector, so the payload is far
	 * too large to serialize into the repository cache profitably.
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
				...array_values(array_merge($post_types, array($post_status)))
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

		// The insert-vs-update decision must never be made from a cached row: a
		// stale positive (row removed by TRUNCATE/migration without a tag bump)
		// would turn the insert into a no-op update. Read straight from the DB.
		$existing = $this->without_repository_cache(function() use ($object_type, $object_id) {
			return $this->get_by_object($object_type, $object_id);
		});

		$data = array(
			'embedding'        => wp_json_encode($embedding),
			'model'            => sanitize_text_field($model),
			'dimensions'       => $dimensions,
			'content_hash'     => sanitize_text_field($content_hash),
			'object_post_type' => $object_post_type,
			'indexed_at'       => $now,
		);

		if ($existing) {
			$result = $this->wpdb->update(
				$this->table,
				$data,
				array(
					'object_type' => $object_type,
					'object_id'   => $object_id,
				),
				array('%s', '%s', '%d', '%s', '%s', '%d'),
				array('%s', '%d')
			);

			if (false !== $result) {
				$this->invalidate_embeddings_cache('embedding_updated');
			}

			return $result;
		}

		$data['object_type'] = $object_type;
		$data['object_id']   = $object_id;

		$result = $this->wpdb->insert(
			$this->table,
			$data,
			array('%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d')
		);

		if (false !== $result) {
			$this->invalidate_embeddings_cache('embedding_inserted');
		}

		return $result;
	}

	/**
	 * Delete embedding for an object.
	 *
	 * @param string $object_type Entity type.
	 * @param int    $object_id   Object ID.
	 * @return int|false
	 */
	public function delete($object_type, $object_id) {
		$result = $this->wpdb->delete(
			$this->table,
			array(
				'object_type' => sanitize_key($object_type),
				'object_id'   => absint($object_id),
			),
			array('%s', '%d')
		);

		if ($result) {
			$this->invalidate_embeddings_cache('embedding_deleted');
		}

		return $result;
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
			$result = $this->wpdb->delete(
				$this->table,
				array('object_type' => sanitize_key($object_type)),
				array('%s')
			);
		} else {
			$result = $this->wpdb->query("TRUNCATE TABLE {$this->table}");
		}

		// TRUNCATE returns 0 rows on success and delete() returns 0 when the
		// table is already empty; both leave the table in a known-empty state,
		// so invalidate on anything but an outright failure.
		if (false !== $result) {
			$this->invalidate_embeddings_cache('embeddings_cleared');
		}

		return $result;
	}

	/**
	 * Get post IDs that do not yet have an embedding, filtered by post types and status.
	 *
	 * Deliberately uncached: this is the cursor that drives the Content Indexer
	 * backfill queue (progressive AJAX chunks and the WP-Cron worker). A stale
	 * page would hand the same post IDs to two consecutive ticks.
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
	 * Joins wp_posts, so the cached value carries CACHE_TAG_POSTS in addition to
	 * the broad tag; AIPS_Embeddings_Cache_Invalidator bumps it on post
	 * transitions and deletions.
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

		$post_types = array_values(array_unique($post_types));
		sort($post_types); // Order-independent result; sort so equal type sets share a cache key.

		return (int) $this->cache_read(
			'embeddings.count_indexed_for_types',
			array(
				'post_types'  => $post_types,
				'post_status' => $post_status,
			),
			function() use ($post_types, $post_status) {
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
		);
	}

	/**
	 * Count total rows in the embeddings table.
	 *
	 * @param string $object_type      Optional entity filter.
	 * @param string $object_post_type Optional post type filter.
	 * @return int
	 */
	public function count($object_type = '', $object_post_type = '') {
		// Filters apply when the raw argument is non-empty (pre-sanitization), which
		// preserves the original query semantics exactly; the sanitized values are
		// what both the cache key and the SQL see.
		$filter_object_type      = !empty($object_type);
		$filter_object_post_type = !empty($object_post_type);
		$object_type             = $filter_object_type ? sanitize_key($object_type) : '';
		$object_post_type        = $filter_object_post_type ? sanitize_key($object_post_type) : '';

		return (int) $this->cache_read(
			'embeddings.count',
			array(
				'object_type'             => $object_type,
				'object_post_type'        => $object_post_type,
				'filter_object_type'      => $filter_object_type,
				'filter_object_post_type' => $filter_object_post_type,
			),
			function() use ($object_type, $object_post_type, $filter_object_type, $filter_object_post_type) {
				$where = array();
				$args  = array();

				if ($filter_object_type) {
					$where[] = 'object_type = %s';
					$args[]  = $object_type;
				}

				if ($filter_object_post_type) {
					$where[] = 'object_post_type = %s';
					$args[]  = $object_post_type;
				}

				$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
				$sql          = "SELECT COUNT(*) FROM {$this->table} {$where_clause}";

				if (!empty($args)) {
					$sql = $this->wpdb->prepare($sql, ...$args);
				}

				return (int) $this->wpdb->get_var($sql);
			}
		);
	}

	/**
	 * Get overall index summary statistics (counts by object_type and object_post_type, active models, dimensions).
	 *
	 * @return array Index statistics breakdown.
	 */
	public function get_stats() {
		return $this->cache_read(
			'embeddings.get_stats',
			array(),
			function() {
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
		);
	}

	/**
	 * Get total count of indexed records for a given object type.
	 *
	 * @param string $object_type Object type ('post', 'topic'). Default 'post'.
	 * @return int
	 */
	public function get_total_indexed($object_type = 'post') {
		return $this->count($object_type);
	}

	/**
	 * Get all indexed post IDs.
	 *
	 * @return int[]
	 */
	public function get_all_indexed_post_ids() {
		return $this->cache_read(
			'embeddings.get_all_indexed_post_ids',
			array(),
			function() {
				$results = $this->wpdb->get_col(
					"SELECT object_id FROM {$this->table} WHERE object_type = 'post' ORDER BY object_id ASC"
				);

				return array_map('intval', (array) $results);
			}
		);
	}

	/**
	 * Get post embeddings for similarity search by post type and status.
	 * Returns row objects with post_id and embedding properties.
	 *
	 * Deliberately uncached for the same payload-size reason as
	 * get_all_for_similarity().
	 *
	 * @param string $post_type   Post type (default: 'post').
	 * @param string $post_status Post status (default: 'publish').
	 * @return object[] Array of rows with post_id and embedding columns.
	 */
	public function get_all_for_similarity_by_type($post_type = 'post', $post_status = 'publish') {
		$post_type   = sanitize_key($post_type);
		$post_status = sanitize_key($post_status);

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT e.object_id AS post_id, e.embedding
				FROM {$this->table} e
				INNER JOIN {$this->wpdb->posts} p ON e.object_id = p.ID
				WHERE e.object_type = 'post'
				AND p.post_type = %s
				AND p.post_status = %s
				ORDER BY e.object_id ASC",
				$post_type,
				$post_status
			)
		);
	}

	/**
	 * Get distinct vector dimensions currently stored across all indexed objects.
	 *
	 * @return int[] Array of distinct dimension integers.
	 */
	public function get_stored_dimensions() {
		return $this->cache_read(
			'embeddings.get_stored_dimensions',
			array(),
			function() {
				$results = $this->wpdb->get_col(
					"SELECT DISTINCT dimensions FROM {$this->table} WHERE dimensions > 0 ORDER BY dimensions ASC"
				);

				return array_map('intval', (array) $results);
			}
		);
	}

	/**
	 * Invalidate cached reads that depend on wp_posts state.
	 *
	 * Called by AIPS_Embeddings_Cache_Invalidator when a post changes status,
	 * type, or is deleted through native WordPress flows that never touch this
	 * repository. Only reads tagged CACHE_TAG_POSTS are affected.
	 *
	 * @param string $reason Invalidation reason for cache telemetry.
	 * @return void
	 */
	public function invalidate_post_dependent_reads($reason = 'post_changed') {
		$this->invalidate_cache_tags(array(self::CACHE_TAG_POSTS), (string) $reason);
	}

	/**
	 * Return the repository cache group for embeddings reads.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_embeddings';
	}

	/**
	 * Return the explicit repository cache policies for embeddings reads.
	 *
	 * Every policy carries the broad `embeddings` tag so a single bump on any
	 * write invalidates all cached reads. `count_indexed_for_types` additionally
	 * carries `embeddings_posts` because it joins wp_posts.
	 *
	 * Operations without a policy (get_all_for_similarity,
	 * get_all_for_similarity_by_type, get_unindexed_post_ids) are intentionally
	 * uncached; see the class docblock.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'embeddings.get_by_object' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array(self::CACHE_TAG),
				'cache_null'  => false,
				'description' => 'Cache single embedding lookups by (object_type, object_id); hot on related-posts render and dedup gates.',
			),
			'embeddings.get_by_post_ids' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array(self::CACHE_TAG),
				'description' => 'Cache batched post embedding lookups.',
			),
			'embeddings.count' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array(self::CACHE_TAG),
				'description' => 'Cache embeddings row counts by object type / post type.',
			),
			'embeddings.count_indexed_for_types' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array(self::CACHE_TAG, self::CACHE_TAG_POSTS),
				'description' => 'Cache indexed-post counts joined to wp_posts; invalidated by writes and by post transitions.',
			),
			'embeddings.get_stats' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array(self::CACHE_TAG),
				'description' => 'Cache the Content Indexer dashboard statistics breakdown.',
			),
			'embeddings.get_all_indexed_post_ids' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array(self::CACHE_TAG),
				'description' => 'Cache the ordered list of indexed post IDs.',
			),
			'embeddings.get_stored_dimensions' => array(
				'tier'        => 'medium',
				'ttl'         => 300,
				'tags'        => array(self::CACHE_TAG),
				'description' => 'Cache the distinct vector dimensions present in the index.',
			),
		);
	}

	/**
	 * Invalidate every cached embeddings read after a write.
	 *
	 * Bumps the broad `embeddings` tag present on every cached read. Writes do
	 * not need to bump `embeddings_posts` separately because every read carrying
	 * that tag also carries the broad tag.
	 *
	 * @param string $reason Invalidation reason.
	 * @return void
	 */
	private function invalidate_embeddings_cache($reason) {
		$this->invalidate_cache_tags(array(self::CACHE_TAG), (string) $reason);
	}
}
