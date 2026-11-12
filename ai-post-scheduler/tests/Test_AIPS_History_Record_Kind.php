<?php
/**
 * Tests for the History record_kind column: the classifier, how rows get their
 * kind when written, the schema, and the backfill of existing rows.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Record_Kind extends WP_UnitTestCase {

	/** @var AIPS_History_Repository */
	private $history;

	/** @var int[] */
	private $history_ids = array();

	public function setUp(): void {
		parent::setUp();
		$this->history = new AIPS_History_Repository();
	}

	public function tearDown(): void {
		global $wpdb;

		foreach ($this->history_ids as $history_id) {
			$wpdb->delete($wpdb->prefix . 'aips_history', array('id' => $history_id), array('%d'));
		}

		parent::tearDown();
	}

	/**
	 * Insert a row the way an old install has it: record_kind left at the column default.
	 *
	 * @return int History ID.
	 */
	private function insert_unclassified($creation_method, $status = 'completed', array $extra = array()) {
		global $wpdb;

		$row = array_merge(array(
			'status'       => $status,
			'created_at'   => time(),
			'completed_at' => time(),
		), $extra);
		if (null !== $creation_method) {
			$row['creation_method'] = $creation_method;
		}

		$wpdb->insert($wpdb->prefix . 'aips_history', $row);
		$id                  = (int) $wpdb->insert_id;
		$this->history_ids[] = $id;

		return $id;
	}

	private function kind_of($history_id) {
		global $wpdb;

		return $wpdb->get_var($wpdb->prepare("SELECT record_kind FROM {$wpdb->prefix}aips_history WHERE id = %d", $history_id));
	}

	// ------------------------------------------------------------ classifier

	public function test_classifier_names_each_kind() {
		foreach (array('scheduled', 'manual', 'regenerate', 'retry', 'bulk_generate', 'post_generation') as $method) {
			$this->assertSame('generation', AIPS_History_Record_Kind::for_creation_method($method), $method);
		}
		foreach (array('content_indexing', 'author_embeddings', 'author_topic_generation', 'source_fetch', 'bulk_batch_slice', 'content_index_scan', 'internal_link_rebuild') as $method) {
			$this->assertSame('job', AIPS_History_Record_Kind::for_creation_method($method), $method);
		}
		foreach (array('topic_post_generation', 'post_review_action', 'notification_sent', 'schedule_lifecycle', 'bulk_delete', 'bulk_schedule', 'manual_generation', 'manual_regeneration') as $method) {
			$this->assertSame('event', AIPS_History_Record_Kind::for_creation_method($method), $method);
		}
	}

	public function test_classifier_defaults_unknown_methods_to_event() {
		$this->assertSame('event', AIPS_History_Record_Kind::for_creation_method('something_new'));
	}

	public function test_classifier_reads_an_empty_method_from_linkage() {
		$this->assertSame('generation', AIPS_History_Record_Kind::for_creation_method(null, array('post_id' => 5)));
		$this->assertSame('generation', AIPS_History_Record_Kind::for_creation_method('', array('template_id' => 2)));
		$this->assertSame('generation', AIPS_History_Record_Kind::for_creation_method(null, array('topic_id' => 3)));
		$this->assertSame('event', AIPS_History_Record_Kind::for_creation_method(null, array()), 'An empty shell is not a run.');
	}

	public function test_resolve_prefers_a_valid_explicit_kind() {
		$this->assertSame('job', AIPS_History_Record_Kind::resolve(array('creation_method' => 'bulk_generate', 'record_kind' => 'job')));
		$this->assertSame('generation', AIPS_History_Record_Kind::resolve(array('creation_method' => 'extension_run', 'record_kind' => 'generation')));
		$this->assertSame('generation', AIPS_History_Record_Kind::resolve(array('creation_method' => 'scheduled', 'record_kind' => 'bogus')), 'An invalid kind is ignored.');
		$this->assertSame('event', AIPS_History_Record_Kind::resolve(array('creation_method' => 'notification_sent')));
	}

	// ------------------------------------------------------------ writing

	public function test_create_stores_the_classified_kind() {
		$run   = $this->history->create(array('creation_method' => 'scheduled', 'status' => 'completed'));
		$job   = $this->history->create(array('creation_method' => 'content_indexing', 'status' => 'completed'));
		$event = $this->history->create(array('creation_method' => 'notification_sent', 'status' => 'completed'));
		$this->history_ids = array_merge($this->history_ids, array($run, $job, $event));

		$this->assertSame('generation', $this->kind_of($run));
		$this->assertSame('job', $this->kind_of($job));
		$this->assertSame('event', $this->kind_of($event));
	}

	public function test_create_stores_an_explicit_kind_whatever_the_method() {
		$extension = $this->history->create(array('creation_method' => 'extension_run', 'record_kind' => 'generation', 'status' => 'completed'));
		$summary   = $this->history->create(array('creation_method' => 'bulk_generate', 'record_kind' => 'job', 'status' => 'completed'));
		$this->history_ids = array_merge($this->history_ids, array($extension, $summary));

		$this->assertSame('generation', $this->kind_of($extension));
		$this->assertSame('job', $this->kind_of($summary));
	}

	public function test_update_accepts_a_valid_kind_only() {
		$id = $this->history->create(array('creation_method' => 'notification_sent', 'status' => 'completed'));
		$this->history_ids[] = $id;

		$this->history->update($id, array('record_kind' => 'bogus'));
		$this->assertSame('event', $this->kind_of($id));

		$this->history->update($id, array('record_kind' => 'job'));
		$this->assertSame('job', $this->kind_of($id));
	}

	public function test_containers_pass_the_explicit_kind_through() {
		$service = new AIPS_History_Service($this->history);

		$run     = $service->create('post_generation', array('creation_method' => 'extension_run', 'record_kind' => AIPS_History_Record_Kind::GENERATION));
		$summary = $service->create('bulk_generate', array('record_kind' => AIPS_History_Record_Kind::JOB));
		$wrapper = $service->create('topic_post_generation', array());

		$run_id     = (int) $run->get_id();
		$summary_id = (int) $summary->get_id();
		$wrapper_id = (int) $wrapper->get_id();
		$this->history_ids = array_merge($this->history_ids, array($run_id, $summary_id, $wrapper_id));

		$this->assertSame('generation', $this->kind_of($run_id), 'A run is a generation whatever its creation_method.');
		$this->assertSame('job', $this->kind_of($summary_id), 'The bulk summary container shares its name with the runs but is a job.');
		$this->assertSame('event', $this->kind_of($wrapper_id));
	}

	// ------------------------------------------------------------ schema

	public function test_schema_has_the_column_and_the_index() {
		global $wpdb;

		$table   = $wpdb->prefix . 'aips_history';
		$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'record_kind'");
		$indexes = $wpdb->get_col("SHOW INDEX FROM `{$table}` WHERE Key_name = 'record_kind_status_post'", 4);

		$this->assertCount(1, $columns);
		$this->assertSame('event', $columns[0]->Default, 'Unclassified rows must default to event.');
		$this->assertSame(array('record_kind', 'status', 'post_id'), $indexes);
	}

	// ------------------------------------------------------------ backfill

	public function test_backfill_classifies_existing_rows() {
		$run        = $this->insert_unclassified('scheduled', 'completed', array('post_id' => 11));
		$manual     = $this->insert_unclassified('manual');
		$container  = $this->insert_unclassified('post_generation');
		$legacy     = $this->insert_unclassified(null, 'completed', array('template_id' => 3));
		$shell      = $this->insert_unclassified(null);
		$indexing   = $this->insert_unclassified('content_indexing');
		$topic_job  = $this->insert_unclassified('author_topic_generation');
		$slice      = $this->insert_unclassified('bulk_batch_slice');
		$wrapper    = $this->insert_unclassified('topic_post_generation');
		$review     = $this->insert_unclassified('post_review_action');
		$lifecycle  = $this->insert_unclassified('schedule_lifecycle', 'processing');
		$unknown    = $this->insert_unclassified('mystery_method');
		$bulk_delete = $this->insert_unclassified('bulk_delete');

		$result = $this->history->backfill_record_kind();

		$this->assertSame(0, $result['errors']);
		$this->assertGreaterThanOrEqual(4, $result['generation']);
		$this->assertGreaterThanOrEqual(3, $result['job']);

		foreach (array($run, $manual, $container, $legacy) as $id) {
			$this->assertSame('generation', $this->kind_of($id));
		}
		foreach (array($indexing, $topic_job, $slice) as $id) {
			$this->assertSame('job', $this->kind_of($id));
		}
		foreach (array($shell, $wrapper, $review, $lifecycle, $unknown, $bulk_delete) as $id) {
			$this->assertSame('event', $this->kind_of($id));
		}
	}

	public function test_backfill_splits_bulk_generate_rows_by_post_and_outcome() {
		$with_post  = $this->insert_unclassified('bulk_generate', 'completed', array('post_id' => 21));
		$failed_run = $this->insert_unclassified('bulk_generate', 'failed');
		$summary    = $this->insert_unclassified('bulk_generate', 'completed');

		$this->history->backfill_record_kind();

		$this->assertSame('generation', $this->kind_of($with_post));
		$this->assertSame('generation', $this->kind_of($failed_run), 'A failed Planner run has no post but is still a run.');
		$this->assertSame('job', $this->kind_of($summary), 'A completed bulk_generate row without a post is the summary container.');
	}

	public function test_backfill_keeps_unfinished_bulk_generate_rows_as_generations() {
		// A Planner run in flight has no post yet either; it must not be taken for the summary.
		$ids = array();
		foreach (array('pending', 'processing', 'terminated', 'partial') as $status) {
			$ids[$status] = $this->insert_unclassified('bulk_generate', $status);
		}

		$this->history->backfill_record_kind();

		foreach ($ids as $status => $id) {
			$this->assertSame('generation', $this->kind_of($id), $status);
		}
	}

	public function test_backfill_reports_rows_with_a_post_that_it_left_as_events() {
		$mystery = $this->insert_unclassified('mystery_run', 'completed', array('post_id' => 31));
		$wrapper = $this->insert_unclassified('topic_post_generation', 'completed', array('post_id' => 31));
		$no_post = $this->insert_unclassified('another_mystery');

		$result = $this->history->backfill_record_kind();

		$this->assertSame('event', $this->kind_of($mystery));
		$this->assertArrayHasKey('mystery_run', $result['unclassified']);
		$this->assertGreaterThanOrEqual(1, $result['unclassified']['mystery_run']);
		$this->assertArrayNotHasKey('topic_post_generation', $result['unclassified'], 'A known event type is not a surprise.');
		$this->assertArrayNotHasKey('another_mystery', $result['unclassified'], 'Without a post it cannot hide a draft.');
		$this->assertNotNull($wrapper);
		$this->assertNotNull($no_post);
	}

	/**
	 * The backfill is SQL and the classifier is PHP; they must reach the same answer, or
	 * rows written after the upgrade and rows classified during it would disagree.
	 */
	public function test_backfill_agrees_with_the_classifier_for_every_known_method() {
		$methods = array_unique(array_merge(
			array_diff(AIPS_History_Repository::generation_run_creation_methods(), array('bulk_generate')),
			AIPS_History_Record_Kind::event_creation_methods(),
			AIPS_History_Record_Kind::job_creation_methods(),
			array('content_index_scan', 'internal_link_rebuild', 'embedding_sync', 'batch_probe', 'bulk_regenerate', 'mystery_method')
		));

		$ids = array();
		foreach ($methods as $method) {
			$ids[$method] = $this->insert_unclassified($method);
		}
		$legacy_linked = $this->insert_unclassified(null, 'completed', array('template_id' => 3));
		$legacy_shell  = $this->insert_unclassified(null);

		$result = $this->history->backfill_record_kind();
		$this->assertSame(0, $result['errors']);

		foreach ($ids as $method => $id) {
			$this->assertSame(AIPS_History_Record_Kind::for_creation_method($method), $this->kind_of($id), $method);
		}
		$this->assertSame(AIPS_History_Record_Kind::for_creation_method(null, array('template_id' => 3)), $this->kind_of($legacy_linked));
		$this->assertSame(AIPS_History_Record_Kind::for_creation_method(null, array()), $this->kind_of($legacy_shell));
	}

	public function test_backfill_never_overwrites_a_kind_that_is_already_set() {
		$tagged_job = $this->insert_unclassified('scheduled', 'completed', array('record_kind' => 'job'));
		$extension  = $this->insert_unclassified('extension_run', 'completed', array('record_kind' => 'generation'));

		$this->history->backfill_record_kind();

		$this->assertSame('job', $this->kind_of($tagged_job));
		$this->assertSame('generation', $this->kind_of($extension));
	}

	public function test_backfill_is_idempotent_and_batches() {
		$ids = array();
		for ($i = 0; $i < 5; $i++) {
			$ids[] = $this->insert_unclassified('scheduled');
		}

		$first  = $this->history->backfill_record_kind(2);
		$second = $this->history->backfill_record_kind(2);

		$this->assertGreaterThanOrEqual(5, $first['generation']);
		$this->assertSame(0, $second['generation']);
		$this->assertSame(0, $second['job']);
		$this->assertSame(0, $second['errors']);
		foreach ($ids as $id) {
			$this->assertSame('generation', $this->kind_of($id));
		}
	}

	// ------------------------------------------------------------ kind_condition & query visibility

	public function test_kind_condition_builds_expected_sql() {
		$this->assertSame("record_kind = 'generation'", AIPS_History_Repository::kind_condition('generation'));
		$this->assertSame("h.record_kind = 'generation'", AIPS_History_Repository::kind_condition('generation', 'h'));
		$this->assertSame("record_kind IN ('generation', 'job')", AIPS_History_Repository::kind_condition(array('generation', 'job')));
		$this->assertSame("h.record_kind IN ('generation', 'job')", AIPS_History_Repository::kind_condition(array('generation', 'job'), 'h'));
		$this->assertSame("1=0", AIPS_History_Repository::kind_condition('invalid_kind'));
	}

	public function test_default_history_list_excludes_all_event_kinds() {
		$run          = $this->history->create(array('creation_method' => 'scheduled', 'status' => 'completed', 'record_kind' => 'generation'));
		$job          = $this->history->create(array('creation_method' => 'content_indexing', 'status' => 'completed', 'record_kind' => 'job'));
		$wrapper      = $this->history->create(array('creation_method' => 'manual_generation', 'status' => 'completed', 'record_kind' => 'event'));
		$topic_gen    = $this->history->create(array('creation_method' => 'topic_post_generation', 'status' => 'completed', 'record_kind' => 'event'));
		$review       = $this->history->create(array('creation_method' => 'post_review_action', 'status' => 'completed', 'record_kind' => 'event'));
		$notification = $this->history->create(array('creation_method' => 'notification_sent', 'status' => 'completed', 'record_kind' => 'event'));

		$this->history_ids = array_merge($this->history_ids, array($run, $job, $wrapper, $topic_gen, $review, $notification));

		$default_list = $this->history->get_history();
		$default_ids  = wp_list_pluck($default_list['items'], 'id');

		$this->assertContains((string) $run, $default_ids);
		$this->assertContains((string) $job, $default_ids);
		$this->assertNotContains((string) $wrapper, $default_ids);
		$this->assertNotContains((string) $topic_gen, $default_ids);
		$this->assertNotContains((string) $review, $default_ids);
		$this->assertNotContains((string) $notification, $default_ids);

		// Explicit review domain includes the review action.
		$review_list = $this->history->get_history(array('domain' => 'post_review'));
		$review_ids  = wp_list_pluck($review_list['items'], 'id');
		$this->assertContains((string) $review, $review_ids);

		// Explicit record_kind=event includes all events.
		$event_list = $this->history->get_history(array('record_kind' => 'event'));
		$event_ids  = wp_list_pluck($event_list['items'], 'id');
		$this->assertContains((string) $wrapper, $event_ids);
		$this->assertContains((string) $topic_gen, $event_ids);
		$this->assertContains((string) $review, $event_ids);
		$this->assertContains((string) $notification, $event_ids);
		$this->assertNotContains((string) $run, $event_ids);
		$this->assertNotContains((string) $job, $event_ids);
	}
}
