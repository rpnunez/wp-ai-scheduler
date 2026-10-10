<?php
/**
 * Test cases for AIPS_Cache.
 *
 * Split out of Test_AIPS_Cache_Array_Driver.php: PHPUnit only runs the
 * test class named after its file, so classes sharing a file never ran.
 *
 * @package AI_Post_Scheduler
 */

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
		if ( AIPS_DEBUG_LEVEL <= 1 ) {
			// AIPS_Repository_Cache_Observer only writes log entries above debug level 1.
			$this->markTestSkipped( 'Repository cache invalidation logging requires AIPS_DEBUG_LEVEL > 1.' );
		}

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

		$telemetry = AIPS_Telemetry::instance();
		$ref = new ReflectionProperty( 'AIPS_Telemetry', 'events' );
		$ref->setAccessible( true );
		// Enabling telemetry above goes through the option cache, which records
		// its own cache events; start from an empty buffer.
		$ref->setValue( $telemetry, array() );

		$this->cache->get( 'missing', 'example' );
		$this->cache->set( 'foo', 'bar', 60, 'example' );
		$this->cache->get( 'foo', 'example' );

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
