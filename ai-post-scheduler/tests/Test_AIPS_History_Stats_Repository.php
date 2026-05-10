<?php
/**
 * Tests for History Stats Repository
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Stats_Repository extends WP_UnitTestCase {

    private $repository;

    public function setUp(): void {
        parent::setUp();
        $this->repository = new AIPS_History_Stats_Repository();
    }

    public function test_get_stats_empty() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aips_history';
        $wpdb->query("TRUNCATE TABLE {$table_name}");
        delete_transient('aips_history_stats');

        $stats = $this->repository->get_stats();

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['completed']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(0, $stats['processing']);
        $this->assertEquals(0, $stats['partial']);
        $this->assertEquals(0, $stats['success_rate']);
    }

    public function test_get_all_template_stats_empty() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aips_history';
        $wpdb->query("TRUNCATE TABLE {$table_name}");

        $stats = $this->repository->get_all_template_stats();
        $this->assertEmpty($stats);
    }
}
