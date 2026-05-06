<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Redis_Driver
 *
 * Persistent cache driver backed by Redis.
 *
 * Requires the PHP `redis` extension (pecl/phpredis). The driver reports
 * whether the connection succeeded via {@see is_connected()}. When the
 * extension is missing or the server is unreachable every method returns a
 * safe no-op value — the caller (usually AIPS_Cache_Factory) is responsible
 * for falling back to another driver.
 *
 * Keys are prefixed as: `{prefix}:{group}:{key}` to avoid collisions with
 * other applications sharing the same Redis instance or database.
 *
 * @package AI_Post_Scheduler
 * @since   2.3.0
 */
class AIPS_Cache_Redis_Driver implements AIPS_Cache_Driver {

	/**
	 * phpredis client instance, or null when not connected.
	 *
	 * @var Redis|null
	 */
	private $redis = null;

	/**
	 * Whether a live Redis connection is available.
	 *
	 * @var bool
	 */
	private $connected = false;

	/**
	 * Key prefix used for all cache entries.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Redis connection error message from the last failed attempt, or empty.
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Constructor.
	 *
	 * Attempts to open a Redis connection. If the `redis` PHP extension is not
	 * loaded, or if the connection/auth fails, $connected stays false and
	 * every method silently no-ops.
	 *
	 * @param string $host     Redis server hostname or IP. Default '127.0.0.1'.
	 * @param int    $port     Redis server port. Default 6379.
	 * @param string $password Optional authentication password. Default ''.
	 * @param int    $db       Redis database index to select. Default 0.
	 * @param string $prefix   Key prefix. Default 'aips'.
	 * @param float  $timeout  Connection timeout in seconds. Default 2.0.
	 */
	public function __construct(
		$host     = '127.0.0.1',
		$port     = 6379,
		$password = '',
		$db       = 0,
		$prefix   = 'aips',
		$timeout  = 2.0
	) {
		$this->prefix = (string) $prefix;
		$this->connect( $host, (int) $port, $password, (int) $db, (float) $timeout );
	}

	// -----------------------------------------------------------------------
	// Public status helpers
	// -----------------------------------------------------------------------

	/**
	 * Return whether a live connection to Redis is available.
	 *
	 * @return bool
	 */
	public function is_connected() {
		return $this->connected;
	}

	/**
	 * Return the last connection error message, or an empty string when healthy.
	 *
	 * @return string
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	// -----------------------------------------------------------------------
	// AIPS_Cache_Driver implementation
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function get( $key, $group = 'default' ) {
		if (!$this->connected) {
			return null;
		}

		$value = $this->redis->get( $this->prefix_key( $key, $group ) );

		if ($value === false) {
			return null;
		}

		return maybe_unserialize( $value );
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( $key, $value, $ttl = 0, $group = 'default' ) {
		if (!$this->connected) {
			return false;
		}

		$serialized = maybe_serialize( $value );
		$redis_key  = $this->prefix_key( $key, $group );

		if ($ttl > 0) {
			return (bool) $this->redis->setex( $redis_key, (int) $ttl, $serialized );
		}

		return (bool) $this->redis->set( $redis_key, $serialized );
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $key, $group = 'default' ) {
		if (!$this->connected) {
			return false;
		}

		// del() returns the number of keys removed (0 when the key doesn't exist)
		// or false on error. Deleting a non-existent key is a successful no-op, so
		// we return true for any non-false result to match other driver semantics.
		$result = $this->redis->del( $this->prefix_key( $key, $group ) );
		return false !== $result;
	}

	/**
	 * {@inheritdoc}
	 *
	 * When a key prefix is configured, only keys belonging to this driver's
	 * prefix are deleted, so unrelated data in the same Redis DB is preserved.
	 * When no prefix is configured the full database is flushed via FLUSHDB.
	 */
	public function flush() {
		if (!$this->connected) {
			return false;
		}

		if (empty( $this->prefix )) {
			// No prefix — full DB flush (intentional; caller configured no prefix).
			return (bool) $this->redis->flushDb();
		}

		// Prefix-scoped flush: find and delete only keys belonging to this driver.
		// KEYS is O(N) but acceptable in a plugin context without millions of keys.
		$pattern = $this->prefix . ':*';
		$keys    = $this->redis->keys( $pattern );

		if (empty( $keys )) {
			return true;
		}

		return false !== $this->redis->del( $keys );
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( $key, $group = 'default' ) {
		if (!$this->connected) {
			return false;
		}

		return (bool) $this->redis->exists( $this->prefix_key( $key, $group ) );
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Establish a connection to the Redis server.
	 *
	 * @param string $host     Redis host.
	 * @param int    $port     Redis port.
	 * @param string $password Authentication password (empty = none).
	 * @param int    $db       Database index.
	 * @param float  $timeout  Connection timeout in seconds.
	 * @return void
	 */
	private function connect( $host, $port, $password, $db, $timeout = 2.0 ) {
		if (!extension_loaded( 'redis' )) {
			return;
		}

		try {
			$this->redis = new Redis();
			// connect( host, port, timeout )
			$this->redis->connect( $host, $port, $timeout );

			if (!empty( $password )) {
				$this->redis->auth( $password );
			}

			if ($db !== 0) {
				$this->redis->select( $db );
			}

			$this->connected = true;
		} catch ( Exception $e ) {
			$this->redis      = null;
			$this->connected  = false;
			$this->last_error = $e->getMessage();
			AIPS_Logger::instance()->error( 'AIPS Redis cache driver connection failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Build the fully-qualified Redis key including prefix and group.
	 *
	 * Format: `{prefix}:{group}:{key}` (prefix part omitted when empty).
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return string Redis storage key.
	 */
	private function prefix_key( $key, $group ) {
		$parts = array();

		if (!empty( $this->prefix )) {
			$parts[] = $this->prefix;
		}

		$parts[] = $group;
		$parts[] = $key;

		return implode( ':', $parts );
	}
}
