<?php
/**
 * Test N+1 Query Fix
 *
 * @package AI_Post_Scheduler
 */

class Test_N_Plus_One extends WP_UnitTestCase {

    public function test_get_all_pending_stats() {
        global $wpdb;
        $table_templates = $wpdb->prefix . 'aips_templates';

        // Create 2 templates
        $wpdb->insert($table_templates, array('name' => 'T1', 'prompt_template' => 'P1', 'is_active' => 1));
        $t1 = $wpdb->insert_id;
        $wpdb->insert($table_templates, array('name' => 'T2', 'prompt_template' => 'P2', 'is_active' => 1));
        $t2 = $wpdb->insert_id;

        // Create schedules through the repository so its cached
        // get_active_schedules() result is invalidated. next_run is a UTC
        // timestamp column.
        $schedule_repository = new AIPS_Schedule_Repository();
        $now = AIPS_DateTime::now()->timestamp();

        // T1: 1 today
        $schedule_repository->create(array(
            'template_id' => $t1,
            'frequency' => 'daily',
            'next_run' => $now,
            'is_active' => 1
        ));

        // T2: 1 today, 1 week
        $schedule_repository->create(array(
            'template_id' => $t2,
            'frequency' => 'daily',
            'next_run' => $now,
            'is_active' => 1
        ));

        // Stats are cached in a transient; make sure this run computes them.
        delete_transient('aips_pending_schedule_stats');

        $templates = new AIPS_Templates();
        $stats = $templates->get_all_pending_stats();

        $this->assertArrayHasKey($t1, $stats);
        $this->assertArrayHasKey($t2, $stats);

        // T1 should have counts (at least 1 today, likely more for week/month due to recurrence)
        $this->assertGreaterThanOrEqual(1, $stats[$t1]['today']);

        // T2 should have counts
        $this->assertGreaterThanOrEqual(1, $stats[$t2]['today']);
    }
}
