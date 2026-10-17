<?php
/**
 * Tests for the History status sync (completed <-> partial).
 *
 * A post's component state belongs to its generation run. Indexing, other
 * background jobs and event containers carry the same post_id, are always newer
 * than the run, and used to be picked as "the post's latest row", so the status
 * was written to the wrong row.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Status_Sync extends WP_UnitTestCase {

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
	 * @param string|null $incomplete Value of the incomplete meta flag, or null for none.
	 */
	private function make_post($incomplete = null) {
		$post_id = self::factory()->post->create(array('post_status' => 'draft'));
		$this->post_ids[] = $post_id;
		if (null !== $incomplete) {
			update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, $incomplete);
		}

		return (int) $post_id;
	}

	private function insert($post_id, $creation_method, $status) {
		global $wpdb;

		$wpdb->insert($wpdb->prefix . 'aips_history', array(
			'post_id'         => $post_id,
			'creation_method' => $creation_method,
			'status'          => $status,
			'record_kind'     => AIPS_History_Record_Kind::for_creation_method($creation_method, array('post_id' => $post_id)),
			'created_at'      => time(),
			'completed_at'    => time(),
		));
		$id                  = (int) $wpdb->insert_id;
		$this->history_ids[] = $id;

		return $id;
	}

	private function status($history_id) {
		global $wpdb;

		return $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}aips_history WHERE id = %d", $history_id));
	}

	// ------------------------------------------------------------ sync

	public function test_sync_moves_the_run_not_a_newer_indexing_row() {
		$post_id  = $this->make_post();
		$run      = $this->insert($post_id, 'scheduled', 'completed');
		$indexing = $this->insert($post_id, 'content_indexing', 'completed');

		$this->assertTrue($this->history->sync_generation_status_for_post($post_id, true));

		$this->assertSame('partial', $this->status($run));
		$this->assertSame('completed', $this->status($indexing), 'Indexing never ends partial.');
	}

	public function test_sync_restores_the_run_when_components_are_fixed() {
		$post_id  = $this->make_post();
		$run      = $this->insert($post_id, 'manual', 'partial');
		$indexing = $this->insert($post_id, 'content_indexing', 'completed');

		$this->assertTrue($this->history->sync_generation_status_for_post($post_id, false));

		$this->assertSame('completed', $this->status($run));
		$this->assertSame('completed', $this->status($indexing));
	}

	public function test_sync_is_not_blocked_by_a_newer_failed_indexing_row() {
		$post_id = $this->make_post();
		$run     = $this->insert($post_id, 'scheduled', 'completed');
		$failed  = $this->insert($post_id, 'content_indexing', 'failed');

		$this->assertTrue($this->history->sync_generation_status_for_post($post_id, true));

		$this->assertSame('partial', $this->status($run));
		$this->assertSame('failed', $this->status($failed));
	}

	public function test_sync_ignores_event_rows() {
		$post_id = $this->make_post();
		$run     = $this->insert($post_id, 'scheduled', 'completed');
		$events  = array(
			$this->insert($post_id, 'topic_post_generation', 'completed'),
			$this->insert($post_id, 'post_review_action', 'completed'),
			$this->insert($post_id, 'notification_sent', 'completed'),
		);

		$this->history->sync_generation_status_for_post($post_id, true);

		$this->assertSame('partial', $this->status($run));
		foreach ($events as $event_id) {
			$this->assertSame('completed', $this->status($event_id));
		}
	}

	public function test_sync_updates_the_last_good_run_and_leaves_a_failed_regeneration_alone() {
		$post_id = $this->make_post();
		$good    = $this->insert($post_id, 'scheduled', 'completed');
		$failed  = $this->insert($post_id, 'regenerate', 'failed');

		$this->history->sync_generation_status_for_post($post_id, true);

		$this->assertSame('partial', $this->status($good), 'The failed regeneration did not change the post, so the last good run carries its state.');
		$this->assertSame('failed', $this->status($failed));
	}

	public function test_sync_only_touches_the_requested_post() {
		$first  = $this->make_post();
		$second = $this->make_post();
		$first_run  = $this->insert($first, 'scheduled', 'completed');
		$second_run = $this->insert($second, 'scheduled', 'completed');

		$this->history->sync_generation_status_for_post($first, true);

		$this->assertSame('partial', $this->status($first_run));
		$this->assertSame('completed', $this->status($second_run));
	}

	public function test_sync_reports_no_change_when_already_in_step() {
		$post_id = $this->make_post();
		$this->insert($post_id, 'scheduled', 'completed');

		$this->assertFalse($this->history->sync_generation_status_for_post($post_id, false));
		$this->assertFalse($this->history->sync_generation_status_for_post(0, true));
	}

	// ------------------------------------------------------------ backfill

	public function test_backfill_marks_the_run_not_the_indexing_row() {
		$post_id  = $this->make_post('true');
		$run      = $this->insert($post_id, 'scheduled', 'completed');
		$indexing = $this->insert($post_id, 'content_indexing', 'completed');

		$this->history->backfill_partial_generation_status();

		$this->assertSame('partial', $this->status($run));
		$this->assertSame('completed', $this->status($indexing));
	}

	// ------------------------------------------------------------ repair

	public function test_repair_moves_the_status_from_the_indexing_row_to_the_run() {
		// The state the old sync left behind: the indexing row partial, the run still completed.
		$post_id  = $this->make_post('true');
		$run      = $this->insert($post_id, 'scheduled', 'completed');
		$indexing = $this->insert($post_id, 'content_indexing', 'partial');

		$repaired = $this->history->repair_generation_statuses();

		$this->assertGreaterThanOrEqual(1, $repaired['jobs_reset']);
		$this->assertGreaterThanOrEqual(1, $repaired['marked_partial']);
		$this->assertSame('completed', $this->status($indexing));
		$this->assertSame('partial', $this->status($run));
	}

	public function test_repair_leaves_run_partials_and_event_rows_alone() {
		$post_id = $this->make_post('true');
		$run     = $this->insert($post_id, 'scheduled', 'partial');
		$summary = $this->insert($post_id, 'post_review_action', 'partial');

		$this->history->repair_generation_statuses();

		$this->assertSame('partial', $this->status($run));
		$this->assertSame('partial', $this->status($summary), 'Event rows are not background jobs and are not reset.');
	}

	public function test_repair_only_resets_content_indexing_rows_not_other_partial_rows() {
		// A real run under a creation_method the allowlist does not name, and another job.
		$post_id   = $this->make_post('true');
		$custom    = $this->insert($post_id, 'extension_generation', 'partial');
		$bulk_slice = $this->insert($post_id, 'bulk_batch_slice', 'partial');

		$repaired = $this->history->repair_generation_statuses();

		$this->assertSame('partial', $this->status($custom), 'A legitimately partial run under an unlisted method must survive.');
		$this->assertSame('partial', $this->status($bulk_slice));
		$this->assertSame(0, $repaired['jobs_reset']);
		$this->assertSame(0, $repaired['errors']);
	}

	public function test_repair_marks_repaired_posts_completed_and_ignores_posts_without_a_flag() {
		$repaired_post = $this->make_post('false');
		$repaired_run  = $this->insert($repaired_post, 'scheduled', 'partial');
		$unflagged     = $this->make_post();
		$unflagged_run = $this->insert($unflagged, 'scheduled', 'partial');

		$this->history->repair_generation_statuses();

		$this->assertSame('completed', $this->status($repaired_run));
		$this->assertSame('partial', $this->status($unflagged_run), 'Without the incomplete flag there is nothing to reconcile against.');
	}

	public function test_repair_is_idempotent() {
		$post_id = $this->make_post('true');
		$this->insert($post_id, 'scheduled', 'completed');
		$this->insert($post_id, 'content_indexing', 'partial');

		$this->history->repair_generation_statuses();
		$second = $this->history->repair_generation_statuses();

		$this->assertSame(array('jobs_reset' => 0, 'marked_partial' => 0, 'marked_completed' => 0, 'errors' => 0), $second);
	}
}
