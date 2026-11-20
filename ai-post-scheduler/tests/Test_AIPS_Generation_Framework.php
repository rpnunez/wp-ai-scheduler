<?php
/**
 * Tests for Universal Generation Strategy & Component Framework (Issue #2400 Phase 1).
 *
 * Covers:
 * - AIPS_Generation_Result universal payload/history_id extensions
 * - AIPS_Generation_Component_Registry registration, aliases, filtering, and defaults
 * - Concrete Post Components (keys, stages, schemas, sanitization, apply_to_post, reconcile_status)
 * - AIPS_Generation_Pipeline (Post strategy execute + preview, generic strategy success/partial/failure)
 * - AIPS_Component_Regeneration_Coordinator (typed result + unknown component handling)
 * - AIPS_Container bindings for Phase 1 framework singletons
 *
 * @package AI_Post_Scheduler
 */

/**
 * Class Test_AIPS_Generation_Framework
 */
class Test_AIPS_Generation_Framework extends WP_UnitTestCase {

	/**
	 * Build a minimal template generation context for tests.
	 *
	 * @param array $overrides Optional template field overrides.
	 * @return AIPS_Template_Context
	 */
	private function make_template_context(array $overrides = array()): AIPS_Template_Context {
		$defaults = array(
			'id'                      => 101,
			'name'                    => 'Framework Test Template',
			'prompt_template'         => 'Write an article about {{topic}}.',
			'title_prompt'            => 'Write a title for {{topic}}.',
			'slug_prompt'             => '',
			'generate_slug'           => 1,
			'post_status'             => 'draft',
			'post_type'               => 'post',
			'post_category'           => '',
			'post_tags'               => 'testing, architecture',
			'post_author'             => 1,
			'generate_featured_image' => 0,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => '',
			'include_sources'         => 0,
			'affiliate_links_enabled' => 0,
		);

		$template = (object) array_merge($defaults, $overrides);
		return new AIPS_Template_Context($template, null, 'PHP Design Patterns');
	}

	/**
	 * AIPS_Generation_Result supports universal data, history_id, and getters while keeping legacy readonly fields.
	 */
	public function test_generation_result_universal_accessors() {
		$statuses = array('post_title' => true, 'post_content' => true);
		$success  = AIPS_Generation_Result::success(55, $statuses, 1.25, 900);

		$this->assertTrue($success->is_success());
		$this->assertFalse($success->is_partial());
		$this->assertFalse($success->is_failure());
		$this->assertSame(55, $success->post_id);
		$this->assertSame(55, $success->get_data());
		$this->assertSame($statuses, $success->get_component_statuses());
		$this->assertSame(array(), $success->get_errors());
		$this->assertSame(900, $success->get_history_id());

		$topic_ids = array(10, 11, 12);
		$universal = AIPS_Generation_Result::from_data(
			$topic_ids,
			AIPS_Generation_Result::STATUS_COMPLETED,
			array('topic_title' => true),
			array(),
			42,
			0.75
		);

		$this->assertTrue($universal->is_success());
		$this->assertFalse($universal->has_post());
		$this->assertSame($topic_ids, $universal->get_data());
		$this->assertSame(array('topic_title' => true), $universal->get_component_statuses());
		$this->assertSame(42, $universal->get_history_id());
	}

