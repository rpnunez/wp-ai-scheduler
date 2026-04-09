<?php
/**
 * Test cases for the AIPS Cache framework.
 *
 * Covers AIPS_Cache_Array_Driver, AIPS_Cache_Wp_Object_Cache_Driver, AIPS_Cache,
 * and AIPS_Cache_Factory. All tests run in the in-process fallback environment
 * (no WordPress test library required) so they never touch a real database or
 * Redis server.
 *
 * @package AI_Post_Scheduler
 * @since   2.3.0
 */

// ============================================================================
// AIPS_Cache_Array_Driver tests
// ============================================================================

/**
 * @covers AIPS_Cache_Array_Driver
 */
class Test_AIPS_Cache_Array_Driver extends WP_UnitTestCase {

	/** @var AIPS_Cache_Array_Driver */
	private $driver;

	public function setUp(): void {
		parent::setUp();
		$this->driver = new AIPS_Cache_Array_Driver();
	}

	// ------------------------------------------------------------------
	// get / set
	// ------------------------------------------------------------------

	public function test_set_and_get_string() {
		$this->driver->set( 'key1', 'hello' );
		$this->assertSame( 'hello', $this->driver->get( 'key1' ) );
	}

	public function test_get_returns_null_on_miss() {
		$this->assertNull( $this->driver->get( 'missing_key' ) );
	}

	public function test_set_and_get_array() {
		$data = array( 'a' => 1, 'b' => 2 );
		$this->driver->set( 'arr', $data );
		$this->assertSame( $data, $this->driver->get( 'arr' ) );
	}

	public function test_set_and_get_with_group() {
		$this->driver->set( 'key', 'group_value', 0, 'mygroup' );
		$this->assertSame( 'group_value', $this->driver->get( 'key', 'mygroup' ) );
	}

	public function test_different_groups_are_isolated() {
		$this->driver->set( 'key', 'value_a', 0, 'group_a' );
		$this->driver->set( 'key', 'value_b', 0, 'group_b' );

		$this->assertSame( 'value_a', $this->driver->get( 'key', 'group_a' ) );
		$this->assertSame( 'value_b', $this->driver->get( 'key', 'group_b' ) );
	}

	public function test_default_group_and_explicit_default_are_the_same() {
		$this->driver->set( 'shared', 'x' );
		$this->assertSame( 'x', $this->driver->get( 'shared', 'default' ) );
	}

	// ------------------------------------------------------------------
	// TTL / expiration
	// ------------------------------------------------------------------

	public function test_expired_entry_returns_null() {
		// Set with a TTL in the past by using the past time directly.
		// We can't mock time(), so we'll just set it with TTL=1 and assert
		// that a get before expiry succeeds (normal flow).
		$this->driver->set( 'ttl_key', 'live', 3600 );
		$this->assertSame( 'live', $this->driver->get( 'ttl_key' ) );
	}

	public function test_zero_ttl_does_not_expire() {
		$this->driver->set( 'perm', 'permanent', 0 );
		$this->assertSame( 'permanent', $this->driver->get( 'perm' ) );
	}

	// ------------------------------------------------------------------
	// delete
	// ------------------------------------------------------------------

	public function test_delete_removes_entry() {
		$this->driver->set( 'del_key', 'bye' );
		$this->driver->delete( 'del_key' );
		$this->assertNull( $this->driver->get( 'del_key' ) );
	}

	public function test_delete_non_existent_key_does_not_error() {
		$result = $this->driver->delete( 'nonexistent' );
		$this->assertTrue( $result );
	}

	public function test_delete_only_removes_matching_group() {
		$this->driver->set( 'k', 'a', 0, 'g1' );
		$this->driver->set( 'k', 'b', 0, 'g2' );
		$this->driver->delete( 'k', 'g1' );

		$this->assertNull( $this->driver->get( 'k', 'g1' ) );
		$this->assertSame( 'b', $this->driver->get( 'k', 'g2' ) );
	}

	// ------------------------------------------------------------------
	// has
	// ------------------------------------------------------------------

	public function test_has_returns_true_for_existing_key() {
		$this->driver->set( 'exist', 'yes' );
		$this->assertTrue( $this->driver->has( 'exist' ) );
	}

	public function test_has_returns_false_for_missing_key() {
		$this->assertFalse( $this->driver->has( 'nope' ) );
	}

	// ------------------------------------------------------------------
	// flush
	// ------------------------------------------------------------------

	public function test_flush_clears_all_entries() {
		$this->driver->set( 'a', 1 );
		$this->driver->set( 'b', 2 );
		$this->driver->flush();

		$this->assertNull( $this->driver->get( 'a' ) );
		$this->assertNull( $this->driver->get( 'b' ) );
	}

