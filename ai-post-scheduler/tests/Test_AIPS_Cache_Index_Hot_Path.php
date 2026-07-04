<?php
/**
 * @group cache
 */
class Test_AIPS_Cache_Index_Hot_Path extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( 'aips_enable_cache_system', '1' );
		update_option( 'aips_cache_monitor_enabled', '1' ); // Master switch (Task 5 defaults it off).
		update_option( 'aips_cache_monitor_index_enabled', '1' );
		AIPS_Cache::reset_system_enabled_flag();
	}

	private function count_index_rows(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index" );
	}

	public function test_array_driver_set_does_not_write_index_rows() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_cache_index" );

		$cache = new AIPS_Cache( new AIPS_Cache_Array_Driver() );
		$cache->set( 'transient_value', 'abc', 60, 'default' );

		$this->assertSame( 0, $this->count_index_rows(), 'Array-driver entries must not be indexed.' );
	}

	public function test_db_driver_set_still_writes_index_rows() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_cache_index" );

		$cache = new AIPS_Cache( new AIPS_Cache_Db_Driver() );
		$cache->set( 'persistent_value', 'abc', 60, 'default' );
		// Task 2 defers index writes to shutdown; flush explicitly.
		if ( method_exists( 'AIPS_Cache_Index', 'flush_pending' ) ) {
			AIPS_Cache_Index::flush_pending();
		}

		$this->assertGreaterThan( 0, $this->count_index_rows(), 'DB-driver entries must still be indexed.' );
	}
}
