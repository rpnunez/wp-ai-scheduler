<?php
/**
 * Contract Matrix Tests for the Universal Generation Framework
 *
 * Sweeps all 28 default components, all 10 strategies, and all generation
 * contexts (including AIPS_Abstract_Generation_Context's 24 default methods)
 * via PHPUnit data providers to verify interface contracts, stage wiring,
 * prompt building, and XSS/type-safe sanitization invariants.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

require_once __DIR__ . '/support/class-aips-generation-test-harness.php';

class Test_AIPS_Generation_Contract_Matrix extends WP_UnitTestCase {

	/**
	 * Provide all 28 default registered components from AIPS_Generation_Component_Registry.
	 *
	 * @return array<string, array{0:string,1:string,2:string}>
	 */
	public function provide_default_components(): array {
		$registry = (new AIPS_Generation_Component_Registry())->register_all_defaults();
		$datasets = array();

		foreach ($registry->all() as $strategy_key => $components) {
			foreach ($components as $component_key => $instance) {
				$label            = $strategy_key . ':' . $component_key;
				$datasets[$label] = array(
					$strategy_key,
					$component_key,
					get_class($instance),
				);
			}
		}

		return $datasets;
	}

	/**
	 * Verify that register_all_defaults() registers all 28 built-in components across 10 strategies.
	 */
	public function test_registry_registers_all_default_components() {
		$datasets = $this->provide_default_components();
		$this->assertCount(28, $datasets, 'Expected 28 built-in generation components across all strategies.');
	}

	/**
	 * Contract sweep across all 28 built-in generation components.
	 *
	 * @dataProvider provide_default_components
	 *
	 * @param string $expected_strategy_key Strategy key in registry.
	 * @param string $expected_component_key Component key in registry.
	 * @param string $expected_class        Expected concrete class name.
	 */
	public function test_component_contract_invariants(
		string $expected_strategy_key,
		string $expected_component_key,
		string $expected_class
	) {
		$ai_service = new AIPS_Generation_Test_AI_Service();
		AIPS_Container::get_instance()->singleton(AIPS_AI_Service_Interface::class, static function () use ($ai_service) {
			return $ai_service;
		});

		$registry  = (new AIPS_Generation_Component_Registry())->register_all_defaults();
		$component = $registry->get($expected_component_key, $expected_strategy_key);

		$this->assertInstanceOf($expected_class, $component);
		$this->assertInstanceOf(AIPS_Generation_Component_Interface::class, $component);
		$this->assertSame($expected_component_key, $component->get_key());
		$this->assertSame($expected_strategy_key, $component->get_strategy_key());
		$this->assertNotSame('', trim($component->get_label()), "Component {$expected_component_key} must expose a non-empty label.");

		$contexts = AIPS_Generation_Test_Fixtures::make_all_contexts();
		$context  = isset($contexts[$expected_strategy_key]) ? $contexts[$expected_strategy_key] : $contexts['posts'];

		$this->assertIsBool($component->is_enabled($context));
		$this->assertIsBool($component->is_required());
		$this->assertIsBool($component->supports_ai_edit());

		// Prompt contract: returns a string without throwing for its matching context.
		$prompt = $component->build_prompt($context);
		$this->assertIsString($prompt);

		// Post-specific stage & combined metadata-turn schema contract.
		if ($component instanceof AIPS_Post_Component_Interface) {
			$valid_stages = array(
				AIPS_Post_Component_Interface::STAGE_CONTENT,
				AIPS_Post_Component_Interface::STAGE_METADATA,
				AIPS_Post_Component_Interface::STAGE_PERSISTENCE,
				AIPS_Post_Component_Interface::STAGE_MEDIA,
				AIPS_Post_Component_Interface::STAGE_INTEGRATIONS,
				AIPS_Post_Component_Interface::STAGE_FINALIZATION,
			);
			$this->assertContains($component->get_stage(), $valid_stages);

			$schema = $component->get_metadata_turn_schema();
			$this->assertIsArray($schema);
			if ($component->participates_in_metadata_turn()) {
				$this->assertNotEmpty($schema, "Metadata-turn participant {$expected_component_key} must return a non-empty schema fragment.");
			} else {
				$this->assertSame(array(), $schema, "Non-participant {$expected_component_key} must return an empty schema fragment.");
			}

			// Invalid post IDs must always fail apply/reconcile safely.
			$this->assertFalse($component->apply(0, 'test'));
			$this->assertFalse($component->apply_to_post(0, 'test'));
			$this->assertFalse($component->reconcile_status(0));
		} else {
			$this->assertIsBool($component->reconcile_status(0));
			if (!$component->supports_ai_edit()) {
				$variants = $component->generate_variants(0, $context, 1);
				$this->assertTrue(is_array($variants) || is_wp_error($variants));
			}
		}

		// Sanitization contract across empty, scalar, array, and <script> payloads.
		$sanitized_empty  = $component->sanitize_value('');
		$sanitized_null   = $component->sanitize_value(null);
		$sanitized_array  = $component->sanitize_value(array());
		$sanitized_xss    = $component->sanitize_value('Safe Value <script>alert(1)</script>');

		$this->assertFalse(is_wp_error($sanitized_empty));
		$this->assertFalse(is_wp_error($sanitized_null));
		$this->assertFalse(is_wp_error($sanitized_array));
		if (is_string($sanitized_xss)) {
			$this->assertStringNotContainsString('<script>', $sanitized_xss, "Component {$expected_component_key}::sanitize_value() leaked <script> tag.");
		}
	}

	/**
	 * Provide all 10 concrete generation strategies and their expected keys.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public function provide_strategies(): array {
		return array(
			'posts'           => array(AIPS_Post_Generation_Strategy::class, 'posts'),
			'author_topics'   => array(AIPS_Topic_Generation_Strategy::class, 'author_topics'),
			'author_personas' => array(AIPS_Author_Persona_Strategy::class, 'author_personas'),
			'campaigns'       => array(AIPS_Campaign_Generation_Strategy::class, 'campaigns'),
			'research'        => array(AIPS_Research_Generation_Strategy::class, 'research'),
			'consolidation'   => array(AIPS_Consolidation_Generation_Strategy::class, 'consolidation'),
			'content_audit'   => array(AIPS_Content_Audit_Strategy::class, 'content_audit'),
			'taxonomy'        => array(AIPS_Taxonomy_Generation_Strategy::class, 'taxonomy'),
			'planner_topics'  => array(AIPS_Planner_Topic_Strategy::class, 'planner_topics'),
			'ai_assistance'   => array(AIPS_AI_Assistance_Strategy::class, 'ai_assistance'),
		);
	}

	/**
	 * Contract sweep across all 10 pipeline-executable strategies.
	 *
	 * @dataProvider provide_strategies
	 *
	 * @param string $strategy_class Strategy class name.
	 * @param string $expected_key   Expected strategy key.
	 */
	public function test_strategy_contract_invariants(string $strategy_class, string $expected_key) {
		$ai_service = new AIPS_Generation_Test_AI_Service();
		AIPS_Container::get_instance()->singleton(AIPS_AI_Service_Interface::class, static function () use ($ai_service) {
			return $ai_service;
		});

		/** @var AIPS_Pipeline_Executable_Strategy_Interface $strategy */
		$strategy = new $strategy_class();

		$this->assertInstanceOf(AIPS_Generation_Strategy_Interface::class, $strategy);
		$this->assertInstanceOf(AIPS_Pipeline_Executable_Strategy_Interface::class, $strategy);
		$this->assertSame($expected_key, $strategy->get_key());
		$this->assertNotSame('', trim($strategy->get_label()));

		$stages = $strategy->get_stages();
		$this->assertIsArray($stages);
		$this->assertNotEmpty($stages);

		$contexts = AIPS_Generation_Test_Fixtures::make_all_contexts();
		$context  = $contexts[$expected_key];

		$components = $strategy->get_components($context);
		$this->assertIsArray($components);
		$this->assertNotEmpty($components, "Strategy {$expected_key} should resolve at least one enabled component for its default fixture context.");
		foreach ($components as $comp) {
			$this->assertInstanceOf(AIPS_Generation_Component_Interface::class, $comp);
		}

		// Lifecycle hooks: before_pipeline resets last_error; after_pipeline preserves result contract.
		$strategy->before_pipeline($context);
		$this->assertNull($strategy->get_last_error());

		$seed_result = AIPS_Generation_Result::success(1, array('probe' => true));
		$after       = $strategy->after_pipeline($context, $seed_result);
		$this->assertInstanceOf(AIPS_Generation_Result::class, $after);
	}

	/**
	 * Verify all 24 default methods on AIPS_Abstract_Generation_Context.
	 */
	public function test_abstract_generation_context_defaults() {
		$ctx = new AIPS_Test_Abstract_Generation_Context_Stub();

		$this->assertSame('abstract_stub', $ctx->get_type());
		$this->assertSame(99, $ctx->get_id());
		$this->assertSame('Abstract Stub Context', $ctx->get_name());
		$this->assertSame('', $ctx->get_content_prompt());
		$this->assertSame('', $ctx->get_title_prompt());
		$this->assertNull($ctx->get_slug_prompt());
		$this->assertFalse($ctx->should_generate_slug());
		$this->assertNull($ctx->get_image_prompt());
		$this->assertFalse($ctx->should_generate_featured_image());
		$this->assertSame('ai_prompt', $ctx->get_featured_image_source());
		$this->assertSame('', $ctx->get_unsplash_keywords());
		$this->assertSame('', $ctx->get_media_library_ids());
		$this->assertFalse($ctx->has_content_images());
		$this->assertSame(array(), $ctx->get_content_images());
		$this->assertSame('draft', $ctx->get_post_status());
		$this->assertSame('post', $ctx->get_post_type());
		$this->assertSame(0, $ctx->get_post_category());
		$this->assertSame('', $ctx->get_post_tags());
		$this->assertSame(0, $ctx->get_post_author());
		$this->assertNull($ctx->get_article_structure_id());
		$this->assertNull($ctx->get_voice_id());
		$this->assertNull($ctx->get_voice());
		$this->assertNull($ctx->get_topic());
		$this->assertSame('manual', $ctx->get_creation_method());
		$this->assertFalse($ctx->get_include_sources());
		$this->assertSame(array(), $ctx->get_source_group_ids());
		$this->assertFalse($ctx->get_affiliate_links_enabled());
		$this->assertSame(
			array(
				'type' => 'abstract_stub',
				'id'   => 99,
				'name' => 'Abstract Stub Context',
			),
			$ctx->to_array()
		);
	}

	/**
	 * Provide all concrete strategy generation context keys.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public function provide_concrete_contexts(): array {
		return array(
			'author_topics'   => array('author_topics', 'author_topics'),
			'author_personas' => array('author_personas', 'author_personas'),
			'campaigns'       => array('campaigns', 'campaigns'),
			'research'        => array('research', 'research'),
			'consolidation'   => array('consolidation', 'consolidation'),
			'content_audit'   => array('content_audit', 'content_audit'),
			'taxonomy'        => array('taxonomy', 'taxonomy'),
			'planner_topics'  => array('planner_topics', 'planner_topics'),
			'ai_assistance'   => array('ai_assistance', 'ai_assistance'),
		);
	}

	/**
	 * Contract sweep across all 9 domain-specific generation contexts.
	 *
	 * @dataProvider provide_concrete_contexts
	 *
	 * @param string $fixture_key   Key in AIPS_Generation_Test_Fixtures::make_all_contexts().
	 * @param string $expected_type Expected get_type() return value.
	 */
	public function test_concrete_generation_context_getters_and_serialization(string $fixture_key, string $expected_type) {
		$contexts = AIPS_Generation_Test_Fixtures::make_all_contexts();
		$ctx      = $contexts[$fixture_key];

		$this->assertInstanceOf(AIPS_Generation_Context::class, $ctx);
		$this->assertInstanceOf(AIPS_Abstract_Generation_Context::class, $ctx);
		$this->assertSame($expected_type, $ctx->get_type());
		$this->assertIsString((string) $ctx->get_name());
		$this->assertIsString($ctx->get_content_prompt());
		$this->assertIsBool($ctx->get_include_sources());
		$this->assertIsArray($ctx->get_source_group_ids());

		$serialized = $ctx->to_array();
		$this->assertIsArray($serialized);
		$this->assertSame($expected_type, $serialized['type']);

		// Exercise domain-specific getters on each concrete context.
		if ($ctx instanceof AIPS_Author_Topic_Generation_Context) {
			$this->assertNotNull($ctx->get_author());
			$this->assertTrue($ctx->should_apply_auto_approval());
			$this->assertSame(3, $ctx->get_quantity());
			$this->assertSame('scheduled', $ctx->get_creation_method());
			$this->assertTrue($ctx->get_include_sources());
			$this->assertSame(array(3, 7), $ctx->get_source_group_ids());
		} elseif ($ctx instanceof AIPS_Author_Persona_Generation_Context) {
			$this->assertSame(3, $ctx->get_count());
			$this->assertSame('Cloud Native WordPress', $ctx->get_inputs()['site_niche']);
			$this->assertSame('Cloud Native WordPress', $ctx->get_topic());
		} elseif ($ctx instanceof AIPS_Campaign_Generation_Context) {
			$this->assertSame('WooCommerce Scaling', $ctx->get_intake()['topic_niche']);
			$this->assertSame(array('daily', 'weekly'), $ctx->get_available_frequencies());
			$this->assertSame(array('post'), $ctx->get_available_post_types());
			$this->assertArrayHasKey('how_to', $ctx->get_available_output_styles());
		} elseif ($ctx instanceof AIPS_Research_Generation_Context) {
			$this->assertSame(AIPS_Research_Generation_Context::MODE_TRENDING, $ctx->get_mode());
			$this->assertSame('PHP 8.3 Modernization', $ctx->get_niche());
			$this->assertSame(5, $ctx->get_count());
			$this->assertSame(array('readonly', 'enums'), $ctx->get_keywords());
			$this->assertSame(array(4), $ctx->get_term_ids());
			$this->assertNotEmpty($ctx->get_topic_payload());
			$this->assertCount(2, $ctx->get_comparison_topics());
		} elseif ($ctx instanceof AIPS_Consolidation_Generation_Context) {
			$this->assertInstanceOf(WP_Post::class, $ctx->get_keep_post());
			$this->assertInstanceOf(WP_Post::class, $ctx->get_retire_post());
			$this->assertSame('Preserve code benchmarks from both posts.', $ctx->get_instructions());
		} elseif ($ctx instanceof AIPS_Content_Audit_Generation_Context) {
			$this->assertSame('topic_gaps', $ctx->get_pass_type());
			$this->assertNotEmpty($ctx->get_prompt());
			$this->assertSame(0.3, $ctx->get_ai_options()['temperature']);
			$this->assertSame(0.4, $ctx->get_fallback_temperature());
			$this->assertFalse($ctx->is_text_only());
		} elseif ($ctx instanceof AIPS_Taxonomy_Generation_Context) {
			$this->assertSame('category', $ctx->get_taxonomy_type());
			$this->assertCount(2, $ctx->get_post_ids());
			$this->assertSame('Focus on technical developer categories.', $ctx->get_generation_prompt());
		} elseif ($ctx instanceof AIPS_Planner_Topic_Generation_Context) {
			$this->assertSame('WordPress Block Hooks API', $ctx->get_niche());
			$this->assertSame(5, $ctx->get_count());
			$this->assertSame('WordPress Block Hooks API', $ctx->get_topic());
		} elseif ($ctx instanceof AIPS_AI_Assistance_Generation_Context) {
			$this->assertSame('authors', $ctx->get_form_context());
			$this->assertSame('field_niche', $ctx->get_form_field_id());
			$this->assertSame('Author Niche', $ctx->get_field_name());
			$this->assertSame('sess_test_123', $ctx->get_session_id());
			$this->assertSame(1, $ctx->get_user_id());
			$this->assertNotEmpty($ctx->get_field_config());
		}
	}
}
