<?php
/**
 * Shared Golden Test Helper for Mirror Golden Tests
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('AIPS_Golden_Helper')) {

	/**
	 * Class AIPS_Golden_Helper
	 */
	class AIPS_Golden_Helper {

		/**
		 * Normalize dynamic elements in prompt strings or JSON blobs.
		 *
		 * @param string $content Captured text or JSON string.
		 * @return string
		 */
		public static function normalize(string $content): string {
			// Normalize uniqueness seeds (e.g., Unique generation seed: 1a2b3c4d.)
			$content = preg_replace('/Unique generation seed:\s*[a-f0-9]{8}\./i', 'Unique generation seed: <SEED>.', $content);

			// Normalize Post IDs / Template IDs / Timestamps / Hashes if present
			$content = preg_replace('/(["\']?template_id["\']?\s*:\s*)\d+/i', '$1<TEMPLATE_ID>', $content);
			$content = preg_replace('/(["\']?author_id["\']?\s*:\s*)\d+/i', '$1<AUTHOR_ID>', $content);

			return $content;
		}

		/**
		 * Assert or update a golden snapshot file.
		 *
		 * @param WP_UnitTestCase $test_case Test case instance.
		 * @param string          $golden_path Path to golden file.
		 * @param string          $actual Content to test against golden.
		 * @param string          $scenario_comment Optional description comment for header.
		 * @return void
		 */
		public static function assert_golden($test_case, string $golden_path, string $actual, string $scenario_comment = ''): void {
			$actual_normalized = self::normalize($actual);
			$marker_path = dirname(__DIR__) . '/golden/.update';
			$marker_exists = file_exists($marker_path) || file_exists(dirname($golden_path) . '/.update');

			if ($marker_exists) {
				$dir = dirname($golden_path);
				if (!is_dir($dir)) {
					mkdir($dir, 0755, true);
				}

				$content_to_write = '';
				if (!empty($scenario_comment)) {
					$content_to_write .= "# Scenario: " . $scenario_comment . "\n\n";
				}
				$content_to_write .= $actual_normalized;

				file_put_contents($golden_path, $content_to_write);
				$test_case->markTestIncomplete("Created/Updated golden snapshot: {$golden_path}");
				return;
			}

			if (!file_exists($golden_path)) {
				$test_case->fail("Golden file missing: {$golden_path}. Run with .update marker file present to generate initial golden.");
			}

			$expected_raw = file_get_contents($golden_path);
			// Strip initial comment line if present
			$expected_lines = explode("\n", $expected_raw);
			if (isset($expected_lines[0]) && str_starts_with(trim($expected_lines[0]), '#')) {
				array_shift($expected_lines);
				if (isset($expected_lines[0]) && trim($expected_lines[0]) === '') {
					array_shift($expected_lines);
				}
				$expected_normalized = implode("\n", $expected_lines);
			} else {
				$expected_normalized = $expected_raw;
			}

			// Normalize line endings for comparison
			$expected_clean = str_replace("\r\n", "\n", trim($expected_normalized));
			$actual_clean   = str_replace("\r\n", "\n", trim($actual_normalized));

			$test_case->assertEquals($expected_clean, $actual_clean, "Golden assertion failed for: {$golden_path}");
		}
	}
}
