<?php
/**
 * Test Concurrency Check Logic
 *
 * This file verifies the logic of AIPS_Schedule_Repository::update_next_run_atomic
 * and its usage in AIPS_Scheduler.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Test_Concurrency_Check extends WP_UnitTestCase {

    private $repository;
    private $scheduler;

    public function setUp(): void {
        parent::setUp();
        $this->repository = new AIPS_Schedule_Repository();
        $this->scheduler = new AIPS_Scheduler();
    }

    public function test_atomic_update_success() {
        // Create a schedule
        $id = $this->repository->create(array(
            'template_id' => 1,
            'frequency' => 'daily',
            'next_run' => '2024-01-01 12:00:00',
            'is_active' => 1
        ));

        $new_time = '2024-01-02 12:00:00';

        // Attempt atomic update with CORRECT old value
        $result = $this->repository->update_next_run_atomic($id, $new_time, '2024-01-01 12:00:00');

        $this->assertTrue($result, 'Atomic update should succeed when old value matches');

        // Verify value changed
        $schedule = $this->repository->get_by_id($id);
        $this->assertEquals($new_time, $schedule->next_run);
    }

    public function test_atomic_update_failure() {
        // Create a schedule
        $id = $this->repository->create(array(
            'template_id' => 1,
            'frequency' => 'daily',
            'next_run' => '2024-01-01 12:00:00',
            'is_active' => 1
        ));

        $new_time = '2024-01-02 12:00:00';

        // Attempt atomic update with INCORRECT old value (simulating race condition)
        $result = $this->repository->update_next_run_atomic($id, $new_time, '2024-01-01 12:00:01');

        $this->assertFalse($result, 'Atomic update should fail when old value does not match');

        // Verify value did NOT change
        $schedule = $this->repository->get_by_id($id);
        $this->assertEquals('2024-01-01 12:00:00', $schedule->next_run);
    }

    public function test_race_condition_prevention() {
        // Simulate two processes trying to update the same schedule
        $id = $this->repository->create(array(
            'template_id' => 1,
            'frequency' => 'daily',
            'next_run' => '2024-01-01 12:00:00',
            'is_active' => 1
        ));

        $process_a_next_run = '2024-01-02 12:00:00';
        $process_b_next_run = '2024-01-02 12:00:00';

        // Both processes read the initial state
        $initial_next_run = '2024-01-01 12:00:00';

        // Process A updates first
        $result_a = $this->repository->update_next_run_atomic($id, $process_a_next_run, $initial_next_run);
        $this->assertTrue($result_a, 'Process A should succeed');

        // Process B tries to update using the OLD initial state
        $result_b = $this->repository->update_next_run_atomic($id, $process_b_next_run, $initial_next_run);
        $this->assertFalse($result_b, 'Process B should fail because state changed');
    }
}
