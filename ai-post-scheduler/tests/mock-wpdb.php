<?php
/**
 * Mock WPDB Implementation
 *
 * @package AI_Post_Scheduler
 */

if (!class_exists('MockWPDB')) {
    class MockWPDB {
        public $prefix = 'wp_';
        public $insert_id = 0;
        private $data = array();

        public function esc_like($text) {
            return addcslashes($text, '_%\\');
        }

        public function prepare($query, ...$args) {
            // Simple mock prepare - just return the query with args
            // In real implementation, this would properly escape and format
            if (empty($args)) {
                return $query;
            }

            // Handle array argument (WP 3.5+)
            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }

            // Replace placeholders in order
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    // Handle array args (e.g., for IN clauses)
                    $arg = "'" . implode("','", $arg) . "'";
                    $query = preg_replace('/%[sd]/', $arg, $query, 1);
                } else {
                    $query = preg_replace('/%[sd]/', is_numeric($arg) ? $arg : "'$arg'", $query, 1);
                }
            }
            return $query;
        }

        public function get_results($query, $output = OBJECT) {
            return array();
        }

        public function get_row($query, $output = OBJECT, $y = 0) {
            // Return a default object with common properties to prevent null reference errors
            $obj = new stdClass();
            $obj->id = 1; // Default ID
            $obj->total = 0;
            $obj->completed = 0;
            $obj->failed = 0;
            $obj->processing = 0;
            $obj->count = 0;

            if ($output == ARRAY_A) {
                return (array) $obj;
            }

            return $obj;
        }

        public function get_var($query, $x = 0, $y = 0) {
            return null;
        }

        public function query($query) {
            return true;
        }

        public function insert($table, $data, $format = null) {
            static $next_insert_id = 1;
            $this->insert_id = $next_insert_id++;
            return true;
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            return true;
        }

        public function delete($table, $where, $where_format = null) {
            return true;
        }

        public function get_charset_collate() {
            return "DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci";
        }

        public function get_col($query = null, $x = 0) {
            return array();
        }
    }
}

// Ensure global $wpdb exists
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new MockWPDB();
}
