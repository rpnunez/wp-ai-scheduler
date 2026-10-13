<?php
/**
 * Tests for the "generation run" helper and the Pending Review queries built on it.
 *
 * Event containers (topic-generation wrappers, review actions, notifications) and
 * indexing rows carry the same post_id as the run that generated a post and finish
 * as 'completed', so a draft must still be listed exactly once.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generation_Run_Pending_Review extends WP_UnitTestCase {

	/** @var AIPS_Post_Review_Repository */
	private $repository;

	/** @var int[] */
	private $post_ids = array();

	/** @var int[] */
	private $history_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_Post_Review_Repository();
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
	 * Insert a history row for a post and remember it for cleanup.
	 *
	 * @param int         $post_id         Post ID.
	 * @param string|null $creation_method creation_method column (null leaves it NULL).
	 * @param string      $status          History status.
	 * @param string|null $post_type       History post_type column (null leaves it NULL).
	 * @return int History ID.
	 */
	private function insert_history($post_id, $creation_method, $status = 'completed', $post_type = null) {
		global $wpdb;

		$row = array(
			'post_id'         => $post_id,
			'status'          => $status,
			'generated_title' => 'Generated Title',
			'created_at'      => time(),
			'completed_at'    => time(),
		);
		if (null !== $creation_method) {
			$row['creation_method'] = $creation_method;
		}
		if (null !== $post_type) {
			$row['post_type'] = $post_type;
		}

		$wpdb->insert($wpdb->prefix . 'aips_history', $row);
		$history_id         = (int) $wpdb->insert_id;
		$this->history_ids[] = $history_id;

		return $history_id;
	}

	/**
	 * Create a draft post whose generation run is a history row of the given method.
	 *
	 * @return array{post_id:int, run_id:int}
	 */
	private function make_draft($creation_method = 'scheduled', $post_type = 'post') {
		$post_id = wp_insert_post(array(
			'post_title'   => 'Draft ' . uniqid(),
			'post_content' => 'Content',
			'post_status'  => 'draft',
			'post_type'    => $post_type,
		));
		$this->post_ids[] = $post_id;

		return array(
			'post_id' => (int) $post_id,
			'run_id'  => $this->insert_history($post_id, $creation_method),
		);
	}

	/**
	 * Add the event and indexing rows seen on production around one generation.
	 */
	private function add_event_rows($post_id) {
		$this->insert_history($post_id, 'topic_post_generation');
		$this->insert_history($post_id, 'post_review_action');
		$this->insert_history($post_id, 'content_indexing', 'completed', 'post');
		$this->insert_history($post_id, 'notification_sent');
	}

	/**
	 * Items in a result that belong to a post.
	 */
	private function items_for_post($result, $post_id) {
		return array_values(array_filter($result['items'], function ($item) use ($post_id) {
			return (int) $item->post_id === (int) $post_id;
		}));
	}

	public function test_generation_run_methods_never_overlap_event_only_methods() {
		$this->assertSame(
			array(),
			array_values(array_intersect(
				AIPS_History_Repository::generation_run_creation_methods(),
				AIPS_History_Repository::event_only_creation_methods()
			))
		);
	}

	public function test_generation_run_condition_covers_legacy_rows_and_run_methods() {
		$sql = AIPS_History_Repository::generation_run_condition('h');

		$this->assertStringContainsString('h.creation_method IS NULL', $sql);
		$this->assertStringContainsString("h.creation_method = ''", $sql);
		$this->assertStringContainsString("'scheduled'", $sql);
		$this->assertStringNotContainsString('topic_post_generation', $sql);
		$this->assertStringNotContainsString('content_indexing', $sql);
	}

	/**
	 * Every creation_method literal the plugin writes must be classified, so a new
	 * container type cannot silently start (or stop) appearing in post-keyed run
	 * queries.
	 *
	 * Limits: it only sees string literals passed to history `create()` or assigned
	 * to a `'creation_method'` key. Types supplied through variables (for example
	 * `$event->type()`) are not visible to it; those event types are covered by
	 * AIPS_History_Event_Type and must be classified by hand when added.
	 */
	public function test_every_creation_method_literal_is_classified() {
		$classified = array_merge(
			AIPS_History_Repository::generation_run_creation_methods(),
			AIPS_History_Repository::event_only_creation_methods(),
			// Background jobs and wrappers that are not generation runs.
			array(
				'author_embeddings', 'author_suggestion', 'author_topic_generation', 'bulk_batch_slice',
				'content_indexing', 'manual_generation', 'manual_regeneration',
			)
		);

		$found    = array();
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/includes', FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if ('php' !== $file->getExtension()) {
				continue;
			}
			$source = file_get_contents($file->getPathname());
			if (preg_match_all("/(?:history_service|history_repository|history)->create\(\s*'([a-z_]+)'/", $source, $matches)) {
				$found = array_merge($found, $matches[1]);
			}
			if (preg_match_all("/'creation_method'\s*=>\s*'([a-z_]+)'/", $source, $matches)) {
				$found = array_merge($found, $matches[1]);
			}
		}

		$this->assertNotEmpty($found, 'The scan found no creation_method literals; the patterns are out of date.');

		$unclassified = array_values(array_diff(array_unique($found), $classified));

		$this->assertSame(
			array(),
			$unclassified,
			'New history creation_method values must be added to AIPS_History_Repository::generation_run_creation_methods() or event_only_creation_methods() (or the job list in this test): ' . implode(', ', $unclassified)
		);
	}

	public function test_draft_with_event_and_indexing_rows_is_listed_once() {
		$draft = $this->make_draft('scheduled');
		$count_before = $this->repository->get_draft_count();
		$this->add_event_rows($draft['post_id']);

		$result = $this->repository->get_draft_posts(array('per_page' => 500));
		$items  = $this->items_for_post($result, $draft['post_id']);

		$this->assertCount(1, $items, 'A draft must appear once however many history rows reference it.');
		$this->assertSame($draft['run_id'], (int) $items[0]->id, 'The generation run row, not an event row, must represent the post.');
		$this->assertSame('scheduled', $items[0]->creation_method);
		$this->assertSame($count_before, $this->repository->get_draft_count());
	}

	public function test_newest_generation_run_represents_the_post() {
		$draft  = $this->make_draft('scheduled');
		$rerun  = $this->insert_history($draft['post_id'], 'regenerate');
		$this->add_event_rows($draft['post_id']);

		$items = $this->items_for_post($this->repository->get_draft_posts(array('per_page' => 500)), $draft['post_id']);

		$this->assertCount(1, $items);
		$this->assertSame($rerun, (int) $items[0]->id);
	}

	public function test_failed_regeneration_does_not_hide_the_draft() {
		$draft = $this->make_draft('manual');
		$this->insert_history($draft['post_id'], 'regenerate', 'failed');

		$items = $this->items_for_post($this->repository->get_draft_posts(array('per_page' => 500)), $draft['post_id']);

		$this->assertCount(1, $items);
		$this->assertSame($draft['run_id'], (int) $items[0]->id);
	}

	public function test_legacy_row_without_creation_method_is_listed() {
		$post_id = wp_insert_post(array('post_title' => 'Legacy ' . uniqid(), 'post_status' => 'draft', 'post_type' => 'post'));
		$this->post_ids[] = $post_id;
		$run_id = $this->insert_history($post_id, null);

		$items = $this->items_for_post($this->repository->get_draft_posts(array('per_page' => 500)), $post_id);

		$this->assertCount(1, $items);
		$this->assertSame($run_id, (int) $items[0]->id);
	}

	public function test_run_whose_creation_method_is_the_container_default_is_listed() {
		$draft = $this->make_draft('post_generation');
		$this->add_event_rows($draft['post_id']);

		$items = $this->items_for_post($this->repository->get_draft_posts(array('per_page' => 500)), $draft['post_id']);

		$this->assertCount(1, $items);
		$this->assertSame($draft['run_id'], (int) $items[0]->id);
	}

	public function test_post_with_only_event_rows_is_not_listed_or_counted() {
		$post_id = wp_insert_post(array('post_title' => 'Notified ' . uniqid(), 'post_status' => 'draft', 'post_type' => 'post'));
		$this->post_ids[] = $post_id;
		$count_before = $this->repository->get_draft_count();
		$this->insert_history($post_id, 'notification_sent');

		$items = $this->items_for_post($this->repository->get_draft_posts(array('per_page' => 500)), $post_id);

		$this->assertSame(array(), $items);
		$this->assertSame($count_before, $this->repository->get_draft_count());
	}

	public function test_type_comes_from_the_post_not_the_history_row() {
		register_post_type('aips_run_test_cpt', array('public' => true, 'label' => 'AIPS Run Test CPT'));
		try {
			$draft = $this->make_draft('scheduled', 'aips_run_test_cpt');

			$items = $this->items_for_post($this->repository->get_draft_posts(array('per_page' => 500)), $draft['post_id']);

			$this->assertCount(1, $items);
			$this->assertEmpty($items[0]->post_type, 'The history row has no post_type (written before the column existed).');
			$this->assertSame('aips_run_test_cpt', $items[0]->wp_post_type);
		} finally {
			unregister_post_type('aips_run_test_cpt');
		}
	}

	public function test_template_filter_matches_the_newest_run_only() {
		global $wpdb;

		$draft = $this->make_draft('scheduled');
		$wpdb->update($wpdb->prefix . 'aips_history', array('template_id' => 901), array('id' => $draft['run_id']));
		$rerun = $this->insert_history($draft['post_id'], 'regenerate');
		$wpdb->update($wpdb->prefix . 'aips_history', array('template_id' => 902), array('id' => $rerun));

		$old_template = $this->items_for_post($this->repository->get_draft_posts(array('per_page' => 500, 'template_id' => 901)), $draft['post_id']);
		$new_template = $this->items_for_post($this->repository->get_draft_posts(array('per_page' => 500, 'template_id' => 902)), $draft['post_id']);

		$this->assertSame(array(), $old_template, 'The older run no longer represents the post.');
		$this->assertCount(1, $new_template);
		$this->assertSame($rerun, (int) $new_template[0]->id);
	}

	public function test_latest_run_subquery_can_be_restricted_to_a_post_status() {
		global $wpdb;

		$draft = $this->make_draft('scheduled');
		$published_id = wp_insert_post(array('post_title' => 'Published ' . uniqid(), 'post_status' => 'publish', 'post_type' => 'post'));
		$this->post_ids[] = $published_id;
		$this->insert_history($published_id, 'scheduled');

		$all   = $wpdb->get_col('SELECT post_id FROM ' . AIPS_History_Repository::latest_generation_run_subquery() . ' latest');
		$draft_only = $wpdb->get_col('SELECT post_id FROM ' . AIPS_History_Repository::latest_generation_run_subquery('draft') . ' latest');

		$this->assertContains((string) $published_id, $all);
		$this->assertNotContains((string) $published_id, $draft_only);
		$this->assertContains((string) $draft['post_id'], $draft_only);
	}

	public function test_total_and_count_count_posts_not_history_rows() {
		$before = $this->repository->get_draft_posts(array('per_page' => 2));
		$count_before = $this->repository->get_draft_count();

		for ($i = 0; $i < 3; $i++) {
			$draft = $this->make_draft('scheduled');
			$this->add_event_rows($draft['post_id']);
		}

		$after = $this->repository->get_draft_posts(array('per_page' => 2));

		$this->assertSame($before['total'] + 3, $after['total']);
		$this->assertSame($count_before + 3, $this->repository->get_draft_count());
		$this->assertSame((int) ceil($after['total'] / 2), (int) $after['pages']);
		$this->assertSame($after['total'], $this->repository->get_draft_count());
	}
}
