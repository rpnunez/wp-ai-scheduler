<?php
/**
 * Tests for AIPS_Content_Auditor_Engine
 *
 * @package AI_Post_Scheduler
 */
class Test_AIPS_Content_Auditor_Engine extends WP_UnitTestCase {

	/**
	 * @var AIPS_Content_Auditor_Engine
	 */
	private $engine;

	/**
	 * @var PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_ai;

	/**
	 * @var PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_logger;

	public function setUp(): void {
		parent::setUp();

		$this->mock_ai     = $this->createMock(AIPS_AI_Service_Interface::class);
		$this->mock_logger = $this->createMock(AIPS_Logger_Interface::class);

		$this->engine = new AIPS_Content_Auditor_Engine(
			$this->mock_ai,
			$this->mock_logger
		);
	}

	public function test_analyze_topic_gaps_success() {
		$mock_gaps = array(
			array(
				'missing_topic'   => 'Kubernetes Cluster Setup',
				'priority'        => 'High',
				'type'            => 'Pillar',
				'search_intent'   => 'Informational',
				'reason'          => 'Foundational topic missing from DevOps coverage',
				'suggested_angle' => 'Step-by-step production cluster deployment',
			),
		);

		$this->mock_ai->expects($this->once())
			->method('generate_json')
			->willReturn($mock_gaps);

		$fingerprints = array(
			array('id' => 1, 'title' => 'Docker Basics', 'categories' => array('DevOps')),
		);
		$clusters = array(
			'top_keyphrases'        => array('docker' => 5),
			'category_distribution' => array('DevOps' => 1),
		);

		$result = $this->engine->analyze_topic_gaps('DevOps Engineering', $fingerprints, $clusters);

		$this->assertSame(1, $result['gap_count']);
		$this->assertSame('Kubernetes Cluster Setup', $result['gaps'][0]['missing_topic']);
		$this->assertSame('High', $result['gaps'][0]['priority']);
		$this->assertSame('Pillar', $result['gaps'][0]['type']);
	}

	public function test_analyze_cannibalization_with_candidates() {
		$candidates = array(
			array(
				'post_a'             => array('id' => 10, 'title' => 'Docker Tutorial', 'url' => 'https://example.org/docker-1/'),
				'post_b'             => array('id' => 11, 'title' => 'Docker Complete Tutorial', 'url' => 'https://example.org/docker-2/'),
				'similarity_score'   => 0.85,
				'shared_keyphrases'  => array('docker', 'tutorial'),
				'recommended_action' => 'Consolidate or 301 redirect',
			),
		);

		$mock_conflicts = array(
			array(
				'pair_index'            => 1,
				'is_cannibalizing'      => true,
				'severity'              => 'High',
				'primary_post_id'       => 10,
				'secondary_post_id'     => 11,
				'conflict_summary'      => 'Both posts target identical beginner docker search intent',
				'action_recommendation' => 'Consolidate & 301 Redirect',
			),
		);

		$this->mock_ai->expects($this->once())
			->method('generate_json')
			->willReturn($mock_conflicts);

		$result = $this->engine->analyze_cannibalization($candidates);

		$this->assertSame(1, $result['conflict_count']);
		$this->assertSame('action_required', $result['status']);
		$this->assertSame('Consolidate & 301 Redirect', $result['conflicts'][0]['action_recommendation']);
	}

	public function test_analyze_content_decay() {
		$decayed = array(
			array('id' => 20, 'title' => 'Guide to PHP 7.4', 'age_days' => 450, 'word_count' => 1200),
		);
		$thin = array(
			array('id' => 21, 'title' => 'Quick PHP Tip', 'word_count' => 250),
		);

		$mock_recommendations = array(
			array(
				'post_id'               => 20,
				'title'                 => 'Guide to PHP 7.4',
				'urgency'               => 'Urgent',
				'refresh_actions'       => array('Upgrade code samples to PHP 8.2+', 'Add modern types and attributes'),
				'suggested_word_target' => 1500,
				'editorial_notes'       => 'PHP 7.4 is EOL, refresh to PHP 8.x syntax.',
			),
		);

		$this->mock_ai->expects($this->once())
			->method('generate_json')
			->willReturn($mock_recommendations);

		$result = $this->engine->analyze_content_decay($decayed, $thin);

		$this->assertSame(1, $result['decay_count']);
		$this->assertSame(1, $result['thin_count']);
		$this->assertSame(1, count($result['recommendations']));
		$this->assertSame('Urgent', $result['recommendations'][0]['urgency']);
	}

	public function test_analyze_internal_linking_and_orphan_matching() {
		$fingerprints = array(
			array(
				'id'                 => 101,
				'title'              => 'WordPress Plugin Development Guide',
				'categories'         => array('WordPress'),
				'inbound_link_count' => 5,
			),
			array(
				'id'                 => 102,
				'title'              => 'WordPress Hooks Reference',
				'categories'         => array('WordPress'),
				'inbound_link_count' => 0, // Orphan
			),
		);

		$link_graph = array(
			'orphan_post_ids'            => array(102),
			'orphan_count'               => 1,
			'total_internal_connections' => 5,
			'category_silo_health'       => array(
				'WordPress' => array('post_count' => 2, 'inbound_links' => 5, 'orphan_count' => 1),
			),
		);

		$result = $this->engine->analyze_internal_linking($link_graph, $fingerprints);

		$this->assertSame(1, $result['orphan_count']);
		$this->assertCount(1, $result['link_suggestions']);
		$this->assertSame(102, $result['link_suggestions'][0]['orphan_post_id']);
		$this->assertSame(101, $result['link_suggestions'][0]['suggested_source_id']);
	}

	public function test_synthesize_overall_health() {
		$modules = array(
			'cannibalization' => array('conflict_count' => 1),
			'gaps'            => array('gap_count' => 3),
		);
		$clusters = array(
			'total_posts' => 10,
			'decay_count' => 2,
			'thin_count'  => 1,
		);
		$link_graph = array(
			'orphan_count' => 2,
		);

		$scorecard = $this->engine->synthesize_overall_health($modules, $clusters, $link_graph);

		$this->assertArrayHasKey('overall_score', $scorecard);
		$this->assertArrayHasKey('freshness_score', $scorecard);
		$this->assertArrayHasKey('link_score', $scorecard);
		$this->assertArrayHasKey('cannibalization_score', $scorecard);
		$this->assertArrayHasKey('gap_score', $scorecard);
		$this->assertGreaterThan(0, $scorecard['overall_score']);
		$this->assertNotEmpty($scorecard['key_takeaways']);
	}

	public function test_run_full_audit_orchestration() {
		$this->mock_ai->method('generate_json')
			->willReturn(array());

		$fingerprints = array(
			array('id' => 1, 'title' => 'Post 1', 'categories' => array('Tech'), 'inbound_link_count' => 1),
		);
		$link_graph = array(
			'orphan_post_ids'            => array(),
			'orphan_count'               => 0,
			'total_internal_connections' => 1,
			'category_silo_health'       => array(),
		);
		$clusters = array(
			'total_posts'                => 1,
			'decay_count'                => 0,
			'thin_count'                 => 0,
			'cannibalization_candidates' => array(),
		);

		$report = $this->engine->run_full_audit('Technology', $fingerprints, $link_graph, $clusters, array(
			'modules' => array('gaps', 'cannibalization', 'decay', 'links'),
		));

		$this->assertSame('Technology', $report['niche']);
		$this->assertArrayHasKey('health_scorecard', $report);
		$this->assertArrayHasKey('gaps', $report['modules']);
		$this->assertArrayHasKey('cannibalization', $report['modules']);
		$this->assertArrayHasKey('decay', $report['modules']);
		$this->assertArrayHasKey('links', $report['modules']);
	}

	public function test_analyze_cannibalization_empty_candidates() {
		$result = $this->engine->analyze_cannibalization(array());
		$this->assertSame(0, $result['conflict_count']);
		$this->assertSame('fresh', $result['health_status']);
		$this->assertEmpty($result['conflicts']);
	}

	public function test_analyze_content_decay_empty_inputs() {
		$result = $this->engine->analyze_content_decay(array(), array());
		$this->assertSame(0, $result['decay_count']);
		$this->assertSame(0, $result['thin_count']);
		$this->assertSame('fresh', $result['health_status']);
		$this->assertEmpty($result['recommendations']);
	}
}
