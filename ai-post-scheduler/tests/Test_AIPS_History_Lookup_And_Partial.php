<?php
/**
 * Tests for the Post review History domain, event-aware post/container lookups,
 * and the 'partial' generation status.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_History_Lookup_And_Partial extends WP_UnitTestCase {

	/** @var AIPS_History_Repository */
	private $repository;

	/** @var AIPS_History_Service */
	private $service;

	public function set_up() {
		parent::set_up();
		$this->repository = new AIPS_History_Repository();
		$this->service    = new AIPS_History_Service($this->repository);
	}

	private function status_of($history_id) {
		global $wpdb;

		return $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}aips_history WHERE id = %d", $history_id));
	}

	private function set_status($history_id, $status) {
		global $wpdb;

		$wpdb->update($wpdb->prefix . 'aips_history', array('status' => $status), array('id' => $history_id));
	}

	private function row($creation_method, array $metadata = array(), $status = 'completed') {
		$container = $this->service->create($creation_method, array_merge(array('creation_method' => $creation_method), $metadata));
		$container->record('activity', 'x', array('event_type' => 'e', 'event_status' => 'success'));
		$this->set_status($container->get_id(), $status);

		return (int) $container->get_id();
	}

	private function ids_for(array $args) {
		$result = $this->repository->get_history(array_merge(array('per_page' => 100, 'fields' => 'list'), $args));

		return array_map('intval', wp_list_pluck($result['items'], 'id'));
	}

	// -- Post review domain -------------------------------------------------------

	public function test_review_actions_are_hidden_by_default_but_listed_in_the_post_review_domain() {
		$review    = $this->row('post_review_action', array('post_id' => 11));
		$lifecycle = $this->row('schedule_lifecycle', array('schedule_id' => 1));
		$run       = $this->row('post_generation', array('post_id' => 12));

		$default = $this->ids_for(array());
		$this->assertNotContains($review, $default);
		$this->assertContains($run, $default);

		$domain = $this->ids_for(array('domain' => 'post_review'));
		$this->assertContains($review, $domain);
		$this->assertNotContains($run, $domain);
		$this->assertNotContains($lifecycle, $domain, 'Other lifecycle rows stay hidden.');
	}

	public function test_review_actions_do_not_leak_into_stats() {
		$this->row('post_review_action', array('post_id' => 11), 'failed');

		$this->assertSame(0, $this->repository->get_stats()['failed']);
	}

	// -- get_by_post_id ------------------------------------------------------------

	public function test_get_by_post_id_prefers_the_generation_row_over_newer_event_rows() {
		$generation = $this->row('post_generation', array('post_id' => 21));
		$this->row('post_review_action', array('post_id' => 21));
		$this->row('topic_post_generation', array('post_id' => 21));

		$this->assertSame($generation, (int) $this->repository->get_by_post_id(21)->id);
	}

	public function test_get_by_post_id_is_null_when_only_event_rows_exist() {
		$this->row('post_review_action', array('post_id' => 22));

		$this->assertNull($this->repository->get_by_post_id(22));
	}

	// -- post_has_history_and_completed ---------------------------------------------

	public function test_review_gate_accepts_completed_and_partial_generations() {
		$this->row('post_generation', array('post_id' => 31), 'completed');
		$this->row('post_generation', array('post_id' => 32), 'partial');
		$this->row('post_generation', array('post_id' => 33), 'failed');
		$this->row('post_generation', array('post_id' => 34), 'processing');

		$this->assertTrue($this->repository->post_has_history_and_completed(31));
		$this->assertTrue($this->repository->post_has_history_and_completed(32), 'A partial draft must stay publishable.');
		$this->assertFalse($this->repository->post_has_history_and_completed(33));
		$this->assertFalse($this->repository->post_has_history_and_completed(34));
	}

	public function test_review_gate_ignores_completed_event_rows() {
		$this->row('post_review_action', array('post_id' => 35), 'completed');
		$this->row('topic_post_generation', array('post_id' => 35), 'completed');

		$this->assertFalse($this->repository->post_has_history_and_completed(35));
	}

	// -- find_incomplete -------------------------------------------------------------

	public function test_find_incomplete_is_not_crowded_out_by_other_open_rows() {
		$target = $this->service->create('author_embeddings', array('author_id' => 5, 'creation_method' => 'author_embeddings'));
		$this->set_status($target->get_id(), 'processing');

		// More than the old 20-row window of newer, unrelated in-progress rows.
		for ($i = 0; $i < 25; $i++) {
			$this->service->create('post_generation', array('topic_id' => 100 + $i, 'creation_method' => 'post_generation'));
		}

		$found = $this->service->find_incomplete('author_embeddings', array('author_id' => 5));

		$this->assertNotNull($found);
		$this->assertSame((int) $target->get_id(), (int) $found->get_id());
	}

	public function test_find_incomplete_can_resume_a_stalled_container() {
		global $wpdb;

		$target = $this->service->create('author_embeddings', array('author_id' => 6, 'creation_method' => 'author_embeddings'));
		$then   = AIPS_DateTime::now()->timestamp() - 12 * HOUR_IN_SECONDS;
		$wpdb->update($wpdb->prefix . 'aips_history', array('created_at' => $then), array('id' => $target->get_id()));

		$found = $this->service->find_incomplete('author_embeddings', array('author_id' => 6));

		$this->assertNotNull($found, 'A stalled container is still open and resumable.');
		$this->assertSame((int) $target->get_id(), (int) $found->get_id());
	}

	public function test_find_incomplete_matches_creation_method() {
		$this->service->create('content_indexing', array('author_id' => 7, 'creation_method' => 'content_indexing'));

		$this->assertNull($this->service->find_incomplete('author_embeddings', array('author_id' => 7)));
	}

	public function test_find_incomplete_still_resumes_legacy_rows_without_a_creation_method() {
		global $wpdb;

		$legacy = $this->service->create('author_embeddings', array('author_id' => 8));
		$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}aips_history SET creation_method = '' WHERE id = %d", $legacy->get_id()));

		$found = $this->service->find_incomplete('author_embeddings', array('author_id' => 8));

		$this->assertNotNull($found);
		$this->assertSame((int) $legacy->get_id(), (int) $found->get_id());
	}

	// -- 'generated' status filter -----------------------------------------------------

	public function test_generated_filter_includes_completed_and_partial_only() {
		$completed = $this->row('post_generation', array('post_id' => 41), 'completed');
		$partial   = $this->row('post_generation', array('post_id' => 42), 'partial');
		$failed    = $this->row('post_generation', array('post_id' => 43), 'failed');

		$ids = $this->ids_for(array('status' => 'generated'));

		$this->assertContains($completed, $ids);
		$this->assertContains($partial, $ids);
		$this->assertNotContains($failed, $ids);
		$this->assertSame(array($partial), $this->ids_for(array('status' => 'partial')));
	}

	// -- Partial status sync -------------------------------------------------------------

	public function test_sync_moves_the_latest_generation_row_between_completed_and_partial() {
		$id = $this->row('post_generation', array('post_id' => 51), 'completed');

		$this->assertTrue($this->repository->sync_generation_status_for_post(51, true));
		$this->assertSame('partial', $this->status_of($id));

		$this->assertFalse($this->repository->sync_generation_status_for_post(51, true), 'No change when already partial.');

		$this->assertTrue($this->repository->sync_generation_status_for_post(51, false));
		$this->assertSame('completed', $this->status_of($id));
	}

	public function test_sync_never_touches_failed_or_in_progress_rows() {
		$failed = $this->row('post_generation', array('post_id' => 52), 'failed');
		$this->assertFalse($this->repository->sync_generation_status_for_post(52, true));
		$this->assertSame('failed', $this->status_of($failed));

		$open = $this->row('post_generation', array('post_id' => 53), 'processing');
		$this->assertFalse($this->repository->sync_generation_status_for_post(53, true));
		$this->assertSame('processing', $this->status_of($open));
	}

	public function test_reconciler_hook_syncs_history_status() {
		new AIPS_Partial_Generation_State_Reconciler();
		$id = $this->row('post_generation', array('post_id' => 54), 'completed');

		do_action('aips_partial_generation_state_reconciled', 54, array('post_title' => true, 'featured_image' => false), 'test');
		$this->assertSame('partial', $this->status_of($id));

		do_action('aips_partial_generation_state_reconciled', 54, array('post_title' => true, 'featured_image' => true), 'test');
		$this->assertSame('completed', $this->status_of($id));
	}

	public function test_partial_generations_list_includes_partial_rows() {
		$post_id = self::factory()->post->create(array('post_status' => 'draft'));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');
		$this->row('post_generation', array('post_id' => $post_id), 'partial');

		$result = $this->repository->get_partial_generations(array('per_page' => 50));
		$post_ids = array_map('intval', wp_list_pluck($result['items'], 'post_id'));

		$this->assertContains($post_id, $post_ids);
	}

	public function test_backfill_marks_only_the_newest_generation_row_of_incomplete_posts() {
		$incomplete = self::factory()->post->create(array('post_status' => 'draft'));
		update_post_meta($incomplete, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');
		$complete = self::factory()->post->create(array('post_status' => 'draft'));
		update_post_meta($complete, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'false');

		$old     = $this->row('post_generation', array('post_id' => $incomplete), 'completed');
		$latest  = $this->row('post_generation', array('post_id' => $incomplete), 'completed');
		$event   = $this->row('post_review_action', array('post_id' => $incomplete), 'completed');
		$fine    = $this->row('post_generation', array('post_id' => $complete), 'completed');

		$changed = $this->repository->backfill_partial_generation_status();

		$this->assertSame(1, $changed);
		$this->assertSame('partial', $this->status_of($latest));
		$this->assertSame('completed', $this->status_of($old));
		$this->assertSame('completed', $this->status_of($event));
		$this->assertSame('completed', $this->status_of($fine));
	}

	// -- Queue health ----------------------------------------------------------------------

	public function test_partial_runs_are_not_counted_as_stuck() {
		global $wpdb;

		$id   = $this->row('post_generation', array('post_id' => 61), 'partial');
		$then = AIPS_DateTime::now()->timestamp() - 5 * DAY_IN_SECONDS;
		$wpdb->update($wpdb->prefix . 'aips_history', array('created_at' => $then), array('id' => $id));

		$health = (new AIPS_Metrics_Repository())->get_queue_health_metrics();

		$this->assertSame(0, $health['stuck_count']);
		$this->assertSame(1, $health['partial_count']);
	}

	public function test_stalled_runs_are_counted_as_stuck_and_lifecycle_containers_are_not() {
		global $wpdb;

		$stalled   = $this->row('post_generation', array('topic_id' => 1), 'processing');
		$lifecycle = $this->row('schedule_lifecycle', array('schedule_id' => 1), 'processing');
		$then      = AIPS_DateTime::now()->timestamp() - 3 * HOUR_IN_SECONDS;
		foreach (array($stalled, $lifecycle) as $id) {
			$wpdb->update($wpdb->prefix . 'aips_history', array('created_at' => $then), array('id' => $id));
			$wpdb->update($wpdb->prefix . 'aips_history_log', array('timestamp' => $then), array('history_id' => $id));
		}

		$health = (new AIPS_Metrics_Repository())->get_queue_health_metrics();

		$this->assertSame(1, $health['stuck_count']);
		$this->assertGreaterThanOrEqual(179, $health['oldest_stuck_age_minutes']);
	}
}
