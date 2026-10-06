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
     *
     * process() runs the first due schedule inline and queues the rest as
     * single cron events (AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK),
     * so the second schedule runs when its queued event fires.
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
            'next_run' => AIPS_DateTime::now()->timestamp() - 2 * HOUR_IN_SECONDS, // Overdue, runs first
            'is_active' => 1,
            'topic' => 'Topic 1'
        ));

        $schedule2_id = $this->schedule_repo->create(array(
            'template_id' => $template_id,
            'frequency' => 'daily',
            'next_run' => AIPS_DateTime::now()->timestamp() - HOUR_IN_SECONDS, // Overdue, queued
            'is_active' => 1,
            'topic' => 'Topic 2'
        ));

        global $wpdb;
        if (isset($wpdb) && is_object($wpdb) && property_exists($wpdb, 'get_results_return_val')) {
            $s1 = new stdClass();
            $s1->id = $schedule1_id;
            $s1->schedule_id = $schedule1_id;
            $s1->template_id = $template_id;
            $s1->name = 'Resilience Test Template';
            $s1->prompt_template = 'Write about {{topic}}';
            $s1->post_status = 'publish';
            $s1->post_category = 1;
            $s1->is_active = 1;
            $s1->frequency = 'daily';
            $s1->next_run = date('Y-m-d H:i:s', strtotime('-1 hour'));
            $s1->topic = 'Topic 1';
            $s1->post_quantity = 1;

            $s2 = new stdClass();
            $s2->id = $schedule2_id;
            $s2->schedule_id = $schedule2_id;
            $s2->template_id = $template_id;
            $s2->name = 'Resilience Test Template';
            $s2->prompt_template = 'Write about {{topic}}';
            $s2->post_status = 'publish';
            $s2->post_category = 1;
            $s2->is_active = 1;
            $s2->frequency = 'daily';
            $s2->next_run = date('Y-m-d H:i:s', strtotime('-1 hour'));
            $s2->topic = 'Topic 2';
            $s2->post_quantity = 1;

            $wpdb->get_results_return_val = array($s1, $s2);
        }

        // 3. Mock the Generator
        $mock_generator = $this->getMockBuilder('AIPS_Generator')
            ->disableOriginalConstructor()
            ->onlyMethods(array('generate_post'))
            ->getMock();

        // Expect generate_post to be called twice
        // If the crash in Topic 1 stops execution, this expectation will fail (called once)
        $mock_generator->expects($this->exactly(2))
            ->method('generate_post')
            ->will($this->returnCallback(function($context) {
                if ($context->get_topic() === 'Topic 1') {
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

        // 5. Run the scheduler: Topic 1 crashes inline, Topic 2 is queued.
        wp_unschedule_hook(AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK);
        $this->scheduler->process();

        $queued = wp_next_scheduled(AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK, array((int) $schedule2_id));
        $this->assertNotFalse($queued, 'The second due schedule should be queued, not dropped.');

        // 6. Fire the queued event: the crash must not have blocked it.
        $this->scheduler->process_queued_due_schedule((int) $schedule2_id);
        wp_unschedule_hook(AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK);
    }
}
