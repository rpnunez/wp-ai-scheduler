<?php
/**
 * Tests that History stats, the dashboard and the metrics repository count
 * generation runs only.
 *
 * Event containers (topic-generation wrappers, review actions, notifications),
 * content-indexing rows and lifecycle containers are also stored in aips_history
 * and finish as completed/failed. Counting them inflated the totals and the
 * success rate, and counted every failure twice (the run plus its wrapper).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generation_Run_Stats extends WP_UnitTestCase {

	/** @var AIPS_History_Repository */
	private $history;

	/** @var AIPS_Metrics_Repository */
	private $metrics;

	/** @var AIPS_Dashboard_Repository */
	private $dashboard;

	/** @var int[] */
	private $history_ids = array();

	/** @var int[] */
	private $topic_ids = array();

	/** @var int Seconds-granularity counter so every dashboard call uses its own cache key. */
	private $window_offset = 0;

	public function setUp(): void {
		parent::setUp();
		$this->history   = new AIPS_History_Repository();
		$this->metrics   = new AIPS_Metrics_Repository();
		$this->dashboard = new AIPS_Dashboard_Repository();
	}

	public function tearDown(): void {
		global $wpdb;

		foreach ($this->history_ids as $history_id) {
			$wpdb->delete($wpdb->prefix . 'aips_history', array('id' => $history_id), array('%d'));
		}
		foreach ($this->topic_ids as $topic_id) {
			$wpdb->delete($wpdb->prefix . 'aips_author_topics', array('id' => $topic_id), array('%d'));
		}
		$this->metrics->invalidate_cache();

		parent::tearDown();
	}

	/**
	 * Insert a History row through the repository (which invalidates its caches).
	 *
	 * @return int History ID.
	 */
	private function seed($creation_method, $status, array $extra = array()) {
		global $wpdb;

		$id = $this->history->create(array_merge(array(
			'creation_method' => $creation_method,
			'status'          => $status,
		), $extra));
		$this->history_ids[] = $id;

		if (in_array($status, array('completed', 'failed', 'partial', 'terminated'), true)) {
			$wpdb->update($wpdb->prefix . 'aips_history', array('completed_at' => time()), array('id' => $id));
		}

		return (int) $id;
	}

	/**
	 * One real generation of each outcome plus the rows that used to be counted with them.
	 *
	 * @return array{completed:int, failed:int, partial:int} The run row IDs.
	 */
	private function seed_mixed_rows() {
		$runs = array(
			'completed' => $this->seed('scheduled', 'completed'),
			'failed'    => $this->seed('scheduled', 'failed'),
			'partial'   => $this->seed('manual', 'partial'),
		);

		// The failed run's wrapper twin, and a successful wrapper.
		$this->seed('topic_post_generation', 'failed');
		$this->seed('topic_post_generation', 'completed');
		// Review action, notification, lifecycle container.
		$this->seed('post_review_action', 'completed');
		$this->seed('notification_sent', 'completed');
		$this->seed('schedule_lifecycle', 'processing');
		// Background jobs.
		$this->seed('content_indexing', 'completed');
		$this->seed('content_indexing', 'failed');
		$this->seed('content_indexing', 'partial');
		$this->seed('content_indexing', 'indexed');
		$this->seed('author_topic_generation', 'terminated');

		return $runs;
	}

	/**
	 * Start from an empty History so exact values can be asserted.
	 *
	 * The test case runs inside a transaction that is rolled back, and these tables
	 * live only in the dedicated test database. A throwaway row is created afterwards
	 * because writing through the repository is what invalidates its caches.
	 */
	private function reset_history() {
		global $wpdb;

		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_history_log");
		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_history");
		$this->seed('scheduled', 'pending');
		$this->metrics->invalidate_cache();
	}

	/**
	 * Give a finished row an exact duration ending now.
	 */
	private function set_duration($history_id, $seconds) {
		global $wpdb;

		$end = time();
		$wpdb->update($wpdb->prefix . 'aips_history', array('created_at' => $end - $seconds, 'completed_at' => $end), array('id' => $history_id));
	}

	private function window() {
		$this->window_offset++;

		return array(time() - HOUR_IN_SECONDS, time() + HOUR_IN_SECONDS + $this->window_offset);
	}

	// ------------------------------------------------------------ History stats

	public function test_history_stats_count_generation_runs_only() {
		$before = $this->history->get_stats();
		$this->seed_mixed_rows();
		$after = $this->history->get_stats();

		$this->assertSame($before['completed'] + 1, $after['completed']);
		$this->assertSame($before['failed'] + 1, $after['failed'], 'A failure must count once, not once for the run and once for its wrapper.');
		$this->assertSame($before['partial'] + 1, $after['partial']);
		$this->assertSame($before['processing'], $after['processing'], 'Lifecycle containers are not runs and must not count as in progress.');
		$this->assertSame($before['stalled'], $after['stalled']);
		$this->assertSame($before['total'] + 3, $after['total']);
	}

	public function test_history_daily_counts_count_generation_runs_only() {
		$today  = AIPS_DateTime::now()->format('Y-m-d');
		$before = $this->history->get_daily_generation_counts(1);
		$before = isset($before[$today]) ? $before[$today] : array('completed' => 0, 'failed' => 0, 'total' => 0);

		$this->seed_mixed_rows();

		$after = $this->history->get_daily_generation_counts(1);
		$after = $after[$today];

		$this->assertSame($before['completed'] + 1, $after['completed']);
		$this->assertSame($before['failed'] + 1, $after['failed']);
		$this->assertSame($before['total'] + 3, $after['total']);
	}

	// ------------------------------------------------------------ Dashboard

	public function test_dashboard_summary_stats_count_generation_runs_only() {
		list($from, $to_before) = $this->window();
		$before = $this->dashboard->get_summary_stats($from, $to_before);

		$this->seed_mixed_rows();

		list($from, $to_after) = $this->window();
		$after = $this->dashboard->get_summary_stats($from, $to_after);

		$this->assertSame($before['completed'] + 1, $after['completed']);
		$this->assertSame($before['failed'] + 1, $after['failed']);
		$this->assertSame($before['partial'] + 1, $after['partial']);
		$this->assertSame($before['processing'], $after['processing']);
		$this->assertSame($before['total'] + 3, $after['total']);
	}

	public function test_dashboard_daily_generation_stats_count_generation_runs_only() {
		$today = AIPS_DateTime::now()->format('Y-m-d');

		list($from, $to_before) = $this->window();
		$before = $this->dashboard->get_daily_generation_stats($from, $to_before);
		$before = isset($before[$today]) ? $before[$today] : array('completed' => 0, 'failed' => 0, 'total' => 0);

		$this->seed_mixed_rows();

		list($from, $to_after) = $this->window();
		$after = $this->dashboard->get_daily_generation_stats($from, $to_after);
		$after = $after[$today];

		$this->assertSame($before['completed'] + 1, $after['completed']);
		$this->assertSame($before['failed'] + 1, $after['failed']);
		$this->assertSame($before['total'] + 3, $after['total']);
	}

	public function test_dashboard_recent_posts_list_runs_not_event_rows() {
		$runs   = $this->seed_mixed_rows();
		$events = array(
			$this->seed('topic_post_generation', 'completed'),
			$this->seed('post_review_action', 'completed'),
			$this->seed('content_indexing', 'completed'),
			$this->seed('notification_sent', 'completed'),
		);

		list($from, $to) = $this->window();
		$ids = array_map('intval', wp_list_pluck($this->dashboard->get_recent_posts($from, $to, 100), 'id'));

		foreach ($runs as $run_id) {
			$this->assertContains($run_id, $ids);
		}
		foreach ($events as $event_id) {
			$this->assertNotContains($event_id, $ids);
		}
	}

	public function test_dashboard_posts_by_topic_lists_a_topic_once() {
		global $wpdb;

		$title = 'Stats Topic ' . uniqid();
		$wpdb->insert($wpdb->prefix . 'aips_author_topics', array(
			'author_id'   => 1,
			'topic_title' => $title,
			'status'      => 'approved',
		));
		$topic_id          = (int) $wpdb->insert_id;
		$this->topic_ids[] = $topic_id;

		$this->seed('scheduled', 'completed', array('topic_id' => $topic_id, 'author_id' => 1, 'generated_title' => 'Run Title'));
		$this->seed('topic_post_generation', 'completed', array('topic_id' => $topic_id, 'author_id' => 1));

		list($from, $to) = $this->window();
		$rows = array_values(array_filter($this->dashboard->get_posts_by_topic($from, $to, 100), function ($row) use ($title) {
			return $row->topic_title === $title;
		}));

		$this->assertCount(1, $rows, "The run and its wrapper both carry the topic; only the run is the post's generation.");
		$this->assertSame('Run Title', $rows[0]->generated_title);
	}

	// ------------------------------------------------------------ Metrics

	public function test_generation_metrics_count_generation_runs_only() {
		$this->metrics->invalidate_cache();
		$before = $this->metrics->get_generation_metrics(30);

		$this->seed_mixed_rows();

		$this->metrics->invalidate_cache();
		$after = $this->metrics->get_generation_metrics(30);

		$this->assertSame($before['total'] + 3, $after['total']);
		$this->assertSame($before['successful'] + 1, $after['successful']);
		$this->assertSame($before['failed'] + 1, $after['failed']);
		$this->assertSame($before['partial'] + 1, $after['partial']);
		$this->assertSame($before['terminated'], $after['terminated'], 'A terminated author-topic job is not a generation run.');
	}

	public function test_queue_health_counts_generation_runs_only() {
		$this->metrics->invalidate_cache();
		$before = $this->metrics->get_queue_health_metrics();

		$this->seed_mixed_rows();

		$this->metrics->invalidate_cache();
		$after = $this->metrics->get_queue_health_metrics();

		$this->assertSame($before['partial_count'] + 1, $after['partial_count'], 'A partial indexing row is not a partial generation.');
		$this->assertSame($before['failed_24h'] + 1, $after['failed_24h'], 'Wrapper and indexing failures must not count as failed generations.');
	}

	public function test_recent_outcomes_exclude_event_rows() {
		$this->seed_mixed_rows();
		$event_id = $this->seed('post_review_action', 'completed');

		$this->metrics->invalidate_cache();
		$outcomes = $this->metrics->get_generation_metrics(30)['recent_outcomes'];
		$methods  = wp_list_pluck($outcomes, 'creation_method');

		$this->assertNotContains('post_review_action', $methods);
		$this->assertNotContains('notification_sent', $methods);
		$this->assertNotContains($event_id, wp_list_pluck($outcomes, 'id'));
	}

	public function test_stalled_summary_runs_when_nothing_is_in_progress() {
		global $wpdb;

		// Nothing in progress at all: the stalled condition used to render a CASE with no WHEN.
		$this->reset_history();

		$previous_suppression = $wpdb->suppress_errors(false);
		$wpdb->last_error     = '';
		try {
			$summary = $this->history->get_stalled_summary();

			$this->assertSame('', $wpdb->last_error, 'The stalled summary query must be valid SQL.');
		} finally {
			$wpdb->suppress_errors($previous_suppression);
		}

		$this->assertSame(0, $summary['count']);
		$this->assertNull($summary['oldest_idle_seconds']);
	}

	public function test_stalled_summary_counts_only_generation_runs() {
		global $wpdb;

		$this->reset_history();

		$run = $this->seed('scheduled', 'processing');
		$job = $this->seed('author_embeddings', 'processing');

		// Age both rows past their stalled thresholds (2 hours for runs, 8 hours for chain jobs).
		$past_run = AIPS_DateTime::now()->timestamp() - 2 * HOUR_IN_SECONDS;
		$past_job = AIPS_DateTime::now()->timestamp() - 8 * HOUR_IN_SECONDS;
		$wpdb->update($wpdb->prefix . 'aips_history', array('created_at' => $past_run), array('id' => $run));
		$wpdb->update($wpdb->prefix . 'aips_history', array('created_at' => $past_job), array('id' => $job));

		$summary = $this->history->get_stalled_summary();

		$this->assertSame(1, $summary['count'], 'Stalled summary must count generation runs only.');
	}

	// ------------------------------------------------------------ Duration, AI calls, retry saturation

	public function test_duration_figures_ignore_event_rows() {
		$this->reset_history();

		foreach (array(100, 200, 600) as $seconds) {
			$this->set_duration($this->seed('scheduled', 'completed'), $seconds);
		}
		// Near-instant rows that used to drag every duration figure toward zero.
		foreach (array('topic_post_generation', 'topic_post_generation', 'post_review_action', 'content_indexing') as $method) {
			$this->set_duration($this->seed($method, 'completed'), 0);
		}

		$metrics = $this->metrics->get_generation_metrics(30);
		$stats   = $this->history->get_stats();

		$this->assertSame(300, $metrics['avg_duration_seconds']);
		$this->assertSame(200, $metrics['p50_duration_seconds']);
		$this->assertSame(200, $metrics['p95_duration_seconds']);
		$this->assertSame(200, $stats['median_duration']);
	}

	public function test_average_ai_calls_per_post_ignores_event_rows() {
		$this->reset_history();

		$runs = array($this->seed('scheduled', 'completed'), $this->seed('manual', 'completed'));
		foreach (array(4, 2) as $index => $calls) {
			for ($i = 0; $i < $calls; $i++) {
				$this->history->add_log_entry($runs[$index], array('request' => $i), AIPS_History_Type::AI_REQUEST);
			}
		}
		// Completed event rows have no AI requests and used to deflate the average.
		$this->seed('topic_post_generation', 'completed');
		$this->seed('post_review_action', 'completed');
		$this->seed('notification_sent', 'completed');

		$this->assertSame(3.0, $this->metrics->get_generation_metrics(30)['avg_ai_calls_per_post']);
	}

	public function test_retry_saturation_counts_generation_runs_only() {
		$this->reset_history();

		for ($i = 0; $i < 3; $i++) {
			$this->seed('scheduled', 'completed');
		}
		$this->seed('scheduled', 'failed');
		// The failed run's wrapper twin, a successful wrapper and two failed indexing runs.
		$this->seed('topic_post_generation', 'failed');
		$this->seed('topic_post_generation', 'completed');
		$this->seed('content_indexing', 'failed');
		$this->seed('content_indexing', 'failed');

		$this->assertSame(25.0, $this->metrics->get_queue_health_metrics()['retry_saturation_pct']);
	}

	// ------------------------------------------------------------ Background jobs and list domains

	public function test_history_stats_report_background_jobs_separately_from_runs() {
		global $wpdb;

		$before = $this->history->get_stats();

		$this->seed('content_indexing', 'completed');
		$this->seed('content_indexing', 'failed');
		$this->seed('author_topic_generation', 'completed');
		$this->seed('source_fetch', 'failed');
		$idle_job = $this->seed('content_indexing', 'processing');
		// A chained job is Stalled after six hours without activity.
		$wpdb->update($wpdb->prefix . 'aips_history', array('created_at' => time() - 7 * HOUR_IN_SECONDS), array('id' => $idle_job));

		// Not jobs: one real run, a wrapper, a review action, a notification, a lifecycle container.
		$this->seed('scheduled', 'completed');
		$this->seed('topic_post_generation', 'completed');
		$this->seed('post_review_action', 'completed');
		$this->seed('notification_sent', 'completed');
		$this->seed('schedule_lifecycle', 'processing');

		$after = $this->history->get_stats();

		$this->assertSame($before['jobs']['total'] + 5, $after['jobs']['total']);
		$this->assertSame($before['jobs']['failed'] + 2, $after['jobs']['failed']);
		$this->assertSame($before['jobs']['stalled'] + 1, $after['jobs']['stalled']);
		$this->assertSame($before['jobs']['processing'], $after['jobs']['processing'], 'The idle job is Stalled, not In progress.');

		// The run figures only moved for the one real run.
		$this->assertSame($before['completed'] + 1, $after['completed']);
		$this->assertSame($before['failed'], $after['failed']);
		$this->assertSame($before['stalled'], $after['stalled']);
	}

	public function test_history_list_domains_split_runs_from_background_jobs() {
		$run     = $this->seed('scheduled', 'completed');
		$job     = $this->seed('content_indexing', 'completed');
		$wrapper = $this->seed('topic_post_generation', 'completed');

		$runs = $this->list_ids('generation_runs');
		$jobs = $this->list_ids('background_jobs');

		$this->assertContains($run, $runs);
		$this->assertNotContains($job, $runs);
		$this->assertNotContains($wrapper, $runs, 'A wrapper is an event, not a run.');

		$this->assertContains($job, $jobs);
		$this->assertNotContains($run, $jobs);
		$this->assertNotContains($wrapper, $jobs, 'A wrapper is an event, not a job.');
	}

	private function list_ids($domain) {
		$result = $this->history->get_history(array('domain' => $domain, 'per_page' => 100, 'fields' => 'list'));

		return array_map('intval', wp_list_pluck($result['items'], 'id'));
	}

	public function test_template_stats_and_schedule_count_only_count_generation_runs() {
		global $wpdb;

		$this->reset_history();
		$template_id = 777;

		$wpdb->insert($wpdb->prefix . 'aips_schedule', array(
			'template_id' => $template_id,
			'frequency'   => 'daily',
			'next_run'    => time() + 3600,
			'is_active'   => 1,
			'created_at'  => time() - 60,
		));
		$schedule_id = (int) $wpdb->insert_id;

		try {
			$this->seed('scheduled', 'completed', array('template_id' => $template_id, 'schedule_id' => $schedule_id));
			$this->seed('manual', 'partial', array('template_id' => $template_id));
			// Non-generation rows carrying the same template_id and schedule_id must be ignored.
			$this->seed('template_lifecycle', 'completed', array('template_id' => $template_id));
			$this->seed('schedule_lifecycle', 'completed', array('template_id' => $template_id, 'schedule_id' => $schedule_id));
			$this->seed('post_review_action', 'completed', array('template_id' => $template_id));
			$this->seed('content_indexing', 'completed', array('template_id' => $template_id));

			$this->assertSame(2, $this->history->get_template_stats($template_id));
			$all_stats = $this->history->get_all_template_stats();
			$this->assertSame(2, $all_stats[$template_id]);
			$this->assertSame(1, $this->history->count_completed_for_schedule($schedule_id));
		} finally {
			$wpdb->delete($wpdb->prefix . 'aips_schedule', array('id' => $schedule_id), array('%d'));
		}
	}

	public function test_trend_duration_and_failure_reasons_only_count_generation_runs() {
		$this->reset_history();

		$run_ok   = $this->seed('scheduled', 'completed');
		$run_fail = $this->seed('scheduled', 'failed', array('error_message' => 'AI provider timeout'));
		$this->set_duration($run_ok, 120);

		// Event and job rows that must not pollute trend, duration, or failure reasons.
		$event_fail = $this->seed('topic_post_generation', 'failed', array('error_message' => 'Wrapper failure'));
		$job_ok     = $this->seed('content_indexing', 'completed');
		$this->set_duration($event_fail, 5);
		$this->set_duration($job_ok, 10);

		$trends = $this->history->get_daily_success_failure_trend(1);
		$this->assertNotEmpty($trends);
		$today_trend = end($trends);
		$this->assertSame(1, (int) $today_trend['success_count']);
		$this->assertSame(1, (int) $today_trend['failure_count']);

		$flows = $this->history->get_average_duration_by_flow(1);
		$flow_types = wp_list_pluck($flows, 'flow_type');
		$this->assertContains('scheduled', $flow_types);
		$this->assertNotContains('topic_post_generation', $flow_types);
		$this->assertNotContains('content_indexing', $flow_types);

		$reasons = $this->history->get_top_failure_reasons(1, 8);
		$reason_labels = wp_list_pluck($reasons, 'reason');
		$this->assertContains('AI provider timeout', $reason_labels);
		$this->assertNotContains('Wrapper failure', $reason_labels);
	}

	public function test_get_history_filters_by_correlation_id() {
		$this->reset_history();

		$corr_a = 'corr-uuid-alpha';
		$corr_b = 'corr-uuid-beta';

		$run_a = $this->seed('scheduled', 'completed', array('correlation_id' => $corr_a));
		$job_a = $this->seed('content_indexing', 'completed', array('correlation_id' => $corr_a));
		$run_b = $this->seed('scheduled', 'completed', array('correlation_id' => $corr_b));

		$result = $this->history->get_history(array(
			'correlation_id' => $corr_a,
			'per_page'       => 50,
			'fields'         => 'list',
		));
		$ids = array_map('intval', wp_list_pluck($result['items'], 'id'));

		$this->assertContains($run_a, $ids);
		$this->assertContains($job_a, $ids);
		$this->assertNotContains($run_b, $ids);
	}
}
