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
        $mock_generator = $this->getMockBuilder(\AIPS\Generation\Generator::class)
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
}
