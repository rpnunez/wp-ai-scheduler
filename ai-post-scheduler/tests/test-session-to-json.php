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

}
