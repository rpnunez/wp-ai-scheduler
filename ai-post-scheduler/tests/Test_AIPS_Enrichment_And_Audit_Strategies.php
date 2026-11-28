<?php
/**
 * Tests for Consolidation, Competitor Gap, Integration Fields, Affiliate Links,
 * AI Assistance, Taxonomy, Planner, and Content Audit Generation Strategies.
 *
 * @package AI_Post_Scheduler
 */

/**
 * Class Test_AIPS_Enrichment_And_Audit_Strategies
 */
class Test_AIPS_Enrichment_And_Audit_Strategies extends WP_UnitTestCase {

	/**
	 * Registry registers enrichment, research, audit, and AI assistance components cleanly.
	 */
	public function test_registry_registers_enrichment_and_audit_components() {
		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_post_components();
		$registry->register_default_research_components();
		$registry->register_default_enrichment_components();
		$registry->register_default_ai_assistance_components();

		$this->assertTrue($registry->has('post_integration_fields', 'posts'));
		$this->assertTrue($registry->has('post_affiliate_links', 'posts'));
		$this->assertTrue($registry->has('competitor_gap', 'research'));
		$this->assertTrue($registry->has('post_consolidation', 'consolidation'));
		$this->assertTrue($registry->has('content_audit_pass', 'content_audit'));
		$this->assertTrue($registry->has('field_suggestion', 'ai_assistance'));
	}

	/**
	 * AIPS_Consolidation_Generation_Strategy merges two posts and sanitizes fenced HTML.
	 */
	public function test_consolidation_strategy_pipeline_execution() {
		$keep_id   = self::factory()->post->create(array('post_title' => 'Keep Post', 'post_content' => '<p>Keep body.</p>', 'post_status' => 'publish'));
		$retire_id = self::factory()->post->create(array('post_title' => 'Retire Post', 'post_content' => '<p>Retire body.</p>', 'post_status' => 'publish'));

		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->expects($this->once())
			->method('generate_text')
			->willReturn("```html\n<p>Unified canonical article content.</p>\n```");

		$context  = new AIPS_Consolidation_Generation_Context(get_post($keep_id), get_post($retire_id));
		$strategy = new AIPS_Consolidation_Generation_Strategy(null, $ai_mock);
		$pipeline = new AIPS_Generation_Pipeline($strategy, new AIPS_Generation_Component_Registry());
		$result   = $pipeline->execute($context);

		$this->assertTrue($result->is_success());
		$this->assertSame('<p>Unified canonical article content.</p>', $result->get_data());
		$this->assertSame(array('post_consolidation' => true), $result->get_component_statuses());
	}

	/**
	 * AIPS_Competitor_Gap_Component executes via AIPS_Research_Generation_Strategy (MODE_GAP_SUGGESTIONS).
	 */
	public function test_competitor_gap_component_via_research_strategy() {
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->expects($this->once())
			->method('generate_text')
			->willReturn(wp_json_encode(array(
				array(
					'title'     => 'Bridge Topic One',
					'rationale' => 'Strengthens cluster depth.',
					'brief'     => 'Cover advanced workflows.',
				),
			)));

		$context = new AIPS_Research_Generation_Context(
			AIPS_Research_Generation_Context::MODE_GAP_SUGGESTIONS,
			array(
				'niche' => 'WordPress',
				'topic' => array(
					'post_title'   => 'WP Performance',
					'post_excerpt' => 'Speed tips.',
					'cluster_name' => 'Performance',
				),
			)
		);

		$strategy = new AIPS_Research_Generation_Strategy(null, $ai_mock);
		$pipeline = new AIPS_Generation_Pipeline($strategy, new AIPS_Generation_Component_Registry());
		$result   = $pipeline->execute($context);

		$this->assertTrue($result->is_success());
		$data = $result->get_data();
		$this->assertCount(1, $data);
		$this->assertSame('Bridge Topic One', $data[0]['title']);
	}

	/**
	 * AIPS_Affiliate_Links_Post_Component injects rel="nofollow sponsored" anchor cleanly.
	 */
	public function test_affiliate_links_post_component_injection() {
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->expects($this->once())
			->method('generate_json')
			->willReturn(array(
				'match'       => 'Try managed WordPress hosting for faster sites.',
				'replacement' => 'Try [[managed WordPress hosting]] for faster sites.',
			));

		$component = new AIPS_Affiliate_Links_Post_Component(null, $ai_mock);
		$updated   = $component->inject_affiliate_link(
			'Intro. Try managed WordPress hosting for faster sites. Outro.',
			'https://partner.example.com/host',
			'hosting'
		);

		$this->assertStringContainsString('href="https://partner.example.com/host"', $updated);
		$this->assertStringContainsString('rel="nofollow sponsored"', $updated);
		$this->assertStringContainsString('>managed WordPress hosting</a>', $updated);
	}

