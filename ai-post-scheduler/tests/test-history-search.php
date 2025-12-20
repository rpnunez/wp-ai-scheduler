<?php
/**
 * Test case for Search Functionality in AIPS_History::get_history
 */

class Test_AIPS_History_Search extends WP_UnitTestCase {

    public function setUp() {
        parent::setUp();
        // Setup mock data if needed
    }

    /**
     * Test that get_history correctly applies the LIKE clause when search is provided.
     */
    public function test_get_history_with_search() {
        global $wpdb;
        $history_class = new AIPS_History();
        $table_name = $wpdb->prefix . 'aips_history';

        // Insert dummy data
        $wpdb->insert($table_name, array(
            'status' => 'completed',
            'generated_title' => 'Test Title 123',
            'created_at' => current_time('mysql'),
            'template_id' => 0
        ));

        $wpdb->insert($table_name, array(
            'status' => 'failed',
            'generated_title' => 'Another Article',
            'created_at' => current_time('mysql'),
            'template_id' => 0
        ));

        // Test search matching one item
        $args = array(
            'search' => 'Title',
            'per_page' => 10
        );

        $result = $history_class->get_history($args);

        $this->assertEquals(1, count($result['items']));
        $this->assertEquals('Test Title 123', $result['items'][0]->generated_title);

        // Test search matching no items
        $args = array(
            'search' => 'NonExistent',
            'per_page' => 10
        );

        $result = $history_class->get_history($args);
        $this->assertEquals(0, count($result['items']));
    }
}
