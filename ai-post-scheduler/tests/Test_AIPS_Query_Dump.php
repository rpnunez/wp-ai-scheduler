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

	/**
	 * An INF/NAN duration value makes wp_json_encode() return false (JSON
	 * cannot represent non-finite floats, and unlike malformed UTF-8 — which
	 * wp_json_encode() auto-sanitizes — there is no recovery path for this).
	 * Concatenating false with "\n" casts to an empty string, so fwrite()
	 * alone can't detect this — capture() must check wp_json_encode()'s
	 * return value explicitly and discard the partial file rather than
	 * reporting success with a corrupt/truncated .jsonl line.
	 */
	public function test_capture_returns_null_and_removes_partial_file_on_encode_failure() {
		global $wpdb;
		$saved = $wpdb->queries;
		$wpdb->queries   = array();
		$wpdb->queries[] = array( 'SELECT 1', INF, 'test_caller' );

		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'aips-logs';
		$before  = glob( $dir . '/queries-*.jsonl' ) ?: array();

		$dump = new AIPS_Query_Dump();
		$path = $dump->capture();

		$this->assertNull( $path );

		$after = glob( $dir . '/queries-*.jsonl' ) ?: array();
		$this->assertSame( $before, $after, 'No partial .jsonl file must remain after an encode failure.' );

		$wpdb->queries = $saved;
	}
}
