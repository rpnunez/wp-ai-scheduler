<?php
/**
 * Regression tests for Universal Generation Strategy edge cases and invariants.
 *
 * Covers:
 * - Universal AI Edit: per-post edit_post check, missing post, pre-apply revision snapshot.
 * - Topic strategy History trace row is stored as an event, not a generation run.
 * - Featured-image stress case passes its prompt and never retries with a second image call.
 * - Consolidation output drops a leading H1 and script/style blocks.
 * - Campaign cluster/pillar AI Edit call the AI with the directive; empty clusters do not apply.
 * - Component registry extension hook fires once, after defaults, and extension overrides stick.
 * - Topic component prompts are assembled by AIPS_Prompt_Builder_Topic_Component.
 *
 * @package AI_Post_Scheduler
 */

/**
 * AI service stub that records prompts and image calls.
 */
class AIPS_Test_Review_Fixes_AI_Service implements AIPS_AI_Service_Interface {
	public $json_prompts = array();
	public $text_prompts = array();
	public $image_calls  = 0;
	public $json_response;

	public function __construct($json_response = array()) {
		$this->json_response = $json_response;
	}

	public function is_available() {
		return true;
	}

	public function generate_text($prompt, $options = array()) {
		$this->text_prompts[] = $prompt;
		return 'Generated text value.';
	}

	public function generate_json($prompt, $options = array()) {
		$this->json_prompts[] = $prompt;
		return $this->json_response;
	}

	public function generate_image($prompt, $options = array()) {
		$this->image_calls++;
		return new WP_Error('image_unsupported', 'Image generation is not stubbed.');
	}

	public function generate_embedding($text, $options = array()) {
		return array_fill(0, 8, 0.1);
	}

	public function supports_embeddings() {
		return false;
	}

	public function supports_conversation() {
		return false;
	}

	public function clear_call_log() {}

	public function get_call_log() {
		return array();
	}
}

/**
 * Silent logger.
 */
class AIPS_Test_Review_Fixes_Logger implements AIPS_Logger_Interface {
	public function log($message, $level = 'info', $context = array()) {}
	public function addSeparator($text) {}
}

/**
 * Minimal extension component used to override a default registration.
 */
class AIPS_Test_Review_Fixes_Topic_Title_Override extends AIPS_Topic_Title_Component {
}

class Test_AIPS_Generation_Strategy_Review_Fixes extends WP_UnitTestCase {

