<?php
/**
 * @group diagnostics
 */
class Test_AIPS_Query_Dump extends WP_UnitTestCase {

	public function test_capture_writes_jsonl_file_when_savequeries_enabled() {
		global $wpdb;
		if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
			$wpdb->queries = array();
			// WP test suite defines SAVEQUERIES; if not, simulate the buffer.
		}
		$wpdb->queries[] = array( 'SELECT 1', 0.001, 'test_caller' );

		$dump = new AIPS_Query_Dump();
		$path = $dump->capture();

		$this->assertNotNull( $path );
		$this->assertFileExists( $path );
		$lines = array_filter( explode( "\n", (string) file_get_contents( $path ) ) );
		$last  = json_decode( end( $lines ), true );
		$this->assertSame( 'SELECT 1', $last['sql'] );
		$this->assertFileExists( dirname( $path ) . '/.htaccess' );
		unlink( $path );
	}

	public function test_capture_returns_null_when_buffer_empty() {
		global $wpdb;
		$saved = $wpdb->queries;
		$wpdb->queries = array();

		$dump = new AIPS_Query_Dump();
		$this->assertNull( $dump->capture() );

		$wpdb->queries = $saved;
	}
}
