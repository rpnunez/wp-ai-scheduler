<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Db_Driver
 *
 * Persistent cache driver backed by the WordPress database.
 *
 * Stores serialized values in the `{prefix}aips_cache` table.
 * Handles TTL-based expiration and allows an optional key namespace/prefix to
 * isolate entries from different callers when sharing the same table.
 *
 * The table schema is managed by AIPS_DB_Manager; this class never creates or
 * alters the table itself.
 *
 * @package AI_Post_Scheduler
 * @since   2.3.0
 */
class AIPS_Cache_Db_Driver implements AIPS_Cache_Driver, AIPS_Cache_Monitorable_Driver {

	/**
	 * Optional prefix applied to every cache key before writing to the DB.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Constructor.
	 *
	 * @param string $prefix Optional key prefix/namespace. Default ''.
	 */
	public function __construct( $prefix = '' ) {
		$this->prefix = (string) $prefix;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( $key, $group = 'default' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache';
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT value, expires_at FROM `{$table}` WHERE cache_key = %s AND cache_group = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->namespace_key( $key ),
				(string) $group
			)
		);

		if (!$row) {
			return null;
		}

		// Honour TTL: 0 means "never expires"; only expire when expires_at > 0 and in the past.
		if ((int) $row->expires_at > 0 && (int) $row->expires_at < AIPS_DateTime::now()->timestamp()) {
			$this->delete( $key, $group );
			return null;
		}

