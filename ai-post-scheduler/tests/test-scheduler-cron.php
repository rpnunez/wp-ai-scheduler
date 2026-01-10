<?php
/**
 * Test AIPS_Scheduler cron integration.
 *
 * Ensures WP-Cron single events are queued and replaced correctly.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Scheduler_Cron extends WP_UnitTestCase {

	/**
	 * Reset cron queue and stub database prefix.
	 */
	public function setUp(): void {
		parent::setUp();
		global $aips_test_cron, $wpdb;

		$aips_test_cron = array();
		$wpdb = (object) array('prefix' => 'wp_');
	}

	/**
	 * It should schedule a future WP-Cron event for a given schedule.
	 */
	public function test_schedule_single_event_queues_cron_job() {
		global $aips_test_cron;

		$scheduler = new AIPS_Scheduler();
		$future = date('Y-m-d H:i:s', current_time('timestamp') + 3600);

		$scheduler->schedule_single_event(42, $future);

		$this->assertNotEmpty($aips_test_cron);
		$this->assertEquals('aips_run_single_schedule', $aips_test_cron[0]['hook']);
		$this->assertEquals(42, $aips_test_cron[0]['args'][0]);
		$this->assertGreaterThan(current_time('timestamp'), $aips_test_cron[0]['timestamp']);
	}

	/**
	 * It should replace an existing event when the next run changes.
	 */
	public function test_schedule_single_event_replaces_existing_timestamp() {
		global $aips_test_cron;

		$scheduler = new AIPS_Scheduler();

		$first = date('Y-m-d H:i:s', current_time('timestamp') + 120);
		$second = date('Y-m-d H:i:s', current_time('timestamp') + 300);

		$scheduler->schedule_single_event(99, $first);
		$this->assertCount(1, $aips_test_cron);

		$scheduler->schedule_single_event(99, $second);

		$this->assertCount(1, $aips_test_cron);
		$this->assertEquals('aips_run_single_schedule', $aips_test_cron[0]['hook']);
		$this->assertEquals(99, $aips_test_cron[0]['args'][0]);
		$this->assertEquals(strtotime($second), $aips_test_cron[0]['timestamp']);
	}
}
