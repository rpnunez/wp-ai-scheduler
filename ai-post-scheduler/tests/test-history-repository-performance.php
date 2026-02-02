<?php
/**
 * Test case for AIPS_History_Repository Optimization
 */

class Test_AIPS_History_Optimization extends WP_UnitTestCase {

    private $original_wpdb;
    private $wpdb_mock;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->original_wpdb = $wpdb;

        // Create a mock for wpdb
        $this->wpdb_mock = $this->getMockBuilder('stdClass')
            ->setMethods(array('prepare', 'get_results', 'get_var', 'esc_like'))
            ->getMock();

        $this->wpdb_mock->prefix = 'wp_';

        // Mock prepare to just return the query (simplified)
        $this->wpdb_mock->expects($this->any())
            ->method('prepare')
            ->will($this->returnCallback(function($query, $args = null) {
                return $query; // For this test, we just want to inspect the query structure
            }));

        $this->wpdb_mock->expects($this->any())
             ->method('esc_like')
             ->will($this->returnArgument(0));

        $wpdb = $this->wpdb_mock;
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;
        parent::tearDown();
    }

    public function test_get_history_selects_specific_columns() {
        $repository = new AIPS_History_Repository();

        // We expect get_results to be called with a query containing specific columns
        $this->wpdb_mock->expects($this->once())
            ->method('get_results')
            ->with($this->callback(function($query) {
                // Check if the query selects specific columns and NOT h.*
                $has_specific_columns = strpos($query, 'SELECT h.id, h.uuid, h.post_id, h.template_id, h.status, h.generated_title, h.error_message, h.created_at, h.completed_at, t.name as template_name') !== false;
                $no_wildcard = strpos($query, 'SELECT h.*') === false;

                return $has_specific_columns && $no_wildcard;
            }))
            ->willReturn(array());

        $repository->get_history(array('per_page' => 10));
    }
}
