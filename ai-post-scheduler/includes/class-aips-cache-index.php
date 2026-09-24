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
	 * Memoized result of the aips_cache_index table-existence check.
	 *
	 * Null until first resolved, then true/false for the remainder of the
	 * request. Guards every DB operation so that cache writes triggered before
	 * the table is installed (for example, during plugin activation before
	 * install_tables() runs) no-op silently instead of emitting DB errors.
	 *
	 * @var bool|null
	 */
	private $table_exists = null;

	/**
	 * In-memory buffer for writes awaiting flush on shutdown.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $pending_writes = array();

	/**
	 * In-memory buffer for access timestamps awaiting flush on shutdown.
	 *
	 * @var array<string, int>
	 */
	private $pending_access = array();

	/**
	 * In-memory map of key_hash to cache_group for pending access timestamps.
	 *
	 * @var array<string, string>
	 */
	private $pending_access_groups = array();

	/**
	 * Whether the shutdown hook has been registered for this request.
	 *
	 * @var bool
	 */
	private $shutdown_registered = false;

	/**
	 * Buffered entry count (writes + accesses) that triggers an early flush.
	 *
	 * Long-running requests (cron batches) would otherwise accumulate an
	 * unbounded buffer until shutdown.
	 */
	const BUFFER_FLUSH_THRESHOLD = 200;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Use get_option() directly to avoid routing through AIPS_Config's internal
		// AIPS_Cache instance. AIPS_Config::get_option() with a non-null default
		// always calls config_cache->set() after reading from the DB, which would
		// trigger the index on the config cache (record_set() -> prepare_index_row_data()),
		// which previously called back into AIPS_Config::get_option() and recursed.
		$enabled           = get_option( 'aips_cache_monitor_index_enabled', '1' );
		$this->enabled     = ( $enabled !== '0' && $enabled !== 0 && $enabled !== false );
		$this->max_entries = (int) get_option( 'aips_cache_monitor_max_index_entries', 10000 );
	}

	/**
	 * Register the WordPress shutdown hook to flush buffered telemetry in bulk.
	 *
	 * @return void
	 */
	private function register_shutdown_hook(): void {
		if ( $this->shutdown_registered ) {
			return;
		}
		$this->shutdown_registered = true;
		add_action( 'shutdown', array( $this, 'flush_buffer' ) );
	}

	// -----------------------------------------------------------------------
	// Public API (called by AIPS_Cache hooks)
	// -----------------------------------------------------------------------

	/**
	 * Record or update a cache write in the index.
	 *
	 * Buffers the write in memory and flushes to the database on request shutdown
	 * to prevent blocking runtime cache execution.
	 *
	 * @param string $key      Raw cache key.
	 * @param mixed  $value    Cached value (used only to determine type/size).
	 * @param int    $ttl      TTL in seconds (0 = no expiration).
	 * @param string $group    Cache group.
	 * @param array  $context  Optional context metadata passed by AIPS_Cache::set().
	 * @return void
	 */
	public function record_set( string $key, $value, int $ttl, string $group, array $context = array() ): void {
		if (!$this->enabled || !$this->table_ready()) {
			return;
		}

		try {
			$composite = $group . ':' . $key;
			$this->pending_writes[ $composite ] = $this->prepare_index_row_data( $key, $value, $ttl, $group, $context );
			$this->register_shutdown_hook();
			$this->maybe_flush_early();
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
		$composite = $group . ':' . $key;
		$key_hash  = hash( 'sha256', $composite );
		unset( $this->pending_writes[ $composite ], $this->pending_access[ $key_hash ], $this->pending_access_groups[ $key_hash ] );

		if (!$this->enabled || !$this->table_ready()) {
			return;
		}

		try {
			global $wpdb;
			$table = $wpdb->prefix . 'aips_cache_index';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $table, array( 'key_hash' => $key_hash ), array( '%s' ) );
		} catch ( Throwable $e ) {
			// Swallow.
		}
	}

	/**
	 * Remove all buffered entries for a specific cache group from memory.
	 *
	 * Purges any unwritten sets or pending access timestamps for the group
	 * so they are not re-inserted on shutdown after a group flush.
	 *
	 * @param string $group Cache group.
	 * @return void
	 */
	public function record_delete_group( string $group ): void {
		$prefix = $group . ':';
		foreach ( $this->pending_writes as $composite => $row ) {
			if ( 0 === strpos( $composite, $prefix ) || ( isset( $row['cache_group'] ) && $row['cache_group'] === $group ) ) {
				unset( $this->pending_writes[ $composite ] );
			}
		}

		foreach ( $this->pending_access_groups as $key_hash => $entry_group ) {
			if ( $entry_group === $group ) {
				unset( $this->pending_access[ $key_hash ], $this->pending_access_groups[ $key_hash ] );
			}
		}
	}

	/**
	 * Clear all index rows (called on full flush).
	 *
	 * @return void
	 */
	public function record_flush(): void {
		$this->pending_writes        = array();
		$this->pending_access        = array();
		$this->pending_access_groups = array();

		if (!$this->enabled || !$this->table_ready()) {
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
	 * Buffers the access timestamp in memory and flushes in bulk on request shutdown
	 * rather than issuing synchronous SQL updates during runtime reads.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return void
	 */
	public function record_access( string $key, string $group ): void {
		if (!$this->enabled || !$this->table_ready()) {
			return;
		}

		try {
			$key_hash                                  = hash( 'sha256', $group . ':' . $key );
			$this->pending_access[ $key_hash ]        = AIPS_DateTime::now()->timestamp();
			$this->pending_access_groups[ $key_hash ] = $group;
			$this->register_shutdown_hook();
			$this->maybe_flush_early();
		} catch ( Throwable $e ) {
			// Swallow.
		}
	}

	/**
	 * Flush buffered writes and access timestamps to the database.
	 *
	 * Automatically called on WordPress 'shutdown' hook.
	 *
	 * @return void
	 */
	public function flush_buffer(): void {
		if (!$this->enabled || !$this->table_ready()) {
			$this->pending_writes        = array();
			$this->pending_access        = array();
			$this->pending_access_groups = array();
			return;
		}

		if (!empty($this->pending_writes)) {
			$this->flush_pending_writes();

			// Trim once per flush rather than once per write: the COUNT(*)
			// that enforce_max_entries() runs is what the buffer exists to avoid.
			try {
				$this->enforce_max_entries();
			} catch ( Throwable $e ) {
				// Swallow.
			}
		}

		if (!empty($this->pending_access)) {
			$this->flush_pending_access();
		}
	}

	/**
	 * Flush the buffers before shutdown once they exceed the threshold.
	 *
	 * @return void
	 */
	private function maybe_flush_early(): void {
		if (count( $this->pending_writes ) + count( $this->pending_access ) >= self::BUFFER_FLUSH_THRESHOLD) {
			$this->flush_buffer();
		}
	}

	/**
	 * Flush buffered cache writes to the database in chunks.
	 *
	 * @return void
	 */
	private function flush_pending_writes(): void {
		$writes = $this->pending_writes;
		$this->pending_writes = array();

		if (empty($writes)) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'aips_cache_index';

		$chunks = array_chunk( $writes, 50, true );

		foreach ($chunks as $chunk) {
			$values_sql    = array();
			$prepared_args = array();

			foreach ($chunk as $row) {
				$values_sql[]    = '(%s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d, %d, %d, %d, %s, %d)';
				$prepared_args[] = $row['cache_key'];
				$prepared_args[] = $row['key_hash'];
				$prepared_args[] = $row['cache_group'];
				$prepared_args[] = $row['driver'];
				$prepared_args[] = $row['tier'];
				$prepared_args[] = $row['operation_id'];
				$prepared_args[] = $row['repository_class'];
				$prepared_args[] = $row['tags'];
				$prepared_args[] = $row['domain'];
				$prepared_args[] = $row['ttl'];
				$prepared_args[] = $row['created_at'];
				$prepared_args[] = $row['updated_at'];
				$prepared_args[] = $row['expires_at'];
				$prepared_args[] = $row['value_size'];
				$prepared_args[] = $row['value_type'];
				$prepared_args[] = $row['last_accessed_at'];
			}

			$values_clause = implode( ', ', $values_sql );
			$query = "INSERT INTO `{$table}` 
				(cache_key, key_hash, cache_group, driver, tier, operation_id, repository_class, tags, domain, ttl, created_at, updated_at, expires_at, value_size, value_type, last_accessed_at)
				VALUES {$values_clause}
				ON DUPLICATE KEY UPDATE
				cache_key = VALUES(cache_key),
				driver = VALUES(driver),
				tier = VALUES(tier),
				operation_id = VALUES(operation_id),
				repository_class = VALUES(repository_class),
				tags = VALUES(tags),
				domain = VALUES(domain),
				ttl = VALUES(ttl),
				updated_at = VALUES(updated_at),
				expires_at = VALUES(expires_at),
				value_size = VALUES(value_size),
				value_type = VALUES(value_type),
				last_accessed_at = VALUES(last_accessed_at)";

			try {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $wpdb->prepare( $query, $prepared_args ) );
			} catch ( Throwable $e ) {
				// Swallow.
			}
		}
	}

	/**
	 * Flush buffered access timestamps to the database.
	 *
	 * @return void
	 */
	private function flush_pending_access(): void {
		$access                      = $this->pending_access;
		$this->pending_access        = array();
		$this->pending_access_groups = array();

		if (empty($access)) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'aips_cache_index';

		$by_time = array();
		foreach ($access as $key_hash => $timestamp) {
			$by_time[ $timestamp ][] = $key_hash;
		}

		foreach ($by_time as $timestamp => $hashes) {
			$chunks = array_chunk( $hashes, 100 );
			foreach ($chunks as $chunk) {
				$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
				$args         = array_merge( array( (int) $timestamp ), $chunk );
				try {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE `{$table}` SET last_accessed_at = %d WHERE key_hash IN ($placeholders)",
							$args
						)
					);
				} catch ( Throwable $e ) {
					// Swallow.
				}
			}
		}
	}

	/**
	 * Prune expired index entries.
	 *
	 * @return int Number of rows deleted.
	 */
	public function prune_expired(): int {
		if (!$this->table_ready()) {
			return 0;
		}

		try {
			global $wpdb;
			$table = $wpdb->prefix . 'aips_cache_index';
			$now   = AIPS_DateTime::now()->timestamp();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $wpdb->query(
				$wpdb->prepare( "DELETE FROM `{$table}` WHERE expires_at > 0 AND expires_at < %d", $now )
			);

			$this->enforce_max_entries();

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

		if (!$this->table_ready()) {
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

		if (!$this->table_ready()) {
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
	 * Determine whether the aips_cache_index table exists, memoizing the result.
	 *
	 * The index is a fire-and-forget metadata store. When a cache write happens
	 * before the table is created — most notably during plugin activation, where
	 * the logger writes cache entries before AIPS_DB_Manager::install_tables()
	 * runs — querying the missing table would emit a "table doesn't exist" DB
	 * error for every write. Gating all DB operations on this check keeps those
	 * pre-install writes silent while the index self-heals once the table lands.
	 *
	 * @return bool
	 */
	private function table_ready(): bool {
		if (null !== $this->table_exists) {
			return $this->table_exists;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'aips_cache_index';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->table_exists = ( $found === $table );

		return $this->table_exists;
	}

	/**
	 * Prepare normalized row data for a cache index entry.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $value   Cached value.
	 * @param int    $ttl     TTL in seconds.
	 * @param string $group   Cache group.
	 * @param array  $context Additional context metadata.
	 * @return array<string, mixed>
	 */
	private function prepare_index_row_data( string $key, $value, int $ttl, string $group, array $context ): array {
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

		return array(
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
		if (is_int( $value )) {
			return PHP_INT_SIZE;
		}
		if (is_float( $value )) {
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
