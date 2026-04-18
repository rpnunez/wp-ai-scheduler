<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Cache
 *
 * Central cache access layer for the AI Post Scheduler plugin.
 *
 * Wraps a concrete AIPS_Cache_Driver implementation and exposes a
 * higher-level API with group/namespace support, TTL handling, and a
 * "remember" helper for cache-aside patterns.
 *
 * Typical usage:
 *   $cache = AIPS_Cache_Factory::instance();
 *   $value = $cache->remember( 'my_key', 3600, function() { return expensive_call(); } );
 *
 * @package AI_Post_Scheduler
 * @since   2.3.0
 */
class AIPS_Cache {

	/**
	 * Underlying cache driver.
	 *
	 * @var AIPS_Cache_Driver
	 */
	private $driver;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Cache_Driver|null $driver Optional driver. When null, the
	 *                                        factory resolves the driver from
	 *                                        the current admin settings.
	 */
	public function __construct( AIPS_Cache_Driver $driver = null ) {
		if ($driver === null) {
			$driver = AIPS_Cache_Factory::make_driver();
		}
		$this->driver = $driver;
	}

	// -----------------------------------------------------------------------
	// Core API
	// -----------------------------------------------------------------------

	/**
	 * Retrieve a value from the cache.
	 *
	 * @param string $key     Cache key.
	 * @param string $group   Cache group. Default 'default'.
	 * @param mixed  $default Value to return on a cache miss. Default null.
	 * @return mixed Cached value or $default.
	 */
	public function get( $key, $group = 'default', $default = null ) {
		$value = $this->driver->get( $key, $group );
		$this->record_cache_event(
			'get',
			array(
				'key'   => (string) $key,
				'group' => (string) $group,
				'hit'   => null !== $value,
			)
		);
		return $value !== null ? $value : $default;
	}

	/**
	 * Store a value in the cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time-to-live in seconds. 0 = no expiration. Default 0.
	 * @param string $group Cache group. Default 'default'.
	 * @return bool True on success.
	 */
	public function set( $key, $value, $ttl = 0, $group = 'default' ) {
		$result = $this->driver->set( $key, $value, (int) $ttl, $group );
		$this->record_cache_event(
			'set',
			array(
				'key'     => (string) $key,
				'group'   => (string) $group,
				'ttl'     => (int) $ttl,
				'success' => (bool) $result,
			)
		);
		return $result;
	}

	/**
	 * Remove a value from the cache.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Default 'default'.
	 * @return bool True on success.
	 */
	public function delete( $key, $group = 'default' ) {
		$result = $this->driver->delete( $key, $group );
		$this->record_cache_event(
			'delete',
			array(
				'key'     => (string) $key,
				'group'   => (string) $group,
				'success' => (bool) $result,
			)
		);
		return $result;
	}

	/**
	 * Check whether a key exists in the cache.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Default 'default'.
	 * @return bool True if the key exists and has not expired.
	 */
	public function has( $key, $group = 'default' ) {
		$result = $this->driver->has( $key, $group );
		$this->record_cache_event(
			'has',
			array(
				'key'     => (string) $key,
				'group'   => (string) $group,
				'present' => (bool) $result,
			)
		);
		return $result;
	}

	/**
	 * Flush all values from the cache.
	 *
	 * @return bool True on success.
	 */
	public function flush() {
		$result = $this->driver->flush();
		$this->record_cache_event(
			'flush',
			array(
				'success' => (bool) $result,
			)
		);
		return $result;
	}

	// -----------------------------------------------------------------------
	// Higher-level helpers
	// -----------------------------------------------------------------------

	/**
	 * Get a cached value, computing and storing it on a cache miss.
	 *
	 * @param string   $key      Cache key.
	 * @param int      $ttl      Time-to-live in seconds.
	 * @param callable $callback Callable that returns the value to cache.
	 * @param string   $group    Cache group. Default 'default'.
	 * @return mixed Cached or freshly computed value.
	 */
	public function remember( $key, $ttl, $callback, $group = 'default' ) {
		if ($this->has( $key, $group )) {
			$this->record_cache_event(
				'remember',
				array(
					'key'   => (string) $key,
					'group' => (string) $group,
					'ttl'   => (int) $ttl,
					'hit'   => true,
				)
			);
			return $this->get( $key, $group );
		}

		$value = $callback();
		$this->set( $key, $value, (int) $ttl, $group );
		$this->record_cache_event(
			'remember',
			array(
				'key'   => (string) $key,
				'group' => (string) $group,
				'ttl'   => (int) $ttl,
				'hit'   => false,
			)
		);

		return $value;
	}

	/**
	 * Increment a numeric value in the cache.
	 *
	 * If the key does not exist, it is initialised to 0 before incrementing.
	 * Note: counters are always stored with TTL=0 (no expiration).
	 *
	 * @param string $key   Cache key.
	 * @param int    $step  Amount to add. Default 1.
	 * @param string $group Cache group. Default 'default'.
	 * @return int New value.
	 */
	public function increment( $key, $step = 1, $group = 'default' ) {
		$value = (int) $this->get( $key, $group, 0 );
		$value += (int) $step;
		$this->set( $key, $value, 0, $group );
		$this->record_cache_event(
			'increment',
			array(
				'key'   => (string) $key,
				'group' => (string) $group,
				'step'  => (int) $step,
				'value' => $value,
			)
		);
		return $value;
	}

	/**
	 * Decrement a numeric value in the cache.
	 *
	 * If the key does not exist, it is initialised to 0 before decrementing.
	 * Note: counters are always stored with TTL=0 (no expiration).
	 *
	 * @param string $key   Cache key.
	 * @param int    $step  Amount to subtract. Default 1.
	 * @param string $group Cache group. Default 'default'.
	 * @return int New value.
	 */
	public function decrement( $key, $step = 1, $group = 'default' ) {
		$value = (int) $this->get( $key, $group, 0 );
		$value -= (int) $step;
		$this->set( $key, $value, 0, $group );
		$this->record_cache_event(
			'decrement',
			array(
				'key'   => (string) $key,
				'group' => (string) $group,
				'step'  => (int) $step,
				'value' => $value,
			)
		);
		return $value;
	}

	// -----------------------------------------------------------------------
	// Introspection
	// -----------------------------------------------------------------------

	/**
	 * Return the underlying driver instance.
	 *
	 * @return AIPS_Cache_Driver
	 */
	public function get_driver() {
		return $this->driver;
	}

	/**
	 * Record a cache-specific telemetry event when request telemetry is enabled.
	 *
	 * @param string $operation Cache operation name.
	 * @param array  $data      Additional event metadata.
	 * @return void
	 */
	private function record_cache_event( $operation, array $data ) {
		if (!class_exists( 'AIPS_Telemetry' ) || !AIPS_Telemetry::is_enabled()) {
			return;
		}

		$driver = get_class( $this->driver );
		AIPS_Telemetry::instance()->add_event(
			'cache',
			array_merge(
				array(
					'type'      => 'cache_' . sanitize_key( $operation ),
					'operation' => sanitize_key( $operation ),
					'driver'    => sanitize_text_field( $driver ),
				),
				$data
			)
		);
	}
}
