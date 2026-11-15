<?php
/**
 * Tests for AIPS_Sources_Enrichment_Middleware and
 * AIPS_Diversity_Injection_Middleware.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Test_Stub_Sources_Context implements AIPS_Generation_Context {
	private $include_sources;
	private $group_ids;

	public function __construct($include_sources, array $group_ids) {
		$this->include_sources = $include_sources;
		$this->group_ids = $group_ids;
	}

	public function get_type() { return 'stub'; }
	public function get_id() { return 1; }
	public function get_name() { return 'Stub'; }
	public function get_content_prompt() { return ''; }
	public function get_title_prompt() { return ''; }
	public function get_slug_prompt() { return null; }
	public function should_generate_slug() { return false; }
	public function get_image_prompt() { return null; }
	public function should_generate_featured_image() { return false; }
	public function get_featured_image_source() { return 'ai_prompt'; }
	public function get_unsplash_keywords() { return ''; }
	public function get_media_library_ids() { return ''; }
	public function has_content_images() { return false; }
	public function get_content_images() { return array(); }
	public function get_post_status() { return 'draft'; }
	public function get_post_type() { return 'post'; }
	public function get_post_category() { return ''; }
	public function get_post_tags() { return ''; }
	public function get_post_author() { return 1; }
	public function get_article_structure_id() { return null; }
	public function get_voice_id() { return null; }
	public function get_voice() { return null; }
	public function get_topic() { return 'Stub Topic'; }
	public function get_creation_method() { return null; }
	public function get_include_sources() { return $this->include_sources; }
	public function get_source_group_ids() { return $this->group_ids; }
	public function get_affiliate_links_enabled() { return false; }
	public function to_array() { return array(); }
}

class Test_AIPS_Sources_Diversity_Middlewares extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
	}

	// -- AIPS_Sources_Enrichment_Middleware ----------------------------------

	public function test_sources_middleware_noop_when_sources_disabled() {
		$middleware = new AIPS_Sources_Enrichment_Middleware();
		$document = $middleware->process(new AIPS_Prompt_Document(), new AIPS_Test_Stub_Sources_Context(false, array(901)));

		$this->assertSame(array(), $document->get_context_payload());
	}

	public function test_sources_middleware_noop_when_no_group_ids() {
		$middleware = new AIPS_Sources_Enrichment_Middleware();
		$document = $middleware->process(new AIPS_Prompt_Document(), new AIPS_Test_Stub_Sources_Context(true, array()));

		$this->assertSame(array(), $document->get_context_payload());
	}

	public function test_sources_middleware_injects_block_from_active_source() {
		$repo = new AIPS_Sources_Repository();
		$id = $repo->create(array('url' => 'https://middleware.sources.example.com', 'is_active' => 1));
		$repo->set_source_terms($id, array(902));

		$data = new AIPS_Sources_Data_Repository();
		$data->insert_if_new($id, array(
			'url'            => 'https://middleware.sources.example.com',
			'extracted_text' => 'Middleware reference content.',
			'fetch_status'   => 'success',
		));

		$prompt_builder = new AIPS_Prompt_Builder(null, null, $repo);
		$middleware = new AIPS_Sources_Enrichment_Middleware($prompt_builder);

		$document = $middleware->process(new AIPS_Prompt_Document(), new AIPS_Test_Stub_Sources_Context(true, array(902)));

		$payload = implode("\n", $document->get_context_payload());
		$this->assertStringContainsString('Middleware reference content.', $payload);

		$repo->delete($id);
	}

	// -- AIPS_Diversity_Injection_Middleware ---------------------------------

	public function test_diversity_middleware_adds_seed_line_always() {
		$context = new AIPS_Topic_Context((object) array('id' => 0), (object) array('id' => 0, 'topic_title' => 'Any Topic'));
		$middleware = new AIPS_Diversity_Injection_Middleware();

		$document = $middleware->process(new AIPS_Prompt_Document(), $context);

		$payload = implode("\n", $document->get_context_payload());
		$this->assertStringContainsString('Unique generation seed:', $payload);
	}

	public function test_diversity_middleware_noop_for_avoid_titles_when_no_history() {
		$context = new AIPS_Topic_Context((object) array('id' => 0), (object) array('id' => 0, 'topic_title' => 'Any Topic'));
		$middleware = new AIPS_Diversity_Injection_Middleware();

		$document = $middleware->process(new AIPS_Prompt_Document(), $context);

		$this->assertSame(array(), $document->get_constraints());
	}

	public function test_diversity_middleware_uses_injected_dependency() {
		$stub_injector = new class extends AIPS_Prompt_Builder_Diversity_Injector {
			public function __construct() {}
			public function build_avoid_titles_block($subject) { return 'Avoid: Stub Title'; }
			public function build_uniqueness_seed_line_block($subject = null) { return 'Stub seed line'; }
		};

		$middleware = new AIPS_Diversity_Injection_Middleware($stub_injector);
		$document = $middleware->process(new AIPS_Prompt_Document(), new AIPS_Test_Stub_Sources_Context(false, array()));

		$this->assertSame(array('Avoid: Stub Title'), $document->get_constraints());
		$this->assertSame(array('Stub seed line'), $document->get_context_payload());
	}
}
