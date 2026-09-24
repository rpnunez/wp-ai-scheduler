<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache_Memcached_Driver
 *
 * Direct Memcached cache driver using the PHP Memcached extension (\Memcached).
 * Supports multi-key operations (getMulti, setMulti, deleteMulti) and
 * connection testing.
 *
 * @package AI_Post_Scheduler
 * @since   3.6.7
 */
class AIPS_Cache_Memcached_Driver implements AIPS_Cache_Driver, AIPS_Cache_Monitorable_Driver {

	/**
	 * Memcached client instance.
	 *
	 * @var \Memcached|null
	 */
	protected $memcached = null;

	/**
	 * Connection status.
	 *
	 * @var bool
	 */
	protected $connected = false;

	/**
	 * Server pool list.
	 *
	 * @var array<int, array{host: string, port: int, weight: int}>
	 */
	protected $servers = array();

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
		$servers_str = '';

		if ( ! empty( $config['servers'] ) ) {
			$servers_str = is_array( $config['servers'] ) ? implode( ',', $config['servers'] ) : (string) $config['servers'];
		} elseif ( defined( 'AIPS_MEMCACHED_SERVERS' ) ) {
			$servers_str = is_array( AIPS_MEMCACHED_SERVERS ) ? implode( ',', AIPS_MEMCACHED_SERVERS ) : (string) AIPS_MEMCACHED_SERVERS;
		} elseif ( defined( 'WP_MEMCACHED_SERVERS' ) ) {
			$servers_str = is_array( WP_MEMCACHED_SERVERS ) ? implode( ',', WP_MEMCACHED_SERVERS ) : (string) WP_MEMCACHED_SERVERS;
		} else {
			$servers_str = (string) get_option( 'aips_cache_memcached_servers', '127.0.0.1:11211' );
		}

		$this->servers = $this->parse_servers( $servers_str );

