<?php
/**
 * Tests for the post cluster, orphan, pillar and content gap methods of
 * AIPS_Similarity_Evaluator (consolidated from AIPS_Post_Clusters_Service).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Similarity_Evaluator_Clusters extends WP_UnitTestCase {

	/** @var int[] */
	private $post_ids = array();

	/** @var AIPS_Embeddings_Repository|\PHPUnit\Framework\MockObject\MockObject */
	private $embeddings_repo;

	/** @var AIPS_Relationships_Repository|\PHPUnit\Framework\MockObject\MockObject */
	private $relationships_repo;

	/** @var AIPS_AI_Service_Interface|\PHPUnit\Framework\MockObject\MockObject */
	private $ai_service;

	/** @var AIPS_Similarity_Evaluator */
	private $evaluator;

	public function setUp(): void {
		parent::setUp();

		delete_option('aips_post_clusters');

		// Posts A and B are near-identical, C is unrelated.
		$vectors = array(
			array(1.0, 0.0, 0.0),
			array(0.99, 0.01, 0.0),
			array(0.0, 0.0, 1.0),
		);

		$rows = array();
		foreach ($vectors as $i => $vec) {
			$post_id          = $this->factory->post->create(array('post_title' => 'Cluster Post ' . $i));
			$this->post_ids[] = $post_id;
			$rows[]           = (object) array(
				'object_id' => $post_id,
				'post_type' => 'post',
				'embedding' => $vec,
			);
		}

		$this->embeddings_repo = $this->getMockBuilder(AIPS_Embeddings_Repository::class)
			->disableOriginalConstructor()
			->onlyMethods(array('get_all_for_similarity', 'decode_embedding'))
			->getMock();
		$this->embeddings_repo->method('get_all_for_similarity')->willReturn($rows);
		$this->embeddings_repo->method('decode_embedding')->willReturnCallback(function ($raw) {
			return $raw;
		});

		$this->relationships_repo = $this->getMockBuilder(AIPS_Relationships_Repository::class)
			->disableOriginalConstructor()
			->onlyMethods(array('sync_for_source', 'count_incoming_internal_links'))
			->getMock();

		$this->ai_service = $this->createMock(AIPS_AI_Service_Interface::class);

		$this->evaluator = new AIPS_Similarity_Evaluator(
			AIPS_Config::get_instance(),
			$this->embeddings_repo,
			null,
			$this->relationships_repo,
			$this->ai_service
		);
	}

	public function tearDown(): void {
		delete_option('aips_post_clusters');
		parent::tearDown();
	}

	public function test_detect_post_clusters_groups_similar_posts_and_persists() {
		$this->relationships_repo->expects($this->once())
			->method('sync_for_source')
			->with('post', $this->anything(), $this->countOf(1), 'pillar_spoke');

		$clusters = $this->evaluator->detect_post_clusters(0.9);

		$this->assertCount(1, $clusters);
		$cluster = $clusters['cluster_1'];
		$this->assertSame(2, $cluster['post_count']);
		$this->assertEqualsCanonicalizing(array($this->post_ids[0], $this->post_ids[1]), $cluster['member_ids']);
		$this->assertContains($cluster['pillar_id'], $cluster['member_ids']);
		$this->assertNotContains($this->post_ids[2], $cluster['member_ids']);

		$saved = get_option('aips_post_clusters');
		$this->assertArrayHasKey('cluster_1', $saved);
	}

	public function test_get_orphan_posts_semantic_flags_island() {
		$this->relationships_repo->method('count_incoming_internal_links')->willReturn(3);

		$orphans = $this->evaluator->get_orphan_posts(0.9, 'semantic');
		$ids     = wp_list_pluck($orphans, 'id');

		// Every post has at most one neighbour, so all three are semantic islands.
		$this->assertEqualsCanonicalizing($this->post_ids, $ids);
		$this->assertSame('semantic_island', $orphans[0]['orphan_type']);
	}

	public function test_get_orphan_posts_links_flags_unlinked_only() {
		$unlinked = $this->post_ids[2];
		$this->relationships_repo->method('count_incoming_internal_links')->willReturnCallback(function ($post_id) use ($unlinked) {
			return ((int) $post_id === $unlinked) ? 0 : 2;
		});

		$orphans = $this->evaluator->get_orphan_posts(0.9, 'links');

		$this->assertCount(1, $orphans);
		$this->assertSame($unlinked, $orphans[0]['id']);
		$this->assertSame('unlinked_post', $orphans[0]['orphan_type']);
	}

	public function test_set_pillar_post_and_rename_update_saved_cluster() {
		$this->evaluator->detect_post_clusters(0.9);

		$this->assertTrue($this->evaluator->set_pillar_post('cluster_1', $this->post_ids[1]));
		$this->assertTrue($this->evaluator->rename_post_cluster('cluster_1', 'Renamed Cluster'));

		$saved = get_option('aips_post_clusters');
		$this->assertSame($this->post_ids[1], $saved['cluster_1']['pillar_id']);
		$this->assertSame('Renamed Cluster', $saved['cluster_1']['name']);
	}

	public function test_set_pillar_and_rename_return_false_for_unknown_cluster() {
		$this->assertFalse($this->evaluator->set_pillar_post('cluster_missing', $this->post_ids[0]));
		$this->assertFalse($this->evaluator->rename_post_cluster('cluster_missing', 'Nope'));
	}

	public function test_generate_gap_suggestions_parses_ai_json() {
		$this->ai_service->expects($this->once())
			->method('generate_text')
			->willReturn("```json\n[{\"title\":\"Gap A\",\"rationale\":\"R\",\"brief\":\"B\"}]\n```");

		$suggestions = $this->evaluator->generate_gap_suggestions($this->post_ids[0]);

		$this->assertIsArray($suggestions);
		$this->assertSame('Gap A', $suggestions[0]['title']);
	}

	public function test_generate_gap_suggestions_propagates_ai_error() {
		$this->ai_service->method('generate_text')->willReturn(new WP_Error('ai_unavailable', 'down'));

		$result = $this->evaluator->generate_gap_suggestions($this->post_ids[0]);

		$this->assertWPError($result);
	}

	public function test_generate_gap_suggestions_missing_post_returns_error() {
		$this->assertWPError($this->evaluator->generate_gap_suggestions(999999));
	}
}
