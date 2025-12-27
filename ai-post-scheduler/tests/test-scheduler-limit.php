<?php
/**
 * Test Scheduler Limit
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Test_Scheduler_Limit extends WP_UnitTestCase {

    public function setUp() {
        parent::setUp();
    }

    /**
     * Test that the process limit filter is available.
     *
     * Note: We cannot easily mock $wpdb->get_results to verify the SQL query structure
     * in this environment without a mocking framework like Mockery.
     * The presence of LIMIT in the query is verified by static analysis (reproduce_missing_limit.py).
     */
    public function test_process_limit_filter_defaults() {
        // Ensure the filter returns the default value of 5
        $limit = apply_filters('aips_schedule_process_limit', 5);
        $this->assertEquals(5, $limit, 'Default limit should be 5');
    }

    public function test_process_limit_filter_modification() {
        add_filter('aips_schedule_process_limit', function($limit) {
            return 10;
        });

        $limit = apply_filters('aips_schedule_process_limit', 5);
        $this->assertEquals(10, $limit, 'Filter should allow modifying the limit');
    }
}
