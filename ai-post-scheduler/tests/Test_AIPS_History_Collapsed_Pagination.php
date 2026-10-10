<?php
/**
 * Tests for entry-based (collapsed) History pagination.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Collapsed_Pagination extends WP_UnitTestCase {

	const POST_TYPE = 'aips_collapse_test';

	public function test_plan_entry_sizes_groups_runs_and_keeps_singles() {
		$sizes = AIPS_History_Repository::plan_entry_sizes(array('a', 'a', 'a', 'b', 'a', 'c', 'c'), 50);

		$this->assertSame(array(3, 1, 1, 2), $sizes);
	}

	public function test_plan_entry_sizes_splits_long_runs_at_cap() {
		$keys  = array_merge(array_fill(0, 100, 'post_generation'), array('x', 'y'));
		$sizes = AIPS_History_Repository::plan_entry_sizes($keys, 50);

		$this->assertSame(array(50, 50, 1, 1), $sizes);
	}

	public function test_plan_entry_sizes_remainder_of_one_stays_single() {
		$sizes = AIPS_History_Repository::plan_entry_sizes(array_fill(0, 51, 'a'), 50);

		// 51 rows => one group of 50 plus a lone single row.
		$this->assertSame(array(50, 1), $sizes);
	}

	public function test_plan_entry_sizes_empty() {
		$this->assertSame(array(), AIPS_History_Repository::plan_entry_sizes(array(), 50));
	}

	public function test_group_contiguous_items_respects_cap() {
		$history = new AIPS_History();
		$items   = array();
		for ($i = 1; $i <= 7; $i++) {
			$item = new stdClass();
			$item->id = $i;
			$item->creation_method = 'scheduled';
			$item->status = 'completed';
			$item->post_type = 'post';
			$item->relative_date = 'now';
			$items[] = $item;
		}

		$grouped = $history->group_contiguous_items($items, 3);

		$this->assertCount(3, $grouped);
		$this->assertSame(3, $grouped[0]['count']);
		$this->assertSame(3, $grouped[1]['count']);
		$this->assertFalse($grouped[2]['is_group']);
	}

	private function seed_rows() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';
		$base  = time();
		$n     = 0;

		// Newest: 100 identical rows, then 60 alternating singles.
		$methods = array_fill(0, 100, 'scheduled');
		for ($i = 0; $i < 60; $i++) {
			$methods[] = ($i % 2 === 0) ? 'manual_ui' : 'scheduled';
		}

		foreach ($methods as $method) {
			$wpdb->insert($table, array(
				'uuid'            => wp_generate_uuid4(),
				'post_type'       => self::POST_TYPE,
				'template_id'     => 1,
				'status'          => 'completed',
				'creation_method' => $method,
				'created_at'      => $base - $n,
			));
			$n++;
		}
	}

	public function test_collapsed_pages_are_filled_with_entries() {
		$this->seed_rows();
		$repo = new AIPS_History_Repository();
		$args = array(
			'post_type' => self::POST_TYPE,
			'per_page'  => 50,
			'collapse'  => true,
			'fields'    => 'list',
		);

		$page1 = $repo->get_history($args + array('page' => 1));
		$page2 = $repo->get_history($args + array('page' => 2));

		// 100 rows => 2 groups of 50, plus 60 singles => 62 entries over 2 pages.
		$this->assertSame(62, $page1['total_entries']);
		$this->assertSame(160, $page1['total']);
		$this->assertSame(2, $page1['pages']);

		// Page 1: 2 groups (100 rows) + 48 singles.
		$this->assertCount(148, $page1['items']);
		$grouped = (new AIPS_History())->group_contiguous_items($page1['items'], $page1['group_max_rows']);
		$this->assertCount(50, $grouped);
		$this->assertTrue($grouped[0]['is_group']);
		$this->assertTrue($grouped[1]['is_group']);
		$this->assertFalse($grouped[2]['is_group']);

		// Page 2: the remaining 12 singles.
		$this->assertCount(12, $page2['items']);
		$this->assertCount(12, (new AIPS_History())->group_contiguous_items($page2['items'], $page2['group_max_rows']));

		// No overlap between pages.
		$ids1 = wp_list_pluck($page1['items'], 'id');
		$ids2 = wp_list_pluck($page2['items'], 'id');
		$this->assertSame(array(), array_intersect($ids1, $ids2));
	}

	public function test_non_collapsed_pagination_is_unchanged() {
		$this->seed_rows();
		$repo = new AIPS_History_Repository();

		$result = $repo->get_history(array(
			'post_type' => self::POST_TYPE,
			'per_page'  => 50,
			'page'      => 1,
			'fields'    => 'list',
		));

		$this->assertCount(50, $result['items']);
		$this->assertSame(160, $result['total']);
		$this->assertArrayNotHasKey('total_entries', $result);
	}
}
