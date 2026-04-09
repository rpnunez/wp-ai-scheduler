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
class AIPS_Cache_Db_Driver implements AIPS_Cache_Driver {

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

		// Honour TTL: remove and return null if the entry has expired.
		if ($row->expires_at !== null && strtotime( $row->expires_at ) < time()) {
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

		if ($ttl > 0) {
			$expires_at = gmdate( 'Y-m-d H:i:s', time() + (int) $ttl );

			$result = $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'cache_key'   => $cache_key,
					'cache_group' => $cache_group,
					'value'       => $cache_value,
					'expires_at'  => $expires_at,
				),
				array( '%s', '%s', '%s', '%s' )
			);
		} else {
			// TTL = 0 means "never expire". We cannot pass null through $wpdb->replace()
			// with a '%s' format (it would be coerced to an empty string and treated as
			// expired on read). Use a raw REPLACE INTO with a literal NULL instead.
			$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"REPLACE INTO `{$table}` (`cache_key`, `cache_group`, `value`, `expires_at`) VALUES (%s, %s, %s, NULL)",
					$cache_key,
					$cache_group,
					$cache_value
				)
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
		return $this->get( $key, $group ) !== null;
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
				"DELETE FROM `{$table}` WHERE expires_at IS NOT NULL AND expires_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql', true )
			)
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
