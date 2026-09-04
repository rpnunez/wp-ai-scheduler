<?php
/**
 * Tests for AIPS_Content_Auditor_Repository
 *
 * @package AI_Post_Scheduler
 */
class Test_AIPS_Content_Auditor_Repository extends WP_UnitTestCase {

	/**
	 * @var AIPS_Content_Auditor_Repository
	 */
	private $repository;

	public function setUp(): void {
		parent::setUp();

		AIPS_DB_Manager::install_tables();
		$this->repository = new AIPS_Content_Auditor_Repository();
	}

	public function test_save_and_get_by_id() {
		$mock_report = array(
			'niche'            => 'DevOps & Cloud',
			'audited_at'       => '2026-08-25 12:00:00',
			'total_posts'      => 45,
			'modules'          => array(
				'gaps'            => array('gap_count' => 3, 'gaps' => array(array('missing_topic' => 'K8s'))),
				'cannibalization' => array('conflict_count' => 1, 'conflicts' => array()),
				'decay'           => array('decay_count' => 5),
				'links'           => array('orphan_count' => 2),
			),
			'health_scorecard' => array(
				'overall_score'         => 82,
				'freshness_score'       => 78,
				'link_score'            => 85,
				'cannibalization_score' => 90,
				'gap_score'             => 75,
				'key_takeaways'         => array('2 orphan posts found.'),
			),
		);

		$insert_id = $this->repository->save($mock_report);

		$this->assertIsInt($insert_id);
		$this->assertGreaterThan(0, $insert_id);

		$retrieved = $this->repository->get_by_id($insert_id);

		$this->assertNotNull($retrieved);
		$this->assertSame($insert_id, $retrieved['id']);
		$this->assertSame('DevOps & Cloud', $retrieved['niche']);
		$this->assertSame(82, $retrieved['overall_score']);
		$this->assertSame(78, $retrieved['freshness_score']);
		$this->assertSame(85, $retrieved['link_score']);
		$this->assertSame(90, $retrieved['cannibalization_score']);
		$this->assertSame(75, $retrieved['gap_score']);
		$this->assertSame(45, $retrieved['total_posts']);
		$this->assertSame(2, $retrieved['orphan_count']);
		$this->assertSame(5, $retrieved['decay_count']);
		$this->assertSame(1, $retrieved['conflict_count']);
		$this->assertSame(3, $retrieved['gap_count']);
		$this->assertIsArray($retrieved['report']);
		$this->assertSame('DevOps & Cloud', $retrieved['report']['niche']);
	}

	public function test_get_latest_and_get_history() {
		$report_a = array(
			'niche'            => 'Web Development',
			'total_posts'      => 10,
			'health_scorecard' => array('overall_score' => 70),
		);
		$report_b = array(
			'niche'            => 'Web Development',
			'total_posts'      => 15,
			'health_scorecard' => array('overall_score' => 85),
		);

		$id_a = $this->repository->save($report_a);
		$id_b = $this->repository->save($report_b);

		$latest = $this->repository->get_latest('Web Development');
		$this->assertNotNull($latest);
		$this->assertSame($id_b, $latest['id']);
		$this->assertSame(85, $latest['overall_score']);

		$history = $this->repository->get_history(10, 0, 'Web Development');
		$this->assertCount(2, $history);
		$this->assertSame($id_b, (int) $history[0]['id']);
		$this->assertSame($id_a, (int) $history[1]['id']);

		$count = $this->repository->count('Web Development');
		$this->assertSame(2, $count);
	}

	public function test_delete_audit() {
		$report = array(
			'niche'            => 'Security',
			'total_posts'      => 5,
			'health_scorecard' => array('overall_score' => 90),
		);

		$id = $this->repository->save($report);
		$this->assertGreaterThan(0, $id);

		$deleted = $this->repository->delete($id);
		$this->assertTrue($deleted);

		$this->assertNull($this->repository->get_by_id($id));
	}
}
