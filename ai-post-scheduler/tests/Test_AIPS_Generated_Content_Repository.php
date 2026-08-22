<?php
/**
 * Test AIPS_Generated_Content_Repository and related persistence helpers.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generated_Content_Repository extends WP_UnitTestCase {

	/**
	 * @var AIPS_Generated_Content_Repository
	 */
	private $repo;

	protected function setUp(): void {
		parent::setUp();
		$this->repo = AIPS_Generated_Content_Repository::instance();
	}

	public function test_singleton_instance_and_interface_binding() {
		$this->assertInstanceOf(AIPS_Generated_Content_Repository_Interface::class, $this->repo);
		$container = AIPS_Container::get_instance();
		$resolved = $container->make(AIPS_Generated_Content_Repository_Interface::class);
		$this->assertInstanceOf(AIPS_Generated_Content_Repository::class, $resolved);
	}

	public function test_get_content_kpis_returns_expected_keys() {
		$kpis = $this->repo->get_content_kpis();
		$this->assertIsArray($kpis);
		$this->assertArrayHasKey('total_content', $kpis);
		$this->assertArrayHasKey('total_published', $kpis);
		$this->assertArrayHasKey('total_scheduled', $kpis);
		$this->assertArrayHasKey('total_pending_review', $kpis);
		$this->assertArrayHasKey('total_incomplete', $kpis);
		$this->assertArrayHasKey('avg_duration_seconds', $kpis);
	}

	public function test_schedule_repository_reset_circuit_state() {
		$schedule_repo = AIPS_Schedule_Repository::instance();
		$schedule_id = $schedule_repo->create(array(
			'template_id'   => 1,
			'interval_type' => 'daily',
			'circuit_state' => 'open',
		));

		$this->assertGreaterThan(0, $schedule_id);

		$schedule = $schedule_repo->get_by_id($schedule_id);
		$this->assertSame('open', $schedule->circuit_state);

		$reset_result = $schedule_repo->reset_circuit_state($schedule_id);
		$this->assertTrue($reset_result);

		$updated_schedule = $schedule_repo->get_by_id($schedule_id);
		$this->assertSame('closed', $updated_schedule->circuit_state);
	}
}
