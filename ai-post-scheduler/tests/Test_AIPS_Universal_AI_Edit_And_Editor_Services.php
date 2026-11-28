<?php
/**
 * Tests for Universal AI Edit, Editor Variants & Link Weave Migration.
 *
 * Covers:
 * - AIPS_Editor_Directive_Service::generate_component_variants() delegating to unified Post Components
 * - AIPS_Editor_Link_Weave_Service + AIPS_Internal_Links_Post_Component
 * - AIPS_Ajax_Registry::$map entries for Universal AI Edit endpoints
 * - Universal AI Edit coordination across posts, author_topics, and campaigns strategies
 *
 * @package AI_Post_Scheduler
 */

require_once __DIR__ . '/support/class-aips-generation-test-harness.php';

/**
 * Class Test_AIPS_Universal_AI_Edit_And_Editor_Services
 */
class Test_AIPS_Universal_AI_Edit_And_Editor_Services extends WP_UnitTestCase {

	/**
	 * AIPS_Editor_Directive_Service::generate_component_variants() delegates to Title, Slug, and Excerpt components.
	 */
	public function test_editor_directive_service_delegates_variants_to_post_components() {
		$post_id = self::factory()->post->create(array(
			'post_title'   => 'Original Post Title',
			'post_content' => '<p>Detailed WordPress engineering guide covering performance and architecture.</p>',
			'post_excerpt' => 'Original excerpt.',
			'post_name'    => 'original-post-title',
			'post_status'  => 'publish',
		));

		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('is_available')->willReturn(true);
		$ai_mock->method('generate_text')->willReturnOnConsecutiveCalls(
			'["Title Option One", "Title Option Two", "Title Option Three"]',
			'["WP Engineering Guide", "WordPress Architecture Tips", "Performance Best Practices"]',
			'["Excerpt one summary.", "Excerpt two summary.", "Excerpt three summary."]'
		);

		$service = new AIPS_Editor_Directive_Service($ai_mock);

		$titles = $service->generate_component_variants($post_id, 'title');
		$this->assertSame(array('Title Option One', 'Title Option Two', 'Title Option Three'), $titles);

		$slugs = $service->generate_component_variants($post_id, 'slug');
		$this->assertSame(array('wp-engineering-guide', 'wordpress-architecture-tips', 'performance-best-practices'), $slugs);

		$excerpts = $service->generate_component_variants($post_id, 'excerpt');
		$this->assertSame(array('Excerpt one summary.', 'Excerpt two summary.', 'Excerpt three summary.'), $excerpts);

		// Missing post returns post_not_found WP_Error.
		$missing = $service->generate_component_variants(999999, 'title');
		$this->assertWPError($missing);
		$this->assertSame('post_not_found', $missing->get_error_code());
	}

	/**
	 * AIPS_Editor_Link_Weave_Service delegates to AIPS_Internal_Links_Post_Component.
	 */
	public function test_editor_link_weave_service_uses_internal_links_post_component() {
		$current_id = self::factory()->post->create(array(
			'post_title'  => 'Current Guide',
			'post_status' => 'publish',
		));
		$candidate_id = self::factory()->post->create(array(
			'post_title'  => 'Caching Deep Dive',
			'post_status' => 'publish',
		));

		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('is_available')->willReturn(true);
		$ai_mock->expects($this->once())
			->method('generate_text')
			->willReturn(wp_json_encode(array(
				'modified_html'  => '<p>Read our <a href="' . get_permalink($candidate_id) . '">caching deep dive</a> for more.</p>',
				'inserted_links' => array(
					array(
						'url'    => get_permalink($candidate_id),
						'title'  => 'Caching Deep Dive',
						'anchor' => 'caching deep dive',
					),
				),
			)));

		$service = new AIPS_Editor_Link_Weave_Service($ai_mock);
		$result  = $service->weave_internal_links($current_id, '<p>Read our caching guide for more.</p>');

		$this->assertIsArray($result);
		$this->assertStringContainsString('<a href=', $result['modified_html']);
		$this->assertCount(1, $result['inserted_links']);

		// Empty content returns empty_content WP_Error.
		$empty = $service->weave_internal_links($current_id, '   ');
		$this->assertWPError($empty);
		$this->assertSame('empty_content', $empty->get_error_code());
	}

	/**
	 * AIPS_Ajax_Registry maps Universal AI Edit endpoints to AIPS_AI_Edit_Controller.
	 */
	public function test_ajax_registry_registers_universal_ai_edit_endpoints() {
		$this->assertTrue(AIPS_Ajax_Registry::has_action('aips_universal_ai_edit'));
		$this->assertSame('AIPS_AI_Edit_Controller', AIPS_Ajax_Registry::get_controller_for('aips_universal_ai_edit'));

		$this->assertTrue(AIPS_Ajax_Registry::has_action('aips_get_editable_components'));
		$this->assertSame('AIPS_AI_Edit_Controller', AIPS_Ajax_Registry::get_controller_for('aips_get_editable_components'));
	}

