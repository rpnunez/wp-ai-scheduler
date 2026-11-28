<?php
/**
 * Golden Test Helper and Normalizer
 *
 * Provides normalization methods to sanitize non-deterministic elements (uniqueness seeds,
 * timestamps, post IDs, history IDs, site URLs) into deterministic placeholders.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AIPS_Golden_Helper')) {

    class AIPS_Golden_Helper {

        /**
         * Normalize dynamic content and parameters for golden comparison.
         *
         * @param mixed $data Data to normalize (string, array, etc.)
         * @return mixed
         */
        public static function normalize($data) {
            if (is_string($data)) {
                return self::normalize_string($data);
            }

            if (is_array($data)) {
                $normalized = array();
                foreach ($data as $key => $value) {
                    $norm_key = is_string($key) ? self::normalize_string($key) : $key;
                    $normalized[$norm_key] = self::normalize($value);
                }
                return $normalized;
            }

            if (is_object($data)) {
                return self::normalize((array) $data);
            }

            return $data;
        }

        /**
         * Normalize a single string.
         *
         * @param string $text
         * @return string
         */
        public static function normalize_string(string $text): string {
            // Replace diversity injector uniqueness hex seed line with <SEED>
            $text = preg_replace('/Unique generation seed:\s*[a-f0-9]{8,}/i', 'Unique generation seed: <SEED>', $text);
            $text = preg_replace('/Uniqueness seed:\s*[a-f0-9]{8,}/i', 'Uniqueness seed: <SEED>', $text);

            // Replace post IDs / history IDs / term IDs if present in tags or URLs
            $text = preg_replace('/http:\/\/[a-z0-9\.:\/-]+/i', '<SITE_URL>', $text);

            // Normalize line endings to LF
            $text = str_replace(array("\r\n", "\r"), "\n", $text);

            return $text;
        }

        /**
         * Assert or update a golden snapshot file.
         *
         * @param string $golden_path Absolute path to golden file.
         * @param string $actual_content Actual content string to match.
         * @param WP_UnitTestCase $test_case Test case instance.
         */
        public static function assert_golden(string $golden_path, string $actual_content, $test_case): void {
            $update_marker = dirname(__DIR__) . '/golden/.update';
            $golden_dir = dirname($golden_path);

            if (!file_exists($golden_dir)) {
                mkdir($golden_dir, 0755, true);
            }

            if (file_exists($update_marker)) {
                file_put_contents($golden_path, $actual_content);
                $test_case->markTestIncomplete('Golden snapshot updated or created at ' . $golden_path);
                return;
            }

            if (!file_exists($golden_path)) {
                $test_case->fail('Golden file missing: ' . $golden_path . '. Create file ' . $update_marker . ' to generate.');
                return;
            }

            $expected_content = file_get_contents($golden_path);
            $test_case->assertSame($expected_content, $actual_content, 'Golden snapshot mismatch for ' . basename($golden_path));
        }
    }
}
