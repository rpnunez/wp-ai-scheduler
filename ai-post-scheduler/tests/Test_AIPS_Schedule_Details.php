<?php
/**
 * Tests for the Schedules table details row data.
 *
 * Covers:
 *  - AIPS_History_Repository::get_recent_schedule_post_logs()
 *  - AIPS_Author_Topic_Logs_Repository::get_recent_generated_posts_by_author()
 *  - AIPS_Unified_Schedule_Service::get_details()
 *  - AIPS_Schedule_Controller::ajax_get_schedule_details()
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Schedule_Details extends WP_UnitTestCase {

	/** @var AIPS_History_Repository */
	private $history_repo;

	/** @var AIPS_Schedule_Controller */
	private $controller;

	/** @var int */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->history_repo = new AIPS_History_Repository();
		$this->admin_id     = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_id);

		$mock_scheduler = $this->getMockBuilder('AIPS_Scheduler')
			->disableOriginalConstructor()
			->onlyMethods(array('run_schedule_now', 'save_schedule', 'toggle_active'))
			->getMock();

		$this->controller = new AIPS_Schedule_Controller($mock_scheduler);
	}

	public function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Fixtures
	// ------------------------------------------------------------------

	/**
	 * Insert a history log row with explicit event type and timestamp.
	 *
	 * @param int    $history_id Container ID.
	 * @param string $event_type Event type column value.
	 * @param int    $timestamp  Unix timestamp.
	 * @param array  $details    Details payload.
	 * @param int    $type_id    History type.
	 * @return int Log ID.
	 */
	private function insert_log($history_id, $event_type, $timestamp, array $details = array(), $type_id = AIPS_History_Type::ACTIVITY) {
		global $wpdb;
		$wpdb->insert($wpdb->prefix . 'aips_history_log', array(
			'history_id'      => $history_id,
			'history_type_id' => $type_id,
			'event_type'      => $event_type,
			'event_status'    => AIPS_History_Type::ERROR === $type_id ? 'failed' : 'success',
			'timestamp'       => $timestamp,
			'details'         => wp_json_encode($details),
		));
		return (int) $wpdb->insert_id;
	}

	/**
	 * Create a template schedule linked to a fresh history container.
	 *
	 * @return array{schedule_id:int, history_id:int}
	 */
	private function create_template_schedule() {
		$history_id = $this->history_repo->create(array(
			'status'          => 'processing',
			'creation_method' => 'scheduled',
		));

		$schedule_repo = new AIPS_Schedule_Repository();
		$schedule_id   = $schedule_repo->create(array(
			'template_id' => 0,
			'title'       => 'Details Test Schedule',
			'frequency'   => 'daily',
			'next_run'    => time() + DAY_IN_SECONDS,
			'is_active'   => 1,
		));
		$schedule_repo->update($schedule_id, array('schedule_history_id' => $history_id));

		return array('schedule_id' => (int) $schedule_id, 'history_id' => (int) $history_id);
	}

	/**
	 * Create an author with both pipeline stages scheduled.
	 *
	 * @return int Author ID.
	 */
	private function create_blueprint_author() {
		$authors = new AIPS_Authors_Repository();
		return (int) $authors->create(array(
			'name'                       => 'Details Persona',
			'field_niche'                => 'Testing',
			'is_active'                  => 1,
			'topic_generation_frequency' => 'weekly',
			'topic_generation_next_run'  => time() + DAY_IN_SECONDS,
			'topic_generation_is_active' => 1,
			'post_generation_frequency'  => 'daily',
			'post_generation_next_run'   => time() + HOUR_IN_SECONDS,
			'post_generation_is_active'  => 1,
		));
	}

	/**
	 * Insert an author topic with a given generated_at timestamp.
	 *
	 * @return int Topic ID.
	 */
	private function create_topic($author_id, $title, $generated_at, $status = 'pending') {
		global $wpdb;
		$wpdb->insert($wpdb->prefix . 'aips_author_topics', array(
			'author_id'    => $author_id,
			'topic_title'  => $title,
			'status'       => $status,
			'generated_at' => $generated_at,
		));
		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert a post_generated topic log.
	 */
	private function create_post_log($topic_id, $post_id, $created_at, $action = 'post_generated') {
		global $wpdb;
		$wpdb->insert($wpdb->prefix . 'aips_author_topic_logs', array(
			'author_topic_id' => $topic_id,
			'post_id'         => $post_id,
			'action'          => $action,
			'created_at'      => $created_at,
		));
	}

	private function capture_ajax_response($callback) {
		ob_start();
		try {
			$callback();
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		} catch (WPAjaxDieStopException $e) {
			// Expected.
		}
		$output = ob_get_clean();
		if ('' === $output && isset($GLOBALS['aips_last_ajax_output'])) {
			$output = (string) $GLOBALS['aips_last_ajax_output'];
		}
		return json_decode($output, true);
	}

	// ------------------------------------------------------------------
	// Repositories
	// ------------------------------------------------------------------

	public function test_recent_schedule_post_logs_are_newest_first_and_limited() {
		$history_id = $this->history_repo->create(array('status' => 'processing'));
		$now        = time();

		$this->insert_log($history_id, AIPS_History_Event_Type::POST_DRAFT, $now - 300, array('context' => array('post_id' => 1)));
		$this->insert_log($history_id, AIPS_History_Event_Type::POST_PUBLISHED, $now - 100, array('context' => array('post_id' => 2)));
		$this->insert_log($history_id, AIPS_History_Event_Type::POST_DRAFT, $now - 200, array('context' => array('post_id' => 3)));
		// Not a post-producing event: must be ignored.
		$this->insert_log($history_id, 'schedule_enabled', $now, array());

		$logs = $this->history_repo->get_recent_schedule_post_logs($history_id, 2);

		$this->assertCount(2, $logs);
		$this->assertSame($now - 100, (int) $logs[0]->timestamp);
		$this->assertSame($now - 200, (int) $logs[1]->timestamp);
	}

	public function test_recent_schedule_post_logs_rejects_invalid_input() {
		$this->assertSame(array(), $this->history_repo->get_recent_schedule_post_logs(0, 10));
		$this->assertSame(array(), $this->history_repo->get_recent_schedule_post_logs(5, 0));
	}

	public function test_recent_generated_posts_by_author_filters_and_orders() {
		$author_id = $this->create_blueprint_author();
		$other_id  = $this->create_blueprint_author();
		$topic_a   = $this->create_topic($author_id, 'Topic A', time());
		$topic_b   = $this->create_topic($other_id, 'Topic B', time());
		$now       = time();

		$this->create_post_log($topic_a, 101, $now - 50);
		$this->create_post_log($topic_a, 102, $now - 10);
		$this->create_post_log($topic_a, null, $now);              // Disassociated: excluded.
		$this->create_post_log($topic_a, 103, $now, 'approved');   // Other action: excluded.
		$this->create_post_log($topic_b, 104, $now);               // Other author: excluded.

		$repo = new AIPS_Author_Topic_Logs_Repository();
		$rows = $repo->get_recent_generated_posts_by_author($author_id, 10);

		$this->assertSame(array(102, 101), array_map('intval', wp_list_pluck($rows, 'post_id')));
		$this->assertSame('Topic A', $rows[0]->topic_title);
		$this->assertCount(1, $repo->get_recent_generated_posts_by_author($author_id, 1));
	}

	// ------------------------------------------------------------------
	// Service
	// ------------------------------------------------------------------

	public function test_get_details_rejects_invalid_type_and_unknown_schedule() {
		$service = new AIPS_Unified_Schedule_Service();

		$invalid = $service->get_details(1, 'nope');
		$this->assertWPError($invalid);
		$this->assertSame('invalid_type', $invalid->get_error_code());

		$missing = $service->get_details(999999, AIPS_Unified_Schedule_Service::TYPE_TEMPLATE);
		$this->assertWPError($missing);
		$this->assertSame('not_found', $missing->get_error_code());
	}

	public function test_get_details_for_template_dedupes_and_limits_recent_posts() {
		$fixture = $this->create_template_schedule();
		$now     = time();
		$posts   = array();

		for ($i = 0; $i < 12; $i++) {
			$posts[$i] = self::factory()->post->create(array('post_title' => 'Generated ' . $i, 'post_status' => 'draft'));
			$this->insert_log($fixture['history_id'], AIPS_History_Event_Type::POST_DRAFT, $now - (100 * (12 - $i)), array('context' => array('post_id' => $posts[$i])));
		}
		// Completion event for the newest post: same post, must not appear twice.
		$this->insert_log($fixture['history_id'], AIPS_History_Event_Type::MANUAL_SCHEDULE_COMPLETED, $now, array('context' => array('post_id' => $posts[11])));
		// A deleted post is skipped.
		$deleted = self::factory()->post->create();
		wp_delete_post($deleted, true);
		$this->insert_log($fixture['history_id'], AIPS_History_Event_Type::POST_DRAFT, $now - 1, array('context' => array('post_id' => $deleted)));

		$service = new AIPS_Unified_Schedule_Service();
		$details = $service->get_details($fixture['schedule_id'], AIPS_Unified_Schedule_Service::TYPE_TEMPLATE);

		$this->assertIsArray($details);
		$this->assertSame(10, $details['recent_limit']);
		$this->assertCount(10, $details['recent']);

		$ids = array();
		foreach ($details['recent'] as $item) {
			$this->assertSame('post', $item['kind']);
			$ids[] = $item['title'];
		}
		$this->assertSame('Generated 11', $details['recent'][0]['title']);
		$this->assertSame('Generated 2', $details['recent'][9]['title']);
		$this->assertCount(10, array_unique($ids));
		$this->assertSame('draft', $details['recent'][0]['status']);
		$this->assertNotEmpty($details['recent'][0]['time_display']);

		$this->assertSame('healthy', $details['health']['state']);
		$this->assertNull($details['health']['last_error']);
		$this->assertSame('Posts generated', $details['activity']['stats'][0]['label']);
		$this->assertSame(array(), $details['activity']['stages']);
	}

	public function test_get_details_for_template_reports_last_error_and_open_circuit() {
		$fixture = $this->create_template_schedule();
		$this->insert_log($fixture['history_id'], 'schedule_failed', time(), array('message' => 'Provider timed out'), AIPS_History_Type::ERROR);

		$schedule_repo = new AIPS_Schedule_Repository();
		$schedule_repo->update($fixture['schedule_id'], array('circuit_state' => 'open'));

		$service = new AIPS_Unified_Schedule_Service();
		$details = $service->get_details($fixture['schedule_id'], AIPS_Unified_Schedule_Service::TYPE_TEMPLATE);

		$this->assertSame('error', $details['health']['state']);
		$this->assertContains('reset_circuit', $details['health']['actions']);
		$this->assertSame('Provider timed out', $details['health']['last_error']['message']);
	}

	public function test_get_details_hides_old_error_when_schedule_is_healthy() {
		$fixture = $this->create_template_schedule();
		$this->insert_log($fixture['history_id'], 'schedule_failed', time() - (90 * DAY_IN_SECONDS), array('message' => 'Old failure'), AIPS_History_Type::ERROR);

		$service = new AIPS_Unified_Schedule_Service();
		$details = $service->get_details($fixture['schedule_id'], AIPS_Unified_Schedule_Service::TYPE_TEMPLATE);

		$this->assertSame('healthy', $details['health']['state']);
		$this->assertNull($details['health']['last_error']);
	}

	public function test_get_details_for_blueprint_merges_topics_and_posts() {
		$author_id = $this->create_blueprint_author();
		$now       = time();

		$old_topic = $this->create_topic($author_id, 'Older topic', $now - 500, 'approved');
		$this->create_topic($author_id, 'Newest topic', $now - 10);
		$post_id = self::factory()->post->create(array('post_title' => 'Persona post', 'post_status' => 'publish'));
		$this->create_post_log($old_topic, $post_id, $now - 100);

		$service = new AIPS_Unified_Schedule_Service();
		$details = $service->get_details($author_id, AIPS_Unified_Schedule_Service::TYPE_BLUEPRINT);

		$this->assertIsArray($details);
		$this->assertSame(
			array('Newest topic', 'Persona post', 'Older topic'),
			wp_list_pluck($details['recent'], 'title')
		);
		$this->assertSame(array('topic', 'post', 'topic'), wp_list_pluck($details['recent'], 'kind'));
		$this->assertSame('publish', $details['recent'][1]['status']);
		$this->assertStringContainsString('tab=author-topics', $details['recent'][0]['url']);

		$this->assertCount(2, $details['activity']['stages']);
		$this->assertSame(array('Posts generated', 'Topics generated', 'Last run'), wp_list_pluck($details['activity']['stats'], 'label'));
		$this->assertSame('healthy', $details['health']['state']);
	}

	// ------------------------------------------------------------------
	// AJAX
	// ------------------------------------------------------------------

	public function test_ajax_rejects_invalid_nonce() {
		$_POST['nonce']    = 'bad';
		$_REQUEST['nonce'] = 'bad';
		$_POST['id']       = 1;
		$_POST['type']     = AIPS_Unified_Schedule_Service::TYPE_TEMPLATE;

		$response = $this->capture_ajax_response(array($this->controller, 'ajax_get_schedule_details'));

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid nonce', $response['data']['message']);
	}

	public function test_ajax_denies_non_admin() {
		wp_set_current_user($this->factory->user->create(array('role' => 'editor')));
		$_POST['nonce']    = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['id']       = 1;
		$_POST['type']     = AIPS_Unified_Schedule_Service::TYPE_TEMPLATE;

		$response = $this->capture_ajax_response(array($this->controller, 'ajax_get_schedule_details'));

		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Permission denied', $response['data']['message']);
	}

	public function test_ajax_requires_id_and_type() {
		$_POST['nonce']    = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['id']       = 0;
		$_POST['type']     = '';

		$response = $this->capture_ajax_response(array($this->controller, 'ajax_get_schedule_details'));

		$this->assertFalse($response['success']);
	}

	public function test_ajax_returns_not_found_for_unknown_schedule() {
		$_POST['nonce']    = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['id']       = 999999;
		$_POST['type']     = AIPS_Unified_Schedule_Service::TYPE_TEMPLATE;

		$response = $this->capture_ajax_response(array($this->controller, 'ajax_get_schedule_details'));

		$this->assertFalse($response['success']);
		$this->assertSame('not_found', $response['data']['code']);
	}

	public function test_ajax_returns_details_for_template_schedule() {
		$fixture = $this->create_template_schedule();

		$_POST['nonce']    = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['id']       = $fixture['schedule_id'];
		$_POST['type']     = AIPS_Unified_Schedule_Service::TYPE_TEMPLATE;

		$response = $this->capture_ajax_response(array($this->controller, 'ajax_get_schedule_details'));

		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('activity', $response['data']);
		$this->assertArrayHasKey('health', $response['data']);
		$this->assertSame(array(), $response['data']['recent']);
	}

	public function test_details_action_is_registered() {
		$this->assertSame('AIPS_Schedule_Controller', AIPS_Ajax_Registry::get_controller_for('aips_get_schedule_details'));
	}
}
