<?php
/**
 * Tests for AIPS_Schedule_Batch_Resume_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Batch_Resume_Service extends WP_UnitTestCase {

	private $repository;
	private $batch_service;
	private $result_handler;
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->repository     = $this->createMock(AIPS_Schedule_Repository_Interface::class);
		$this->batch_service  = $this->getMockBuilder(AIPS_Batch_Queue_Service::class)
			->disableOriginalConstructor()
			->onlyMethods(array('dispatch', 'clear_pending_slices'))
			->getMock();
		$this->result_handler = $this->getMockBuilder(AIPS_Schedule_Result_Handler::class)
			->disableOriginalConstructor()
			->onlyMethods(array('get_or_create_schedule_history'))
			->getMock();
		$this->logger         = $this->createMock(AIPS_Logger_Interface::class);
	}

	/**
	 * Build the service with a config stub reporting the given setting state.
	 *
	 * @param bool $prevented Whether generation is currently prevented.
	 * @return AIPS_Schedule_Batch_Resume_Service
	 */
	private function make_service($prevented = false) {
		$config = $this->getMockBuilder(AIPS_Config::class)
			->disableOriginalConstructor()
			->onlyMethods(array('is_scheduled_ai_generation_prevented'))
			->getMock();
		$config->method('is_scheduled_ai_generation_prevented')->willReturn($prevented);

		return new AIPS_Schedule_Batch_Resume_Service(
			$this->repository,
			$this->batch_service,
			$this->result_handler,
			$this->logger,
			$config
		);
	}

	/**
	 * Build a schedule row carrying the given run_state payload.
	 *
	 * @param int   $id        Schedule ID.
	 * @param array $run_state Run state to encode onto the row.
	 * @return object
	 */
	private function schedule_row($id, array $run_state) {
		return (object) array(
			'id'          => $id,
			'template_id' => 7,
			'run_state'   => wp_json_encode($run_state),
		);
	}

	public function test_does_nothing_while_generation_is_still_prevented() {
		// The guard must fire before the repository is even queried, or the newly
		// dispatched slices would terminate again and overwrite their own cursors.
		$this->repository->expects($this->never())->method('get_schedules_with_run_state');
		$this->batch_service->expects($this->never())->method('dispatch');

		$summary = $this->make_service(true)->resume_all();

		$this->assertSame(0, $summary['resumed']);
	}

	public function test_resumes_from_the_cursor_with_the_original_total() {
		$this->repository->method('get_schedules_with_run_state')->willReturn(array(
			$this->schedule_row(42, array(
				'status'         => AIPS_History_Event_Status::TERMINATED,
				'total'          => 30,
				'resume_index'   => 12,
				'resumable'      => true,
				'correlation_id' => 'corr-123',
			)),
		));

		// Stale slices from the interrupted run must be cancelled first, otherwise
		// they would run alongside the replacement slices once run_state clears.
		$this->batch_service->expects($this->once())
			->method('clear_pending_slices')
			->with(42);

		$this->batch_service->expects($this->once())
			->method('dispatch')
			->with(
				42,
				18, // remaining, not the original total
				$this->isType('int'),
				'corr-123',
				array(
					'index_offset'   => 12,
					'total_override' => 30,
				)
			)
			->willReturn(array('scheduled_batches' => 2));

		$this->repository->expects($this->once())
			->method('update_run_state')
			->with(
				42,
				$this->callback(function($state) {
					return $state['status'] === 'batch_processing'
						&& $state['completed'] === 12
						&& $state['total'] === 30
						&& !isset($state['resumable']);
				})
			);

		$history = $this->createMock(AIPS_History_Container::class);
		$history->expects($this->once())
			->method('record')
			->with(
				'activity',
				$this->anything(),
				$this->callback(function($meta) {
					return $meta['event_type'] === AIPS_History_Event_Type::BATCH_RESUMED;
				})
			);
		$this->result_handler->method('get_or_create_schedule_history')->willReturn($history);

		$summary = $this->make_service()->resume_all();

		$this->assertSame(1, $summary['resumed']);
	}

	public function test_ignores_terminated_runs_without_a_resume_cursor() {
		// A run blocked before it ever dispatched is a skipped occurrence, not an
		// interrupted batch, so it carries no resumable flag and must be left alone.
		$this->repository->method('get_schedules_with_run_state')->willReturn(array(
			$this->schedule_row(43, array(
				'status' => AIPS_History_Event_Status::TERMINATED,
				'total'  => 30,
			)),
			$this->schedule_row(44, array(
				'status' => 'batch_processing',
				'total'  => 30,
			)),
		));

		$this->batch_service->expects($this->never())->method('dispatch');
		$this->repository->expects($this->never())->method('update_run_state');

		$summary = $this->make_service()->resume_all();

		$this->assertSame(0, $summary['resumed']);
		$this->assertSame(0, $summary['finished']);
	}

	public function test_clears_the_cursor_when_nothing_remains() {
		$this->repository->method('get_schedules_with_run_state')->willReturn(array(
			$this->schedule_row(45, array(
				'status'       => AIPS_History_Event_Status::TERMINATED,
				'total'        => 20,
				'resume_index' => 20,
				'resumable'    => true,
			)),
		));

		$this->batch_service->expects($this->never())->method('dispatch');

		$this->repository->expects($this->once())
			->method('update_run_state')
			->with(
				45,
				$this->callback(function($state) {
					return $state['status'] === 'success' && !isset($state['resumable'], $state['resume_index']);
				})
			);

		$summary = $this->make_service()->resume_all();

		$this->assertSame(1, $summary['finished']);
		$this->assertSame(0, $summary['resumed']);
	}

	public function test_reports_a_failed_dispatch_without_clearing_the_terminal_state() {
		$this->repository->method('get_schedules_with_run_state')->willReturn(array(
			$this->schedule_row(46, array(
				'status'       => AIPS_History_Event_Status::TERMINATED,
				'total'        => 30,
				'resume_index' => 10,
				'resumable'    => true,
			)),
		));

		$this->batch_service->method('dispatch')
			->willReturn(new WP_Error('dispatch_failed', 'nope'));

		// run_state must stay terminal so the sweep can retry later.
		$this->repository->expects($this->never())->method('update_run_state');

		$summary = $this->make_service()->resume_all();

		$this->assertSame(1, $summary['failed']);
		$this->assertSame(0, $summary['resumed']);
	}
}
