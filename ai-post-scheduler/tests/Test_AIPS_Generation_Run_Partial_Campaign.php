<?php
/**
 * Tests for the generation-run helper in the Partial Generations tab, campaign
 * recent posts and the history post_type backfill.
 *
 * Event containers and indexing rows carry the same post_id as the run that
 * generated a post and are always newer than it, so a bare MAX(id) per post
 * resolves to them instead of the run.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generation_Run_Partial_Campaign extends WP_UnitTestCase {

	/** @var AIPS_History_Repository */
	private $history;

	/** @var int[] */
	private $post_ids = array();

	/** @var int[] */
	private $history_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->history = new AIPS_History_Repository();
	}

	public function tearDown(): void {
		global $wpdb;

		foreach ($this->post_ids as $post_id) {
			wp_delete_post($post_id, true);
		}
		foreach ($this->history_ids as $history_id) {
			$wpdb->delete($wpdb->prefix . 'aips_history', array('id' => $history_id), array('%d'));
		}

		parent::tearDown();
	}

	/**
	 * @param int         $post_id         Post ID (not necessarily existing).
	 * @param string|null $creation_method creation_method (null leaves it NULL).
	 * @param string      $status          History status.
	 * @param int|null    $campaign_id     Campaign ID.
	 * @param string|null $post_type       History post_type (null leaves it NULL).
	 * @return int History ID.
	 */
	private function insert_history($post_id, $creation_method, $status = 'completed', $campaign_id = null, $post_type = null) {
		global $wpdb;

		$row = array(
			'post_id'         => $post_id,
			'status'          => $status,
			'generated_title' => 'Generated Title',
			'created_at'      => time(),
			'completed_at'    => time(),
			'record_kind'     => AIPS_History_Record_Kind::for_creation_method($creation_method, array('post_id' => $post_id)),
		);
		if (null !== $creation_method) {
			$row['creation_method'] = $creation_method;
		}
		if (null !== $campaign_id) {
			$row['campaign_id'] = $campaign_id;
		}
		if (null !== $post_type) {
			$row['post_type'] = $post_type;
		}

		$wpdb->insert($wpdb->prefix . 'aips_history', $row);
		$history_id          = (int) $wpdb->insert_id;
		$this->history_ids[] = $history_id;

		return $history_id;
	}

	private function make_post($post_type = 'post', $status = 'draft') {
		$post_id = wp_insert_post(array(
			'post_title'   => 'Post ' . uniqid(),
			'post_content' => 'Content',
			'post_status'  => $status,
			'post_type'    => $post_type,
		));
		$this->post_ids[] = $post_id;

		return (int) $post_id;
	}

	/**
	 * A post with an incomplete generation, as listed by Partial Generations.
	 *
	 * @return array{post_id:int, run_id:int}
	 */
	private function make_partial_post($post_type = 'post', $campaign_id = null) {
		$post_id = $this->make_post($post_type);
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');

		return array(
			'post_id' => $post_id,
			'run_id'  => $this->insert_history($post_id, 'scheduled', 'partial', $campaign_id),
		);
	}

	private function add_event_rows($post_id, $campaign_id = null) {
		$this->insert_history($post_id, 'topic_post_generation', 'completed', $campaign_id);
		$this->insert_history($post_id, 'post_review_action', 'completed', $campaign_id);
		$this->insert_history($post_id, 'content_indexing', 'completed', $campaign_id, 'post');
		$this->insert_history($post_id, 'notification_sent', 'completed', $campaign_id);
	}

	private function items_for_post($result, $post_id) {
		return array_values(array_filter($result['items'], function ($item) use ($post_id) {
			return (int) $item->post_id === (int) $post_id;
		}));
	}

	// ------------------------------------------------------------ Partial Generations tab

	public function test_partial_tab_shows_the_run_row_not_a_newer_event_row() {
		$partial = $this->make_partial_post();
		$this->add_event_rows($partial['post_id']);

		$items = $this->items_for_post($this->history->get_partial_generations(array('per_page' => 500)), $partial['post_id']);

		$this->assertCount(1, $items);
		$this->assertSame($partial['run_id'], (int) $items[0]->id);
		$this->assertSame('scheduled', $items[0]->creation_method);
	}

	public function test_partial_tab_total_counts_posts_not_history_rows() {
		$before = $this->history->get_partial_generations(array('per_page' => 2));

		for ($i = 0; $i < 3; $i++) {
			$partial = $this->make_partial_post();
			$this->add_event_rows($partial['post_id']);
		}

		$after = $this->history->get_partial_generations(array('per_page' => 2));

		$this->assertSame($before['total'] + 3, $after['total']);
		$this->assertSame((int) ceil($after['total'] / 2), $after['pages']);
	}

	public function test_partial_tab_post_type_filter_uses_the_post_not_the_history_row() {
		register_post_type('aips_part_test_cpt', array('public' => true, 'label' => 'AIPS Partial Test CPT'));
		try {
			$cpt  = $this->make_partial_post('aips_part_test_cpt');
			$post = $this->make_partial_post('post');

			$cpt_items = $this->history->get_partial_generations(array('per_page' => 500, 'post_type' => 'aips_part_test_cpt'));

			$this->assertCount(1, $this->items_for_post($cpt_items, $cpt['post_id']), 'The history row has no post_type, the post does.');
			$this->assertCount(0, $this->items_for_post($cpt_items, $post['post_id']));
		} finally {
			unregister_post_type('aips_part_test_cpt');
		}
	}

	public function test_partial_tab_without_a_post_type_filter_lists_every_type() {
		$partial = $this->make_partial_post('post');

		$items = $this->items_for_post($this->history->get_partial_generations(array('per_page' => 500)), $partial['post_id']);

		$this->assertCount(1, $items);
	}

	// ------------------------------------------------------------ Campaign recent posts

	public function test_campaign_recent_posts_return_the_run_row_not_a_newer_event_row() {
		$post_id = $this->make_post();
		$run_id  = $this->insert_history($post_id, 'scheduled', 'completed', 7001);
		$this->add_event_rows($post_id, 7001);

		$rows = (new AIPS_Campaigns_Repository())->get_recent_generated_posts(7001, 10);

		$this->assertCount(1, $rows);
		$this->assertSame($run_id, (int) $rows[0]->history_id);
	}

	public function test_campaign_recent_posts_only_consider_that_campaigns_runs() {
		$post_id   = $this->make_post();
		$own_run   = $this->insert_history($post_id, 'scheduled', 'completed', 7002);
		$this->insert_history($post_id, 'scheduled', 'completed', 7003);

		$rows = (new AIPS_Campaigns_Repository())->get_recent_generated_posts(7002, 10);

		$this->assertCount(1, $rows);
		$this->assertSame($own_run, (int) $rows[0]->history_id);
	}

	// ------------------------------------------------------------ post_type backfill

	public function test_backfill_fills_null_post_type_on_run_rows_from_the_post() {
		$post_id = $this->make_post('page');
		$run_id  = $this->insert_history($post_id, 'scheduled');

		$changed = $this->history->backfill_post_type();

		$this->assertGreaterThanOrEqual(1, $changed);
		$this->assertSame('page', $this->history->get_by_id($run_id)->post_type);
	}

	public function test_backfill_also_fills_legacy_rows_without_a_creation_method() {
		$post_id = $this->make_post('post');
		$run_id  = $this->insert_history($post_id, null);

		$this->history->backfill_post_type();

		$this->assertSame('post', $this->history->get_by_id($run_id)->post_type);
	}

	public function test_backfill_leaves_event_rows_alone() {
		$post_id  = $this->make_post();
		$event_id = $this->insert_history($post_id, 'topic_post_generation');
		$index_id = $this->insert_history($post_id, 'content_indexing');

		$this->history->backfill_post_type();

		$this->assertNull($this->history->get_by_id($event_id)->post_type);
		$this->assertNull($this->history->get_by_id($index_id)->post_type);
	}

	public function test_backfill_never_overwrites_an_existing_post_type() {
		$post_id = $this->make_post('page');
		$run_id  = $this->insert_history($post_id, 'scheduled', 'completed', null, 'post');

		$this->history->backfill_post_type();

		$this->assertSame('post', $this->history->get_by_id($run_id)->post_type);
	}

	public function test_backfill_skips_rows_whose_post_no_longer_exists() {
		$run_id = $this->insert_history(999999991, 'scheduled');

		$this->history->backfill_post_type();

		$this->assertNull($this->history->get_by_id($run_id)->post_type);
	}

	public function test_backfill_is_idempotent_and_batches() {
		$ids = array();
		for ($i = 0; $i < 5; $i++) {
			$ids[] = $this->insert_history($this->make_post(), 'scheduled');
		}

		$first  = $this->history->backfill_post_type(2);
		$second = $this->history->backfill_post_type(2);

		$this->assertGreaterThanOrEqual(5, $first);
		$this->assertSame(0, $second);
		foreach ($ids as $id) {
			$this->assertSame('post', $this->history->get_by_id($id)->post_type);
		}
	}
}
