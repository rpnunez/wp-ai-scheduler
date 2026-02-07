<?php
/**
 * Test case for History Repository Performance Optimization
 */

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = '') {
        if (is_object($args)) {
            $r = get_object_vars($args);
        } elseif (is_array($args)) {
            $r =& $args;
        } else {
            $r = array();
            // Simple string parse simulation if needed, but we mostly pass arrays
        }

        if (is_array($defaults)) {
            return array_merge($defaults, $r);
        }
        return $r;
    }
}

class Test_AIPS_History_Repository_Performance extends WP_UnitTestCase {

    private $wpdb_backup;

    public function setUp(): void {
        parent::setUp();
        // Backup existing wpdb
        if (isset($GLOBALS['wpdb'])) {
            $this->wpdb_backup = $GLOBALS['wpdb'];
        }
    }

    public function tearDown(): void {
        // Restore wpdb
        if ($this->wpdb_backup) {
            $GLOBALS['wpdb'] = $this->wpdb_backup;
        }
        parent::tearDown();
    }

    /**
     * Test that get_history selects specific columns instead of *.
     */
    public function test_get_history_selects_specific_columns() {
        // Create a mock for wpdb
        $wpdb_mock = $this->getMockBuilder('stdClass')
            ->setMethods(array('prepare', 'get_results', 'get_var', 'esc_like'))
            ->getMock();

        $wpdb_mock->prefix = 'wp_';

        // Mock prepare to return the query (simulating what happens in bootstrap but we need to capture it)
        $wpdb_mock->method('prepare')
            ->will($this->returnCallback(function($query, ...$args) {
                // Return query as is for inspection, or formatted if needed.
                // For this test, we just want to see the SQL.
                return $query;
            }));

        $wpdb_mock->method('esc_like')
            ->will($this->returnCallback(function($text) {
                return $text;
            }));

        $wpdb_mock->method('get_var')
             ->willReturn(0);

        // Expect get_results to be called with a query containing specific columns
        $wpdb_mock->expects($this->once())
            ->method('get_results')
            ->with($this->callback(function($query) {
                // Check if the query selects specific columns
                // We expect something like SELECT h.id, h.uuid, ...
                // and NOT SELECT h.*

                $has_specific_columns = strpos($query, 'SELECT h.id, h.uuid, h.post_id, h.template_id, h.status, h.generated_title, h.error_message, h.created_at, h.completed_at') !== false;
                $has_wildcard = strpos($query, 'SELECT h.*') !== false;

                // For the test to fail initially (TDD), we expect the current code to have wildcard
                // But we want to Assert that it DOES NOT have wildcard in the final state.
                // Since this is the test that defines success, I will assert what I WANT:
                // It should have specific columns and NOT have h.*

                return $has_specific_columns && !$has_wildcard;
            }))
            ->willReturn(array());

        // Assign mock to global
        $GLOBALS['wpdb'] = $wpdb_mock;

        // Instantiate repository (it captures global wpdb in constructor)
        $repo = new AIPS_History_Repository();

        // Call get_history
        $repo->get_history();
    }
}
