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


class Test_AIPS_Cache extends WP_UnitTestCase {

	/** @var AIPS_Cache */
	private $cache;

	public function setUp(): void {
		parent::setUp();
		// Always test with the Array driver for isolation.
		$this->cache = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
	}

	public function tearDown(): void {
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

	public function test_remember_does_not_cache_null_results() {
		$calls = 0;
		$cb    = function() use ( &$calls ) {
			$calls++;
			return null;
		};

		$this->assertNull( $this->cache->remember( 'nullable', 60, $cb ) );
		$this->assertNull( $this->cache->remember( 'nullable', 60, $cb ) );
		$this->assertSame( 2, $calls, 'null is a miss and must be recomputed, not served as a hit.' );
	}

	public function test_remember_caches_falsy_non_null_results() {
		foreach ( array( false, 0, '' ) as $i => $falsy ) {
			$calls = 0;
			$cb    = function() use ( &$calls, $falsy ) {
				$calls++;
				return $falsy;
			};
			$this->cache->remember( "falsy_$i", 60, $cb );
			$this->assertSame( $falsy, $this->cache->remember( "falsy_$i", 60, $cb ) );
			$this->assertSame( 1, $calls );
		}
	}

	public function test_bump_tag_version_ignores_stale_l1_copy() {
		// The Array driver disables L1, so wrap it in a non-array driver.
		$backing = new AIPS_Cache_Array_Driver();
		$wrapper = new class( $backing ) implements AIPS_Cache_Driver {
			private $b;
			public function __construct( $b ) { $this->b = $b; }
			public function is_available() { return true; }
			public function get( $key, $group = 'default' ) { return $this->b->get( $key, $group ); }
			public function get_multiple( array $keys, $group = 'default' ) { return $this->b->get_multiple( $keys, $group ); }
			public function set( $key, $value, $ttl = 0, $group = 'default' ) { return $this->b->set( $key, $value, $ttl, $group ); }
			public function delete( $key, $group = 'default' ) { return $this->b->delete( $key, $group ); }
			public function flush() { return $this->b->flush(); }
			public function has( $key, $group = 'default' ) { return $this->b->has( $key, $group ); }
		};
		$cache = new AIPS_Cache( $wrapper );

		$this->assertSame( 2, $cache->bump_tag_version( 'posts' ) );
		$this->assertSame( 2, $cache->get_tag_version( 'posts' ) ); // primes L1 with v2

		// Another process bumps the tag behind this instance's back.
		$backing->set( 'tag_version:posts', 5, 0, 'default' );

		$this->assertSame( 6, $cache->bump_tag_version( 'posts' ), 'Bump must build on the stored version, not a stale L1 copy.' );

		$backing->set( 'tag_version:posts', 9, 0, 'default' );
		$cache->get_tag_versions( array( 'posts' ) ); // re-prime L1
		$backing->set( 'tag_version:posts', 12, 0, 'default' );
		$this->assertSame( array( 'posts' => 13 ), $cache->bump_tag_versions( array( 'posts' ) ) );
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

	// ------------------------------------------------------------------
	// get_driver()
	// ------------------------------------------------------------------

	public function test_get_driver_returns_driver_instance() {
		$driver = $this->cache->get_driver();
		$this->assertInstanceOf( 'AIPS_Cache_Driver', $driver );
	}

	public function test_delete_group_clears_l1_and_driver_entries() {
		$this->cache->set( 'key1', 'val1', 60, 'grp_a' );
		$this->cache->set( 'key2', 'val2', 60, 'grp_a' );
		$this->cache->set( 'key3', 'val3', 60, 'grp_b' );

		$this->assertSame( 'val1', $this->cache->get( 'key1', 'grp_a' ) );
		$this->assertSame( 'val2', $this->cache->get( 'key2', 'grp_a' ) );
		$this->assertSame( 'val3', $this->cache->get( 'key3', 'grp_b' ) );

		$result = $this->cache->delete_group( 'grp_a' );
		$this->assertTrue( $result );

		$this->assertNull( $this->cache->get( 'key1', 'grp_a' ) );
		$this->assertNull( $this->cache->get( 'key2', 'grp_a' ) );
		$this->assertSame( 'val3', $this->cache->get( 'key3', 'grp_b' ) );
	}

	public function test_delete_group_invalidates_l1_copies_on_other_instances() {
		// A non-Array driver so both instances keep L1 copies over one shared store.
		$driver = new AIPS_Cache_Wp_Object_Cache_Driver( 'aips_l1_test' );
		$reader = new AIPS_Cache( $driver );
		$writer = new AIPS_Cache( $driver );

		$reader->set( 'vec', 'old', 60, 'grp_l1' );
		$this->assertSame( 'old', $reader->get( 'vec', 'grp_l1' ) );

		$this->assertTrue( $writer->delete_group( 'grp_l1' ) );

		$this->assertNull( $reader->get( 'vec', 'grp_l1' ) );
	}

}
