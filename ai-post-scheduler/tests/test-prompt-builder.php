<?php
/**
 * Test AIPS_Prompt_Builder class
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_Prompt_Builder extends WP_UnitTestCase {

	/**
	 * Test build_content_prompt with basic template.
	 */
	public function test_build_content_prompt_basic() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$template = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'article_structure_id' => null,
		);

		$result = $builder->build_content_prompt($template, 'AI Technology', null);

		$this->assertStringContainsString('Write about AI Technology', $result);
		// Output instructions are now moved to build_content_context
		// $this->assertStringContainsString('Output the response for use as a WordPress post', $result);
	}

	/**
	 * Test build_content_prompt with voice instructions.
	 */
	public function test_build_content_prompt_with_voice() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$template = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'article_structure_id' => null,
		);

		$voice = (object) array(
			'content_instructions' => 'Use a professional tone when discussing {{topic}}',
		);

		$result = $builder->build_content_prompt($template, 'Machine Learning', $voice);

		$this->assertStringContainsString('Use a professional tone when discussing Machine Learning', $result);
		$this->assertStringContainsString('Write about Machine Learning', $result);
	}

	/**
	 * Test build_title_prompt with template only.
	 */
	public function test_build_title_prompt_template_only() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$template = (object) array(
			'title_prompt' => 'Create an engaging title about {{topic}}',
		);

		$content = 'This is the article content about AI technology...';
		$result = $builder->build_title_prompt($template, 'AI', null, $content);

		$this->assertStringContainsString('Generate a title for a blog post', $result);
		$this->assertStringContainsString('Create an engaging title about AI', $result);
		$this->assertStringContainsString('This is the article content about AI technology', $result);
	}

	/**
	 * Test build_title_prompt with voice override.
	 */
	public function test_build_title_prompt_voice_override() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$template = (object) array(
			'title_prompt' => 'Create a template title about {{topic}}',
		);

		$voice = (object) array(
			'title_prompt' => 'Create a voice-specific title for {{topic}}',
		);

		$content = 'Article content here...';
		$result = $builder->build_title_prompt($template, 'Testing', $voice, $content);

		// Voice title prompt should take precedence
		$this->assertStringContainsString('Create a voice-specific title for Testing', $result);
		$this->assertStringNotContainsString('Create a template title', $result);
	}

	/**
	 * Test build_title_prompt without instructions.
	 */
	public function test_build_title_prompt_no_instructions() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$template = (object) array(
			'title_prompt' => '',
		);

		$content = 'Article content without specific title instructions...';
		$result = $builder->build_title_prompt($template, 'Topic', null, $content);

		$this->assertStringContainsString('Generate a title for a blog post', $result);
		$this->assertStringContainsString('Article content without specific title instructions', $result);
	}

	/**
	 * Test build_excerpt_prompt with basic inputs.
	 */
	public function test_build_excerpt_prompt_basic() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$title = 'Understanding AI Technology';
		$content = 'This article discusses various aspects of artificial intelligence...';
		
		$result = $builder->build_excerpt_prompt($title, $content, null, null);

		$this->assertStringContainsString('Write an excerpt for an article', $result);
		$this->assertStringContainsString('between 40 and 60 words', $result);
		$this->assertStringContainsString('ARTICLE TITLE:', $result);
		$this->assertStringContainsString('Understanding AI Technology', $result);
		$this->assertStringContainsString('ARTICLE BODY:', $result);
		$this->assertStringContainsString('artificial intelligence', $result);
	}

	/**
	 * Test build_excerpt_prompt with voice instructions.
	 */
	public function test_build_excerpt_prompt_with_voice() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$voice = (object) array(
			'excerpt_instructions' => 'Write in a conversational style about {{topic}}',
		);

		$title = 'Machine Learning Basics';
		$content = 'Content about machine learning...';
		
		$result = $builder->build_excerpt_prompt($title, $content, $voice, 'ML');

		$this->assertStringContainsString('Write in a conversational style about ML', $result);
		$this->assertStringContainsString('Machine Learning Basics', $result);
	}

	/**
	 * Test build_excerpt_instructions (legacy method).
	 */
	public function test_build_excerpt_instructions_with_voice() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$voice = (object) array(
			'excerpt_instructions' => 'Use simple language for {{topic}}',
		);

		$result = $builder->build_excerpt_instructions($voice, 'Testing');

		$this->assertEquals('Use simple language for Testing', $result);
	}

	/**
	 * Test build_excerpt_instructions returns null when no voice.
	 */
	public function test_build_excerpt_instructions_no_voice() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$result = $builder->build_excerpt_instructions(null, 'Testing');

		$this->assertNull($result);
	}

	/**
	 * Test build_excerpt_instructions returns null when voice has no instructions.
	 */
	public function test_build_excerpt_instructions_empty_voice() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$voice = (object) array(
			'excerpt_instructions' => '',
		);

		$result = $builder->build_excerpt_instructions($voice, 'Testing');

		$this->assertNull($result);
	}

	/**
	 * Test title prompt with filter hook.
	 */
	public function test_build_title_prompt_with_filter() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		// Add filter to modify title prompt
		add_filter('aips_title_prompt', function($prompt) {
			return $prompt . "\n\nADDITIONAL INSTRUCTION";
		});

		$template = (object) array(
			'title_prompt' => 'Create title',
		);

		$content = 'Content';
		$result = $builder->build_title_prompt($template, 'Topic', null, $content);

		$this->assertStringContainsString('ADDITIONAL INSTRUCTION', $result);

		// Clean up filter
		remove_all_filters('aips_title_prompt');
	}

	/**
	 * Test excerpt prompt with filter hook.
	 */
	public function test_build_excerpt_prompt_with_filter() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		// Add filter to modify excerpt prompt
		add_filter('aips_excerpt_prompt', function($prompt) {
			return $prompt . "\n\nCUSTOM FILTER APPLIED";
		});

		$title = 'Test Title';
		$content = 'Test content';
		
		$result = $builder->build_excerpt_prompt($title, $content, null, null);

		$this->assertStringContainsString('CUSTOM FILTER APPLIED', $result);

		// Clean up filter
		remove_all_filters('aips_excerpt_prompt');
	}

	/**
	 * Test content prompt with filter hook.
	 */
	public function test_build_content_prompt_with_filter() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		// Add filter to modify content prompt
		add_filter('aips_content_prompt', function($prompt) {
			return $prompt . "\n\nFILTERED CONTENT";
		});

		$template = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'article_structure_id' => null,
		);

		$result = $builder->build_content_prompt($template, 'Testing', null);

		$this->assertStringContainsString('FILTERED CONTENT', $result);

		// Clean up filter
		remove_all_filters('aips_content_prompt');
	}

	/**
	 * Test title prompt handles empty content gracefully.
	 */
	public function test_build_title_prompt_empty_content() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$template = (object) array(
			'title_prompt' => 'Create title',
		);

		$result = $builder->build_title_prompt($template, 'Topic', null, '');

		$this->assertStringContainsString('Generate a title for a blog post', $result);
		$this->assertStringContainsString('Here is the content:', $result);
	}

	/**
	 * Test excerpt prompt handles empty content gracefully.
	 */
	public function test_build_excerpt_prompt_empty_content() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$builder = new AIPS_Prompt_Builder($template_processor, $structure_manager);

		$title = 'Test Title';
		
		$result = $builder->build_excerpt_prompt($title, '', null, null);

		$this->assertStringContainsString('ARTICLE TITLE:', $result);
		$this->assertStringContainsString('ARTICLE BODY:', $result);
		$this->assertStringContainsString('Test Title', $result);
	}

	/**
	 * Test build_prompts method generates all prompts.
	 */
	public function test_build_prompts_basic() {
		$builder = new AIPS_Prompt_Builder();

		$template_data = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => '',
			'image_prompt' => '',
			'generate_featured_image' => 0,
		);

		$result = $builder->build_prompts($template_data);

		$this->assertArrayHasKey('prompts', $result);
		$this->assertArrayHasKey('metadata', $result);
		$this->assertArrayHasKey('content', $result['prompts']);
		$this->assertArrayHasKey('title', $result['prompts']);
		$this->assertArrayHasKey('excerpt', $result['prompts']);
		$this->assertArrayHasKey('image', $result['prompts']);
		
		// Content prompt should include the sample topic
		$this->assertStringContainsString('Example Topic', $result['prompts']['content']);
	}

	/**
	 * Test build_prompts with custom sample topic.
	 */
	public function test_build_prompts_custom_topic() {
		$builder = new AIPS_Prompt_Builder();

		$template_data = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => '',
		);

		$result = $builder->build_prompts($template_data, 'Custom Topic');

		$this->assertEquals('Custom Topic', $result['metadata']['sample_topic']);
		$this->assertStringContainsString('Custom Topic', $result['prompts']['content']);
	}

	/**
	 * Test build_prompts with voice.
	 */
	public function test_build_prompts_with_voice() {
		$builder = new AIPS_Prompt_Builder();

		$voice = (object) array(
			'name' => 'Test Voice',
			'content_instructions' => 'Write in a formal style',
		);

		$template_data = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => '',
		);

		$result = $builder->build_prompts($template_data, null, $voice);

		$this->assertEquals('Test Voice', $result['metadata']['voice']);
		// Voice instructions should be included in content prompt
		$this->assertStringContainsString('formal style', $result['prompts']['content']);
	}

	/**
	 * Test build_prompts with image enabled.
	 */
	public function test_build_prompts_with_image() {
		$builder = new AIPS_Prompt_Builder();

		$template_data = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => '',
			'image_prompt' => 'A photo of {{topic}}',
			'generate_featured_image' => 1,
			'featured_image_source' => 'ai_prompt',
		);

		$result = $builder->build_prompts($template_data);

		$this->assertNotEmpty($result['prompts']['image']);
		$this->assertStringContainsString('Example Topic', $result['prompts']['image']);
	}

	/**
	 * Test get_voice with invalid ID.
	 */
	public function test_get_voice_invalid_id() {
		$builder = new AIPS_Prompt_Builder();

		$voice = $builder->get_voice(0);
		$this->assertNull($voice);

		$voice = $builder->get_voice(-1);
		$this->assertNull($voice);
	}

	/**
	 * Test get_voice with valid ID.
	 */
	public function test_get_voice_valid_id() {
		$builder = new AIPS_Prompt_Builder();

		// Create a test voice
		$voice_service = new AIPS_Voices();
		$voice_id = $voice_service->save(array(
			'name' => 'Test Voice',
			'title_prompt' => 'Test title prompt',
			'content_instructions' => 'Test instructions',
		));

		$voice = $builder->get_voice($voice_id);
		$this->assertNotNull($voice);
		$this->assertEquals('Test Voice', $voice->name);

		// Clean up
		$voice_service->delete($voice_id);
	}
}
