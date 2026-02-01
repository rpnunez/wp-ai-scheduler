<?php
/**
 * Test case for Manual Schedule Execution
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Manual_Schedule_Execution extends WP_UnitTestCase {

    private $scheduler;

    public function setUp(): void {
        parent::setUp();
        $this->scheduler = new AIPS_Scheduler();
    }

    public function test_run_schedule_now_success() {
        // 1. Mock Schedule Repository
        $mock_schedule_repo = $this->getMockBuilder('AIPS_Schedule_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

        $schedule = (object) array(
            'id' => 123,
            'template_id' => 456,
            'topic' => 'Manual Topic',
            'frequency' => 'daily',
            'next_run' => '2023-01-01 00:00:00',
            'is_active' => 1
        );

        $mock_schedule_repo->expects($this->once())
            ->method('get_by_id')
            ->with(123)
            ->willReturn($schedule);

        $this->scheduler->set_repository($mock_schedule_repo);

        // 2. Mock Template Repository
        $mock_template_repo = $this->getMockBuilder('AIPS_Template_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

        $template = (object) array(
            'id' => 456,
            'name' => 'Manual Test Template',
            'post_quantity' => 5, // Should be overridden to 1
            'is_active' => 1
        );

        $mock_template_repo->expects($this->once())
            ->method('get_by_id')
            ->with(456)
            ->willReturn($template);

        $this->scheduler->set_template_repository($mock_template_repo);

        // 3. Mock Generator
        $mock_generator = $this->getMockBuilder('AIPS_Generator')
            ->disableOriginalConstructor()
            ->onlyMethods(array('generate_post'))
            ->getMock();

        $mock_generator->expects($this->once())
            ->method('generate_post')
            ->with(
                $this->callback(function($template) {
                    return $template->post_quantity === 1; // Verify quantity is forced to 1
                }),
                $this->anything(),
                $this->equalTo('Manual Topic')
            )
            ->willReturn(123);

        $this->scheduler->set_generator($mock_generator);

        // 4. Run the manual schedule
        $result = $this->scheduler->run_schedule_now(123);

        // 5. Assertions
        if (is_wp_error($result)) {
            echo "Error: " . $result->get_error_message() . "\n";
        }
        $this->assertEquals(123, $result);
    }

    public function test_run_schedule_now_not_found() {
         // Mock to return null
         $mock_schedule_repo = $this->getMockBuilder('AIPS_Schedule_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

         $mock_schedule_repo->expects($this->once())
            ->method('get_by_id')
            ->willReturn(null);

         $this->scheduler->set_repository($mock_schedule_repo);

         $result = $this->scheduler->run_schedule_now(9999);
         $this->assertWPError($result);
         $this->assertEquals('schedule_not_found', $result->get_error_code());
    }
}
