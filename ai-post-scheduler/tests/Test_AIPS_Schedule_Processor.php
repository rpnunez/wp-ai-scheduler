<?php

class Test_AIPS_Schedule_Processor extends WP_UnitTestCase {
    private $processor;
    private $generator_mock;
    private $repository_mock;
    private $template_repository_mock;
    private $runner_mock;
    private $logger_mock;
    private $result_handler_mock;
    private $batch_queue_mock;
    private $template_type_selector_mock;

    public function setUp(): void {
        parent::setUp();

        if (!class_exists('AIPS_Schedule_Processor')) {
            $this->markTestSkipped('AIPS_Schedule_Processor class not found.');
        }

        $this->generator_mock = $this->createMock(AIPS_Generator::class);
        $this->repository_mock = $this->createMock(AIPS_Schedule_Repository_Interface::class);
        $this->template_repository_mock = $this->createMock(AIPS_Template_Repository::class);

        // Mock runner
        $this->runner_mock = $this->getMockBuilder(\stdClass::class)->addMethods(['run'])->getMock();

        $this->logger_mock = $this->createMock(AIPS_Logger_Interface::class);

        $this->result_handler_mock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_or_create_schedule_history', 'handle_post_execution_cleanup', 'handle_execution_failure', 'handle_execution_success'])
            ->getMock();

        $this->batch_queue_mock = $this->createMock(AIPS_Batch_Queue_Service::class);

        $this->template_type_selector_mock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['select_structure', 'invalidate_count_cache'])
            ->getMock();

        $this->processor = new AIPS_Schedule_Processor(
            $this->repository_mock,
            $this->template_repository_mock,
            $this->generator_mock,
            null, // history service
            $this->template_type_selector_mock,
            $this->logger_mock,
            $this->runner_mock,
            $this->result_handler_mock
        );

        $this->processor->set_generator($this->generator_mock);
        $this->processor->set_repository($this->repository_mock);
        $this->processor->set_template_repository($this->template_repository_mock);
        $this->processor->set_runner($this->runner_mock);
        $this->processor->set_batch_queue_service($this->batch_queue_mock);
    }

    public function test_process_single_schedule_success() {
        $schedule = (object)[
            'id' => 1,
            'schedule_id' => 1,
            'template_id' => 10,
            'name' => 'Test Schedule',
            'frequency' => 'daily',
            'topic' => 'Test Topic',
            'batch_progress' => ''
        ];

        $template = (object)[
            'id' => 10,
            'name' => 'Test Template',
            'post_quantity' => 1,
        ];

        $this->repository_mock->expects($this->once())
            ->method('get_by_id')
            ->with(1)
            ->willReturn($schedule);

        $this->template_repository_mock->expects($this->exactly(2))
            ->method('get_by_id')
            ->with(10)
            ->willReturn($template);

        $this->batch_queue_mock->expects($this->once())
            ->method('needs_batch_queue')
            ->with(1)
            ->willReturn(false);

        $this->template_type_selector_mock->expects($this->once())
            ->method('select_structure')
            ->willReturn(5);

        $this->generator_mock->expects($this->once())
            ->method('generate_post')
            ->willReturn(123);

        $this->repository_mock->expects($this->once())
            ->method('clear_batch_progress');

        $result = $this->processor->process_single_schedule(1);

        $this->assertEquals([123], $result);
    }

    public function test_process_single_schedule_batch_queue_dispatch() {
        $schedule = (object)[
            'id' => 2,
            'schedule_id' => 2,
            'template_id' => 20,
            'name' => 'Test Large Schedule',
            'frequency' => 'daily',
            'topic' => 'Large Topic',
            'batch_progress' => ''
        ];

        $template = (object)[
            'id' => 20,
            'name' => 'Test Large Template',
            'post_quantity' => 50,
        ];

        $this->repository_mock->expects($this->once())
            ->method('get_by_id')
            ->with(2)
            ->willReturn($schedule);

        $this->template_repository_mock->expects($this->exactly(2))
            ->method('get_by_id')
            ->with(20)
            ->willReturn($template);

        $this->batch_queue_mock->expects($this->once())
            ->method('needs_batch_queue')
            ->with(50)
            ->willReturn(true);

        $this->batch_queue_mock->expects($this->once())
            ->method('dispatch')
            ->willReturn([
                'num_batches' => 5,
                'scheduled_batches' => 5,
                'posts_per_batch' => 10,
                'window_seconds' => 3600
            ]);

        $this->template_type_selector_mock->expects($this->once())
            ->method('select_structure')
            ->willReturn(5);

        $this->generator_mock->expects($this->never())
            ->method('generate_post');

        $result = $this->processor->process_single_schedule(2);

        $this->assertNull($result);
    }

    public function test_process_single_schedule_not_found() {
        $this->repository_mock->expects($this->once())
            ->method('get_by_id')
            ->with(99)
            ->willReturn(null);

        $result = $this->processor->process_single_schedule(99);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('schedule_not_found', $result->get_error_code());
    }
}
