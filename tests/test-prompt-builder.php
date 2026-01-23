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
		$this->assertStringContainsString('Output the response for use as a WordPress post', $result);
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
		$this->assertStringContainsString('between 40 and 60 characters', $result);
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
}