	/**
	 * Registry exposes AI-editable components across posts, author_topics, and campaigns strategies.
	 */
	public function test_universal_ai_edit_discovers_components_across_strategies() {
		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_post_components();
		$registry->register_default_topic_components();
		$registry->register_default_campaign_components();

		$this->assertTrue($registry->has('post_internal_links', 'posts'));

		$post_editable = array_map(static function ($c) {
			return $c->get_key();
		}, $registry->get_ai_editable('posts'));
		$this->assertContains('post_title', $post_editable);
		$this->assertContains('post_internal_links', $post_editable);

		$topic_editable = array_map(static function ($c) {
			return $c->get_key();
		}, $registry->get_ai_editable('author_topics'));
		$this->assertContains('topic_title', $topic_editable);
		$this->assertContains('topic_prompt', $topic_editable);
		$this->assertContains('topic_keywords', $topic_editable);
		$this->assertNotContains('topic_embedding', $topic_editable);

		$campaign_editable = array_map(static function ($c) {
			return $c->get_key();
		}, $registry->get_ai_editable('campaigns'));
		$this->assertContains('campaign_pillar', $campaign_editable);
		$this->assertContains('campaign_cluster', $campaign_editable);
	}

	/**
	 * AIPS_Editor_Directive_Service::apply_directive() delegates rewrites through AIPS_Content_Post_Component.
	 */
	public function test_editor_directive_apply_directive_delegates_to_content_component() {
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('is_available')->willReturn(true);
		$ai_mock->method('generate_text')->willReturnOnConsecutiveCalls(
			'"Sharper, punchier rewrite."',
			'["Variant A", "Variant B", "Variant C"]'
		);

		$service = new AIPS_Editor_Directive_Service($ai_mock);

		$single = $service->apply_directive('Original text.', AIPS_Editor_Directive_Service::PRESET_PUNCHY, '', 1);
		$this->assertSame(array('Sharper, punchier rewrite.'), $single);

		$multi = $service->apply_directive('Original text.', AIPS_Editor_Directive_Service::PRESET_PUNCHY, '', 3);
		$this->assertSame(array('Variant A', 'Variant B', 'Variant C'), $multi);
	}

	/**
	 * AIPS_Taxonomy_Generation_Strategy and AIPS_Planner_Topic_Strategy execute via AIPS_Generation_Pipeline.
	 */
	public function test_taxonomy_and_planner_strategies_execute_via_pipeline() {
		$post_id = self::factory()->post->create(array(
			'post_title'   => 'Scaling MySQL on WordPress',
			'post_excerpt' => 'Database indexing and query profiling tips.',
			'post_status'  => 'publish',
		));

		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('is_available')->willReturn(true);
		$ai_mock->method('generate_text')->willReturn("1. Performance\n2. Database Optimization");

		$tax_repo = new class() {
			private $items = array();
			public function insert($data) {
				$id               = count($this->items) + 1;
				$data['id']       = $id;
				$this->items[$id] = (object) $data;
				return $id;
			}
			public function get_by_id($id) {
				return $this->items[$id] ?? null;
			}
		};

		$tax_strategy = new AIPS_Taxonomy_Generation_Strategy(null, $ai_mock, $tax_repo);
		$tax_pipeline = new AIPS_Generation_Pipeline($tax_strategy, new AIPS_Generation_Component_Registry());
		$tax_result   = $tax_pipeline->execute(new AIPS_Taxonomy_Generation_Context('category', array($post_id)));

		$this->assertTrue($tax_result->is_success());
		$this->assertCount(2, $tax_result->get_data());
		$this->assertSame('Performance', $tax_result->get_data()[0]->name);

		$mock_generator = $this->getMockBuilder(AIPS_Generator::class)
			->disableOriginalConstructor()
			->onlyMethods(array('is_available', 'generate_content'))
			->getMock();
		$mock_generator->method('is_available')->willReturn(true);
		$mock_generator->method('generate_content')->willReturn('["Topic One", "Topic Two"]');

		$planner_strategy = new AIPS_Planner_Topic_Strategy(null, $mock_generator);
		$planner_pipeline = new AIPS_Generation_Pipeline($planner_strategy, new AIPS_Generation_Component_Registry());
		$planner_result   = $planner_pipeline->execute(new AIPS_Planner_Topic_Generation_Context('WordPress', 2));

		$this->assertTrue($planner_result->is_success());
		$this->assertSame(array('Topic One', 'Topic Two'), $planner_result->get_data());
	}

