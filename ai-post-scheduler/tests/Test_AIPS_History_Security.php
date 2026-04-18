<?php
/**
 * Test case for Security Fix: Double Prepare in AIPS_History::get_history
 *
 * To run this test, you would need a WordPress environment with PHPUnit.
 * This file serves as documentation of the test case.
 */

class Test_AIPS_History_Security extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Setup mock data if needed
    }

    /**
     * Test that get_history does not fail when status contains formatting characters.
     * This verifies the fix for the double prepare vulnerability.
     */
    public function test_get_history_with_percent_in_status() {
        $history_class = new AIPS_History();

        // Scenario 1: Status contains '%s'
        // Vulnerable code would interpret this as a placeholder in the second prepare call
        // and fail due to missing arguments.
        // Fixed code treats it as a string literal.

        $args = array(
            'status' => '%s',
            'per_page' => 10
        );

        // This should not throw an exception or return a WP_Error
        $result = $history_class->get_history($args);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);

        // Scenario 2: Status contains quotes and placeholders
        $args = array(
            'status' => "' OR 1=1 -- %s", // SQL Injection attempt + placeholder confusion
            'per_page' => 10
        );

        $result = $history_class->get_history($args);

        $this->assertIsArray($result);
    }
}
