<?php

class Test_AIPS_History_Performance extends WP_UnitTestCase {
    private $original_wpdb;
    private $wpdb_mock;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->original_wpdb = $wpdb;

        // We need to mock wpdb. If the class exists (real WP tests), mock it.
        // Otherwise mock stdClass with the methods we need.
        if (class_exists('wpdb')) {
            $this->wpdb_mock = $this->getMockBuilder('wpdb')
                ->disableOriginalConstructor()
                ->getMock();
        } else {
            $this->wpdb_mock = $this->getMockBuilder('stdClass')
                ->addMethods(['get_results', 'prepare', 'get_var', 'esc_like'])
                ->getMock();
        }

        $this->wpdb_mock->prefix = 'wp_';

        // Assign mock to global so the Repository picks it up
        $wpdb = $this->wpdb_mock;
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;
        parent::tearDown();
    }

    public function test_get_history_selects_specific_columns() {
        // Expect prepare to be called
        // We simulate prepare returning the query string for inspection in get_results
        $this->wpdb_mock->expects($this->any())
            ->method('prepare')
            ->willReturnCallback(function($query, $args = null) {
                return $query;
            });

        $this->wpdb_mock->expects($this->any())
             ->method('esc_like')
             ->willReturnArgument(0);

        // We expect get_results to be called with a query that selects specific columns
        $this->wpdb_mock->expects($this->once())
            ->method('get_results')
            ->with($this->callback(function($query) {
                // Debug output if needed: echo "\nQuery: $query\n";

                // Assert that the query does NOT contain 'h.*'
                if (strpos($query, 'SELECT h.*') !== false) {
                    return false;
                }

                // Assert that the query DOES contain specific columns
                $required_columns = [
                    'h.id',
                    'h.post_id',
                    'h.generated_title',
                    'h.status',
                    'h.error_message',
                    'h.created_at',
                    'h.template_id'
                ];

                foreach ($required_columns as $col) {
                    if (strpos($query, $col) === false) {
                        return false;
                    }
                }

                return true;
            }))
            ->willReturn(array());

        $history_repo = new AIPS_History_Repository();
        $history_repo->get_history(array('per_page' => 10));
    }
}