	/**
	 * AIPS_Content_Audit_Strategy executes JSON pass with text fallback cleanly.
	 */
	public function test_content_audit_strategy_json_and_text_fallback() {
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->expects($this->once())
			->method('generate_json')
			->willReturn(new WP_Error('json_failed', 'Schema unsupported'));
		$ai_mock->expects($this->once())
			->method('generate_text')
			->willReturn("```json\n[{\"topic\":\"Missing Guide\"}]\n```");

		$context  = new AIPS_Content_Audit_Generation_Context('topic_gaps', 'Audit prompt', array('temperature' => 0.6), 0.6, false);
		$strategy = new AIPS_Content_Audit_Strategy(null, $ai_mock);
		$pipeline = new AIPS_Generation_Pipeline($strategy, new AIPS_Generation_Component_Registry());
		$result   = $pipeline->execute($context);

		$this->assertTrue($result->is_success());
		$this->assertSame(array(array('topic' => 'Missing Guide')), $result->get_data());
	}

	/**
	 * AIPS_AI_Assistance_Strategy and AIPS_AI_Assistance_Service execute via AIPS_Generation_Pipeline.
	 */
	public function test_ai_assistance_strategy_and_service_pipeline_execution() {
		update_option('aips_ai_assistance_enabled', 1);

		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->expects($this->once())
			->method('generate_text')
			->willReturn('Pragmatic Cloud Engineering');

		$repo = $this->getMockBuilder(AIPS_AI_Assistance_Repository::class)
			->disableOriginalConstructor()
			->onlyMethods(array('create'))
			->getMock();
		$repo->expects($this->once())
			->method('create')
			->willReturn(42);

		$service = new AIPS_AI_Assistance_Service($ai_mock, $repo);
		$result  = $service->get_field_suggestion(
			array(
				'field_name'        => 'Focus Niche',
				'form_field_id'     => 'field_niche',
				'form_context'      => 'authors',
				'description'       => 'Primary niche for the author.',
				'influence'         => 'Guides topic ideation.',
				'expected_response' => 'Short phrase',
				'current_value'     => '',
			),
			'session-xyz',
			1
		);

		$this->assertIsArray($result);
		$this->assertSame('Pragmatic Cloud Engineering', $result['response']);
		$this->assertSame(42, $result['record_id']);
		$this->assertStringContainsString('Focus Niche', $result['prompt']);

		delete_option('aips_ai_assistance_enabled');
	}

	public function test_integration_fields_component_generate_and_apply() {
		$post_id = self::factory()->post->create(array('post_status' => 'draft'));
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('generate_json')->willReturnOnConsecutiveCalls(
			array('seo_title' => 'Mapped SEO Title'),
			array()
		);

		$component = new AIPS_Integration_Fields_Post_Component(null, $ai_mock);
		$context   = new AIPS_Template_Context((object) array(
			'id'              => 1,
			'name'            => 'Integration',
			'prompt_template' => 'Write',
			'title_prompt'    => '',
			'post_status'     => 'draft',
			'post_type'       => 'post',
		), null, 'Integrations');

		$generated = $component->generate($context, null, array(
			'generatable' => array(
				array(
					'mapping'   => (object) array('field_key' => 'seo_title', 'meta_key' => 'seo_title'),
					'shape'     => array(),
					'field_def' => array('label' => 'SEO Title', 'name' => 'seo_title'),
				),
			),
		));

		$this->assertSame(array('seo_title' => 'Mapped SEO Title'), $generated);
		$this->assertTrue($component->apply_to_post($post_id, $generated));
		$this->assertSame('Mapped SEO Title', get_post_meta($post_id, 'seo_title', true));

		$empty = $component->generate($context, null, array(
			'generatable' => array(
				array(
					'mapping'   => (object) array('field_key' => 'required_key', 'meta_key' => 'required_key'),
					'shape'     => array(),
					'field_def' => array('label' => 'Required', 'name' => 'required_key'),
				),
			),
		));
		$this->assertArrayNotHasKey('required_key', is_array($empty) ? $empty : array());
	}

