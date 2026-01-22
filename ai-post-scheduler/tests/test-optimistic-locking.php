<?php
/**
 * Test case for Optimistic Locking
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Optimistic_Locking extends WP_UnitTestCase {

    private $schedule_repo;
    private $template_repo;

    public function setUp(): void {
        parent::setUp();
        $this->schedule_repo = new AIPS_Schedule_Repository();
        $this->template_repo = new AIPS_Template_Repository();
    }

    /**
     * Test that update_next_run_conditional respects the optimistic lock.
     */
    public function test_update_next_run_conditional() {
        // 1. Create a dummy template
        $template_id = $this->template_repo->create(array(
            'name' => 'Locking Test Template',
            'prompt_template' => 'Write about {{topic}}',
            'post_status' => 'publish',
            'post_category' => 1,
            'is_active' => 1
        ));

        $initial_next_run = date('Y-m-d H:i:s');

        // 2. Create a schedule
        $schedule_id = $this->schedule_repo->create(array(
            'template_id' => $template_id,
            'frequency' => 'daily',
            'next_run' => $initial_next_run,
            'is_active' => 1,
            'topic' => 'Locking Topic'
        ));

        // 3. Attempt to update with CORRECT old value (Should Success)
        $new_next_run_1 = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $success = $this->schedule_repo->update_next_run_conditional($schedule_id, $new_next_run_1, $initial_next_run);

        $this->assertTrue($success, 'Should update when old value matches.');

        // Verify DB updated
        $schedule = $this->schedule_repo->get_by_id($schedule_id);
        $this->assertEquals($new_next_run_1, $schedule->next_run);

        // 4. Attempt to update with INCORRECT old value (Should Fail)
        // We pass $initial_next_run, but the DB now has $new_next_run_1
        $new_next_run_2 = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $fail = $this->schedule_repo->update_next_run_conditional($schedule_id, $new_next_run_2, $initial_next_run);

        $this->assertFalse($fail, 'Should fail to update when old value does not match.');

        // Verify DB NOT updated
        $schedule = $this->schedule_repo->get_by_id($schedule_id);
        $this->assertEquals($new_next_run_1, $schedule->next_run, 'next_run should remain unchanged after failed lock.');
    }
}
