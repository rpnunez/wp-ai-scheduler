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

	public function setUp(): void {
		parent::setUp();
		// Flush cache factory registry and the config singleton's own cache to
		// ensure each test starts with a clean slate.  Hook re-registration is
		// handled by WP_UnitTestCase::tearDown() → reset_hooks().
		AIPS_Cache_Factory::reset();
		$this->config = AIPS_Config::get_instance();
		$this->config->flush_option_cache();

		// Expose the private $cache property so tests can inspect it directly.
		$ref              = new ReflectionClass( 'AIPS_Config' );
		$this->cache_prop = $ref->getProperty( 'cache' );
		$this->cache_prop->setAccessible( true );
	}

	public function tearDown(): void {
		// Reset the factory registry before the base-class tearDown so any
		// named instances created during the test are discarded cleanly.
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

	// -----------------------------------------------------------------------
	// Population on first read
	// -----------------------------------------------------------------------

	/**
	 * After the first get_option() call the resolved value must be in the cache.
	 */
	public function test_first_read_populates_cache() {
		$GLOBALS['aips_test_options']['aips_ai_model'] = 'gpt-4o';

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
		$GLOBALS['aips_test_options']['aips_ai_model'] = 'claude-3-sonnet';

		$result = $this->config->get_option( 'aips_ai_model' );

		$this->assertSame( 'claude-3-sonnet', $result );
		$this->assertSame( 'claude-3-sonnet', $this->get_cache()->get( 'aips_ai_model' ) );
	}

	// -----------------------------------------------------------------------
	// Cache-hit on subsequent reads (one get_option() per key per request)
	// -----------------------------------------------------------------------

	/**
	 * A second call to get_option() for the same key must return the cached
	 * value even when the underlying option store is mutated directly (i.e.,
	 * without firing WordPress hooks), proving that no second DB lookup occurs.
	 */
	public function test_subsequent_read_returns_cached_value() {
		$GLOBALS['aips_test_options']['aips_ai_model'] = 'original-model';

		// First read — populates the cache.
		$first = $this->config->get_option( 'aips_ai_model' );

		// Mutate the option store directly, bypassing WordPress hooks so no
		// cache-invalidation fires.  This simulates "another get_option() call
		// to WordPress" that the cache should prevent.
		$GLOBALS['aips_test_options']['aips_ai_model'] = 'mutated-model';

		// Second read — must come from cache, not from the option store.
		$second = $this->config->get_option( 'aips_ai_model' );

		$this->assertSame( 'original-model', $first );
		$this->assertSame(
			'original-model',
			$second,
			'Second read must return the cached value, not the mutated option store value.'
		);
	}

	/**
	 * Multiple repeated reads must all return the same cached value.
	 */
	public function test_repeated_reads_all_return_cached_value() {
		$GLOBALS['aips_test_options']['aips_temperature'] = '0.7';

		$results = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$results[] = $this->config->get_option( 'aips_temperature' );
		}

		// Mutate the store to prove subsequent reads are from cache.
		$GLOBALS['aips_test_options']['aips_temperature'] = '9.9';

		$results[] = $this->config->get_option( 'aips_temperature' );

		$this->assertCount( 6, $results );
		foreach ( $results as $value ) {
			$this->assertSame(
				'0.7',
				$value,
				'All reads must return the first-resolved (cached) value.'
			);
		}
	}

	// -----------------------------------------------------------------------
	// Null value caching (sentinel)
	// -----------------------------------------------------------------------

	/**
	 * When an option is absent from the store and has no registered default,
	 * the resolved null must be cached using the sentinel so subsequent reads
	 * do not re-hit the option store.
	 *
	 * Note: the test-environment get_option() stub uses isset(), which treats
	 * PHP null as "not set".  The null-caching path is therefore exercised
	 * via an option key that has no entry in the store and no registered
	 * default — both routes resolve to null and use the sentinel.
	 */
	public function test_absent_option_with_no_default_is_cached_as_null() {
		// Make sure the key is not in the store.
		unset( $GLOBALS['aips_test_options']['aips_nonexistent_option_xyz'] );

		$first = $this->config->get_option( 'aips_nonexistent_option_xyz' );

		// Cache must hold a sentinel entry so the next read is a hit, not a miss.
		$this->assertTrue(
			$this->get_cache()->has( 'aips_nonexistent_option_xyz' ),
			'Absent option with no registered default must be cached as null-sentinel.'
		);
		$this->assertNull( $first, 'Absent option with no registered default must return null.' );

		// Confirm the second read comes from cache (not a re-query).
		// Temporarily insert a value into the store without firing hooks.
		$GLOBALS['aips_test_options']['aips_nonexistent_option_xyz'] = 'should-not-appear';

		$second = $this->config->get_option( 'aips_nonexistent_option_xyz' );

		$this->assertNull(
			$second,
			'Second read must return cached null, not the value inserted into the store without invalidation.'
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
		unset( $GLOBALS['aips_test_options']['aips_nonexistent_option_xyz'] );

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
		unset( $GLOBALS['aips_test_options']['aips_nonexistent_option_xyz'] );

		// First call with explicit default — must NOT cache.
		$this->config->get_option( 'aips_nonexistent_option_xyz', 'caller-default' );

		// Now store a real value.
		$GLOBALS['aips_test_options']['aips_nonexistent_option_xyz'] = 'real-value';

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
		$GLOBALS['aips_test_options']['aips_ai_model'] = 'before-update';

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
		$GLOBALS['aips_test_options']['aips_ai_model'] = 'will-be-deleted';

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
	 * stale cache entry (e.g. a cached null/default from before the option
	 * existed).
	 */
	public function test_add_option_invalidates_cache() {
		// Key absent — reads and caches the registered default or null.
		unset( $GLOBALS['aips_test_options']['aips_ai_model'] );
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
		$GLOBALS['aips_test_options']['aips_ai_model']       = 'model-value';
		$GLOBALS['aips_test_options']['aips_enable_logging'] = true;

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
		$GLOBALS['aips_test_options']['aips_ai_model'] = 'original';

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
		$GLOBALS['aips_test_options']['aips_ai_model']       = 'model';
		$GLOBALS['aips_test_options']['aips_enable_logging'] = true;

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
		$GLOBALS['aips_test_options']['aips_ai_model'] = 'before-flush';

		$this->config->get_option( 'aips_ai_model' );
		$this->config->flush_option_cache();

		// Change the store value while cache is empty.
		$GLOBALS['aips_test_options']['aips_ai_model'] = 'after-flush';

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
	 * read and returned on subsequent reads.
	 */
	public function test_registered_default_is_cached() {
		// Remove the option so get_option() falls through to registered defaults.
		unset( $GLOBALS['aips_test_options']['aips_temperature'] );

		$first = $this->config->get_option( 'aips_temperature' );

		$this->assertTrue(
			$this->get_cache()->has( 'aips_temperature' ),
			'Registered default must be stored in the cache.'
		);
		$this->assertSame( 0.7, $first, 'Registered default value must be returned.' );

		// Mutate the store without hooks to confirm the second read is from cache.
		$GLOBALS['aips_test_options']['aips_temperature'] = 999;

		$second = $this->config->get_option( 'aips_temperature' );
		$this->assertSame(
			0.7,
			$second,
			'Second read must return the cached registered default, not the mutated store value.'
		);
	}
}
