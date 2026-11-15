<?php
/**
 * Tests for AIPS_Prompt_Builder::compile_content_prompt_via_composer()
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Builder_Compile_Content_Via_Composer extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
	}

	public function tearDown(): void {
		delete_option('aips_site_niche');
		delete_option('aips_site_content_guidelines');
		delete_option('aips_site_content_language');
		parent::tearDown();
	}

	public function test_includes_task_directive_with_topic_applied() {
		$template = (object) array(
			'id'                   => 1,
			'prompt_template'      => 'Deep dive into {{topic}}.',
			'article_structure_id' => null,
		);
		$context = new AIPS_Template_Context($template, null, 'Kubernetes');

		$builder = new AIPS_Prompt_Builder();
		$result = $builder->compile_content_prompt_via_composer($context);

		$this->assertStringContainsString('Kubernetes', $result);
	}

	public function test_includes_voice_instructions_via_middleware() {
		$template = (object) array(
			'id'                   => 1,
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);
		$voice = (object) array('id' => 5, 'content_instructions' => 'Be concise and technical.');
		$context = new AIPS_Template_Context($template, $voice, 'eBPF');

		$builder = new AIPS_Prompt_Builder();
		$result = $builder->compile_content_prompt_via_composer($context);

		$this->assertStringContainsString('Be concise and technical.', $result);
	}

	public function test_includes_site_context_and_editorial_guidelines() {
		update_option('aips_site_niche', 'Cloud infrastructure');
		update_option('aips_site_content_guidelines', 'Always include a code example.');

		$template = (object) array(
			'id'                   => 1,
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);
		$context = new AIPS_Template_Context($template, null, 'Terraform');

		$builder = new AIPS_Prompt_Builder();
		$result = $builder->compile_content_prompt_via_composer($context);

		$this->assertStringContainsString('Cloud infrastructure', $result);
		$this->assertStringContainsString('Always include a code example.', $result);
	}

	public function test_sets_sources_selection_on_post_content_builder_when_disabled() {
		$template = (object) array(
			'id'                   => 1,
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
			'include_sources'      => 0,
		);
		$context = new AIPS_Template_Context($template, null, 'Observability');

		$builder = new AIPS_Prompt_Builder();
		$builder->compile_content_prompt_via_composer($context);

		$this->assertSame(array(), $builder->get_post_content_builder()->get_sources_selection());
	}

	public function test_sets_sources_selection_on_post_content_builder_when_enabled() {
		$repo = new AIPS_Sources_Repository();
		$id = $repo->create(array('url' => 'https://composer-compile.example.com', 'is_active' => 1));
		$repo->set_source_terms($id, array(910));

		$data = new AIPS_Sources_Data_Repository();
		$data->insert_if_new($id, array(
			'url'            => 'https://composer-compile.example.com',
			'extracted_text' => 'Composer compile reference text.',
			'fetch_status'   => 'success',
		));

		$template = (object) array(
			'id'                   => 2,
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
			'include_sources'      => 1,
			'source_group_ids'     => '[910]',
		);
		$context = new AIPS_Template_Context($template, null, 'Networking');

		$builder = new AIPS_Prompt_Builder(null, null, $repo);
		$result = $builder->compile_content_prompt_via_composer($context);

		$this->assertStringContainsString('Composer compile reference text.', $result);
		$selection = $builder->get_post_content_builder()->get_sources_selection();
		$this->assertArrayHasKey('snapshots', $selection);
		$this->assertNotEmpty($selection['snapshots']);

		$repo->delete($id);
	}

	public function test_omits_aips_content_prompt_filter() {
		add_filter('aips_content_prompt', function ($prompt) {
			return $prompt . "\nFILTERED";
		});

		$template = (object) array(
			'id'                   => 1,
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);
		$context = new AIPS_Template_Context($template, null, 'Observability');

		$builder = new AIPS_Prompt_Builder();
		$result = $builder->compile_content_prompt_via_composer($context);

		remove_all_filters('aips_content_prompt');

		$this->assertStringNotContainsString('FILTERED', $result);
	}

	public function test_does_not_mutate_legacy_build_content_prompt_behavior() {
		add_filter('aips_content_prompt', function ($prompt) {
			return $prompt . "\nFILTERED";
		});

		$template = (object) array(
			'id'                   => 1,
			'prompt_template'      => 'Write about {{topic}}.',
			'article_structure_id' => null,
		);
		$context = new AIPS_Template_Context($template, null, 'Observability');

		$builder = new AIPS_Prompt_Builder();
		$legacy_result = $builder->build_content_prompt($context);

		remove_all_filters('aips_content_prompt');

		$this->assertStringContainsString('FILTERED', $legacy_result);
	}
}
