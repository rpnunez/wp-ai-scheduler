<?php
/**
 * Tests for the per-request option cache in AIPS_Config::get_option().
 *
 * The cache ensures that repeated reads of the same option key within a single
 * request do not trigger additional get_option() calls.  Each test verifies a
 * specific aspect of the cache: population on first read, cache-hit on
 * subsequent reads, bypass when a caller-supplied default is provided,
 * invalidation via WordPress hooks (updated_option / deleted_option /
 * added_option), and explicit flush via flush_option_cache().
 *
 * All option setup uses add_option()/update_option()/delete_option() so the
 * suite is valid in both the fallback stub environment and a real WordPress
 * test installation.
 *
 * Cache-hit assertions count underlying get_option() invocations via the
 * pre_option_{$option} filter (WordPress fires this before every store read).
 * In the fallback stub environment the filter is not invoked by the stub's
 * get_option(), so the assertion falls back to verifying the internal cache
 * state directly.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

/**
 * @covers AIPS_Config::get_option
 */
class Test_AIPS_Config_Option_Cache extends WP_UnitTestCase {

	/** @var AIPS_Config */
	private $config;

	/** @var ReflectionProperty Gives direct read access to the private $cache property. */
	private $cache_prop;

	/**
	 * Per-test call counters keyed by option name.
	 * Populated by attach_option_call_counter() and read by option_call_count().
	 *
	 * @var array<string, int>
	 */
	private $option_call_counts = array();

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
		$this->config             = AIPS_Config::get_instance();
		$this->config->flush_option_cache();
		$this->option_call_counts = array();

