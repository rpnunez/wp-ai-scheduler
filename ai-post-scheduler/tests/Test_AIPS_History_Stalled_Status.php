<?php
/**
 * Tests for the derived "Stalled" display status and the History Timeline /
 * label fixes: a run is Processing or Stalled, never both; event-only rows no
 * longer present the linked post's components as their own.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_History_Stalled_Status extends WP_UnitTestCase {

	/** @var AIPS_History_Repository */
	private $repository;

	/** @var AIPS_History_Service */
	private $service;

	public function set_up() {
		parent::set_up();
		$this->repository = new AIPS_History_Repository();
		$this->service    = new AIPS_History_Service($this->repository);
	}

	public function tear_down() {
		remove_all_filters('aips_history_stalled_after_seconds');
		parent::tear_down();
	}

	/**
	 * Open generation-style container (linked to a topic so it is listed and counted).
	 */
	private function open_run($creation_method = 'post_generation', $idle_seconds = 0) {
		$container = $this->service->create($creation_method, array('topic_id' => 1, 'creation_method' => $creation_method));
		$container->record('activity', 'Started', array('event_type' => 'run_started', 'event_status' => 'running'));

		if ($idle_seconds > 0) {
			global $wpdb;
			$then = AIPS_DateTime::now()->timestamp() - $idle_seconds;
			$wpdb->update($wpdb->prefix . 'aips_history', array('created_at' => $then), array('id' => $container->get_id()));
			$wpdb->update($wpdb->prefix . 'aips_history_log', array('timestamp' => $then), array('history_id' => $container->get_id()));
		}

		return (int) $container->get_id();
	}

	private function listed_ids($status) {
		$result = $this->repository->get_history(array('status' => $status, 'per_page' => 100, 'fields' => 'list'));

		return array_map('intval', wp_list_pluck($result['items'], 'id'));
	}

	// -- Status resolution --------------------------------------------------------

	public function test_resolve_effective_status_only_changes_processing() {
		$now = 10000;

		$this->assertSame('processing', AIPS_History_Repository::resolve_effective_status('processing', 'post_generation', $now - 60, $now));
		$this->assertSame('stalled', AIPS_History_Repository::resolve_effective_status('processing', 'post_generation', $now - 901, $now));
		$this->assertSame('completed', AIPS_History_Repository::resolve_effective_status('completed', 'post_generation', 0, $now));
		$this->assertSame('failed', AIPS_History_Repository::resolve_effective_status('failed', 'post_generation', 0, $now));
	}

	public function test_chained_jobs_get_a_longer_stalled_threshold() {
		$now = 100000;

		foreach (array('content_indexing', 'author_embeddings', 'bulk_generation', 'planner_batch') as $method) {
			$this->assertTrue(AIPS_History_Repository::is_chain_method($method), $method);
			$this->assertSame('processing', AIPS_History_Repository::resolve_effective_status('processing', $method, $now - HOUR_IN_SECONDS, $now), $method);
			$this->assertSame('stalled', AIPS_History_Repository::resolve_effective_status('processing', $method, $now - 7 * HOUR_IN_SECONDS, $now), $method);
		}

		$this->assertFalse(AIPS_History_Repository::is_chain_method('post_generation'));
	}

	public function test_stalled_threshold_is_filterable_and_clamped() {
		add_filter('aips_history_stalled_after_seconds', function () {
			return 5;
		});

		$this->assertSame(60, AIPS_History_Repository::stalled_threshold_seconds('post_generation'));
	}

	// -- Repository queries -------------------------------------------------------

	public function test_idle_processing_run_is_stalled_and_not_processing() {
		$fresh = $this->open_run('post_generation', 0);
		$idle  = $this->open_run('post_generation', 2 * HOUR_IN_SECONDS);

		$processing = $this->listed_ids('processing');
		$stalled    = $this->listed_ids('stalled');

		$this->assertContains($fresh, $processing);
		$this->assertNotContains($idle, $processing, 'A stalled run must not also be Processing.');
		$this->assertContains($idle, $stalled);
		$this->assertNotContains($fresh, $stalled);
		$this->assertSame(array(), array_values(array_intersect($processing, $stalled)));
	}

	public function test_recent_log_activity_keeps_an_old_run_processing() {
		$id = $this->open_run('post_generation', 2 * HOUR_IN_SECONDS);
		$container = AIPS_History_Container::load_existing($this->repository, $id);
		$container->record('activity', 'Still working', array('event_type' => 'progress', 'event_status' => 'running'));

		$this->assertContains($id, $this->listed_ids('processing'));
		$this->assertNotContains($id, $this->listed_ids('stalled'));
	}

	public function test_chained_run_idle_for_an_hour_is_still_processing() {
		$id = $this->open_run('content_indexing', HOUR_IN_SECONDS);

		$this->assertContains($id, $this->listed_ids('processing'));
		$this->assertNotContains($id, $this->listed_ids('stalled'));
	}

	public function test_rows_carry_effective_status() {
		$idle  = $this->open_run('post_generation', 2 * HOUR_IN_SECONDS);
		$fresh = $this->open_run('post_generation', 0);

		$result = $this->repository->get_history(array('per_page' => 100, 'fields' => 'list'));
		$by_id  = array();
		foreach ($result['items'] as $item) {
			$by_id[(int) $item->id] = $item;
		}

		$this->assertSame('stalled', $by_id[$idle]->effective_status);
		$this->assertSame('processing', $by_id[$fresh]->effective_status);
		$this->assertSame('processing', $by_id[$idle]->status, 'The stored status is unchanged.');
	}

	public function test_stats_split_processing_and_stalled() {
		$this->open_run('post_generation', 0);
		$this->open_run('post_generation', 2 * HOUR_IN_SECONDS);
		$this->open_run('post_generation', 3 * HOUR_IN_SECONDS);

		$stats = $this->repository->get_stats();

		$this->assertSame(1, $stats['processing']);
		$this->assertSame(2, $stats['stalled']);
	}

	public function test_get_by_id_exposes_effective_status() {
		$id = $this->open_run('post_generation', 2 * HOUR_IN_SECONDS);

		$this->assertSame('stalled', $this->repository->get_by_id($id)->effective_status);
	}

	public function test_threshold_override_applies_to_filter_stats_and_badge_alike() {
		add_filter('aips_history_stalled_after_seconds', function ($seconds, $method) {
			return $method === 'post_generation' ? 2 * HOUR_IN_SECONDS : $seconds;
		}, 10, 2);

		$id = $this->open_run('post_generation', HOUR_IN_SECONDS);

		$this->assertContains($id, $this->listed_ids('processing'));
		$this->assertNotContains($id, $this->listed_ids('stalled'));
		$this->assertSame(1, $this->repository->get_stats()['processing']);
		$this->assertSame(0, $this->repository->get_stats()['stalled']);
		$this->assertSame('processing', $this->repository->get_by_id($id)->effective_status);
	}

	public function test_stats_notice_a_run_going_idle_without_any_cache_invalidation() {
		global $wpdb;

		$id = $this->open_run('post_generation', 0);
		$this->assertSame(1, $this->repository->get_stats()['processing']);

		// Time passes with no History write, so nothing invalidates the stats cache.
		$then = AIPS_DateTime::now()->timestamp() - 2 * HOUR_IN_SECONDS;
		$wpdb->update($wpdb->prefix . 'aips_history', array('created_at' => $then), array('id' => $id));
		$wpdb->update($wpdb->prefix . 'aips_history_log', array('timestamp' => $then), array('history_id' => $id));

		$stats = $this->repository->get_stats();
		$this->assertSame(0, $stats['processing']);
		$this->assertSame(1, $stats['stalled']);
	}

	// -- Row template -------------------------------------------------------------

	private function render_row($item) {
		ob_start();
		include AIPS_PLUGIN_DIR . 'templates/partials/history-row.php';

		return (string) ob_get_clean();
	}

	private function row_item(array $overrides = array()) {
		$now = AIPS_DateTime::now()->timestamp();

		return (object) array_merge(array(
			'id' => 1, 'post_id' => 0, 'post_type' => '', 'template_id' => 0, 'template_name' => '', 'topic_id' => 0,
			'status' => 'processing', 'effective_status' => 'processing', 'generated_title' => '', 'prompt' => '',
			'creation_method' => 'post_generation', 'created_at' => $now - 3 * HOUR_IN_SECONDS,
			'last_activity_at' => $now - 2 * HOUR_IN_SECONDS, 'error_count' => 0, 'warning_count' => 0, 'ai_call_count' => 0,
			'duration_label' => '', 'latest_message' => '', 'relative_date' => 'x', 'formatted_date' => 'x',
		), $overrides);
	}

	public function test_stalled_row_renders_one_status_badge() {
		$html = $this->render_row($this->row_item(array('effective_status' => 'stalled')));

		$this->assertStringContainsString('Stalled', $html);
		$this->assertDoesNotMatchRegularExpression('/Processing\s*<\/span>/', $html);
	}

	public function test_active_row_renders_processing_only() {
		$html = $this->render_row($this->row_item(array('last_activity_at' => AIPS_DateTime::now()->timestamp())));

		$this->assertMatchesRegularExpression('/Processing\s*<\/span>/', $html);
		$this->assertStringNotContainsString('Stalled', $html);
	}

	// -- Labels -------------------------------------------------------------------

	public function test_event_methods_have_explicit_labels() {
		foreach (array('post_review_action', 'topic_post_generation', 'bulk_delete', 'settings_connection_test', 'notification_sent') as $method) {
			$this->assertTrue(AIPS_History::has_creation_method_label($method), $method);
		}
		$this->assertFalse(AIPS_History::has_creation_method_label('something_unknown'));

		$title = AIPS_History::get_display_title((object) array('creation_method' => 'post_review_action'));
		$this->assertSame('Post Review', $title);
		$this->assertNotSame('Generation Event', $title);
	}

	public function test_is_generation_method() {
		foreach (array('post_generation', 'scheduled', 'manual', 'regenerate', 'bulk_generate', 'author_post_gen') as $method) {
			$this->assertTrue(AIPS_History::is_generation_method($method), $method);
		}
		foreach (array('post_review_action', 'topic_post_generation', 'schedule_lifecycle', 'notification_sent', 'bulk_delete') as $method) {
			$this->assertFalse(AIPS_History::is_generation_method($method), $method);
		}
	}

	// -- Timeline data ------------------------------------------------------------

	private function timeline_for($creation_method, $post_id) {
		$handler = new AIPS_History();
		$method  = new ReflectionMethod($handler, 'build_history_timeline_data');
		$method->setAccessible(true);

		$item = (object) array('post_id' => $post_id, 'creation_method' => $creation_method, 'post_type' => 'post', 'generated_title' => '', 'error_message' => '');

		return $method->invoke($handler, array('post_id' => $post_id), $item, array());
	}

	public function test_review_action_rows_do_not_show_generation_milestones() {
		$post_id = self::factory()->post->create(array('post_title' => 'Telemetry post', 'post_content' => 'Body'));

		$this->assertFalse($this->timeline_for('post_review_action', $post_id)['show_milestones']);
		$this->assertTrue($this->timeline_for('post_generation', $post_id)['show_milestones']);
	}

	public function test_internal_meta_is_not_listed_as_custom_fields() {
		$post_id = self::factory()->post->create(array('post_content' => 'Body'));
		update_post_meta($post_id, '_aips_post_generation_incomplete', 'false');
		update_post_meta($post_id, '_aips_link_index_hash', 'abc');
		update_post_meta($post_id, '_aips_publish_linked', '1');
		update_post_meta($post_id, 'aips_source_snapshots_used', array('x'));
		update_post_meta($post_id, 'headline', 'Visible');

		$names = wp_list_pluck($this->timeline_for('post_generation', $post_id)['custom_fields'], 'name');

		$this->assertSame(array('headline'), array_values($names));
	}

	public function test_incomplete_generation_is_a_warning_and_failed_image_is_distinct() {
		$post_id = self::factory()->post->create(array('post_content' => 'Body'));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode(array(
			'post_title' => true, 'post_excerpt' => true, 'featured_image' => false, 'post_content' => true,
		)));

		$timeline = $this->timeline_for('post_generation', $post_id);

		$this->assertSame('failed', $timeline['image_status']);
		$this->assertStringContainsString('featured image', $timeline['warning_text']);
	}

	public function test_missing_image_is_not_a_failure_when_not_requested() {
		$post_id = self::factory()->post->create(array('post_content' => 'Body'));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode(array(
			'post_title' => true, 'post_excerpt' => true, 'featured_image' => true, 'post_content' => true,
		)));

		$timeline = $this->timeline_for('post_generation', $post_id);

		$this->assertSame('none', $timeline['image_status']);
		$this->assertSame('', $timeline['warning_text']);
	}
}
