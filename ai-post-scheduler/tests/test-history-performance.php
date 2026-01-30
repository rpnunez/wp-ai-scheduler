<?php
/**
 * Test case for History Query Optimization
 */
class Test_AIPS_History_Performance extends WP_UnitTestCase {
    private $wpdb_backup;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->wpdb_backup = $wpdb;
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->wpdb_backup;
        parent::tearDown();
    }

    public function test_get_history_selects_specific_columns() {
        global $wpdb;

        // Mock the wpdb object
        $mock_wpdb = $this->getMockBuilder('stdClass')
                     ->setMethods(array('prepare', 'get_results', 'get_var', 'esc_like'))
                     ->getMock();

        $mock_wpdb->prefix = 'wp_';

        // Mock prepare to return the query pattern so we can inspect it
        $mock_wpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            return $query;
        });

        // We expect get_results to be called with a query containing specific columns
        $mock_wpdb->expects($this->once())
             ->method('get_results')
             ->with($this->callback(function($query) {
                 // Check if the query selects specific columns and NOT *
                 // The query should NOT contain "SELECT h.*"
                 $has_wildcard = strpos($query, 'SELECT h.*') !== false;

                 // The query SHOULD contain the specific columns
                 $has_specific_cols = strpos($query, 'h.id') !== false
                                   && strpos($query, 'h.post_id') !== false
                                   && strpos($query, 'h.template_id') !== false
                                   && strpos($query, 'h.status') !== false
                                   && strpos($query, 'h.generated_title') !== false
                                   && strpos($query, 'h.error_message') !== false
                                   && strpos($query, 'h.created_at') !== false
                                   && strpos($query, 'h.completed_at') !== false
                                   && strpos($query, 't.name as template_name') !== false;

                 if ($has_wildcard) {
                     echo "\nQuery still uses h.* wildcard selection\n";
                 }
                 if (!$has_specific_cols) {
                     echo "\nQuery is missing specific columns selection\n";
                     echo "Query was: " . $query . "\n";
                 }

                 return !$has_wildcard && $has_specific_cols;
             }))
             ->willReturn(array());

        // Replace global wpdb with mock
        $wpdb = $mock_wpdb;

        // Instantiate repository which will capture the mocked wpdb
        $repo = new AIPS_History_Repository();
        $repo->get_history(array());
    }
}