	/**
	 * AIPS_Component_Regeneration_Service::resolve_and_persist_component_overrides() persists and clears sticky overrides.
	 */
	public function test_component_regeneration_service_sticky_overrides() {
		$post_id = self::factory()->post->create(array('post_title' => 'Sticky Override Test'));
		$service = new AIPS_Component_Regeneration_Service();

		$res = $service->resolve_and_persist_component_overrides($post_id, 'title', 'gpt-4o', 0.6, true, true);
		$this->assertSame('gpt-4o', $res['model']);
		$this->assertSame(0.6, $res['temperature']);
		$this->assertSame('gpt-4o', $res['overrides']['title']['model']);

		$reloaded = $service->resolve_and_persist_component_overrides($post_id, 'title', '', null, false, false);
		$this->assertSame('gpt-4o', $reloaded['model']);
		$this->assertSame(0.6, $reloaded['temperature']);
	}

	/**
	 * Invoke an AJAX controller callback and decode its JSON output.
	 *
	 * @param callable $handler Controller method.
	 * @return array<string, mixed>
	 */
	private function dispatch_ajax(callable $handler): array {
		$_REQUEST = $_POST;
		ob_start();
		try {
			$handler();
		} catch (WPAjaxDieContinueException $e) {
			// Expected after wp_send_json_*.
		} catch (WPAjaxDieStopException $e) {
			// Expected after wp_die.
		}
		$raw     = (string) ob_get_clean();
		$decoded = json_decode($raw, true);
		$this->assertIsArray($decoded, 'Expected JSON AJAX payload, received: ' . $raw);

		return $decoded;
	}

