<?php
/**
 * Tests for the History trigger source / trigger method feature.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Generation_Trigger extends WP_UnitTestCase {

	/**
	 * Build a minimal recording stand-in for AIPS_History_Container.
	 *
	 * @return object
	 */
	private function make_history() {
		return new class {
			public $records = array();

			public function record($log_type, $message, $input = null, $output = null, $context = array()) {
				$this->records[] = compact('log_type', 'message', 'input', 'output', 'context');
				return count($this->records);
			}
		};
	}

	private function make_template($campaign_id = 0) {
		return (object) array(
			'id'              => 7,
			'name'            => 'Weekly Roundup',
			'prompt_template' => 'Write about {{topic}}',
			'campaign_id'     => $campaign_id,
		);
	}

	/**
	 * @dataProvider creation_method_provider
	 */
	public function test_classify_creation_method($creation_method, $expected) {
		$this->assertSame($expected, AIPS_Generation_Trigger::classify_creation_method($creation_method));
	}

	public static function creation_method_provider() {
		return array(
			'manual'                 => array('manual', 'manual'),
			'manual generation'      => array('manual_generation', 'manual'),
			'manual regeneration'    => array('manual_regeneration', 'manual'),
			'post review action'     => array('post_review_action', 'manual'),
			'campaign lifecycle'     => array('campaign_lifecycle', 'manual'),
			'bulk delete'            => array('bulk_delete', 'manual'),
			'bulk generate'          => array('bulk_generate', 'manual'),
			'retry'                  => array('retry', 'manual'),
			'uppercase'              => array('MANUAL', 'manual'),
			'scheduled'              => array('scheduled', 'scheduled'),
			'template schedule'      => array('template_schedule', 'scheduled'),
			'author topic generation' => array('author_topic_generation', 'scheduled'),
			'author post generation' => array('author_post_generation', 'scheduled'),
			'author embeddings'      => array('author_embeddings', 'scheduled'),
			'content indexing'       => array('content_indexing', 'unknown'),
			'notification sent'      => array('notification_sent', 'unknown'),
			'unrecognised'           => array('something_new', 'unknown'),
			'empty'                  => array('', 'unknown'),
			'null'                   => array(null, 'unknown'),
		);
	}

	public function test_describe_method_labels() {
		$manual = AIPS_Generation_Trigger::describe_method('manual');
		$this->assertSame('manual', $manual['method']);
		$this->assertSame('Manual', $manual['label']);

		$auto = AIPS_Generation_Trigger::describe_method('scheduled');
		$this->assertSame('scheduled', $auto['method']);
		$this->assertStringContainsString('Automatic', $auto['label']);

		$unknown = AIPS_Generation_Trigger::describe_method('content_indexing');
		$this->assertSame('unknown', $unknown['method']);
		$this->assertSame('Unknown', $unknown['label']);
	}

	public function test_unknown_methods_are_never_reported_as_automatic() {
		$this->assertFalse(AIPS_Generation_Trigger::is_manual_creation_method('post_review_action_typo'));
		$this->assertNotSame('scheduled', AIPS_Generation_Trigger::describe_method('brand_new_type')['method']);
	}

	public function test_detect_creation_method_uses_current_user() {
		wp_set_current_user(0);
		$this->assertSame('unknown', AIPS_Generation_Trigger::detect_creation_method());

		wp_set_current_user($this->factory->user->create(array('role' => 'administrator')));
		$this->assertSame('manual', AIPS_Generation_Trigger::detect_creation_method());
		wp_set_current_user(0);
	}

	public function test_template_context_trigger_context_round_trip() {
		$context = new AIPS_Template_Context($this->make_template(), null, 'Topic', 'scheduled');
		$this->assertSame(array(), $context->get_trigger_context());

		$returned = $context->set_trigger_context(array('schedule_id' => 4));
		$this->assertSame($context, $returned);
		$this->assertSame(array('schedule_id' => 4), $context->get_trigger_context());
	}

	public function test_describe_scheduled_template_context() {
		$context = new AIPS_Template_Context($this->make_template(), null, 'AI trends', 'scheduled');
		$context->set_trigger_context(array(
			'schedule_id'   => 4,
			'schedule_name' => 'Morning run',
			'frequency'     => 'daily',
		));

		$result = AIPS_Generation_Trigger::describe($context);

		$this->assertSame('scheduled', $result['method']['method']);
		$this->assertSame(4, $result['source']['schedule_id']);
		$this->assertSame('Morning run', $result['source']['schedule_name']);
		$this->assertSame(7, $result['source']['template_id']);
		$this->assertSame('Weekly Roundup', $result['source']['template_name']);
		$this->assertSame('AI trends', $result['source']['topic']);
		$this->assertStringContainsString('Schedule "Morning run" (ID 4)', $result['source_message']);
		$this->assertStringContainsString('Template "Weekly Roundup" (ID 7)', $result['source_message']);
		$this->assertStringContainsString('Topic "AI trends"', $result['source_message']);
	}

	public function test_describe_includes_campaign_when_template_has_one() {
		$context = new AIPS_Template_Context($this->make_template(99), null, null, 'scheduled');

		$result = AIPS_Generation_Trigger::describe($context);

		$this->assertSame(99, $result['source']['campaign_id']);
		$this->assertStringContainsString('Campaign', $result['source_message']);
		$this->assertStringContainsString('ID 99', $result['source_message']);
	}

	public function test_describe_context_without_creation_method_is_manual() {
		$context = new AIPS_Template_Context($this->make_template(), null, null);

		$this->assertSame('manual', AIPS_Generation_Trigger::describe($context)['method']['method']);
	}

	public function test_describe_includes_trigger_detail() {
		$context = new AIPS_Template_Context($this->make_template(), null, 'x', 'bulk_generate');
		$context->set_trigger_context(array('detail' => 'Planner bulk generation'));

		$result = AIPS_Generation_Trigger::describe($context);

		$this->assertSame('manual', $result['method']['method']);
		$this->assertStringStartsWith('Planner bulk generation', $result['source_message']);
	}

	public function test_describe_topic_context_reports_author_and_topic() {
		$author = (object) array('id' => 3, 'name' => 'Ada');
		$topic  = (object) array('id' => 12, 'topic_title' => 'Compilers');

		$result = AIPS_Generation_Trigger::describe(new AIPS_Topic_Context($author, $topic, '', 'scheduled'));

		$this->assertSame('scheduled', $result['method']['method']);
		$this->assertSame(3, $result['source']['author_id']);
		$this->assertSame('Ada', $result['source']['author_name']);
		$this->assertSame(12, $result['source']['topic_id']);
		$this->assertStringContainsString('Author "Ada" (ID 3)', $result['source_message']);
		$this->assertStringContainsString('Topic "Compilers"', $result['source_message']);
	}

	public function test_record_writes_source_then_method() {
		$history = $this->make_history();

		AIPS_Generation_Trigger::record($history, array('event' => 'Content indexing', 'post_id' => 55), 'scheduled');

		$this->assertCount(2, $history->records);
		$this->assertSame('trigger_source', $history->records[0]['log_type']);
		$this->assertStringContainsString('Content indexing', $history->records[0]['message']);
		$this->assertStringContainsString('Post (ID 55)', $history->records[0]['message']);
		$this->assertSame(55, $history->records[0]['input']['post_id']);

		$this->assertSame('trigger_method', $history->records[1]['log_type']);
		$this->assertSame('scheduled', $history->records[1]['input']['method']);
		$this->assertStringContainsString('Automatic', $history->records[1]['input']['method_label']);
	}

	public function test_record_with_empty_source_uses_fallback_text() {
		$history = $this->make_history();

		AIPS_Generation_Trigger::record($history, array(), 'manual');

		$this->assertStringContainsString('No originating', $history->records[0]['message']);
		$this->assertSame('manual', $history->records[1]['input']['method']);
	}

	public function test_record_tolerates_missing_history() {
		AIPS_Generation_Trigger::record(null, array('event' => 'x'), 'manual');
		AIPS_Generation_Trigger::record(new stdClass(), array('event' => 'x'), 'manual');
		$this->assertTrue(true);
	}

	public function test_format_source_message_is_empty_without_fields() {
		$this->assertSame('', AIPS_Generation_Trigger::format_source_message(array()));
	}

	public function test_history_container_maps_trigger_types_to_activity() {
		$map = new ReflectionMethod('AIPS_History_Container', 'map_log_type_to_history_type');
		$map->setAccessible(true);
		$container = (new ReflectionClass('AIPS_History_Container'))->newInstanceWithoutConstructor();

		$this->assertSame(AIPS_History_Type::ACTIVITY, $map->invoke($container, 'trigger_source'));
		$this->assertSame(AIPS_History_Type::ACTIVITY, $map->invoke($container, 'trigger_method'));
	}

	// -- History modal (Overview) ------------------------------------------------

	private function extract_trigger_info(array $logs, $history_item, array $container) {
		$history = new AIPS_History();
		$method  = new ReflectionMethod($history, 'extract_history_trigger_info');
		$method->setAccessible(true);

		return $method->invoke($history, $logs, $history_item, $container);
	}

	private function make_log($log_type, $message, array $input) {
		return array(
			'id'              => 1,
			'log_type'        => $log_type,
			'history_type_id' => AIPS_History_Type::ACTIVITY,
			'details'         => array('log_subtype' => $log_type, 'message' => $message, 'input' => $input),
		);
	}

	public function test_overview_prefers_recorded_trigger_entries() {
		$logs = array(
			$this->make_log('trigger_source', 'Started from: x', array('schedule_id' => 4, 'schedule_name' => 'Morning run', 'template_id' => 7, 'template_name' => 'Weekly Roundup')),
			$this->make_log('trigger_method', 'Triggered automatically', array('method' => 'scheduled', 'method_label' => 'Automatic (Scheduled run)')),
		);

		$info = $this->extract_trigger_info($logs, (object) array('id' => 1), array('creation_method' => 'manual'));

		$this->assertSame('scheduled', $info['method']);
		$this->assertSame('Automatic (Scheduled run)', $info['method_label']);
		$this->assertStringContainsString('Schedule "Morning run" (ID 4)', $info['source_message']);
	}

	public function test_overview_legacy_run_falls_back_to_known_creation_method() {
		$info = $this->extract_trigger_info(array(), (object) array('id' => 1, 'template_id' => 0), array('creation_method' => 'manual'));

		$this->assertSame('Manual', $info['method_label']);
	}

	/**
	 * @dataProvider non_generation_creation_method_provider
	 */
	public function test_overview_hides_trigger_for_unclassifiable_legacy_containers($creation_method) {
		$info = $this->extract_trigger_info(array(), (object) array('id' => 1), array('creation_method' => $creation_method));

		$this->assertSame('', $info['method_label']);
		$this->assertSame('', $info['source_message']);
	}

	public static function non_generation_creation_method_provider() {
		return array(
			'content indexing'  => array('content_indexing'),
			'notification sent' => array('notification_sent'),
			'schedule lifecycle' => array('schedule_lifecycle'),
		);
	}

	public function test_overview_reports_classified_non_generation_containers() {
		$campaign = $this->extract_trigger_info(array(), (object) array('id' => 1), array('creation_method' => 'campaign_lifecycle'));
		$this->assertSame('Manual', $campaign['method_label']);

		$embeddings = $this->extract_trigger_info(array(), (object) array('id' => 1), array('creation_method' => 'author_embeddings'));
		$this->assertStringContainsString('Automatic', $embeddings['method_label']);
	}

	public function test_overview_legacy_topic_run_lists_ids() {
		$item = (object) array('id' => 1, 'author_id' => 0, 'topic_id' => 12);

		$info = $this->extract_trigger_info(array(), $item, array('creation_method' => 'scheduled'));

		$this->assertStringContainsString('Topic (ID 12)', $info['source_message']);
	}
}
