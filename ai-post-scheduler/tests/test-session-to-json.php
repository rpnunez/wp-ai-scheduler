<?php
/**
 * Test AIPS Session to JSON error handling
 */

class AIPS_Test_Session_To_JSON extends PHPUnit\Framework\TestCase {

	public function test_cleanup_old_exports_handles_unwritable_file() {
		// Mock wp_upload_dir
		$temp_dir = sys_get_temp_dir() . '/wp-uploads-mock';
		$base_dir = $temp_dir . '/aips-exports';

		if (!file_exists($base_dir)) {
			mkdir($base_dir, 0777, true);
		}


		// We mock wp_upload_dir() explicitly to return our test directory
		// We can do this because limited mode tests lack the real function or we can mock it here.
		global $wp_upload_dir_mock;
		$wp_upload_dir_mock = array(
			'basedir' => $temp_dir,
			'baseurl' => 'http://example.com/wp-content/uploads',
		);

		// Since AIPS_Session_To_JSON uses wp_upload_dir() and trailingslashit(), which in limited mode might fail, let's make sure we mock them.
		if (!function_exists('trailingslashit')) {
			function trailingslashit($string) {
				return rtrim($string, '/\\') . '/';
			}
		}

		if (!function_exists('wp_upload_dir')) {
			function wp_upload_dir() {
				global $wp_upload_dir_mock;
				return $wp_upload_dir_mock;
			}
		}

		// Use the actual path returned by wp_upload_dir()
		$upload = wp_upload_dir();
		$real_base_dir = trailingslashit($upload['basedir']) . 'aips-exports';

		if (!file_exists($real_base_dir)) {
			mkdir($real_base_dir, 0777, true);
		}

		// Ensure the directory is writable first! (it might have been restricted by previous runs)
		chmod($real_base_dir, 0777);

		// Create file in the actual mock path
		$real_file_path = trailingslashit($real_base_dir) . 'aips-session-test.json';

		if (file_exists($real_file_path)) {
			chmod($real_file_path, 0777);
			unlink($real_file_path);
		}

		file_put_contents($real_file_path, '{}');
		touch($real_file_path, time() - 7200); // 2 hours old
		chmod($real_file_path, 0444);

		$result = AIPS_Session_To_JSON::cleanup_old_exports(3600);

		$this->assertEquals(0, $result['deleted']);
		$this->assertContains('File is not writable/deletable: aips-session-test.json', $result['errors']);

		// Cleanup
		chmod($real_file_path, 0777);
		unlink($real_file_path);
	}
}
