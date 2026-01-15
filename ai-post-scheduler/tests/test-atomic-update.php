<?php
/**
 * Test Atomic Update Logic
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Test_Atomic_Update extends WP_UnitTestCase {

    private $repository;

    public function setUp() {
        parent::setUp();
        $this->repository = new AIPS_Schedule_Repository();
    }

    public function test_update_next_run_atomic_success() {
        // Create a schedule
        $id = $this->repository->create(array(
            'template_id' => 1,
            'frequency' => 'hourly',
            'next_run' => '2024-01-01 12:00:00',
            'is_active' => 1
        ));

        $old_next_run = '2024-01-01 12:00:00';
        $new_next_run = '2024-01-01 13:00:00';

        // Attempt atomic update with correct old value
        $result = $this->repository->update_next_run_atomic($id, $new_next_run, $old_next_run);

        $this->assertTrue($result, 'Atomic update should succeed when old value matches');

        // Verify value changed
        $schedule = $this->repository->get_by_id($id);
        $this->assertEquals($new_next_run, $schedule->next_run);
    }

    public function test_update_next_run_atomic_failure() {
        // Create a schedule
        $id = $this->repository->create(array(
            'template_id' => 1,
            'frequency' => 'hourly',
            'next_run' => '2024-01-01 12:00:00',
            'is_active' => 1
        ));

        $wrong_old_next_run = '2024-01-01 11:00:00';
        $new_next_run = '2024-01-01 13:00:00';

        // Attempt atomic update with INCORRECT old value
        $result = $this->repository->update_next_run_atomic($id, $new_next_run, $wrong_old_next_run);

        $this->assertFalse($result, 'Atomic update should fail when old value does not match');

        // Verify value did NOT change
        $schedule = $this->repository->get_by_id($id);
        $this->assertEquals('2024-01-01 12:00:00', $schedule->next_run);
    }
}
