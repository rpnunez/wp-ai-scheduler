<?php
/**
 * Tests for History Repository Performance
 */

// Load repository if needed (usually handled by bootstrap or autoloader)
if (!class_exists('AIPS_History_Repository')) {
    require_once dirname(dirname(__FILE__)) . '/includes/class-aips-history-repository.php';
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = '') {
        if (is_object($args)) {
            $r = get_object_vars($args);
        } elseif (is_array($args)) {
            $r = &$args;
        } else {
            parse_str($args, $r);
        }

        if (is_array($defaults)) {
            return array_merge($defaults, $r);
        }
        return $r;
    }
}

class Test_History_Performance extends WP_UnitTestCase {

    public function test_get_history_selects_specific_columns() {
        global $wpdb;
        $original_wpdb = $wpdb;

        $wpdb = $this->getMockBuilder('stdClass')
                     ->setMethods(['prepare', 'get_results', 'get_var', 'esc_like'])
                     ->getMock();
        $wpdb->prefix = 'wp_';

        // Mock prepare to return the query string
        $wpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            return $query;
        });

        $wpdb->method('esc_like')->willReturnArgument(0);
        $wpdb->method('get_var')->willReturn(0); // For count query

        // Expect get_results to be called with a query containing specific columns
        // This validates that we are NOT fetching huge content columns
        $wpdb->expects($this->once())
             ->method('get_results')
             ->with($this->callback(function($query) {
                 // Check if query selects specific columns instead of h.*
                 $has_specific_cols = strpos($query, 'h.id') !== false
                                   && strpos($query, 'h.generated_title') !== false;

                 $has_star = strpos($query, 'h.*') !== false;

                 if ($has_star) {
                     echo "\nQuery still uses h.*: " . $query . "\n";
                 }

                 return $has_specific_cols && !$has_star;
             }))
             ->willReturn(array());

        // Re-instantiate repo to pick up the mock wpdb
        $repo = new AIPS_History_Repository();

        $repo->get_history();

        $wpdb = $original_wpdb;
    }
}