	/**
	 * End-to-end ajax_universal_ai_edit on posts: apply="false" does not persist;
	 * apply="true" on post_content preserves Gutenberg blocks, strips XSS, reconciles
	 * incomplete state, and fires aips_post_components_updated with classic sanitized payload.
	 */
	public function test_universal_ai_edit_posts_preview_vs_apply_reconciles_and_fires_sanitized_hook() {
		wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));

		$post_id = self::factory()->post->create(array(
			'post_title'   => 'Complete Title',
			'post_excerpt' => 'Complete excerpt.',
			'post_content' => '',
			'post_status'  => 'draft',
		));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, 'true');
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_COMPONENT_STATUSES, wp_json_encode(array(
			'post_title'     => true,
			'post_excerpt'   => true,
			'post_content'   => false,
			'featured_image' => true,
		)));

		$ai_service = new AIPS_Generation_Test_AI_Service();
		$ai_service
			->queue_text('<p>Preview-only regenerated body.</p>')
			->queue_text("<!-- wp:paragraph -->\n<p>Applied safe block body.</p><script>alert(1)</script>\n<!-- /wp:paragraph -->");

		$container = AIPS_Container::get_instance();
		$container->singleton(AIPS_AI_Service_Interface::class, static function () use ($ai_service) {
			return $ai_service;
		});
		$generator = new AIPS_Generator(null, $ai_service);
		$registry  = (new AIPS_Generation_Component_Registry())->register_all_defaults($generator);
		$container->singleton(AIPS_Generation_Component_Registry::class, static function () use ($registry) {
			return $registry;
		});
		$container->singleton(AIPS_Component_Regeneration_Coordinator::class, static function () use ($registry, $generator) {
			return new AIPS_Component_Regeneration_Coordinator($registry, $generator);
		});

		$hook_events = array();
		$listener    = static function ($updated_post_id, $keys, $values) use (&$hook_events) {
			$hook_events[] = array(
				'post_id' => $updated_post_id,
				'keys'    => $keys,
				'values'  => $values,
			);
		};
		add_action('aips_post_components_updated', $listener, 10, 3);

		try {
			$controller = new AIPS_AI_Edit_Controller();

			// 1. String "false" must parse to boolean false and perform zero DB write.
			$_POST = array(
				'action'        => 'aips_universal_ai_edit',
				'nonce'         => wp_create_nonce('aips_ajax_nonce'),
				'strategy_key'  => 'posts',
				'component_key' => 'post_content',
				'target_id'     => $post_id,
				'directive'     => 'Add examples',
				'apply'         => 'false',
			);
			$preview_resp = $this->dispatch_ajax(array($controller, 'ajax_universal_ai_edit'));
			$this->assertTrue($preview_resp['success']);
			$this->assertFalse($preview_resp['data']['applied']);
			$this->assertSame('', (string) get_post_field('post_content', $post_id));
			$this->assertCount(0, $hook_events);

			// 2. apply="true" persists sanitized content, reconciles incomplete meta, and emits classic 'content' hook key.
			$_POST['apply'] = 'true';
			$apply_resp     = $this->dispatch_ajax(array($controller, 'ajax_universal_ai_edit'));
			$this->assertTrue($apply_resp['success']);
			$this->assertTrue($apply_resp['data']['applied']);

			$saved_content = (string) get_post_field('post_content', $post_id);
			$this->assertStringContainsString('<!-- wp:paragraph -->', $saved_content);
			$this->assertStringContainsString('<p>Applied safe block body.</p>', $saved_content);
			$this->assertNotContains('<script>', array($saved_content));
			$this->assertStringNotContainsString('<script>', $saved_content);
			$this->assertSame('false', get_post_meta($post_id, AIPS_Post_Manager::META_GENERATION_INCOMPLETE, true));

			$this->assertCount(1, $hook_events);
			$this->assertSame($post_id, $hook_events[0]['post_id']);
			$this->assertSame(array('content'), $hook_events[0]['keys']);
			$this->assertStringNotContainsString('<script>', $hook_events[0]['values']['content']);
			$this->assertStringContainsString('<!-- wp:paragraph -->', $hook_events[0]['values']['content']);
		} finally {
			remove_action('aips_post_components_updated', $listener, 10);
			$_POST    = array();
			$_REQUEST = array();
		}
	}

	/**
	 * End-to-end ajax_universal_ai_edit on author_topics rejects target_id=0 and
	 * persists regenerated topic_title and topic_keywords when apply="true".
	 */
	public function test_universal_ai_edit_author_topics_rejects_zero_target_and_applies_updates() {
		wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));

		$fixture  = AIPS_Generation_Test_Fixtures::create_persisted_author_and_topic();
		$topic_id = $fixture['topic_id'];

		$ai_service = new AIPS_Generation_Test_AI_Service();
		$ai_service
			->queue_text('"Zero-Downtime REST Schema Versioning in WordPress"')
			->queue_text('rest-api, versioning, backward-compatibility');

		$container = AIPS_Container::get_instance();
		$container->singleton(AIPS_AI_Service_Interface::class, static function () use ($ai_service) {
			return $ai_service;
		});
		$generator = new AIPS_Generator(null, $ai_service);
		$registry  = (new AIPS_Generation_Component_Registry())->register_all_defaults($generator);
		$container->singleton(AIPS_Generation_Component_Registry::class, static function () use ($registry) {
			return $registry;
		});
		$container->singleton(AIPS_Component_Regeneration_Coordinator::class, static function () use ($registry, $generator) {
			return new AIPS_Component_Regeneration_Coordinator($registry, $generator);
		});

		try {
			$controller = new AIPS_AI_Edit_Controller();

			// 1. target_id = 0 must be rejected with invalid_request.
			$_POST = array(
				'action'        => 'aips_universal_ai_edit',
				'nonce'         => wp_create_nonce('aips_ajax_nonce'),
				'strategy_key'  => 'author_topics',
				'component_key' => 'topic_title',
				'target_id'     => 0,
				'apply'         => 'true',
			);
			$zero_resp = $this->dispatch_ajax(array($controller, 'ajax_universal_ai_edit'));
			$this->assertFalse($zero_resp['success']);
			$this->assertSame('invalid_request', $zero_resp['data']['code']);

			// 2. Valid topic_id + topic_title updates DB row.
			$_POST['target_id'] = $topic_id;
			$_POST['directive'] = 'Focus on schema versioning';
			$title_resp         = $this->dispatch_ajax(array($controller, 'ajax_universal_ai_edit'));
			$this->assertTrue($title_resp['success']);
			$this->assertTrue($title_resp['data']['applied']);

			// 3. Valid topic_id + topic_keywords updates metadata['keywords'].
			$_POST['component_key'] = 'topic_keywords';
			$_POST['directive']     = 'Include backward compatibility';
			$kw_resp                = $this->dispatch_ajax(array($controller, 'ajax_universal_ai_edit'));
			$this->assertTrue($kw_resp['success']);
			$this->assertTrue($kw_resp['data']['applied']);

			$updated_topic = (new AIPS_Author_Topics_Repository())->get_by_id($topic_id);
			$this->assertSame('Zero-Downtime REST Schema Versioning in WordPress', $updated_topic->topic_title);
			$updated_meta = json_decode((string) $updated_topic->metadata, true);
			$this->assertSame(array('rest-api', 'versioning', 'backward-compatibility'), $updated_meta['keywords']);
		} finally {
			$_POST    = array();
			$_REQUEST = array();
		}
	}
}
