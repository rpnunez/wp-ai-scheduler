<?php
/**
 * Tests for Session To JSON Converter error handling.
 *
 * @package AI_Post_Scheduler
 */

/**
 * Class Test_Session_To_JSON
 */
class Test_Session_To_JSON extends WP_UnitTestCase {

	/**
	 * Upload directory backup
	 */
	private $upload_dir;

	public function set_up() {
		parent::set_up();
		if (!function_exists('wp_upload_dir')) {
			$this->upload_dir = array('basedir' => sys_get_temp_dir() . '/wp-uploads');
		} else {
				$dir = wp_upload_dir();
				if (empty($dir) || !isset($dir['basedir'])) {
					$this->upload_dir = array('basedir' => sys_get_temp_dir() . '/wp-uploads');
				} else {
					$this->upload_dir = $dir;
				}
		}
	}

	public function tear_down() {
		parent::tear_down();
			if (!$this->upload_dir || !isset($this->upload_dir['basedir'])) {
				return;
			}
		$base_dir = rtrim($this->upload_dir['basedir'], '/\\') . '/aips-exports';

		// Clean up export directory if exists
		if (file_exists($base_dir)) {
			$files = glob($base_dir . '/*');
			if (is_array($files)) {
				foreach ($files as $file) {
					if (is_file($file)) {
						unlink($file);
					}
				}
			}
			rmdir($base_dir);
		}
	}

	// -----------------------------------------------------------------------
	// handle_export_cleanup() tests
	// -----------------------------------------------------------------------

	/**
	 * handle_export_cleanup() fires aips_export_cleanup_completed with correct
	 * payload when no files exist (deleted = 0, errors = 0).
	 */
	public function test_handle_export_cleanup_fires_action_with_zero_counts_when_no_files() {
		$received = null;
		add_action('aips_export_cleanup_completed', function( $payload ) use ( &$received ) {
			$received = $payload;
		});

		AIPS_Session_To_JSON::handle_export_cleanup();

		$this->assertNotNull($received, 'aips_export_cleanup_completed action was not fired.');
		$this->assertArrayHasKey('deleted', $received);
		$this->assertArrayHasKey('errors', $received);
		$this->assertSame(0, $received['deleted']);
		$this->assertSame(0, $received['errors']);
	}

	/**
	 * handle_export_cleanup() fires aips_export_cleanup_completed with a
	 * positive deleted count when old export files are present.
	 */
	public function test_handle_export_cleanup_fires_action_with_deleted_count() {
		if (!$this->upload_dir || !isset($this->upload_dir['basedir'])) {
			$this->markTestSkipped('upload_dir could not be set up properly.');
		}

		$base_dir = rtrim($this->upload_dir['basedir'], '/\\') . '/aips-exports';
		if (!file_exists($base_dir)) {
			mkdir($base_dir, 0777, true);
		}

		// Create a file that appears older than DAY_IN_SECONDS.
		$file_path = $base_dir . '/aips-session-999-20000101-000000-testtoken.json';
		file_put_contents($file_path, '{"test":true}');
		touch($file_path, time() - (DAY_IN_SECONDS + 60));

		$received = null;
		add_action('aips_export_cleanup_completed', function( $payload ) use ( &$received ) {
			$received = $payload;
		});

		AIPS_Session_To_JSON::handle_export_cleanup();

		$this->assertNotNull($received, 'aips_export_cleanup_completed action was not fired.');
		$this->assertSame(1, $received['deleted'], 'Expected one file to be reported as deleted.');
		$this->assertSame(0, $received['errors']);
	}

