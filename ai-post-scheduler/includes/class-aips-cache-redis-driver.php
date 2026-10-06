<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Redis_Driver
 *
 * Direct Redis cache driver using the phpredis extension (\Redis).
 * Supports pipelining (mget/pipeline), non-blocking evictions (UNLINK),
 * namespace-scoped group flushing, and live connection testing.
 *
 * @package AI_Post_Scheduler
 * @since   3.6.7
 */
class AIPS_Cache_Redis_Driver implements AIPS_Cache_Driver, AIPS_Cache_Monitorable_Driver {

	/**
	 * Redis client instance.
	 *
	 * @var \Redis|null
	 */
	protected $redis = null;

	/**
	 * Connection status.
	 *
	 * @var bool
	 */
	protected $connected = false;

	/**
	 * Redis server host.
	 *
	 * @var string
	 */
	protected $host = '127.0.0.1';

	/**
	 * Redis server port.
	 *
	 * @var int
	 */
	protected $port = 6379;

	/**
	 * Redis server auth password.
	 *
	 * @var string
	 */
	protected $password = '';

	/**
	 * Redis database index.
	 *
	 * @var int
	 */
	protected $database = 0;

	/**
	 * Connection timeout in seconds.
	 *
	 * @var float
	 */
	protected $timeout = 1.0;

	/**
	 * Cache key prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'aips:';

	/**
	 * Constructor.
	 *
	 * @param array $config Optional connection configuration parameters.
	 */
	public function __construct( array $config = array() ) {
		$this->resolve_configuration( $config );
		$this->connect();
	}

	/**
	 * Resolve configuration from parameters, constants, and options.
	 *
	 * @param array $config
	 * @return void
	 */
	protected function resolve_configuration( array $config ): void {
		// 1. Host
		if ( ! empty( $config['host'] ) ) {
			$this->host = (string) $config['host'];
		} elseif ( defined( 'AIPS_REDIS_HOST' ) ) {
			$this->host = (string) AIPS_REDIS_HOST;
		} elseif ( defined( 'WP_REDIS_HOST' ) ) {
			$this->host = (string) WP_REDIS_HOST;
		} else {
			$this->host = (string) get_option( 'aips_cache_redis_host', '127.0.0.1' );
		}

		// 2. Port
		if ( ! empty( $config['port'] ) ) {
			$this->port = (int) $config['port'];
		} elseif ( defined( 'AIPS_REDIS_PORT' ) ) {
			$this->port = (int) AIPS_REDIS_PORT;
		} elseif ( defined( 'WP_REDIS_PORT' ) ) {
			$this->port = (int) WP_REDIS_PORT;
		} else {
			$this->port = (int) get_option( 'aips_cache_redis_port', 6379 );
		}

		// 3. Password
		if ( isset( $config['password'] ) ) {
			$this->password = (string) $config['password'];
		} elseif ( defined( 'AIPS_REDIS_PASSWORD' ) ) {
			$this->password = (string) AIPS_REDIS_PASSWORD;
		} elseif ( defined( 'WP_REDIS_PASSWORD' ) ) {
			$this->password = (string) WP_REDIS_PASSWORD;
		} else {
			$this->password = (string) get_option( 'aips_cache_redis_password', '' );
		}

		// 4. Database
		if ( isset( $config['database'] ) ) {
			$this->database = (int) $config['database'];
		} elseif ( defined( 'AIPS_REDIS_DATABASE' ) ) {
			$this->database = (int) AIPS_REDIS_DATABASE;
		} elseif ( defined( 'WP_REDIS_DATABASE' ) ) {
			$this->database = (int) WP_REDIS_DATABASE;
		} else {
			$this->database = (int) get_option( 'aips_cache_redis_database', 0 );
		}

		// 5. Timeout
		if ( isset( $config['timeout'] ) ) {
			$this->timeout = (float) $config['timeout'];
		} elseif ( defined( 'AIPS_REDIS_TIMEOUT' ) ) {
			$this->timeout = (float) AIPS_REDIS_TIMEOUT;
		} elseif ( defined( 'WP_REDIS_TIMEOUT' ) ) {
			$this->timeout = (float) WP_REDIS_TIMEOUT;
		}

		// 6. Key Prefix
		if ( ! empty( $config['prefix'] ) ) {
			$this->prefix = rtrim( (string) $config['prefix'], ':' ) . ':';
		} elseif ( defined( 'WP_CACHE_KEY_SALT' ) ) {
			$this->prefix = 'aips:' . sanitize_key( WP_CACHE_KEY_SALT ) . ':';
		}
	}

