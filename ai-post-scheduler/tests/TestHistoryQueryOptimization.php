<?php

class TestHistoryQueryOptimization extends WP_UnitTestCase {

    public function test_get_history_selects_optimized_columns() {
        global $wpdb;

        // Mock $wpdb using PHPUnit's mock builder
        // We use stdClass because the real wpdb class might not be fully available or loaded
        $mock_wpdb = $this->getMockBuilder('stdClass')
            ->setMethods(array('prepare', 'get_results', 'get_var', 'esc_like'))
            ->getMock();

        $mock_wpdb->prefix = 'wp_';

        // We expect prepare to be called. We verify the query structure here.
        $mock_wpdb->expects($this->atLeastOnce())
            ->method('prepare')
            ->with($this->callback(function($query) {
                // We are only interested in the SELECT statement
                if (strpos($query, 'SELECT') === false) {
                    return true; // Ignore other queries (like COUNT)
                }

                // Check if it's the history query (contains table join)
                if (strpos($query, 'aips_templates') === false) {
                    return true;
                }

                // Assertions for optimization
                $selects_specific = strpos($query, 'h.id') !== false &&
                                    strpos($query, 'h.generated_title') !== false &&
                                    strpos($query, 't.name as template_name') !== false;

                $no_wildcard = strpos($query, 'h.*') === false;

                if (!$selects_specific) {
                     echo "\nQuery does not select specific columns: " . $query . "\n";
                }
                if (!$no_wildcard) {
                     echo "\nQuery uses wildcard h.*: " . $query . "\n";
                }

                return $selects_specific && $no_wildcard;
            }))
            ->will($this->returnCallback(function($query, $args) {
                return $query;
            }));

        $mock_wpdb->expects($this->any())
            ->method('get_results')
            ->willReturn(array());

        $mock_wpdb->expects($this->any())
            ->method('get_var')
            ->willReturn(0);

        // Swap global
        $original_wpdb = $wpdb;
        $wpdb = $mock_wpdb;

        try {
            $repo = new AIPS_History_Repository();
            $repo->get_history();
        } finally {
            $wpdb = $original_wpdb;
        }
    }
}
