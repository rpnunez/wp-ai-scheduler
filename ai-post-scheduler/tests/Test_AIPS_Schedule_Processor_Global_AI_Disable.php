<?php
/**
 * Tests global AI-disable behavior in the schedule processor.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Processor_Global_AI_Disable extends WP_UnitTestCase {
	public function tearDown(): void {
		delete_option('aips_prevent_scheduled_ai_generation');
		AIPS_Config::get_instance()->flush_option_cache();
		parent::tearDown();
	}

	public function test_process_single_schedule_returns_ai_calls_disabled_before_generation() {
		update_option('aips_prevent_scheduled_ai_generation', 1);
		AIPS_Config::get_instance()->flush_option_cache();

		$schedule = (object) array(
			'id'          => 101,
			'schedule_id' => 101,
			'template_id' => 202,
			'frequency'   => 'once',
			'next_run'    => 1700000000,
			'topic'       => 'Blocked topic',
		);
		$template = (object) array(
			'id'            => 202,
			'name'          => 'Blocked Template',
			'post_quantity' => 2,
		);

		$repository = $this->createMock(AIPS_Schedule_Repository_Interface::class);
		$repository->expects($this->once())
			->method('get_by_id')
			->with(101)
			->willReturn($schedule);
		$repository->expects($this->never())
			->method('update');

		$template_repository = $this->getMockBuilder(AIPS_Template_Repository::class)
			->disableOriginalConstructor()
			->onlyMethods(array('get_by_id'))
			->getMock();
		$template_repository->expects($this->once())
			->method('get_by_id')
			->with(202)
			->willReturn($template);

		$generator = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array('generate_post'))
			->getMock();
		$generator->expects($this->never())
			->method('generate_post');

		$history_service = $this->createMock(AIPS_History_Service_Interface::class);
		$template_type_selector = $this->getMockBuilder(AIPS_Template_Type_Selector::class)
			->disableOriginalConstructor()
			->onlyMethods(array('select_structure'))
			->getMock();
		$template_type_selector->expects($this->once())
			->method('select_structure')
			->willReturn(0);

		$logger = $this->createMock(AIPS_Logger_Interface::class);
		$logger->method('log');

		$runner = $this->getMockBuilder(AIPS_Generation_Execution_Runner::class)
			->disableOriginalConstructor()
			->getMock();

		$history = $this->createMock(AIPS_History_Container::class);
		$history->expects($this->once())
			->method('record')
			->with(
				'activity',
				'Manual execution of schedule "Blocked Template" started',
				$this->anything(),
				null,
				$this->anything()
			);

		$terminated_error = new WP_Error('ai_calls_disabled', 'Schedule was terminated early due to Prevent Scheduled AI Generation being enabled.');
		$result_handler = $this->getMockBuilder(AIPS_Schedule_Result_Handler::class)
			->disableOriginalConstructor()
			->onlyMethods(array('get_or_create_schedule_history', 'handle_execution_terminated_by_setting'))
			->getMock();
		$result_handler->expects($this->once())
			->method('get_or_create_schedule_history')
			->with(101)
			->willReturn($history);
		$result_handler->expects($this->once())
			->method('handle_execution_terminated_by_setting')
			->with(
				$this->callback(function($run_schedule) {
					return isset($run_schedule->schedule_id, $run_schedule->template_id) &&
						$run_schedule->schedule_id === 101 &&
						$run_schedule->template_id === 202;
				}),
				$history,
				true,
				'Prevent Scheduled AI Generation',
				$this->callback(function($options) {
					return isset($options['restore_next_run'], $options['total'], $options['completed']) &&
						(int) $options['restore_next_run'] === 1700000000 &&
						(int) $options['total'] === 2 &&
						(int) $options['completed'] === 0;
				})
			)
			->willReturn($terminated_error);

		$processor = new AIPS_Schedule_Processor(
			$repository,
			$template_repository,
			$generator,
			$history_service,
			$template_type_selector,
			$logger,
			$runner,
			$result_handler
		);

		$result = $processor->process_single_schedule(101, null, true);

		$this->assertWPError($result);
		$this->assertSame('ai_calls_disabled', $result->get_error_code());
	}
}
