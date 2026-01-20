<?php
/**
 * Test case for Scheduler Resilience
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Scheduler_Resilience extends WP_UnitTestCase {

    private $scheduler;
    private $template_repo;
    private $schedule_repo;

    public function setUp(): void {
        parent::setUp();
        $this->scheduler = new AIPS_Scheduler();
        $this->template_repo = new AIPS_Template_Repository();
        $this->schedule_repo = new AIPS_Schedule_Repository();
    }

    /**
     * Test that an exception in one schedule does not block subsequent schedules.
     */
    public function test_scheduler_resilience_to_exceptions() {
        // 1. Create a dummy template
        $template_id = $this->template_repo->create(array(
            'name' => 'Resilience Test Template',
            'prompt_template' => 'Write about {{topic}}',
            'post_status' => 'publish',
            'post_category' => 1,
            'is_active' => 1
        ));

        // 2. Create two schedules
        $schedule1_id = $this->schedule_repo->create(array(
            'template_id' => $template_id,
            'frequency' => 'daily',
            'next_run' => date('Y-m-d H:i:s', strtotime('-1 hour')), // Overdue
            'is_active' => 1,
            'topic' => 'Topic 1'
        ));

        $schedule2_id = $this->schedule_repo->create(array(
            'template_id' => $template_id,
            'frequency' => 'daily',
            'next_run' => date('Y-m-d H:i:s', strtotime('-1 hour')), // Overdue
            'is_active' => 1,
            'topic' => 'Topic 2'
        ));

        // 3. Mock the Generator
        $mock_generator = $this->getMockBuilder('AIPS_Generator')
            ->disableOriginalConstructor()
            ->onlyMethods(array('generate_post'))
            ->getMock();

        // Expect generate_post to be called twice
        // If the crash in Topic 1 stops execution, this expectation will fail (called once)
        $mock_generator->expects($this->exactly(2))
            ->method('generate_post')
            ->will($this->returnCallback(function($template, $voice, $topic) {
                if ($topic === 'Topic 1') {
                    throw new Exception('Simulated crash!');
                }
                return 123; // Success for Topic 2
            }));

        // 4. Inject the mock generator
        if (method_exists($this->scheduler, 'set_generator')) {
             $this->scheduler->set_generator($mock_generator);
        } else {
            $this->markTestSkipped('set_generator method not implemented yet.');
        }

        // 5. Run the scheduler
        $this->scheduler->process_scheduled_posts();
    }

    /**
     * Test Optimistic Locking (Hunter/Bolt)
     */
    public function test_optimistic_locking() {
        // 1. Create template and schedule
        $template_id = $this->template_repo->create(array(
            'name' => 'Locking Test Template',
            'prompt_template' => 'Write',
            'is_active' => 1
        ));

        $schedule_id = $this->schedule_repo->create(array(
            'template_id' => $template_id,
            'frequency' => 'daily',
            'next_run' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'is_active' => 1
        ));

        // 2. Simulate concurrent modification
        // Get the schedule to get current 'next_run' (simulating what process_scheduled_posts does)
        $schedule = $this->schedule_repo->get_by_id($schedule_id);
        $original_next_run = $schedule->next_run;

        // Simulate another process updating it FIRST
        $other_process_new_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->schedule_repo->update_next_run($schedule_id, $other_process_new_time);

        // 3. Try to update using optimistic locking with OLD timestamp
        $my_new_time = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $success = $this->schedule_repo->update_next_run_conditional($schedule_id, $my_new_time, $original_next_run);

        // 4. Assert failure
        $this->assertFalse($success, 'Optimistic locking should fail if next_run changed');

        // 5. Verify value wasn't changed by us
        $fresh_schedule = $this->schedule_repo->get_by_id($schedule_id);
        $this->assertEquals($other_process_new_time, $fresh_schedule->next_run);
    }
}
