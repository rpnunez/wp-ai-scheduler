<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Db_Repository
 *
 * Repository for the `{prefix}aips_cache` table. Encapsulates every SQL call
 * made by AIPS_Cache_Db_Driver so that no SQL is issued from outside this
 * class.
 *
 * The repository works exclusively in already-namespaced cache keys and
 * pre-computed expiration timestamps — TTL semantics, key prefixing, and
 * value (un)serialization remain the driver's responsibility.
 *
 * The table schema is managed by AIPS_DB_Manager; this class never creates or
 * alters the table itself.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */
class AIPS_Cache_Db_Repository {

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * @var string Fully-qualified cache table name (with WordPress prefix).
	 */
	private $table_name;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_cache';
	}

	/**
	 * Fetch a single cache row by namespaced key and group.
	 *
	 * @param string $cache_key   Already-namespaced cache key.
	 * @param string $cache_group Cache group.
	 * @return object|null Row with `value` and `expires_at`, or null on miss.
	 */
	public function find( $cache_key, $cache_group ) {
		$table = $this->table_name;

		return $this->wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->wpdb->prepare(
				"SELECT value, expires_at FROM `{$table}` WHERE cache_key = %s AND cache_group = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cache_key,
				$cache_group
			)
		);
	}

	/**
	 * Insert or replace a cache row.
	 *
	 * @param string $cache_key   Already-namespaced cache key.
	 * @param string $cache_group Cache group.
	 * @param string $value       Serialized value to store.
	 * @param int    $expires_at  Absolute expiration timestamp (0 = never).
	 * @param int    $updated_at  Current timestamp.
	 * @return bool True on success, false on failure.
	 */
	public function replace( $cache_key, $cache_group, $value, $expires_at, $updated_at ) {
		$result = $this->wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table_name,
			array(
				'cache_key'   => $cache_key,
				'cache_group' => $cache_group,
				'value'       => $value,
				'expires_at'  => (int) $expires_at,
				'updated_at'  => (int) $updated_at,
			),
			array( '%s', '%s', '%s', '%d', '%d' )
		);

		return false !== $result && '' === $this->wpdb->last_error;
	}

	/**
	 * Delete a single cache row by namespaced key and group.
	 *
	 * @param string $cache_key   Already-namespaced cache key.
	 * @param string $cache_group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $cache_key, $cache_group ) {
		$result = $this->wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table_name,
			array(
				'cache_key'   => $cache_key,
				'cache_group' => $cache_group,
			),
			array( '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Remove every row from the cache table.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function truncate() {
		$table = $this->table_name;

		// TRUNCATE is a DDL statement and cannot be used with $wpdb->prepare().
		// $table is built from $wpdb->prefix which is sanitized by WordPress core.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false !== $this->wpdb->query( "TRUNCATE TABLE `{$table}`" );
	}

	/**
	 * Check whether an unexpired row exists for the given key and group.
	 *
	 * Mirrors find()/get() expiration semantics: expires_at = 0 never expires;
	 * otherwise expires_at must be greater than or equal to $now.
	 *
	 * @param string $cache_key   Already-namespaced cache key.
	 * @param string $cache_group Cache group.
	 * @param int    $now         Current timestamp used as the expiration cutoff.
	 * @return bool True when a matching, non-expired row exists.
	 */
	public function exists_unexpired( $cache_key, $cache_group, $now ) {
		$table = $this->table_name;

		$row = $this->wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->wpdb->prepare(
				"SELECT 1 FROM `{$table}` WHERE cache_key = %s AND cache_group = %s AND (expires_at = 0 OR expires_at >= %d) LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cache_key,
				$cache_group,
				(int) $now
			)
		);

		return null !== $row;
	}

	/**
	 * Delete every row whose expires_at has already passed.
	 *
	 * Rows with expires_at = 0 are treated as "never expires" and are skipped.
	 *
	 * @param int $now Current timestamp used as the expiration cutoff.
	 * @return int|false Number of rows deleted, or false on failure.
	 */
	public function delete_expired( $now ) {
		$table = $this->table_name;

		return $this->wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->wpdb->prepare(
				"DELETE FROM `{$table}` WHERE expires_at > 0 AND expires_at < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $now
			)
		);
	}
}
