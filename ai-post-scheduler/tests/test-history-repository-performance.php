<?php
/**
 * Test History Repository Performance
 *
 * Checks that the get_history method optimizes column selection.
 */

class Test_History_Repository_Performance extends WP_UnitTestCase {
    private $original_wpdb;
    private $mock_wpdb;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->original_wpdb = $wpdb;

        // Create a mock wpdb that captures queries
        $this->mock_wpdb = new class {
            public $prefix = 'wp_';
            public $last_query = '';

            public function prepare($query, $args = []) {
                // Simplified prepare for testing
                if (is_array($args)) {
                    foreach ($args as $arg) {
                        $query = preg_replace('/%[sdF]/', "'$arg'", $query, 1);
                    }
                }
                return $query;
            }

            public function esc_like($text) {
                return $text;
            }

            public function get_results($query) {
                $this->last_query = $query;
                return [];
            }

            public function get_var($query) {
                return 0;
            }
        };

        $wpdb = $this->mock_wpdb;
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;
        parent::tearDown();
    }

    public function test_get_history_selects_all_columns_by_default() {
        $repo = new AIPS_History_Repository();
        $repo->get_history();

        $this->assertStringContainsString('SELECT h.*', $this->mock_wpdb->last_query);
    }

    public function test_get_history_selects_specific_columns_for_list() {
        $repo = new AIPS_History_Repository();
        $repo->get_history(['fields' => 'list']);

        // This assertion will fail until we implement the optimization
        $this->assertStringContainsString('SELECT h.id, h.post_id', $this->mock_wpdb->last_query);
        $this->assertStringNotContainsString('SELECT h.*', $this->mock_wpdb->last_query);
    }

    public function test_get_history_selects_specific_columns_for_dashboard() {
        $repo = new AIPS_History_Repository();
        $repo->get_history(['fields' => 'dashboard']);

        // This assertion will fail until we implement the optimization
        $this->assertStringContainsString('SELECT h.id, h.post_id', $this->mock_wpdb->last_query);
        $this->assertStringNotContainsString('h.error_message', $this->mock_wpdb->last_query);
    }
}
