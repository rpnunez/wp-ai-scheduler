<?php
/**
 * Tests for the three efficiency improvements:
 *
 * 1. AIPS_Config::get_option() in-memory caching
 * 2. AIPS_History_Repository::get_all_template_stats() transient caching
 * 3. AIPS_Logger singleton pattern
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Efficiency_Improvements extends WP_UnitTestCase {

	/**
	 * Reset the AIPS_Config singleton before each test so that the
	 * in-memory cache is always empty at test start.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->reset_config_singleton();
	}

	private function reset_config_singleton() {
		$ref       = new ReflectionClass( 'AIPS_Config' );
		$inst_prop = $ref->getProperty( 'instance' );
		$inst_prop->setAccessible( true );
		$inst_prop->setValue( null, null );
	}

	// =========================================================================
	// Improvement 1: AIPS_Config in-memory option cache
	// =========================================================================

	public function test_config_get_option_returns_existing_value() {
		update_option( 'aips_max_tokens', 1234 );
		$config = AIPS_Config::get_instance();
		$this->assertEquals( 1234, $config->get_option( 'aips_max_tokens' ) );
	}

	public function test_config_get_option_caches_in_memory() {
		update_option( 'aips_test_cache_key', 'original' );
		$config = AIPS_Config::get_instance();

		// First call \u2013 primes the cache.
		$first = $config->get_option( 'aips_test_cache_key' );
		$this->assertEquals( 'original', $first );

		// Directly change the DB row without going through set_option(),
		// simulating an external change.
		update_option( 'aips_test_cache_key', 'changed' );

		// Second call \u2013 should still return the cached value.
		$cached = $config->get_option( 'aips_test_cache_key' );
		$this->assertEquals( 'original', $cached, 'In-memory cache should serve the first-seen value within the same request.' );
	}

	public function test_config_get_option_caches_stored_false() {
		// WordPress can legitimately store boolean false.  The sentinel-based
		// detection must cache it rather than treating it as "missing".
		update_option( 'aips_test_false_val', false );
		$config = AIPS_Config::get_instance();

		$value = $config->get_option( 'aips_test_false_val' );
		$this->assertFalse( $value, 'A stored boolean false must be returned as-is.' );

		// Change it externally; cached value must persist.
		update_option( 'aips_test_false_val', 'new' );
		$this->assertFalse( $config->get_option( 'aips_test_false_val' ), 'Cached false must not be evicted by an external update.' );
	}

	public function test_config_get_option_non_existent_applies_plugin_default_first() {
		$config = AIPS_Config::get_instance();
		delete_option( 'aips_max_tokens' );

		// aips_max_tokens has a plugin default of 2000; caller default should be ignored.
		$this->assertEquals( 2000, $config->get_option( 'aips_max_tokens', 999 ) );
	}

	public function test_config_get_option_falls_back_to_caller_default_when_no_plugin_default() {
		$config = AIPS_Config::get_instance();
		delete_option( 'aips_nonexistent_xyz' );

		// No plugin default; must use caller's fallback.
		$this->assertEquals( 'fallback', $config->get_option( 'aips_nonexistent_xyz', 'fallback' ) );
	}

	public function test_config_get_option_non_existent_returns_null_when_no_defaults() {
		$config = AIPS_Config::get_instance();
		delete_option( 'aips_nonexistent_xyz' );

		// No plugin default, no caller default.
		$this->assertNull( $config->get_option( 'aips_nonexistent_xyz' ) );

		// Second call with an explicit default \u2014 must NOT be masked by a cached null.
		$this->assertEquals( 'fallback', $config->get_option( 'aips_nonexistent_xyz', 'fallback' ) );
	}

	public function test_config_set_option_updates_cache_unconditionally() {
		update_option( 'aips_test_set_key', 'before' );
		$config = AIPS_Config::get_instance();

		// Prime the cache.
		$config->get_option( 'aips_test_set_key' );

		// Update via set_option() \u2013 cache must be refreshed.
		$config->set_option( 'aips_test_set_key', 'after' );
		$this->assertEquals( 'after', $config->get_option( 'aips_test_set_key' ), 'set_option() must update the in-memory cache.' );
	}

	public function test_config_set_option_updates_cache_even_when_value_unchanged() {
		update_option( 'aips_test_noop_key', 'same' );
		$config = AIPS_Config::get_instance();

		// set_option() with same value \u2014 update_option() returns false (no-op).
		// The cache must still be populated so subsequent reads don't hit the DB.
		$config->set_option( 'aips_test_noop_key', 'same' );
		$this->assertEquals( 'same', $config->get_option( 'aips_test_noop_key' ) );
	}

	// =========================================================================
	// Improvement 2: AIPS_History_Repository::get_all_template_stats() caching
	// =========================================================================

	public function test_get_all_template_stats_is_cached() {
		$repo = new AIPS_History_Repository();

		// Ensure the transient is cleared.
		delete_transient( 'aips_all_template_stats' );

		// First call \u2013 populates the transient.
		$repo->get_all_template_stats();

		// The transient should now exist.
		$this->assertNotFalse(
			get_transient( 'aips_all_template_stats' ),
			'get_all_template_stats() should populate the aips_all_template_stats transient.'
		);
	}

	public function test_get_all_template_stats_cache_invalidated_on_create() {
		$repo = new AIPS_History_Repository();

		set_transient( 'aips_all_template_stats', array( 'fake' => 99 ), HOUR_IN_SECONDS );

		$repo->create( array(
			'template_id' => 1,
			'status'      => 'completed',
		) );

		$this->assertFalse(
			get_transient( 'aips_all_template_stats' ),
			'create() should invalidate the aips_all_template_stats transient.'
		);
	}

	public function test_get_all_template_stats_cache_invalidated_on_update() {
		global $wpdb;
		$repo = new AIPS_History_Repository();

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'aips_history',
			array(
				'template_id'     => 1,
				'status'          => 'processing',
				'generated_title' => '',
			),
			array( '%d', '%s', '%s' )
		);
		$id = $inserted ? $wpdb->insert_id : null;

		if ( ! $id ) {
			$this->markTestSkipped( 'Could not insert a history row for update test.' );
		}

		set_transient( 'aips_all_template_stats', array( 'fake' => 7 ), HOUR_IN_SECONDS );

		$repo->update( $id, array( 'status' => 'completed' ) );

		$this->assertFalse(
			get_transient( 'aips_all_template_stats' ),
			'update() should invalidate the aips_all_template_stats transient.'
		);
	}

	public function test_get_all_template_stats_cache_invalidated_on_delete() {
		global $wpdb;
		$repo = new AIPS_History_Repository();

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'aips_history',
			array(
				'template_id'     => 1,
				'status'          => 'completed',
				'generated_title' => '',
			),
			array( '%d', '%s', '%s' )
		);
		$id = $inserted ? $wpdb->insert_id : null;

		if ( ! $id ) {
			$this->markTestSkipped( 'Could not insert a history row for delete test.' );
		}

		set_transient( 'aips_all_template_stats', array( 'fake' => 3 ), HOUR_IN_SECONDS );

		$repo->delete( $id );

		$this->assertFalse(
			get_transient( 'aips_all_template_stats' ),
			'delete() should invalidate the aips_all_template_stats transient.'
		);
	}

	public function test_get_all_template_stats_cache_invalidated_on_delete_bulk() {
		global $wpdb;
		$repo = new AIPS_History_Repository();

		$ids = array();
		for ( $i = 0; $i < 2; $i++ ) {
			$wpdb->insert(
				$wpdb->prefix . 'aips_history',
				array(
					'template_id'     => 1,
					'status'          => 'completed',
					'generated_title' => '',
				),
				array( '%d', '%s', '%s' )
			);
			$ids[] = $wpdb->insert_id;
		}

		if ( empty( array_filter( $ids ) ) ) {
			$this->markTestSkipped( 'Could not insert history rows for delete_bulk test.' );
		}

		set_transient( 'aips_all_template_stats', array( 'fake' => 5 ), HOUR_IN_SECONDS );

		$repo->delete_bulk( $ids );

		$this->assertFalse(
			get_transient( 'aips_all_template_stats' ),
			'delete_bulk() should invalidate the aips_all_template_stats transient.'
		);
	}

	public function test_get_all_template_stats_cache_invalidated_on_delete_by_status() {
		$repo = new AIPS_History_Repository();

		set_transient( 'aips_all_template_stats', array( 'fake' => 1 ), HOUR_IN_SECONDS );

		$repo->delete_by_status( 'completed' );

		$this->assertFalse(
			get_transient( 'aips_all_template_stats' ),
			'delete_by_status() should invalidate the aips_all_template_stats transient.'
		);
	}

	public function test_get_all_template_stats_cache_invalidated_on_clear_history() {
		$repo = new AIPS_History_Repository();

		// Insert at least one record so clear_history() has something to delete.
		$repo->create( array(
			'template_id' => 1,
			'status'      => 'completed',
		) );

		set_transient( 'aips_all_template_stats', array( 'fake' => 2 ), HOUR_IN_SECONDS );

		$repo->clear_history( array( 'status' => 'all' ) );

		$this->assertFalse(
			get_transient( 'aips_all_template_stats' ),
			'clear_history() should invalidate the aips_all_template_stats transient.'
		);
	}

	// =========================================================================
	// Improvement 3: AIPS_Logger singleton
	// =========================================================================

	public function test_logger_get_instance_returns_same_object() {
		$a = AIPS_Logger::get_instance();
		$b = AIPS_Logger::get_instance();
		$this->assertSame( $a, $b, 'AIPS_Logger::get_instance() must always return the same instance.' );
	}

	public function test_logger_can_still_be_instantiated_directly() {
		// Direct instantiation should still work (backward-compat for tests and
		// callers that inject a custom logger).
		$logger = new AIPS_Logger();
		$this->assertInstanceOf( 'AIPS_Logger', $logger );
	}

	public function test_logger_singleton_and_direct_instance_are_different_objects() {
		$singleton = AIPS_Logger::get_instance();
		$direct    = new AIPS_Logger();
		$this->assertNotSame( $singleton, $direct );
		$this->assertInstanceOf( 'AIPS_Logger', $direct );
	}
}
