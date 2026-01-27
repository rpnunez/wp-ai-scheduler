<?php
/**
 * Test Bulk Schedule
 *
 * @package AI_Post_Scheduler
 */

class Test_Bulk_Schedule extends WP_UnitTestCase {

    private $scheduler;
    private $planner;

    public function setUp(): void {
        parent::setUp();
        $this->scheduler = new AIPS_Scheduler();
        $this->planner = new AIPS_Planner();
    }

    public function test_bulk_create() {
        global $wpdb;

        // Create a template first
        // Since we use custom table, insert directly
        $wpdb->insert($wpdb->prefix . 'aips_templates', array(
            'name' => 'Test Template',
            'prompt_template' => 'Test Prompt',
            'is_active' => 1
        ));
        $template_id = $wpdb->insert_id;

        $schedules = array(
            array(
                'template_id' => $template_id,
                'frequency' => 'once',
                'next_run' => '2024-01-01 10:00:00',
                'is_active' => 1,
                'topic' => 'Topic 1'
            ),
            array(
                'template_id' => $template_id,
                'frequency' => 'once',
                'next_run' => '2024-01-01 11:00:00',
                'is_active' => 1,
                'topic' => 'Topic 2'
            )
        );

        $count = $this->scheduler->save_schedule_bulk($schedules);

        $this->assertEquals(2, $count);

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aips_schedule WHERE template_id = %d ORDER BY topic ASC",
                $template_id
            )
        );
        $this->assertCount(2, $results);
        $this->assertEquals('Topic 1', $results[0]->topic);
        $this->assertEquals('Topic 2', $results[1]->topic);
    }

    public function test_bulk_delete() {
        global $wpdb;

        // Create a template first
        $wpdb->insert($wpdb->prefix . 'aips_templates', array(
            'name' => 'Test Template for Delete',
            'prompt_template' => 'Test Prompt',
            'is_active' => 1
        ));
        $template_id = $wpdb->insert_id;

        // Create schedules
        $schedules = array(
            array(
                'template_id' => $template_id,
                'frequency' => 'once',
                'next_run' => '2024-01-01 10:00:00',
                'is_active' => 1,
                'topic' => 'Delete Topic 1'
            ),
            array(
                'template_id' => $template_id,
                'frequency' => 'once',
                'next_run' => '2024-01-01 11:00:00',
                'is_active' => 1,
                'topic' => 'Delete Topic 2'
            )
        );

        $this->scheduler->save_schedule_bulk($schedules);

        // Get IDs
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aips_schedule WHERE template_id = %d",
                $template_id
            )
        );

        $ids = array();
        foreach ($results as $row) {
            $ids[] = $row->id;
        }

        $this->assertCount(2, $ids);

        // Test Bulk Delete
        $deleted_count = $this->scheduler->delete_schedule_bulk($ids);

        $this->assertEquals(2, $deleted_count);

        // Verify they are gone
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aips_schedule WHERE template_id = %d",
                $template_id
            )
        );
        $this->assertEquals(0, $count);
    }
}