	/**
	 * Registry registers all 8 default post components, resolves aliases, and filters by stage/metadata/AI edit.
	 */
	public function test_registry_defaults_aliases_and_filters() {
		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_post_components();

		$expected_keys = array(
			'post_content',
			'post_title',
			'post_excerpt',
			'post_slug',
			'featured_image',
			'post_images',
			'post_taxonomy',
			'post_meta',
		);

		foreach ($expected_keys as $key) {
			$this->assertTrue($registry->has($key, 'posts'), "Expected component {$key} to be registered.");
			$this->assertInstanceOf(AIPS_Post_Component_Interface::class, $registry->get($key, 'posts'));
		}

		// Alias resolution for short AI Edit keys.
		$this->assertSame('post_title', $registry->get('title', 'posts')->get_key());
		$this->assertSame('post_content', $registry->get('content', 'posts')->get_key());
		$this->assertSame('post_excerpt', $registry->get('excerpt', 'posts')->get_key());
		$this->assertSame('post_slug', $registry->get('slug', 'posts')->get_key());

		// Stage filtering.
		$metadata_stage = $registry->get_by_stage(AIPS_Post_Component_Interface::STAGE_METADATA, 'posts');
		$metadata_keys  = array_map(static function ($c) {
			return $c->get_key();
		}, $metadata_stage);
		$this->assertContains('post_title', $metadata_keys);
		$this->assertContains('post_excerpt', $metadata_keys);
		$this->assertContains('post_slug', $metadata_keys);

		// Metadata turn participation.
		$mt_keys = array_map(static function ($c) {
			return $c->get_key();
		}, $registry->get_metadata_turn_components('posts'));
		$this->assertContains('post_title', $mt_keys);
		$this->assertContains('post_excerpt', $mt_keys);
		$this->assertContains('post_slug', $mt_keys);
		$this->assertContains('featured_image', $mt_keys);
		$this->assertContains('post_images', $mt_keys);

		// AI editable filtering excludes post_taxonomy and post_meta.
		$editable_keys = array_map(static function ($c) {
			return $c->get_key();
		}, $registry->get_ai_editable('posts'));
		$this->assertContains('post_title', $editable_keys);
		$this->assertContains('post_content', $editable_keys);
		$this->assertNotContains('post_taxonomy', $editable_keys);
		$this->assertNotContains('post_meta', $editable_keys);
	}

	/**
	 * Concrete Post Components sanitize, apply_to_post, and reconcile_status cleanly.
	 */
	public function test_concrete_post_components_apply_and_reconcile() {
		$post_id = self::factory()->post->create(array(
			'post_title'   => 'Initial Title',
			'post_content' => '<p>Initial body</p>',
			'post_excerpt' => '',
			'post_name'    => 'initial-title',
			'post_status'  => 'draft',
		));

		$title_comp = new AIPS_Title_Post_Component();
		$this->assertSame('Clean Title', $title_comp->sanitize_value(' "Clean Title" '));
		$this->assertTrue($title_comp->apply_to_post($post_id, 'Updated Strategy Title'));
		$this->assertTrue($title_comp->reconcile_status($post_id));
		$this->assertSame('Updated Strategy Title', get_the_title($post_id));

		$slug_comp = new AIPS_Slug_Post_Component();
		$this->assertSame('my-custom-slug', $slug_comp->sanitize_value('"My Custom Slug!"'));
		$this->assertTrue($slug_comp->apply_to_post($post_id, 'my-custom-slug'));
		$this->assertTrue($slug_comp->reconcile_status($post_id));
		$this->assertSame('my-custom-slug', get_post($post_id)->post_name);

		$excerpt_comp = new AIPS_Excerpt_Post_Component();
		$this->assertFalse($excerpt_comp->reconcile_status($post_id));
		$this->assertTrue($excerpt_comp->apply_to_post($post_id, 'A concise generated excerpt.'));
		$this->assertTrue($excerpt_comp->reconcile_status($post_id));

		$content_comp = new AIPS_Content_Post_Component();
		$this->assertTrue($content_comp->apply_to_post($post_id, '<p>Updated body content.</p>'));
		$this->assertTrue($content_comp->reconcile_status($post_id));

		$meta_comp = new AIPS_Custom_Meta_Post_Component();
		$this->assertTrue($meta_comp->apply_to_post($post_id, array('aips_custom_flag' => 'enabled')));
		$this->assertSame('enabled', get_post_meta($post_id, 'aips_custom_flag', true));

		$tax_comp = new AIPS_Taxonomy_Post_Component();
		$this->assertTrue($tax_comp->apply_to_post($post_id, array('post_tag' => 'alpha, beta')));
		$this->assertTrue($tax_comp->reconcile_status($post_id));
	}

