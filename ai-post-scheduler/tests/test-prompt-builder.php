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
	 * Test dedicated post content builder uses the same prompt rules.
	 */
	public function test_post_content_builder_build_basic() {
		$template_processor = new AIPS_Template_Processor();
		$structure_manager = new AIPS_Article_Structure_Manager();
		$section_builder = new AIPS_Prompt_Builder_Article_Structure_Section($structure_manager, null, $template_processor);
		$builder = new AIPS_Prompt_Builder_Post_Content($template_processor, $section_builder);

		$template = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'article_structure_id' => null,
		);

		$result = $builder->build($template, 'AI Technology', null);

		$this->assertStringContainsString('Write about AI Technology', $result);
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
	 * Test dedicated post title builder uses the same template-only prompt rules.
	 */
	public function test_post_title_builder_build_template_only() {
		$template_processor = new AIPS_Template_Processor();
		$builder = new AIPS_Prompt_Builder_Post_Title($template_processor);

		$template = (object) array(
			'title_prompt' => 'Create an engaging title about {{topic}}',
		);

		$content = 'This is the article content about AI technology...';
		$result = $builder->build($template, 'AI', null, $content);

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
	 * Test dedicated post excerpt builder uses the same prompt rules.
	 */
	public function test_post_excerpt_builder_build_basic() {
		$template_processor = new AIPS_Template_Processor();
		$builder = new AIPS_Prompt_Builder_Post_Excerpt($template_processor);

		$title = 'Understanding AI Technology';
		$content = 'This article discusses various aspects of artificial intelligence...';

		$result = $builder->build($title, $content, null, null);

		$this->assertStringContainsString('Write an excerpt for an article', $result);
		$this->assertStringContainsString('ARTICLE TITLE:', $result);
		$this->assertStringContainsString('Understanding AI Technology', $result);
		$this->assertStringContainsString('ARTICLE BODY:', $result);
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
	 * Test base prompt builder delegates title prompt construction to the dedicated builder.
	 */
	public function test_get_post_title_builder_returns_specialized_builder() {
		$builder = new AIPS_Prompt_Builder();

		$this->assertInstanceOf('AIPS_Prompt_Builder_Post_Title', $builder->get_post_title_builder());
	}

	/**
	 * Test base prompt builder exposes the dedicated content builder.
	 */
	public function test_get_post_content_builder_returns_specialized_builder() {
		$builder = new AIPS_Prompt_Builder();

		$this->assertInstanceOf('AIPS_Prompt_Builder_Post_Content', $builder->get_post_content_builder());
	}

	/**
	 * Test base prompt builder exposes the dedicated excerpt builder.
	 */
	public function test_get_post_excerpt_builder_returns_specialized_builder() {
		$builder = new AIPS_Prompt_Builder();

		$this->assertInstanceOf('AIPS_Prompt_Builder_Post_Excerpt', $builder->get_post_excerpt_builder());
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
	 * Test dedicated featured image prompt builder processes prompt variables.
	 */
	public function test_post_featured_image_builder_build_basic() {
		$template_processor = new AIPS_Template_Processor();
		$builder = new AIPS_Prompt_Builder_Post_Featured_Image($template_processor);

		$template = (object) array(
			'image_prompt' => 'A photo of {{topic}}',
			'generate_featured_image' => 1,
			'featured_image_source' => 'ai_prompt',
		);

		$result = $builder->build($template, 'Example Topic');

		$this->assertSame('A photo of Example Topic', $result);
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
	 * Test base prompt builder exposes the dedicated featured image builder.
	 */
	public function test_get_post_featured_image_builder_returns_specialized_builder() {
		$builder = new AIPS_Prompt_Builder();

		$this->assertInstanceOf('AIPS_Prompt_Builder_Post_Featured_Image', $builder->get_post_featured_image_builder());
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

	/**
	 * Regression test: AI variable placeholders in title prompts must be substituted
	 * before the prompt is sent to the AI model.
	 *
	 * This test exercises AIPS_Generator::generate_title() end-to-end using a stub
	 * AI service that captures the exact prompt passed to generate_text(). When
	 * resolved AI variables are provided, the captured prompt must contain the
	 * resolved value and must not contain any raw {{VariableName}} placeholder.
	 *
	 * Before the fix, generate_title_from_context() never called
	 * process_with_ai_variables(), so the model received raw {{PHPTopic}} syntax and
	 * responded with only the variable value — a single word — instead of a full title.
	 *
	 * @see class-aips-generator.php generate_title_from_context()
	 */
	public function test_generator_substitutes_ai_variables_in_title_prompt() {
		// Stub AI service that captures every prompt sent to generate_text().
		$captured_prompts = array();
		$stub_ai_service  = new class( $captured_prompts ) implements AIPS_AI_Service_Interface {
			private $captured_prompts;

			public function __construct( &$captured_prompts ) {
				$this->captured_prompts = &$captured_prompts;
			}

			public function is_available() {
				return true;
			}

			public function generate_text( $prompt, $options = array() ) {
				$this->captured_prompts[] = $prompt;
				// Return a realistic title so the generator does not fall back.
				return 'PHP 9.4 Release Candidate: What Senior Developers Need to Know';
			}

			public function generate_json( $p, $o = array() ) {
				return array();
			}

			public function generate_image( $p, $o = array() ) {
				return '';
			}

			public function get_call_log() {
				return array();
			}
		};

		$template_processor = new AIPS_Template_Processor();

		$generator = new AIPS_Generator(
			null,
			$stub_ai_service,
			$template_processor
		);

		// Template whose title prompt contains an AI variable placeholder.
		$template = (object) array(
			'title_prompt'         => 'Write a compelling title about {{PHPTopic}} for senior developers.',
			'prompt_template'      => 'Write about {{topic}}',
			'article_structure_id' => null,
			'voice_id'             => null,
		);

		$content              = 'This article explores PHP 9.4 release candidate features in depth.';
		$resolved_ai_variables = array( 'PHPTopic' => 'PHP 9.4 Release Candidate' );

		$result = $generator->generate_title( $template, null, null, $content, array(), $resolved_ai_variables );

		// generate_title() must succeed (not a WP_Error).
		$this->assertNotInstanceOf( 'WP_Error', $result, 'generate_title() should not return WP_Error when AI service succeeds.' );

		// Exactly one AI call must have been made for the title prompt.
		$this->assertNotEmpty( $captured_prompts, 'Stub AI service was never called — something prevented the title generation path.' );

		$title_prompt = $captured_prompts[0];

		// The resolved value must be present in the prompt that reached the AI.
		$this->assertStringContainsString(
			'PHP 9.4 Release Candidate',
			$title_prompt,
			'Resolved AI variable value must appear in the prompt sent to the AI service.'
		);

		// The raw placeholder must NOT reach the AI — that is the bug this test guards.
		$this->assertStringNotContainsString(
			'{{PHPTopic}}',
			$title_prompt,
			'Raw AI variable placeholder must be substituted before the prompt is sent to the AI service.'
		);
	}
}
