<?php
/**
 * Tests that AIPS_Job_Dispatcher drives the injected transport and keeps
 * policy (dedup, retry, history) above the transport layer.
 *
 * @package AI_Post_Scheduler
 */

/**
 * In-memory transport double for dispatcher tests.
 */
class AIPS_Fake_Transport implements AIPS_Job_Transport_Interface {

	/** @var array[] Recorded scheduled jobs. */
	public $scheduled = array();

	/** @var array[] Recorded next_scheduled lookups. */
	public $lookups = array();

	/** @var bool|WP_Error Result returned by schedule(). */
	public $schedule_result = true;

	/** @var int|false Result returned by next_scheduled(). */
	public $next_result = false;

	public function schedule(AIPS_Job_Definition $job) {
		$this->scheduled[] = $job;
		return $this->schedule_result;
	}

	public function next_scheduled(AIPS_Job_Definition $job) {
		$this->lookups[] = $job;
		return $this->next_result;
	}

	public function unschedule(AIPS_Job_Definition $job): bool {
		return true;
	}

	public function is_available(): bool {
		return true;
	}

	public function get_name(): string {
		return 'fake';
	}
}

class Test_AIPS_Job_Dispatcher_Transport extends WP_UnitTestCase {

	private function make_dispatcher(AIPS_Fake_Transport $transport, $history = null): AIPS_Job_Dispatcher {
		return new AIPS_Job_Dispatcher(
			new AIPS_Resilience_Service(),
			new AIPS_Logger(),
			$history ?: $this->getMockBuilder('AIPS_History_Service')->getMock(),
			$transport
		);
	}

	private function make_job(): AIPS_Job_Definition {
		return new AIPS_Job_Definition('test_job', 'aips_test_dispatch_hook', array(array('x' => 1)), time() + 60);
	}

	public function test_dispatch_schedules_via_transport() {
		$transport = new AIPS_Fake_Transport();
		$dispatcher = $this->make_dispatcher($transport);

		$this->assertTrue($dispatcher->dispatch($this->make_job()));
		$this->assertCount(1, $transport->scheduled);
		$this->assertSame($transport, $dispatcher->get_transport());
	}

	public function test_dispatch_dedups_via_transport_without_scheduling() {
		$transport = new AIPS_Fake_Transport();
		$transport->next_result = time() + 999; // Pretend a matching job already exists.
		$dispatcher = $this->make_dispatcher($transport);

		// Returns true (duplicate is not an error) but never schedules again.
		$this->assertTrue($dispatcher->dispatch($this->make_job()));
		$this->assertCount(0, $transport->scheduled);
	}

	public function test_dispatch_failure_logs_history_with_transport_name() {
		$transport = new AIPS_Fake_Transport();
		$transport->schedule_result = new WP_Error('boom', 'scheduling failed');

		$history_record = $this->getMockBuilder('AIPS_History_Container')
			->disableOriginalConstructor()
			->getMock();
		$history_record->method('get_id')->willReturn(123);

		$captured = array();
		$history_record->method('record')->will($this->returnCallback(
			function ($type, $message, $flags, $error, $context) use (&$captured) {
				$captured = $context;
			}
		));

		$history = $this->getMockBuilder('AIPS_History_Service')->getMock();
		$history->method('create')->willReturn($history_record);

		$dispatcher = $this->make_dispatcher($transport, $history);

		$result = $dispatcher->dispatch($this->make_job(), array('max_attempts' => 1));

		$this->assertFalse($result);
		$this->assertArrayHasKey('transport', $captured);
		$this->assertSame('fake', $captured['transport']);
	}
}
