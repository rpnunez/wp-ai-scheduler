<?php
/**
 * Test Atomic Update
 *
 * Verifies the optimistic locking mechanism in AIPS_Schedule_Repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_Atomic_Update extends WP_UnitTestCase {

    private $repository;

    public function setUp(): void {
        parent::setUp();
        $this->repository = new AIPS_Schedule_Repository();
    }

    public function test_atomic_update_success() {
        // Create a schedule
        $id = $this->repository->create(array(
            'template_id' => 1,
            'frequency' => 'daily',
            'next_run' => '2023-01-01 12:00:00',
            'is_active' => 1
        ));

        $this->assertNotFalse($id);

        // Attempt atomic update with correct old value
        $new_run = '2023-01-02 12:00:00';
        $result = $this->repository->update_next_run_atomic($id, $new_run, '2023-01-01 12:00:00');

        $this->assertTrue($result, 'Atomic update should succeed when old value matches');

        // Verify value was updated
        $schedule = $this->repository->get_by_id($id);
        $this->assertEquals($new_run, $schedule->next_run);
    }

    public function test_atomic_update_failure() {
        // Create a schedule
        $id = $this->repository->create(array(
            'template_id' => 1,
            'frequency' => 'daily',
            'next_run' => '2023-01-01 12:00:00',
            'is_active' => 1
        ));

        // Attempt atomic update with INCORRECT old value
        $new_run = '2023-01-02 12:00:00';
        $result = $this->repository->update_next_run_atomic($id, $new_run, '2023-01-01 13:00:00'); // Wrong time

        $this->assertFalse($result, 'Atomic update should fail when old value does not match');

        // Verify value was NOT updated
        $schedule = $this->repository->get_by_id($id);
        $this->assertEquals('2023-01-01 12:00:00', $schedule->next_run);
    }

    public function test_concurrent_simulation() {
        // Create a schedule
        $id = $this->repository->create(array(
            'template_id' => 1,
            'frequency' => 'daily',
            'next_run' => '2023-01-01 12:00:00',
            'is_active' => 1
        ));

        // Simulating Process A
        $proc_a_old_run = '2023-01-01 12:00:00';
        $proc_a_new_run = '2023-01-02 12:00:00';

        // Simulating Process B (same view of world)
        $proc_b_old_run = '2023-01-01 12:00:00';
        $proc_b_new_run = '2023-01-02 12:00:00';

        // Process A claims it
        $result_a = $this->repository->update_next_run_atomic($id, $proc_a_new_run, $proc_a_old_run);
        $this->assertTrue($result_a);

        // Process B tries to claim it (but it's too late, DB has changed)
        $result_b = $this->repository->update_next_run_atomic($id, $proc_b_new_run, $proc_b_old_run);
        $this->assertFalse($result_b, 'Second process should fail to claim the lock');
    }
}
