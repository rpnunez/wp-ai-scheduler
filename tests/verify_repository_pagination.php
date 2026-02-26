<?php
define('ABSPATH', __DIR__ . '/../');

// Mock wp_parse_args
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
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

// Mock wp_parse_str
if (!function_exists('wp_parse_str')) {
    function wp_parse_str($string, &$array) {
        parse_str($string, $array);
    }
}

// Mock current_time
if (!function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = 0) {
        return date('Y-m-d H:i:s');
    }
}

// Mock WPDB
class MockWPDB {
    public $prefix = 'wp_';
    public $last_query;
    public $last_params;

    public function prepare($query, $params) {
        if (!is_array($params)) {
            $params = array_slice(func_get_args(), 1);
        }
        $this->last_params = $params;

        // Basic prepare simulation
        // Note: The repository uses %d and %s, and passes args.
        // We need to simulate how WPDB::prepare replaces them roughly for the test string check.
        // But since we check the query string that was PASSED to prepare OR returned by prepare?
        // The repo calls: $this->wpdb->get_results($this->wpdb->prepare($query, $params));
        // So we need `prepare` to return the formatted string.

        $formatted = $query;
        foreach ($params as $param) {
            // Very naive replacement, just replacing first occurrence of %s or %d
             $formatted = preg_replace('/%[sd]/', "'$param'", $formatted, 1);
        }
        return $formatted;
    }

    public function get_results($query) {
        $this->last_query = $query;
        return array(); // Return empty for verification
    }

    public function get_var($query) {
        $this->last_query = $query;
        return 0;
    }

    public function esc_like($text) {
        return addcslashes($text, '_%\\');
    }
}

$GLOBALS['wpdb'] = new MockWPDB();

require_once 'ai-post-scheduler/includes/class-aips-author-topics-repository.php';

$repo = new AIPS_Author_Topics_Repository();

// Test 1: get_by_author with defaults
$repo->get_by_author(1);
$query = $GLOBALS['wpdb']->last_query;
echo "Test 1 (Defaults): " . (strpos($query, "LIMIT") === false ? "PASS" : "FAIL") . "\n";
echo "Query: $query\n";

// Test 2: get_by_author with pagination
$repo->get_by_author(1, null, array('limit' => 10, 'offset' => 20));
$query = $GLOBALS['wpdb']->last_query;
echo "Test 2 (Pagination): " . (strpos($query, "LIMIT '10' OFFSET '20'") !== false ? "PASS" : "FAIL") . "\n";
echo "Query: $query\n";

// Test 3: get_by_author with search
$repo->get_by_author(1, null, array('search' => 'test'));
$query = $GLOBALS['wpdb']->last_query;
echo "Test 3 (Search): " . (strpos($query, "topic_title LIKE '%test%'") !== false ? "PASS" : "FAIL") . "\n";
echo "Query: $query\n";

// Test 4: count_by_author
$repo->count_by_author(1, 'approved', 'test');
$query = $GLOBALS['wpdb']->last_query;
echo "Test 4 (Count): " . (strpos($query, "SELECT COUNT(*)") !== false && strpos($query, "status = 'approved'") !== false ? "PASS" : "FAIL") . "\n";
echo "Query: $query\n";
