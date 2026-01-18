<?php
/**
 * Extended Test case for Scheduler Logic
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Scheduler_Extended extends WP_UnitTestCase {

    private $scheduler;
    private $template_repo;
    private $schedule_repo;

    public function setUp(): void {
        parent::setUp();
        $this->scheduler = new AIPS_Scheduler();
        // Use real repositories for setup
        $this->template_repo = new AIPS_Template_Repository();
        $this->schedule_repo = new AIPS_Schedule_Repository();
    }

    /**
     * Test 'Claim-First' locking strategy.
     * verify that next_run is updated BEFORE generation.
     */
    public function test_claim_first_locking() {
        // 1. Create a dummy template
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

        // 3. Mock the Repository to spy on 'update' calls
        $mock_repo = $this->getMockBuilder('AIPS_Schedule_Repository')
            ->onlyMethods(array('update', 'update_last_run', 'delete', 'set_active'))
            ->getMock();

        // We expect 'update' to be called to lock the schedule
        // It should update 'next_run' to a future date
        $mock_repo->expects($this->atLeastOnce())
            ->method('update')
            ->will($this->returnCallback(function($id, $data) use ($schedule_id, $original_next_run) {
                if ($id == $schedule_id && isset($data['next_run'])) {
                    // Verify it's pushed forward
                    if (strtotime($data['next_run']) <= strtotime($original_next_run)) {
                        throw new Exception('next_run was not pushed forward!');
                    }
                }
                return true;
            }));

        $this->scheduler->set_repository($mock_repo);

        // 4. Mock the Generator to prevent actual generation
        $mock_generator = $this->getMockBuilder('AIPS_Generator')
            ->disableOriginalConstructor()
            ->onlyMethods(array('generate_post'))
            ->getMock();

        $mock_generator->method('generate_post')->willReturn(123);
        $this->scheduler->set_generator($mock_generator);

        // 5. Run
        $this->scheduler->process_scheduled_posts();
    }
}
