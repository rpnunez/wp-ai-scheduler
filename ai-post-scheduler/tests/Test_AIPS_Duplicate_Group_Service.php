<?php
/**
 * Tests for AIPS_Duplicate_Group_Service (complete-linkage duplicate review groups).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Duplicate_Group_Service extends WP_UnitTestCase {

	/** @var AIPS_Relationships_Repository|\PHPUnit\Framework\MockObject\MockObject */
	private $relationships;

	/** @var AIPS_Link_Index_Repository|\PHPUnit\Framework\MockObject\MockObject */
	private $link_index;

	/** @var AIPS_Duplicate_Group_Service */
	private $service;

	public function setUp(): void {
		parent::setUp();

		$this->relationships = $this->getMockBuilder(AIPS_Relationships_Repository::class)
			->disableOriginalConstructor()
			->onlyMethods(array('get_top_duplicate_pairs'))
			->getMock();

		$this->link_index = $this->getMockBuilder(AIPS_Link_Index_Repository::class)
			->disableOriginalConstructor()
			->onlyMethods(array('get_counts_for_posts'))
			->getMock();

		$this->service = new AIPS_Duplicate_Group_Service($this->relationships, $this->link_index, AIPS_Config::get_instance());
	}

	private function pair($a, $b, $sim) {
		return array('a' => $a, 'b' => $b, 'sim' => $sim);
	}

	public function test_chain_of_similar_posts_is_not_merged_into_one_group() {
		// A~B and B~C are close, but A and C were never paired: classic single-linkage chain.
		$groups = $this->service->build_groups(array(
			$this->pair(1, 2, 0.96),
			$this->pair(2, 3, 0.93),
		), 5);

		$this->assertCount(1, $groups);
		$this->assertSame(array(1, 2), $groups[0]['ids']);
	}

	public function test_fully_connected_posts_form_one_group_with_min_and_avg() {
		$groups = $this->service->build_groups(array(
			$this->pair(1, 2, 0.98),
			$this->pair(1, 3, 0.90),
			$this->pair(2, 3, 0.94),
		), 5);

		$this->assertCount(1, $groups);
		$this->assertSame(array(1, 2, 3), $groups[0]['ids']);
		$this->assertEqualsWithDelta(0.90, $groups[0]['min'], 0.0001);
		$this->assertEqualsWithDelta((0.98 + 0.90 + 0.94) / 3, $groups[0]['avg'], 0.0001);
	}

	public function test_group_size_is_capped() {
		$groups = $this->service->build_groups(array(
			$this->pair(1, 2, 0.99),
			$this->pair(1, 3, 0.98),
			$this->pair(2, 3, 0.97),
		), 2);

		// Only the strongest pair fits; the third post is left out of every group.
		$this->assertCount(1, $groups);
		$this->assertSame(array(1, 2), $groups[0]['ids']);
	}

	public function test_unrelated_pairs_make_separate_groups_strongest_first() {
		$groups = $this->service->build_groups(array(
			$this->pair(10, 11, 0.90),
			$this->pair(20, 21, 0.97),
		), 5);

		$this->assertCount(2, $groups);
		$this->assertSame(array(20, 21), $groups[0]['ids']);
		$this->assertSame(array(10, 11), $groups[1]['ids']);
	}

	public function test_pair_direction_and_self_pairs_are_ignored() {
		$groups = $this->service->build_groups(array(
			$this->pair(2, 1, 0.95),
			$this->pair(1, 2, 0.90),
			$this->pair(4, 4, 1.0),
		), 5);

		$this->assertCount(1, $groups);
		$this->assertSame(array(1, 2), $groups[0]['ids']);
		$this->assertEqualsWithDelta(0.95, $groups[0]['min'], 0.0001);
	}

	public function test_recommend_keep_balanced_prefers_linked_longer_older_post() {
		$members = array(
			array('id' => 1, 'inbound_links' => 0, 'words' => 400, 'timestamp' => 2000),
			array('id' => 2, 'inbound_links' => 5, 'words' => 900, 'timestamp' => 1000),
		);

		$result = $this->service->recommend_keep($members, 'balanced');

		$this->assertSame(2, $result[0]['id']);
		$this->assertTrue($result[0]['is_recommended']);
		$this->assertFalse($result[1]['is_recommended']);
		$this->assertNotEmpty($result[0]['reasons']);
	}

	public function test_recommend_keep_strategy_overrides_balanced() {
		$members = array(
			array('id' => 1, 'inbound_links' => 9, 'words' => 400, 'timestamp' => 2000),
			array('id' => 2, 'inbound_links' => 0, 'words' => 900, 'timestamp' => 1000),
		);

		$this->assertSame(2, $this->service->recommend_keep($members, 'longest')[0]['id']);
		$this->assertSame(1, $this->service->recommend_keep($members, 'newest')[0]['id']);
		$this->assertSame(1, $this->service->recommend_keep($members, 'most_linked')[0]['id']);
		$this->assertSame(2, $this->service->recommend_keep($members, 'oldest')[0]['id']);
	}

	public function test_recommend_keep_is_stable_when_posts_are_identical() {
		$members = array(
			array('id' => 7, 'inbound_links' => 0, 'words' => 100, 'timestamp' => 500),
			array('id' => 3, 'inbound_links' => 0, 'words' => 100, 'timestamp' => 500),
		);

		$result = $this->service->recommend_keep($members, 'balanced');

		$this->assertSame(3, $result[0]['id']);
		$this->assertSame(array(), $result[0]['reasons']);
	}

	public function test_get_review_groups_builds_groups_with_recommendation_and_stats() {
		$old = $this->factory->post->create(array('post_title' => 'Old', 'post_content' => str_repeat('word ', 300), 'post_date' => '2023-01-01 00:00:00'));
		$new = $this->factory->post->create(array('post_title' => 'New', 'post_content' => str_repeat('word ', 100), 'post_date' => '2025-01-01 00:00:00'));

		$this->relationships->expects($this->once())
			->method('get_top_duplicate_pairs')
			->with(0.9, 500, 'posts')
			->willReturn(array((object) array('source_id' => $old, 'target_id' => $new, 'similarity' => 0.95)));

		$this->link_index->method('get_counts_for_posts')->willReturn(array(
			$old => array('inbound' => 4, 'outbound' => 0, 'external' => 0, 'broken' => 0),
			$new => array('inbound' => 0, 'outbound' => 0, 'external' => 0, 'broken' => 0),
		));

		$result = $this->service->get_review_groups(0.9, 5, 500, 'balanced');

		$this->assertSame(1, $result['stats']['group_count']);
		$this->assertSame(2, $result['stats']['post_count']);
		$this->assertFalse($result['stats']['pairs_truncated']);

		$group = $result['groups'][0];
		$this->assertSame($old, $group['recommended_keep_id']);
		$this->assertSame($old, $group['posts'][0]['id']);
		$this->assertSame(95.0, $group['min_similarity_pct']);
	}

	public function test_get_review_groups_drops_unpublished_members() {
		$live  = $this->factory->post->create();
		$draft = $this->factory->post->create(array('post_status' => 'draft'));

		$this->relationships->method('get_top_duplicate_pairs')
			->willReturn(array((object) array('source_id' => $live, 'target_id' => $draft, 'similarity' => 0.95)));
		$this->link_index->method('get_counts_for_posts')->willReturn(array());

		$this->assertSame(array(), $this->service->get_review_groups(0.9)['groups']);
	}

	public function test_recommend_keep_protects_well_linked_post_whatever_the_strategy() {
		$members = array(
			array('id' => 1, 'inbound_links' => 12, 'words' => 100, 'timestamp' => 2000),
			array('id' => 2, 'inbound_links' => 0, 'words' => 900, 'timestamp' => 1000),
		);

		$result = $this->service->recommend_keep($members, 'longest', 10);

		$this->assertSame(1, $result[0]['id']);
		$this->assertTrue($result[0]['protected']);
		$this->assertFalse($result[1]['protected']);
	}

	public function test_recommend_keep_protection_off_when_zero() {
		$members = array(
			array('id' => 1, 'inbound_links' => 12, 'words' => 100, 'timestamp' => 2000),
			array('id' => 2, 'inbound_links' => 0, 'words' => 900, 'timestamp' => 1000),
		);

		$result = $this->service->recommend_keep($members, 'longest', 0);

		$this->assertSame(2, $result[0]['id']);
		$this->assertFalse($result[0]['protected']);
	}

	private function two_published_posts() {
		$a = $this->factory->post->create(array('post_title' => 'Alpha'));
		$b = $this->factory->post->create(array('post_title' => 'Beta'));
		$this->relationships->method('get_top_duplicate_pairs')
			->willReturn(array((object) array('source_id' => $a, 'target_id' => $b, 'similarity' => 0.95)));
		$this->link_index->method('get_counts_for_posts')->willReturn(array());
		return array($a, $b);
	}

	public function test_dismissed_groups_are_hidden_and_can_be_restored() {
		list($a, $b) = $this->two_published_posts();

		$this->assertCount(1, $this->service->get_review_groups(0.9)['groups']);

		$this->assertSame(1, $this->service->dismiss_group(array($a, $b)));
		$this->assertSame(0, $this->service->dismiss_group(array($b, $a)));

		$hidden = $this->service->get_review_groups(0.9);
		$this->assertSame(array(), $hidden['groups']);
		$this->assertSame(1, $hidden['stats']['pairs_dismissed']);
		$this->assertSame(1, $hidden['stats']['dismissed_total']);

		$this->service->reset_dismissed();
		$this->assertCount(1, $this->service->get_review_groups(0.9)['groups']);
	}

	public function test_excluded_post_ids_post_types_and_categories_are_skipped() {
		list($a, $b) = $this->two_published_posts();
		$config = AIPS_Config::get_instance();

		$config->set_option('aips_duplicate_excluded_post_ids', (string) $a);
		$excluded = $this->service->get_review_groups(0.9);
		$this->assertSame(array(), $excluded['groups']);
		$this->assertSame(1, $excluded['stats']['pairs_excluded']);
		$config->set_option('aips_duplicate_excluded_post_ids', '');

		$config->set_option('aips_duplicate_excluded_post_types', array('post'));
		$this->assertSame(array(), $this->service->get_review_groups(0.9)['groups']);
		$config->set_option('aips_duplicate_excluded_post_types', array());

		$term = $this->factory->category->create();
		wp_set_post_categories($b, array($term));
		$config->set_option('aips_duplicate_excluded_categories', array($term));
		$this->assertSame(array(), $this->service->get_review_groups(0.9)['groups']);
		$config->set_option('aips_duplicate_excluded_categories', array());

		$this->assertCount(1, $this->service->get_review_groups(0.9)['groups']);
	}

	public function test_confirmed_pillar_posts_are_excluded_unless_turned_off() {
		list($a, $b) = $this->two_published_posts();
		$config = AIPS_Config::get_instance();
		$config->set_option('aips_post_clusters', array('cluster_1' => array('pillar_id' => $a, 'pillar_confirmed' => true)));

		$this->assertSame(array(), $this->service->get_review_groups(0.9)['groups']);

		$config->set_option('aips_duplicate_exclude_pillars', false);
		$this->assertCount(1, $this->service->get_review_groups(0.9)['groups']);

		$config->set_option('aips_duplicate_exclude_pillars', true);
		$config->set_option('aips_post_clusters', array());
	}

	public function test_ensure_schedule_follows_the_setting() {
		$config = AIPS_Config::get_instance();
		wp_clear_scheduled_hook(AIPS_Duplicate_Group_Service::CRON_HOOK);

		$config->set_option('aips_duplicate_scan_schedule', 'daily');
		$this->service->ensure_schedule();
		$this->assertSame('daily', wp_get_scheduled_event(AIPS_Duplicate_Group_Service::CRON_HOOK)->schedule);

		$config->set_option('aips_duplicate_scan_schedule', 'weekly');
		$this->service->ensure_schedule();
		$this->assertSame('weekly', wp_get_scheduled_event(AIPS_Duplicate_Group_Service::CRON_HOOK)->schedule);

		$config->set_option('aips_duplicate_scan_schedule', 'off');
		$this->service->ensure_schedule();
		$this->assertFalse(wp_get_scheduled_event(AIPS_Duplicate_Group_Service::CRON_HOOK));
	}

	public function test_scheduled_scan_records_status_and_reports_only_new_groups() {
		$this->two_published_posts();
		delete_option(AIPS_Duplicate_Group_Service::STATUS_OPTION);

		$first = $this->service->run_scheduled_scan();
		$this->assertSame(1, $first['group_count']);
		$this->assertSame(1, $first['new_groups']);

		$second = $this->service->run_scheduled_scan();
		$this->assertSame(1, $second['group_count']);
		$this->assertSame(0, $second['new_groups']);
	}

	public function test_keep_strategies_include_balanced_default() {
		$this->assertArrayHasKey('balanced', AIPS_Duplicate_Group_Service::get_keep_strategies());
	}
}
