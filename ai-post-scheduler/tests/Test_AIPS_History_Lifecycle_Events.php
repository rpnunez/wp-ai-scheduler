<?php
/**
 * Tests for History lifecycle/operational event recording (history-system-plan.md, Phases 0-3).
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_History_Lifecycle_Events extends WP_UnitTestCase {

	/**
	 * Latest history row with the given creation_method, with its logs decoded.
	 *
	 * @param string $creation_method creation_method column value.
	 * @return array{row:object,logs:array}|null
	 */
	private function latest_container($creation_method) {
		global $wpdb;

		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}aips_history WHERE creation_method = %s ORDER BY id DESC LIMIT 1",
			$creation_method
		));
		if (!$row) {
			return null;
		}

		$raw  = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}aips_history_log WHERE history_id = %d ORDER BY timestamp ASC, id ASC",
			$row->id
		));
		$logs = array();
		foreach ($raw as $log) {
			$logs[] = json_decode($log->details, true);
		}

		return array('row' => $row, 'logs' => $logs);
	}

	private function assert_trigger_first($logs, $expected_method) {
		$this->assertGreaterThanOrEqual(3, count($logs), 'Expected two trigger entries plus the event.');
		$this->assertSame('trigger_source', $logs[0]['log_subtype']);
		$this->assertSame('trigger_method', $logs[1]['log_subtype']);
		$this->assertSame($expected_method, $logs[1]['input']['method']);
	}

	private function make_instance($class, array $props = array()) {
		$instance = (new ReflectionClass($class))->newInstanceWithoutConstructor();
		foreach ($props as $name => $value) {
			$prop = new ReflectionProperty($class, $name);
			$prop->setAccessible(true);
			$prop->setValue($instance, $value);
		}

		return $instance;
	}

	private function call_private($object, $method, array $args = array()) {
		$m = new ReflectionMethod($object, $method);
		$m->setAccessible(true);

		return $m->invokeArgs($object, $args);
	}

	// -- Phase 0: classifier -----------------------------------------------------

	/**
	 * @dataProvider phase0_methods
	 */
	public function test_cron_bulk_methods_are_classified($method, $expected) {
		$this->assertSame($expected, AIPS_Generation_Trigger::classify_creation_method($method));
	}

	public static function phase0_methods() {
		return array(
			'cron'                => array('cron', 'scheduled'),
			'bulk batch slice'    => array('bulk_batch_slice', 'scheduled'),
			'planner post'        => array('planner_post', 'manual'),
			'trending topic post' => array('trending_topic_post', 'manual'),
			'author topic post'   => array('author_topic_post', 'manual'),
			'template lifecycle'  => array('template_lifecycle', 'manual'),
			'author lifecycle'    => array('author_lifecycle', 'manual'),
			'source lifecycle'    => array('source_lifecycle', 'manual'),
			'schedule lifecycle'  => array('schedule_lifecycle', 'unknown'),
		);
	}

	public function test_describe_topic_context_includes_trigger_detail() {
		$context = new AIPS_Topic_Context(
			(object) array('id' => 3, 'name' => 'Ada'),
			(object) array('id' => 9, 'topic_title' => 'Compilers'),
			'',
			'author_topic_post'
		);
		$context->set_trigger_context(array('detail' => 'Bulk job abc (Author topics)'));

		$result = AIPS_Generation_Trigger::describe($context);

		$this->assertSame('manual', $result['method']['method']);
		$this->assertStringStartsWith('Bulk job abc (Author topics)', $result['source_message']);
	}

	// -- Phase 1: shared helper ---------------------------------------------------

	public function test_lifecycle_creation_methods_are_hidden_from_history_and_dashboard() {
		$shared = AIPS_History_Event_Recorder::lifecycle_creation_methods();
		foreach (array('schedule_lifecycle', 'template_lifecycle', 'campaign_lifecycle', 'author_lifecycle', 'source_lifecycle') as $method) {
			$this->assertContains($method, $shared);
		}

		$history_repo = new AIPS_History_Repository();
		$this->assertEmpty(array_diff($shared, $this->call_private($history_repo, 'get_auxiliary_creation_methods')));
		$this->assertContains('notification_sent', $this->call_private($history_repo, 'get_auxiliary_creation_methods'));

		$dashboard = (new ReflectionClass('AIPS_Dashboard_Repository'))->newInstanceWithoutConstructor();
		$this->assertSame($shared, $this->call_private($dashboard, 'auxiliary_creation_methods'));
	}

	public function test_new_event_types_are_registered() {
		foreach (array(
			AIPS_History_Event_Type::AUTHOR_CREATED, AIPS_History_Event_Type::AUTHOR_UPDATED, AIPS_History_Event_Type::AUTHOR_DELETED,
			AIPS_History_Event_Type::TEMPLATE_CREATED, AIPS_History_Event_Type::TEMPLATE_UPDATED, AIPS_History_Event_Type::TEMPLATE_DELETED, AIPS_History_Event_Type::TEMPLATE_CLONED,
			AIPS_History_Event_Type::SCHEDULE_DELETED, AIPS_History_Event_Type::SCHEDULE_CIRCUIT_RESET,
			AIPS_History_Event_Type::CAMPAIGN_DELETED, AIPS_History_Event_Type::CAMPAIGN_DUPLICATED,
			AIPS_History_Event_Type::RESEARCH_RUN, AIPS_History_Event_Type::SOURCE_FETCHED,
			AIPS_History_Event_Type::CONTENT_INDEX_CLEARED, AIPS_History_Event_Type::CANNIBALIZATION_AUDIT_RUN,
			AIPS_History_Event_Type::GSC_SYNC, AIPS_History_Event_Type::LINK_INDEX_REBUILT,
		) as $type) {
			$this->assertTrue(AIPS_History_Event_Type::is_registered($type), $type);
		}
	}

	public function test_record_lifecycle_writes_trigger_entries_first_and_completes() {
		wp_set_current_user($this->factory->user->create(array('role' => 'administrator', 'user_login' => 'lifecycle_admin')));

		$event = AIPS_History_Event::success(
			AIPS_History_Event_Type::AUTHOR_CREATED,
			'Author "Ada" created',
			AIPS_History_Subject::of(AIPS_History_Subject::TYPE_AUTHOR, 42, 'Ada')
		);
		$container = AIPS_History_Event_Recorder::instance()->record_lifecycle(
			$event,
			'author_lifecycle',
			array('event' => 'Author change', 'author_id' => 42, 'author_name' => 'Ada')
		);

		$this->assertNotFalse($container);
		$found = $this->latest_container('author_lifecycle');
		$this->assertSame('completed', $found['row']->status);
		$this->assertSame('42', (string) $found['row']->author_id);
		$this->assert_trigger_first($found['logs'], 'manual');
		$this->assertStringContainsString('By lifecycle_admin', $found['logs'][0]['message']);
		$this->assertSame('author_created', $found['logs'][2]['input']['event_type']);
		wp_set_current_user(0);
	}

	public function test_record_lifecycle_failure_closes_container_as_failed() {
		AIPS_History_Event_Recorder::instance()->record_simple(
			AIPS_History_Event_Type::TEMPLATE_DELETED,
			false,
			'Failed to delete template "X"',
			'template_lifecycle'
		);

		$found = $this->latest_container('template_lifecycle');
		$this->assertSame('failed', $found['row']->status);
		$this->assert_trigger_first($found['logs'], 'manual');
	}

	public function test_record_lifecycle_scheduled_trigger_has_no_user() {
		AIPS_History_Event_Recorder::instance()->record_simple(
			AIPS_History_Event_Type::SOURCE_FETCHED,
			true,
			'Fetched source #1',
			'source_fetch',
			array('event' => 'Source fetch'),
			'scheduled'
		);

		$found = $this->latest_container('source_fetch');
		$this->assert_trigger_first($found['logs'], 'scheduled');
		$this->assertStringNotContainsString('By ', $found['logs'][0]['message']);
	}

	public function test_trigger_record_adds_current_user_for_manual_runs() {
		wp_set_current_user($this->factory->user->create(array('role' => 'administrator', 'user_login' => 'ray')));
		$history = new class {
			public $records = array();
			public function record($type, $message, $input = null) {
				$this->records[] = array($type, $message, $input);
			}
		};

		AIPS_Generation_Trigger::record($history, array('event' => 'Test'), 'manual');

		$this->assertStringContainsString('By ray', $history->records[0][1]);
		wp_set_current_user(0);
	}

	public function test_format_source_message_supports_event_detail_and_ids() {
		$message = AIPS_Generation_Trigger::format_source_message(array(
			'event'    => 'Bulk generation',
			'detail'   => 'Bulk job j1',
			'post_id'  => 7,
			'topic_id' => 3,
			'user'     => 'ray',
		));

		$this->assertSame('Bulk generation · Bulk job j1 · Post (ID 7) · Topic (ID 3) · By ray', $message);
	}

	// -- Phase 2 --------------------------------------------------------------------

	public function test_component_regeneration_records_trigger_once() {
		$service = (new ReflectionClass('AIPS_Component_Regeneration_Service'))->newInstanceWithoutConstructor();
		$history = new class {
			public $records = array();
			public function record($type, $message, $input = null) {
				$this->records[] = array($type, $message, $input);
			}
		};

		$this->call_private($service, 'record_regeneration_trigger', array($history, array('trigger_detail' => 'AI Edit modal'), 'Title', 55));
		$this->assertCount(2, $history->records);
		$this->assertStringContainsString('Component regeneration: Title', $history->records[0][1]);
		$this->assertStringContainsString('Post (ID 55)', $history->records[0][1]);
		$this->assertStringContainsString('AI Edit modal', $history->records[0][1]);
		$this->assertSame('manual', $history->records[1][2]['method']);

		// A multi-component run records once up front and flags the context.
		$this->call_private($service, 'record_regeneration_trigger', array($history, array('trigger_recorded' => true), 'Content', 55));
		$this->assertCount(2, $history->records);
	}

	public function test_research_runs_create_visible_containers_with_triggers() {
		$controller = $this->make_instance('AIPS_Research_Controller', array('history_service' => new AIPS_History_Service()));

		$this->call_private($controller, 'record_research_run', array('Research trending topics', 'AI tools', 12));
		$found = $this->latest_container('research_run');
		$this->assert_trigger_first($found['logs'], 'manual');
		$this->assertSame('completed', $found['row']->status);
		$this->assertSame('research_run', $found['logs'][2]['input']['event_type']);
		$this->assertSame(12, $found['logs'][2]['input']['result_count']);

		$this->call_private($controller, 'record_research_run', array('Scheduled research', 'AI tools', new WP_Error('x', 'AI failed'), 'scheduled'));
		$found = $this->latest_container('research_run');
		$this->assert_trigger_first($found['logs'], 'scheduled');
		$this->assertSame('failed', $found['row']->status);
	}

	public function test_research_containers_are_not_hidden_from_history() {
		$history_repo = new AIPS_History_Repository();
		$this->assertNotContains('research_run', $this->call_private($history_repo, 'get_auxiliary_creation_methods'));
	}

	public function test_manual_generate_topics_records_container() {
		$scheduler = (new ReflectionClass('AIPS_Author_Topics_Scheduler'))->newInstanceWithoutConstructor();
		$prop      = new ReflectionProperty('AIPS_Author_Topics_Scheduler', 'history_service');
		$prop->setAccessible(true);
		$prop->setValue($scheduler, new AIPS_History_Service());

		$author = (object) array('id' => 5, 'name' => 'Ada', 'field_niche' => 'PHP', 'topic_generation_quantity' => 3);
		$this->call_private($scheduler, 'record_topic_generation_history', array($author, array(1, 2, 3), 'manual'));

		$found = $this->latest_container('author_topic_generation');
		$this->assert_trigger_first($found['logs'], 'manual');
		$this->assertSame('completed', $found['row']->status);
		$this->assertSame(3, $found['logs'][2]['context']['topics_generated']);
	}

	// -- Phase 3 --------------------------------------------------------------------

	public function test_template_event_helper_records_lifecycle() {
		$controller = (new ReflectionClass('AIPS_Templates_Controller'))->newInstanceWithoutConstructor();

		$this->call_private($controller, 'record_template_event', array(AIPS_History_Event_Type::TEMPLATE_CLONED, 11, 'Roundup (Copy)', true, array('cloned_from' => 4)));

		$found = $this->latest_container('template_lifecycle');
		$this->assert_trigger_first($found['logs'], 'manual');
		$this->assertSame('template_cloned', $found['logs'][2]['input']['event_type']);
		$this->assertSame('11', (string) $found['row']->template_id);
	}

	public function test_schedule_event_helpers_record_lifecycle() {
		$controller = (new ReflectionClass('AIPS_Schedule_Controller'))->newInstanceWithoutConstructor();

		$this->call_private($controller, 'record_schedule_event', array(AIPS_History_Event_Type::SCHEDULE_DELETED, 8, (object) array('title' => 'Morning run'), true));
		$found = $this->latest_container('schedule_lifecycle');
		$this->assert_trigger_first($found['logs'], 'manual');
		$this->assertStringContainsString('Morning run', $found['logs'][2]['message']);

		$this->call_private($controller, 'record_schedule_bulk_delete', array(array(1, 2, 3), 3, true));
		$found = $this->latest_container('schedule_lifecycle');
		$this->assertSame(array(1, 2, 3), $found['logs'][2]['input']['schedule_ids']);
	}

	public function test_author_event_helper_records_lifecycle() {
		$controller = (new ReflectionClass('AIPS_Authors_Controller'))->newInstanceWithoutConstructor();

		$this->call_private($controller, 'record_author_event', array(AIPS_History_Event_Type::AUTHOR_DELETED, 9, 'Ada', true));

		$found = $this->latest_container('author_lifecycle');
		$this->assert_trigger_first($found['logs'], 'manual');
		$this->assertSame('author_deleted', $found['logs'][2]['input']['event_type']);
	}

	// -- End to end: recorded container -> History modal payload ---------------

	public function test_modal_payload_shows_trigger_in_overview_and_first_two_timeline_rows() {
		AIPS_History_Event_Recorder::instance()->record_simple(
			AIPS_History_Event_Type::SOURCE_FETCHED,
			true,
			'Fetched source #3 (1200 characters)',
			'source_fetch',
			array('event' => 'Source fetch', 'detail' => 'Source #3'),
			'scheduled'
		);
		$found   = $this->latest_container('source_fetch');
		$history = new AIPS_History();
		$item    = (new AIPS_History_Repository())->get_by_id((int) $found['row']->id);

		$payload   = $this->call_private($history, 'prepare_history_modal_view_data', array($item, false));
		$cards     = array();
		foreach ($payload['container']['detail_cards'] as $card) {
			$cards[$card['label']] = $card['value'];
		}

		$this->assertSame('Automatic (Scheduled run)', $cards['Trigger Type']);
		$this->assertStringContainsString('Source #3', $cards['Triggered By']);

		$events = $payload['timeline_data']['events'];
		$this->assertSame('Trigger Source', $events[0]['title']);
		$this->assertSame('Trigger Method', $events[1]['title']);
	}

	public function test_modal_payload_hides_trigger_cards_for_unclassifiable_legacy_container() {
		$repo = new AIPS_History_Repository();
		$id   = $repo->create(array(
			'uuid'            => wp_generate_uuid4(),
			'creation_method' => 'notification_sent',
			'status'          => 'completed',
		));
		$item = $repo->get_by_id((int) $id);

		$payload = $this->call_private(new AIPS_History(), 'prepare_history_modal_view_data', array($item, false));
		$labels  = array_column($payload['container']['detail_cards'], 'label');

		$this->assertNotContains('Trigger Type', $labels);
		$this->assertNotContains('Triggered By', $labels);
	}

	// -- Review fixes -----------------------------------------------------------

	/**
	 * @dataProvider notification_methods
	 */
	public function test_notification_handler_treats_bulk_retry_and_regenerate_as_manual($creation_method, $expected) {
		$handler = (new ReflectionClass('AIPS_Notifications_Event_Handler'))->newInstanceWithoutConstructor();
		$context = new AIPS_Template_Context((object) array('id' => 1, 'name' => 'T', 'prompt_template' => 'p'), null, null, $creation_method);

		$this->assertSame($expected, $this->call_private($handler, 'extract_creation_method', array($context)));
	}

	public static function notification_methods() {
		return array(
			'manual'              => array('manual', 'manual'),
			'retry'               => array('retry', 'manual'),
			'regenerate'          => array('regenerate', 'manual'),
			'bulk generate'       => array('bulk_generate', 'manual'),
			'planner post'        => array('planner_post', 'manual'),
			'author topic post'   => array('author_topic_post', 'manual'),
			'scheduled'           => array('scheduled', 'scheduled'),
			'unknown stays blank' => array('something_new', ''),
		);
	}

	public function test_modal_summary_does_not_call_post_generation_topic_generation() {
		$history = new AIPS_History();
		$base    = array('status' => 'completed', 'template_name' => '');

		$post_run  = $this->call_private($history, 'analyze_history_modal_summary', array($base + array('creation_method' => 'author_topic_post'), array()));
		$topic_run = $this->call_private($history, 'analyze_history_modal_summary', array($base + array('creation_method' => 'author_topic_generation'), array()));

		$this->assertNotSame('Author topic generation', $post_run['what_happened']);
		$this->assertSame('Author topic generation', $topic_run['what_happened']);
	}

	public function test_every_creation_method_literal_in_the_plugin_is_classified_or_allowlisted() {
		// Container types that intentionally have no manual/automatic signal of their own.
		$allowlist = array(
			'content_indexing', 'content_index_operation', 'notification_sent', 'schedule_lifecycle',
			'post_generation', 'topic_post_generation', 'taxonomy_generation', 'stress_test', 'bulk_schedule',
			'research_run', 'source_fetch', 'gsc_sync', 'link_index_run',
		);
		$files   = glob(dirname(__DIR__) . '/includes/*.php');
		$files[] = dirname(__DIR__) . '/ai-post-scheduler.php';
		$found   = array();

		foreach ($files as $file) {
			$code = file_get_contents($file);
			if (preg_match_all("/creation_method'?\s*(?:=>|=)\s*'([a-z_]+)'/", $code, $m)) {
				$found = array_merge($found, $m[1]);
			}
			if (preg_match_all("/history_service->create\(\s*'([a-z_]+)'/", $code, $m)) {
				$found = array_merge($found, $m[1]);
			}
		}

		$unclassified = array();
		foreach (array_unique($found) as $method) {
			if (AIPS_Generation_Trigger::classify_creation_method($method) === 'unknown' && !in_array($method, $allowlist, true)) {
				$unclassified[] = $method;
			}
		}

		$this->assertSame(array(), array_values($unclassified), 'New creation_method values must be added to AIPS_Generation_Trigger (or the allowlist above).');
	}

	public function test_entity_change_helper_wording_for_success_and_failure() {
		$recorder = AIPS_History_Event_Recorder::instance();

		$recorder->record_entity_change(AIPS_History_Event_Type::AUTHOR_DELETED, 'author_lifecycle', AIPS_History_Subject::TYPE_AUTHOR, 7, 'Ada', true, 'Author', 'deleted', 'delete');
		$found = $this->latest_container('author_lifecycle');
		$this->assertSame('Author "Ada" (ID 7) deleted', $found['logs'][2]['message']);
		$this->assertStringContainsString('Author "Ada" (ID 7)', $found['logs'][0]['message']);

		$recorder->record_entity_change(AIPS_History_Event_Type::AUTHOR_DELETED, 'author_lifecycle', AIPS_History_Subject::TYPE_AUTHOR, 7, '', false, 'Author', 'deleted', 'delete');
		$found = $this->latest_container('author_lifecycle');
		$this->assertSame('failed', $found['row']->status);
		$this->assertStringContainsString('Failed to delete author (ID 7)', $found['logs'][2]['message']);
	}

	public function test_bulk_schedule_delete_keeps_item_types() {
		$controller = (new ReflectionClass('AIPS_Schedule_Controller'))->newInstanceWithoutConstructor();
		$items      = array(array('id' => 4, 'type' => 'template'), array('id' => 4, 'type' => 'author_post'));

		$this->call_private($controller, 'record_schedule_bulk_delete', array(array(4, 4), 2, true, $items));

		$found = $this->latest_container('schedule_lifecycle');
		$this->assertSame($items, $found['logs'][2]['input']['items']);
	}

	public function test_regeneration_reuses_a_container_passed_in_the_context() {
		$service   = (new ReflectionClass('AIPS_Component_Regeneration_Service'))->newInstanceWithoutConstructor();
		$container = (new ReflectionClass('AIPS_History_Container'))->newInstanceWithoutConstructor();

		$resolved = $this->call_private($service, 'resolve_history_container', array(array('history_container' => $container), 1, 2));

		$this->assertSame($container, $resolved);
	}

	public function test_record_lifecycle_never_throws() {
		$service = $this->createMock(AIPS_History_Service_Interface::class);
		$service->method('create')->willThrowException(new RuntimeException('boom'));
		$recorder = new AIPS_History_Event_Recorder($service);

		$result = $recorder->record_simple(AIPS_History_Event_Type::SOURCE_FETCHED, true, 'x', 'source_fetch');

		$this->assertFalse($result);
	}
}
