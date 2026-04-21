<?php
/**
 * Test Generation Context Architecture
 *
 * Tests the new context-based architecture for the Generator.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Generation_Context extends WP_UnitTestCase {

	/**
	 * Test that Template Context wraps template data correctly.
	 *
	 * @return void
	 */
	public function test_template_context_wraps_template() {
		$template = (object) array(
			'id' => 123,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate a title',
			'image_prompt' => 'Image of {{topic}}',
			'generate_featured_image' => 1,
			'featured_image_source' => 'ai_prompt',
			'post_status' => 'draft',
			'post_category' => 1,
			'post_tags' => 'test,example',
			'post_author' => 1,
			'article_structure_id' => null,
		);
		
		$context = new AIPS_Template_Context($template, null, 'Test Topic');
		
		$this->assertEquals('template', $context->get_type());
		$this->assertEquals(123, $context->get_id());
		$this->assertEquals('Test Template', $context->get_name());
		$this->assertEquals('Write about {{topic}}', $context->get_content_prompt());
		$this->assertEquals('Generate a title', $context->get_title_prompt());
		$this->assertEquals('Image of {{topic}}', $context->get_image_prompt());
		$this->assertTrue($context->should_generate_featured_image());
		$this->assertEquals('ai_prompt', $context->get_featured_image_source());
		$this->assertEquals('draft', $context->get_post_status());
		$this->assertEquals(1, $context->get_post_category());
		$this->assertEquals('test,example', $context->get_post_tags());
		$this->assertEquals(1, $context->get_post_author());
		$this->assertEquals('Test Topic', $context->get_topic());
		$this->assertNull($context->get_article_structure_id());
	}
	
	/**
	 * Test that Topic Context wraps author and topic correctly.
	 *
	 * @return void
	 */
	public function test_topic_context_wraps_author_and_topic() {
		$author = (object) array(
			'id' => 456,
			'name' => 'Test Author',
			'field_niche' => 'Software Development',
			'generate_featured_image' => 1,
			'featured_image_source' => 'ai_prompt',
			'post_status' => 'publish',
			'post_category' => 2,
			'post_tags' => 'coding,dev',
			'post_author' => 2,
			'article_structure_id' => 5,
		);
		
		$topic = (object) array(
			'id' => 789,
			'topic_title' => 'Best Practices for Clean Code',
			'author_id' => 456,
		);
		
		$expanded_context = 'Related topics: SOLID principles, Code review';
		
		$context = new AIPS_Topic_Context($author, $topic, $expanded_context);
		
		$this->assertEquals('topic', $context->get_type());
		$this->assertEquals(789, $context->get_id());
		$this->assertEquals('Test Author: Best Practices for Clean Code', $context->get_name());
		$this->assertStringContainsString('Write a comprehensive blog post about: Best Practices for Clean Code', $context->get_content_prompt());
		$this->assertStringContainsString('Field/Niche: Software Development', $context->get_content_prompt());
		$this->assertStringContainsString('Related topics: SOLID principles, Code review', $context->get_content_prompt());
		$this->assertEquals('Best Practices for Clean Code', $context->get_title_prompt());
		$this->assertEquals('Best Practices for Clean Code', $context->get_image_prompt());
		$this->assertTrue($context->should_generate_featured_image());
		$this->assertEquals('ai_prompt', $context->get_featured_image_source());
		$this->assertEquals('publish', $context->get_post_status());
		$this->assertEquals(2, $context->get_post_category());
		$this->assertEquals('coding,dev', $context->get_post_tags());
		$this->assertEquals(2, $context->get_post_author());
		$this->assertEquals('Best Practices for Clean Code', $context->get_topic());
		$this->assertEquals(5, $context->get_article_structure_id());
		$this->assertNull($context->get_voice_id());
	}
	
	/**
	 * Test that Generation Session accepts context objects.
	 *
	 * @return void
	 */
	public function test_generation_session_accepts_context() {
		$template = (object) array(
			'id' => 123,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate a title',
			'post_status' => 'draft',
			'post_category' => 1,
			'post_tags' => 'test',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => 0,
			'image_prompt' => '',
		);
		
		$context = new AIPS_Template_Context($template, null, 'Test Topic');
		$session = new AIPS_Generation_Session();
		
		$session->start($context);
		
		$context_data = $session->get_context();
		$this->assertNotNull($context_data);
		$this->assertEquals('template', $context_data['type']);
		$this->assertEquals(123, $context_data['id']);
		$this->assertEquals('Test Template', $context_data['name']);
	}
	
	/**
	 * Test backward compatibility: Generation Session still accepts template objects.
	 *
	 * @return void
	 */
	public function test_generation_session_backward_compatibility_with_templates() {
		$template = (object) array(
			'id' => 123,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate a title',
			'post_status' => 'draft',
			'post_category' => 1,
			'post_tags' => 'test',
			'post_author' => 1,
			'post_quantity' => 1,
			'generate_featured_image' => 0,
			'image_prompt' => '',
		);
		
		$session = new AIPS_Generation_Session();
		
		// Old way of calling start with template object
		$session->start($template, null);
		
		// Should still create context data
		$context_data = $session->get_context();
		$this->assertNotNull($context_data);
		$this->assertEquals('template', $context_data['type']);
		$this->assertEquals(123, $context_data['id']);
		$this->assertEquals('Test Template', $context_data['name']);
		
		// Should also still populate legacy template data
		$template_data = $session->get_template();
		$this->assertNotNull($template_data);
		$this->assertEquals(123, $template_data['id']);
	}
	
	/**
	 * Test that context data is properly serialized to array.
	 *
	 * @return void
	 */
	public function test_context_to_array_serialization() {
		$template = (object) array(
			'id' => 123,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Generate a title',
			'post_status' => 'draft',
			'post_category' => 1,
			'post_tags' => 'test',
			'post_author' => 1,
			'article_structure_id' => 5,
		);
		
		$context = new AIPS_Template_Context($template, null, 'Test Topic');
		$array = $context->to_array();
		
		$this->assertIsArray($array);
		$this->assertEquals('template', $array['type']);
		$this->assertEquals(123, $array['id']);
		$this->assertEquals('Test Template', $array['name']);
		$this->assertEquals('Write about {{topic}}', $array['content_prompt']);
		$this->assertEquals('Test Topic', $array['topic']);
		$this->assertEquals(5, $array['article_structure_id']);
	}
	
	/**
	 * Test that Template Context with voice prioritizes voice title prompt.
	 *
	 * @return void
	 */
	public function test_template_context_voice_title_prompt_precedence() {
		$template = (object) array(
			'id' => 123,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Template title prompt',
			'post_status' => 'draft',
			'post_category' => 1,
			'post_tags' => 'test',
			'post_author' => 1,
		);
		
		$voice = (object) array(
			'id' => 456,
			'name' => 'Test Voice',
			'title_prompt' => 'Voice title prompt',
			'content_instructions' => 'Voice instructions',
			'excerpt_instructions' => 'Voice excerpt',
		);
		
		$context = new AIPS_Template_Context($template, $voice, 'Test Topic');
		
		// Voice title prompt should take precedence
		$this->assertEquals('Voice title prompt', $context->get_title_prompt());
		$this->assertEquals(456, $context->get_voice_id());
	}

	/**
	 * Test that Template Context returns 'en' as the default language.
	 *
	 * @return void
	 */
	public function test_template_context_get_language_defaults_to_english() {
		$template = (object) array(
			'id' => 1,
			'name' => 'Template',
			'prompt_template' => 'Write',
			'post_status' => 'draft',
			'post_category' => 0,
			'post_tags' => '',
			'post_author' => 1,
		);

		$context = new AIPS_Template_Context($template);
		$this->assertSame('en', $context->get_language());
	}

	/**
	 * Test that Template Context returns the configured language code.
	 *
	 * @return void
	 */
	public function test_template_context_get_language_returns_configured_language() {
		$template = (object) array(
			'id' => 1,
			'name' => 'Template',
			'prompt_template' => 'Write',
			'post_status' => 'draft',
			'post_category' => 0,
			'post_tags' => '',
			'post_author' => 1,
			'language' => 'es',
		);

		$context = new AIPS_Template_Context($template);
		$this->assertSame('es', $context->get_language());
	}

	/**
	 * Test that Topic Context returns 'en' as the default language when author has no language.
	 *
	 * @return void
	 */
	public function test_topic_context_get_language_defaults_to_english() {
		$author = (object) array(
			'id' => 1,
			'name' => 'Author',
			'field_niche' => 'Tech',
			'post_status' => 'draft',
			'post_category' => 0,
			'post_tags' => '',
			'post_author' => 1,
		);
		$topic = (object) array(
			'id' => 10,
			'topic_title' => 'A Topic',
			'author_id' => 1,
		);

		$context = new AIPS_Topic_Context($author, $topic);
		$this->assertSame('en', $context->get_language());
	}

	/**
	 * Test that Topic Context returns the author's configured language.
	 *
	 * @return void
	 */
	public function test_topic_context_get_language_returns_author_language() {
		$author = (object) array(
			'id' => 1,
			'name' => 'Author',
			'field_niche' => 'Tech',
			'post_status' => 'draft',
			'post_category' => 0,
			'post_tags' => '',
			'post_author' => 1,
			'language' => 'fr',
		);
		$topic = (object) array(
			'id' => 10,
			'topic_title' => 'A Topic',
			'author_id' => 1,
		);

		$context = new AIPS_Topic_Context($author, $topic);
		$this->assertSame('fr', $context->get_language());
	}

	/**
	 * Test that get_language_name returns the full English name for known codes.
	 *
	 * @return void
	 */
	public function test_prompt_builder_get_language_name_known_codes() {
		$this->assertSame('English', AIPS_Prompt_Builder::get_language_name('en'));
		$this->assertSame('Spanish', AIPS_Prompt_Builder::get_language_name('es'));
		$this->assertSame('French', AIPS_Prompt_Builder::get_language_name('fr'));
		$this->assertSame('German', AIPS_Prompt_Builder::get_language_name('de'));
		$this->assertSame('Japanese', AIPS_Prompt_Builder::get_language_name('ja'));
	}

	/**
	 * Test that get_language_name falls back to the raw code for unknown codes.
	 *
	 * @return void
	 */
	public function test_prompt_builder_get_language_name_unknown_code() {
		$this->assertSame('xx', AIPS_Prompt_Builder::get_language_name('xx'));
	}

	/**
	 * Test that build_content_context injects a language instruction for non-English Template contexts.
	 *
	 * @return void
	 */
	public function test_build_content_context_injects_language_instruction_for_spanish_template() {
		$template = (object) array(
			'id' => 1,
			'name' => 'Template',
			'prompt_template' => 'Write',
			'post_status' => 'draft',
			'post_category' => 0,
			'post_tags' => '',
			'post_author' => 1,
			'language' => 'es',
		);

		$context = new AIPS_Template_Context($template);
		$builder = new AIPS_Prompt_Builder();
		$result  = $builder->build_content_context($context);

		$this->assertStringContainsString('Spanish', $result);
		$this->assertStringContainsString('You must write the entire response in Spanish', $result);
	}

	/**
	 * Test that build_content_context does NOT inject a language instruction for English.
	 *
	 * @return void
	 */
	public function test_build_content_context_no_language_instruction_for_english() {
		$template = (object) array(
			'id' => 1,
			'name' => 'Template',
			'prompt_template' => 'Write',
			'post_status' => 'draft',
			'post_category' => 0,
			'post_tags' => '',
			'post_author' => 1,
			'language' => 'en',
		);

		$context = new AIPS_Template_Context($template);
		$builder = new AIPS_Prompt_Builder();
		$result  = $builder->build_content_context($context);

		$this->assertStringNotContainsString('LANGUAGE REQUIREMENT', $result);
	}

	/**
	 * Test that build_content_context injects a language instruction for non-English Topic contexts.
	 *
	 * @return void
	 */
	public function test_build_content_context_injects_language_instruction_for_french_topic() {
		$author = (object) array(
			'id' => 1,
			'name' => 'Author',
			'field_niche' => 'Tech',
			'post_status' => 'draft',
			'post_category' => 0,
			'post_tags' => '',
			'post_author' => 1,
			'language' => 'fr',
		);
		$topic = (object) array(
			'id' => 10,
			'topic_title' => 'A Topic',
			'author_id' => 1,
		);

		$context = new AIPS_Topic_Context($author, $topic);
		$builder = new AIPS_Prompt_Builder();
		$result  = $builder->build_content_context($context);

		$this->assertStringContainsString('French', $result);
		$this->assertStringContainsString('You must write the entire response in French', $result);
	}
}
