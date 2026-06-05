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
	 * Repository cache observer for tag invalidation telemetry.
	 *
	 * @var AIPS_Repository_Cache_Observer|null
	 */
	private $repository_cache_observer;

	/**
	 * Cache index for the Cache Monitor.
	 *
	 * @var AIPS_Cache_Index|null
	 */
	private $cache_index = null;

	/**
	 * Context for the next set() call. Consumed after one use.
	 *
	 * @var array
	 */
	private $pending_context = array();

	/**
	 * Per-request memoised result of the system-enabled check.
	 *
	 * Null means "not yet read". Populated on the first call to
	 * is_system_enabled() and reset by reset_system_enabled_flag().
	 *
	 * @var bool|null
	 */
	private static $system_enabled = null;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Cache_Driver|null $driver Optional driver. When null, the
	 *                                        factory resolves the driver from
	 *                                        the current admin settings.
	 */
	public function __construct( AIPS_Cache_Driver $driver = null, AIPS_Repository_Cache_Observer $repository_cache_observer = null ) {
		if ($driver === null) {
			$driver = AIPS_Cache_Factory::make_driver();
		}
		$this->driver                    = $driver;
		$this->repository_cache_observer = $repository_cache_observer;
	}

	// -----------------------------------------------------------------------
	// Driver + Index accessors
	// -----------------------------------------------------------------------

	/**
	 * Fluently set metadata context for the next set() call.
	 *
	 * The context array is consumed once by the next set() invocation and
	 * then cleared, so callers should call with_context() immediately before
	 * set() / remember() in the same execution frame.
	 *
	 * @param array $context Metadata: tags, tier, operation_id, repository_class, domain.
	 * @return static Fluent return.
	 */
	public function with_context( array $context ): static {
		$this->pending_context = $context;
		return $this;
	}

	/**
	 * Lazy-initialise and return the Cache Index singleton for this request.
	 *
	 * If the Cache Monitor or the index is disabled, returns null.
	 *
	 * @return AIPS_Cache_Index|null
	 */
	private function get_cache_index(): ?AIPS_Cache_Index {
		if ($this->cache_index !== null) {
			return $this->cache_index;
		}
		// Avoid double-instantiation on repeated misses by checking the flag.
		static $checked = false;
		if ($checked) {
			return null;
		}
		$checked = true;

		$enabled = get_option('aips_cache_monitor_index_enabled', '1');
		if ($enabled === '0' || $enabled === 0 || $enabled === false) {
			return null;
		}

		$this->cache_index = new AIPS_Cache_Index();
		return $this->cache_index;
	}

	// -----------------------------------------------------------------------
	// Core API
	// -----------------------------------------------------------------------

	/**
	 * Whether the plugin cache system is currently enabled.
	 *
	 * Reads the 'aips_enable_cache_system' WordPress option directly (without
	 * going through AIPS_Config) to avoid a bootstrapping circular dependency,
	 * and memoises the result for the duration of the request.
	 *
	 * @return bool True when the cache system is enabled.
	 */
	private static function is_system_enabled() {
		if (self::$system_enabled === null) {
			// Use get_option() directly — not AIPS_Config — to avoid circular
			// dependency (AIPS_Config itself uses AIPS_Cache internally).
			// Default to enabled (true) when the option has never been saved.
			$raw                 = get_option( 'aips_enable_cache_system', '1' );
			self::$system_enabled = ($raw !== '0' && $raw !== 0 && $raw !== false);
		}
		return self::$system_enabled;
	}

	/**
	 * Reset the memoised system-enabled flag.
	 *
	 * Must be called after updating the 'aips_enable_cache_system' option so
	 * that subsequent calls to is_system_enabled() re-read the stored value.
	 * Also useful in test suites that change the option between test methods.
	 *
	 * @return void
	 */
	public static function reset_system_enabled_flag() {
		self::$system_enabled = null;
	}

	/**
	 * Retrieve a value from the cache.
	 *
	 * Returns $default immediately when the cache system is disabled.
	 *
	 * @param string $key     Cache key.
	 * @param string $group   Cache group. Default 'default'.
	 * @param mixed  $default Value to return on a cache miss. Default null.
	 * @return mixed Cached value or $default.
	 */
	public function get( $key, $group = 'default', $default = null ) {
		if (!self::is_system_enabled()) {
			return $default;
		}
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
	 * Returns true immediately (no-op) when the cache system is disabled.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time-to-live in seconds. 0 = no expiration. Default 0.
	 * @param string $group Cache group. Default 'default'.
	 * @return bool True on success.
	 */
	public function set( $key, $value, $ttl = 0, $group = 'default' ) {
		if (!self::is_system_enabled()) {
			return true;
		}
		$result = $this->driver->set( $key, $value, (int) $ttl, $group );
		if ($result) {
			$context               = $this->pending_context;
			$this->pending_context = array();
			$index = $this->get_cache_index();
			if ($index) {
				$index->record_set( (string) $key, $value, (int) $ttl, (string) $group, $context );
			}
		}
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
	 * Returns true immediately (no-op) when the cache system is disabled.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Default 'default'.
	 * @return bool True on success.
	 */
	public function delete( $key, $group = 'default' ) {
		if (!self::is_system_enabled()) {
			return true;
		}
		$result = $this->driver->delete( $key, $group );
		if ($result) {
			$index = $this->get_cache_index();
			if ($index) {
				$index->record_delete( (string) $key, (string) $group );
			}
		}
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
	 * Returns false immediately when the cache system is disabled.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Default 'default'.
	 * @return bool True if the key exists and has not expired.
	 */
	public function has( $key, $group = 'default' ) {
		if (!self::is_system_enabled()) {
			return false;
		}
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
	 * Returns true immediately (no-op) when the cache system is disabled.
	 *
	 * @return bool True on success.
	 */
	public function flush() {
		if (!self::is_system_enabled()) {
			return true;
		}
		$result = $this->driver->flush();
		if ($result) {
			$index = $this->get_cache_index();
			if ($index) {
				$index->record_flush();
			}
		}
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
	 * When the cache system is disabled the callback is always invoked and
	 * its result is returned directly without reading from or writing to
	 * any cache storage.
	 *
	 * @param string   $key      Cache key.
	 * @param int      $ttl      Time-to-live in seconds.
	 * @param callable $callback Callable that returns the value to cache.
	 * @param string   $group    Cache group. Default 'default'.
	 * @return mixed Cached or freshly computed value.
	 */
	public function remember( $key, $ttl, $callback, $group = 'default' ) {
		if (!self::is_system_enabled()) {
			return $callback();
		}
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
	 * When the cache system is disabled, no read or write is performed and
	 * the method returns 0 + $step (as if starting from zero).
	 *
	 * @param string $key   Cache key.
	 * @param int    $step  Amount to add. Default 1.
	 * @param string $group Cache group. Default 'default'.
	 * @return int New value.
	 */
	public function increment( $key, $step = 1, $group = 'default' ) {
		if (!self::is_system_enabled()) {
			return (int) $step;
		}
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
	 * When the cache system is disabled, no read or write is performed and
	 * the method returns 0 - $step (as if starting from zero).
	 *
	 * @param string $key   Cache key.
	 * @param int    $step  Amount to subtract. Default 1.
	 * @param string $group Cache group. Default 'default'.
	 * @return int New value.
	 */
	public function decrement( $key, $step = 1, $group = 'default' ) {
		if (!self::is_system_enabled()) {
			return -(int) $step;
		}
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

	/**
	 * Retrieve the current version for a cache invalidation tag.
	 *
	 * Missing tags default to version 1 without writing to storage.
	 *
	 * @param string $tag Cache invalidation tag.
	 * @param string $group Cache group. Default 'default'.
	 * @return int Tag version.
	 */
	public function get_tag_version( $tag, $group = 'default' ) {
		if (!self::is_system_enabled()) {
			return 1;
		}

		$key     = $this->build_tag_version_key( $tag );
		$version = $this->get( $key, $group, 1 );

		return max( 1, (int) $version );
	}

	/**
	 * Increment and return the version for a cache invalidation tag.
	 *
	 * Missing tags are seeded to version 1 before incrementing so the first
	 * bump advances them to version 2.
	 *
	 * @param string $tag Cache invalidation tag.
	 * @param string $group Cache group. Default 'default'.
	 * @return int New tag version.
	 */
	public function bump_tag_version( $tag, $group = 'default' ) {
		$tag_key = $this->sanitize_tag( $tag );

		if (!self::is_system_enabled()) {
			$this->record_tag_bump_event( array( $tag_key ), $group );
			return 2;
		}

		$key     = $this->build_tag_version_key( $tag_key );
		$current = $this->get( $key, $group, null );
		$version = null === $current ? 2 : max( 2, (int) $current + 1 );
		$this->set( $key, $version, 0, $group );
		$this->record_tag_bump_event( array( $tag_key ), $group );

		return max( 1, (int) $version );
	}

	/**
	 * Retrieve the current versions for multiple cache invalidation tags.
	 *
	 * Missing tags default to version 1. Returned keys are sanitized tag names.
	 *
	 * @param array  $tags Cache invalidation tags.
	 * @param string $group Cache group. Default 'default'.
	 * @return array<string, int>
	 */
	public function get_tag_versions( array $tags, $group = 'default' ) {
		$versions = array();

		foreach ( $this->sanitize_tags( $tags ) as $tag ) {
			$versions[ $tag ] = $this->get_tag_version( $tag, $group );
		}

		return $versions;
	}

	/**
	 * Increment and return versions for multiple cache invalidation tags.
	 *
	 * Returned keys are sanitized tag names.
	 *
	 * @param array  $tags Cache invalidation tags.
	 * @param string $group Cache group. Default 'default'.
	 * @return array<string, int>
	 */
	public function bump_tag_versions( array $tags, $group = 'default' ) {
		$versions       = array();
		$sanitized_tags = $this->sanitize_tags( $tags );

		if (!self::is_system_enabled()) {
			foreach ( $sanitized_tags as $tag ) {
				$versions[ $tag ] = 2;
			}

			$this->record_tag_bump_event( $sanitized_tags, $group );
			return $versions;
		}

		foreach ( $sanitized_tags as $tag ) {
			$key     = $this->build_tag_version_key( $tag );
			$current = $this->get( $key, $group, null );
			$version = null === $current ? 2 : max( 2, (int) $current + 1 );
			$this->set( $key, $version, 0, $group );
			$versions[ $tag ] = $version;
		}

		$this->record_tag_bump_event( $sanitized_tags, $group );

		return $versions;
	}

	// -----------------------------------------------------------------------
	// Introspection
	// -----------------------------------------------------------------------

	/**
	 * Return the underlying driver instance.
	 *
	 * @return AIPS_Cache_Driver
	 */
	public function get_driver(): AIPS_Cache_Driver {
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

	/**
	 * Build the underlying cache key used to store a tag version counter.
	 *
	 * @param string $tag Cache invalidation tag.
	 * @return string
	 */
	private function build_tag_version_key( $tag ) {
		return 'tag_version:' . $this->sanitize_tag( $tag );
	}

	/**
	 * Normalize a tag into a stable cache-safe identifier.
	 *
	 * @param string $tag Cache invalidation tag.
	 * @return string
	 */
	private function sanitize_tag( $tag ) {
		$tag = strtolower( trim( (string) $tag ) );
		$tag = preg_replace( '/[^a-z0-9_-]+/', '_', $tag );
		$tag = trim( (string) $tag, '_' );

		return '' !== $tag ? $tag : 'tag';
	}

	/**
	 * Normalize and de-duplicate a list of tags while preserving first-seen order.
	 *
	 * @param array $tags Raw cache invalidation tags.
	 * @return array<int, string>
	 */
	private function sanitize_tags( array $tags ) {
		$clean = array();

		foreach ( $tags as $tag ) {
			if ( ! is_scalar( $tag ) ) {
				continue;
			}

			$tag = trim( (string) $tag );
			if ( '' === $tag ) {
				continue;
			}

			$sanitized = $this->sanitize_tag( $tag );
			if ( ! in_array( $sanitized, $clean, true ) ) {
				$clean[] = $sanitized;
			}
		}

		return $clean;
	}

	/**
	 * Record a repository-cache invalidation event for one or more bumped tags.
	 *
	 * @param array  $tags Sanitized cache invalidation tags.
	 * @param string $group Cache group.
	 * @return void
	 */
	private function record_tag_bump_event( array $tags, $group ) {
		$observer = $this->get_repository_cache_observer();
		if (!$observer || empty( $tags )) {
			return;
		}

		$observer->record_invalidation(
			array(
				'repository'          => 'AIPS_Cache',
				'operation_id'        => 'cache.tag.bump',
				'cache_group'         => (string) $group,
				'tags'                => $tags,
				'invalidation_reason' => 'tag_bump',
			)
		);
	}

	/**
	 * Resolve the repository cache observer lazily when available.
	 *
	 * @return AIPS_Repository_Cache_Observer|null
	 */
	private function get_repository_cache_observer() {
		if ($this->repository_cache_observer instanceof AIPS_Repository_Cache_Observer) {
			return $this->repository_cache_observer;
		}

		if (!class_exists( 'AIPS_Repository_Cache_Observer' )) {
			return null;
		}

		$this->repository_cache_observer = new AIPS_Repository_Cache_Observer();

		return $this->repository_cache_observer;
	}
}
