<?php
/**
 * Tests that one-shot History event containers are closed instead of being left
 * in 'processing', and that orphaned/stale rows can be repaired.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_History_Event_Closure extends WP_UnitTestCase {

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

		return $wpdb->get_var($wpdb->prepare(
			"SELECT status FROM {$wpdb->prefix}aips_history WHERE id = %d",
			$history_id
		));
	}

	private function row($history_id) {
		global $wpdb;

		return $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}aips_history WHERE id = %d",
			$history_id
		));
	}

	/**
	 * Create a container with one event log and leave it open.
	 */
	private function open_event_container($creation_method, $event_status, array $metadata = array()) {
		$container = $this->service->create($creation_method, $metadata);
		$container->record(
			'activity',
			'Event',
			array('event_type' => 'post_published', 'event_status' => $event_status),
			null,
			array()
		);

		return $container;
	}

	/**
	 * Make a row (and its logs) look old.
	 */
	private function age_row($history_id, $seconds) {
		global $wpdb;

		$then = AIPS_DateTime::now()->timestamp() - $seconds;
		$wpdb->update($wpdb->prefix . 'aips_history', array('created_at' => $then), array('id' => $history_id));
		$wpdb->update($wpdb->prefix . 'aips_history_log', array('timestamp' => $then), array('history_id' => $history_id));
	}

	// -- Container completion -----------------------------------------------------

	public function test_new_containers_start_as_processing() {
		$container = $this->service->create('post_review_action', array('post_id' => 1));

		$this->assertSame('processing', $this->status_of($container->get_id()));
	}

	public function test_complete_success_cannot_override_status() {
		$container = $this->service->create('settings_connection_test', array('user_id' => 1));
		$container->complete_success(array('status' => 'success'));

		$this->assertSame('completed', $this->status_of($container->get_id()));
	}

	public function test_complete_partial_sets_partial_status_message_and_completion_time() {
		$container = $this->service->create('stress_test', array('source' => 'test'));
		$this->assertTrue($container->complete_partial('2/3 passed', array('status' => 'completed')));

		$row = $this->row($container->get_id());
		$this->assertSame('partial', $row->status);
		$this->assertSame('2/3 passed', $row->error_message);
		$this->assertGreaterThan(0, (int) $row->completed_at);
	}

	// -- Post review actions ------------------------------------------------------

	private function review_instance() {
		$review = (new ReflectionClass('AIPS_Post_Review'))->newInstanceWithoutConstructor();
		$prop   = new ReflectionProperty('AIPS_Post_Review', 'history_service');
		$prop->setAccessible(true);
		$prop->setValue($review, $this->service);

		return $review;
	}

	private function call_private($object, $method, array $args = array()) {
		$m = new ReflectionMethod($object, $method);
		$m->setAccessible(true);

		return $m->invoke($object, ...$args);
	}

	private function latest_review_row() {
		global $wpdb;

		return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}aips_history WHERE creation_method = 'post_review_action' ORDER BY id DESC LIMIT 1");
	}

	public function test_review_action_success_is_closed_as_completed() {
		$this->call_private($this->review_instance(), 'log_review_action', array(42, 'Post published from review queue', 'post_published', 'success'));

		$row = $this->latest_review_row();
		$this->assertSame('completed', $row->status);
		$this->assertSame(42, (int) $row->post_id);
		$this->assertGreaterThan(0, (int) $row->completed_at);
	}

	public function test_review_action_failure_is_closed_as_failed() {
		$this->call_private($this->review_instance(), 'log_review_action', array(0, 'Post publish failed: Permission denied', 'post_published', 'failed'));

		$row = $this->latest_review_row();
		$this->assertSame('failed', $row->status);
		$this->assertSame('Post publish failed: Permission denied', $row->error_message);
	}

	public function test_bulk_review_container_status_reflects_outcomes() {
		$review = $this->review_instance();

		foreach (array(
			array(0, 3, 'completed'),
			array(1, 2, 'partial'),
			array(3, 0, 'failed'),
		) as $case) {
			list($failed, $succeeded, $expected) = $case;
			$container = $this->service->create('post_review_action', array());
			$this->call_private($review, 'close_review_container', array($container, $failed, $succeeded, 'Summary'));

			$this->assertSame($expected, $this->status_of($container->get_id()), "failed={$failed} succeeded={$succeeded}");
		}
	}

	public function test_post_review_actions_are_hidden_lifecycle_rows() {
		$this->assertContains('post_review_action', AIPS_History_Event_Recorder::lifecycle_creation_methods());

		$container = $this->service->create('post_review_action', array('post_id' => 7));
		$container->complete_success();

		$listed = $this->repository->get_history(array('per_page' => 100, 'fields' => 'list'));
		$ids    = array_map('intval', wp_list_pluck($listed['items'], 'id'));
		$this->assertNotContains((int) $container->get_id(), $ids);
	}

	// -- Repair: orphaned event rows ----------------------------------------------

	public function test_close_orphaned_event_rows_uses_logged_outcome() {
		$ok     = $this->open_event_container('post_review_action', 'success', array('post_id' => 5));
		$failed = $this->open_event_container('post_review_action', 'failed', array('post_id' => 6));
		$other  = $this->open_event_container('author_embeddings', 'success', array('author_id' => 1));

		$closed = $this->repository->close_orphaned_event_rows(array('post_review_action'));

		$this->assertSame(2, $closed);
		$this->assertSame('completed', $this->status_of($ok->get_id()));
		$this->assertSame('failed', $this->status_of($failed->get_id()));
		$this->assertSame('processing', $this->status_of($other->get_id()), 'Other creation methods must be untouched.');
	}

	public function test_close_orphaned_event_rows_ignores_already_closed_rows() {
		$done = $this->open_event_container('post_review_action', 'failed', array('post_id' => 8));
		$done->complete_success();

		$this->assertSame(0, $this->repository->close_orphaned_event_rows(array('post_review_action')));
		$this->assertSame('completed', $this->status_of($done->get_id()));
	}

	public function test_close_orphaned_event_rows_sets_completed_at_to_last_activity() {
		$container = $this->open_event_container('topic_post_generation', 'success', array('topic_id' => 3));
		$this->age_row($container->get_id(), 7200);
		$last_activity = AIPS_DateTime::now()->timestamp() - 7200;

		$this->repository->close_orphaned_event_rows(array('topic_post_generation'));

		$this->assertSame($last_activity, (int) $this->row($container->get_id())->completed_at);
	}

	// -- Repair: stale rows -------------------------------------------------------

	public function test_close_stale_processing_rows_terminates_idle_rows_and_keeps_them() {
		$stale = $this->open_event_container('author_embeddings', 'success', array('author_id' => 1));
		$this->age_row($stale->get_id(), 3 * HOUR_IN_SECONDS);

		$closed = $this->repository->close_stale_processing_rows(HOUR_IN_SECONDS);

		$this->assertSame(1, $closed);
		$row = $this->row($stale->get_id());
		$this->assertNotNull($row, 'Stale rows are closed, never deleted.');
		$this->assertSame('terminated', $row->status);
		$this->assertNotEmpty($row->error_message);
	}

	public function test_close_stale_processing_rows_leaves_recently_active_rows() {
		$old = $this->service->create('author_embeddings', array('author_id' => 2));
		$this->age_row($old->get_id(), 3 * HOUR_IN_SECONDS);
		// Fresh log activity on an old row means the job is still working.
		$old->record('activity', 'Batch completed', array('event_type' => 'embeddings_batch_complete', 'event_status' => 'success'));

		$this->assertSame(0, $this->repository->close_stale_processing_rows(HOUR_IN_SECONDS));
		$this->assertSame('processing', $this->status_of($old->get_id()));
	}

	public function test_close_stale_processing_rows_respects_excluded_methods() {
		$lifecycle = $this->open_event_container('schedule_lifecycle', 'success', array('schedule_id' => 1));
		$this->age_row($lifecycle->get_id(), 30 * DAY_IN_SECONDS);

		$closed = $this->repository->close_stale_processing_rows(
			HOUR_IN_SECONDS,
			AIPS_History_Event_Recorder::lifecycle_creation_methods()
		);

		$this->assertSame(0, $closed);
		$this->assertSame('processing', $this->status_of($lifecycle->get_id()));
	}

	public function test_close_stale_processing_rows_has_a_minimum_threshold() {
		$fresh = $this->open_event_container('author_embeddings', 'success', array('author_id' => 4));

		// A zero/negative threshold is clamped so a running job is never closed.
		$this->assertSame(0, $this->repository->close_stale_processing_rows(0));
		$this->assertSame('processing', $this->status_of($fresh->get_id()));
	}

	// -- Clear In-Progress closes only what is shown as Stalled ------------------------

	public function test_close_stalled_processing_rows_matches_the_stalled_status() {
		$stalled = $this->open_event_container('post_generation', 'success', array('topic_id' => 1));
		$this->age_row($stalled->get_id(), 2 * HOUR_IN_SECONDS);
		$fresh = $this->open_event_container('post_generation', 'success', array('topic_id' => 2));
		// Chained jobs are only stalled after 6 hours, so one hour idle is still in progress.
		$chain = $this->open_event_container('author_embeddings', 'success', array('author_id' => 1));
		$this->age_row($chain->get_id(), HOUR_IN_SECONDS);
		$lifecycle = $this->open_event_container('schedule_lifecycle', 'success', array('schedule_id' => 1));
		$this->age_row($lifecycle->get_id(), 30 * DAY_IN_SECONDS);

		$closed = $this->repository->close_stalled_processing_rows(AIPS_History_Event_Recorder::lifecycle_creation_methods());

		$this->assertSame(1, $closed);
		$this->assertSame('terminated', $this->status_of($stalled->get_id()));
		$this->assertSame('processing', $this->status_of($fresh->get_id()));
		$this->assertSame('processing', $this->status_of($chain->get_id()));
		$this->assertSame('processing', $this->status_of($lifecycle->get_id()));
	}

	public function test_close_stalled_processing_rows_leaves_stalled_jobs_and_events_untouched() {
		$stalled_run = $this->open_event_container('post_generation', 'success', array('topic_id' => 1));
		$this->age_row($stalled_run->get_id(), 2 * HOUR_IN_SECONDS);
		// Chained job idle 8 hours (past 6 hour threshold, so stalled).
		$stalled_job = $this->open_event_container('author_embeddings', 'success', array('author_id' => 1));
		$this->age_row($stalled_job->get_id(), 8 * HOUR_IN_SECONDS);
		// Open lifecycle container idle 30 days.
		$lifecycle = $this->open_event_container('schedule_lifecycle', 'success', array('schedule_id' => 1));
		$this->age_row($lifecycle->get_id(), 30 * DAY_IN_SECONDS);

		// Default close_stalled_processing_rows closes generation runs only.
		$closed = $this->repository->close_stalled_processing_rows();

		$this->assertSame(1, $closed);
		$this->assertSame('terminated', $this->status_of($stalled_run->get_id()));
		$this->assertSame('processing', $this->status_of($stalled_job->get_id()), 'Stalled background jobs must not be closed by Clear In-Progress runs.');
		$this->assertSame('processing', $this->status_of($lifecycle->get_id()), 'Lifecycle events must never be closed by stalled cleanup.');
	}

	public function test_clear_in_progress_ajax_terminates_stalled_rows_and_deletes_nothing() {
		$stalled = $this->open_event_container('post_generation', 'success', array('topic_id' => 1));
		$this->age_row($stalled->get_id(), 2 * HOUR_IN_SECONDS);
		$chain = $this->open_event_container('author_embeddings', 'success', array('author_id' => 1));
		$this->age_row($chain->get_id(), HOUR_IN_SECONDS);

		wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));
		$_POST = array('nonce' => wp_create_nonce('aips_ajax_nonce'), 'status' => 'processing');
		$_REQUEST = $_POST;

		ob_start();
		try {
			(new AIPS_History())->ajax_clear_history();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		} catch (WPAjaxDieStopException $e) {
			// Expected.
		}
		$response = json_decode(strtok(trim((string) ob_get_clean()), "\r\n"), true);
		$_POST = array();
		$_REQUEST = array();

		$this->assertTrue($response['success']);
		$this->assertSame(1, $response['data']['closed']);
		$this->assertSame('terminated', $this->status_of($stalled->get_id()), 'Stalled rows are kept, not deleted.');
		$this->assertSame('processing', $this->status_of($chain->get_id()), 'A chained job still shown as Processing is left alone.');
	}

	// -- Review events go through the shared recorder ------------------------------------

	public function test_review_action_records_trigger_entries_and_closes_the_container() {
		$this->call_private($this->review_instance(), 'log_review_action', array(77, 'Post published from review queue', 'post_published', 'success'));

		global $wpdb;
		$row      = $this->latest_review_row();
		$subtypes = array();
		foreach ($wpdb->get_col($wpdb->prepare("SELECT details FROM {$wpdb->prefix}aips_history_log WHERE history_id = %d ORDER BY id ASC", $row->id)) as $details) {
			$decoded    = json_decode($details, true);
			$subtypes[] = $decoded['log_subtype'];
		}

		$this->assertSame(array('trigger_source', 'trigger_method', 'activity'), $subtypes);
		$this->assertSame('completed', $row->status);
	}
}
