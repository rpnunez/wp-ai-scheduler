<?php
/**
 * Test AIPS Diagnostics Logs Tab, Controller, and Logger Methods.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Diagnostics_Logs extends WP_UnitTestCase {

	/**
	 * @var AIPS_Diagnostics_Controller
	 */
	private $controller;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @var string
	 */
	private $log_dir;

	/**
	 * @var array<string>
	 */
	private $created_test_files = array();

	/**
	 * Set up test dependencies.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->controller = new AIPS_Diagnostics_Controller();
		$this->logger     = new AIPS_Logger();

		$upload_dir    = wp_upload_dir();
		$this->log_dir = wp_normalize_path( $upload_dir['basedir'] . '/aips-logs' );

		if ( ! file_exists( $this->log_dir ) ) {
			wp_mkdir_p( $this->log_dir );
		}
	}

	/**
	 * Clean up any test files created on disk.
	 */
	public function tearDown(): void {
		foreach ( $this->created_test_files as $file_path ) {
			if ( file_exists( $file_path ) ) {
				@unlink( $file_path );
			}
		}
		$this->created_test_files = array();

		parent::tearDown();
	}

	/**
	 * Helper to create a dummy log file in the aips-logs directory.
	 *
	 * @param string $filename
	 * @param string $content
	 * @return string Full file path.
	 */
	private function create_test_log( $filename, $content = '' ) {
		$path = $this->log_dir . '/' . $filename;
		file_put_contents( $path, $content );
		$this->created_test_files[] = $path;
		return $path;
	}

	/**
	 * Test AIPS_Ajax_Registry registration for log actions.
	 */
	public function test_ajax_registry_registration() {
		$this->assertTrue( AIPS_Ajax_Registry::has_action( 'aips_get_log_files' ) );
		$this->assertSame( 'AIPS_Diagnostics_Controller', AIPS_Ajax_Registry::get_controller_for( 'aips_get_log_files' ) );

		$this->assertTrue( AIPS_Ajax_Registry::has_action( 'aips_get_log_content' ) );
		$this->assertSame( 'AIPS_Diagnostics_Controller', AIPS_Ajax_Registry::get_controller_for( 'aips_get_log_content' ) );

		$this->assertTrue( AIPS_Ajax_Registry::has_action( 'aips_clear_log' ) );
		$this->assertSame( 'AIPS_Diagnostics_Controller', AIPS_Ajax_Registry::get_controller_for( 'aips_clear_log' ) );

		$this->assertTrue( AIPS_Ajax_Registry::has_action( 'aips_download_log' ) );
		$this->assertSame( 'AIPS_Diagnostics_Controller', AIPS_Ajax_Registry::get_controller_for( 'aips_download_log' ) );
	}

	/**
	 * Test Diagnostics tab registration and availability.
	 */
	public function test_diagnostics_tab_registration() {
		$tabs = $this->controller->get_tabs();
		$this->assertArrayHasKey( 'logs', $tabs );
		$this->assertSame( 'Logs', $tabs['logs']['label'] );
		$this->assertSame( 'dashicons-media-text', $tabs['logs']['icon'] );
		$this->assertTrue( AIPS_Diagnostics_Controller::is_tab_available( 'logs' ) );
	}

	/**
	 * Test security validation on log filenames and directory traversal prevention.
	 */
	public function test_validate_log_filename_security() {
		// Non-string or empty
		$this->assertNull( $this->logger->validate_log_filename( '' ) );
		$this->assertNull( $this->logger->validate_log_filename( null ) );

		// Traversal attempts
		$this->assertNull( $this->logger->validate_log_filename( '../../wp-config.php' ) );
		$this->assertNull( $this->logger->validate_log_filename( '../index.php' ) );
		$this->assertNull( $this->logger->validate_log_filename( 'aips-../../../test.log' ) );

		// Invalid extension or format
		$this->assertNull( $this->logger->validate_log_filename( 'aips-2026-11-20.php' ) );
		$this->assertNull( $this->logger->validate_log_filename( 'secret.log' ) );
		$this->assertNull( $this->logger->validate_log_filename( 'aips-invalid space.log' ) );

		// Non-existent file
		$this->assertNull( $this->logger->validate_log_filename( 'aips-9999-99-99-nonexistent.log' ) );

		// Valid file existing inside aips-logs
		$valid_name = 'aips-2026-11-20-testsecurity12.log';
		$valid_path = $this->create_test_log( $valid_name, "test content\n" );

		$resolved = $this->logger->validate_log_filename( $valid_name );
		$this->assertNotNull( $resolved );
		$this->assertSame( wp_normalize_path( realpath( $valid_path ) ), wp_normalize_path( $resolved ) );
	}

	/**
	 * Test listing available log files with metadata.
	 */
	public function test_get_log_files() {
		$f1 = $this->create_test_log( 'aips-2026-11-19-file1.log', 'Log 1 content' );
		$f2 = $this->create_test_log( 'aips-2026-11-20-file2.log', 'Log 2 longer content test' );

		// Adjust timestamps to ensure deterministic sorting
		touch( $f1, time() - 3600 );
		touch( $f2, time() );

		$files = $this->logger->get_log_files();
		$this->assertIsArray( $files );
		$this->assertNotEmpty( $files );

		// Verify structure
		$names = array_column( $files, 'name' );
		$this->assertContains( 'aips-2026-11-19-file1.log', $names );
		$this->assertContains( 'aips-2026-11-20-file2.log', $names );

		// Verify human-friendly display_name and modified without seconds
		$this->assertArrayHasKey( 'display_name', $files[0] );
		$this->assertNotEmpty( $files[0]['display_name'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $files[0]['modified'] );

		// Newest should be sorted before older
		$pos_f1 = array_search( 'aips-2026-11-19-file1.log', $names, true );
		$pos_f2 = array_search( 'aips-2026-11-20-file2.log', $names, true );
		$this->assertLessThan( $pos_f1, $pos_f2 );
	}

	/**
	 * Test reading and parsing log content with multi-level filtering, search, and context.
	 */
	public function test_get_log_file_content_parsing_and_filters() {
		$content  = "[2026-11-20 10:00:00] [INFO] System booted successfully\n";
		$content .= "[2026-11-20 10:05:00] [WARNING] Transient cache missed\n";
		$content .= "[2026-11-20 10:10:00] [ERROR] Failed to query OpenAI API\n";
		$content .= "Context: " . json_encode( array( 'http_code' => 500, 'error' => 'server_error' ) ) . "\n";
		$content .= "[2026-11-20 10:12:00] [DEBUG] Repository cache read\n";
		$content .= "[2026-11-20 10:15:00] [INFO] Generation completed\n";

		$filename = 'aips-2026-11-20-contenttest.log';
		$this->create_test_log( $filename, $content );

		// 1. Read all entries
		$result = $this->logger->get_log_file_content( $filename, 100, 'all', '' );
		$this->assertNotNull( $result );
		$this->assertSame( 5, $result['total_lines'] );
		$this->assertCount( 5, $result['entries'] );

		// 2. Read only ERROR level
		$error_result = $this->logger->get_log_file_content( $filename, 100, 'error', '' );
		$this->assertNotNull( $error_result );
		$this->assertSame( 1, $error_result['total_lines'] );
		$this->assertSame( 'ERROR', $error_result['entries'][0]['level'] );
		$this->assertStringContainsString( 'Failed to query OpenAI API', $error_result['entries'][0]['message'] );
		$this->assertStringContainsString( '500', $error_result['entries'][0]['context'] );

		// 3. Multi-level filter: error AND debug
		$multi_result = $this->logger->get_log_file_content( $filename, 100, array( 'error', 'debug' ), '' );
		$this->assertNotNull( $multi_result );
		$this->assertSame( 2, $multi_result['total_lines'] );
		$levels_found = array_column( $multi_result['entries'], 'level' );
		$this->assertContains( 'ERROR', $levels_found );
		$this->assertContains( 'DEBUG', $levels_found );

		// 4. Multi-level filter via comma string
		$comma_result = $this->logger->get_log_file_content( $filename, 100, 'warning,debug', '' );
		$this->assertNotNull( $comma_result );
		$this->assertSame( 2, $comma_result['total_lines'] );
		$comma_levels = array_column( $comma_result['entries'], 'level' );
		$this->assertContains( 'WARNING', $comma_levels );
		$this->assertContains( 'DEBUG', $comma_levels );

		// 5. Keyword search
		$search_result = $this->logger->get_log_file_content( $filename, 100, 'all', 'Transient' );
		$this->assertNotNull( $search_result );
		$this->assertSame( 1, $search_result['total_lines'] );
		$this->assertSame( 'WARNING', $search_result['entries'][0]['level'] );

		// 6. Lines limitation
		$limit_result = $this->logger->get_log_file_content( $filename, 2, 'all', '' );
		$this->assertNotNull( $limit_result );
		$this->assertCount( 2, $limit_result['entries'] );
	}

	/**
	 * Test emptying (truncating) and deleting a log file.
	 */
	public function test_empty_and_delete_log_file() {
		$filename = 'aips-2026-11-20-clearoperations.log';
		$path     = $this->create_test_log( $filename, "Initial test content with lines\nLine 2\n" );

		$this->assertGreaterThan( 0, filesize( $path ) );

		// Empty file
		$emptied = $this->logger->empty_log_file( $filename );
		$this->assertTrue( $emptied );
		clearstatcache();
		$this->assertSame( 0, filesize( $path ) );
		$this->assertFileExists( $path );

		// Delete file
		$deleted = $this->logger->delete_log_file( $filename );
		$this->assertTrue( $deleted );
		clearstatcache();
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * Test ajax_get_log_files capability check and response.
	 */
	public function test_ajax_get_log_files_authorized() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_REQUEST       = $_POST;

		try {
			ob_start();
			$this->controller->ajax_get_log_files();
		} catch ( \WPDieException $e ) {
			// Expected JSON die
		}
		$response_json = ob_get_clean();
		$response      = json_decode( $response_json, true );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'files', $response['data'] );
	}

	/**
	 * Test ajax_get_log_content returns parsed log data.
	 */
	public function test_ajax_get_log_content_authorized() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$filename = 'aips-2026-11-20-ajaxcontent.log';
		$this->create_test_log( $filename, "[2026-11-20 12:00:00] [INFO] AJAX test success\n" );

		$_POST['nonce']  = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['file']   = $filename;
		$_POST['lines']  = 50;
		$_POST['level']  = 'all';
		$_POST['search'] = '';
		$_REQUEST        = $_POST;

		try {
			ob_start();
			$this->controller->ajax_get_log_content();
		} catch ( \WPDieException $e ) {
			// Expected JSON die
		}
		$response_json = ob_get_clean();
		$response      = json_decode( $response_json, true );

		$this->assertTrue( $response['success'] );
		$this->assertSame( $filename, $response['data']['file'] );
		$this->assertSame( 1, $response['data']['total_lines'] );

		// Test with multi-level levels parameter
		$_POST['levels'] = 'info,debug';
		$_REQUEST        = $_POST;

		try {
			ob_start();
			$this->controller->ajax_get_log_content();
		} catch ( \WPDieException $e ) {
			// Expected JSON die
		}
		$response_json2 = ob_get_clean();
		$response2      = json_decode( $response_json2, true );

		$this->assertTrue( $response2['success'] );
		$this->assertSame( 1, $response2['data']['total_lines'] );
	}

	/**
	 * Test ajax_clear_log for both empty and delete modes.
	 */
	public function test_ajax_clear_log() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$filename = 'aips-2026-11-20-ajaxclear.log';
		$path     = $this->create_test_log( $filename, "Some test lines to clear\n" );

		// Empty mode
		$_POST['nonce'] = wp_create_nonce( 'aips_ajax_nonce' );
		$_POST['file']  = $filename;
		$_POST['mode']  = 'empty';
		$_REQUEST       = $_POST;

		try {
			ob_start();
			$this->controller->ajax_clear_log();
		} catch ( \WPDieException $e ) {
			// Expected JSON die
		}
		$response_json = ob_get_clean();
		$response      = json_decode( $response_json, true );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'empty', $response['data']['mode'] );
		clearstatcache();
		$this->assertSame( 0, filesize( $path ) );

		// Delete mode
		$_POST['mode'] = 'delete';
		$_REQUEST      = $_POST;

		try {
			ob_start();
			$this->controller->ajax_clear_log();
		} catch ( \WPDieException $e ) {
			// Expected JSON die
		}
		$response_json = ob_get_clean();
		$response      = json_decode( $response_json, true );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'delete', $response['data']['mode'] );
		clearstatcache();
		$this->assertFileDoesNotExist( $path );
	}
}