	/**
	 * handle_export_cleanup() fires aips_export_cleanup_completed with a
	 * positive errors count when a file cannot be deleted.
	 */
	public function test_handle_export_cleanup_fires_action_with_error_count() {
		if (!$this->upload_dir || !isset($this->upload_dir['basedir'])) {
			$this->markTestSkipped('upload_dir could not be set up properly.');
		}

		$base_dir = rtrim($this->upload_dir['basedir'], '/\\') . '/aips-exports';
		if (!file_exists($base_dir)) {
			mkdir($base_dir, 0777, true);
		}

		// Create an old file and make it unwritable so cleanup_old_exports
		// cannot delete it and records an error instead.
		$file_path = $base_dir . '/aips-session-888-20000101-000000-errtoken.json';
		file_put_contents($file_path, '{"test":true}');
		touch($file_path, time() - (DAY_IN_SECONDS + 60));
		chmod($file_path, 0444);

		$received = null;
		add_action('aips_export_cleanup_completed', function( $payload ) use ( &$received ) {
			$received = $payload;
		});

		AIPS_Session_To_JSON::handle_export_cleanup();

		// Restore permissions before assertions so tear_down can clean up.
		chmod($file_path, 0666);

		$this->assertNotNull($received, 'aips_export_cleanup_completed action was not fired.');
		$this->assertSame(0, $received['deleted']);
		$this->assertGreaterThan(0, $received['errors'], 'Expected at least one error to be reported.');
	}

	// -----------------------------------------------------------------------
	// cleanup_old_exports() tests
	// -----------------------------------------------------------------------

	/**
	 * Test cleanup_old_exports correctly handles unwritable files
	 */
	public function test_cleanup_old_exports_handles_unwritable_files() {
			if (!$this->upload_dir || !isset($this->upload_dir['basedir'])) {
				$this->markTestSkipped('upload_dir could not be setup properly.');
			}
		$base_dir = rtrim($this->upload_dir['basedir'], '/\\') . '/aips-exports';
		if (!file_exists($base_dir)) {
			mkdir($base_dir, 0777, true);
		}

		// Create a mock old file
		$file_path = $base_dir . '/aips-session-test.json';
		file_put_contents($file_path, '{"test":true}');

		// Make it old
		touch($file_path, time() - 7200);

		// Remove write permissions to simulate failure
		chmod($file_path, 0444);

		if (!function_exists('wp_upload_dir')) {
			require_once __DIR__ . '/bootstrap.php';
			// Mock wp_upload_dir in bootstrap if needed or skip.
			// actually we will need to mock wp_upload_dir to return our mock array
			$this->markTestSkipped('Cannot run this test without wp_upload_dir function mock from WordPress.');
		}

		$result = AIPS_Session_To_JSON::cleanup_old_exports(3600);

		$this->assertArrayHasKey('errors', $result);
		$this->assertNotEmpty($result['errors']);
		$this->assertStringContainsString('File is not writable', $result['errors'][0]);

		// Restore permissions so tear_down can clean it up
		chmod($file_path, 0666);
	}

	/**
	 * Test that create_htaccess_protection uses the correct directory variable
	 */
	public function test_create_htaccess_protection_uses_correct_dir_variable() {
		if (!function_exists('trailingslashit')) {
			$this->markTestSkipped('Requires full WP environment for trailingslashit()');
		}

		$converter = new AIPS_Session_To_JSON();

		if (!$this->upload_dir || !isset($this->upload_dir['basedir'])) {
			$basedir = sys_get_temp_dir() . '/wp-uploads';
		} else {
			$basedir = $this->upload_dir['basedir'];
		}

		$base_dir = rtrim($basedir, '/\\') . '/aips-exports-test-dir';
		if (!file_exists($base_dir)) {
			mkdir($base_dir, 0777, true);
		}

		// Access the private method
		$reflection = new ReflectionClass($converter);
		$method = $reflection->getMethod('create_htaccess_protection');
		$method->setAccessible(true);

		// This should not throw any PHP warnings (like undefined variable $base_dir)
		$method->invokeArgs($converter, array($base_dir));

		$this->assertFileExists($base_dir . '/.htaccess');
		$this->assertFileExists($base_dir . '/index.php');

		// Cleanup
		unlink($base_dir . '/.htaccess');
		unlink($base_dir . '/index.php');
		rmdir($base_dir);
	}

}
