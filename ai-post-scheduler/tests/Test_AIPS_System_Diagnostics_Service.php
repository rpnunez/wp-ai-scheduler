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

		$notifications_repository = isset($overrides['notifications_repository'])
			? $overrides['notifications_repository']
			: $this->mock_notifications_repository();

		$date_time_db_repair = isset($overrides['date_time_db_repair'])
			? $overrides['date_time_db_repair']
			: $this->mock_date_time_db_repair();

		$db_prune_service = isset($overrides['db_prune_service'])
			? $overrides['db_prune_service']
			: null;

		$stress_test_service = isset($overrides['stress_test_service'])
			? $overrides['stress_test_service']
			: null;

		// clear_embeddings_cache() builds its own embeddings repository and
		// deletes transients via $wpdb, so stub it to keep the service isolated.
		$service = $this->getMockBuilder('AIPS_System_Diagnostics_Service')
			->setConstructorArgs(array(
				$history_repository,
				$bulk_batch_job_store,
				$resilience_service,
				$notifications_repository,
				$date_time_db_repair,
				$db_prune_service,
				$stress_test_service,
			))
			->onlyMethods(array('clear_embeddings_cache'))
			->getMock();
		$service->method('clear_embeddings_cache')->willReturn(array(
			'success' => true,
			'message' => 'Embeddings cache cleared.',
		));

		return $service;
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

	public function test_refresh_system_runs_all_non_destructive_steps_in_order() {
		$service = $this->make_service();

		$result = $service->refresh_system();

		$expected_order = array(
			'cleanup_notifications',
			'notifications_hygiene',
			'cleanup_stale_jobs_cache',
			'clear_partial_generations',
			'repair_campaign_data',
			'repair_datetime',
			'reschedule_missed_cron',
			'retry_failed_slices',
			'reset_resilience',
			'rebuild_caches',
			'clear_embeddings_cache',
		);

		$this->assertSame($expected_order, wp_list_pluck($result['steps'], 'step'));
		$this->assertSame(11, $result['succeeded']);
		$this->assertSame(0, $result['failed']);
		$this->assertStringContainsString('11 of 11', $result['message']);

		foreach ($result['steps'] as $step) {
			$this->assertTrue($step['success'], $step['step']);
			$this->assertNotSame('', $step['message'], $step['step']);
		}
	}

	public function test_refresh_task_groups_omit_removed_subsystems() {
		$service = $this->make_service();
		$groups  = $service->get_refresh_task_groups();
		$steps   = array();

		foreach ($groups as $group) {
			foreach ($group['tasks'] as $task) {
				$steps[] = $task['step'];
			}
		}

		$this->assertNotContains('prune_telemetry', $steps);
		$this->assertNotContains('cache_maintenance', $steps);
		$this->assertNotContains('purge_telemetry', $steps);
	}

	public function test_refresh_system_runs_only_selected_steps_in_bundle_order() {
		$service = $this->make_service();

		$result = $service->refresh_system(array(
			'rebuild_caches',
			'cleanup_notifications',
			'repair_campaign_data',
		));

		$this->assertSame(
			array('cleanup_notifications', 'repair_campaign_data', 'rebuild_caches'),
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
		$notifications = $this->getMockBuilder('AIPS_Notifications_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('cleanup_old'))
			->getMock();
		$notifications->method('cleanup_old')->willThrowException(new RuntimeException('boom'));

		$service = $this->make_service(array('notifications_repository' => $notifications));

		$result = $service->refresh_system();

		$this->assertCount(11, $result['steps']);
		$this->assertSame(10, $result['succeeded']);
		$this->assertSame(1, $result['failed']);

		$first_step = $result['steps'][0];
		$this->assertSame('cleanup_notifications', $first_step['step']);
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
}
