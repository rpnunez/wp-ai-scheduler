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
        add_filter('aips_large_batch_threshold', function() { return 10; });
        $this->scheduler = new AIPS_Scheduler();
    }

    public function tearDown(): void {
        remove_all_filters('aips_large_batch_threshold');
        parent::tearDown();
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

    /**
     * Verifies that manual schedule execution passes AIPS_Generation_Trigger::METHOD_MANUAL
     * when creating its schedule_lifecycle container.
     */
    public function test_run_schedule_now_passes_manual_trigger_method_to_result_handler() {
        $schedule = (object) array(
            'id'          => 123,
            'template_id' => 456,
            'topic'       => 'Manual Trigger Topic',
            'frequency'   => 'daily',
            'next_run'    => '2023-01-01 00:00:00',
            'is_active'   => 1,
        );

        $template = (object) array(
            'id'            => 456,
            'name'          => 'Manual Trigger Template',
            'post_quantity' => 1,
            'is_active'     => 1,
        );

        $this->build_scheduler_with_mocks($schedule, $template, 789, 1);

        $mock_history = $this->getMockBuilder('AIPS_History_Container')
            ->disableOriginalConstructor()
            ->onlyMethods(array('record', 'complete_success', 'complete_failure', 'get_id'))
            ->getMock();
        $mock_history->method('get_id')->willReturn(555);

        $mock_result_handler = $this->getMockBuilder('AIPS_Schedule_Result_Handler')
            ->disableOriginalConstructor()
            ->onlyMethods(array('get_or_create_schedule_history', 'handle_execution_success'))
            ->getMock();

        $mock_result_handler->expects($this->once())
            ->method('get_or_create_schedule_history')
            ->with(123, AIPS_Generation_Trigger::METHOD_MANUAL)
            ->willReturn($mock_history);

        $mock_result_handler->expects($this->once())
            ->method('handle_execution_success');

        $processor_ref = new ReflectionProperty($this->scheduler, 'processor');
        $processor_ref->setAccessible(true);
        $processor = $processor_ref->getValue($this->scheduler);

        $handler_ref = new ReflectionProperty($processor, 'result_handler');
        $handler_ref->setAccessible(true);
        $handler_ref->setValue($processor, $mock_result_handler);

        $result = $this->scheduler->run_schedule_now(123, 1, false);
        $this->assertSame(array(789), $result);
    }

    /**
     * Verifies that an uncaught Throwable in process_schedule_batch_slice() closes
     * the slice's schedule_lifecycle container as failed before rethrowing.
     */
    public function test_process_schedule_batch_slice_closes_history_on_exception() {
        $schedule = (object) array(
            'id'          => 123,
            'schedule_id' => 123,
            'template_id' => 456,
            'title'       => 'Batch Slice Schedule',
            'topic'       => 'Batch Topic',
            'frequency'   => 'daily',
            'next_run'    => '2023-01-01 00:00:00',
            'is_active'   => 1,
            'run_state'   => wp_json_encode(array('status' => 'batch_queued')),
        );

        $template = (object) array(
            'id'            => 456,
            'name'          => 'Batch Slice Template',
            'prompt_template' => 'Write about {{topic}}',
            'post_quantity' => 4,
            'is_active'     => 1,
        );

        $mock_schedule_repo = $this->getMockBuilder('AIPS_Schedule_Repository')
            ->onlyMethods(array('get_by_id', 'update_run_state'))
            ->getMock();
        $mock_schedule_repo->method('get_by_id')->with(123)->willReturn($schedule);

        $mock_template_repo = $this->getMockBuilder('AIPS_Template_Repository')
            ->onlyMethods(array('get_by_id'))
            ->getMock();
        $mock_template_repo->method('get_by_id')->with(456)->willReturn($template);

        $mock_generator = $this->getMockBuilder('AIPS_Generator')
            ->disableOriginalConstructor()
            ->onlyMethods(array('generate_post'))
            ->getMock();
        $mock_generator->method('generate_post')
            ->willThrowException(new RuntimeException('Simulated slice crash'));

        $mock_history = $this->getMockBuilder('AIPS_History_Container')
            ->disableOriginalConstructor()
            ->onlyMethods(array('complete_failure'))
            ->getMock();
        $mock_history->expects($this->once())
            ->method('complete_failure')
            ->with(
                'Simulated slice crash',
                $this->callback(function ($data) {
                    return isset($data['schedule_id'], $data['exception_class'])
                        && 123 === $data['schedule_id']
                        && 'RuntimeException' === $data['exception_class'];
                })
            );

        $mock_result_handler = $this->getMockBuilder('AIPS_Schedule_Result_Handler')
            ->disableOriginalConstructor()
            ->onlyMethods(array('get_or_create_schedule_history'))
            ->getMock();
        $mock_result_handler->expects($this->once())
            ->method('get_or_create_schedule_history')
            ->with(123, AIPS_Generation_Trigger::METHOD_AUTOMATIC)
            ->willReturn($mock_history);

        $processor = new AIPS_Schedule_Processor(
            $mock_schedule_repo,
            $mock_template_repo,
            $mock_generator,
            null,
            null,
            null,
            null,
            $mock_result_handler
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Simulated slice crash');
        $processor->process_batch_slice(123, 0, 2, 4, '');
    }
}
