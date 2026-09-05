<?php
/**
 * Tests for AIPS_Content_Links_Repository
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Content_Links_Repository extends WP_UnitTestCase {

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

	public function test_sync_post_links_and_queries() {
		$post_a = $this->factory->post->create(array('post_title' => 'Post A', 'post_status' => 'publish'));
		$post_b = $this->factory->post->create(array('post_title' => 'Post B', 'post_status' => 'publish'));
		$post_c = $this->factory->post->create(array('post_title' => 'Post C', 'post_status' => 'publish'));

		$links_a = array(
			array(
				'target_id'   => $post_b,
				'anchor_text' => 'Learn Post B',
				'link_url'    => get_permalink($post_b),
				'post_type'   => 'post',
			),
			array(
				'target_id'   => $post_c,
				'anchor_text' => 'Learn Post C',
				'link_url'    => get_permalink($post_c),
				'post_type'   => 'post',
			),
		);

		$this->assertTrue($this->repo->sync_post_links($post_a, $links_a));

		// Verify outbound for Post A
		$outbound_a = $this->repo->get_outbound_links($post_a);
		$this->assertCount(2, $outbound_a);
		$this->assertSame(2, $this->repo->get_outbound_count($post_a));

		// Verify inbound for Post B
		$inbound_b = $this->repo->get_inbound_links($post_b);
		$this->assertCount(1, $inbound_b);
		$this->assertSame($post_a, (int) $inbound_b[0]->source_id);
		$this->assertSame('Learn Post B', $inbound_b[0]->anchor_text);
		$this->assertSame(1, $this->repo->get_inbound_count($post_b));

		// Test batch counts
		$batch = $this->repo->get_inbound_counts(array($post_a, $post_b, $post_c));
		$this->assertSame(0, $batch[$post_a]);
		$this->assertSame(1, $batch[$post_b]);
		$this->assertSame(1, $batch[$post_c]);

		// Post A is an orphan (0 inbounds)
		$orphans = $this->repo->get_orphan_post_ids(array('post'));
		$this->assertContains($post_a, $orphans);
		$this->assertNotContains($post_b, $orphans);

		// Verify directed edges
		$edges = $this->repo->get_all_directed_edges();
		$this->assertCount(2, $edges);

		// Delete Post A and verify cascade
		$this->repo->delete_by_post($post_a);
		$this->assertSame(0, $this->repo->get_outbound_count($post_a));
		$this->assertSame(0, $this->repo->get_inbound_count($post_b));
	}

	/**
	 * Pin the save_post closure's revision/autosave guard.
	 *
	 * WordPress fires save_post for every revision and autosave. Without a guard
	 * the plugin's link-graph closure would call sync_post_links($revision_id, [])
	 * — issuing a DELETE FROM wp_aips_content_links WHERE source_id = <revision_id>
	 * on every keystroke autosave. This test drops a canary row keyed by the
	 * revision ID, fires save_post for the revision, and asserts the canary is
	 * still there — proving the guard short-circuited before the DELETE.
	 */
	public function test_save_post_closure_skips_revisions() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_content_links';

		$parent_id = $this->factory->post->create(array(
			'post_title'  => 'Parent Post',
			'post_status' => 'publish',
		));

		$revision_id = wp_save_post_revision($parent_id);
		$this->assertNotEmpty($revision_id, 'wp_save_post_revision should return a revision ID.');
		$revision_post = get_post($revision_id);
		$this->assertInstanceOf(WP_Post::class, $revision_post);
		$this->assertSame('revision', $revision_post->post_type);

		// Drop a canary row keyed by the revision ID. If the guard fails the
		// closure will call sync_post_links($revision_id, []) which purges every
		// row with source_id = $revision_id — including this one.
		$now = current_time('mysql');
		$wpdb->insert(
			$table,
			array(
				'source_id'   => (int) $revision_id,
				'target_id'   => (int) $parent_id,
				'anchor_text' => 'canary',
				'link_url'    => 'https://example.test/canary',
				'post_type'   => 'revision',
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
		);
		$canary_count = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE source_id = %d",
			$revision_id
		));
		$this->assertSame(1, $canary_count, 'Canary row must exist before firing save_post.');

		// Fire save_post for the revision exactly as WordPress would.
		do_action('save_post', (int) $revision_id, $revision_post, false);

		$canary_survived = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE source_id = %d",
			$revision_id
		));
		$this->assertSame(
			1,
			$canary_survived,
			'save_post closure must skip revisions — sync_post_links($revision_id, []) would have deleted the canary row.'
		);
	}

	public function test_get_outbound_counts() {
		$source1 = $this->factory->post->create(array('post_title' => 'S1', 'post_status' => 'publish'));
		$source2 = $this->factory->post->create(array('post_title' => 'S2', 'post_status' => 'publish'));
		$target1 = $this->factory->post->create(array('post_title' => 'T1', 'post_status' => 'publish'));
		$target2 = $this->factory->post->create(array('post_title' => 'T2', 'post_status' => 'publish'));

		$this->repo->sync_post_links($source1, array(
			array('target_id' => $target1, 'anchor_text' => 'A1', 'link_url' => get_permalink($target1), 'post_type' => 'post'),
			array('target_id' => $target2, 'anchor_text' => 'A2', 'link_url' => get_permalink($target2), 'post_type' => 'post'),
		));

		$this->repo->sync_post_links($source2, array(
			array('target_id' => $target1, 'anchor_text' => 'A3', 'link_url' => get_permalink($target1), 'post_type' => 'post'),
		));

		$counts = $this->repo->get_outbound_counts(array($source1, $source2, 999999));
		$this->assertSame(2, $counts[$source1]);
		$this->assertSame(1, $counts[$source2]);
		$this->assertSame(0, $counts[999999]);
	}
}
