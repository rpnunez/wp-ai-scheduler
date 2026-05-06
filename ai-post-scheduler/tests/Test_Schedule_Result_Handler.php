<?php
/**
 * Tests for AIPS_Schedule_Result_Handler
 *
 * @package AI_Post_Scheduler
 */

class Test_Schedule_Result_Handler extends WP_UnitTestCase {

    private $repository_mock;
    private $history_service_mock;
    private $history_repository_mock;
    private $logger_mock;
    private $handler;

    public function setUp(): void {
        parent::setUp();

        $this->repository_mock = $this->createMock(AIPS_Schedule_Repository_Interface::class);
        $this->history_service_mock = $this->createMock(AIPS_History_Service_Interface::class);
        $this->history_repository_mock = $this->createMock(AIPS_History_Repository_Interface::class);
        $this->logger_mock = $this->createMock(AIPS_Logger_Interface::class);

        $this->handler = new AIPS_Schedule_Result_Handler(
            $this->repository_mock,
            $this->history_service_mock,
            $this->history_repository_mock,
            $this->logger_mock
        );
    }

    public function test_handle_post_execution_cleanup_once_success() {
        $schedule = (object) [
            'schedule_id' => 1,
            'frequency' => 'once'
        ];
        $result = [10]; // array of post IDs = success

        $this->repository_mock->expects($this->once())
             ->method('delete')
             ->with(1);

        $this->logger_mock->expects($this->once())
             ->method('log')
             ->with('One-time schedule completed and deleted', 'info', ['schedule_id' => 1]);

        $this->handler->handle_post_execution_cleanup($schedule, $result);
    }

    public function test_handle_post_execution_cleanup_once_failure() {
        $schedule = (object) [
            'schedule_id' => 2,
            'frequency' => 'once',
            'name' => 'Test Schedule',
            'template_id' => 5
        ];
        $result = new WP_Error('err', 'failed');

        // Mock get_by_id to avoid null error in get_or_create_schedule_history
        $this->repository_mock->method('get_by_id')->willReturn($schedule);

        $this->repository_mock->expects($this->once())
             ->method('update'); // we just check it was called for deactivate

        $this->handler->handle_post_execution_cleanup($schedule, $result);
    }

    public function test_handle_post_execution_cleanup_recurring() {
        $schedule = (object) [
            'schedule_id' => 3,
            'frequency' => 'daily'
        ];
        $result = [10];

        $this->repository_mock->expects($this->once())
             ->method('update_last_run');

        $this->handler->handle_post_execution_cleanup($schedule, $result);
    }

    public function test_get_or_create_schedule_history_new() {
        $schedule_id = 4;
        $schedule = (object) [
            'schedule_id' => $schedule_id,
            'schedule_history_id' => 0
        ];

        $this->repository_mock->method('get_by_id')->willReturn($schedule);

        $container_mock = $this->createMock(AIPS_History_Container::class);
        $container_mock->method('get_id')->willReturn(99);

        $this->history_service_mock->expects($this->once())
             ->method('create')
             ->willReturn($container_mock);

        $this->repository_mock->expects($this->once())
             ->method('update')
             ->with($schedule_id, ['schedule_history_id' => 99]);

        $result = $this->handler->get_or_create_schedule_history($schedule_id);
        $this->assertSame($container_mock, $result);
    }
}