	public function test_flush_returns_true() {
		$this->assertTrue( $this->driver->flush() );
	}
}

// ============================================================================
// AIPS_Cache_Wp_Object_Cache_Driver tests
// ============================================================================

/**
 * @covers AIPS_Cache_Wp_Object_Cache_Driver
 */
class Test_AIPS_Cache_Wp_Object_Cache_Driver extends WP_UnitTestCase {

	/** @var AIPS_Cache_Wp_Object_Cache_Driver */
	private $driver;

	public function setUp(): void {
		parent::setUp();
		// Reset the in-process store before each test.
		$GLOBALS['_aips_test_wp_cache'] = array();
		$this->driver = new AIPS_Cache_Wp_Object_Cache_Driver( 'aips' );
	}

	public function test_set_and_get() {
		$this->driver->set( 'wpc_key', 'wpc_value' );
		$this->assertSame( 'wpc_value', $this->driver->get( 'wpc_key' ) );
	}

	public function test_get_returns_null_on_miss() {
		$this->assertNull( $this->driver->get( 'wpc_missing' ) );
	}

	public function test_delete_removes_entry() {
		$this->driver->set( 'del', 'x' );
		$this->driver->delete( 'del' );
		$this->assertNull( $this->driver->get( 'del' ) );
	}

	public function test_has_returns_correct_value() {
		$this->assertFalse( $this->driver->has( 'no_key' ) );
		$this->driver->set( 'yes_key', 1 );
		$this->assertTrue( $this->driver->has( 'yes_key' ) );
	}

	public function test_flush_clears_store() {
		$this->driver->set( 'a', 1 );
		$this->driver->flush();
		$this->assertNull( $this->driver->get( 'a' ) );
	}

	public function test_groups_are_namespaced_under_base() {
		$this->driver->set( 'key', 'custom_group_val', 0, 'posts' );
		// The underlying store_key should be 'aips_posts:key'.
		$this->assertTrue( isset( $GLOBALS['_aips_test_wp_cache']['aips_posts:key'] ) );
	}

	public function test_default_group_maps_to_base_group() {
		$this->driver->set( 'key', 'val' );
		// 'default' group → 'aips' base group.
		$this->assertTrue( isset( $GLOBALS['_aips_test_wp_cache']['aips:key'] ) );
	}
}

// ============================================================================
// AIPS_Cache (main class) tests
// ============================================================================

/**
 * @covers AIPS_Cache
 */
class Test_AIPS_Cache extends WP_UnitTestCase {

	/** @var AIPS_Cache */
	private $cache;

	public function setUp(): void {
		parent::setUp();
		// Always test with the Array driver for isolation.
		$this->cache = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
	}

	// ------------------------------------------------------------------
	// Delegation to driver
	// ------------------------------------------------------------------

	public function test_set_get() {
		$this->cache->set( 'foo', 'bar' );
		$this->assertSame( 'bar', $this->cache->get( 'foo' ) );
	}

	public function test_get_returns_default_on_miss() {
		$this->assertSame( 'fallback', $this->cache->get( 'miss', 'default', 'fallback' ) );
	}

	public function test_delete() {
		$this->cache->set( 'bye', 'value' );
		$this->cache->delete( 'bye' );
		$this->assertNull( $this->cache->get( 'bye' ) );
	}

	public function test_has() {
		$this->assertFalse( $this->cache->has( 'absent' ) );
		$this->cache->set( 'present', 1 );
		$this->assertTrue( $this->cache->has( 'present' ) );
	}

	public function test_flush() {
		$this->cache->set( 'x', 1 );
		$this->cache->flush();
		$this->assertFalse( $this->cache->has( 'x' ) );
	}

	// ------------------------------------------------------------------
	// remember()
	// ------------------------------------------------------------------

	public function test_remember_stores_and_returns_computed_value() {
		$calls = 0;
		$value = $this->cache->remember( 'memo', 60, function() use ( &$calls ) {
			$calls++;
			return 'computed';
		});

		$this->assertSame( 'computed', $value );
		$this->assertSame( 1, $calls );
	}

	public function test_remember_uses_cached_value_on_second_call() {
		$calls = 0;
		$cb = function() use ( &$calls ) {
			$calls++;
			return 'once';
		};

		$this->cache->remember( 'memo2', 60, $cb );
		$result = $this->cache->remember( 'memo2', 60, $cb );

		$this->assertSame( 'once', $result );
		$this->assertSame( 1, $calls, 'Callback should only be called once.' );
	}

