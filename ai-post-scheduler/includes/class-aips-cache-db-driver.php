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
 * All SQL access is delegated to AIPS_Cache_Db_Repository; this class is only
 * concerned with cache semantics (TTL, key namespacing, (un)serialization).
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
	 * Repository handling all SQL access for the cache table.
	 *
	 * @var AIPS_Cache_Db_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param string                        $prefix     Optional key prefix/namespace. Default ''.
	 * @param AIPS_Cache_Db_Repository|null $repository Optional repository for SQL access.
	 *                                                  Defaults to the shared singleton.
	 */
	public function __construct( $prefix = '', AIPS_Cache_Db_Repository $repository = null ) {
		$this->prefix     = (string) $prefix;
		$this->repository = $repository ?: AIPS_Cache_Db_Repository::instance();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( $key, $group = 'default' ) {
		$row = $this->repository->find( $this->namespace_key( $key ), (string) $group );

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
		$now_ts     = AIPS_DateTime::now()->timestamp();
		// TTL = 0 means "never expires". Store expires_at = 0 to match the NOT NULL DEFAULT 0
		// schema contract. The get() and purge_expired() methods treat 0 as "never expires".
		$expires_at = $ttl > 0 ? $now_ts + (int) $ttl : 0;

		return $this->repository->replace(
			$this->namespace_key( $key ),
			(string) $group,
			maybe_serialize( $value ),
			$expires_at,
			$now_ts
		);
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
		return $this->repository->exists_unexpired(
			$this->namespace_key( $key ),
			(string) $group,
			AIPS_DateTime::now()->timestamp()
		);
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
		return $this->repository->delete_expired( AIPS_DateTime::now()->timestamp() );
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
