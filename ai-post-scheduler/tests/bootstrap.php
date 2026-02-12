<?php
/**
 * PHPUnit bootstrap file
 *
 * Initializes WordPress test environment and loads plugin files.
 *
 * @package AI_Post_Scheduler
 */

// Composer autoloader
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Load the Yoast PHPUnit Polyfills autoloader
if (file_exists(dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
}

// Define WordPress test environment constants
if (!defined('WP_TESTS_DIR')) {
    define('WP_TESTS_DIR', getenv('WP_TESTS_DIR') ? getenv('WP_TESTS_DIR') : '/tmp/wordpress-tests-lib');
}

// Define WordPress core directory
if (!defined('WP_CORE_DIR')) {
    define('WP_CORE_DIR', getenv('WP_CORE_DIR') ? getenv('WP_CORE_DIR') : '/tmp/wordpress');
}

// Check if WordPress test library exists
if (file_exists(WP_TESTS_DIR . '/includes/functions.php')) {
    require_once WP_TESTS_DIR . '/includes/functions.php';

    /**
     * Manually load the plugin being tested.
     */
    function _manually_load_plugin() {
        define('ABSPATH', WP_CORE_DIR . '/');

        // Load plugin files
        require dirname(__DIR__) . '/ai-post-scheduler.php';
    }
    tests_add_filter('muplugins_loaded', '_manually_load_plugin');

    // Start up the WP testing environment
    require WP_TESTS_DIR . '/includes/bootstrap.php';
} else {
    // Fallback when WordPress test library is not available
    echo "Warning: WordPress test library not found at " . WP_TESTS_DIR . "\n";
    echo "Tests will run in limited mode without WordPress environment.\n\n";

    // Define minimal WordPress constants and functions for basic testing
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__) . '/');
    }

    if (!defined('AIPS_VERSION')) {
        define('AIPS_VERSION', '1.4.0');
    }

    if (!defined('AIPS_PLUGIN_DIR')) {
        define('AIPS_PLUGIN_DIR', dirname(__DIR__) . '/');
    }

    if (!defined('AIPS_PLUGIN_URL')) {
        define('AIPS_PLUGIN_URL', 'http://example.com/wp-content/plugins/ai-post-scheduler/');
    }

    if (!defined('AIPS_PLUGIN_BASENAME')) {
        define('AIPS_PLUGIN_BASENAME', 'ai-post-scheduler/ai-post-scheduler.php');
    }

    if (!isset($GLOBALS['aips_test_hooks'])) {
        $GLOBALS['aips_test_hooks'] = array(
            'actions' => array(),
            'filters' => array(),
        );
    }

    // WordPress constants
    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }

    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    if (!defined('ARRAY_N')) {
        define('ARRAY_N', 'ARRAY_N');
    }

    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }

    // Mock WordPress functions if not available
    if (!function_exists('esc_html__')) {
        function esc_html__($text, $domain = 'default') {
            return $text;
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html($text) {
            return $text;
        }
    }

    if (!function_exists('esc_html_e')) {
        function esc_html_e($text, $domain = 'default') {
            echo $text;
        }
    }

    if (!function_exists('esc_attr')) {
        function esc_attr($text) {
            return $text;
        }
    }

    if (!function_exists('esc_attr_e')) {
        function esc_attr_e($text, $domain = 'default') {
            echo $text;
        }
    }

    if (!function_exists('esc_url')) {
        function esc_url($url) {
            return $url;
        }
    }

    if (!function_exists('plugin_dir_path')) {
        function plugin_dir_path($file) {
            return dirname($file) . '/';
        }
    }

    if (!function_exists('plugin_dir_url')) {
        function plugin_dir_url($file) {
            return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
        }
    }

    if (!function_exists('plugin_basename')) {
        function plugin_basename($file) {
            return basename(dirname($file)) . '/' . basename($file);
        }
    }

    if (!function_exists('add_action')) {
        function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
            if (!isset($GLOBALS['aips_test_hooks']['actions'][$hook])) {
                $GLOBALS['aips_test_hooks']['actions'][$hook] = array();
            }

            if (!isset($GLOBALS['aips_test_hooks']['actions'][$hook][$priority])) {
                $GLOBALS['aips_test_hooks']['actions'][$hook][$priority] = array();
            }

            $GLOBALS['aips_test_hooks']['actions'][$hook][$priority][] = array(
                'callback' => $callback,
                'accepted_args' => $accepted_args,
            );
        }
    }

    if (!function_exists('add_filter')) {
        function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
            if (!isset($GLOBALS['aips_test_hooks']['filters'][$hook])) {
                $GLOBALS['aips_test_hooks']['filters'][$hook] = array();
            }

            if (!isset($GLOBALS['aips_test_hooks']['filters'][$hook][$priority])) {
                $GLOBALS['aips_test_hooks']['filters'][$hook][$priority] = array();
            }

            $GLOBALS['aips_test_hooks']['filters'][$hook][$priority][] = array(
                'callback' => $callback,
                'accepted_args' => $accepted_args,
            );
        }
    }

    if (!function_exists('apply_filters')) {
        function apply_filters($hook, $value) {
            $args = func_get_args();
            $value = $args[1];

            if (isset($GLOBALS['aips_test_hooks']['filters'][$hook])) {
                ksort($GLOBALS['aips_test_hooks']['filters'][$hook]);

                foreach ($GLOBALS['aips_test_hooks']['filters'][$hook] as $priority_callbacks) {
                    foreach ($priority_callbacks as $callback) {
                        $callback_args = array_slice($args, 1, $callback['accepted_args']);
                        $callback_args[0] = $value;
                        $value = call_user_func_array($callback['callback'], $callback_args);
                    }
                }
            }

            return $value;
        }
    }

    if (!function_exists('do_action')) {
        function do_action($hook) {
            $args = func_get_args();
            array_shift($args);

            if (isset($GLOBALS['aips_test_hooks']['actions'][$hook])) {
                ksort($GLOBALS['aips_test_hooks']['actions'][$hook]);

                foreach ($GLOBALS['aips_test_hooks']['actions'][$hook] as $priority_callbacks) {
                    foreach ($priority_callbacks as $callback) {
                        $callback_args = array_slice($args, 0, $callback['accepted_args']);
                        call_user_func_array($callback['callback'], $callback_args);
                    }
                }
            }
        }
    }

    if (!function_exists('remove_all_filters')) {
        function remove_all_filters($hook_name, $priority = false) {
            if (isset($GLOBALS['aips_test_hooks']['filters'][$hook_name])) {
                if ($priority === false) {
                    unset($GLOBALS['aips_test_hooks']['filters'][$hook_name]);
                } elseif (isset($GLOBALS['aips_test_hooks']['filters'][$hook_name][$priority])) {
                    unset($GLOBALS['aips_test_hooks']['filters'][$hook_name][$priority]);
                }
            }
            return true;
        }
    }

    if (!isset($GLOBALS['aips_test_options'])) {
        $GLOBALS['aips_test_options'] = array();
    }

    if (!function_exists('get_option')) {
        function get_option($option, $default = false) {
            return isset($GLOBALS['aips_test_options'][$option]) ? $GLOBALS['aips_test_options'][$option] : $default;
        }
    }

    if (!function_exists('add_option')) {
        function add_option($option, $value) {
            $GLOBALS['aips_test_options'][$option] = $value;
            return true;
        }
    }

    if (!function_exists('update_option')) {
        function update_option($option, $value) {
            $GLOBALS['aips_test_options'][$option] = $value;
            return true;
        }
    }

    if (!function_exists('delete_option')) {
        function delete_option($option) {
            if (isset($GLOBALS['aips_test_options'][$option])) {
                unset($GLOBALS['aips_test_options'][$option]);
            }
            return true;
        }
    }

    if (!function_exists('set_transient')) {
        function set_transient($transient, $value, $expiration = 0) {
            return update_option('_transient_' . $transient, $value);
        }
    }

    if (!function_exists('get_transient')) {
        function get_transient($transient) {
            return get_option('_transient_' . $transient);
        }
    }

    if (!function_exists('delete_transient')) {
        function delete_transient($transient) {
            return delete_option('_transient_' . $transient);
        }
    }

    if (!function_exists('current_time')) {
        function current_time($type = 'mysql', $gmt = 0) {
            $timestamp = $gmt ? time() : time(); // Simplified time handling

            if ($type === 'timestamp') {
                return $timestamp;
            }

            if ($type === 'mysql') {
                return date('Y-m-d H:i:s', $timestamp);
            }

            // Assume $type is a format string if not mysql/timestamp
            return date($type, $timestamp);
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data, $options = 0, $depth = 512) {
            return json_encode($data, $options, $depth);
        }
    }

    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir() {
            return [
                'path' => '/tmp/uploads',
                'url' => 'http://example.com/wp-content/uploads',
                'subdir' => '',
                'basedir' => '/tmp/uploads',
                'baseurl' => 'http://example.com/wp-content/uploads',
                'error' => false,
            ];
        }
    }

    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p($target) {
            if (!file_exists($target)) {
                return mkdir($target, 0755, true);
            }
            return true;
        }
    }

    if (!class_exists('WP_Error')) {
        class WP_Error {
            private $errors = [];
            private $error_data = [];

            public function __construct($code = '', $message = '', $data = '') {
                if (empty($code)) {
                    return;
                }
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }

            public function get_error_code() {
                $codes = array_keys($this->errors);
                return empty($codes) ? '' : $codes[0];
            }

            public function get_error_message($code = '') {
                if (empty($code)) {
                    $code = $this->get_error_code();
                }
                $messages = isset($this->errors[$code]) ? $this->errors[$code] : [];
                return empty($messages) ? '' : $messages[0];
            }

            public function get_error_data($code = '') {
                if (empty($code)) {
                    $code = $this->get_error_code();
                }
                return isset($this->error_data[$code]) ? $this->error_data[$code] : null;
            }

            public function add($code, $message, $data = '') {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
    }

    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing) {
            return ($thing instanceof WP_Error);
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) {
            $filtered = is_string($str) ? strip_tags($str) : $str;
            return is_string($filtered) ? trim($filtered) : $filtered;
        }
    }

    if (!function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags($string) {
            return trim(strip_tags($string));
        }
    }

    if (!function_exists('absint')) {
        function absint($maybeint) {
            return abs(intval($maybeint));
        }
    }

    if (!function_exists('get_bloginfo')) {
        function get_bloginfo($show = '', $filter = 'raw') {
            switch ($show) {
                case 'name':
                    return 'Test Site';
                case 'description':
                    return 'A test site for unit testing';
                case 'url':
                case 'wpurl':
                case 'home':
                    return 'http://example.com';
                default:
                    return '';
            }
        }
    }

    if (!function_exists('get_current_user_id')) {
        function get_current_user_id() {
            return 1;
        }
    }

    if (!function_exists('wp_insert_post')) {
        function wp_insert_post($postarr, $wp_error = false) {
            static $post_id = 1;
            return $post_id++;
        }
    }

    if (!function_exists('get_post')) {
        function get_post($post_id = null) {
            $post = new stdClass();
            $post->ID = $post_id ? $post_id : 1;
            $post->post_title = 'Test Post';
            $post->post_status = 'draft';
            $post->post_type = 'post';
            return $post;
        }
    }

    if (!function_exists('get_edit_post_link')) {
        function get_edit_post_link($id = 0, $context = 'display') {
            return 'http://example.com/wp-admin/post.php?post=' . $id . '&action=edit';
        }
    }

    if (!function_exists('get_permalink')) {
        function get_permalink($post = 0, $leavename = false) {
            return 'http://example.com/?p=' . $post;
        }
    }

    if (!function_exists('admin_url')) {
        function admin_url($path = '', $scheme = 'admin') {
            return 'http://example.com/wp-admin/' . $path;
        }
    }

    if (!function_exists('date_i18n')) {
        function date_i18n($format, $timestamp_with_offset = false, $gmt = false) {
            $timestamp = $timestamp_with_offset === false ? time() : $timestamp_with_offset;
            return date($format, $timestamp);
        }
    }

    if (!function_exists('selected')) {
        function selected($selected, $current = true, $echo = true) {
            $result = (string) $selected === (string) $current ? " selected='selected'" : '';
            if ($echo) {
                echo $result;
            }
            return $result;
        }
    }

    if (!function_exists('wp_set_post_tags')) {
        function wp_set_post_tags($post_id, $tags) {
            return true;
        }
    }

    if (!function_exists('set_post_thumbnail')) {
        function set_post_thumbnail($post_id, $attachment_id) {
            return true;
        }
    }

    if (!function_exists('update_post_meta')) {
        function update_post_meta($post_id, $meta_key, $meta_value) {
            global $aips_test_meta;

            if (!isset($aips_test_meta)) {
                $aips_test_meta = array();
            }

            if (!isset($aips_test_meta[$post_id])) {
                $aips_test_meta[$post_id] = array();
            }

            $aips_test_meta[$post_id][$meta_key] = $meta_value;
            return true;
        }
    }

    if (!class_exists('WP_UnitTestCase')) {
        // Provide a basic test case for when WordPress test library is not available
        class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {
            protected $factory;

            public function setUp(): void {
                parent::setUp();
                if (!isset($this->factory)) {
                    $this->factory = new stdClass();
                    $this->factory->user = new class {
                        public function create($args = array()) {
                            static $user_id = 1;
                            $id = $user_id++;
                            // Store user role in global
                            global $test_users;
                            if (!isset($test_users)) {
                                $test_users = array();
                            }
                            $test_users[$id] = isset($args['role']) ? $args['role'] : 'subscriber';
                            return $id;
                        }
                    };
                }
            }

            public function tearDown(): void {
                $this->reset_hooks();
                parent::tearDown();
            }

            public function assertWPError($actual, $message = '') {
                $this->assertTrue(is_wp_error($actual), $message);
            }

            /**
             * Reset mocked WordPress hooks to avoid cross-test pollution.
             *
             * @return void
             */
            private function reset_hooks() {
                $GLOBALS['aips_test_hooks'] = array(
                    'actions' => array(),
                    'filters' => array(),
                );
            }
        }
    }

    // Mock AJAX functions
    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer($action = -1, $query_arg = '_wpnonce', $die = true) {
            $nonce = isset($_REQUEST[$query_arg]) ? $_REQUEST[$query_arg] : '';
            if ($nonce !== wp_create_nonce($action)) {
                if ($die) {
                    throw new WPAjaxDieStopException();
                }
                return false;
            }
            return 1;
        }
    }

    if (!function_exists('wp_die')) {
        function wp_die($message = '', $title = '', $args = array()) {
            throw new WPAjaxDieStopException($message);
        }
    }

    if (!function_exists('wp_create_nonce')) {
        function wp_create_nonce($action = -1) {
            return 'test_nonce_' . $action;
        }
    }

    if (!function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($nonce, $action = -1) {
            return $nonce === wp_create_nonce($action) ? 1 : false;
        }
    }

    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success($data = null, $status_code = null) {
            echo json_encode(array('success' => true, 'data' => $data));
            throw new WPAjaxDieContinueException();
        }
    }

    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data = null, $status_code = null) {
            echo json_encode(array('success' => false, 'data' => $data));
            throw new WPAjaxDieContinueException();
        }
    }

    if (!function_exists('wp_set_current_user')) {
        function wp_set_current_user($id, $name = '') {
            global $current_user_id;
            $current_user_id = $id;
            return $id;
        }
    }

    if (!function_exists('current_user_can')) {
        function current_user_can($capability) {
            global $current_user_id, $test_users;
            if (!isset($current_user_id) || !isset($test_users[$current_user_id])) {
                return false;
            }
            // Check if user has admin role
            $role = $test_users[$current_user_id];
            if ($role === 'administrator' && $capability === 'manage_options') {
                return true;
            }
            return false;
        }
    }

    if (!function_exists('sanitize_file_name')) {
        function sanitize_file_name($filename) {
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            return $filename;
        }
    }

    if (!function_exists('sanitize_textarea_field')) {
        function sanitize_textarea_field($str) {
            return strip_tags($str);
        }
    }

    if (!function_exists('wp_kses_post')) {
        function wp_kses_post($data) {
            // Allow some HTML tags
            return strip_tags($data, '<a><strong><em><p><br><ul><ol><li>');
        }
    }

    if (!function_exists('__')) {
        function __($text, $domain = 'default') {
            return $text;
        }
    }

    if (!function_exists('wp_parse_str')) {
        function wp_parse_str($string, &$array) {
            parse_str($string, $array);
        }
    }

    if (!function_exists('wp_parse_args')) {
        function wp_parse_args($args, $defaults = array()) {
            if (is_object($args)) {
                $r = get_object_vars($args);
            } elseif (is_array($args)) {
                $r = &$args;
            } else {
                wp_parse_str($args, $r);
            }

            if (is_array($defaults)) {
                return array_merge($defaults, $r);
            }
            return $r;
        }
    }

    if (!function_exists('add_query_arg')) {
        function add_query_arg() {
            $args = func_get_args();

            // Determine URI and new parameters based on arguments.
            if (is_array($args[0])) {
                // Signature: add_query_arg( array $params, $uri = $_SERVER['REQUEST_URI'] )
                $new_params = $args[0];
                if (count($args) < 2 || false === $args[1]) {
                    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                } else {
                    $uri = $args[1];
                }
            } else {
                // Signature: add_query_arg( $key, $value, $uri = $_SERVER['REQUEST_URI'] )
                $key   = $args[0];
                $value = isset($args[1]) ? $args[1] : null;
                $new_params = array( $key => $value );

                if (count($args) < 3 || false === $args[2]) {
                    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                } else {
                    $uri = $args[2];
                }
            }

            // Separate fragment from URI, if any.
            $fragment = '';
            if (false !== ($hash_pos = strpos($uri, '#'))) {
                $fragment = substr($uri, $hash_pos);
                $uri      = substr($uri, 0, $hash_pos);
            }

            // Separate base and existing query string.
            $base  = $uri;
            $query = '';
            if (false !== ($q_pos = strpos($uri, '?'))) {
                $base  = substr($uri, 0, $q_pos);
                $query = substr($uri, $q_pos + 1);
            }

            // Parse existing query parameters.
            $existing_params = array();
            if ('' !== $query) {
                parse_str($query, $existing_params);
            }

            // Merge existing and new parameters (new ones override existing).
            $merged_params = array_merge($existing_params, $new_params);

            // Rebuild query string.
            $query_string = http_build_query($merged_params);

            $result = $base;
            if ('' !== $query_string) {
                $result .= '?' . $query_string;
            }

            // Reattach fragment, if any.
            $result .= $fragment;

            return $result;
        }
    }

    // AJAX exception classes for testing
    if (!class_exists('WPAjaxDieContinueException')) {
        class WPAjaxDieContinueException extends Exception {}
    }

    if (!class_exists('WPAjaxDieStopException')) {
        class WPAjaxDieStopException extends Exception {}
    }

    // Mock global $wpdb
    if (!isset($GLOBALS['wpdb'])) {
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public $data = array();

            public function esc_like($text) {
                return addcslashes($text, '_%\\');
            }

            public function prepare($query, ...$args) {
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
                        $arg = is_numeric($arg) ? $arg : addslashes((string)$arg);
                        $query = preg_replace('/%[sd]/', is_numeric($arg) ? $arg : "'$arg'", $query, 1);
                    }
                }
                return $query;
            }

            public function get_results($query, $output = OBJECT) {
                // Parse table name
                preg_match('/FROM\s+([^\s]+)/i', $query, $matches);
                $table = isset($matches[1]) ? str_replace('`', '', $matches[1]) : '';

                if (empty($table) || !isset($this->data[$table])) {
                    return array();
                }

                $results = $this->data[$table];

                // Parse WHERE clauses (simple)
                // Supports: col = val, col LIKE val, col IN (val1, val2)
                if (preg_match('/WHERE\s+(.*?)(\sORDER|\sLIMIT|\sGROUP|$)/is', $query, $matches)) {
                    $where_clause = $matches[1];
                    // Split by AND
                    $conditions = preg_split('/\s+AND\s+/i', $where_clause);

                    $results = array_filter($results, function($row) use ($conditions) {
                        foreach ($conditions as $condition) {
                            $condition = trim($condition);

                            // Handle 1=1
                            if ($condition === '1=1') {
                                continue;
                            }

                            if (preg_match('/([a-zA-Z0-9_]+)\s*(=|LIKE|>=|<=|>|<)\s*[\'"]?([^\'"]*)[\'"]?/i', $condition, $parts)) {
                                $col = $parts[1];
                                $op = strtoupper($parts[2]);
                                $val = $parts[3];

                                // Handle DATE_SUB(NOW(), ...) approximation
                                if (strpos($val, 'DATE_SUB') !== false) {
                                    // For testing purposes, assume true
                                    continue;
                                }

                                if (!isset($row[$col])) return false;

                                if ($op === '=') {
                                    if ((string)$row[$col] !== (string)$val) return false;
                                } elseif ($op === 'LIKE') {
                                    $pattern = '/^' . str_replace('%', '.*', preg_quote($val, '/')) . '$/i';
                                    if (!preg_match($pattern, $row[$col])) return false;
                                } elseif ($op === '>=') {
                                    if ($row[$col] < $val) return false;
                                } elseif ($op === '<=') {
                                    if ($row[$col] > $val) return false;
                                } elseif ($op === '>') {
                                    if ($row[$col] <= $val) return false;
                                } elseif ($op === '<') {
                                    if ($row[$col] >= $val) return false;
                                }
                            } elseif (preg_match('/([a-zA-Z0-9_]+)\s+IN\s*\((.*?)\)/i', $condition, $parts)) {
                                $col = $parts[1];
                                $vals = explode(',', str_replace("'", "", $parts[2]));
                                $vals = array_map('trim', $vals);

                                if (!isset($row[$col])) return false;
                                if (!in_array((string)$row[$col], $vals)) return false;
                            }
                        }
                        return true;
                    });
                }

                // Parse ORDER BY
                if (preg_match('/ORDER BY\s+([a-zA-Z0-9_]+)\s*(ASC|DESC)?/i', $query, $matches)) {
                    $col = $matches[1];
                    $dir = isset($matches[2]) ? strtoupper($matches[2]) : 'ASC';

                    usort($results, function($a, $b) use ($col, $dir) {
                        if (!isset($a[$col]) || !isset($b[$col])) return 0;
                        if ($a[$col] == $b[$col]) return 0;
                        if ($dir === 'ASC') {
                            return ($a[$col] < $b[$col]) ? -1 : 1;
                        } else {
                            return ($a[$col] > $b[$col]) ? -1 : 1;
                        }
                    });
                }

                // Parse GROUP BY (Basic implementation for get_niche_list)
                if (preg_match('/GROUP BY\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
                     $group_col = $matches[1];
                     $grouped_results = [];
                     foreach ($results as $row) {
                         $key = $row[$group_col];
                         if (!isset($grouped_results[$key])) {
                             $grouped_results[$key] = [
                                 $group_col => $key,
                                 'count' => 0
                             ];
                         }
                         $grouped_results[$key]['count']++;
                     }
                     // If query selects count, return formatted results
                     if (preg_match('/SELECT\s+.*COUNT\(\*\).*/i', $query)) {
                         $results = array_values($grouped_results);
                     }
                }

                // Parse LIMIT
                if (preg_match('/LIMIT\s+(\d+)(?:\s+OFFSET\s+(\d+))?/i', $query, $matches)) {
                    $limit = intval($matches[1]);
                    $offset = isset($matches[2]) ? intval($matches[2]) : 0;
                    $results = array_slice($results, $offset, $limit);
                }

                // Handle Aggregations in SELECT
                if (preg_match('/SELECT\s+(.*?)\s+FROM/is', $query, $matches)) {
                    $select_clause = $matches[1];

                    if (stripos($select_clause, 'COUNT(*)') !== false && stripos($query, 'GROUP BY') === false) {
                        // Look for 'as' alias
                        $key = 'COUNT(*)';
                        if (preg_match('/COUNT\(\*\)\s+as\s+([a-zA-Z0-9_]+)/i', $select_clause, $alias_matches)) {
                            $key = $alias_matches[1];
                        }
                        $res = array($key => count($results));
                        return $output == OBJECT ? array((object)$res) : array($res);
                    }

                    if (preg_match('/COUNT\(DISTINCT\s+([a-zA-Z0-9_]+)\)/i', $select_clause, $count_matches)) {
                        $col = $count_matches[1];
                        $unique = array_unique(array_column($results, $col));
                        $res = array('COUNT(DISTINCT ' . $col . ')' => count($unique));
                        return $output == OBJECT ? array((object)$res) : array($res);
                    }

                    if (preg_match('/AVG\s*\(\s*([a-zA-Z0-9_]+)\s*\)/i', $select_clause, $avg_matches)) {
                        $col = $avg_matches[1];
                        $values = array_column($results, $col);
                        $avg = count($values) > 0 ? array_sum($values) / count($values) : 0;
                        $key = 'AVG(' . $col . ')';
                        if (preg_match('/AVG\s*\(\s*([a-zA-Z0-9_]+)\s*\)\s+as\s+([a-zA-Z0-9_]+)/i', $select_clause, $alias_matches)) {
                             $key = $alias_matches[2];
                        }
                        $res = array($key => $avg);
                        return $output == OBJECT ? array((object)$res) : array($res);
                    }

                    // Handle MAX aggregation specifically for get_niche_stats
                    if (preg_match('/MAX\s*\(\s*([a-zA-Z0-9_]+)\s*\)/i', $select_clause, $max_matches)) {
                         // This is a simplified handler that assumes a row return with aliases
                         // It constructs a single row with all aggregates if multiple present
                         $row = array();

                         if (preg_match('/COUNT\(\*\)\s+as\s+([a-zA-Z0-9_]+)/i', $select_clause, $m)) {
                             $row[$m[1]] = count($results);
                         }
                         if (preg_match('/AVG\s*\(\s*score\s*\)\s+as\s+([a-zA-Z0-9_]+)/i', $select_clause, $m)) {
                             $values = array_column($results, 'score');
                             $row[$m[1]] = count($values) > 0 ? array_sum($values) / count($values) : 0;
                         }
                         if (preg_match('/MAX\s*\(\s*score\s*\)\s+as\s+([a-zA-Z0-9_]+)/i', $select_clause, $m)) {
                             $values = array_column($results, 'score');
                             $row[$m[1]] = count($values) > 0 ? max($values) : 0;
                         }
                         if (preg_match('/MAX\s*\(\s*researched_at\s*\)\s+as\s+([a-zA-Z0-9_]+)/i', $select_clause, $m)) {
                             $values = array_column($results, 'researched_at');
                             $row[$m[1]] = count($values) > 0 ? max($values) : null;
                         }

                         return $output == OBJECT ? array((object)$row) : array($row);
                    }
                }

                // Convert to objects
                $results = array_values($results); // Re-index
                if ($output == OBJECT) {
                    $results = array_map(function($row) {
                        return (object) $row;
                    }, $results);
                }

                return $results;
            }

            public function get_row($query, $output = OBJECT, $y = 0) {
                $results = $this->get_results($query, $output);
                return !empty($results) ? $results[0] : null;
            }

            public function get_var($query, $x = 0, $y = 0) {
                $results = $this->get_results($query, ARRAY_A);
                if (!empty($results)) {
                    $row = array_values($results[0]);
                    return isset($row[$x]) ? $row[$x] : null;
                }
                return null;
            }

            public function query($query) {
                // Handle TRUNCATE
                if (preg_match('/TRUNCATE\s+TABLE\s+([^\s]+)/i', $query, $matches)) {
                    $table = str_replace('`', '', $matches[1]);
                    $this->data[$table] = array();
                    return true;
                }

                // Handle CREATE TABLE (Initialize)
                if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([^\s]+)/i', $query, $matches)) {
                    $table = str_replace('`', '', $matches[1]);
                    if (!isset($this->data[$table])) {
                        $this->data[$table] = array();
                    }
                    return true;
                }

                // Handle INSERT INTO ... VALUES ... (basic support for create_bulk)
                if (preg_match('/INSERT\s+INTO\s+([^\s\(]+)\s*\(([^)]+)\)\s*VALUES\s*(.*)/is', $query, $matches)) {
                    $table = str_replace('`', '', $matches[1]);
                    $columns_str = $matches[2];
                    $values_str = $matches[3];

                    if (!isset($this->data[$table])) {
                        $this->data[$table] = array();
                    }

                    $columns = array_map('trim', explode(',', $columns_str));

                    // Simple parsing of values: assumes 'val', 'val' format and parentheses grouping
                    // This is brittle but might work for the test case
                    $rows = preg_split('/\)\s*,\s*\(/', $values_str);
                    $inserted = 0;

                    foreach ($rows as $row_str) {
                        $row_str = trim($row_str, "() \t\n\r\0\x0B");
                        // Split by comma respecting quotes is hard with simple regex, assume simple values for now
                        // Or use str_getcsv if format allows? No, it's SQL syntax.
                        // Assuming string values are quoted with '

                        $row_values = [];
                        $current_val = '';
                        $in_quote = false;
                        $len = strlen($row_str);
                        for ($i = 0; $i < $len; $i++) {
                            $char = $row_str[$i];
                            if ($char === "'" && ($i === 0 || $row_str[$i-1] !== '\\')) {
                                $in_quote = !$in_quote;
                            } elseif ($char === ',' && !$in_quote) {
                                $row_values[] = $current_val;
                                $current_val = '';
                                continue;
                            }
                            $current_val .= $char;
                        }
                        $row_values[] = $current_val;

                        $data = [];
                        foreach ($columns as $idx => $col) {
                            $val = isset($row_values[$idx]) ? trim($row_values[$idx]) : null;
                            // Unquote
                            if (substr($val, 0, 1) === "'" && substr($val, -1) === "'") {
                                $val = substr($val, 1, -1);
                                $val = stripslashes($val);
                            }
                            $data[$col] = $val;
                        }

                        // Add ID
                        static $next_insert_id = 1;
                        if (!isset($data['id'])) {
                            $data['id'] = $next_insert_id++;
                        }

                        $this->data[$table][] = $data;
                        $inserted++;
                    }

                    return $inserted;
                }

                // Handle DELETE FROM ... WHERE ... IN ...
                if (preg_match('/DELETE\s+FROM\s+([^\s]+)\s+WHERE\s+(.*)/is', $query, $matches)) {
                    $table = str_replace('`', '', $matches[1]);
                    $where_clause = $matches[2];

                    if (!isset($this->data[$table])) {
                        return 0;
                    }

                    $initial_count = count($this->data[$table]);

                    // Parse WHERE IN
                    if (preg_match('/([a-zA-Z0-9_]+)\s+IN\s*\((.*?)\)/i', $where_clause, $parts)) {
                        $col = $parts[1];
                        $vals = explode(',', str_replace("'", "", $parts[2]));
                        $vals = array_map('trim', $vals);

                        $this->data[$table] = array_filter($this->data[$table], function($row) use ($col, $vals) {
                             if (!isset($row[$col])) return true;
                             return !in_array((string)$row[$col], $vals);
                        });

                        // Re-index
                        $this->data[$table] = array_values($this->data[$table]);

                        return $initial_count - count($this->data[$table]);
                    } elseif (preg_match('/([a-zA-Z0-9_]+)\s*(=|LIKE|>=|<=|>|<)\s*[\'"]?([^\'"]*)[\'"]?/i', $where_clause, $parts)) {
                        // Simple WHERE support
                         $col = $parts[1];
                         $op = strtoupper($parts[2]);
                         $val = $parts[3];

                        $this->data[$table] = array_filter($this->data[$table], function($row) use ($col, $op, $val) {
                             if (!isset($row[$col])) return true;

                            if ($op === '=') {
                                return (string)$row[$col] !== (string)$val;
                            } elseif ($op === '<') {
                                return $row[$col] >= $val;
                            }
                            // Add other ops if needed
                            return true;
                        });

                        $this->data[$table] = array_values($this->data[$table]);
                        return $initial_count - count($this->data[$table]);
                    }

                    return 0;
                }

                return true;
            }

            public function insert($table, $data, $format = null) {
                static $next_insert_id = 1;
                $this->insert_id = $next_insert_id++;

                if (!isset($this->data[$table])) {
                    $this->data[$table] = array();
                }

                // Add ID if not present
                if (!isset($data['id'])) {
                    $data['id'] = $this->insert_id;
                }

                $this->data[$table][] = $data;
                return 1;
            }

            public function update($table, $data, $where, $format = null, $where_format = null) {
                if (!isset($this->data[$table])) {
                    return false;
                }

                $updated_count = 0;
                foreach ($this->data[$table] as $key => $row) {
                    $match = true;
                    foreach ($where as $col => $val) {
                        if (!isset($row[$col]) || $row[$col] != $val) {
                            $match = false;
                            break;
                        }
                    }

                    if ($match) {
                        $this->data[$table][$key] = array_merge($row, $data);
                        $updated_count++;
                    }
                }

                return $updated_count;
            }

            public function delete($table, $where, $where_format = null) {
                if (!isset($this->data[$table])) {
                    return false;
                }

                $deleted_count = 0;
                foreach ($this->data[$table] as $key => $row) {
                    $match = true;
                    foreach ($where as $col => $val) {
                        if (!isset($row[$col]) || $row[$col] != $val) {
                            $match = false;
                            break;
                        }
                    }

                    if ($match) {
                        unset($this->data[$table][$key]);
                        $deleted_count++;
                    }
                }

                $this->data[$table] = array_values($this->data[$table]); // Re-index
                return $deleted_count;
            }

            public function get_charset_collate() {
                return "DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci";
            }

            public function get_col($query = null, $x = 0) {
                 $results = $this->get_results($query, ARRAY_A);
                 $col = array();
                 foreach ($results as $row) {
                     $values = array_values($row);
                     if (isset($values[$x])) {
                         $col[] = $values[$x];
                     }
                 }
                 return $col;
            }
        };
    }

    // Mock global $wp_filter for action/filter hooks
    if (!isset($GLOBALS['wp_filter'])) {
        $GLOBALS['wp_filter'] = array();
    }

    // Load plugin classes
    $includes_dir = dirname(__DIR__) . '/includes/';
    $files = [
        'class-aips-logger.php',
        'class-aips-config.php',
        'class-aips-db-manager.php',
        'class-aips-history-repository.php',
        'class-aips-schedule-repository.php',
        'class-aips-template-repository.php',
        'class-aips-article-structure-repository.php',
        'class-aips-prompt-section-repository.php',
        'class-aips-template-processor.php',
        'class-aips-prompt-builder.php',
        'class-aips-article-structure-manager.php',
        'class-aips-template-type-selector.php',
        'class-aips-interval-calculator.php',
        'class-aips-resilience-service.php',
        'class-aips-ai-service.php',
        'class-aips-image-service.php',
        'interface-aips-generation-context.php',
        'class-aips-template-context.php',
        'class-aips-topic-context.php',
        'class-aips-generation-session.php',
        'class-aips-post-creator.php',
        'class-aips-generator.php',
        'class-aips-scheduler.php',
        'class-aips-schedule-controller.php',
        'class-aips-planner.php',
        'class-aips-history.php',
        'class-aips-settings.php',
        'class-aips-admin-assets.php',
        'class-aips-system-status.php',
        'class-aips-templates.php',
        'class-aips-upgrades.php',
        'class-aips-voices-repository.php',
        'class-aips-voices.php',
        'class-aips-structures-controller.php',
        'class-aips-templates-controller.php',
        'class-aips-research-controller.php',
        'class-aips-trending-topics-repository.php', // Added this one
    ];

    foreach ($files as $file) {
        if (file_exists($includes_dir . $file)) {
            require_once $includes_dir . $file;
        }
    }
}
