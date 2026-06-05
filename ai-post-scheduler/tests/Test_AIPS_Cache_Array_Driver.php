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
class AIPS_Test_Cache_Tag_Observer_Logger implements AIPS_Logger_Interface {
	public $entries = array();

	public function log($message, $level = 'info', $context = array()) {
		$this->entries[] = array(
			'message' => $message,
			'level'   => $level,
			'context' => $context,
		);
	}

	public function addSeparator($text) {}
}

class AIPS_Test_Cache_Index_Recorder extends AIPS_Cache_Index {
	public $set_contexts = array();
	public $accesses     = array();

	public function __construct() {}

	public function record_set( string $key, $value, int $ttl, string $group, array $context = array() ): void {
		$this->set_contexts[] = $context;
	}

	public function record_access( string $key, string $group ): void {
		$this->accesses[] = array(
			'key'   => $key,
			'group' => $group,
		);
	}
}

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

	public function test_get_tag_version_defaults_to_one_for_missing_tag() {
		$this->assertSame( 1, $this->cache->get_tag_version( 'History Entries' ) );
	}

	public function test_get_tag_versions_returns_stable_sanitized_versions() {
		$versions = $this->cache->get_tag_versions(
			array(
				'History Entries',
				'history-entries',
				'author/42',
				'',
			)
		);

		$this->assertSame(
			array(
				'history_entries' => 1,
				'history-entries' => 1,
				'author_42'       => 1,
			),
			$versions
		);
	}

	public function test_bump_tag_version_advances_missing_tag_from_default_version_one() {
		$this->assertSame( 2, $this->cache->bump_tag_version( 'History Entries' ) );
		$this->assertSame( 2, $this->cache->get_tag_version( 'history entries' ) );
	}

	public function test_bump_tag_versions_changes_tag_version_set_without_deleting_existing_cached_values() {
		$operation_id = 'history:stats';
		$args         = array( 'template_id' => 55 );
		$group        = 'aips_history';

		$initial_versions = $this->cache->get_tag_versions( array( 'history', 'template:55' ), $group );
		$initial_key      = AIPS_Repository_Cache_Key_Builder::build_key( $operation_id, $args, $initial_versions );

		$this->cache->set( $initial_key, array( 'count' => 12 ), 300, $group );
		$this->cache->bump_tag_version( 'history', $group );

		$updated_versions = $this->cache->get_tag_versions( array( 'history', 'template:55' ), $group );
		$updated_key      = AIPS_Repository_Cache_Key_Builder::build_key( $operation_id, $args, $updated_versions );

		$this->assertNotSame( $initial_versions, $updated_versions );
		$this->assertNotSame( $initial_key, $updated_key );
		$this->assertSame( array( 'count' => 12 ), $this->cache->get( $initial_key, $group ) );
		$this->assertFalse( $this->cache->has( $updated_key, $group ) );
	}

	public function test_bump_tag_version_records_repository_cache_invalidation_event() {
		$logger   = new AIPS_Test_Cache_Tag_Observer_Logger();
		$observer = new AIPS_Repository_Cache_Observer( $logger );
		$cache    = new AIPS_Cache( new AIPS_Cache_Array_Driver(), $observer );

		$version = $cache->bump_tag_version( 'History Entries', 'aips_history' );

		$this->assertSame( 2, $version );
		$this->assertCount( 1, $logger->entries );
		$this->assertSame( 'Repository cache invalidation', $logger->entries[0]['message'] );
		$this->assertSame( 'debug', $logger->entries[0]['level'] );
		$this->assertSame( 'invalidation', $logger->entries[0]['context']['event_type'] );
		$this->assertSame( 'aips_history', $logger->entries[0]['context']['cache_group'] );
		$this->assertSame( array( 'history_entries' ), $logger->entries[0]['context']['tags'] );
		$this->assertSame( 'tag_bump', $logger->entries[0]['context']['invalidation_reason'] );
	}

	public function test_get_cache_index_is_resolved_per_instance() {
		update_option( 'aips_cache_monitor_index_enabled', '1' );
		AIPS_Config::get_instance()->flush_option_cache();

		$cache_one = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
		$cache_two = new AIPS_Cache( new AIPS_Cache_Array_Driver() );

		$method = new ReflectionMethod( 'AIPS_Cache', 'get_cache_index' );
		$method->setAccessible( true );

		$first_index  = $method->invoke( $cache_one );
		$second_index = $method->invoke( $cache_two );

		$this->assertInstanceOf( 'AIPS_Cache_Index', $first_index );
		$this->assertInstanceOf( 'AIPS_Cache_Index', $second_index );
	}

	public function test_with_context_is_consumed_after_one_set() {
		$index = new AIPS_Test_Cache_Index_Recorder();
		$this->inject_cache_index( $index );

		$this->cache->with_context(
			array(
				'tags' => array( 'alpha' ),
				'tier' => 'repository',
			)
		)->set( 'context:key', 'value', 60, 'ctx' );
		$this->cache->set( 'context:key-2', 'value', 60, 'ctx' );

		$this->assertSame( array( 'tags' => array( 'alpha' ), 'tier' => 'repository' ), $index->set_contexts[0] );
		$this->assertSame( array(), $index->set_contexts[1] );
	}

	public function test_get_and_has_record_index_access_on_hits() {
		$index = new AIPS_Test_Cache_Index_Recorder();
		$this->inject_cache_index( $index );

		$this->cache->set( 'hit-key', 'present', 30, 'hit-group' );

		$this->assertSame( 'present', $this->cache->get( 'hit-key', 'hit-group' ) );
		$this->assertTrue( $this->cache->has( 'hit-key', 'hit-group' ) );

		$this->assertCount( 2, $index->accesses );
		$this->assertSame( 'hit-key', $index->accesses[0]['key'] );
		$this->assertSame( 'hit-group', $index->accesses[0]['group'] );
	}

	public function test_with_context_is_cleared_even_when_index_is_disabled() {
		update_option( 'aips_cache_monitor_index_enabled', '0' );
		AIPS_Config::get_instance()->flush_option_cache();

		$this->cache->with_context( array( 'operation_id' => 'disabled-index' ) )->set( 'disabled:key', 'value', 10, 'ctx' );

		$pending_context_property = new ReflectionProperty( 'AIPS_Cache', 'pending_context' );
		$pending_context_property->setAccessible( true );
		$this->assertSame( array(), $pending_context_property->getValue( $this->cache ) );

		update_option( 'aips_cache_monitor_index_enabled', '1' );
		AIPS_Config::get_instance()->flush_option_cache();
	}

	private function inject_cache_index( AIPS_Cache_Index $index ) {
		$property = new ReflectionProperty( 'AIPS_Cache', 'cache_index' );
		$property->setAccessible( true );
		$property->setValue( $this->cache, $index );
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
// AIPS_Cache disabled-mode tests
// ============================================================================

/**
 * @covers AIPS_Cache
 */
class Test_AIPS_Cache_Disabled extends WP_UnitTestCase {

	/** @var AIPS_Cache */
	private $cache;

	public function setUp(): void {
		parent::setUp();

		// Disable the cache system.
		update_option( 'aips_enable_cache_system', '0' );
		AIPS_Cache::reset_system_enabled_flag();

		$this->cache = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
	}

	public function tearDown(): void {
		// Re-enable the cache system and reset the static flag.
		update_option( 'aips_enable_cache_system', '1' );
		AIPS_Cache::reset_system_enabled_flag();

		AIPS_Config::get_instance()->flush_option_cache();
		AIPS_Cache_Factory::reset();

		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// get()
	// ------------------------------------------------------------------

	public function test_get_returns_default_when_disabled() {
		// Nothing is stored; should return $default directly.
		$this->assertNull( $this->cache->get( 'key' ) );
	}

	public function test_get_returns_caller_default_when_disabled() {
		$this->assertSame( 'my_default', $this->cache->get( 'key', 'default', 'my_default' ) );
	}

	// ------------------------------------------------------------------
	// set() / has()
	// ------------------------------------------------------------------

	public function test_set_returns_true_when_disabled() {
		$result = $this->cache->set( 'key', 'value' );
		$this->assertTrue( $result );
	}

	public function test_set_does_not_persist_value_when_disabled() {
		$this->cache->set( 'key', 'stored' );
		// get() must still return null — the value was never written.
		$this->assertNull( $this->cache->get( 'key' ) );
	}

	public function test_has_returns_false_when_disabled() {
		$this->cache->set( 'key', 'value' );
		$this->assertFalse( $this->cache->has( 'key' ) );
	}

	// ------------------------------------------------------------------
	// delete() / flush()
	// ------------------------------------------------------------------

	public function test_delete_returns_true_when_disabled() {
		$this->assertTrue( $this->cache->delete( 'key' ) );
	}

	public function test_flush_returns_true_when_disabled() {
		$this->assertTrue( $this->cache->flush() );
	}

	// ------------------------------------------------------------------
	// remember()
	// ------------------------------------------------------------------

	public function test_remember_always_calls_callback_when_disabled() {
		$calls = 0;
		$cb    = function() use ( &$calls ) {
			$calls++;
			return 'fresh';
		};

		$result1 = $this->cache->remember( 'key', 60, $cb );
		$result2 = $this->cache->remember( 'key', 60, $cb );

		$this->assertSame( 'fresh', $result1 );
		$this->assertSame( 'fresh', $result2 );
		// Both calls must have invoked the callback because nothing is cached.
		$this->assertSame( 2, $calls );
	}

	public function test_remember_does_not_store_value_when_disabled() {
		$this->cache->remember( 'memo', 60, function() {
			return 'computed';
		});

		// A subsequent has() call must confirm nothing was stored.
		$this->assertFalse( $this->cache->has( 'memo' ) );
	}

	// ------------------------------------------------------------------
	// increment() / decrement()
	// ------------------------------------------------------------------

	public function test_increment_returns_step_when_disabled() {
		// No prior state; should return 0 + step.
		$this->assertSame( 1, $this->cache->increment( 'counter' ) );
		$this->assertSame( 5, $this->cache->increment( 'counter', 5 ) );
	}

	public function test_decrement_returns_negative_step_when_disabled() {
		$this->assertSame( -1, $this->cache->decrement( 'counter' ) );
		$this->assertSame( -3, $this->cache->decrement( 'counter', 3 ) );
	}
}

// ============================================================================
// AIPS_Cache_Factory disabled-mode tests
// ============================================================================

/**
 * @covers AIPS_Cache_Factory
 */
class Test_AIPS_Cache_Factory_Disabled extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
		update_option( 'aips_enable_cache_system', '0' );
		AIPS_Cache::reset_system_enabled_flag();
	}

	public function tearDown(): void {
		update_option( 'aips_enable_cache_system', '1' );
		AIPS_Cache::reset_system_enabled_flag();
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	public function test_make_driver_returns_array_driver_when_cache_disabled() {
		// Even if the configured driver is something else, the factory must
		// return an ArrayDriver without attempting other driver setup.
		update_option( 'aips_cache_driver', 'wp_object_cache' );

		$driver = AIPS_Cache_Factory::make_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}

	public function test_make_driver_ignores_explicit_driver_name_when_disabled() {
		// An explicit $driver_name argument must also be ignored when disabled.
		$driver = AIPS_Cache_Factory::make_driver( 'wp_object_cache' );
		$this->assertInstanceOf( 'AIPS_Cache_Array_Driver', $driver );
	}
}


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

	public function test_make_legacy_session_driver_migrates_to_wp_object_cache_driver() {
		$cache  = AIPS_Cache_Factory::make( 'session' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Wp_Object_Cache_Driver', $driver );
	}

	public function test_make_legacy_redis_driver_migrates_to_wp_object_cache_driver() {
		$cache  = AIPS_Cache_Factory::make( 'redis' );
		$driver = $cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Wp_Object_Cache_Driver', $driver );
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

public function test_named_legacy_session_driver_maps_to_wp_object_cache_driver() {
$cache  = AIPS_Cache_Factory::named( 'sess_cache', 'session' );
$driver = $cache->get_driver();
$this->assertInstanceOf( 'AIPS_Cache_Wp_Object_Cache_Driver', $driver );
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

