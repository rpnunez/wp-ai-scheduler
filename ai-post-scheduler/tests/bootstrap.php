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
    
    // Mock WordPress functions if not available
    if (!function_exists('esc_html__')) {
        function esc_html__($text, $domain = 'default') {
            return $text;
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
            global $wp_filter;
            if (!isset($wp_filter[$hook])) {
                $wp_filter[$hook] = array();
            }
            $wp_filter[$hook][] = array(
                'function' => $callback,
                'accepted_args' => $accepted_args,
            );
            return true;
        }
    }
    
    if (!function_exists('add_filter')) {
        function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
            // No-op in test environment
        }
    }
    
    if (!function_exists('apply_filters')) {
        function apply_filters($hook, $value) {
            return $value;
        }
    }
    
    if (!function_exists('do_action')) {
        function do_action($hook) {
            // No-op in test environment
        }
    }
    
    if (!function_exists('current_time')) {
        function current_time($type = 'mysql', $gmt = 0) {
            if ($type === 'timestamp') {
                return time();
            }

            if ($type === 'mysql') {
                if ($gmt) {
                    return gmdate('Y-m-d H:i:s');
                }

                return date('Y-m-d H:i:s');
            }

            // Fallback: return a Unix timestamp for unknown types.
            return time();
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
                parent::tearDown();
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
    
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) {
            return strip_tags($str);
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
    
    if (!function_exists('absint')) {
        function absint($maybeint) {
            return abs(intval($maybeint));
        }
    }
    
    if (!function_exists('__')) {
        function __($text, $domain = 'default') {
            return $text;
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
            private $data = array();
            
            public function prepare($query, ...$args) {
                return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
            }
            
            public function get_results($query, $output = OBJECT) {
                return array();
            }
            
            public function get_row($query, $output = OBJECT, $y = 0) {
                return null;
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
        'class-aips-article-structure-manager.php',
        'class-aips-template-type-selector.php',
        'class-aips-interval-calculator.php',
        'class-aips-resilience-service.php',
        'class-aips-ai-service.php',
        'class-aips-image-service.php',
        'class-aips-generation-session.php',
        'class-aips-post-creator.php',
        'class-aips-generator.php',
        'class-aips-scheduler.php',
        'class-aips-schedule-controller.php',
        'class-aips-planner.php',
        'class-aips-history.php',
        'class-aips-settings.php',
        'class-aips-system-status.php',
        'class-aips-templates.php',
        'class-aips-upgrades.php',
        'class-aips-voices.php',
        'class-aips-structures-controller.php',
        'class-aips-templates-controller.php',
        'class-aips-research-controller.php',
    ];
    
    foreach ($files as $file) {
        if (file_exists($includes_dir . $file)) {
            require_once $includes_dir . $file;
        }
    }
}