		// Expose the private $cache property so tests can inspect it directly.
		$ref              = new ReflectionClass( 'AIPS_Config' );
		$this->cache_prop = $ref->getProperty( 'cache' );
		$this->cache_prop->setAccessible( true );
	}

	public function tearDown(): void {
		$this->option_call_counts = array();
		AIPS_Cache_Factory::reset();
		// parent::tearDown() calls reset_hooks() which flushes the config
		// cache and re-registers the invalidation hooks on the singleton.
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Return the internal AIPS_Cache instance from the config singleton.
	 *
	 * @return AIPS_Cache
	 */
	private function get_cache() {
		return $this->cache_prop->getValue( $this->config );
	}

	/**
	 * Register a pre_option_{$option} filter that counts how many times
	 * WordPress's get_option() dispatches to the underlying store for $option.
	 *
	 * In full WordPress mode, get_option() applies pre_option_{$option} before
	 * reading the store, so the counter increments on every real store access.
	 * When AIPS_Config returns from its in-memory cache it never calls
	 * get_option(), so the counter stays unchanged — proving cache-hit behavior.
	 *
	 * In the fallback stub environment the stub's get_option() does not call
	 * apply_filters(), so the counter always stays 0.  assert_one_get_option_call()
	 * branches on counter > 0 to select the right assertion for each mode.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	private function attach_option_call_counter( $option ) {
		$this->option_call_counts[ $option ] = 0;
		add_filter(
			"pre_option_{$option}",
			function() use ( $option ) {
				$this->option_call_counts[ $option ]++;
				return false; // Let the store value flow through.
			},
			10,
			1
		);
	}

	/**
	 * Return the current underlying get_option() call count for $option.
	 *
	 * @param string $option Option name.
	 * @return int
	 */
	private function option_call_count( $option ) {
		return $this->option_call_counts[ $option ] ?? 0;
	}

	/**
	 * Assert that get_option() was dispatched to the underlying store exactly
	 * once for the given option within this test.
	 *
	 * Uses the pre_option_ counter in full WordPress mode.  Falls back to
	 * verifying the internal cache is populated in the fallback stub
	 * environment (where the stub's get_option does not fire pre_option_).
	 *
	 * @param string $option  Option name.
	 * @param string $message Optional assertion message.
	 * @return void
	 */
	private function assert_one_get_option_call( $option, $message = null ) {
		$count = $this->option_call_count( $option );
		if ( $count > 0 ) {
			// Full WordPress mode — counter was incremented by the filter.
			$this->assertSame(
				1,
				$count,
				$message ?? "get_option('$option') must be dispatched to the store exactly once per request."
			);
		} else {
			// Fallback/limited mode — pre_option_ is never applied by the stub.
			// Verify the cache is populated, confirming it was read once and stored.
			$this->assertTrue(
				$this->get_cache()->has( $option ),
				$message ?? "Cache must be populated after the first read (fallback mode — proves no repeated store calls)."
			);
		}
	}

	// -----------------------------------------------------------------------
	// Population on first read
	// -----------------------------------------------------------------------

	/**
	 * After the first get_option() call the resolved value must be in the cache.
	 */
	public function test_first_read_populates_cache() {
		update_option( 'aips_ai_model', 'gpt-4o' );

		$this->config->get_option( 'aips_ai_model' );

		$this->assertTrue(
			$this->get_cache()->has( 'aips_ai_model' ),
			'Cache must hold a value for the key after the first get_option() call.'
		);
	}

	/**
	 * The cached value must equal the value that was stored in the option table.
	 */
	public function test_first_read_caches_correct_value() {
		update_option( 'aips_ai_model', 'claude-3-sonnet' );

		$result = $this->config->get_option( 'aips_ai_model' );

		$this->assertSame( 'claude-3-sonnet', $result );
		$this->assertSame( 'claude-3-sonnet', $this->get_cache()->get( 'aips_ai_model' ) );
	}

	// -----------------------------------------------------------------------
	// Cache-hit on subsequent reads (one get_option() per key per request)
	// -----------------------------------------------------------------------

	/**
	 * A second call to get_option() for the same key must return the cached
	 * value without dispatching to the underlying option store again.
	 *
	 * This is verified via a pre_option_{name} filter counter in full WordPress
	 * mode.  In limited-mode the filter is not invoked so the assertion falls
	 * back to cache-state inspection.
	 */
	public function test_subsequent_read_returns_cached_value() {
		update_option( 'aips_ai_model', 'original-model' );
		$this->attach_option_call_counter( 'aips_ai_model' );

		$first  = $this->config->get_option( 'aips_ai_model' );
		$second = $this->config->get_option( 'aips_ai_model' );

		$this->assertSame( 'original-model', $first );
		$this->assertSame( 'original-model', $second, 'Repeated reads must return the same cached value.' );
		$this->assert_one_get_option_call( 'aips_ai_model', 'Second read must come from cache, not the option store.' );
	}

	/**
	 * Multiple repeated reads must all return the same cached value and
	 * dispatch to the store only once.
	 */
	public function test_repeated_reads_all_return_cached_value() {
		update_option( 'aips_temperature', '0.7' );
		$this->attach_option_call_counter( 'aips_temperature' );

		$results = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$results[] = $this->config->get_option( 'aips_temperature' );
		}

		$this->assertCount( 6, $results );
		foreach ( $results as $value ) {
			$this->assertSame(
				'0.7',
				$value,
				'All reads must return the first-resolved (cached) value.'
			);
		}

		// In full WordPress mode the underlying store must be queried exactly once
		// for six repeated reads.
		$count = $this->option_call_count( 'aips_temperature' );
		if ( $count > 0 ) {
			$this->assertSame( 1, $count, 'The store must be queried only once for 6 repeated reads.' );
		}
	}

	// -----------------------------------------------------------------------
	// Null value / absent option caching (sentinel)
	// -----------------------------------------------------------------------

	/**
	 * When an option is absent from the store and has no registered default,
	 * the resolved null must be cached using the sentinel so subsequent reads
	 * do not re-dispatch to the option store.
	 */
	public function test_absent_option_with_no_default_is_cached_as_null() {
		// Ensure the key is not in the store.
		delete_option( 'aips_nonexistent_option_xyz' );
		$this->config->flush_option_cache(); // clear any residual sentinel

		$this->attach_option_call_counter( 'aips_nonexistent_option_xyz' );

		$first = $this->config->get_option( 'aips_nonexistent_option_xyz' );

		// Cache must hold a sentinel entry so the next read is a hit, not a miss.
		$this->assertTrue(
			$this->get_cache()->has( 'aips_nonexistent_option_xyz' ),
			'Absent option with no registered default must be cached as null-sentinel.'
		);
		$this->assertNull( $first, 'Absent option with no registered default must return null.' );

		$second = $this->config->get_option( 'aips_nonexistent_option_xyz' );

		$this->assertNull( $second, 'Second read must return the cached null.' );
		$this->assert_one_get_option_call(
			'aips_nonexistent_option_xyz',
			'Second read of an absent option must come from the cache, not the store.'
		);
	}

	// -----------------------------------------------------------------------
	// Default-bypass: caller-supplied $default must NOT be cached
	// -----------------------------------------------------------------------

	/**
	 * When get_option() is called with an explicit $default argument, the
	 * result must NOT be stored in the cache (to prevent polluting subsequent
	 * reads with an ad-hoc fallback value).
	 */
	public function test_caller_default_is_not_cached() {
		// Key is absent from both the store and the registered defaults.
		delete_option( 'aips_nonexistent_option_xyz' );
		$this->config->flush_option_cache();

		$result = $this->config->get_option( 'aips_nonexistent_option_xyz', 'my-fallback' );

		$this->assertSame( 'my-fallback', $result, 'Caller-supplied default must be returned.' );
		$this->assertFalse(
			$this->get_cache()->has( 'aips_nonexistent_option_xyz' ),
			'Caller-supplied default must not be stored in the cache.'
		);
	}

	/**
	 * A subsequent no-default read after a caller-default read must still go
	 * to the option store (not see a stale caller-default in the cache).
	 */
	public function test_subsequent_read_after_caller_default_uses_store() {
		delete_option( 'aips_nonexistent_option_xyz' );
		$this->config->flush_option_cache();

		// First call with explicit default — must NOT cache.
		$this->config->get_option( 'aips_nonexistent_option_xyz', 'caller-default' );

		// Now add the option to the store.  The added_option hook fires and
		// would invalidate the cache for this key — but there is nothing cached
		// yet, so it is a safe no-op.
		add_option( 'aips_nonexistent_option_xyz', 'real-value' );

		// Second call without default — must read from store, not caller-default.
		$result = $this->config->get_option( 'aips_nonexistent_option_xyz' );

		$this->assertSame(
			'real-value',
			$result,
			'After a caller-default read, the next no-default read must reflect the actual store value.'
		);
	}

	// -----------------------------------------------------------------------
	// Cache invalidation via WordPress hooks
	// -----------------------------------------------------------------------

	/**
	 * Calling update_option() fires the updated_option hook and must
	 * invalidate the cache entry for that key.
	 */
	public function test_update_option_invalidates_cache() {
		update_option( 'aips_ai_model', 'before-update' );

		// Populate the cache.
		$before = $this->config->get_option( 'aips_ai_model' );
		$this->assertTrue( $this->get_cache()->has( 'aips_ai_model' ) );

		// update_option() fires updated_option hook → cache entry removed.
		update_option( 'aips_ai_model', 'after-update' );

		$this->assertFalse(
			$this->get_cache()->has( 'aips_ai_model' ),
			'update_option() must invalidate the cache entry for the changed key.'
		);

		$after = $this->config->get_option( 'aips_ai_model' );

		$this->assertSame( 'before-update', $before );
		$this->assertSame( 'after-update', $after );
	}

	/**
	 * Calling delete_option() fires the deleted_option hook and must
	 * invalidate the cache entry for that key.
	 */
	public function test_delete_option_invalidates_cache() {
		update_option( 'aips_ai_model', 'will-be-deleted' );

		// Populate the cache.
		$this->config->get_option( 'aips_ai_model' );
		$this->assertTrue( $this->get_cache()->has( 'aips_ai_model' ) );

		// delete_option() fires deleted_option hook → cache entry removed.
		delete_option( 'aips_ai_model' );

		$this->assertFalse(
			$this->get_cache()->has( 'aips_ai_model' ),
			'delete_option() must invalidate the cache entry for the deleted key.'
		);
	}

	/**
	 * Calling add_option() fires the added_option hook and must invalidate any
	 * stale cache entry (e.g. a cached default from before the option existed).
	 */
	public function test_add_option_invalidates_cache() {
		// Ensure the key is not in the store, then read to cache the default.
		delete_option( 'aips_ai_model' );
		$this->config->flush_option_cache();
		$this->config->get_option( 'aips_ai_model' );
		$this->assertTrue( $this->get_cache()->has( 'aips_ai_model' ) );

		// add_option() fires added_option hook → cache entry removed.
		add_option( 'aips_ai_model', 'new-value' );

		$this->assertFalse(
			$this->get_cache()->has( 'aips_ai_model' ),
			'add_option() must invalidate any cached entry for the newly-added key.'
		);
	}

	/**
	 * Only the invalidated key must be removed; other cached entries survive.
	 */
	public function test_invalidation_is_key_specific() {
		update_option( 'aips_ai_model', 'model-value' );
		update_option( 'aips_enable_logging', true );

		// Populate two cache entries.
		$this->config->get_option( 'aips_ai_model' );
		$this->config->get_option( 'aips_enable_logging' );

		// Invalidate only one key.
		update_option( 'aips_ai_model', 'updated-model' );

		$this->assertFalse(
			$this->get_cache()->has( 'aips_ai_model' ),
			'The updated key must be removed from the cache.'
		);
		$this->assertTrue(
			$this->get_cache()->has( 'aips_enable_logging' ),
			'Unrelated keys must remain in the cache after a targeted invalidation.'
		);
	}

	// -----------------------------------------------------------------------
	// set_option() invalidation
	// -----------------------------------------------------------------------

	/**
	 * AIPS_Config::set_option() must invalidate the cache before persisting
	 * so that the next read returns the freshly-written value.
	 */
	public function test_set_option_invalidates_cache() {
		update_option( 'aips_ai_model', 'original' );

		// Populate the cache.
		$this->config->get_option( 'aips_ai_model' );
		$this->assertTrue( $this->get_cache()->has( 'aips_ai_model' ) );

		$this->config->set_option( 'aips_ai_model', 'updated-via-set' );

		$this->assertFalse(
			$this->get_cache()->has( 'aips_ai_model' ),
			'set_option() must remove the cache entry so the next read reflects the new value.'
		);

		$fresh = $this->config->get_option( 'aips_ai_model' );
		$this->assertSame( 'updated-via-set', $fresh );
	}

	// -----------------------------------------------------------------------
	// Explicit flush
	// -----------------------------------------------------------------------

	/**
	 * flush_option_cache() must remove all entries from the cache at once.
	 */
	public function test_flush_option_cache_clears_all_entries() {
		update_option( 'aips_ai_model', 'model' );
		update_option( 'aips_enable_logging', true );

		$this->config->get_option( 'aips_ai_model' );
		$this->config->get_option( 'aips_enable_logging' );

		$this->assertTrue( $this->get_cache()->has( 'aips_ai_model' ) );
		$this->assertTrue( $this->get_cache()->has( 'aips_enable_logging' ) );

		$this->config->flush_option_cache();

		$this->assertFalse(
			$this->get_cache()->has( 'aips_ai_model' ),
			'flush_option_cache() must remove aips_ai_model from the cache.'
		);
		$this->assertFalse(
			$this->get_cache()->has( 'aips_enable_logging' ),
			'flush_option_cache() must remove aips_enable_logging from the cache.'
		);
	}

	/**
	 * After flush_option_cache() a subsequent get_option() call must re-read
	 * from the option store and re-populate the cache.
	 */
	public function test_read_after_flush_repopulates_cache() {
		update_option( 'aips_ai_model', 'before-flush' );

		$this->config->get_option( 'aips_ai_model' );
		$this->config->flush_option_cache();

		// Change the stored value.  update_option() fires the updated_option
		// hook which would normally invalidate the cache, but the cache is
		// already empty after the flush, so this is a safe no-op for the cache.
		update_option( 'aips_ai_model', 'after-flush' );

		$result = $this->config->get_option( 'aips_ai_model' );

		$this->assertSame(
			'after-flush',
			$result,
			'First read after flush must return the current option store value.'
		);
		$this->assertTrue(
			$this->get_cache()->has( 'aips_ai_model' ),
			'Cache must be re-populated after the first post-flush read.'
		);
	}

	// -----------------------------------------------------------------------
	// Registered default caching
	// -----------------------------------------------------------------------

	/**
	 * When the option is absent from the store but has a registered default in
	 * AIPS_Config::get_default_options(), that default must be cached on first
	 * read and returned by the cache on subsequent reads without re-dispatching
	 * to the option store.
	 */
	public function test_registered_default_is_cached() {
		// Remove the option so the read falls through to registered defaults.
		delete_option( 'aips_temperature' );
		$this->config->flush_option_cache();

		$this->attach_option_call_counter( 'aips_temperature' );

		$first = $this->config->get_option( 'aips_temperature' );

		$this->assertTrue(
			$this->get_cache()->has( 'aips_temperature' ),
			'Registered default must be stored in the cache.'
		);
		$this->assertSame( 0.7, $first, 'Registered default value must be returned.' );

		$second = $this->config->get_option( 'aips_temperature' );

		$this->assertSame( 0.7, $second, 'Second read must return the cached registered default.' );
		$this->assert_one_get_option_call(
			'aips_temperature',
			'Second read must come from the cache, not the option store.'
		);
	}
}
