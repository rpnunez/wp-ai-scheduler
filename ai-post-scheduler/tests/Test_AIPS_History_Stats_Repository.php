<?php
/**
 * Test case for AIPS_History_Stats_Repository
 */

class Test_AIPS_History_Stats_Repository extends WP_UnitTestCase {

    private $stats_repo;
    private $wpdb;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->stats_repo = new AIPS_History_Stats_Repository($wpdb, $wpdb->prefix . 'aips_history', $wpdb->prefix . 'aips_history_log');
    }

    public function test_get_daily_success_failure_trend() {
        $result = $this->stats_repo->get_daily_success_failure_trend(7);
        $this->assertIsArray($result);
    }

    public function test_get_average_duration_by_flow() {
        $result = $this->stats_repo->get_average_duration_by_flow(7);
        $this->assertIsArray($result);
    }

    public function test_get_retry_counts_by_service() {
        $result = $this->stats_repo->get_retry_counts_by_service(7);
        $this->assertIsArray($result);
    }

    public function test_get_top_failure_reasons() {
        $result = $this->stats_repo->get_top_failure_reasons(7, 5);
        $this->assertIsArray($result);
    }

    public function test_get_estimated_generation_time() {
        $result = $this->stats_repo->get_estimated_generation_time(10);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('per_post_seconds', $result);
        $this->assertArrayHasKey('sample_size', $result);
    }

    public function test_get_stats() {
        $result = $this->stats_repo->get_stats();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
    }

    public function test_get_daily_generation_counts() {
        if (!function_exists('wp_date')) {
            $this->markTestSkipped('wp_date is not available in limited test mode.');
        }
        $result = $this->stats_repo->get_daily_generation_counts(7);
        $this->assertIsArray($result);
    }

    public function test_get_template_stats() {
        $result = $this->stats_repo->get_template_stats(1);
        $this->assertIsInt($result);
    }

    public function test_get_all_template_stats() {
        $result = $this->stats_repo->get_all_template_stats();
        $this->assertIsArray($result);
    }

    public function test_get_schedule_generated_post_counts() {
        $result = $this->stats_repo->get_schedule_generated_post_counts([1, 2, 3]);
        $this->assertIsArray($result);
    }
}