	/**
	 * AIPS_Generation_Pipeline executes a generic non-post strategy for success, partial, and required failure.
	 */
	public function test_pipeline_generic_strategy_execution_outcomes() {
		$context  = $this->make_template_context();
		$registry = new AIPS_Generation_Component_Registry();

		$make_component = static function (string $key, bool $required, $result_value) {
			return new class($key, $required, $result_value) implements AIPS_Generation_Component_Interface {
				private $key;
				private $required;
				private $result_value;

				public function __construct($key, $required, $result_value) {
					$this->key          = $key;
					$this->required     = $required;
					$this->result_value = $result_value;
				}
				public function get_key(): string { return $this->key; }
				public function get_label(): string { return $this->key; }
				public function get_strategy_key(): string { return 'custom'; }
				public function is_enabled(AIPS_Generation_Context $context): bool { return true; }
				public function is_required(): bool { return $this->required; }
				public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string { return 'prompt'; }
				public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) { return $this->result_value; }
				public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) { return $this->result_value; }
				public function generate_variants($target_id, AIPS_Generation_Context $context, int $count = 3, array $options = array()) { return array($this->result_value); }
				public function apply($target, $value): bool { return true; }
				public function reconcile_status($target_id): bool { return true; }
				public function supports_ai_edit(): bool { return true; }
				public function sanitize_value($value) { return $value; }
			};
		};

		$make_strategy = static function (array $components) {
			return new class($components) implements AIPS_Generation_Strategy_Interface {
				private $components;
				public function __construct(array $components) { $this->components = $components; }
				public function get_key(): string { return 'custom'; }
				public function get_label(): string { return 'Custom'; }
				public function get_stages(): array { return array('stage_1'); }
				public function get_components(AIPS_Generation_Context $context): array { return $this->components; }
				public function before_pipeline(AIPS_Generation_Context $context): void {}
				public function after_pipeline(AIPS_Generation_Context $context, AIPS_Generation_Result $result): AIPS_Generation_Result { return $result; }
			};
		};

		// 1. All succeed.
		$pipeline_ok = new AIPS_Generation_Pipeline(
			$make_strategy(array($make_component('a', true, 'val_a'), $make_component('b', false, 'val_b'))),
			$registry
		);
		$res_ok = $pipeline_ok->execute($context);
		$this->assertTrue($res_ok->is_success());
		$this->assertSame(array('a' => 'val_a', 'b' => 'val_b'), $res_ok->get_data());
		$this->assertSame(array('a' => true, 'b' => true), $res_ok->get_component_statuses());

		// 2. Optional component fails -> partial.
		$pipeline_partial = new AIPS_Generation_Pipeline(
			$make_strategy(array(
				$make_component('a', true, 'val_a'),
				$make_component('b', false, new WP_Error('opt_fail', 'Optional error')),
			)),
			$registry
		);
		$res_partial = $pipeline_partial->execute($context);
		$this->assertTrue($res_partial->is_partial());
		$this->assertSame(array('a' => 'val_a'), $res_partial->get_data());
		$this->assertSame(array('a' => true, 'b' => false), $res_partial->get_component_statuses());
		$this->assertSame(array('Optional error'), $res_partial->get_errors());

		// 3. Required component fails -> failure.
		$pipeline_fail = new AIPS_Generation_Pipeline(
			$make_strategy(array(
				$make_component('a', true, new WP_Error('req_fail', 'Fatal error')),
				$make_component('b', false, 'val_b'),
			)),
			$registry
		);
		$res_fail = $pipeline_fail->execute($context);
		$this->assertTrue($res_fail->is_failure());
		$this->assertSame(array('Fatal error'), $res_fail->get_errors());
		$this->assertSame(array('a' => false), $res_fail->get_component_statuses());
	}

	/**
	 * AIPS_Component_Regeneration_Coordinator returns typed AIPS_Generation_Result and handles unknown keys.
	 */
	public function test_regeneration_coordinator_delegates_and_handles_unknown() {
		$context = $this->make_template_context();
		$post_id = self::factory()->post->create(array(
			'post_title'   => 'Old Title',
			'post_content' => '<p>Body text</p>',
		));

		$mock_generator = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array('supports_conversation', 'set_conversation', 'set_history_container', 'generate_title_for_context'))
			->getMock();
		$mock_generator->method('supports_conversation')->willReturn(false);
		$mock_generator->expects($this->once())
			->method('generate_title_for_context')
			->willReturn('Regenerated Title');

		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_post_components($mock_generator);

		$coordinator = new AIPS_Component_Regeneration_Coordinator($registry, $mock_generator);

		$result = $coordinator->regenerate('posts', 'title', $post_id, $context, 'Make it punchy');
		$this->assertTrue($result->is_success());
		$this->assertSame('Regenerated Title', $result->get_data());
		$this->assertSame(array('post_title' => true), $result->get_component_statuses());
		$this->assertSame($post_id, $result->post_id);

		$unknown = $coordinator->regenerate('posts', 'nonexistent_component', $post_id, $context);
		$this->assertTrue($unknown->is_failure());
		$this->assertNotEmpty($unknown->get_errors());
	}

	/**
	 * AIPS_Container resolves Phase 1 singletons.
	 */
	public function test_container_resolves_generation_framework_singletons() {
		$container = AIPS_Container::get_instance();
		if (!$container->has(AIPS_Generation_Component_Registry::class)) {
			$this->markTestSkipped('Container bindings not initialized in this test bootstrap.');
		}

		$this->assertInstanceOf(
			AIPS_Generation_Component_Registry::class,
			$container->make(AIPS_Generation_Component_Registry::class)
		);
		$this->assertInstanceOf(
			AIPS_Post_Generation_Strategy::class,
			$container->make(AIPS_Post_Generation_Strategy::class)
		);
		$this->assertFalse(
			$container->has(AIPS_Generation_Pipeline::class),
			'Pipeline must not be bound as a posts-only process singleton.'
		);
		$this->assertInstanceOf(
			AIPS_Component_Regeneration_Coordinator::class,
			$container->make(AIPS_Component_Regeneration_Coordinator::class)
		);
	}

	/**
	 * Nested JSON objects must not TypeError; invalid payloads return WP_Error.
	 */
	public function test_parse_json_variants_response_rejects_non_scalar_elements() {
		$component = new AIPS_Title_Post_Component();
		$method    = new ReflectionMethod(AIPS_Abstract_Post_Component::class, 'parse_json_variants_response');
		$method->setAccessible(true);

		$ok = $method->invoke($component, 'Here is prose [before] ["Alpha", "Beta", "Gamma"]', 3);
		$this->assertSame(array('Alpha', 'Beta', 'Gamma'), $ok);

		$nested = $method->invoke($component, '[{"title":"Nope"}, "Keep me"]', 3);
		$this->assertSame(array('Keep me'), $nested);

		$object = $method->invoke($component, '{"title":"Nope"}', 3);
		$this->assertWPError($object);
		$this->assertSame('invalid_variants_payload', $object->get_error_code());
	}

	/**
	 * Production post pipeline execute_run handles required failure, optional partial, and completed persist.
	 */
	public function test_post_generation_strategy_execute_run_outcomes() {
		$context = $this->make_template_context(array('generate_featured_image' => 0));

		$history = new class() {
			public function with_session($context) {
				return $this;
			}
			public function get_id() {
				return 42;
			}
			public function record() {}
			public function complete_failure() {}
			public function complete_partial() {}
			public function complete_success() {}
		};

		$history_service = $this->createMock(AIPS_History_Service_Interface::class);
		$history_service->method('create')->willReturn($history);

		$logger = $this->createMock(AIPS_Logger_Interface::class);

		$fail_generator = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array(
				'set_history_container',
				'open_conversation_session',
				'run_content_stage',
				'close_conversation_session',
			))
			->getMock();
		$fail_generator->method('run_content_stage')->willReturn(new WP_Error('content_failed', 'Required content failed'));

		$fail_strategy = new AIPS_Post_Generation_Strategy(
			new AIPS_Generation_Component_Registry(),
			$fail_generator,
			$this->createMock(AIPS_Post_Manager::class),
			$history_service,
			$logger
		);
		$fail_pipeline = new AIPS_Generation_Pipeline($fail_strategy, new AIPS_Generation_Component_Registry());
		$fail_result   = $fail_pipeline->execute($context);
		$this->assertTrue($fail_result->is_failure());
		$this->assertSame(array('Required content failed'), $fail_result->get_errors());
		$this->assertSame(42, $fail_result->get_history_id());

		$ok_generator = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array(
				'set_history_container',
				'open_conversation_session',
				'run_content_stage',
				'run_metadata_stage',
				'get_current_source_snapshots',
				'close_conversation_session',
				'get_active_conversation',
			))
			->getMock();
		$ok_generator->method('run_content_stage')->willReturn('<p>Generated body.</p>');
		$ok_generator->method('run_metadata_stage')->willReturn(array(
			'title'                          => 'Generated Title',
			'slug'                           => 'generated-title',
			'excerpt'                        => 'Generated excerpt',
			'resolved_image_prompt'          => '',
			'resolved_content_image_prompts' => array(),
			'title_success'                  => true,
			'excerpt_success'                => true,
			'slug_success'                   => true,
		));
		$ok_generator->method('get_current_source_snapshots')->willReturn(array());
		$ok_generator->method('get_active_conversation')->willReturn(null);

		$post_manager = $this->getMockBuilder(AIPS_Post_Manager::class)
			->disableOriginalConstructor()
			->onlyMethods(array('create_post', 'update_generation_status_meta'))
			->getMock();
		$post_manager->method('create_post')->willReturn(555);
		$post_manager->expects($this->once())->method('update_generation_status_meta');

		$ok_strategy = new AIPS_Post_Generation_Strategy(
			new AIPS_Generation_Component_Registry(),
			$ok_generator,
			$post_manager,
			$history_service,
			$logger
		);
		$ok_pipeline = new AIPS_Generation_Pipeline($ok_strategy, new AIPS_Generation_Component_Registry());
		$ok_result   = $ok_pipeline->execute($context);
		$this->assertTrue($ok_result->is_success());
		$this->assertSame(555, $ok_result->get_data());
		$this->assertTrue($ok_result->get_component_statuses()['post_content']);
		$this->assertTrue($ok_result->get_component_statuses()['post_title']);

		$partial_generator = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array(
				'set_history_container',
				'open_conversation_session',
				'run_content_stage',
				'run_metadata_stage',
				'get_current_source_snapshots',
				'close_conversation_session',
				'get_active_conversation',
			))
			->getMock();
		$partial_generator->method('run_content_stage')->willReturn('<p>Generated body.</p>');
		$partial_generator->method('run_metadata_stage')->willReturn(array(
			'title'                          => 'Generated Title',
			'slug'                           => 'generated-title',
			'excerpt'                        => '',
			'resolved_image_prompt'          => '',
			'resolved_content_image_prompts' => array(),
			'title_success'                  => true,
			'excerpt_success'                => false,
			'slug_success'                   => true,
		));
		$partial_generator->method('get_current_source_snapshots')->willReturn(array());
		$partial_generator->method('get_active_conversation')->willReturn(null);

		$partial_manager = $this->getMockBuilder(AIPS_Post_Manager::class)
			->disableOriginalConstructor()
			->onlyMethods(array('create_post', 'update_generation_status_meta'))
			->getMock();
		$partial_manager->method('create_post')->willReturn(556);

		$partial_strategy = new AIPS_Post_Generation_Strategy(
			new AIPS_Generation_Component_Registry(),
			$partial_generator,
			$partial_manager,
			$history_service,
			$logger
		);
		$partial_result = (new AIPS_Generation_Pipeline($partial_strategy, new AIPS_Generation_Component_Registry()))->execute($context);
		$this->assertTrue($partial_result->is_partial());
		$this->assertSame(556, $partial_result->get_data());
		$this->assertFalse($partial_result->get_component_statuses()['post_excerpt']);
	}
}
