<?php
/**
 * Tests for AIPS_Job_Dispatcher
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Job_Dispatcher extends WP_UnitTestCase {

	private $dispatcher;
	private $resilience_service;
	private $logger;
	private $history_service;

	public function setUp(): void {
		parent::setUp();
		$this->resilience_service = $this->getMockBuilder('AIPS_Resilience_Service')
			->getMock();
		$this->logger = $this->getMockBuilder('AIPS_Logger')
			->getMock();
		$this->history_service = $this->getMockBuilder('AIPS_History_Service')
			->getMock();

		$this->dispatcher = new AIPS_Job_Dispatcher(
			$this->resilience_service,
			$this->logger,
			$this->history_service
		);
	}

	public function test_dispatch_schedules_job_successfully() {
		// Mock successful scheduling
		$this->resilience_service->expects($this->once())
			->method('retry_with_backoff')
			->willReturn(true);

		$job = new AIPS_Job_Definition(
			'test_job',
			'test_hook',
			array('arg1', 'arg2'),
			time() + 60
		);

		$result = $this->dispatcher->dispatch($job);

		$this->assertTrue($result);
	}

	public function test_dispatch_detects_duplicates() {
		// Create a job and schedule it manually first
		$hook = 'test_duplicate_hook';
		$args = array('arg1');
		$timestamp = time() + 120;

		// Schedule manually to create duplicate condition
		wp_schedule_single_event($timestamp, $hook, $args);

		// Try to dispatch the same job
		$job = new AIPS_Job_Definition(
			'test_job',
			$hook,
			$args,
			$timestamp
		);

		// Should return true because duplicate was detected (not an error)
		$result = $this->dispatcher->dispatch($job);
		$this->assertTrue($result);
	}

	public function test_dispatch_with_correlation_id() {
		$this->resilience_service->expects($this->once())
			->method('retry_with_backoff')
			->willReturn(true);

		$correlation_id = 'test-correlation-123';
		$job = new AIPS_Job_Definition(
			'test_job',
			'test_hook',
			array('arg1'),
			time() + 60,
			array(),
			$correlation_id
		);

		$result = $this->dispatcher->dispatch($job);
		$this->assertTrue($result);
	}

	public function test_dispatch_with_custom_retry_options() {
		$this->resilience_service->expects($this->once())
			->method('retry_with_backoff')
			->with(
				$this->anything(),
				5, // max_attempts
				2, // initial_delay
				true // use_backoff
			)
			->willReturn(true);

		$job = new AIPS_Job_Definition(
			'test_job',
			'test_hook',
			array('arg1'),
			time() + 60
		);

		$result = $this->dispatcher->dispatch($job, array(
			'max_attempts'   => 5,
			'initial_delay'  => 2,
			'use_backoff'    => true,
		));

		$this->assertTrue($result);
	}

	public function test_is_scheduled_detects_existing_event() {
		$hook = 'test_existing_hook';
		$args = array('arg1', 'arg2');
		$timestamp = time() + 180;

		// Schedule event first
		wp_schedule_single_event($timestamp, $hook, $args);

		$job = new AIPS_Job_Definition(
			'test_job',
			$hook,
			$args,
			$timestamp
		);

		// Use reflection to test protected method
		$reflection = new ReflectionClass($this->dispatcher);
		$method = $reflection->getMethod('is_scheduled');
		$method->setAccessible(true);

		$this->assertTrue($method->invoke($this->dispatcher, $job));
	}

	public function test_is_scheduled_returns_false_for_new_event() {
		$job = new AIPS_Job_Definition(
			'test_job',
			'test_new_hook',
			array('arg1'),
			time() + 240
		);

		// Use reflection to test protected method
		$reflection = new ReflectionClass($this->dispatcher);
		$method = $reflection->getMethod('is_scheduled');
		$method->setAccessible(true);

		$this->assertFalse($method->invoke($this->dispatcher, $job));
	}
}