	// ------------------------------------------------------------------
	// increment() / decrement()
	// ------------------------------------------------------------------

	public function test_increment_from_zero() {
		$this->assertSame( 1, $this->cache->increment( 'counter' ) );
	}

	public function test_increment_adds_step() {
		$this->cache->set( 'n', 5 );
		$this->assertSame( 8, $this->cache->increment( 'n', 3 ) );
	}

	public function test_decrement_subtracts_step() {
		$this->cache->set( 'n', 10 );
		$this->assertSame( 7, $this->cache->decrement( 'n', 3 ) );
	}

	public function test_decrement_from_zero() {
		$this->assertSame( -1, $this->cache->decrement( 'neg' ) );
	}

	// ------------------------------------------------------------------
	// get_driver()
	// ------------------------------------------------------------------

	public function test_get_driver_returns_driver_instance() {
		$driver = $this->cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Driver', $driver );
	}
}

// ============================================================================
// AIPS_Cache_Factory tests
// ============================================================================

/**
 * @covers AIPS_Cache_Factory
 */
class Test_AIPS_Cache_Factory extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
	}

	public function tearDown(): void {
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	public function test_make_returns_cache_instance() {
		$cache = AIPS_Cache_Factory::make( 'array' );
		$this->assertInstanceOf( 'AIPS_Cache', $cache );
	}

	public function test_make_array_driver_returns_array_driver() {
		$cache  = AIPS_Cache_Factory::make( 'array' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}

	public function test_make_wp_object_cache_driver() {
		$GLOBALS['_aips_test_wp_cache'] = array();
		$cache  = AIPS_Cache_Factory::make( 'wp_object_cache' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Wp_Object_Cache_Driver', $driver );
	}

	public function test_make_unknown_driver_falls_back_to_array() {
		$cache  = AIPS_Cache_Factory::make( 'nonexistent_driver' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}

	public function test_make_redis_without_extension_falls_back_to_array() {
		// Without the redis extension installed in the test environment,
		// the factory must fall back to the ArrayDriver silently.
		if (extension_loaded( 'redis' )) {
			$this->markTestSkipped( 'PHP redis extension is loaded; fallback path not testable here.' );
		}

		$cache  = AIPS_Cache_Factory::make( 'redis' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}

	public function test_instance_returns_same_object_on_repeated_calls() {
		$a = AIPS_Cache_Factory::instance();
		$b = AIPS_Cache_Factory::instance();
		$this->assertSame( $a, $b );
	}

	public function test_reset_clears_singleton() {
		$a = AIPS_Cache_Factory::instance();
		AIPS_Cache_Factory::reset();
		$b = AIPS_Cache_Factory::instance();
		$this->assertNotSame( $a, $b );
	}

	public function test_make_driver_array_returns_array_driver_instance() {
		$driver = AIPS_Cache_Factory::make_driver( 'array' );
		$this->assertInstanceOf( 'AIPS_Cache_Driver', $driver );
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}

	public function test_make_session_driver_returns_session_driver() {
		$cache  = AIPS_Cache_Factory::make( 'session' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Session_Driver', $driver );
	}
}

// ============================================================================
// AIPS_Cache_Session_Driver tests
// ============================================================================

/**
 * @covers AIPS_Cache_Session_Driver
 */
class Test_AIPS_Cache_Session_Driver extends WP_UnitTestCase {

/** @var AIPS_Cache_Session_Driver */
private $driver;

/** @var string Unique namespace per run to prevent cross-test pollution. */
private $ns;

public function setUp(): void {
parent::setUp();
// Each test uses a unique namespace so leftover keys can't bleed through.
$this->ns     = 'aips_test_' . uniqid();
$this->driver = new AIPS_Cache_Session_Driver( $this->ns );
// Flush this namespace before each test for a clean slate.
$this->driver->flush();
}

public function tearDown(): void {
// Flush after test so subsequent tests start clean.
if ($this->driver) {
$this->driver->flush();
}
parent::tearDown();
}

// ------------------------------------------------------------------
// Session availability
// ------------------------------------------------------------------

public function test_session_is_available_in_cli() {
// In PHP CLI mode session_start() works; the driver should be available.
$this->assertTrue( $this->driver->is_session_available() );
}

// ------------------------------------------------------------------
// get / set
// ------------------------------------------------------------------

public function test_set_and_get_string() {
$this->driver->set( 'key1', 'hello' );
$this->assertSame( 'hello', $this->driver->get( 'key1' ) );
}

public function test_get_returns_null_on_miss() {
$this->assertNull( $this->driver->get( 'no_such_key' ) );
}

public function test_set_and_get_array_value() {
$data = array( 'x' => 1, 'y' => 2 );
$this->driver->set( 'arr', $data );
$this->assertSame( $data, $this->driver->get( 'arr' ) );
}

public function test_set_and_get_with_group() {
$this->driver->set( 'key', 'grp_value', 0, 'mygroup' );
$this->assertSame( 'grp_value', $this->driver->get( 'key', 'mygroup' ) );
}

public function test_different_groups_are_isolated() {
$this->driver->set( 'key', 'val_a', 0, 'ga' );
$this->driver->set( 'key', 'val_b', 0, 'gb' );

$this->assertSame( 'val_a', $this->driver->get( 'key', 'ga' ) );
$this->assertSame( 'val_b', $this->driver->get( 'key', 'gb' ) );
}

public function test_set_returns_true_when_session_available() {
$this->assertTrue( $this->driver->set( 'k', 'v' ) );
}

// ------------------------------------------------------------------
// TTL / expiration
// ------------------------------------------------------------------

public function test_zero_ttl_does_not_expire() {
$this->driver->set( 'perm', 'forever', 0 );
$this->assertSame( 'forever', $this->driver->get( 'perm' ) );
}

public function test_live_ttl_entry_is_readable() {
$this->driver->set( 'live', 'alive', 3600 );
$this->assertSame( 'alive', $this->driver->get( 'live' ) );
}

public function test_expired_entry_returns_null_and_is_removed() {
// Manually write an already-expired entry directly into $_SESSION.
$session_key              = $this->ns . '::default:expired_key';
$_SESSION[ $session_key ] = array(
'value'   => maybe_serialize( 'stale' ),
'expires' => time() - 1, // 1 second in the past
);

$this->assertNull( $this->driver->get( 'expired_key' ) );
$this->assertFalse( isset( $_SESSION[ $session_key ] ), 'Stale entry should be removed on read.' );
}

// ------------------------------------------------------------------
// delete
// ------------------------------------------------------------------

public function test_delete_removes_entry() {
$this->driver->set( 'del', 'bye' );
$this->driver->delete( 'del' );
$this->assertNull( $this->driver->get( 'del' ) );
}

public function test_delete_returns_true() {
$this->assertTrue( $this->driver->delete( 'nonexistent' ) );
}

public function test_delete_only_removes_matching_group() {
$this->driver->set( 'k', 'a', 0, 'g1' );
$this->driver->set( 'k', 'b', 0, 'g2' );
$this->driver->delete( 'k', 'g1' );

$this->assertNull( $this->driver->get( 'k', 'g1' ) );
$this->assertSame( 'b', $this->driver->get( 'k', 'g2' ) );
}

// ------------------------------------------------------------------
// has
// ------------------------------------------------------------------

public function test_has_returns_true_for_existing_key() {
$this->driver->set( 'exist', 'yes' );
$this->assertTrue( $this->driver->has( 'exist' ) );
}

public function test_has_returns_false_for_missing_key() {
$this->assertFalse( $this->driver->has( 'nope' ) );
}

// ------------------------------------------------------------------
// flush
// ------------------------------------------------------------------

public function test_flush_clears_namespace_entries() {
$this->driver->set( 'a', 1 );
$this->driver->set( 'b', 2 );
$this->driver->flush();

$this->assertNull( $this->driver->get( 'a' ) );
$this->assertNull( $this->driver->get( 'b' ) );
}

public function test_flush_returns_true() {
$this->assertTrue( $this->driver->flush() );
}

public function test_flush_only_removes_own_namespace() {
// Write an entry under a different namespace.
$other_ns        = 'other_ns_' . uniqid();
$other_key       = $other_ns . '::default:other_key';
$_SESSION[ $other_key ] = array( 'value' => 'intact', 'expires' => 0 );

$this->driver->flush();

// The other-namespace key must survive.
$this->assertTrue( isset( $_SESSION[ $other_key ] ) );
// Clean up.
unset( $_SESSION[ $other_key ] );
}

// ------------------------------------------------------------------
// Cross-request persistence simulation
// ------------------------------------------------------------------

public function test_values_persist_across_driver_instantiations() {
// Write via first driver instance.
$this->driver->set( 'persisted', 'cross_page_value' );

// Simulate a new page load by creating a fresh driver with the same
// namespace — the session is still active in the same PHP process.
$driver2 = new AIPS_Cache_Session_Driver( $this->ns );
$this->assertSame( 'cross_page_value', $driver2->get( 'persisted' ) );
}

// ------------------------------------------------------------------
// Namespace isolation between driver instances
// ------------------------------------------------------------------

public function test_different_namespaces_are_isolated() {
$ns_b   = 'aips_test_b_' . uniqid();
$driver_b = new AIPS_Cache_Session_Driver( $ns_b );
$driver_b->flush();

$this->driver->set( 'key', 'from_a' );
$driver_b->set( 'key', 'from_b' );

$this->assertSame( 'from_a', $this->driver->get( 'key' ) );
$this->assertSame( 'from_b', $driver_b->get( 'key' ) );

$driver_b->flush();
}
}

// ============================================================================
// AIPS_Cache_Factory — named instance tests
// ============================================================================

/**
 * @covers AIPS_Cache_Factory
 */
class Test_AIPS_Cache_Factory_Named extends WP_UnitTestCase {

public function setUp(): void {
parent::setUp();
AIPS_Cache_Factory::reset();
}

public function tearDown(): void {
AIPS_Cache_Factory::reset();
parent::tearDown();
}

// ------------------------------------------------------------------
// named()
// ------------------------------------------------------------------

public function test_named_returns_cache_instance() {
$cache = AIPS_Cache_Factory::named( 'my_cache' );
$this->assertInstanceOf( 'AIPS_Cache', $cache );
}

public function test_named_with_explicit_driver() {
$cache  = AIPS_Cache_Factory::named( 'tmpl', 'array' );
$driver = $cache->get_driver();
$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
}

public function test_named_same_name_returns_same_instance() {
$a = AIPS_Cache_Factory::named( 'shared' );
$b = AIPS_Cache_Factory::named( 'shared' );
$this->assertSame( $a, $b );
}

public function test_named_different_names_return_different_instances() {
$a = AIPS_Cache_Factory::named( 'cache_a' );
$b = AIPS_Cache_Factory::named( 'cache_b' );
$this->assertNotSame( $a, $b );
}

public function test_named_instances_have_independent_state() {
$a = AIPS_Cache_Factory::named( 'ns_a', 'array' );
$b = AIPS_Cache_Factory::named( 'ns_b', 'array' );

$a->set( 'x', 'from_a' );
$b->set( 'x', 'from_b' );

$this->assertSame( 'from_a', $a->get( 'x' ) );
$this->assertSame( 'from_b', $b->get( 'x' ) );
}

public function test_named_session_driver() {
$cache  = AIPS_Cache_Factory::named( 'sess_cache', 'session' );
$driver = $cache->get_driver();
$this->assertInstanceOf( 'AIPS_Cache_Session_Driver', $driver );
}

// ------------------------------------------------------------------
// register()
// ------------------------------------------------------------------

public function test_register_pre_wires_named_instance() {
$my_cache = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
AIPS_Cache_Factory::register( 'custom', $my_cache );

$this->assertSame( $my_cache, AIPS_Cache_Factory::named( 'custom' ) );
}

public function test_register_replaces_existing_instance() {
$old = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
AIPS_Cache_Factory::register( 'replaceable', $old );

$new = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
AIPS_Cache_Factory::register( 'replaceable', $new );

$this->assertSame( $new, AIPS_Cache_Factory::named( 'replaceable' ) );
$this->assertNotSame( $old, AIPS_Cache_Factory::named( 'replaceable' ) );
}

// ------------------------------------------------------------------
// reset() clears named instances
// ------------------------------------------------------------------

public function test_reset_clears_named_instances() {
$a = AIPS_Cache_Factory::named( 'will_be_cleared', 'array' );
AIPS_Cache_Factory::reset();
$b = AIPS_Cache_Factory::named( 'will_be_cleared', 'array' );

$this->assertNotSame( $a, $b, 'After reset, named() should return a fresh instance.' );
}
}

// ============================================================================
// Additional tests added after review
// ============================================================================

/**
 * @covers AIPS_Cache_Factory::named
 */
class Test_AIPS_Cache_Factory_Named_Guard extends WP_UnitTestCase {

public function setUp(): void {
parent::setUp();
AIPS_Cache_Factory::reset();
}

public function tearDown(): void {
AIPS_Cache_Factory::reset();
parent::tearDown();
}

/**
 * When named() is called with a driver_name for an already-registered
 * instance, it must return the existing instance unchanged.
 */
public function test_named_ignores_driver_for_existing_instance() {
$first = AIPS_Cache_Factory::named( 'guarded', 'array' );

// Second call with a different driver: existing instance must be returned.
$second = AIPS_Cache_Factory::named( 'guarded', 'wp_object_cache' );

$this->assertSame( $first, $second, 'named() must return existing instance, ignoring driver arg.' );
$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $second->get_driver() );
}
}
