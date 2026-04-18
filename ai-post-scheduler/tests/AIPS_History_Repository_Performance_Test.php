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

class AIPS_History_Repository_Performance_Test extends WP_UnitTestCase {

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
        $captured_query = null;

        $wpdb_mock = new class {
            public $prefix = 'wp_';
            public $captured_query = null;
            public function prepare($query, ...$args) { return $query; }
            public function esc_like($text) { return $text; }
            public function get_var($query, $x = 0, $y = 0) { return 0; }
            public function get_results($query, $output = OBJECT) {
                $this->captured_query = $query;
                return array();
            }
        };

        $GLOBALS['wpdb'] = $wpdb_mock;

        $repo = new AIPS_History_Repository();
        $repo->get_history();

        $query = $wpdb_mock->captured_query;
        $this->assertNotNull($query, 'get_results should have been called');
        $this->assertStringContainsString(
            'SELECT h.id, h.uuid, h.post_id, h.template_id, h.status, h.generated_title, h.error_message, h.created_at, h.completed_at',
            $query,
            'Query should select specific columns'
        );
        $this->assertStringNotContainsString('SELECT h.*', $query, 'Query should not use wildcard');
    }

    /**
     * Test that unbounded partial generation queries do not emit LIMIT -1.
     */
    public function test_get_partial_generations_omits_limit_for_unbounded_requests() {
        $prepare_called   = false;
        $results_query    = null;
        $var_query        = null;

        $wpdb_mock = new class {
            public $prefix   = 'wp_';
            public $posts    = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public $prepare_called = false;
            public $results_query  = null;
            public $var_query      = null;
            public function prepare($query, ...$args) {
                $this->prepare_called = true;
                return $query;
            }
            public function esc_like($text) { return $text; }
            public function get_results($query, $output = OBJECT) {
                $this->results_query = $query;
                return array();
            }
            public function get_var($query, $x = 0, $y = 0) {
                $this->var_query = $query;
                return 2;
            }
        };

        $GLOBALS['wpdb'] = $wpdb_mock;

        $repo   = new AIPS_History_Repository();
        $result = $repo->get_partial_generations(array(
            'per_page' => -1,
            'page'     => 1,
        ));

        $this->assertFalse($wpdb_mock->prepare_called, 'prepare() should not be called for unbounded queries');
        $this->assertNotNull($wpdb_mock->results_query, 'get_results should have been called');
        $this->assertStringNotContainsString('LIMIT', $wpdb_mock->results_query, 'Results query should not have LIMIT');
        $this->assertStringNotContainsString('OFFSET', $wpdb_mock->results_query, 'Results query should not have OFFSET');
        $this->assertNotNull($wpdb_mock->var_query, 'get_var should have been called');
        $this->assertStringNotContainsString('LIMIT', $wpdb_mock->var_query, 'Count query should not have LIMIT');

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['pages']);
        $this->assertSame(1, $result['current_page']);
    }
}
