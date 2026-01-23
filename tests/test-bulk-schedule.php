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
}
