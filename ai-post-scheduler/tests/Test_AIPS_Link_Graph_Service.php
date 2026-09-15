<?php
/**
 * Tests for AIPS_Link_Graph_Service
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Link_Graph_Service extends WP_UnitTestCase {

	/** @var AIPS_Content_Links_Repository */
	private $repo;

	/** @var AIPS_Link_Graph_Service */
	private $service;

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->repo    = new AIPS_Content_Links_Repository();
		$this->service = new AIPS_Link_Graph_Service($this->repo);
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "aips_content_links");
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "aips_content_links");
		parent::tearDown();
	}

	public function test_parse_content_for_internal_links() {
		$target_post = $this->factory->post->create(array('post_title' => 'Vector Guide', 'post_status' => 'publish'));
		$target_url  = get_permalink($target_post);

		$html = '<p>Check out our <a href="' . $target_url . '">Vector Guide</a> and also <a href="https://external-domain.com">External</a> and <a href="#heading">Anchor</a>.</p>';

		$links = $this->service->parse_content_for_internal_links($html, 9999);

		$this->assertCount(1, $links);
		$this->assertSame($target_post, $links[0]['target_id']);
		$this->assertSame('Vector Guide', $links[0]['anchor_text']);
	}

	public function test_calculate_graph_depth_bfs() {
		$root = $this->factory->post->create(array('post_title' => 'Root Pillar', 'post_status' => 'publish'));
		$hop1 = $this->factory->post->create(array('post_title' => 'Hop 1', 'post_status' => 'publish'));
		$hop2 = $this->factory->post->create(array('post_title' => 'Hop 2', 'post_status' => 'publish'));
		$hop3 = $this->factory->post->create(array('post_title' => 'Hop 3', 'post_status' => 'publish'));
		$disconnected = $this->factory->post->create(array('post_title' => 'Isolated', 'post_status' => 'publish'));

		// Graph: root -> hop1 -> hop2 -> hop3
		$this->repo->sync_post_links($root, array(array('target_id' => $hop1, 'anchor_text' => 'H1', 'link_url' => get_permalink($hop1), 'post_type' => 'post')));
		$this->repo->sync_post_links($hop1, array(array('target_id' => $hop2, 'anchor_text' => 'H2', 'link_url' => get_permalink($hop2), 'post_type' => 'post')));
		$this->repo->sync_post_links($hop2, array(array('target_id' => $hop3, 'anchor_text' => 'H3', 'link_url' => get_permalink($hop3), 'post_type' => 'post')));

		$depth_root = $this->service->calculate_graph_depth($root, $root);
		$depth_1    = $this->service->calculate_graph_depth($hop1, $root);
		$depth_2    = $this->service->calculate_graph_depth($hop2, $root);
		$depth_3    = $this->service->calculate_graph_depth($hop3, $root);
		$depth_disc = $this->service->calculate_graph_depth($disconnected, $root);

		$this->assertSame(0, $depth_root);
		$this->assertSame(1, $depth_1);
		$this->assertSame(2, $depth_2);
		$this->assertSame(3, $depth_3);
		$this->assertSame(99, $depth_disc);
	}

	public function test_calculate_post_seo_metrics() {
		$source = $this->factory->post->create(array('post_title' => 'Pillar', 'post_status' => 'publish'));
		$target = $this->factory->post->create(array('post_title' => 'Target Child', 'post_status' => 'publish'));

		$this->repo->sync_post_links($source, array(
			array('target_id' => $target, 'anchor_text' => 'Child', 'link_url' => get_permalink($target), 'post_type' => 'post')
		));

		$metrics_target = $this->service->calculate_post_seo_metrics($target);
		$this->assertSame(1, $metrics_target['inbound_count']);
		$this->assertSame(0, $metrics_target['outbound_count']);
		$this->assertFalse($metrics_target['is_orphan']);

		$metrics_source = $this->service->calculate_post_seo_metrics($source);
		$this->assertSame(0, $metrics_source['inbound_count']);
		$this->assertSame(1, $metrics_source['outbound_count']);
		$this->assertTrue($metrics_source['is_orphan']);
		$this->assertSame('orphan', $metrics_source['equity_tier']);
	}

	public function test_get_cross_link_relationship() {
		$post_a = $this->factory->post->create(array('post_title' => 'A', 'post_status' => 'publish'));
		$post_b = $this->factory->post->create(array('post_title' => 'B', 'post_status' => 'publish'));
		$post_c = $this->factory->post->create(array('post_title' => 'C', 'post_status' => 'publish'));
		$hub    = $this->factory->post->create(array('post_title' => 'Hub', 'post_status' => 'publish'));

		// Direct: A -> B
		// 2-Hop: A -> B -> C
		// Co-citation: Hub -> A and Hub -> C
		$this->repo->sync_post_links($post_a, array(array('target_id' => $post_b, 'anchor_text' => 'B', 'link_url' => get_permalink($post_b), 'post_type' => 'post')));
		$this->repo->sync_post_links($post_b, array(array('target_id' => $post_c, 'anchor_text' => 'C', 'link_url' => get_permalink($post_c), 'post_type' => 'post')));
		$this->repo->sync_post_links($hub, array(
			array('target_id' => $post_a, 'anchor_text' => 'A', 'link_url' => get_permalink($post_a), 'post_type' => 'post'),
			array('target_id' => $post_c, 'anchor_text' => 'C', 'link_url' => get_permalink($post_c), 'post_type' => 'post')
		));

		$rel_ab = $this->service->get_cross_link_relationship($post_a, $post_b);
		$this->assertTrue($rel_ab['is_direct']);
		$this->assertSame(1, $rel_ab['hop_distance']);

		$rel_ac = $this->service->get_cross_link_relationship($post_a, $post_c);
		$this->assertFalse($rel_ac['is_direct']);
		$this->assertTrue($rel_ac['is_two_hop']);
		$this->assertTrue($rel_ac['is_co_cited']);
		$this->assertContains($hub, $rel_ac['co_cited_by']);
	}

	public function test_get_all_graph_depths() {
		$root = $this->factory->post->create(array('post_title' => 'R', 'post_status' => 'publish'));
		$hop1 = $this->factory->post->create(array('post_title' => 'H1', 'post_status' => 'publish'));
		$hop2 = $this->factory->post->create(array('post_title' => 'H2', 'post_status' => 'publish'));
		$disc = $this->factory->post->create(array('post_title' => 'D', 'post_status' => 'publish'));

		$this->repo->sync_post_links($root, array(array('target_id' => $hop1, 'anchor_text' => 'H1', 'link_url' => get_permalink($hop1), 'post_type' => 'post')));
		$this->repo->sync_post_links($hop1, array(array('target_id' => $hop2, 'anchor_text' => 'H2', 'link_url' => get_permalink($hop2), 'post_type' => 'post')));

		$depths = $this->service->get_all_graph_depths($root);

		$this->assertSame(0, $depths[$root]);
		$this->assertSame(1, $depths[$hop1]);
		$this->assertSame(2, $depths[$hop2]);
		$this->assertArrayNotHasKey($disc, $depths);
	}
}
