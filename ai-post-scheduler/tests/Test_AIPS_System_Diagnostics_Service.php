<?php
/**
 * Tests for AIPS_System_Diagnostics_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_System_Diagnostics_Service extends WP_UnitTestCase {

	private function make_service(array $overrides = array()) {
		$history_repository = isset($overrides['history_repository'])
			? $overrides['history_repository']
			: $this->mock_history_repository();

		$bulk_batch_job_store = isset($overrides['bulk_batch_job_store'])
			? $overrides['bulk_batch_job_store']
			: $this->mock_bulk_batch_job_store();

		$resilience_service = isset($overrides['resilience_service'])
			? $overrides['resilience_service']
			: $this->mock_resilience_service();

		$cache_monitor_service = isset($overrides['cache_monitor_service'])
			? $overrides['cache_monitor_service']
			: $this->mock_cache_monitor_service();

		$notifications_repository = isset($overrides['notifications_repository'])
			? $overrides['notifications_repository']
			: $this->mock_notifications_repository();

		$date_time_db_repair = isset($overrides['date_time_db_repair'])
			? $overrides['date_time_db_repair']
			: $this->mock_date_time_db_repair();

		return new AIPS_System_Diagnostics_Service(
			$history_repository,
			$bulk_batch_job_store,
			$resilience_service,
			$cache_monitor_service,
			$notifications_repository,
			$date_time_db_repair
		);
	}

	private function mock_history_repository() {
		$mock = $this->getMockBuilder('AIPS_History_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('repair_missing_campaign_ids', 'get_partial_generations'))
			->getMock();
		$mock->method('get_partial_generations')->willReturn(array('items' => array()));
		return $mock;
	}

	private function mock_bulk_batch_job_store() {
		$mock = $this->getMockBuilder('AIPS_Bulk_Batch_Job_Store')
			->disableOriginalConstructor()
			->onlyMethods(array('cleanup_old_jobs'))
			->getMock();
		$mock->method('cleanup_old_jobs')->willReturn(0);
		return $mock;
	}

	private function mock_resilience_service() {
		return $this->getMockBuilder('AIPS_Resilience_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('reset_circuit_breaker', 'reset_rate_limiter'))
			->getMock();
	}

	private function mock_cache_monitor_service() {
		$mock = $this->getMockBuilder('AIPS_Cache_Monitor_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('run_maintenance'))
			->getMock();
		$mock->method('run_maintenance')->willReturn(array(
			'pruned_index'   => 0,
			'pruned_orphans' => 0,
			'pruned_events'  => 0,
		));
		return $mock;
	}

	private function mock_notifications_repository() {
		$mock = $this->getMockBuilder('AIPS_Notifications_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('cleanup_old'))
			->getMock();
		$mock->method('cleanup_old')->willReturn(0);
		return $mock;
	}

	private function mock_date_time_db_repair() {
		$mock = $this->getMockBuilder('AIPS_Date_Time_DB_Repair')
			->disableOriginalConstructor()
			->onlyMethods(array('run'))
			->getMock();
		$mock->method('run')->willReturn(array(
			'converted_columns'        => 0,
			'normalized_null_values'   => 0,
			'fixed_schedule_next_runs' => 0,
			'fixed_author_next_runs'   => 0,
			'fixed_source_next_runs'   => 0,
		));
		return $mock;
	}

	public function test_refresh_system_runs_all_steps_in_order() {
		$service = $this->make_service();

		$result = $service->refresh_system();

		$expected_order = array(
			'cache_maintenance',
			'cleanup_notifications',
			'cleanup_stale_jobs_cache',
			'clear_partial_generations',
			'repair_campaign_data',
			'repair_datetime',
			'reschedule_missed_cron',
			'retry_failed_slices',
			'reset_resilience',
			'rebuild_caches',
		);

		$this->assertSame($expected_order, wp_list_pluck($result['steps'], 'step'));
		$this->assertSame(10, $result['succeeded']);
		$this->assertSame(0, $result['failed']);
		$this->assertStringContainsString('10 of 10', $result['message']);

		foreach ($result['steps'] as $step) {
			$this->assertTrue($step['success'], $step['step']);
			$this->assertNotSame('', $step['message'], $step['step']);
		}
	}

	public function test_refresh_system_runs_only_selected_steps_in_bundle_order() {
		$service = $this->make_service();

		$result = $service->refresh_system(array(
			'rebuild_caches',
			'cache_maintenance',
			'repair_campaign_data',
		));

		$this->assertSame(
			array('cache_maintenance', 'repair_campaign_data', 'rebuild_caches'),
			wp_list_pluck($result['steps'], 'step')
		);
		$this->assertSame(3, $result['succeeded']);
		$this->assertSame(0, $result['failed']);
		$this->assertStringContainsString('3 of 3', $result['message']);
	}

	public function test_refresh_system_requires_selected_steps_when_explicitly_provided() {
		$service = $this->make_service();

		$result = $service->refresh_system(array());

		$this->assertFalse($result['success']);
		$this->assertSame(array(), $result['steps']);
		$this->assertStringContainsString('Select at least one maintenance task', $result['message']);
	}

	public function test_refresh_system_continues_after_a_throwing_step() {
		$cache_monitor = $this->getMockBuilder('AIPS_Cache_Monitor_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('run_maintenance'))
			->getMock();
		$cache_monitor->method('run_maintenance')->willThrowException(new RuntimeException('boom'));

		$service = $this->make_service(array('cache_monitor_service' => $cache_monitor));

		$result = $service->refresh_system();

		$this->assertCount(10, $result['steps']);
		$this->assertSame(9, $result['succeeded']);
		$this->assertSame(1, $result['failed']);

		$first_step = $result['steps'][0];
		$this->assertSame('cache_maintenance', $first_step['step']);
		$this->assertFalse($first_step['success']);
		$this->assertSame('boom', $first_step['message']);

		// The step after the failure still ran.
		$this->assertTrue($result['steps'][1]['success']);
	}

	public function test_cleanup_notifications_uses_thirty_day_default() {
		$notifications_repository = $this->getMockBuilder('AIPS_Notifications_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('cleanup_old'))
			->getMock();
		$notifications_repository->expects($this->once())
			->method('cleanup_old')
			->with(30)
			->willReturn(7);

		$service = $this->make_service(array('notifications_repository' => $notifications_repository));

		$result = $service->cleanup_notifications();

		$this->assertTrue($result['success']);
		$this->assertSame(7, $result['deleted']);
	}

	public function test_reset_resilience_resets_circuit_breaker_and_rate_limiter() {
		$resilience_service = $this->mock_resilience_service();
		$resilience_service->expects($this->once())->method('reset_circuit_breaker');
		$resilience_service->expects($this->once())->method('reset_rate_limiter');

		$service = $this->make_service(array('resilience_service' => $resilience_service));

		$result = $service->reset_resilience();

		$this->assertTrue($result['success']);
	}

	public function test_run_cache_maintenance_reports_pruned_counts() {
		$cache_monitor = $this->getMockBuilder('AIPS_Cache_Monitor_Service')
			->disableOriginalConstructor()
			->onlyMethods(array('run_maintenance'))
			->getMock();
		$cache_monitor->method('run_maintenance')->willReturn(array(
			'pruned_index'   => 3,
			'pruned_orphans' => 2,
			'pruned_events'  => 11,
		));

		$service = $this->make_service(array('cache_monitor_service' => $cache_monitor));

		$result = $service->run_cache_maintenance();

		$this->assertTrue($result['success']);
		$this->assertStringContainsString('3 expired entries', $result['message']);
		$this->assertStringContainsString('2 orphaned index rows', $result['message']);
		$this->assertStringContainsString('11 old events', $result['message']);
	}
}