	/**
	 * AI Assistance policy-disabled + DB insert failure branches, plus preview &
	 * failure coverage across Consolidation, Content Audit, Taxonomy, and Planner strategies.
	 */
	public function test_ai_assistance_failures_and_enrichment_strategy_previews() {
		$field_config = array(
			'field_name'        => 'Author Niche',
			'form_field_id'     => 'field_niche',
			'form_context'      => 'authors',
			'description'       => 'Primary niche.',
			'influence'         => 'Topics.',
			'expected_response' => 'Phrase',
		);
		$assist_ctx = new AIPS_AI_Assistance_Generation_Context($field_config, 'sess-err', 1);

		// 1. Policy disabled -> failure with ai_assistance_disabled.
		update_option('aips_ai_assistance_enabled', 0);
		$ai_ok = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_ok->method('generate_text')->willReturn('Suggested Value');

		$assist_strategy = new AIPS_AI_Assistance_Strategy(null, $ai_ok);
		$assist_pipeline = new AIPS_Generation_Pipeline($assist_strategy, new AIPS_Generation_Component_Registry());
		$disabled_res    = $assist_pipeline->execute($assist_ctx);
		$this->assertTrue($disabled_res->is_failure());
		$this->assertSame('ai_assistance_disabled', $assist_strategy->get_last_error()->get_error_code());

		// 2. Enabled + DB insert failure -> db_insert_failed; preview succeeds without DB insert.
		update_option('aips_ai_assistance_enabled', 1);
		$failing_repo = $this->getMockBuilder(AIPS_AI_Assistance_Repository::class)
			->disableOriginalConstructor()
			->onlyMethods(array('create'))
			->getMock();
		$failing_repo->method('create')->willReturn(false);

		$fail_db_strategy = new AIPS_AI_Assistance_Strategy(null, $ai_ok, $failing_repo);
		$fail_db_pipeline = new AIPS_Generation_Pipeline($fail_db_strategy, new AIPS_Generation_Component_Registry());
		$db_fail_res      = $fail_db_pipeline->execute($assist_ctx);
		$this->assertTrue($db_fail_res->is_failure());
		$this->assertSame('db_insert_failed', $fail_db_strategy->get_last_error()->get_error_code());

		$preview_assist = $fail_db_pipeline->execute_preview($assist_ctx);
		$this->assertIsArray($preview_assist);
		delete_option('aips_ai_assistance_enabled');

		// 3. Taxonomy & Planner strategy execute + execute_preview.
		$post_id  = self::factory()->post->create(array('post_title' => 'Observability in PHP', 'post_excerpt' => 'OpenTelemetry traces.'));
		$tax_ai   = $this->createMock(AIPS_AI_Service_Interface::class);
		$tax_ai->method('is_available')->willReturn(true);
		$tax_ai->method('generate_text')->willReturn("Observability\nTracing");

		$tax_repo = new class() {
			public $created = array();
			public function insert($row) {
				$id                  = count($this->created) + 1;
				$row['id']           = $id;
				$this->created[$id]  = (object) $row;
				return $id;
			}
			public function get_by_id($id) {
				return $this->created[$id] ?? null;
			}
		};
		$tax_strategy = new AIPS_Taxonomy_Generation_Strategy(null, $tax_ai, $tax_repo);
		$tax_pipeline = new AIPS_Generation_Pipeline($tax_strategy, new AIPS_Generation_Component_Registry());
		$tax_ctx      = new AIPS_Taxonomy_Generation_Context('post_tag', array($post_id));

		$tax_exec = $tax_pipeline->execute($tax_ctx);
		$this->assertTrue($tax_exec->is_success());
		$this->assertCount(2, $tax_repo->created);
		$this->assertIsArray($tax_pipeline->execute_preview($tax_ctx));

		$planner_gen = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array('is_available', 'generate_content'))
			->getMock();
		$planner_gen->method('is_available')->willReturn(true);
		$planner_gen->method('generate_content')->willReturn('["Topic 1", "Topic 2"]');
		$planner_strategy = new AIPS_Planner_Topic_Strategy(null, $planner_gen);
		$planner_pipeline = new AIPS_Generation_Pipeline($planner_strategy, new AIPS_Generation_Component_Registry());
		$planner_ctx      = new AIPS_Planner_Topic_Generation_Context('OpenTelemetry', 2);
		$this->assertTrue($planner_pipeline->execute($planner_ctx)->is_success());
		$this->assertIsArray($planner_pipeline->execute_preview($planner_ctx));
	}

	/**
	 * AIPS_Competitor_Gap_Component falls back to 3 default bridge suggestions when AI
	 * returns malformed JSON and propagates WP_Error when AI fails.
	 */
	public function test_competitor_gap_component_invalid_json_fallback_and_wp_error_propagation() {
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('generate_text')->willReturnOnConsecutiveCalls(
			'Unstructured prose that is not JSON',
			new WP_Error('gap_ai_down', 'AI service unavailable')
		);

		$context = new AIPS_Research_Generation_Context(
			AIPS_Research_Generation_Context::MODE_GAP_SUGGESTIONS,
			array(
				'niche' => 'WordPress',
				'topic' => array(
					'post_title'   => 'Object Caching Internals',
					'post_excerpt' => 'Persistent Redis caching.',
					'cluster_name' => 'Performance',
				),
			)
		);

		$strategy = new AIPS_Research_Generation_Strategy(null, $ai_mock);
		$pipeline = new AIPS_Generation_Pipeline($strategy, new AIPS_Generation_Component_Registry());

		$fallback_res = $pipeline->execute($context);
		$this->assertTrue($fallback_res->is_success());
		$suggestions = $fallback_res->get_data();
		$this->assertCount(3, $suggestions);
		$this->assertStringContainsString('Object Caching Internals', $suggestions[0]['title']);

		$component_err = (new AIPS_Competitor_Gap_Component($ai_mock))->generate($context);
		$this->assertWPError($component_err);
		$this->assertSame('gap_ai_down', $component_err->get_error_code());
	}
}
