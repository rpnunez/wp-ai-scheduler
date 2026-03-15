<?php
/**
 * Tests for Component Regeneration Service
 *
 * @package AI_Post_Scheduler
 */

class Test_Component_Regeneration_Service extends WP_UnitTestCase {
	
	private $service;
	private $history_repository;
	private $template_repository;
	private $author_topics_repository;
	
	public function setUp(): void {
		parent::setUp();
		$this->service = new AIPS_Component_Regeneration_Service();
		$this->history_repository = new AIPS_History_Repository();
		$this->template_repository = new AIPS_Template_Repository();
		$this->author_topics_repository = new AIPS_Author_Topics_Repository();
	}
	
	public function tearDown(): void {
		parent::tearDown();
	}
	
	/**
	 * Test that the service can be instantiated
	 */
	public function test_service_instantiation() {
		$this->assertInstanceOf('AIPS_Component_Regeneration_Service', $this->service);
	}
	
	/**
	 * Test get_generation_context retrieves correct data
	 */
	public function test_get_generation_context() {
		// Create a template
		$template_id = $this->template_repository->create(array(
			'name' => 'Test Template',
			'system_prompt' => 'Test system prompt',
			'user_prompt' => 'Test user prompt',
			'is_active' => 1,
		));
		
		// Create a post
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Test Post',
		));
		
		// Create a history record
		$history_id = $this->history_repository->create(array(
			'template_id' => $template_id,
			'post_id' => $post_id,
			'status' => 'completed',
		));
		
		// Get generation context
		$context = $this->service->get_generation_context($history_id);
		
		$this->assertIsArray($context);
		$this->assertArrayHasKey('history_id', $context);
		$this->assertArrayHasKey('post_id', $context);
		$this->assertArrayHasKey('generation_context', $context);
		$this->assertArrayHasKey('context_type', $context);
		$this->assertArrayHasKey('context_name', $context);
		$this->assertEquals($history_id, $context['history_id']);
		$this->assertEquals($post_id, $context['post_id']);
		$this->assertEquals('template', $context['context_type']);
		$this->assertInstanceOf('AIPS_Template_Context', $context['generation_context']);
	}
	
	/**
	 * Test get_generation_context returns error for invalid history
	 */
	public function test_get_generation_context_invalid_history() {
		$context = $this->service->get_generation_context(99999);
		
		$this->assertInstanceOf('WP_Error', $context);
		$this->assertEquals('invalid_history', $context->get_error_code());
	}
	
	/**
	 * Test get_generation_context includes topic data when available
	 */
	public function test_get_generation_context_with_topic() {
		// Create a template
		$template_id = $this->template_repository->create(array(
			'name' => 'Test Template',
			'system_prompt' => 'Test system prompt',
			'user_prompt' => 'Test user prompt',
			'is_active' => 1,
		));
		
		// Create an author-topic
		$topic_id = $this->author_topics_repository->create(array(
			'author_id' => 1,
			'topic_title' => 'Test Topic',
			'topic_prompt' => 'Test description',
			'status' => 'approved',
		));
		
		// Create a post
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Test Post',
		));
		
		// Create a history record with topic
		$history_id = $this->history_repository->create(array(
			'template_id' => $template_id,
			'post_id' => $post_id,
			'topic_id' => $topic_id,
			'status' => 'completed',
		));
		
		// Get generation context
		$context = $this->service->get_generation_context($history_id);
		
		$this->assertArrayHasKey('generation_context', $context);
		$this->assertArrayHasKey('context_type', $context);
		$this->assertEquals('template', $context['context_type']);
		$this->assertInstanceOf('AIPS_Template_Context', $context['generation_context']);
		
		// Check that topic is included in the context
		$generation_context = $context['generation_context'];
		$this->assertEquals('Test Topic', $generation_context->get_topic());
	}
	
	/**
	 * Test regenerate_title requires generation context
	 */
	public function test_regenerate_title_requires_context() {
		$context = array();
		$result = $this->service->regenerate_title($context);
		
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('missing_context', $result->get_error_code());
	}
	
	/**
	 * Test regenerate_title with template context
	 */
	public function test_regenerate_title_with_template() {
		// Create a minimal template object
		$template = (object) array(
			'id' => 1,
			'name' => 'Test Template',
			'system_prompt' => 'You are a helpful assistant.',
			'user_prompt' => 'Write a title about: {topic}',
			'title_prompt' => 'Generate a title',
			'prompt_template' => 'Write about {topic}',
		);
		
		$generation_context = new AIPS_Template_Context($template, null, 'Test Topic');
		
		$context = array(
			'generation_context' => $generation_context,
		);
		
		// Note: This will actually try to call AI, so it might return an error
		// if AI Engine is not available. We just check it doesn't crash.
		$result = $this->service->regenerate_title($context);
		
		// Should be either a string or WP_Error, not null
		$this->assertTrue(is_string($result) || is_wp_error($result));
	}
	
	/**
	 * Test regenerate_excerpt requires generation context
	 */
	public function test_regenerate_excerpt_requires_context() {
		$context = array();
		$result = $this->service->regenerate_excerpt($context);
		
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('missing_context', $result->get_error_code());
	}
	
	/**
	 * Test regenerate_excerpt with template context
	 */
	public function test_regenerate_excerpt_with_template() {
		$template = (object) array(
			'id' => 1,
			'name' => 'Test Template',
			'system_prompt' => 'You are a helpful assistant.',
			'user_prompt' => 'Write about: {topic}',
			'excerpt_prompt' => 'Generate an excerpt',
			'prompt_template' => 'Write about {topic}',
		);
		
		$generation_context = new AIPS_Template_Context($template, null, 'Test Topic');
		
		$context = array(
			'generation_context' => $generation_context,
			'current_title' => 'Test Title',
		);
		
		$result = $this->service->regenerate_excerpt($context);
		
		// Should be either a string or WP_Error
		$this->assertTrue(is_string($result) || is_wp_error($result));
	}
	
	/**
	 * Test regenerate_content requires generation context
	 */
	public function test_regenerate_content_requires_context() {
		$context = array();
		$result = $this->service->regenerate_content($context);
		
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('missing_context', $result->get_error_code());
	}
	
	/**
	 * Test regenerate_content with template context
	 */
	public function test_regenerate_content_with_template() {
		$template = (object) array(
			'id' => 1,
			'name' => 'Test Template',
			'system_prompt' => 'You are a helpful assistant.',
			'user_prompt' => 'Write about: {topic}',
			'content_prompt' => 'Generate content',
			'prompt_template' => 'Write about {topic}',
		);
		
		$generation_context = new AIPS_Template_Context($template, null, 'Test Topic');
		
		$context = array(
			'generation_context' => $generation_context,
			'current_title' => 'Test Title',
		);
		
		$result = $this->service->regenerate_content($context);
		
		// Should be either a string or WP_Error
		$this->assertTrue(is_string($result) || is_wp_error($result));
	}
	
	/**
	 * Test regenerate_featured_image requires generation context
	 */
	public function test_regenerate_featured_image_requires_context() {
		$context = array();
		$result = $this->service->regenerate_featured_image($context);
		
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('missing_context', $result->get_error_code());
	}
	
	/**
	 * Test regenerate_featured_image requires post ID
	 */
	public function test_regenerate_featured_image_requires_post_id() {
		$template = (object) array(
			'id' => 1,
			'name' => 'Test Template',
			'prompt_template' => 'Write about {topic}',
		);
		
		$generation_context = new AIPS_Template_Context($template);
		
		$context = array(
			'generation_context' => $generation_context,
		);
		
		$result = $this->service->regenerate_featured_image($context);
		
		$this->assertInstanceOf('WP_Error', $result);
		$this->assertEquals('missing_post_id', $result->get_error_code());
	}
	
	/**
	 * Test regenerate_featured_image with template context and post ID
	 */
	public function test_regenerate_featured_image_with_template_and_post() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Test Post',
		));
		
		$template = (object) array(
			'id' => 1,
			'name' => 'Test Template',
			'system_prompt' => 'You are a helpful assistant.',
			'user_prompt' => 'Write about: {topic}',
			'image_prompt' => 'Generate an image',
			'prompt_template' => 'Write about {topic}',
		);
		
		$generation_context = new AIPS_Template_Context($template, null, 'Test Topic');
		
		$context = array(
			'generation_context' => $generation_context,
			'current_title' => 'Test Title',
			'post_id' => $post_id,
		);
		
		$result = $this->service->regenerate_featured_image($context);
		
		// Should be either an array with attachment_id and url, or WP_Error
		$this->assertTrue(
			(is_array($result) && isset($result['attachment_id']) && isset($result['url'])) ||
			is_wp_error($result)
		);
	}
	
	/**
	 * Test that service handles structured content properly
	 */
	public function test_regenerate_content_with_structure() {
		$template = (object) array(
			'id' => 1,
			'name' => 'Test Template',
			'system_prompt' => 'You are a helpful assistant.',
			'user_prompt' => 'Write about: {topic}',
			'prompt_template' => 'Write about {topic}',
			'article_structure_id' => 1,
		);
		
		$generation_context = new AIPS_Template_Context($template, null, 'Test Topic');
		
		$context = array(
			'generation_context' => $generation_context,
			'current_title' => 'Test Title',
		);
		
		$result = $this->service->regenerate_content($context);
		
		// Should be either a string or WP_Error
		$this->assertTrue(is_string($result) || is_wp_error($result));
	}

	/**
	 * Regression test: featured image regeneration should use the generator's
	 * resolved prompt output (template + AI variables already processed).
	 */
	public function test_regenerate_featured_image_uses_resolved_prompt_path() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Prompt Resolution Post',
			'post_content' => 'Original body content for regeneration context.',
		));

		$history_id = $this->history_repository->create(array(
			'post_id' => $post_id,
			'status' => 'completed',
		));

		$template = (object) array(
			'id' => 77,
			'name' => 'Prompt Resolution Template',
			'prompt_template' => 'Write about {{topic}}',
			'image_prompt' => 'Image for {{topic}} by {{Brand}} in {{year}}',
		);

		$generation_context = new AIPS_Template_Context($template, null, 'Security Automation');

		$generator_stub = new class {
			public $last_context = null;
			public $last_content = null;
			public $last_title = null;

			public function process_featured_image_prompt($context, $content = '', $title = '') {
				$this->last_context = $context;
				$this->last_content = $content;
				$this->last_title = $title;
				return 'Resolved featured image prompt for Security Automation by Nimbus in 2026';
			}
		};

		$image_service_stub = new class {
			public $received_prompt = null;
			public $received_title = null;

			public function generate_and_upload_featured_image($prompt, $title) {
				$this->received_prompt = $prompt;
				$this->received_title = $title;
				return 987;
			}
		};

		$this->set_private_property($this->service, 'generator', $generator_stub);
		$this->set_private_property($this->service, 'image_service', $image_service_stub);

		$result = $this->service->regenerate_featured_image(array(
			'generation_context' => $generation_context,
			'current_title' => 'Regenerated Title',
			'post_id' => $post_id,
			'history_id' => $history_id,
		));

		$this->assertIsArray($result);
		$this->assertArrayHasKey('attachment_id', $result);
		$this->assertSame(987, $result['attachment_id']);
		$this->assertSame(
			'Resolved featured image prompt for Security Automation by Nimbus in 2026',
			$image_service_stub->received_prompt
		);
		$this->assertSame('Regenerated Title', $image_service_stub->received_title);
		$this->assertSame('Original body content for regeneration context.', $generator_stub->last_content);
		$this->assertInstanceOf('AIPS_Template_Context', $generator_stub->last_context);
		$this->assertSame('Regenerated Title', $generator_stub->last_title);
	}

	/**
	 * Set a private property value on an object for test doubles.
	 *
	 * @param object $object Target object.
	 * @param string $property Property name.
	 * @param mixed  $value Value to assign.
	 * @return void
	 */
	private function set_private_property($object, $property, $value) {
		$reflection = new ReflectionClass($object);
		$property_ref = $reflection->getProperty($property);
		$property_ref->setAccessible(true);
		$property_ref->setValue($object, $value);
	}
}