		// Key Prefix
		if ( ! empty( $config['prefix'] ) ) {
			$this->prefix = rtrim( (string) $config['prefix'], ':' ) . ':';
		} elseif ( defined( 'WP_CACHE_KEY_SALT' ) ) {
			$this->prefix = 'aips:' . sanitize_key( WP_CACHE_KEY_SALT ) . ':';
		}
	}

	/**
	 * Parse server pool string into host/port array.
	 *
	 * @param string $servers_str Comma- or newline-separated list of host:port.
	 * @return array<int, array{host: string, port: int, weight: int}>
	 */
	protected function parse_servers( string $servers_str ): array {
		$parsed  = array();
		$entries = preg_split( '/[\r\n,]+/', $servers_str );

		foreach ( $entries as $entry ) {
			$entry = trim( $entry );
			if ( empty( $entry ) ) {
				continue;
			}

			$parts  = explode( ':', $entry );
			$host   = ! empty( $parts[0] ) ? trim( $parts[0] ) : '127.0.0.1';
			$port   = isset( $parts[1] ) ? (int) $parts[1] : 11211;
			$weight = isset( $parts[2] ) ? (int) $parts[2] : 1;

			$parsed[] = array(
				'host'   => $host,
				'port'   => $port,
				'weight' => $weight,
			);
		}

		if ( empty( $parsed ) ) {
			$parsed[] = array(
				'host'   => '127.0.0.1',
				'port'   => 11211,
				'weight' => 1,
			);
		}

		return $parsed;
	}

	/**
	 * Connect to Memcached server pool.
	 *
	 * @return bool
	 */
	public function connect(): bool {
		if ( ! class_exists( 'Memcached' ) ) {
			$this->connected = false;
			return false;
		}

		try {
			$client = new \Memcached( 'aips_pool' );

			if ( empty( $client->getServerList() ) ) {
				$formatted = array();
				foreach ( $this->servers as $s ) {
					$formatted[] = array( $s['host'], $s['port'], $s['weight'] );
				}
				$client->addServers( $formatted );
			}

			// Verify basic connectivity
			$stats = $client->getStats();
			if ( empty( $stats ) ) {
				$this->connected = false;
				$this->memcached = null;
				return false;
			}

			$this->memcached = $client;
			$this->connected = true;
			return true;
		} catch ( \Throwable $e ) {
			$this->connected = false;
			$this->memcached = null;
			return false;
		}
	}

	/**
	 * Format key with prefix and group.
	 *
	 * @param string $key
	 * @param string $group
	 * @return string
	 */
	protected function format_key( string $key, string $group ): string {
		return $this->prefix . $group . ':' . $key;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available() {
		return $this->connected && $this->memcached !== null;
	}

	/**
	 * Test server connection and measure round-trip latency.
	 *
	 * @return array{success: bool, latency_ms: float, message: string}
	 */
	public function test_connection(): array {
		if ( ! class_exists( 'Memcached' ) ) {
			return array(
				'success'    => false,
				'latency_ms' => 0.0,
				'message'    => __( 'The PHP Memcached extension is not installed on this server.', 'ai-post-scheduler' ),
			);
		}

		if ( ! $this->is_available() ) {
			$reconnected = $this->connect();
			if ( ! $reconnected || ! $this->memcached ) {
				return array(
					'success'    => false,
					'latency_ms' => 0.0,
					'message'    => __( 'Could not connect to Memcached server pool.', 'ai-post-scheduler' ),
				);
			}
		}

		try {
			$start    = microtime( true );
			$test_key = $this->prefix . 'test:' . uniqid( '', true );

			$this->memcached->set( $test_key, 'ping', 10 );
			$val = $this->memcached->get( $test_key );
			$this->memcached->delete( $test_key );

			$latency = ( microtime( true ) - $start ) * 1000;

			if ( 'ping' === $val ) {
				return array(
					'success'    => true,
					'latency_ms' => round( $latency, 2 ),
					'message'    => sprintf(
						/* translators: 1: latency ms, 2: server count */
						__( 'Connected to Memcached (%2$d server(s)) in %1$0.2f ms.', 'ai-post-scheduler' ),
						$latency,
						count( $this->servers )
					),
				);
			}

			return array(
				'success'    => false,
				'latency_ms' => round( $latency, 2 ),
				'message'    => __( 'Write/read test failed on Memcached connection.', 'ai-post-scheduler' ),
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
			$val      = $this->memcached->get( $full_key );

			if ( \Memcached::RES_NOTFOUND === $this->memcached->getResultCode() ) {
				return null;
			}

			return $val;
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
			$map = array();
			foreach ( $keys as $k ) {
				$full_key         = $this->format_key( $k, $group );
				$map[ $full_key ] = $k;
			}

			$values = $this->memcached->getMulti( array_keys( $map ) );

			if ( is_array( $values ) ) {
				foreach ( $values as $full_k => $v ) {
					if ( isset( $map[ $full_k ] ) ) {
						$results[ $map[ $full_k ] ] = $v;
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Fallback to nulls.
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
			$full_key = $this->format_key( $key, $group );
			return (bool) $this->memcached->set( $full_key, $value, (int) $ttl );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Set multiple items in one batch.
	 *
	 * @param array<string, mixed> $items Key => value map.
	 * @param int                  $ttl   TTL in seconds.
	 * @param string               $group Cache group.
	 * @return bool
	 */
	public function set_multiple( array $items, int $ttl = 0, string $group = 'default' ): bool {
		if ( empty( $items ) || ! $this->is_available() ) {
			return false;
		}

		try {
			$batch = array();
			foreach ( $items as $key => $value ) {
				$batch[ $this->format_key( (string) $key, $group ) ] = $value;
			}

			return (bool) $this->memcached->setMulti( $batch, (int) $ttl );
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
			return (bool) $this->memcached->delete( $full_key );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Delete multiple keys.
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

			$res = $this->memcached->deleteMulti( $full_keys );
			return ! empty( $res );
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
			return (bool) $this->memcached->flush();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( $key, $group = 'default' ) {
		return $this->get( $key, $group ) !== null;
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
			'delete_group'  => false,
			'flush_plugin'  => true,
			'size_bytes'    => false,
			'ttl_remaining' => false,
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
		return array();
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
		return false;
	}

	/**
	 * Get the driver identifier name.
	 *
	 * @return string
	 */
	public function get_driver_name(): string {
		return 'memcached';
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
			$stats = $this->memcached->getStats();
			if ( is_array( $stats ) ) {
				foreach ( $stats as $server_stats ) {
					if ( is_array( $server_stats ) && isset( $server_stats['bytes'] ) ) {
						$bytes += (int) $server_stats['bytes'];
					}
				}
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
			'driver'      => 'memcached',
			'label'       => __( 'Memcached', 'ai-post-scheduler' ),
			'persistent'  => true,
			'servers'     => $this->servers,
			'connected'   => $this->connected,
			'limitations' => array(
				__( 'Distributed high-performance in-memory caching.', 'ai-post-scheduler' ),
			),
		);
	}
}
