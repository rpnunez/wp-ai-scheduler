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

	public function test_record_access_is_deferred_and_deduped() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_cache_index" );

		$cache = new AIPS_Cache( new AIPS_Cache_Db_Driver() );
		$cache->set( 'hot_key', 'v', 60, 'default' );
		AIPS_Cache_Index::flush_pending();

		// 3 driver SELECTs are expected; no UPDATE on the index table yet.
		$cache->get( 'hot_key', 'default' );
		$cache->get( 'hot_key', 'default' );
		$cache->get( 'hot_key', 'default' );
		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index WHERE cache_key = %s AND last_accessed_at > 0",
				'hot_key'
			) ),
			'last_accessed_at must not be written synchronously.'
		);

		AIPS_Cache_Index::flush_pending();

		$this->assertSame(
			1,
			(int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache_index WHERE cache_key = %s AND last_accessed_at > 0",
				'hot_key'
			) ),
			'flush_pending must persist one deduped access row.'
		);
	}

	public function test_record_set_does_not_count_rows_synchronously() {
		global $wpdb;
		$cache = new AIPS_Cache( new AIPS_Cache_Db_Driver() );

		$captured = array();
		add_filter( 'query', function ( $q ) use ( &$captured ) {
			$captured[] = $q;
			return $q;
		} );
		$cache->set( 'count_probe', 'v', 60, 'default' );
		$count_queries = array_filter( $captured, static function ( $q ) {
			return stripos( $q, 'COUNT(*)' ) !== false;
		} );
		$this->assertSame( array(), array_values( $count_queries ),
			'record_set must not run COUNT(*) per write.' );
	}
}
