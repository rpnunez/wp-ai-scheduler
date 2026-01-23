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
     * Test that update_next_run_conditional only updates if the expected value matches.
     */
    public function test_optimistic_locking_mechanism() {
        // 1. Create a template
        $template_id = $this->template_repo->create(array(
            'name' => 'Locking Test Template',
            'prompt_template' => 'Write about {{topic}}',
            'post_status' => 'publish',
            'post_category' => 1,
            'is_active' => 1
        ));

        // 2. Create a schedule
        $original_next_run = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $schedule_id = $this->schedule_repo->create(array(
            'template_id' => $template_id,
            'frequency' => 'daily',
            'next_run' => $original_next_run,
            'is_active' => 1,
            'topic' => 'Locking Topic'
        ));

        // 3. Attempt update with CORRECT old value (Should succeed)
        $new_next_run_1 = date('Y-m-d H:i:s', strtotime('+1 day'));
        $result1 = $this->schedule_repo->update_next_run_conditional(
            $schedule_id,
            $new_next_run_1,
            $original_next_run
        );
        $this->assertTrue($result1, 'Update should succeed when old value matches.');

        // Verify DB value
        $schedule = $this->schedule_repo->get_by_id($schedule_id);
        $this->assertEquals($new_next_run_1, $schedule->next_run);

        // 4. Attempt update with INCORRECT old value (Should fail)
        $new_next_run_2 = date('Y-m-d H:i:s', strtotime('+2 days'));
        $wrong_old_value = date('Y-m-d H:i:s', strtotime('2000-01-01')); // Random wrong date

        $result2 = $this->schedule_repo->update_next_run_conditional(
            $schedule_id,
            $new_next_run_2,
            $wrong_old_value
        );
        $this->assertFalse($result2, 'Update should fail when old value does not match.');

        // Verify DB value remains unchanged from step 3
        $schedule = $this->schedule_repo->get_by_id($schedule_id);
        $this->assertEquals($new_next_run_1, $schedule->next_run);
    }
}