		return maybe_unserialize( $row->value );
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( $key, $value, $ttl = 0, $group = 'default' ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'aips_cache';
		$cache_key   = $this->namespace_key( $key );
		$cache_group = (string) $group;
		$cache_value = maybe_serialize( $value );

		$now_ts = AIPS_DateTime::now()->timestamp();

		if ($ttl > 0) {
			$expires_at = $now_ts + (int) $ttl;

			$result = $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'cache_key'   => $cache_key,
					'cache_group' => $cache_group,
					'value'       => $cache_value,
					'expires_at'  => $expires_at,
					'updated_at'  => $now_ts,
				),
				array( '%s', '%s', '%s', '%d', '%d' )
			);
		} else {
			// TTL = 0 means "never expires". Store expires_at = 0 to match the NOT NULL DEFAULT 0
			// schema contract. The get() and purge_expired() methods treat 0 as "never expires".
			$result = $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'cache_key'   => $cache_key,
					'cache_group' => $cache_group,
					'value'       => $cache_value,
					'expires_at'  => 0,
					'updated_at'  => $now_ts,
				),
				array( '%s', '%s', '%s', '%d', '%d' )
			);
		}

		return false !== $result && '' === $wpdb->last_error;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $key, $group = 'default' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache';
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'cache_key'   => $this->namespace_key( $key ),
				'cache_group' => (string) $group,
			),
			array( '%s', '%s' )
		);

		return true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Removes ALL rows from the cache table (not prefix-scoped).
	 */
	public function flush() {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// TRUNCATE is a DDL statement and cannot be used with $wpdb->prepare().
		// $table is constructed from $wpdb->prefix which is set and sanitized by WordPress core.
		$wpdb->query( "TRUNCATE TABLE `{$table}`" );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( $key, $group = 'default' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache';
		$row   = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT 1 FROM `{$table}` WHERE cache_key = %s AND cache_group = %s AND " . $this->get_non_expired_predicate_sql() . " LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->namespace_key( $key ),
				(string) $group,
				AIPS_DateTime::now()->timestamp()
			)
		);

		return null !== $row;
	}

	/**
	 * Delete all expired entries from the cache table.
	 *
	 * Intended to be called periodically (e.g., via a cron job) to keep the
	 * table from growing unbounded.
	 *
	 * @return int|false Number of rows deleted, or false on failure.
	 */
	public function purge_expired() {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache';
		return $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE expires_at > 0 AND expires_at < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				AIPS_DateTime::now()->timestamp()
			)
		);
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * SQL predicate for rows that should be treated as non-expired.
	 *
	 * Mirrors get() semantics: expires_at = 0 never expires, otherwise
	 * expires_at must be greater than or equal to the current timestamp.
	 *
	 * @return string
	 */
	private function get_non_expired_predicate_sql() {
		return '(expires_at = 0 OR expires_at >= %d)';
	}

	// -----------------------------------------------------------------------
	// AIPS_Cache_Monitorable_Driver implementation
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function get_monitor_capabilities(): array {
		return array(
			'list_keys'     => true,
			'inspect_entry' => true,
			'delete_key'    => true,
			'delete_group'  => true,
			'flush_plugin'  => true,
			'size_bytes'    => true,
			'ttl_remaining' => true,
			'tag_versions'  => false,
			'live_metrics'  => false,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function list_entries( array $filters = array(), int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache';
		$now   = AIPS_DateTime::now()->timestamp();

		$where  = array( '1=1' );
		$params = array();

		if (!empty( $filters['group'] )) {
			$where[]  = 'cache_group = %s';
			$params[] = sanitize_key( $filters['group'] );
		}

		if (!empty( $filters['key_hash'] )) {
			$where[]  = 'SHA2(cache_key, 256) LIKE %s';
			$params[] = $wpdb->esc_like( sanitize_text_field( $filters['key_hash'] ) ) . '%';
		}

		if (!empty( $filters['ttl_state'] )) {
			switch ($filters['ttl_state']) {
				case 'expired':
					$where[]  = 'expires_at > 0 AND expires_at < %d';
					$params[] = $now;
					break;
				case 'active':
					$where[]  = '(expires_at = 0 OR expires_at >= %d)';
					$params[] = $now;
					break;
				case 'no_expiration':
					$where[] = 'expires_at = 0';
					break;
			}
		}

		$where_sql = implode( ' AND ', $where );

		$limit  = max( 1, min( 500, (int) $limit ) );
		$offset = max( 0, (int) $offset );

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cache_key, cache_group, expires_at, updated_at, LENGTH(value) as value_size FROM `{$table}` WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		if (!is_array( $rows )) {
			return array();
		}

		$result = array();
		foreach ($rows as $row) {
			$result[] = array(
				'cache_key'   => $row['cache_key'],
				'key_hash'    => hash( 'sha256', $row['cache_key'] ),
				'cache_group' => $row['cache_group'],
				'expires_at'  => (int) $row['expires_at'],
				'updated_at'  => (int) $row['updated_at'],
				'value_size'  => (int) $row['value_size'],
				'ttl_remaining' => $row['expires_at'] > 0 ? max( 0, (int) $row['expires_at'] - $now ) : null,
				'driver'      => 'db',
			);
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function count_entries( array $filters = array() ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache';
		$now   = AIPS_DateTime::now()->timestamp();

		$where  = array( '1=1' );
		$params = array();

		if (!empty( $filters['group'] )) {
			$where[]  = 'cache_group = %s';
			$params[] = sanitize_key( $filters['group'] );
		}

		if (!empty( $filters['ttl_state'] )) {
			switch ($filters['ttl_state']) {
				case 'expired':
					$where[]  = 'expires_at > 0 AND expires_at < %d';
					$params[] = $now;
					break;
				case 'active':
					$where[]  = '(expires_at = 0 OR expires_at >= %d)';
					$params[] = $now;
					break;
				case 'no_expiration':
					$where[] = 'expires_at = 0';
					break;
			}
		}

		$where_sql = implode( ' AND ', $where );

		if (empty( $params )) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}" );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", ...$params ) );
		}

		return (int) $count;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_entry_metadata( string $key, string $group = 'default' ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache';
		$now   = AIPS_DateTime::now()->timestamp();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cache_key, cache_group, expires_at, updated_at, LENGTH(value) as value_size FROM `{$table}` WHERE cache_key = %s AND cache_group = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->namespace_key( $key ),
				(string) $group
			),
			ARRAY_A
		);

		if (!$row) {
			return array();
		}

		return array(
			'cache_key'     => $row['cache_key'],
			'key_hash'      => hash( 'sha256', $row['cache_key'] ),
			'cache_group'   => $row['cache_group'],
			'expires_at'    => (int) $row['expires_at'],
			'updated_at'    => (int) $row['updated_at'],
			'value_size'    => (int) $row['value_size'],
			'ttl_remaining' => $row['expires_at'] > 0 ? max( 0, (int) $row['expires_at'] - $now ) : null,
			'driver'        => 'db',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete_entry( string $key, string $group = 'default' ): bool {
		return $this->delete( $key, $group );
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete_group( string $group ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$table,
			array( 'cache_group' => (string) $group ),
			array( '%s' )
		);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function estimate_size( array $filters = array() ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_cache';
		$now   = AIPS_DateTime::now()->timestamp();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $wpdb->get_row( "SELECT COUNT(*) as cnt, COALESCE(SUM(LENGTH(value)),0) as sz FROM `{$table}`", ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$expired = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) as cnt, COALESCE(SUM(LENGTH(value)),0) as sz FROM `{$table}` WHERE expires_at > 0 AND expires_at < %d", $now ), ARRAY_A );

		return array(
			'total_bytes'   => (int) ($total['sz'] ?? 0),
			'row_count'     => (int) ($total['cnt'] ?? 0),
			'expired_bytes' => (int) ($expired['sz'] ?? 0),
			'expired_count' => (int) ($expired['cnt'] ?? 0),
			'available'     => true,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_driver_info(): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'aips_cache';
		$now     = AIPS_DateTime::now()->timestamp();
		$size    = $this->estimate_size();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$largest = $wpdb->get_results(
			"SELECT cache_key, cache_group, LENGTH(value) as value_size FROM `{$table}` ORDER BY value_size DESC LIMIT 5",
			ARRAY_A
		);

		return array(
			'driver'        => 'db',
			'label'         => __( 'Database (MySQL)', 'ai-post-scheduler' ),
			'table'         => $table,
			'prefix'        => $this->prefix,
			'row_count'     => $size['row_count'],
			'total_bytes'   => $size['total_bytes'],
			'expired_count' => $size['expired_count'],
			'expired_bytes' => $size['expired_bytes'],
			'largest_rows'  => is_array( $largest ) ? $largest : array(),
			'limitations'   => array(),
		);
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Optionally prepend the configured prefix to a cache key.
	 *
	 * @param string $key Raw cache key.
	 * @return string Namespaced key.
	 */
	private function namespace_key( $key ) {
		if (!empty( $this->prefix )) {
			return $this->prefix . ':' . $key;
		}
		return $key;
	}
}
