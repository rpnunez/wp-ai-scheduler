<?php
/** Cache metadata must respect explicit installation timing without discovery SQL. */
class Test_AIPS_Cache_Index extends WP_UnitTestCase {

	public function test_installation_suspends_metadata_and_resumes_in_same_request() {
		global $wpdb;
		AIPS_DB_Manager::install_tables();
		update_option( 'aips_cache_monitor_index_enabled', '1' );
		$index = new AIPS_Cache_Index();
		$table = $wpdb->prefix . 'aips_cache_index';
		$queries = array();
		$capture = static function( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		};
		add_filter( 'query', $capture );
		try {
			AIPS_Cache_Index::suspend();
			try {
				$index->record_set( 'before-install', 'value', 60, 'contract' );
				$index->record_delete( 'before-install', 'contract' );
				$index->flush_buffer();
				$this->assertSame( array(), $queries );
			} finally {
				AIPS_Cache_Index::resume();
			}
			$index->record_set( 'after-install', 'value', 60, 'contract' );
			$index->flush_buffer();
			$this->assertSame( '1', $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE cache_group = 'contract' AND cache_key = 'after-install'" ) );
			foreach ( $queries as $sql ) {
				$this->assertDoesNotMatchRegularExpression( '/^\s*SHOW\s+TABLES\b/i', $sql );
			}
		} finally {
			remove_filter( 'query', $capture );
			remove_action( 'shutdown', array( $index, 'flush_buffer' ) );
			$wpdb->delete( $table, array( 'cache_group' => 'contract' ) );
		}
	}
}
