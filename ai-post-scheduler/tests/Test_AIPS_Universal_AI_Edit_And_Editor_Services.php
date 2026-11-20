<?php
/**
 * Tests for Universal AI Edit, Editor Variants & Link Weave Migration (Issue #2400 Phase 4).
 *
 * Covers:
 * - AIPS_Editor_Directive_Service::generate_component_variants() delegating to unified Post Components
 * - AIPS_Editor_Link_Weave_Service + AIPS_Internal_Links_Post_Component
 * - AIPS_Ajax_Registry::$map entries for Universal AI Edit endpoints
 * - Universal AI Edit coordination across posts, author_topics, and campaigns strategies
 *
 * @package AI_Post_Scheduler
 */

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
		$this->assertArrayHasKey('aips_universal_ai_edit', AIPS_Ajax_Registry::$map);
		$this->assertSame('AIPS_AI_Edit_Controller', AIPS_Ajax_Registry::$map['aips_universal_ai_edit']);

		$this->assertArrayHasKey('aips_get_editable_components', AIPS_Ajax_Registry::$map);
		$this->assertSame('AIPS_AI_Edit_Controller', AIPS_Ajax_Registry::$map['aips_get_editable_components']);
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
}
