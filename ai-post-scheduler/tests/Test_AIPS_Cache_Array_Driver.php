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
		$this->driver->set( 'ttl_key', 'live', 3600 );

		// Force the expiry timestamp into the past via reflection so we can
		// verify the expiration path without sleeping.
		$prop = new ReflectionProperty( 'AIPS_Cache_Array_Driver', 'expiries' );
		$prop->setAccessible( true );
		$expiries                  = $prop->getValue( $this->driver );
		$expiries['default:ttl_key'] = time() - 1;
		$prop->setValue( $this->driver, $expiries );

		$this->assertNull( $this->driver->get( 'ttl_key' ) );
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

	public function test_flush_does_not_purge_unrelated_wp_cache_entries() {
		$this->driver->set( 'plugin_key', 'plugin_val' );

		// Store something directly in wp_cache outside our driver's namespace.
		wp_cache_set( 'external_key', 'external_val', 'some_other_plugin' );

		$this->driver->flush();

		// Driver's own entry becomes unreachable.
		$this->assertNull( $this->driver->get( 'plugin_key' ) );

		// The unrelated WP object cache entry is untouched.
		$this->assertSame( 'external_val', wp_cache_get( 'external_key', 'some_other_plugin' ) );
	}

	public function test_flush_returns_true() {
		$this->assertTrue( $this->driver->flush() );
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

		if ( class_exists( 'AIPS_Telemetry' ) ) {
			$ref = new ReflectionProperty( 'AIPS_Telemetry', 'instance' );
			$ref->setAccessible( true );
			$ref->setValue( null, null );
		}
	}

	public function tearDown(): void {
		delete_option( 'aips_enable_telemetry' );
		AIPS_Config::get_instance()->flush_option_cache();

		if ( class_exists( 'AIPS_Telemetry' ) ) {
			$ref = new ReflectionProperty( 'AIPS_Telemetry', 'instance' );
			$ref->setAccessible( true );
			$ref->setValue( null, null );
		}

		parent::tearDown();
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

	public function test_cache_operations_record_telemetry_when_enabled() {
		if ( ! class_exists( 'AIPS_Telemetry' ) ) {
			$this->markTestSkipped( 'Telemetry class is unavailable in this limited PHPUnit environment.' );
		}

		update_option( 'aips_enable_telemetry', 1 );
		AIPS_Config::get_instance()->flush_option_cache();

		$this->cache->get( 'missing', 'example' );
		$this->cache->set( 'foo', 'bar', 60, 'example' );
		$this->cache->get( 'foo', 'example' );

		$telemetry = AIPS_Telemetry::instance();
		$ref = new ReflectionProperty( 'AIPS_Telemetry', 'events' );
		$ref->setAccessible( true );
		$events = $ref->getValue( $telemetry );

		$this->assertNotEmpty( $events );
		$this->assertSame( 'cache', $events[0]['_bucket'] );
		$this->assertSame( 'cache_get', $events[0]['type'] );
		$this->assertFalse( $events[0]['hit'] );
		$this->assertSame( 'cache_set', $events[1]['type'] );
		$this->assertSame( 'cache_get', $events[2]['type'] );
		$this->assertTrue( $events[2]['hit'] );
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

// ============================================================================
// AIPS_Cache_Db_Driver tests
// ============================================================================

/**
 * @covers AIPS_Cache_Db_Driver
 */
class Test_AIPS_Cache_Db_Driver extends WP_UnitTestCase {

/** @var AIPS_Cache_Db_Driver */
private $driver;

public function setUp(): void {
parent::setUp();
global $wpdb;
// Default: simulate a cache miss (false is recognised by the driver's
// `if (!$row)` guard; null would bypass the isset() check in the stub
// and return the default stub object, causing false positives).
$wpdb->get_row_return_val = false;
$wpdb->last_error         = '';
$this->driver = new AIPS_Cache_Db_Driver();
}

public function tearDown(): void {
global $wpdb;
// Restore stub defaults for other test classes.
$wpdb->get_row_return_val = null;
$wpdb->last_error         = '';
parent::tearDown();
}

// ------------------------------------------------------------------
// set()
// ------------------------------------------------------------------

public function test_set_with_ttl_returns_true() {
$this->assertTrue( $this->driver->set( 'key', 'value', 3600 ) );
}

public function test_set_without_ttl_returns_true() {
// TTL=0 uses the REPLACE INTO ... NULL path.
$this->assertTrue( $this->driver->set( 'key', 'value', 0 ) );
}

public function test_set_returns_false_when_db_has_error() {
// Inject a DB error; set() checks $wpdb->last_error after the query.
global $wpdb;
$wpdb->last_error = 'Simulated DB error';

$this->assertFalse( $this->driver->set( 'key', 'value' ) );

$wpdb->last_error = ''; // Reset for subsequent tests.
}

// ------------------------------------------------------------------
// get()
// ------------------------------------------------------------------

public function test_get_returns_null_on_miss() {
// get_row_return_val = false → driver returns null.
$this->assertNull( $this->driver->get( 'missing' ) );
}

public function test_get_returns_value_on_hit_no_expiry() {
global $wpdb;
$row             = new stdClass();
$row->value      = maybe_serialize( 'cached_value' );
$row->expires_at = null; // Never expires.
$wpdb->get_row_return_val = $row;

$this->assertSame( 'cached_value', $this->driver->get( 'my_key' ) );
}

public function test_get_returns_value_for_non_expired_row() {
global $wpdb;
$row             = new stdClass();
$row->value      = maybe_serialize( 42 );
$row->expires_at = gmdate( 'Y-m-d H:i:s', time() + 3600 ); // Future.
$wpdb->get_row_return_val = $row;

$this->assertSame( 42, $this->driver->get( 'live_key' ) );
}

public function test_get_returns_null_for_expired_row() {
global $wpdb;
$row             = new stdClass();
$row->value      = maybe_serialize( 'stale' );
$row->expires_at = gmdate( 'Y-m-d H:i:s', time() - 1 ); // Past.
$wpdb->get_row_return_val = $row;

$this->assertNull( $this->driver->get( 'expired_key' ) );
}

public function test_get_unserializes_array_value() {
global $wpdb;
$data            = array( 'foo' => 'bar', 'num' => 7 );
$row             = new stdClass();
$row->value      = maybe_serialize( $data );
$row->expires_at = null;
$wpdb->get_row_return_val = $row;

$this->assertSame( $data, $this->driver->get( 'arr_key' ) );
}

// ------------------------------------------------------------------
// delete() / has()
// ------------------------------------------------------------------

public function test_delete_returns_true() {
$this->assertTrue( $this->driver->delete( 'any_key' ) );
}

public function test_has_returns_false_on_miss() {
$this->assertFalse( $this->driver->has( 'nope' ) );
}

public function test_has_returns_true_on_hit() {
global $wpdb;
$row             = new stdClass();
$row->value      = maybe_serialize( 'present' );
$row->expires_at = null;
$wpdb->get_row_return_val = $row;

$this->assertTrue( $this->driver->has( 'present_key' ) );
}

// ------------------------------------------------------------------
// purge_expired()
// ------------------------------------------------------------------

public function test_purge_expired_returns_truthy() {
// The wpdb stub's query() always returns true; verify no fatal errors.
$result = $this->driver->purge_expired();
$this->assertTrue( (bool) $result );
}

// ------------------------------------------------------------------
// Key prefix / namespace
// ------------------------------------------------------------------

public function test_namespace_key_with_prefix() {
$driver = new AIPS_Cache_Db_Driver( 'myprefix' );
$method = new ReflectionMethod( 'AIPS_Cache_Db_Driver', 'namespace_key' );
$method->setAccessible( true );

$this->assertSame( 'myprefix:testkey', $method->invoke( $driver, 'testkey' ) );
}

public function test_namespace_key_without_prefix() {
$method = new ReflectionMethod( 'AIPS_Cache_Db_Driver', 'namespace_key' );
$method->setAccessible( true );

$this->assertSame( 'testkey', $method->invoke( $this->driver, 'testkey' ) );
}
}

// ============================================================================
// AIPS_Cache_Redis_Driver tests
// ============================================================================

/**
 * @covers AIPS_Cache_Redis_Driver
 *
 * These tests cover behaviour that can be exercised without a live Redis
 * server — namely disconnected no-ops and internal key-prefix formatting.
 * Tests that require the redis extension to actually be absent are skipped
 * when the extension is loaded and a server happens to be reachable.
 */
class Test_AIPS_Cache_Redis_Driver extends WP_UnitTestCase {

/** @var AIPS_Cache_Redis_Driver */
private $driver;

public function setUp(): void {
parent::setUp();
// The driver will fail to connect in environments without a Redis
// server (or the extension), making connected = false.
$this->driver = new AIPS_Cache_Redis_Driver();
}

// ------------------------------------------------------------------
// Disconnected / no-op behaviour
// ------------------------------------------------------------------

private function skip_if_connected() {
if ($this->driver->is_connected()) {
$this->markTestSkipped(
'Redis is connected; disconnected no-op tests require an unreachable server.'
);
}
}

public function test_is_not_connected_without_available_server() {
$this->skip_if_connected();
$this->assertFalse( $this->driver->is_connected() );
}

public function test_get_returns_null_when_not_connected() {
$this->skip_if_connected();
$this->assertNull( $this->driver->get( 'key' ) );
}

public function test_set_returns_false_when_not_connected() {
$this->skip_if_connected();
$this->assertFalse( $this->driver->set( 'key', 'val' ) );
}

public function test_delete_returns_false_when_not_connected() {
$this->skip_if_connected();
$this->assertFalse( $this->driver->delete( 'key' ) );
}

public function test_has_returns_false_when_not_connected() {
$this->skip_if_connected();
$this->assertFalse( $this->driver->has( 'key' ) );
}

public function test_flush_returns_false_when_not_connected() {
$this->skip_if_connected();
$this->assertFalse( $this->driver->flush() );
}

public function test_get_last_error_is_empty_when_extension_missing() {
if (extension_loaded( 'redis' )) {
$this->markTestSkipped( 'redis extension loaded.' );
}
// When extension is absent, connect() returns early without an error.
$this->assertSame( '', $this->driver->get_last_error() );
}

// ------------------------------------------------------------------
// prefix_key() — does not require a connection
// ------------------------------------------------------------------

public function test_prefix_key_with_default_prefix() {
// Default prefix is 'aips'. Format: {prefix}:{group}:{key}
$method = new ReflectionMethod( 'AIPS_Cache_Redis_Driver', 'prefix_key' );
$method->setAccessible( true );

$this->assertSame(
'aips:default:mykey',
$method->invoke( $this->driver, 'mykey', 'default' )
);
}

public function test_prefix_key_with_custom_group() {
$method = new ReflectionMethod( 'AIPS_Cache_Redis_Driver', 'prefix_key' );
$method->setAccessible( true );

$this->assertSame(
'aips:posts:post_1',
$method->invoke( $this->driver, 'post_1', 'posts' )
);
}

public function test_prefix_key_with_empty_prefix() {
$driver = new AIPS_Cache_Redis_Driver( '127.0.0.1', 6379, '', 0, '', 2.0 );
$method = new ReflectionMethod( 'AIPS_Cache_Redis_Driver', 'prefix_key' );
$method->setAccessible( true );

// No prefix: {group}:{key}
$this->assertSame(
'default:mykey',
$method->invoke( $driver, 'mykey', 'default' )
);
}

public function test_prefix_key_with_custom_prefix() {
$driver = new AIPS_Cache_Redis_Driver( '127.0.0.1', 6379, '', 0, 'myplugin', 2.0 );
$method = new ReflectionMethod( 'AIPS_Cache_Redis_Driver', 'prefix_key' );
$method->setAccessible( true );

$this->assertSame(
'myplugin:items:item_42',
$method->invoke( $driver, 'item_42', 'items' )
);
}
}
