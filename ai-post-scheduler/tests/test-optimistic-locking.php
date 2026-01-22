<?php
/**
 * Test case for Optimistic Locking
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Optimistic_Locking extends WP_UnitTestCase {

    private $schedule_repo;

    public function setUp(): void {
        parent::setUp();
        $this->schedule_repo = new AIPS_Schedule_Repository();
    }

    public function test_update_next_run_conditional() {
        if (!method_exists($this->schedule_repo, 'update_next_run_conditional')) {
            $this->markTestSkipped('update_next_run_conditional method not implemented yet.');
        }

        // Setup
        $schedule_id = $this->schedule_repo->create(array(
            'template_id' => 1,
            'frequency' => 'daily',
            'next_run' => '2023-01-01 12:00:00',
            'is_active' => 1
        ));

        $old_next_run = '2023-01-01 12:00:00';
        $new_next_run = '2023-01-02 12:00:00';

        // Success case
        $result = $this->schedule_repo->update_next_run_conditional($schedule_id, $new_next_run, $old_next_run);
        $this->assertTrue($result, 'Should update when old_next_run matches');

        // Verify update
        $schedule = $this->schedule_repo->get_by_id($schedule_id);
        $this->assertEquals($new_next_run, $schedule->next_run);

        // Failure case (concurrency simulation)
        $another_next_run = '2023-01-03 12:00:00';
        $result = $this->schedule_repo->update_next_run_conditional($schedule_id, $another_next_run, $old_next_run);
        $this->assertFalse($result, 'Should NOT update when old_next_run does not match');

        // Verify no update
        $schedule = $this->schedule_repo->get_by_id($schedule_id);
        $this->assertEquals($new_next_run, $schedule->next_run);
    }
}
