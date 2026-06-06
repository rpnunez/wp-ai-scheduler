<?php
/**
 * Tests for existing-post improvement repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Existing_Post_Improvement_Repository extends WP_UnitTestCase {

	/** @var AIPS_Existing_Post_Improvement_Repository */
	private $repository;

	private $schedule_ids = array();

	public function setUp(): void {
		parent::setUp();
		if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
			$this->markTestSkipped('Existing-post improvement repository tests require the full WordPress test library.');
		}

		AIPS_DB_Manager::install_tables();
		$this->repository = new AIPS_Existing_Post_Improvement_Repository();
	}

	public function tearDown(): void {
		foreach ($this->schedule_ids as $schedule_id) {
			$this->repository->delete_schedule($schedule_id);
		}

		parent::tearDown();
	}

	public function test_create_schedule_defaults_include_generated_false() {
		$schedule_id = $this->repository->create_schedule(
			array(
				'title'     => 'My Existing Post Scan',
				'frequency' => 'daily',
				'next_run'  => time() + HOUR_IN_SECONDS,
			)
		);
		$this->schedule_ids[] = $schedule_id;

		$this->assertGreaterThan(0, $schedule_id);
		$schedule = $this->repository->get_schedule_by_id($schedule_id);
		$this->assertNotNull($schedule);
		$this->assertSame('0', (string) $schedule->include_generated_posts);
	}

	public function test_get_due_schedules_returns_active_items_only() {
		$due_id = $this->repository->create_schedule(
			array(
				'title'     => 'Due scan',
				'frequency' => 'daily',
				'status'    => 'active',
				'next_run'  => time() - 10,
			)
		);
		$this->schedule_ids[] = $due_id;

		$inactive_id = $this->repository->create_schedule(
			array(
				'title'     => 'Inactive scan',
				'frequency' => 'daily',
				'status'    => 'inactive',
				'next_run'  => time() - 10,
			)
		);
		$this->schedule_ids[] = $inactive_id;

		$due = $this->repository->get_due_schedules(time(), 10);
		$ids = wp_list_pluck($due, 'id');

		$this->assertContains($due_id, array_map('intval', $ids));
		$this->assertNotContains($inactive_id, array_map('intval', $ids));
	}
}
