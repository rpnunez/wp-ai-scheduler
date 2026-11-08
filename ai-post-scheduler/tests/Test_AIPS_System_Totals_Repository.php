<?php
/**
 * Tests for AIPS_System_Totals_Repository, the source of the "Totals & Counts"
 * section on Diagnostics → System Info.
 *
 * Every assertion compares totals before and after seeding, so rows left by
 * other tests do not matter.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_System_Totals_Repository extends WP_UnitTestCase {

	/** @var AIPS_System_Totals_Repository */
	private $repository;

	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_System_Totals_Repository();
	}

	private function insert($short_table, array $data) {
		global $wpdb;
		$wpdb->insert($wpdb->prefix . 'aips_' . $short_table, $data);
		return (int) $wpdb->insert_id;
	}

	private function totals() {
		return $this->repository->get_totals(true);
	}

	private function group($groups, $key) {
		return isset($groups[$key]) ? (int) $groups[$key] : 0;
	}

	public function test_returns_every_expected_key() {
		$totals = $this->totals();

		foreach (array(
			'generated_posts', 'history', 'author_posts', 'authors', 'author_topics', 'ai_requests',
			'ai_assistance', 'background_ai_calls', 'embeddings', 'relationships', 'internal_links',
			'link_index', 'templates', 'schedules', 'campaigns', 'trending_topics', 'sources',
			'source_pages', 'bulk_jobs',
		) as $key) {
			$this->assertArrayHasKey($key, $totals, $key);
		}
	}

	public function test_counts_generated_posts_and_ignores_trashed_and_unmarked() {
		$before = $this->totals()['generated_posts'];

		$kept    = self::factory()->post->create();
		$trashed = self::factory()->post->create(array('post_status' => 'trash'));
		self::factory()->post->create();
		update_post_meta($kept, AIPS_Post_Manager::META_GENERATED_POST, '1');
		update_post_meta($trashed, AIPS_Post_Manager::META_GENERATED_POST, '1');

		$this->assertSame($before + 1, $this->totals()['generated_posts']);
	}

	public function test_history_totals_by_kind_status_and_method() {
		$before = $this->totals()['history'];

		$this->insert('history', array('record_kind' => 'generation', 'status' => 'completed', 'creation_method' => 'manual', 'post_id' => 11, 'author_id' => 3));
		$this->insert('history', array('record_kind' => 'generation', 'status' => 'failed', 'creation_method' => 'scheduled'));
		$this->insert('history', array('record_kind' => 'generation', 'status' => 'partial', 'creation_method' => 'manual', 'post_id' => 12));
		$this->insert('history', array('record_kind' => 'job', 'status' => 'completed', 'creation_method' => 'content_indexing'));
		$this->insert('history', array('record_kind' => 'event', 'status' => 'completed'));

		$after = $this->totals()['history'];

		$this->assertSame($before['total'] + 5, $after['total']);
		$this->assertSame($this->group($before['by_kind'], 'generation') + 3, $this->group($after['by_kind'], 'generation'));
		$this->assertSame($this->group($before['by_kind'], 'job') + 1, $this->group($after['by_kind'], 'job'));
		$this->assertSame($this->group($before['by_kind'], 'event') + 1, $this->group($after['by_kind'], 'event'));
		$this->assertSame($this->group($before['generation_by_status'], 'completed') + 1, $this->group($after['generation_by_status'], 'completed'));
		$this->assertSame($this->group($before['generation_by_status'], 'failed') + 1, $this->group($after['generation_by_status'], 'failed'));
		$this->assertSame($this->group($before['generation_by_status'], 'partial') + 1, $this->group($after['generation_by_status'], 'partial'));
		$this->assertSame($this->group($before['generation_by_method'], 'manual') + 2, $this->group($after['generation_by_method'], 'manual'));
		$this->assertSame($this->group($before['generation_by_method'], 'scheduled') + 1, $this->group($after['generation_by_method'], 'scheduled'));
		$this->assertArrayNotHasKey('content_indexing', $after['generation_by_method']);
	}

	public function test_author_posts_need_author_post_and_success() {
		$before = $this->totals()['author_posts'];

		$this->insert('history', array('record_kind' => 'generation', 'status' => 'completed', 'post_id' => 21, 'author_id' => 4));
		$this->insert('history', array('record_kind' => 'generation', 'status' => 'failed', 'post_id' => 22, 'author_id' => 4));
		$this->insert('history', array('record_kind' => 'generation', 'status' => 'completed', 'post_id' => 23));
		$this->insert('history', array('record_kind' => 'job', 'status' => 'completed', 'post_id' => 24, 'author_id' => 4));

		$this->assertSame($before + 1, $this->totals()['author_posts']);
	}

	public function test_counts_ai_request_log_entries_only() {
		$before     = $this->totals()['ai_requests'];
		$history_id = $this->insert('history', array('record_kind' => 'generation', 'status' => 'completed'));

		$this->insert('history_log', array('history_id' => $history_id, 'history_type_id' => AIPS_History_Type::AI_REQUEST, 'details' => '{}'));
		$this->insert('history_log', array('history_id' => $history_id, 'history_type_id' => AIPS_History_Type::AI_REQUEST, 'details' => '{}'));
		$this->insert('history_log', array('history_id' => $history_id, 'history_type_id' => AIPS_History_Type::AI_RESPONSE, 'details' => '{}'));

		$this->assertSame($before + 2, $this->totals()['ai_requests']);
	}

	public function test_active_counts_for_authors_and_templates() {
		$before = $this->totals();

		$this->insert('authors', array('name' => 'A', 'field_niche' => 'n', 'is_active' => 1));
		$this->insert('authors', array('name' => 'B', 'field_niche' => 'n', 'is_active' => 0));
		$this->insert('templates', array('name' => 'T', 'prompt_template' => 'p', 'is_active' => 1));

		$after = $this->totals();

		$this->assertSame($before['authors']['total'] + 2, $after['authors']['total']);
		$this->assertSame($before['authors']['active'] + 1, $after['authors']['active']);
		$this->assertSame($before['templates']['total'] + 1, $after['templates']['total']);
		$this->assertSame($before['templates']['active'] + 1, $after['templates']['active']);
	}

	public function test_grouped_and_summed_totals() {
		$before = $this->totals();

		$this->insert('author_topics', array('author_id' => 1, 'topic_title' => 'x', 'status' => 'approved'));
		$this->insert('author_topics', array('author_id' => 1, 'topic_title' => 'y', 'status' => 'pending'));
		$this->insert('embeddings', array('object_type' => 'post', 'object_id' => 5, 'embedding' => 'x'));
		$this->insert('link_index', array('source_post_id' => 1, 'target_url' => 'https://a.test', 'link_type' => 'external', 'inserted_by_aips' => 1));
		$this->insert('background_processes', array('process_key' => 'k', 'ai_calls_used' => 7));
		$this->insert('sources_data', array('source_id' => 1, 'fetch_status' => 'success', 'num_used' => 4));
		$this->insert('bulk_batch_jobs', array('job_id' => wp_generate_uuid4(), 'job_type' => 't', 'items_json' => '[]', 'options_json' => '{}', 'processed' => 6));

		$after = $this->totals();

		$this->assertSame($before['author_topics']['total'] + 2, $after['author_topics']['total']);
		$this->assertSame($this->group($before['author_topics']['groups'], 'approved') + 1, $this->group($after['author_topics']['groups'], 'approved'));
		$this->assertSame($this->group($before['embeddings']['groups'], 'post') + 1, $this->group($after['embeddings']['groups'], 'post'));
		$this->assertSame($this->group($before['link_index']['by_type'], 'external') + 1, $this->group($after['link_index']['by_type'], 'external'));
		$this->assertSame($before['link_index']['inserted_by_aips'] + 1, $after['link_index']['inserted_by_aips']);
		$this->assertSame($before['background_ai_calls'] + 7, $after['background_ai_calls']);
		$this->assertSame($before['source_pages']['times_used'] + 4, $after['source_pages']['times_used']);
		$this->assertSame($before['bulk_jobs']['total'] + 1, $after['bulk_jobs']['total']);
		$this->assertSame($before['bulk_jobs']['processed'] + 6, $after['bulk_jobs']['processed']);
	}
}
