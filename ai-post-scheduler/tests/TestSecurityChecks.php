<?php
/**
 * Test Security Checks
 *
 * Checks if repositories are using prepared statements.
 */

class TestSecurityChecks extends PHPUnit\Framework\TestCase {

    public function test_repositories_use_prepare() {
        // Correct path relative to this file
        $includes_dir = dirname(__DIR__) . '/includes';
        $files = glob($includes_dir . '/*-repository.php');

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Skip if file doesn't use wpdb
            if (strpos($content, '$this->wpdb') === false && strpos($content, 'global $wpdb') === false) {
                continue;
            }

            if (strpos($content, '$this->wpdb->get_results') !== false || strpos($content, '$this->wpdb->query') !== false || strpos($content, '$this->wpdb->get_row') !== false) {
                 $this->assertStringContainsString('prepare', $content, "File $file should use prepare() for database queries.");
            }
        }
    }
}
