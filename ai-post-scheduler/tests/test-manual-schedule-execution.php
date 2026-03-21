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

    /**
     * Helper to build a configured scheduler with mocked dependencies.
     *
     * @param object   $schedule         The schedule object returned by the repository.
     * @param object   $template         The template object returned by the repository.
     * @param int      $expected_post_id The post ID the mock generator should return.
     * @param int|null $expected_qty     The post_quantity expected inside the AIPS_Template_Context.
     * @return AIPS_Scheduler
     */
    private function build_scheduler_with_mocks($schedule, $template, $expected_post_id, $expected_qty = null) {
        // Schedule repository mock
        $mock_schedule_repo = $this->getMockBuilder('AIPS_Schedule_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

        $mock_schedule_repo->method('get_by_id')
            ->with($schedule->id)
            ->willReturn($schedule);

        $this->scheduler->set_repository($mock_schedule_repo);

        // Template repository mock
        $mock_template_repo = $this->getMockBuilder('AIPS_Template_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();

        $mock_template_repo->method('get_by_id')
            ->with($template->id)
            ->willReturn($template);

        $this->scheduler->set_template_repository($mock_template_repo);

        // Generator mock — generate_post receives a single AIPS_Template_Context argument.
        $mock_generator = $this->getMockBuilder('AIPS_Generator')
            ->disableOriginalConstructor()
            ->onlyMethods(array('generate_post'))
            ->getMock();

        $expected_call_count = $expected_qty ? $expected_qty : 1;
        $mock_generator->expects($this->exactly($expected_call_count))
            ->method('generate_post')
            ->with(
                $this->callback(function($context) use ($expected_qty) {
                    if (!($context instanceof AIPS_Template_Context)) {
                        return false;
                    }
                    if ($expected_qty === null) {
                        return true;
                    }
                    return $context->get_template()->post_quantity === $expected_qty;
                })
            )
            ->willReturn($expected_post_id);

        $this->scheduler->set_generator($mock_generator);

        return $this->scheduler;
    }

    /**
     * Verifies that run_schedule_now() uses the template's post_quantity (not a hard-coded 1).
     */
    public function test_run_schedule_now_uses_template_post_quantity() {
        $schedule = (object) array(
            'id' => 123,
            'template_id' => 456,
            'topic' => 'Manual Topic',
            'frequency' => 'daily',
            'next_run' => '2023-01-01 00:00:00',
            'is_active' => 1
        );

        $template = (object) array(
            'id' => 456,
            'name' => 'Manual Test Template',
            'post_quantity' => 5, // Should now be honoured, not overridden to 1
            'is_active' => 1
        );

        $this->build_scheduler_with_mocks($schedule, $template, 123, 5);

        $result = $this->scheduler->run_schedule_now(123);

        if (is_wp_error($result)) {
            $this->fail('Unexpected WP_Error: ' . $result->get_error_message());
        }
        $this->assertEquals(array(123, 123, 123, 123, 123), $result);
    }

    /**
     * Verifies that a caller-supplied quantity_override takes precedence over the template's post_quantity.
     */
    public function test_run_schedule_now_respects_quantity_override() {
        $schedule = (object) array(
            'id' => 123,
            'template_id' => 456,
            'topic' => 'Override Topic',
            'frequency' => 'daily',
            'next_run' => '2023-01-01 00:00:00',
            'is_active' => 1
        );

        $template = (object) array(
            'id' => 456,
            'name' => 'Override Test Template',
            'post_quantity' => 5, // Template says 5; override should win
            'is_active' => 1
        );

        $this->build_scheduler_with_mocks($schedule, $template, 123, 3);

        // Pass an explicit override of 3
        $result = $this->scheduler->run_schedule_now(123, 3);

        if (is_wp_error($result)) {
            $this->fail('Unexpected WP_Error: ' . $result->get_error_message());
        }
        $this->assertEquals(array(123, 123, 123), $result);
    }

    /**
     * Verifies that a schedule_not_found error is returned when the schedule does not exist.
     */
    public function test_run_schedule_now_not_found() {
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