	/**
	 * @var callable|null
	 */
	private $cap_filter;

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user($this->factory->user->create(array('role' => 'administrator')));
	}

	public function tearDown(): void {
		if ($this->cap_filter) {
			remove_filter('map_meta_cap', $this->cap_filter, 10);
			$this->cap_filter = null;
		}
		remove_all_actions('aips_register_generation_components');
		$_POST    = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/**
	 * Run a controller AJAX handler and return the decoded JSON response.
	 *
	 * @param callable $handler Handler to invoke.
	 * @return array
	 */
	private function run_ajax(callable $handler) {
		$_REQUEST = $_POST;
		ob_start();
		try {
			$handler();
		} catch (WPAjaxDieContinueException $e) {
			// wp_send_json_* ends the request this way.
		} catch (WPAjaxDieStopException $e) {
			// wp_die() without output.
		}
		$output = ob_get_clean();

		$decoded = json_decode((string) $output, true);
		$this->assertIsArray($decoded, 'Expected a JSON response, got: ' . $output);

		return $decoded;
	}

	public function test_universal_ai_edit_denies_post_the_user_cannot_edit() {
		$post_id = $this->factory->post->create(array('post_content' => 'Original body.'));

		$this->cap_filter = function ($caps, $cap, $user_id, $args) use ($post_id) {
			if ($cap === 'edit_post' && isset($args[0]) && (int) $args[0] === $post_id) {
				return array('do_not_allow');
			}
			return $caps;
		};
		add_filter('map_meta_cap', $this->cap_filter, 10, 4);

		$_POST = array(
			'action'        => 'aips_universal_ai_edit',
			'nonce'         => wp_create_nonce('aips_ajax_nonce'),
			'strategy_key'  => 'posts',
			'component_key' => 'post_content',
			'target_id'     => $post_id,
			'apply'         => '1',
		);

		$controller = new AIPS_AI_Edit_Controller();
		$response   = $this->run_ajax(array($controller, 'ajax_universal_ai_edit'));

		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
		$this->assertSame('Original body.', get_post_field('post_content', $post_id));
	}

	public function test_universal_ai_edit_rejects_missing_post() {
		$_POST = array(
			'action'        => 'aips_universal_ai_edit',
			'nonce'         => wp_create_nonce('aips_ajax_nonce'),
			'strategy_key'  => 'posts',
			'component_key' => 'post_content',
			'target_id'     => 999999,
		);

		$controller = new AIPS_AI_Edit_Controller();
		$response   = $this->run_ajax(array($controller, 'ajax_universal_ai_edit'));

		$this->assertFalse($response['success']);
		$this->assertSame('not_found', $response['data']['code']);
	}

	public function test_universal_ai_edit_snapshots_current_value_before_apply() {
		$post_id = $this->factory->post->create(array('post_content' => '<p>Body before AI edit.</p>'));

		$history_repository = new AIPS_History_Repository();
		$history_id         = $history_repository->create(array(
			'post_id'         => $post_id,
			'creation_method' => 'manual',
			'status'          => 'completed',
		));
		$this->assertNotEmpty($history_id);

		$controller = new AIPS_AI_Edit_Controller();
		$method     = new ReflectionMethod($controller, 'capture_universal_post_revision');
		$method->setAccessible(true);

		$this->assertTrue($method->invoke($controller, $post_id, 'post_content'));

		$revisions = $history_repository->get_component_revisions($post_id, 'content', 10);
		$this->assertNotEmpty($revisions);
		$this->assertStringContainsString('Body before AI edit.', (string) $revisions[0]['value']);

		// A second call with the same value does not add a duplicate snapshot.
		$this->assertTrue($method->invoke($controller, $post_id, 'post_content'));
		$this->assertCount(count($revisions), $history_repository->get_component_revisions($post_id, 'content', 10));

		// Components without a revision type are skipped.
		$this->assertTrue($method->invoke($controller, $post_id, 'post_taxonomy'));
	}

	public function test_topic_strategy_history_trace_is_an_event_row() {
		global $wpdb;

		$author = (object) array(
			'id'                        => 4242,
			'name'                      => 'Trace Author',
			'field_niche'               => 'Gardening',
			'topic_generation_quantity' => 1,
			'auto_approval_mode'        => 'manual',
		);

		$ai = new AIPS_Test_Review_Fixes_AI_Service(array(
			array('title' => 'Balcony Herb Gardens for Beginners', 'score' => 80, 'keywords' => array('herbs')),
		));

		$topics_repo = new class() {
			public $created = array();
			public function get_approved_summary($author_id, $limit = 10) { return array(); }
			public function get_rejected_summary($author_id, $limit = 10) { return array(); }
			public function create_bulk($topics) { $this->created = $topics; return true; }
			public function get_latest_by_author($author_id, $limit) {
				$rows = array();
				foreach ($this->created as $i => $t) {
					$t['id'] = 900 + $i;
					$rows[]  = (object) $t;
				}
				return array_slice($rows, 0, $limit);
			}
		};
		$logs_repo = new class() {
			public function create($data) { return 1; }
		};
		$authors_repo = new class() {
			public function update_topic_generation_last_run($author_id, $ts) { return true; }
		};
		$feedback_repo = new class() {
			public function get_reason_category_statistics($author_id) { return array(); }
		};
		$dedup_service = new class() {
			public function evaluate_topics_for_duplicates($topics, $author_id) { return $topics; }
		};
		$embeddings_service = new class() {
			public function is_enabled() { return false; }
		};
		$prompt_builder = new class() {
			public function build($author, $approved, $rejected, $guidance) { return 'Topic prompt'; }
			public function get_sources_selection() { return array(); }
			public function record_sources_usage($selection) {}
		};

		$generator = new AIPS_Author_Topics_Generator(
			$ai,
			null,
			$topics_repo,
			$logs_repo,
			$embeddings_service,
			$feedback_repo,
			$prompt_builder,
			$dedup_service,
			$authors_repo
		);
		$generator->generate_topics_result($author, false);

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT creation_method, record_kind FROM {$wpdb->prefix}aips_history WHERE author_id = %d",
			4242
		));

		$this->assertNotEmpty($rows);
		foreach ($rows as $row) {
			$this->assertSame('topic_generation', $row->creation_method);
			$this->assertSame(AIPS_History_Record_Kind::EVENT, $row->record_kind);
		}
	}

	public function test_featured_image_stress_case_reports_failure_without_second_image_call() {
		$ai      = new AIPS_Test_Review_Fixes_AI_Service();
		$service = new AIPS_Stress_Test_Service($ai, new AIPS_Test_Review_Fixes_Logger());
		$result  = $service->run('generate_featured_image');

		$this->assertSame('failed', $result['status']);
		$this->assertLessThanOrEqual(1, $ai->image_calls, 'A failed component attempt must not trigger a second image generation call.');
	}

	public function test_consolidation_sanitize_drops_leading_h1_and_script_style_blocks() {
		$component = new AIPS_Post_Consolidation_Component();

		$html  = "```html\n<h1>Keep Post Title</h1>\n<p>Merged body.</p><style>.x{color:red}</style><script>alert(1)</script>\n```";
		$clean = $component->sanitize_value($html);

		$this->assertSame('<p>Merged body.</p>', $clean);
	}

	public function test_campaign_cluster_regenerate_calls_ai_with_directive() {
		$ai = new AIPS_Test_Review_Fixes_AI_Service(array(
			'sample_article_ideas' => array('Idea one', 'Idea two'),
			'risks_assumptions'    => array('Assumes beginners'),
		));
		$component = new AIPS_Campaign_Cluster_Component($ai);
		$context   = new AIPS_Campaign_Generation_Context(array('topic_niche' => 'Urban gardening'));

		$value = $component->regenerate(0, $context, null, 'Focus on renters');

		$this->assertSame(array('Idea one', 'Idea two'), $value['sample_article_ideas']);
		$this->assertCount(1, $ai->json_prompts);
		$this->assertStringContainsString('Urban gardening', $ai->json_prompts[0]);
		$this->assertStringContainsString('Focus on renters', $ai->json_prompts[0]);
		$this->assertTrue($component->apply(0, $value));
	}

	public function test_campaign_cluster_empty_response_is_an_error_and_does_not_apply() {
		$ai        = new AIPS_Test_Review_Fixes_AI_Service(array('sample_article_ideas' => array()));
		$component = new AIPS_Campaign_Cluster_Component($ai);
		$context   = new AIPS_Campaign_Generation_Context(array('topic_niche' => 'Urban gardening'));

		$this->assertWPError($component->regenerate(0, $context, null, ''));
		$this->assertFalse($component->apply(0, array('sample_article_ideas' => array(), 'risks_assumptions' => array())));
	}

	public function test_campaign_pillar_regenerate_includes_directive() {
		$ai        = new AIPS_Test_Review_Fixes_AI_Service(array('campaign_name' => 'Grow More', 'post_type' => 'post'));
		$component = new AIPS_Campaign_Pillar_Component($ai);
		$context   = new AIPS_Campaign_Generation_Context(array('topic_niche' => 'Urban gardening'));

		$value = $component->regenerate(0, $context, null, 'Make it playful');

		$this->assertSame('Grow More', $value['campaign_name']);
		$this->assertStringContainsString('Make it playful', $ai->json_prompts[0]);
	}

	public function test_registry_extension_hook_fires_once_after_defaults_and_overrides_stick() {
		$fired = 0;
		add_action('aips_register_generation_components', function ($registry) use (&$fired) {
			$fired++;
			$registry->register(new AIPS_Test_Review_Fixes_Topic_Title_Override());
		});

		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_post_components();
		$registry->register_default_topic_components();

		$this->assertInstanceOf(AIPS_Test_Review_Fixes_Topic_Title_Override::class, $registry->get('topic_title', 'author_topics'));

		// Defaults registered after the hook fired do not replace the extension component.
		$registry->register_default_topic_components();
		$this->assertInstanceOf(AIPS_Test_Review_Fixes_Topic_Title_Override::class, $registry->get('topic_title', 'author_topics'));

		$registry->get_by_strategy('posts');
		$registry->all();
		$this->assertSame(1, $fired);

		// A registry built by hand (as the regeneration coordinator does) also runs the hook.
		$coordinator          = new AIPS_Component_Regeneration_Coordinator();
		$coordinator_registry = $coordinator->get_registry();
		$this->assertNotNull($coordinator_registry->get('post_content'));
		$this->assertInstanceOf(AIPS_Test_Review_Fixes_Topic_Title_Override::class, $coordinator_registry->get('topic_title', 'author_topics'));
		$this->assertSame(2, $fired);
	}

	public function test_topic_component_prompt_builder_assembles_parts() {
		$context = new AIPS_Author_Topic_Generation_Context((object) array(
			'id'          => 1,
			'name'        => 'Ada',
			'field_niche' => 'Gardening',
		));
		$builder = new AIPS_Prompt_Builder_Topic_Component();

		$title = $builder->build_title($context, array('current_title' => 'Old Title', 'directive' => 'Shorter'));
		$this->assertStringContainsString('Current topic title to improve: Old Title', $title);
		$this->assertStringContainsString('Follow this directive: Shorter', $title);

		$prompt = $builder->build_editorial_prompt($context, array('topic_title' => 'Herbs', 'current_prompt' => 'Old prompt'));
		$this->assertStringContainsString("Topic title: Herbs\nCurrent prompt to refine: Old prompt", $prompt);
		$this->assertStringNotContainsString('Follow this directive', $prompt);

		$keywords = $builder->build_keywords($context);
		$this->assertStringContainsString('SEO keywords', $keywords);
		$this->assertStringNotContainsString('Topic title:', $keywords);

		// Components route through the builder.
		$ai        = new AIPS_Test_Review_Fixes_AI_Service();
		$component = new AIPS_Topic_Keywords_Component($ai);
		$component->generate($context, null, array('topic_title' => 'Balcony herbs'));
		$this->assertSame($builder->build_keywords($context, array('topic_title' => 'Balcony herbs')), $ai->text_prompts[0]);
	}

	public function test_universal_ai_edit_requires_nonce() {
		$_POST = array(
			'action'        => 'aips_universal_ai_edit',
			'strategy_key'  => 'posts',
			'component_key' => 'post_title',
			'target_id'     => 1,
		);

		$controller = new AIPS_AI_Edit_Controller();
		$response   = $this->run_ajax(array($controller, 'ajax_universal_ai_edit'));
		$this->assertFalse($response['success']);
	}

	public function test_get_editable_components_requires_nonce() {
		$_POST = array(
			'action'       => 'aips_get_editable_components',
			'strategy_key' => 'posts',
		);

		$controller = new AIPS_AI_Edit_Controller();
		$response   = $this->run_ajax(array($controller, 'ajax_get_editable_components'));
		$this->assertFalse($response['success']);
	}

	public function test_universal_ai_edit_denied_without_manage_options() {
		wp_set_current_user($this->factory->user->create(array('role' => 'subscriber')));

		$_POST = array(
			'action'        => 'aips_universal_ai_edit',
			'nonce'         => wp_create_nonce('aips_ajax_nonce'),
			'strategy_key'  => 'posts',
			'component_key' => 'post_title',
			'target_id'     => 1,
		);

		$controller = new AIPS_AI_Edit_Controller();
		$response   = $this->run_ajax(array($controller, 'ajax_universal_ai_edit'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	public function test_get_editable_components_denied_without_manage_options() {
		wp_set_current_user($this->factory->user->create(array('role' => 'subscriber')));

		$_POST = array(
			'action'       => 'aips_get_editable_components',
			'nonce'        => wp_create_nonce('aips_ajax_nonce'),
			'strategy_key' => 'posts',
		);

		$controller = new AIPS_AI_Edit_Controller();
		$response   = $this->run_ajax(array($controller, 'ajax_get_editable_components'));
		$this->assertFalse($response['success']);
		$this->assertSame('permission_denied', $response['data']['code']);
	}

	public function test_get_editable_components_lists_post_keys() {
		$_POST = array(
			'action'       => 'aips_get_editable_components',
			'nonce'        => wp_create_nonce('aips_ajax_nonce'),
			'strategy_key' => 'posts',
		);

		$controller = new AIPS_AI_Edit_Controller();
		$response   = $this->run_ajax(array($controller, 'ajax_get_editable_components'));
		$this->assertTrue($response['success']);
		$keys = array_column($response['data']['components'], 'key');
		$this->assertContains('post_title', $keys);
	}

	public function test_universal_ai_edit_rejects_unsupported_component() {
		$post_id = $this->factory->post->create(array('post_title' => 'Keep'));

		$_POST = array(
			'action'        => 'aips_universal_ai_edit',
			'nonce'         => wp_create_nonce('aips_ajax_nonce'),
			'strategy_key'  => 'posts',
			'component_key' => 'not_a_component',
			'target_id'     => $post_id,
		);

		$controller = new AIPS_AI_Edit_Controller();
		$response   = $this->run_ajax(array($controller, 'ajax_universal_ai_edit'));
		$this->assertFalse($response['success']);
		$this->assertSame('unsupported_component', $response['data']['code']);
		$this->assertSame('Keep', get_post_field('post_title', $post_id));
	}

	public function test_universal_ai_edit_apply_updates_post_title() {
		$post_id = $this->factory->post->create(array('post_title' => 'Old Title'));

		$mock_generator = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array('supports_conversation', 'set_conversation', 'set_history_container', 'generate_title_for_context'))
			->getMock();
		$mock_generator->method('supports_conversation')->willReturn(false);
		$mock_generator->method('generate_title_for_context')->willReturn('New Title From AI');

		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_post_components($mock_generator);
		$coordinator = new AIPS_Component_Regeneration_Coordinator($registry, $mock_generator);
		$container   = AIPS_Container::get_instance();
		$container->singleton(AIPS_Component_Regeneration_Coordinator::class, static function () use ($coordinator) {
			return $coordinator;
		});
		$singletons = new ReflectionProperty($container, 'singletons');
		$singletons->setAccessible(true);
		$cached = $singletons->getValue($container);
		$cached[AIPS_Component_Regeneration_Coordinator::class] = $coordinator;
		$singletons->setValue($container, $cached);

		$_POST = array(
			'action'        => 'aips_universal_ai_edit',
			'nonce'         => wp_create_nonce('aips_ajax_nonce'),
			'strategy_key'  => 'posts',
			'component_key' => 'post_title',
			'target_id'     => $post_id,
			'apply'         => '1',
		);

		$controller = new AIPS_AI_Edit_Controller();
		$response   = $this->run_ajax(array($controller, 'ajax_universal_ai_edit'));
		$this->assertTrue($response['success']);
		$this->assertTrue($response['data']['applied']);
		$this->assertSame('New Title From AI', get_post_field('post_title', $post_id));
	}

	public function test_legacy_generate_title_is_documented_deprecated() {
		$method = new ReflectionMethod(AIPS_Generator::class, 'generate_title');
		$this->assertStringContainsString('@deprecated 3.7.25', $method->getDocComment());
	}

	public function test_link_weave_service_uses_injected_component() {
		$component = $this->getMockBuilder(AIPS_Internal_Links_Post_Component::class)
			->disableOriginalConstructor()
			->onlyMethods(array('weave_links'))
			->getMock();
		$component->expects($this->once())
			->method('weave_links')
			->willReturn(array(
				'modified_html'  => '<p>Linked</p>',
				'inserted_links' => array(),
			));

		$ai      = new AIPS_Test_Review_Fixes_AI_Service();
		$service = new AIPS_Editor_Link_Weave_Service($ai, $component);
		$result  = $service->weave_internal_links(1, '<p>Body</p>');
		$this->assertSame('<p>Linked</p>', $result['modified_html']);
	}
}
