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
	 * @var AIPS_Cache_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param string $prefix Optional key prefix/namespace. Default ''.
	 */
	public function __construct( $prefix = '', $repository = null ) {
		$this->prefix = (string) $prefix;
		$this->repository = $repository instanceof AIPS_Cache_Repository ? $repository : new AIPS_Cache_Repository();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( $key, $group = 'default' ) {
		$row = $this->repository->get_row( $this->namespace_key( $key ), (string) $group );

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

		$cache_key   = $this->namespace_key( $key );
		$cache_group = (string) $group;
		$cache_value = maybe_serialize( $value );

		$now_ts = AIPS_DateTime::now()->timestamp();

		if ($ttl > 0) {
			$expires_at = $now_ts + (int) $ttl;

			$result = $this->repository->replace(
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
			$result = $this->repository->replace(
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

		return false !== $result && '' === $this->repository->last_error();
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $key, $group = 'default' ) {
		$this->repository->delete( $this->namespace_key( $key ), (string) $group );

		return true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Removes ALL rows from the cache table (not prefix-scoped).
	 */
	public function flush() {
		$this->repository->truncate();

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( $key, $group = 'default' ) {
		$row = $this->repository->has_non_expired( $this->namespace_key( $key ), (string) $group, AIPS_DateTime::now()->timestamp() );

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
		return $this->repository->purge_expired( AIPS_DateTime::now()->timestamp() );
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
