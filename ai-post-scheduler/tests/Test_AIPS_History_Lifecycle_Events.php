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
			'planner post'        => array('planner_post', 'scheduled'),
			'trending topic post' => array('trending_topic_post', 'scheduled'),
			'author topic post'   => array('author_topic_post', 'scheduled'),
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

		$this->assertSame('scheduled', $result['method']['method']);
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

	public function test_record_lifecycle_never_throws() {
		$service = $this->createMock(AIPS_History_Service_Interface::class);
		$service->method('create')->willThrowException(new RuntimeException('boom'));
		$recorder = new AIPS_History_Event_Recorder($service);

		$result = $recorder->record_simple(AIPS_History_Event_Type::SOURCE_FETCHED, true, 'x', 'source_fetch');

		$this->assertFalse($result);
	}
}
