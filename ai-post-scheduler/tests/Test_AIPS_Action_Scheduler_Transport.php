<?php
/**
 * Tests for AIPS_Action_Scheduler_Transport.
 *
 * These tests run in isolated processes and load in-memory Action Scheduler
 * function stubs so the global as_* definitions never affect the rest of the
 * suite (which must resolve to the WP-Cron transport).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Action_Scheduler_Transport extends WP_UnitTestCase {

	private function make_job(string $hook, array $args, int $fire_at, string $group = ''): AIPS_Job_Definition {
		return new AIPS_Job_Definition('test', $hook, $args, $fire_at, array(), '', $group);
	}

	public function test_name_is_action_scheduler() {
		$transport = new AIPS_Action_Scheduler_Transport();
		$this->assertSame('action_scheduler', $transport->get_name());
	}

	/**
	 * When Action Scheduler is not loaded (the default suite environment), the
	 * transport reports unavailable and refuses to schedule.
	 */
	public function test_unavailable_without_action_scheduler() {
		$transport = new AIPS_Action_Scheduler_Transport();
		$this->assertFalse($transport->is_available());

		$job = $this->make_job('aips_test_as_hook', array(array('a' => 1)), time() + 60);
		$result = $transport->schedule($job);
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('action_scheduler_unavailable', $result->get_error_code());
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_schedule_passes_positional_args_and_group() {
		require dirname(__DIR__) . '/tests/stubs/action-scheduler-function-stubs.php';

		$transport = new AIPS_Action_Scheduler_Transport();
		$this->assertTrue($transport->is_available());

		$args = array(array('author_id' => 9, 'batch_size' => 25));
		$fire_at = time() + 120;
		$job = $this->make_job('aips_test_as_hook', $args, $fire_at, AIPS_Job_Groups::EMBEDDINGS);

		$this->assertTrue($transport->schedule($job));

		$store = $GLOBALS['_aips_as_store'];
		$this->assertCount(1, $store);
		// The stored argument list is the job's args, unmodified.
		$this->assertSame($args, $store[0]['args']);
		$this->assertSame(AIPS_Job_Groups::EMBEDDINGS, $store[0]['group']);
		$this->assertSame($fire_at, $store[0]['timestamp']);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_empty_group_normalizes_to_default() {
		require dirname(__DIR__) . '/tests/stubs/action-scheduler-function-stubs.php';

		$transport = new AIPS_Action_Scheduler_Transport();
		$job = $this->make_job('aips_test_as_hook', array(array('x' => 1)), time() + 60);

		$transport->schedule($job);
		$this->assertSame(AIPS_Job_Groups::DEFAULT_GROUP, $GLOBALS['_aips_as_store'][0]['group']);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_next_scheduled_and_unschedule() {
		require dirname(__DIR__) . '/tests/stubs/action-scheduler-function-stubs.php';

		$transport = new AIPS_Action_Scheduler_Transport();
		$args = array(array('author_id' => 4));
		$fire_at = time() + 90;
		$job = $this->make_job('aips_test_as_hook', $args, $fire_at, AIPS_Job_Groups::EMBEDDINGS);

		$this->assertFalse($transport->next_scheduled($job));

		$transport->schedule($job);
		$this->assertSame($fire_at, $transport->next_scheduled($job));

		$this->assertTrue($transport->unschedule($job));
		$this->assertFalse($transport->next_scheduled($job));
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_schedule_failure_returns_wp_error() {
		require dirname(__DIR__) . '/tests/stubs/action-scheduler-function-stubs.php';
		$GLOBALS['_aips_as_fail'] = true;

		$transport = new AIPS_Action_Scheduler_Transport();
		$job = $this->make_job('aips_test_as_hook', array(array('x' => 1)), time() + 60);

		$result = $transport->schedule($job);
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertSame('action_scheduler_schedule_failed', $result->get_error_code());
	}

	/**
	 * A falsy return from as_schedule_single_action must NOT be reported as a
	 * failure when the action was actually stored — otherwise the dispatcher
	 * would retry and schedule a duplicate.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_silent_success_is_not_treated_as_failure() {
		require dirname(__DIR__) . '/tests/stubs/action-scheduler-function-stubs.php';
		$GLOBALS['_aips_as_silent_success'] = true;

		$transport = new AIPS_Action_Scheduler_Transport();
		$job = $this->make_job('aips_test_as_hook', array(array('x' => 1)), time() + 60, AIPS_Job_Groups::EMBEDDINGS);

		$this->assertTrue($transport->schedule($job));
		$this->assertCount(1, $GLOBALS['_aips_as_store']);
	}

	/**
	 * Acceptance: both transports deliver the SAME positional arguments to the
	 * hook callback. WP-Cron fires via do_action_ref_array($hook, $args); Action
	 * Scheduler fires via do_action_ref_array($hook, array_values($args)). Given
	 * the same job, the callback must receive identical arguments.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_callback_argument_parity_between_transports() {
		require dirname(__DIR__) . '/tests/stubs/action-scheduler-function-stubs.php';

		$payload = array('author_id' => 11, 'batch_size' => 30, 'last_processed_id' => 5);
		$args = array($payload); // canonical: single associative payload wrapped once.
		$hook = 'aips_test_parity_hook';
		$fire_at = time() + 60;

		$job = new AIPS_Job_Definition('test', $hook, $args, $fire_at, array(), '', AIPS_Job_Groups::EMBEDDINGS);

		// --- WP-Cron path ---
		$wp_cron = new AIPS_WP_Cron_Transport();
		$wp_cron->schedule($job);
		// WordPress delivers the stored args positionally to the callback.
		$received_wp_cron = null;
		$cb = function () use (&$received_wp_cron) {
			$received_wp_cron = func_get_args();
		};
		add_action($hook, $cb, 10, 10);
		do_action_ref_array($hook, $args);
		remove_action($hook, $cb, 10);

		// --- Action Scheduler path ---
		$as = new AIPS_Action_Scheduler_Transport();
		$as->schedule($job);
		$stored = $GLOBALS['_aips_as_store'][0]['args'];
		$received_as = null;
		$cb2 = function () use (&$received_as) {
			$received_as = func_get_args();
		};
		add_action($hook, $cb2, 10, 10);
		do_action_ref_array($hook, array_values($stored));
		remove_action($hook, $cb2, 10);

		$this->assertSame($received_wp_cron, $received_as);
		$this->assertSame(array($payload), $received_as);
	}
}
