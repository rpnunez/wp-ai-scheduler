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
        $table_schedule = $wpdb->prefix . 'aips_schedule';

        // Create 2 templates
        $wpdb->insert($table_templates, array('name' => 'T1', 'prompt_template' => 'P1', 'is_active' => 1));
        $t1 = $wpdb->insert_id;
        $wpdb->insert($table_templates, array('name' => 'T2', 'prompt_template' => 'P2', 'is_active' => 1));
        $t2 = $wpdb->insert_id;

        // Create schedules
        // T1: 1 today
        $wpdb->insert($table_schedule, array(
            'template_id' => $t1,
            'frequency' => 'daily',
            'next_run' => current_time('mysql'),
            'is_active' => 1
        ));

        // T2: 1 today, 1 week
        $wpdb->insert($table_schedule, array(
            'template_id' => $t2,
            'frequency' => 'daily',
            'next_run' => current_time('mysql'),
            'is_active' => 1
        ));

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
