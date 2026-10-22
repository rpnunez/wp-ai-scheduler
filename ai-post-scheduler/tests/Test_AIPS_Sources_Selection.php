<?php
/** Source selection is read only until a request is submitted. */
class Test_AIPS_Sources_Selection extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
	}

	public function test_empty_groups_have_actionable_diagnostic() {
		$selection = (new AIPS_Prompt_Builder())->build_sources_selection(array());
		$this->assertSame('', $selection['block']);
		$this->assertSame('no_groups', $selection['diagnostics'][0]['reason']);
		$this->assertSame(array(), $selection['snapshots']);
	}

	public function test_inactive_and_mismatched_sources_do_not_broaden_scope() {
		$repo = new AIPS_Sources_Repository();
		$id = $repo->create(array('url' => 'https://inactive.selection.example.com', 'is_active' => 0));
		$repo->set_source_terms($id, array(793));
		$builder = new AIPS_Prompt_Builder(null, null, $repo);
		$this->assertSame('no_active_sources', $builder->build_sources_selection(array(793))['diagnostics'][0]['reason']);
		$repo->set_active($id, true);
		$this->assertSame('', $builder->build_sources_selection(array(794))['block']);
		$repo->delete($id);
	}

	public function test_url_change_retains_archive_but_renders_explicit_fallback() {
		$repo = new AIPS_Sources_Repository();
		$id = $repo->create(array('url' => 'https://old.selection.example.com', 'is_active' => 1));
		$repo->set_source_terms($id, array(795));
		$data = new AIPS_Sources_Data_Repository();
		$data->insert_if_new($id, array('url' => 'https://old.selection.example.com', 'extracted_text' => 'Old provenance', 'fetch_status' => 'success'));
		$row = $data->pick_next_for_prompt_bulk(array($id))[$id];
		$repo->update($id, array('url' => 'https://new.selection.example.com'));
		$selection = (new AIPS_Prompt_Builder(null, null, $repo))->build_sources_selection(array(795));
		$this->assertSame('no_content', $selection['diagnostics'][0]['reason']);
		$this->assertSame(array(), $selection['snapshots']);
		$this->assertStringContainsString('[No extracted content available;', $selection['block']);
		$this->assertStringNotContainsString('Old provenance', $selection['block']);
		$this->assertNotNull($data->get_by_id($row->id));
		$repo->delete($id);
	}

	public function test_content_and_topic_builds_report_missing_groups_and_reset_when_disabled() {
		$base = new AIPS_Prompt_Builder();
		$content = $base->get_post_content_builder();
		$template = (object) array('prompt_template' => 'Write {{topic}}', 'include_sources' => 1, 'source_group_ids' => '[]');
		$content->build($template, 'Example');
		$this->assertSame('no_groups', $content->get_sources_selection()['diagnostics'][0]['reason']);
		$template->include_sources = 0;
		$content->build($template, 'Example');
		$this->assertSame(array(), $content->get_sources_selection());
		$topic = new AIPS_Prompt_Builder_Topic($base);
		$author = (object) array('id' => 1, 'field_niche' => 'Science', 'topic_generation_quantity' => 1, 'include_sources' => 1, 'source_group_ids' => '[]');
		$topic->build($author);
		$this->assertSame('no_groups', $topic->get_sources_selection()['diagnostics'][0]['reason']);
		$author->include_sources = 0;
		$topic->build($author);
		$this->assertSame(array(), $topic->get_sources_selection());
	}

	public function test_failed_generation_records_selection_once_before_submission() {
		$reflection = new ReflectionClass(AIPS_Generator::class);
		$generator = $reflection->newInstanceWithoutConstructor();
		$builder = new class {
			public $recorded = array();
			public function record_sources_usage(array $snapshots) {
				$this->recorded = array_merge($this->recorded, $snapshots);
			}
		};
		$service = new class($builder) {
			private $builder;
			public $recorded_at_submission = array();
			public function __construct($builder) { $this->builder = $builder; }
			public function generate_text($prompt, $options) {
				if (isset($options['_aips_on_submission'])) {
					$options['_aips_on_submission']();
					$options['_aips_on_submission'](); // An injected transport must not double-commit.
				}
				$this->recorded_at_submission[] = count($this->builder->recorded);
				return new WP_Error('fixture_failure', 'Provider unavailable');
			}
		};
		$values = array(
			'prompt_builder' => $builder,
			'ai_service' => $service,
			'logger' => new class { public function log($message, $level, $context = array()) {} },
			'pending_sources_selection' => array('snapshots' => array(array('source_data_id' => 33, 'source_id' => 3)), 'diagnostics' => array(), 'group_ids' => array(4), 'sources' => array()),
		);
		foreach ($values as $name => $value) {
			$property = $reflection->getProperty($name);
			$property->setValue($generator, $value);
		}
		$this->assertWPError($generator->generate_content('Submitted references', array(), 'content'));
		$this->assertWPError($generator->generate_content('Second request', array(), 'content'));
		$this->assertSame(array(1, 1), $service->recorded_at_submission);
		$this->assertCount(1, $builder->recorded);
	}

	public function test_custom_service_preflight_rejection_does_not_record_usage() {
		$reflection = new ReflectionClass(AIPS_Generator::class);
		$generator = $reflection->newInstanceWithoutConstructor();
		$builder = new class {
			public $recorded = array();
			public function record_sources_usage(array $snapshots) { $this->recorded = $snapshots; }
		};
		$values = array(
			'prompt_builder' => $builder,
			'ai_service' => new class { public function generate_text($prompt, $options) { return new WP_Error('ai_unavailable', 'No submission'); } },
			'logger' => new class { public function log($message, $level, $context = array()) {} },
			'pending_sources_selection' => array('snapshots' => array(array('source_data_id' => 33)), 'diagnostics' => array()),
		);
		foreach ($values as $name => $value) { $reflection->getProperty($name)->setValue($generator, $value); }
		$this->assertWPError($generator->generate_content('References'));
		$this->assertSame(array(), $builder->recorded);
	}

	public function test_component_regeneration_records_explicit_selection_at_submission() {
		$sources = new AIPS_Sources_Repository();
		$id = $sources->create(array('url' => 'https://regeneration.selection.example.com', 'is_active' => 1));
		$sources->set_source_terms($id, array(796));
		$data = new AIPS_Sources_Data_Repository();
		$data->insert_if_new($id, array('url' => 'https://regeneration.selection.example.com', 'extracted_text' => 'Regeneration reference', 'fetch_status' => 'success'));
		$row = $data->pick_next_for_prompt_bulk(array($id))[$id];
		$ai = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai->method('generate_text')->willReturnCallback(function ($prompt, $options) use ($data, $row) {
			$this->assertStringContainsString('Regeneration reference', $prompt);
			$this->assertSame(0, (int) $data->get_by_id($row->id)->num_used);
			if (isset($options['_aips_on_submission'])) { $options['_aips_on_submission'](); }
			return 'Regenerated article';
		});
		$service = new AIPS_Component_Regeneration_Service();
		$reflection = new ReflectionClass($service);
		$reflection->getProperty('generator')->setValue($service, new AIPS_Generator(null, $ai));
		$template = (object) array('id' => 1, 'prompt_template' => 'Write {{topic}}', 'include_sources' => 1, 'source_group_ids' => '[796]');
		$post_id = self::factory()->post->create();
		$history = (new AIPS_History_Repository())->create(array('post_id' => $post_id, 'status' => 'completed'));
		$result = $service->regenerate_content(array('generation_context' => new AIPS_Template_Context($template, null, 'Example'), 'post_id' => $post_id, 'history_id' => $history));
		$this->assertSame('Regenerated article', $result);
		$this->assertSame(1, (int) $data->get_by_id($row->id)->num_used);
		$sources->delete($id);
	}

	public function test_topic_usage_and_history_distinguish_preflight_from_failed_submission() {
		$sources = new AIPS_Sources_Repository();
		$id = $sources->create(array('url' => 'https://topics.selection.example.com', 'is_active' => 1));
		$sources->set_source_terms($id, array(797));
		$data = new AIPS_Sources_Data_Repository();
		$data->insert_if_new($id, array('url' => 'https://topics.selection.example.com', 'extracted_text' => 'Topic reference', 'fetch_status' => 'success'));
		$row = $data->pick_next_for_prompt_bulk(array($id))[$id];
		$author = (object) array('id' => 1, 'name' => 'Fixture', 'field_niche' => 'Science', 'topic_generation_quantity' => 1, 'include_sources' => 1, 'source_group_ids' => '[797]');
		foreach (array(false, true) as $submitted) {
			$ai = $this->createMock(AIPS_AI_Service_Interface::class);
			$ai->method('generate_json')->willReturnCallback(function ($prompt, $options) use ($submitted) {
				$this->assertStringContainsString('Topic reference', $prompt);
				if ($submitted && isset($options['_aips_on_submission'])) {
					$options['_aips_on_submission']();
					$options['_aips_on_submission']();
				}
				return new WP_Error($submitted ? 'provider_failed' : 'ai_unavailable', 'Fixture failure');
			});
			$generator = new AIPS_Author_Topics_Generator($ai);
			$this->assertWPError($generator->generate_topics($author));
			$this->assertCount($submitted ? 1 : 0, $generator->get_last_sources_selection()['snapshots']);
			$this->assertSame($submitted ? 1 : 0, (int) $data->get_by_id($row->id)->num_used);
		}
		$sources->delete($id);
	}

	public function test_topic_history_preserves_each_selection_diagnostic() {
		$reflection = new ReflectionClass(AIPS_Author_Topics_Scheduler::class);
		$scheduler = $reflection->newInstanceWithoutConstructor();
		$diagnostics = array();
		foreach (array('no_groups', 'no_active_sources', 'no_content', 'prompt_budget') as $reason) {
			$diagnostics[] = array('reason' => $reason, 'group_ids' => array(2), 'source_ids' => array(3), 'omitted_source_ids' => array());
		}
		$generator = new class($diagnostics) {
			private $diagnostics;
			public function __construct($diagnostics) { $this->diagnostics = $diagnostics; }
			public function get_last_sources_selection() { return array('diagnostics' => $this->diagnostics); }
		};
		$reflection->getProperty('topics_generator')->setValue($scheduler, $generator);
		$history = new class {
			public $records = array();
			public function record(...$args) { $this->records[] = $args; }
		};
		$reflection->getMethod('record_sources_selection_history')->invoke($scheduler, $history);
		$this->assertCount(4, $history->records);
		foreach ($diagnostics as $index => $diagnostic) {
			$this->assertSame('warning', $history->records[$index][0]);
			$this->assertSame($diagnostic, $history->records[$index][4]['source_selection_diagnostic']);
		}
	}

	public function test_render_is_read_only_and_usage_is_explicit() {
		$repo = new AIPS_Sources_Repository();
		$id = $repo->create(array('url' => 'https://selection.example.com', 'is_active' => 1));
		$repo->set_source_terms($id, array(791));
		$data = new AIPS_Sources_Data_Repository();
		$data->insert_if_new($id, array('url' => 'https://selection.example.com', 'extracted_text' => 'Reference facts', 'fetch_status' => 'success'));
		$builder = new AIPS_Prompt_Builder(null, null, $repo);
		$selection = $builder->build_sources_selection(array(791));
		$builder->build_sources_block(array(791));
		$this->assertSame(0, (int) $data->pick_next_for_prompt_bulk(array($id))[$id]->num_used);
		$builder->record_sources_usage(array_merge($selection['snapshots'], $selection['snapshots']));
		$this->assertSame(1, (int) $data->pick_next_for_prompt_bulk(array($id))[$id]->num_used);
		$repo->delete($id);
	}

	public function test_budget_omits_sources_and_preserves_current_url() {
		$repo = new AIPS_Sources_Repository();
		$data = new AIPS_Sources_Data_Repository();
		$ids = array();
		update_option('aips_source_prompt_total_max_chars', 16000);
		update_option('aips_source_snippet_max_chars', 12000);
		foreach (array('a', 'b', 'c') as $key) {
			$url = 'https://' . $key . '.selection.example.com';
			$id = $repo->create(array('url' => $url, 'is_active' => 1));
			$ids[] = $id;
			$repo->set_source_terms($id, array(792));
			$data->insert_if_new($id, array('url' => $url, 'extracted_text' => str_repeat('字', 12000), 'fetch_status' => 'success'));
		}
		$selection = (new AIPS_Prompt_Builder(null, null, $repo))->build_sources_selection(array(792));
		$this->assertCount(2, $selection['snapshots']);
		$this->assertSame('prompt_budget', end($selection['diagnostics'])['reason']);
		$this->assertStringNotContainsString('id="' . $ids[2] . '"', $selection['block']);
		foreach ($ids as $id) { $repo->delete($id); }
		delete_option('aips_source_prompt_total_max_chars');
		delete_option('aips_source_snippet_max_chars');
	}
}
