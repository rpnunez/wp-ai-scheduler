<?php
/**
 * Tests for batch queue timestamp normalization.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Batch_Queue_Service_Test extends WP_UnitTestCase {

	/** @var AIPS_Batch_Queue_Service */
	private $service;

	public function setUp(): void {
		parent::setUp();
		$this->service = new AIPS_Batch_Queue_Service();
	}

	public function tearDown(): void {
		wp_clear_scheduled_hook(AIPS_Batch_Queue_Service::HOOK);
		parent::tearDown();
	}

	/**
	 * Past base timestamps are normalized so queued jobs do not start in the past.
	 */
	public function test_dispatch_normalizes_past_base_timestamp_to_now(): void {
		$schedule_id   = 314;
		$post_quantity = 4;
		$past_ts       = mktime(0, 0, 0, 1, 1, 2000);
		$before_now    = time();

		$this->service->dispatch($schedule_id, $post_quantity, $past_ts, 'past-base');

		$scheduled = _get_cron_array();
		$first_ts  = null;

		foreach ($scheduled as $ts => $hooks) {
			if (!isset($hooks[AIPS_Batch_Queue_Service::HOOK])) {
				continue;
			}

			foreach ($hooks[AIPS_Batch_Queue_Service::HOOK] as $job) {
				if ($job['args'][0] === $schedule_id && $job['args'][1] === 0) {
					$first_ts = $ts;
					break 2;
				}
			}
		}

		$this->assertNotNull($first_ts, 'First normalized batch event not found.');
		$this->assertGreaterThanOrEqual($before_now, $first_ts, 'First batch should not be scheduled in the past.');
	}
}
