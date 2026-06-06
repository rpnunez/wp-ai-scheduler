<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Index
 *
 * Maintains a plugin-owned metadata index for every value written via
 * AIPS_Cache::set(). This makes cache entries enumerable even when the
 * underlying driver (for example, WP Object Cache) cannot list its keys.
 *
 * The index stores metadata only — never the cached value. Writes are
 * fire-and-forget: any DB error is silently swallowed so that a failing
 * index never blocks a cache write.
 *
 * @package AI_Post_Scheduler
 * @since   2.9.0
 */
class AIPS_Cache_Index {

	/**
	 * Whether the index is currently enabled.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Maximum number of rows to keep in aips_cache_index.
	 *
	 * @var int
	 */
	private $max_entries;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Use get_option() directly to avoid routing through AIPS_Config's internal
		// AIPS_Cache instance. AIPS_Config::get_option() with a non-null default
		// always calls config_cache->set() after reading from the DB, which would
		// trigger the index on the config cache, which calls upsert_index_row(),
		// which calls get_option() again — producing infinite recursion.
		$enabled           = get_option( 'aips_cache_monitor_index_enabled', '1' );
		$this->enabled     = ( $enabled !== '0' && $enabled !== 0 && $enabled !== false );
		$this->max_entries = (int) get_option( 'aips_cache_monitor_max_index_entries', 10000 );
	}

	// -----------------------------------------------------------------------
	// Public API (called by AIPS_Cache hooks)
	// -----------------------------------------------------------------------

	/**
	 * Record or update a cache write in the index.
	 *
	 * @param string $key      Raw cache key.
	 * @param mixed  $value    Cached value (used only to determine type/size).
	 * @param int    $ttl      TTL in seconds (0 = no expiration).
	 * @param string $group    Cache group.
	 * @param array  $context  Optional context metadata passed by AIPS_Cache::set().
	 * @return void
	 */
	public function record_set( string $key, $value, int $ttl, string $group, array $context = array() ): void {
		if (!$this->enabled) {
			return;
		}

		try {
			$this->upsert_index_row( $key, $value, $ttl, $group, $context );
			$this->enforce_max_entries();
		} catch ( Throwable $e ) {
			// Index errors must never break cache writes.
		}
	}

	/**
	 * Remove an entry from the index on cache delete.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return void
	 */
	public function record_delete( string $key, string $group ): void {
		if (!$this->enabled) {
			return;
		}

		try {
			global $wpdb;
			$table    = $wpdb->prefix . 'aips_cache_index';
			$key_hash = hash( 'sha256', $group . ':' . $key );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $table, array( 'key_hash' => $key_hash ), array( '%s' ) );
		} catch ( Throwable $e ) {
			// Swallow.
		}
	}

	/**
	 * Clear all index rows (called on full flush).
	 *
	 * @return void
	 */
	public function record_flush(): void {
		if (!$this->enabled) {
			return;
		}

		try {
			global $wpdb;
			$table = $wpdb->prefix . 'aips_cache_index';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "TRUNCATE TABLE `{$table}`" );
		} catch ( Throwable $e ) {
			// Swallow.
		}
	}

	/**
	 * Update the last_accessed_at timestamp for an index entry on cache read.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return void
	 */
	public function record_access( string $key, string $group ): void {
		if (!$this->enabled) {
			return;
		}

		try {
			global $wpdb;
			$table    = $wpdb->prefix . 'aips_cache_index';
			$key_hash = hash( 'sha256', $group . ':' . $key );
			$now      = AIPS_DateTime::now()->timestamp();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, array( 'last_accessed_at' => $now ), array( 'key_hash' => $key_hash ), array( '%d' ), array( '%s' ) );
		} catch ( Throwable $e ) {
			// Swallow.
		}
	}

	/**
	 * Prune expired index entries.
	 *
	 * @return int Number of rows deleted.
	 */
	public function prune_expired(): int {
		try {
			global $wpdb;
			$table = $wpdb->prefix . 'aips_cache_index';
			$now   = AIPS_DateTime::now()->timestamp();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $wpdb->query(
				$wpdb->prepare( "DELETE FROM `{$table}` WHERE expires_at > 0 AND expires_at < %d", $now )
			);

			return (int) $deleted;
		} catch ( Throwable $e ) {
			return 0;
		}
	}

	/**
	 * Prune orphaned index entries (entries whose key no longer exists in the
	 * cache table when using the DB driver).
	 *
	 * Only runs when the configured driver is 'db' since other drivers cannot
	 * be cross-checked safely.
	 *
	 * @return int Number of rows deleted.
	 */
	public function prune_orphans(): int {
		// Use get_option() directly — see constructor comment.
		$driver = get_option( 'aips_cache_driver', 'array' );

		if ($driver !== 'db') {
			return 0;
		}

		try {
			global $wpdb;
			$index_table = $wpdb->prefix . 'aips_cache_index';
			$cache_table = $wpdb->prefix . 'aips_cache';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $wpdb->query(
				"DELETE ci FROM `{$index_table}` ci
				 LEFT JOIN `{$cache_table}` c ON c.cache_group = ci.cache_group AND (c.cache_key = ci.cache_key OR c.cache_key LIKE CONCAT('%:', REPLACE(REPLACE(REPLACE(ci.cache_key, '\\\\', '\\\\\\\\'), '%', '\\\\%'), '_', '\\\\_')) ESCAPE '\\')
				 WHERE c.cache_key IS NULL"
			);

			return (int) $deleted;
		} catch ( Throwable $e ) {
			return 0;
		}
	}

	/**
	 * Rebuild the index from the DB cache table.
	 *
	 * Only safe when driver is 'db'. For other drivers this is a no-op.
	 *
	 * @return int Number of rows inserted/updated.
	 */
	public function rebuild_from_db(): int {
		// Use get_option() directly — see constructor comment.
		$driver = get_option( 'aips_cache_driver', 'array' );

		if ($driver !== 'db') {
			return 0;
		}

		try {
			global $wpdb;
			$index_table = $wpdb->prefix . 'aips_cache_index';
			$cache_table = $wpdb->prefix . 'aips_cache';
			$now         = AIPS_DateTime::now()->timestamp();
			$limit       = 1000;
			$offset      = 0;
			$inserted = 0;

			do {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT cache_key, cache_group, expires_at, updated_at, LENGTH(value) as value_size FROM `{$cache_table}` LIMIT %d OFFSET %d",
						$limit,
						$offset
					),
					ARRAY_A
				);

				if (empty( $rows ) || !is_array( $rows )) {
					break;
				}

				foreach ($rows as $row) {
					$cache_key = $this->normalize_db_cache_key( (string) $row['cache_key'] );
					$composite = $row['cache_group'] . ':' . $cache_key;
					$key_hash  = hash( 'sha256', $composite );
					$expires   = (int) $row['expires_at'];
					$ttl       = $expires > 0 ? max( 0, $expires - $now ) : 0;

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$result = $wpdb->replace(
						$index_table,
						array(
							'cache_key'       => $cache_key,
							'key_hash'        => $key_hash,
							'cache_group'     => $row['cache_group'],
							'driver'          => 'db',
							'tier'            => '',
							'operation_id'    => '',
							'repository_class' => '',
							'tags'            => '',
							'domain'          => '',
							'ttl'             => $ttl,
							'created_at'      => (int) ($row['updated_at'] ?: $now),
							'updated_at'      => (int) ($row['updated_at'] ?: $now),
							'expires_at'      => $expires,
							'value_size'      => (int) $row['value_size'],
							'value_type'      => '',
							'last_accessed_at' => 0,
						),
						array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d' )
					);

					if (false !== $result) {
						$inserted++;
					}
				}

				$offset += $limit;
			} while ( count( $rows ) === $limit );

			return $inserted;
		} catch ( Throwable $e ) {
			return 0;
		}
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Insert or update a single index row.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $value   Cached value.
	 * @param int    $ttl     TTL in seconds.
	 * @param string $group   Cache group.
	 * @param array  $context Additional context metadata.
	 * @return void
	 */
	private function upsert_index_row( string $key, $value, int $ttl, string $group, array $context ): void {
		global $wpdb;

		$table     = $wpdb->prefix . 'aips_cache_index';
		$composite = $group . ':' . $key;
		$key_hash  = hash( 'sha256', $composite );
		$now       = AIPS_DateTime::now()->timestamp();
		$expires   = $ttl > 0 ? $now + $ttl : 0;

		$value_size  = $this->estimate_value_size( $value );
		$value_type  = $this->resolve_value_type( $value );
		// Use get_option() directly to avoid routing through AIPS_Config's internal
		// AIPS_Cache instance, which would trigger record_set() recursively.
		$driver_name = get_option( 'aips_cache_driver', 'array' );

		$tags_raw   = isset( $context['tags'] ) && is_array( $context['tags'] ) ? implode( ',', $context['tags'] ) : '';
		$tier       = isset( $context['tier'] ) ? sanitize_key( $context['tier'] ) : '';
		$op_id      = isset( $context['operation_id'] ) ? sanitize_text_field( $context['operation_id'] ) : '';
		$repo_class = isset( $context['repository_class'] ) ? sanitize_text_field( $context['repository_class'] ) : '';
		$domain     = isset( $context['domain'] ) ? sanitize_text_field( $context['domain'] ) : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$table,
			array(
				'cache_key'        => (string) $key,
				'key_hash'         => $key_hash,
				'cache_group'      => $group,
				'driver'           => (string) $driver_name,
				'tier'             => $tier,
				'operation_id'     => $op_id,
				'repository_class' => $repo_class,
				'tags'             => $tags_raw,
				'domain'           => $domain,
				'ttl'              => $ttl,
				'created_at'       => $now,
				'updated_at'       => $now,
				'expires_at'       => $expires,
				'value_size'       => $value_size,
				'value_type'       => $value_type,
				'last_accessed_at' => 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d' )
		);
	}

	/**
	 * Determine a display-friendly PHP type label for a cached value.
	 *
	 * @param mixed $value The cached value.
	 * @return string
	 */
	private function resolve_value_type( $value ): string {
		if (is_null( $value )) {
			return 'null';
		}
		if (is_bool( $value )) {
			return 'bool';
		}
		if (is_int( $value )) {
			return 'int';
		}
		if (is_float( $value )) {
			return 'float';
		}
		if (is_string( $value )) {
			return 'string';
		}
		if (is_array( $value )) {
			return 'array[' . count( $value ) . ']';
		}
		if (is_object( $value )) {
			return 'object:' . get_class( $value );
		}
		return gettype( $value );
	}

	/**
	 * Return a memory-safe byte-size estimate for a cached value.
	 *
	 * Calling maybe_serialize() on large arrays or deep object graphs to
	 * measure their serialized length can double peak memory usage and, when
	 * cache writes are frequent (or recursive through AIPS_Config), exhaust
	 * the PHP memory limit.  Because the size column is used only for display
	 * in the Cache Monitor, an approximation is acceptable.
	 *
	 * @param mixed $value The cached value.
	 * @return int Estimated byte size.
	 */
	private function estimate_value_size( $value ): int {
		if (is_string( $value )) {
			// Exact for strings — the most common cached type (HTML, JSON, etc.).
			return strlen( $value );
		}
		if (is_int( $value ) || is_float( $value )) {
			// PHP floats are always 8 bytes (IEEE 754 double) regardless of platform.
			return 8;
		}
		if (is_bool( $value ) || is_null( $value )) {
			return 1;
		}
		if (is_array( $value )) {
			// Rough estimate: avoid full serialization of potentially huge arrays.
			// 64 bytes per element is a conservative approximation.
			return count( $value ) * 64;
		}
		// Objects and other types: skip expensive serialization.
		return 0;
	}

	/**
	 * Trim the index table to max_entries by removing the oldest rows.
	 *
	 * @return void
	 */
	private function enforce_max_entries(): void {
		if ($this->max_entries <= 0) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'aips_cache_index';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

		if ($count <= $this->max_entries) {
			return;
		}

		$excess = $count - $this->max_entries;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM `{$table}` ORDER BY updated_at ASC LIMIT %d", $excess )
		);
	}

	/**
	 * Remove the configured global DB prefix from a persisted cache key.
	 *
	 * @param string $cache_key Persisted DB cache key.
	 * @return string
	 */
	private function normalize_db_cache_key( string $cache_key ): string {
		$prefix = (string) get_option( 'aips_cache_db_prefix', '' );
		if ('' !== $prefix) {
			$needle = $prefix . ':';
			if (strpos( $cache_key, $needle ) === 0) {
				return substr( $cache_key, strlen( $needle ) );
			}
		}

		return $cache_key;
	}
}
