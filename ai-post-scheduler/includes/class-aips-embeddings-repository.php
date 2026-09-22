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
	 * @var AIPS_Config Config instance.
	 */
	private $config;

	/**
	 * @var array<string, float[]> In-memory instance cache for decoded vectors.
	 */
	private $decoded_memory_cache = array();

	/**
	 * Initialize the repository.
	 *
	 * @param AIPS_Config|null $config Config instance.
	 */
	public function __construct(?AIPS_Config $config = null) {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->table  = $wpdb->prefix . 'aips_embeddings';
		$container    = AIPS_Container::get_instance();
		$this->config = $config ?: ($container->has(AIPS_Config::class) ? $container->make(AIPS_Config::class) : AIPS_Config::get_instance());
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
	 * Pack a PHP float array into a compact IEEE 754 float32 binary string.
	 *
	 * @param float[] $vector Array of float numbers.
	 * @return string Binary packed string (4 bytes per dimension).
	 */
	public function encode_embedding(array $vector) {
		if (empty($vector)) {
			return '';
		}

		return pack('f*', ...array_map('floatval', array_values($vector)));
	}

	/**
	 * Decode an embedding from either packed binary float32, JSON string, or pass-through array.
	 *
	 * Fully backward-compatible polymorphic decoder with multi-tiered caching:
	 * 1. In-memory runtime instance cache
	 * 2. AIPS_Cache ('aips_embeddings' group) when enabled
	 * 3. Individual WordPress transients (aips_ev_{type}_{id}_{hash}) with 7-day TTL
	 *
	 * Supports:
	 * 1. Packed IEEE 754 binary string (`pack('f*')`)
	 * 2. Legacy JSON string (e.g. `[0.12, 0.34, ...]`)
	 * 3. Already-decoded PHP array
	 *
	 * @param mixed  $raw          Raw embedding value from database or cache.
	 * @param string $object_type  Optional entity type ('post', 'topic', etc.).
	 * @param int    $object_id    Optional object ID.
	 * @param string $content_hash Optional content hash.
	 * @return float[] Array of float values.
	 */
	public function decode_embedding($raw, $object_type = '', $object_id = 0, $content_hash = '') {
		if (empty($raw)) {
			return array();
		}

		if (is_array($raw)) {
			return array_map('floatval', array_values($raw));
		}

		if (!is_string($raw)) {
			return array();
		}

		// 1. Build cache keys for in-memory and persistent caching
		$object_type = sanitize_key($object_type);
		$object_id   = absint($object_id);
		$hash_suffix = !empty($content_hash) ? substr($content_hash, 0, 16) : substr(md5($raw), 0, 16);

		$mem_key = (!empty($object_type) && $object_id > 0)
			? "{$object_type}_{$object_id}_{$hash_suffix}"
			: 'raw_' . $hash_suffix;

		if (isset($this->decoded_memory_cache[$mem_key])) {
			return $this->decoded_memory_cache[$mem_key];
		}

		// 2. Check persistent caches if object context is provided
		$cache_key     = '';
		$transient_key = '';
		$cache_driver  = null;

		if (!empty($object_type) && $object_id > 0) {
			$cache_key     = "vec_{$object_type}_{$object_id}_{$hash_suffix}";
			$transient_key = 'aips_ev_' . substr(md5("{$object_type}_{$object_id}_{$hash_suffix}"), 0, 32);

			if (class_exists('AIPS_Cache_Factory')) {
				$cache_driver = AIPS_Cache_Factory::instance();
				if ($cache_driver && $cache_driver->is_available()) {
					$cached = $cache_driver->get($cache_key, 'aips_embeddings');
					if (is_array($cached) && !empty($cached)) {
						$this->decoded_memory_cache[$mem_key] = $cached;
						return $cached;
					}
				}
			}

			// Fallback: WordPress transient
			$cached_transient = get_transient($transient_key);
			if (is_array($cached_transient) && !empty($cached_transient)) {
				$this->decoded_memory_cache[$mem_key] = $cached_transient;
				return $cached_transient;
			}
		}

		// 3. Decode raw embedding
		$vector  = array();
		$trimmed = ltrim($raw);
		if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
			$decoded = json_decode($trimmed, true);
			if (is_array($decoded)) {
				$vector = array_map('floatval', array_values($decoded));
			}
		} else {
			// Packed binary float32 (single precision IEEE 754)
			$unpacked = @unpack('f*', $raw);
			if (is_array($unpacked) && !empty($unpacked)) {
				$vector = array_values($unpacked);
			}
		}

		if (empty($vector)) {
			return array();
		}

		// 4. Save to in-memory and persistent caches
		$this->decoded_memory_cache[$mem_key] = $vector;

		if (!empty($object_type) && $object_id > 0) {
			$ttl = 7 * DAY_IN_SECONDS; // 7 days expiration window
			if ($cache_driver && $cache_driver->is_available()) {
				$cache_driver->set($cache_key, $vector, $ttl, 'aips_embeddings');
			} else {
				set_transient($transient_key, $vector, $ttl);
			}
		}

		return $vector;
	}

	/**
	 * Format a summary description of an embedding vector for logging and UI previews.
	 *
	 * @param mixed $raw           Raw embedding or decoded array.
	 * @param int   $preview_count Number of elements to preview.
	 * @return array{dimensions: int, preview: float[], byte_size: int, is_binary: bool}
	 */
	public function format_vector_summary($raw, $preview_count = 5) {
		$is_binary = is_string($raw) && (ltrim($raw) === '' || (ltrim($raw)[0] !== '[' && ltrim($raw)[0] !== '{'));
		$byte_size = is_string($raw) ? strlen($raw) : 0;
		$vector    = $this->decode_embedding($raw);
		$dims      = count($vector);
		$preview   = array_slice($vector, 0, max(1, (int) $preview_count));

		return array(
			'dimensions' => $dims,
			'preview'    => $preview,
			'byte_size'  => $byte_size,
			'is_binary'  => $is_binary,
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
			'embedding'        => $this->encode_embedding($embedding),
			'model'            => sanitize_text_field($model),
			'dimensions'       => $dimensions,
			'content_hash'     => sanitize_text_field($content_hash),
			'object_post_type' => $object_post_type,
			'indexed_at'       => $now,
		);

		if ($existing) {
			$res = $this->wpdb->update(
				$this->table,
				$data,
				array(
					'object_type' => $object_type,
					'object_id'   => $object_id,
				),
				array('%s', '%s', '%d', '%s', '%s', '%d'),
				array('%s', '%d')
			);
			if (false !== $res) {
				$this->invalidate_cached_vector($object_type, $object_id);
			}
			return $res;
		}

		$data['object_type'] = $object_type;
		$data['object_id']   = $object_id;

		$res = $this->wpdb->insert(
			$this->table,
			$data,
			array('%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d')
		);
		if (false !== $res) {
			$this->invalidate_cached_vector($object_type, $object_id);
		}
		return $res;
	}

	/**
	 * Delete embedding for an object.
	 *
	 * @param string $object_type Entity type.
	 * @param int    $object_id   Object ID.
	 * @return int|false
	 */
	public function delete($object_type, $object_id) {
		$res = $this->wpdb->delete(
			$this->table,
			array(
				'object_type' => sanitize_key($object_type),
				'object_id'   => absint($object_id),
			),
			array('%s', '%d')
		);
		if (false !== $res) {
			$this->invalidate_cached_vector($object_type, $object_id);
		}
		return $res;
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
		$this->flush_embeddings_cache();

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
	 * Invalidate cached vector for a specific object across memory, AIPS_Cache, and transients.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @return void
	 */
	public function invalidate_cached_vector($object_type, $object_id) {
		$object_type = sanitize_key($object_type);
		$object_id   = absint($object_id);

		// 1. Purge in-memory instance cache entries
		$prefix = "{$object_type}_{$object_id}_";
		foreach (array_keys($this->decoded_memory_cache) as $key) {
			if (strpos($key, $prefix) === 0) {
				unset($this->decoded_memory_cache[$key]);
			}
		}

		// 2. Invalidate AIPS_Cache if group exists
		if (class_exists('AIPS_Cache_Factory')) {
			$cache = AIPS_Cache_Factory::instance();
			if ($cache && $cache->is_available()) {
				// Delete common cache keys
				$cache->delete("vec_{$object_type}_{$object_id}", 'aips_embeddings');
			}
		}

		// 3. Purge related transients
		$search_pattern = '%' . $this->wpdb->esc_like("_{$object_type}_{$object_id}_") . '%';
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->wpdb->options}
				 WHERE (option_name LIKE '_transient_aips_ev_%' OR option_name LIKE '_transient_timeout_aips_ev_%')
				   AND option_name LIKE %s",
				$search_pattern
			)
		);
	}

	/**
	 * Flush the entire embeddings vector cache (AIPS_Cache group, transients, and runtime memory).
	 *
	 * @return array{success: bool, message: string, deleted_transients: int}
	 */
	public function flush_embeddings_cache(): array {
		$this->decoded_memory_cache = array();

		// Flush AIPS_Cache group
		if (class_exists('AIPS_Cache_Monitor_Service')) {
			$container = AIPS_Container::get_instance();
			$monitor_service = $container->has(AIPS_Cache_Monitor_Service::class)
				? $container->make(AIPS_Cache_Monitor_Service::class)
				: null;
			if ($monitor_service) {
				$monitor_service->flush_group('aips_embeddings');
			}
		}

		// Flush WordPress transients
		$deleted = $this->wpdb->query(
			"DELETE FROM {$this->wpdb->options} 
			 WHERE option_name LIKE '_transient_aips_ev_%' 
			    OR option_name LIKE '_transient_timeout_aips_ev_%'
			    OR option_name LIKE '_transient_aips_emb_vec_%'
			    OR option_name LIKE '_transient_timeout_aips_emb_vec_%'"
		);

		return array(
			'success'            => true,
			'message'            => sprintf(
				/* translators: %d: count of deleted transients */
				__('Embeddings vector cache flushed successfully (%d transient records purged).', 'ai-post-scheduler'),
				(int) $deleted
			),
			'deleted_transients' => (int) $deleted,
		);
	}

	/**
	 * Retrieve metrics and statistics for the embeddings vector cache.
	 *
	 * @return array<string, mixed> Embeddings cache health and stats.
	 */
	public function get_embeddings_cache_stats(): array {
		$driver_label = 'WordPress Transients';
		$cache_active = false;

		if (class_exists('AIPS_Cache_Factory')) {
			$cache = AIPS_Cache_Factory::instance();
			if ($cache && $cache->is_available()) {
				$driver_label = get_class($cache->get_driver());
				$cache_active = true;
			}
		}

		$transient_count = (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->wpdb->options} 
			 WHERE option_name LIKE '_transient_aips_ev_%' 
			    OR option_name LIKE '_transient_aips_emb_vec_%'"
		);

		return array(
			'driver'          => $driver_label,
			'is_cache_active' => $cache_active,
			'cached_vectors'  => $transient_count,
			'memory_cached'   => count($this->decoded_memory_cache),
			'ttl_days'        => 7,
		);
	}

	/**
	 * Build SQL conditions and joins for the indexing scope filter.
	 *
	 * @param string|null $scope      Scope filter ('aips_only', 'date_range', 'all'). If null, falls back to config.
	 * @param array       $scope_args Optional args (date_days, date_after).
	 * @return array{join: string, where: string, params: array}
	 */
	public function build_scope_conditions($scope = null, array $scope_args = array()) {
		if ($scope === null) {
			$scope = (string) $this->config->get_option('aips_embeddings_scope', 'aips_only');
		}

		$join   = '';
		$where  = '';
		$params = array();

		if ('aips_only' === $scope) {
			$join = "INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_aips_generated_post'";
		} elseif ('date_range' === $scope) {
			$date_after = isset($scope_args['date_after']) ? (string) $scope_args['date_after'] : (string) $this->config->get_option('aips_embeddings_date_after', '');
			$date_days  = isset($scope_args['date_days']) ? (int) $scope_args['date_days'] : (int) $this->config->get_option('aips_embeddings_date_days', 30);

			if (!empty($date_after)) {
				$where    = "AND p.post_date >= %s";
				$params[] = $date_after . ' 00:00:00';
			} elseif ($date_days > 0) {
				$cutoff   = gmdate('Y-m-d H:i:s', time() - ($date_days * DAY_IN_SECONDS));
				$where    = "AND p.post_date >= %s";
				$params[] = $cutoff;
			}
		}

		return array(
			'join'   => $join,
			'where'  => $where,
			'params' => $params,
		);
	}

	/**
	 * Get post IDs that do not yet have an embedding, filtered by post types, status, and scope.
	 *
	 * @param int             $limit        Batch limit.
	 * @param int             $last_post_id Cursor pagination: return IDs > this value.
	 * @param string[]|string $post_types   Post types to index.
	 * @param string          $post_status  Post status to index.
	 * @param string|null     $scope        Scope filter ('aips_only', 'date_range', 'all').
	 * @param array           $scope_args   Scope arguments (date_days, date_after).
	 * @return int[] Array of unindexed post IDs.
	 */
	public function get_unindexed_post_ids($limit = 20, $last_post_id = 0, $post_types = array('post'), $post_status = 'publish', $scope = null, array $scope_args = array()) {
		$post_types   = (array) $post_types;
		$post_types   = array_map('sanitize_key', $post_types);
		$post_status  = sanitize_key($post_status);
		$limit        = absint($limit);
		$last_post_id = absint($last_post_id);

		if (empty($post_types)) {
			$post_types = array('post');
		}

		$placeholders = implode(',', array_fill(0, count($post_types), '%s'));

		$scope_clause = $this->build_scope_conditions($scope, $scope_args);
		$join_sql     = $scope_clause['join'];
		$where_sql    = $scope_clause['where'];
		$scope_params = $scope_clause['params'];

		if ($last_post_id > 0) {
			$params = array_merge($post_types, array($post_status, $last_post_id), $scope_params, array($limit));
			$sql = $this->wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$this->wpdb->posts} p
				{$join_sql}
				LEFT JOIN {$this->table} e ON p.ID = e.object_id AND e.object_type = 'post'
				WHERE p.post_type IN ($placeholders)
				AND p.post_status = %s
				AND p.ID > %d
				{$where_sql}
				AND e.id IS NULL
				ORDER BY p.ID ASC
				LIMIT %d",
				...$params
			);
		} else {
			$params = array_merge($post_types, array($post_status), $scope_params, array($limit));
			$sql = $this->wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$this->wpdb->posts} p
				{$join_sql}
				LEFT JOIN {$this->table} e ON p.ID = e.object_id AND e.object_type = 'post'
				WHERE p.post_type IN ($placeholders)
				AND p.post_status = %s
				{$where_sql}
				AND e.id IS NULL
				ORDER BY p.ID ASC
				LIMIT %d",
				...$params
			);
		}

		$results = $this->wpdb->get_col($sql);
		return array_map('intval', (array) $results);
	}

	/**
	 * Count total indexed objects matching post types, status, and scope.
	 *
	 * @param string[]|string $post_types  Post types.
	 * @param string          $post_status Post status.
	 * @param string|null     $scope       Scope filter ('aips_only', 'date_range', 'all').
	 * @param array           $scope_args  Scope arguments.
	 * @return int Count of indexed records.
	 */
	public function count_indexed_for_types($post_types = array('post'), $post_status = 'publish', $scope = null, array $scope_args = array()) {
		$post_types  = (array) $post_types;
		$post_types  = array_map('sanitize_key', $post_types);
		$post_status = sanitize_key($post_status);

		if (empty($post_types)) {
			$post_types = array('post');
		}

		$placeholders = implode(',', array_fill(0, count($post_types), '%s'));

		$scope_clause = $this->build_scope_conditions($scope, $scope_args);
		$join_sql     = $scope_clause['join'];
		$where_sql    = $scope_clause['where'];
		$scope_params = $scope_clause['params'];

		$params = array_merge($post_types, array($post_status), $scope_params);

		$sql = $this->wpdb->prepare(
			"SELECT COUNT(DISTINCT e.id)
			FROM {$this->table} e
			INNER JOIN {$this->wpdb->posts} p ON e.object_id = p.ID AND e.object_type = 'post'
			{$join_sql}
			WHERE p.post_type IN ($placeholders)
			AND p.post_status = %s
			{$where_sql}",
			...$params
		);

		return (int) $this->wpdb->get_var($sql);
	}

	/**
	 * Count total WordPress posts matching post types, status, and scope.
	 *
	 * @param string[]|string $post_types  Post types.
	 * @param string          $post_status Post status.
	 * @param string|null     $scope       Scope filter ('aips_only', 'date_range', 'all').
	 * @param array           $scope_args  Scope arguments.
	 * @return int Total posts within scope.
	 */
	public function count_total_posts_for_scope($post_types = array('post'), $post_status = 'publish', $scope = null, array $scope_args = array()) {
		$post_types  = (array) $post_types;
		$post_types  = array_map('sanitize_key', $post_types);
		$post_status = sanitize_key($post_status);

		if (empty($post_types)) {
			$post_types = array('post');
		}

		$placeholders = implode(',', array_fill(0, count($post_types), '%s'));

		$scope_clause = $this->build_scope_conditions($scope, $scope_args);
		$join_sql     = $scope_clause['join'];
		$where_sql    = $scope_clause['where'];
		$scope_params = $scope_clause['params'];

		$params = array_merge($post_types, array($post_status), $scope_params);

		$sql = $this->wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$this->wpdb->posts} p
			{$join_sql}
			WHERE p.post_type IN ($placeholders)
			AND p.post_status = %s
			{$where_sql}",
			...$params
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
		$results = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT object_id FROM {$this->table} WHERE object_type = 'post' ORDER BY object_id ASC"
			)
		);

		return array_map('intval', (array) $results);
	}

	/**
	 * Get post embeddings for similarity search by post type and status.
	 * Returns row objects with post_id and embedding properties.
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
		$results = $this->wpdb->get_col(
			"SELECT DISTINCT dimensions FROM {$this->table} WHERE dimensions > 0 ORDER BY dimensions ASC"
		);

		return array_map('intval', (array) $results);
	}

	/**
	 * Get unindexed topic IDs up to a given limit.
	 *
	 * @param int $limit Maximum topic IDs to retrieve. Default 50.
	 * @return int[] Array of unindexed topic IDs.
	 */
	public function get_unindexed_topic_ids(int $limit = 50): array {
		$topics_table = $this->wpdb->prefix . 'aips_author_topics';
		$limit = max(1, min(500, absint($limit)));

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT t.id 
				 FROM {$topics_table} t
				 LEFT JOIN {$this->table} e ON t.id = e.object_id AND e.object_type = 'topic'
				 WHERE e.id IS NULL 
				   AND t.status IN ('pending', 'approved', 'used')
				 ORDER BY t.id ASC
				 LIMIT %d",
				$limit
			)
		);

		return array_map('intval', (array) $results);
	}

	/**
	 * Get total count of unindexed topics.
	 *
	 * @return int
	 */
	public function get_unindexed_topic_count(): int {
		$topics_table = $this->wpdb->prefix . 'aips_author_topics';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $this->wpdb->get_var(
			"SELECT COUNT(*) 
			 FROM {$topics_table} t
			 LEFT JOIN {$this->table} e ON t.id = e.object_id AND e.object_type = 'topic'
			 WHERE e.id IS NULL 
			   AND t.status IN ('pending', 'approved', 'used')"
		);

		return absint($count);
	}

	/**
	 * Get total count of active topics (pending, approved, used).
	 *
	 * @return int
	 */
	public function get_total_topic_count(): int {
		$topics_table = $this->wpdb->prefix . 'aips_author_topics';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $this->wpdb->get_var(
			"SELECT COUNT(*) 
			 FROM {$topics_table} 
			 WHERE status IN ('pending', 'approved', 'used')"
		);

		return absint($count);
	}

	/**
	 * Get all indexed topic IDs.
	 *
	 * @return int[]
	 */
	public function get_all_indexed_topic_ids(): array {
		$results = $this->wpdb->get_col(
			"SELECT object_id FROM {$this->table} WHERE object_type = 'topic' ORDER BY object_id ASC"
		);

		return array_map('intval', (array) $results);
	}

	/**
	 * Get all indexed topics with their embeddings for similarity searches.
	 *
	 * @return object[] Array of topic objects with topic_id, topic_title, author_id, status, and embedding.
	 */
	public function get_all_topics_for_similarity(): array {
		$topics_table = $this->wpdb->prefix . 'aips_author_topics';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $this->wpdb->get_results(
			"SELECT e.object_id AS topic_id, t.topic_title, t.author_id, t.status, e.embedding
			 FROM {$this->table} e
			 INNER JOIN {$topics_table} t ON e.object_id = t.id
			 WHERE e.object_type = 'topic'
			   AND t.status IN ('pending', 'approved', 'used')
			 ORDER BY e.object_id ASC"
		);
	}
}
