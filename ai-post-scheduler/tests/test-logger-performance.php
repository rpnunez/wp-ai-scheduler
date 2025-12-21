<?php
/**
 * Test case for Logger Performance Fix
 *
 * This test verifies that get_logs() works correctly and doesn't crash on large files.
 * Note: To truly test performance, we'd need to measure execution time on a large file.
 */

class Test_AIPS_Logger_Performance extends WP_UnitTestCase {

    private $logger;
    private $log_file;

    public function setUp() {
        parent::setUp();
        $this->logger = new AIPS_Logger();

        // Reflection to get private log_file property
        $reflection = new ReflectionClass($this->logger);
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $this->log_file = $property->getValue($this->logger);

        // Create a dummy log file
        file_put_contents($this->log_file, "");
    }

    public function tearDown() {
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
        parent::tearDown();
    }

    public function test_get_logs_returns_correct_lines() {
        // Write 10 lines
        for ($i = 1; $i <= 10; $i++) {
            $this->logger->log("Line $i");
        }

        $logs = $this->logger->get_logs(5);
        $this->assertCount(5, $logs);

        // Verify it's the last 5 lines (Line 6 to 10)
        // Note: logs might contain timestamp and other info, so we check content
        $this->assertStringContainsString("Line 10", end($logs));
        $this->assertStringContainsString("Line 6", reset($logs));
    }

    public function test_get_logs_with_small_file() {
        $this->logger->log("Line 1");
        $logs = $this->logger->get_logs(10);
        $this->assertCount(1, $logs);
    }

    // This test simulates a large file scenario logic (though we won't create a 500MB file here)
    // We mainly want to ensure the chunk reading logic is sound.
    public function test_get_logs_chunk_boundary() {
        // Write enough data to exceed typical small chunk but maybe not the 100KB limit in test.
        // But if we mock the chunk size (if we refactor to allow injecting it), we could test boundary.
        // For now, let's just write 1000 lines.
        for ($i = 1; $i <= 1000; $i++) {
            $this->logger->log("Line $i - " . str_repeat("X", 100)); // Make lines longer
        }

        $logs = $this->logger->get_logs(10);
        $this->assertCount(10, $logs);
        $this->assertStringContainsString("Line 1000", end($logs));
    }
}
