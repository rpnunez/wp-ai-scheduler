<?php
/**
 * Test Bulk Schedule Delete
 *
 * @package AI_Post_Scheduler
 */

class Test_Bulk_Schedule_Delete extends WP_UnitTestCase {

    private $repository;
    private $template_id;

    public function setUp(): void {
        parent::setUp();
        $this->repository = new AIPS_Schedule_Repository();

        global $wpdb;
        // Create a template first
        $wpdb->insert($wpdb->prefix . 'aips_templates', array(
            'name' => 'Test Template for Bulk Delete',
            'prompt_template' => 'Test Prompt',
            'is_active' => 1
        ));
        $this->template_id = $wpdb->insert_id;
    }

    public function test_delete_bulk() {
        // Create 3 schedules
        $schedules = array(
            array(
                'template_id' => $this->template_id,
                'frequency' => 'daily',
                'next_run' => '2024-01-01 10:00:00',
                'is_active' => 1,
                'topic' => 'Topic 1'
            ),
            array(
                'template_id' => $this->template_id,
                'frequency' => 'daily',
                'next_run' => '2024-01-01 11:00:00',
                'is_active' => 1,
                'topic' => 'Topic 2'
            ),
            array(
                'template_id' => $this->template_id,
                'frequency' => 'daily',
                'next_run' => '2024-01-01 12:00:00',
                'is_active' => 1,
                'topic' => 'Topic 3'
            )
        );

        $this->repository->create_bulk($schedules);

        // Get all schedules to get their IDs
        $all_schedules = $this->repository->get_all();
        $this->assertCount(3, $all_schedules);

        $ids_to_delete = array($all_schedules[0]->id, $all_schedules[1]->id);

        // Attempt to bulk delete
        // Note: this method doesn't exist yet, so this test is expected to fail or error out
        $result = $this->repository->delete_bulk($ids_to_delete);

        $this->assertEquals(2, $result, 'Should return number of deleted rows');

        // Verify only 1 remains
        $remaining = $this->repository->get_all();
        $this->assertCount(1, $remaining);
        $this->assertEquals('Topic 3', $remaining[0]->topic);
    }
}