	/**
	 * Create and initialize the Redis client instance.
	 *
	 * Subclasses (e.g. Relay) override this to instantiate specialized clients.
	 *
	 * @return \Redis
	 */
	protected function create_client_instance() {
		return new \Redis();
	}

	/**
	 * Establish connection to the Redis server.
	 *
	 * @return bool
	 */
	public function connect(): bool {
		if ( ! class_exists( 'Redis' ) ) {
			$this->connected = false;
			return false;
		}

		try {
			$client = $this->create_client_instance();
			$ok     = $client->connect( $this->host, $this->port, $this->timeout );

			if ( ! $ok ) {
				$this->connected = false;
				return false;
			}

			if ( $this->password !== '' ) {
				$auth_ok = $client->auth( $this->password );
				if ( ! $auth_ok ) {
					$this->connected = false;
					return false;
				}
			}

			if ( $this->database > 0 ) {
				$select_ok = $client->select( $this->database );
				if ( ! $select_ok ) {
					$this->connected = false;
					return false;
				}
			}

			$this->redis     = $client;
			$this->connected = true;
			return true;
		} catch ( \Throwable $e ) {
			$this->connected = false;
			$this->redis     = null;
			return false;
		}
	}

	/**
	 * Format an internal cache key with prefix and group.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return string Full Redis key.
	 */
	protected function format_key( string $key, string $group ): string {
		return $this->prefix . $group . ':' . $key;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available() {
		return $this->connected && $this->redis !== null;
	}

	/**
	 * Test server connection and measure round-trip latency.
	 *
	 * @return array{success: bool, latency_ms: float, message: string}
	 */
	public function test_connection(): array {
		if ( ! $this->is_available() ) {
			if ( ! class_exists( 'Redis' ) ) {
				return array(
					'success'    => false,
					'latency_ms' => 0.0,
					'message'    => __( 'The PHP Redis extension (phpredis) is not installed on this server.', 'ai-post-scheduler' ),
				);
			}

			$reconnected = $this->connect();
			if ( ! $reconnected || ! $this->redis ) {
				return array(
					'success'    => false,
					'latency_ms' => 0.0,
					'message'    => sprintf(
						/* translators: 1: host, 2: port */
						__( 'Could not connect to Redis at %1$s:%2$d.', 'ai-post-scheduler' ),
						$this->host,
						$this->port
					),
				);
			}
		}

		try {
			$start    = microtime( true );
			$test_key = $this->prefix . 'test:' . uniqid( '', true );

			$this->redis->setex( $test_key, 10, 'ping' );
			$val = $this->redis->get( $test_key );
			$this->redis->unlink( $test_key );

			$latency = ( microtime( true ) - $start ) * 1000;

			if ( 'ping' === $val ) {
				return array(
					'success'    => true,
					'latency_ms' => round( $latency, 2 ),
					'message'    => sprintf(
						/* translators: 1: latency ms, 2: host, 3: port, 4: database */
						__( 'Connected to Redis at %2$s:%3$d (DB %4$d) in %1$0.2f ms.', 'ai-post-scheduler' ),
						$latency,
						$this->host,
						$this->port,
						$this->database
					),
				);
			}

			return array(
				'success'    => false,
				'latency_ms' => round( $latency, 2 ),
				'message'    => __( 'Write/read test failed on Redis connection.', 'ai-post-scheduler' ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'    => false,
				'latency_ms' => 0.0,
				'message'    => $e->getMessage(),
			);
		}
	}

	// -----------------------------------------------------------------------
	// AIPS_Cache_Driver implementation
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function get( $key, $group = 'default' ) {
		if ( ! $this->is_available() ) {
			return null;
		}

		try {
			$full_key = $this->format_key( $key, $group );
			$raw      = $this->redis->get( $full_key );

			if ( false === $raw || null === $raw ) {
				return null;
			}

			return maybe_unserialize( $raw );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_multiple( array $keys, $group = 'default' ) {
		$results = array();
		foreach ( $keys as $k ) {
			$results[ $k ] = null;
		}

		if ( empty( $keys ) || ! $this->is_available() ) {
			return $results;
		}

		try {
			$full_keys = array();
			foreach ( $keys as $k ) {
				$full_keys[] = $this->format_key( $k, $group );
			}

			$values = $this->redis->mget( $full_keys );

			if ( is_array( $values ) ) {
				$i = 0;
				foreach ( $keys as $k ) {
					$raw = isset( $values[ $i ] ) ? $values[ $i ] : false;
					if ( false !== $raw && null !== $raw ) {
						$results[ $k ] = maybe_unserialize( $raw );
					}
					$i++;
				}
			}
		} catch ( \Throwable $e ) {
			// Fall through to null defaults.
		}

		return $results;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( $key, $value, $ttl = 0, $group = 'default' ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		try {
			$full_key   = $this->format_key( $key, $group );
			$serialized = maybe_serialize( $value );

			if ( $ttl > 0 ) {
				return (bool) $this->redis->setex( $full_key, (int) $ttl, $serialized );
			}

			return (bool) $this->redis->set( $full_key, $serialized );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Set multiple values via pipelining.
	 *
	 * @param array<string, mixed> $items Key => value pairs.
	 * @param int                  $ttl   TTL in seconds.
	 * @param string               $group Cache group.
	 * @return bool
	 */
	public function set_multiple( array $items, int $ttl = 0, string $group = 'default' ): bool {
		if ( empty( $items ) || ! $this->is_available() ) {
			return false;
		}

		try {
			$pipe = $this->redis->multi( \Redis::PIPELINE );

			foreach ( $items as $key => $value ) {
				$full_key   = $this->format_key( (string) $key, $group );
				$serialized = maybe_serialize( $value );

				if ( $ttl > 0 ) {
					$pipe->setex( $full_key, $ttl, $serialized );
				} else {
					$pipe->set( $full_key, $serialized );
				}
			}

			$pipe->exec();
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $key, $group = 'default' ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		try {
			$full_key = $this->format_key( $key, $group );
			// Non-blocking unlink when supported, fallback to del.
			if ( method_exists( $this->redis, 'unlink' ) ) {
				return (int) $this->redis->unlink( $full_key ) > 0;
			}
			return (int) $this->redis->del( $full_key ) > 0;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Delete multiple keys in a single non-blocking call.
	 *
	 * @param array  $keys  Array of cache keys.
	 * @param string $group Cache group.
	 * @return bool
	 */
	public function delete_multiple( array $keys, string $group = 'default' ): bool {
		if ( empty( $keys ) || ! $this->is_available() ) {
			return false;
		}

		try {
			$full_keys = array();
			foreach ( $keys as $k ) {
				$full_keys[] = $this->format_key( (string) $k, $group );
			}

			if ( method_exists( $this->redis, 'unlink' ) ) {
				return (int) $this->redis->unlink( $full_keys ) > 0;
			}
			return (int) $this->redis->del( $full_keys ) > 0;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function flush() {
		if ( ! $this->is_available() ) {
			return false;
		}

		try {
			// Purge only plugin-prefixed keys rather than FLUSHDB.
			$pattern  = $this->prefix . '*';
			$iterator = null;

			while ( true ) {
				$keys = $this->redis->scan( $iterator, $pattern, 200 );
				if ( false === $keys ) {
					break;
				}

				if ( ! empty( $keys ) ) {
					if ( method_exists( $this->redis, 'unlink' ) ) {
						$this->redis->unlink( $keys );
					} else {
						$this->redis->del( $keys );
					}
				}

				if ( 0 === (int) $iterator ) {
					break;
				}
			}

			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( $key, $group = 'default' ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		try {
			$full_key = $this->format_key( $key, $group );
			return (int) $this->redis->exists( $full_key ) > 0;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	// -----------------------------------------------------------------------
	// AIPS_Cache_Monitorable_Driver implementation
	// -----------------------------------------------------------------------

	/**
	 * {@inheritdoc}
	 */
	public function get_monitor_capabilities(): array {
		return array(
			'list_keys'     => false,
			'inspect_entry' => false,
			'delete_key'    => true,
			'delete_group'  => true,
			'flush_plugin'  => true,
			'size_bytes'    => false,
			'ttl_remaining' => true,
			'tag_versions'  => false,
			'live_metrics'  => false,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function list_entries( array $filters = array(), int $limit = 100, int $offset = 0 ): array {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function count_entries( array $filters = array() ): int {
		return 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_entry_metadata( string $key, string $group = 'default' ): array {
		if ( ! $this->is_available() ) {
			return array();
		}

		try {
			$full_key = $this->format_key( $key, $group );
			$ttl      = (int) $this->redis->ttl( $full_key );

			return array(
				'cache_key'     => $key,
				'cache_group'   => $group,
				'ttl_remaining' => $ttl > 0 ? $ttl : ( -1 === $ttl ? null : 0 ),
			);
		} catch ( \Throwable $e ) {
			return array();
		}
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
		if ( ! $this->is_available() ) {
			return false;
		}

		try {
			$pattern  = $this->prefix . $group . ':*';
			$iterator = null;

			while ( true ) {
				$keys = $this->redis->scan( $iterator, $pattern, 200 );
				if ( false === $keys ) {
					break;
				}

				if ( ! empty( $keys ) ) {
					if ( method_exists( $this->redis, 'unlink' ) ) {
						$this->redis->unlink( $keys );
					} else {
						$this->redis->del( $keys );
					}
				}

				if ( 0 === (int) $iterator ) {
					break;
				}
			}

			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Get the driver identifier name.
	 *
	 * @return string
	 */
	public function get_driver_name(): string {
		return 'redis';
	}

	/**
	 * {@inheritdoc}
	 */
	public function estimate_size( array $filters = array() ): array {
		if ( ! $this->is_available() ) {
			return array(
				'total_bytes'   => 0,
				'row_count'     => 0,
				'expired_bytes' => 0,
				'expired_count' => 0,
				'available'     => false,
			);
		}

		$bytes = 0;
		try {
			$info = $this->redis->info( 'memory' );
			if ( is_array( $info ) && isset( $info['used_memory'] ) ) {
				$bytes = (int) $info['used_memory'];
			}
		} catch ( \Throwable $e ) {
			$bytes = 0;
		}

		return array(
			'total_bytes'   => $bytes,
			'row_count'     => 0,
			'expired_bytes' => 0,
			'expired_count' => 0,
			'available'     => $bytes > 0,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_driver_info(): array {
		return array(
			'driver'      => 'redis',
			'label'       => __( 'Redis (phpredis)', 'ai-post-scheduler' ),
			'persistent'  => true,
			'host'        => $this->host,
			'port'        => $this->port,
			'database'    => $this->database,
			'connected'   => $this->connected,
			'limitations' => array(
				__( 'High-speed in-memory store with persistence across requests.', 'ai-post-scheduler' ),
			),
		);
	}
}
