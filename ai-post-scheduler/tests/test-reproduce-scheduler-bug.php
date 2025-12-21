<?php
/**
 * Verification Test Case for Scheduler ID Collision Fix
 *
 * Issue: The scheduler query used `SELECT s.*, t.*` which caused column collision.
 * Fix: Changed to `SELECT t.*, s.*` so `s.id` (Schedule ID) overwrites `t.id`.
 *
 * This test verifies that the new SQL pattern correctly preserves the Schedule ID.
 */

class Test_AIPS_Scheduler_Bug extends WP_UnitTestCase {

    public function setUp() {
        parent::setUp();
    }

    public function test_schedule_query_column_collision_fix() {
        global $wpdb;

        $schedule_table = $wpdb->prefix . 'aips_schedule';
        $templates_table = $wpdb->prefix . 'aips_templates';

        // 1. Create a Template with ID 100
        $wpdb->insert($templates_table, array(
            'id' => 100,
            'name' => 'Test Template',
            'prompt_template' => 'Write about testing.',
            'is_active' => 1
        ));

        // 2. Create a Schedule with ID 200, linked to Template ID 100
        $wpdb->insert($schedule_table, array(
            'id' => 200,
            'template_id' => 100,
            'frequency' => 'daily',
            'next_run' => date('Y-m-d H:i:s', strtotime('-1 hour')), // Due now
            'is_active' => 1
        ));

        // 3. Run the FIXED query pattern
        $sql = "
            SELECT t.*, s.*
            FROM {$schedule_table} s
            INNER JOIN {$templates_table} t ON s.template_id = t.id
            WHERE s.is_active = 1
            AND s.next_run <= %s
            AND t.is_active = 1
            ORDER BY s.next_run ASC
        ";

        $results = $wpdb->get_results($wpdb->prepare($sql, current_time('mysql')));

        if (empty($results)) {
            $this->fail("No results found. Setup failed.");
        }

        $row = $results[0];

        // 4. Assert that $row->id is CORRECTLY 200 (Schedule ID)

        echo "Row ID: " . $row->id . "\n";
        echo "Template ID: " . $row->template_id . "\n";

        $this->assertEquals(200, $row->id, "Fix Verified: Schedule ID (200) is preserved over Template ID (100).");
        $this->assertEquals(100, $row->template_id, "Template ID (100) is still available as template_id.");
        $this->assertEquals('Test Template', $row->name, "Template Name is preserved from t.*");
    }
}
