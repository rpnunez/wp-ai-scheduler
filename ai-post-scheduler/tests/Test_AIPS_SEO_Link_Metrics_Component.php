<?php
/**
 * Tests for AIPS_SEO_Link_Metrics_Component and AIPS_Post_List_Columns
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_SEO_Link_Metrics_Component extends WP_UnitTestCase {

	/** @var AIPS_Content_Links_Repository */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->repo = new AIPS_Content_Links_Repository();
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "aips_content_links");
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "aips_content_links");
		parent::tearDown();
	}

	public function test_calculate_equity_tier() {
		$this->assertSame('orphan', AIPS_SEO_Link_Metrics_Component::calculate_equity_tier(0));
		$this->assertSame('low', AIPS_SEO_Link_Metrics_Component::calculate_equity_tier(1));
		$this->assertSame('low', AIPS_SEO_Link_Metrics_Component::calculate_equity_tier(2));
		$this->assertSame('moderate', AIPS_SEO_Link_Metrics_Component::calculate_equity_tier(3));
		$this->assertSame('moderate', AIPS_SEO_Link_Metrics_Component::calculate_equity_tier(5));
		$this->assertSame('high_hub', AIPS_SEO_Link_Metrics_Component::calculate_equity_tier(6));
		$this->assertSame('high_hub', AIPS_SEO_Link_Metrics_Component::calculate_equity_tier(20));
	}

	public function test_render_badge_markup() {
		$orphan_html = AIPS_SEO_Link_Metrics_Component::render_badge(0, 2, true, 'orphan', false);
		$this->assertStringContainsString('aips-link-metrics-badge', $orphan_html);
		$this->assertStringContainsString('aips-pill-orphan', $orphan_html);
		$this->assertStringContainsString('In:', $orphan_html);
		$this->assertStringContainsString('Out:', $orphan_html);

		$hub_html = AIPS_SEO_Link_Metrics_Component::render_badge(7, 3, false, 'high_hub', false);
		$this->assertStringContainsString('aips-link-metrics-badge', $hub_html);
		$this->assertStringContainsString('aips-pill-hub', $hub_html);
		$this->assertStringNotContainsString('aips-pill-orphan', $hub_html);
	}

	public function test_prime_batch_counts_and_post_metrics() {
		$post_a = $this->factory->post->create(array('post_title' => 'Post A', 'post_status' => 'publish'));
		$post_b = $this->factory->post->create(array('post_title' => 'Post B', 'post_status' => 'publish'));

		// A links to B
		$this->repo->sync_post_links($post_a, array(
			array(
				'target_id'   => $post_b,
				'anchor_text' => 'Read B',
				'link_url'    => get_permalink($post_b),
				'post_type'   => 'post',
			),
		));

		AIPS_SEO_Link_Metrics_Component::prime_batch_counts(array($post_a, $post_b));

		$metrics_a = AIPS_SEO_Link_Metrics_Component::get_post_metrics($post_a);
		$this->assertSame(0, $metrics_a['inbound_count']);
		$this->assertSame(1, $metrics_a['outbound_count']);
		$this->assertTrue($metrics_a['is_orphan']);
		$this->assertSame('orphan', $metrics_a['equity_tier']);

		$metrics_b = AIPS_SEO_Link_Metrics_Component::get_post_metrics($post_b);
		$this->assertSame(1, $metrics_b['inbound_count']);
		$this->assertSame(0, $metrics_b['outbound_count']);
		$this->assertFalse($metrics_b['is_orphan']);
		$this->assertSame('low', $metrics_b['equity_tier']);
	}

	public function test_post_list_columns_class() {
		wp_set_current_user($this->factory->user->create(array('role' => 'administrator')));

		$columns_handler = new AIPS_Post_List_Columns();

		$existing_cols = array(
			'cb'         => '<input type="checkbox" />',
			'title'      => 'Title',
			'author'     => 'Author',
			'categories' => 'Categories',
			'date'       => 'Date',
		);

		$cols = $columns_handler->add_column($existing_cols);
		$this->assertArrayHasKey('aips_internal_links', $cols);

		$sortable = $columns_handler->register_sortable_column(array('title' => 'title'));
		$this->assertArrayHasKey('aips_internal_links', $sortable);
	}
}
