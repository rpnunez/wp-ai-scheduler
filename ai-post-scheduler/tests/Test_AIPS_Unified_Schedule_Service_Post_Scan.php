<?php
/**
 * Tests unified schedule integration for existing-post scans.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Unified_Schedule_Service_Post_Scan extends WP_UnitTestCase {

	/** @var AIPS_Post_Improvement_Repository */
	private $repository;

	private $schedule_id = 0;

	public function setUp(): void {
		parent::setUp();
		if (!defined('ABSPATH') || !file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
			$this->markTestSkipped('Unified schedule existing-post scan tests require the full WordPress test library.');
		}

		AIPS_DB_Manager::install_tables();
		$this->repository  = new AIPS_Post_Improvement_Repository();
		$this->schedule_id = $this->repository->create_schedule(
			array(
				'title'     => 'Unified Existing Scan',
				'frequency' => 'daily',
				'status'    => 'active',
				'next_run'  => time() + HOUR_IN_SECONDS,
			)
		);
	}

	public function tearDown(): void {
		if ($this->schedule_id) {
			$this->repository->delete_schedule($this->schedule_id);
		}

		parent::tearDown();
	}

	public function test_unified_schedule_service_lists_existing_scan_type() {
		$service = new AIPS_Unified_Schedule_Service();
		$items   = $service->get_all(AIPS_Unified_Schedule_Service::TYPE_EXISTING_POST_SCAN, false);
		$this->assertNotEmpty($items);

		$found = false;
		foreach ($items as $item) {
			if ((int) $item['id'] === (int) $this->schedule_id && $item['type'] === AIPS_Unified_Schedule_Service::TYPE_EXISTING_POST_SCAN) {
				$found = true;
				break;
			}
		}

		$this->assertTrue($found, 'Expected existing-post scan schedule to appear in unified list.');
	}
}
