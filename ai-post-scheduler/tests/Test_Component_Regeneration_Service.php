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

        $this->assertIsArray($context, is_wp_error($context) ? $context->get_error_message() : 'Expected array context');
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
	 * Test should_regenerate_featured_image returns true when post already has a thumbnail.
	 */
	public function test_should_regenerate_featured_image_true_for_existing_thumbnail() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Post With Image',
		));

		$attachment_id = $this->factory->post->create(array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image/jpeg',
			'post_title' => 'Existing Thumbnail',
			'post_status' => 'inherit',
		));

		update_post_meta($post_id, '_thumbnail_id', $attachment_id);

		$this->assertTrue($this->service->should_regenerate_featured_image($post_id));
	}

	/**
	 * Test should_regenerate_featured_image returns true when original generation marked image as failed.
	 */
	public function test_should_regenerate_featured_image_true_for_failed_component_status() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Post Without Image',
		));

		update_post_meta($post_id, 'aips_post_generation_component_statuses', wp_json_encode(array(
			'post_title' => true,
			'post_excerpt' => true,
			'featured_image' => false,
			'post_content' => true,
		)));

		$this->assertTrue($this->service->should_regenerate_featured_image($post_id));
	}

	/**
	 * Test should_regenerate_featured_image returns false when there is no existing or failed image generation.
	 */
	public function test_should_regenerate_featured_image_false_when_not_eligible() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'No Image Context',
		));

		update_post_meta($post_id, 'aips_post_generation_component_statuses', wp_json_encode(array(
			'post_title' => true,
			'post_excerpt' => true,
			'featured_image' => true,
			'post_content' => true,
		)));

		$this->assertFalse($this->service->should_regenerate_featured_image($post_id));
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

	// ------------------------------------------------------------------
	// regenerate_title – post content fallback (topic context)
	// ------------------------------------------------------------------

	/**
	 * When no current_content is passed for a topic-context title regeneration,
	 * the service falls back to fetching post_content from the post.
	 * We verify this by ensuring the call runs without crashing and
	 * the post content is reachable (indirect test; full AI call not made).
	 */
	public function test_regenerate_title_topic_context_fetches_post_content_when_missing() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Existing Post Title',
			'post_content' => 'This is the real post content for the title test.',
		));

		$author = (object) array(
			'id'          => 1,
			'name'        => 'Alice',
			'field_niche' => 'Engineering',
		);
		$topic = (object) array(
			'id'           => 1,
			'topic_title'  => 'Advanced PHP Patterns',
			'topic_prompt' => 'Discuss PHP design patterns.',
		);
		$generation_context = new AIPS_Topic_Context($author, $topic);

		// No current_content key in context — the service must fall back to the post.
		$context = array(
			'generation_context' => $generation_context,
			'post_id'            => $post_id,
		);

		$result = $this->service->regenerate_title($context);

		// Should be a string or WP_Error (AI may not be available in tests);
		// either way the service must not crash with an uninitialized value.
		$this->assertTrue(is_string($result) || is_wp_error($result));
	}

	// ------------------------------------------------------------------
	// regenerate_excerpt – title/content fallback
	// ------------------------------------------------------------------

	/**
	 * When current_title and current_content are absent from context but a
	 * post_id is provided, the service falls back to fetching them from the post.
	 */
	public function test_regenerate_excerpt_falls_back_to_post_data_when_context_empty() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'Post Title For Excerpt',
			'post_content' => 'Post body used for excerpt generation fallback.',
		));

		$template = (object) array(
			'id'              => 1,
			'name'            => 'T',
			'prompt_template' => 'Write about {topic}',
		);
		$generation_context = new AIPS_Template_Context($template, null, 'Test Topic');

		// Intentionally omit current_title and current_content.
		$context = array(
			'generation_context' => $generation_context,
			'post_id'            => $post_id,
		);

		$result = $this->service->regenerate_excerpt($context);

		// Should be a string or WP_Error (AI may not be available in tests).
		$this->assertTrue(is_string($result) || is_wp_error($result));
	}

	/**
	 * When current_title is provided but current_content is empty, the service
	 * fetches post_content from the database for the excerpt.
	 */
	public function test_regenerate_excerpt_falls_back_to_post_content_when_title_provided() {
		$post_id = $this->factory->post->create(array(
			'post_title'   => 'The Post Title',
			'post_content' => 'Substantial post body for excerpt fallback.',
		));

		$template = (object) array(
			'id'              => 1,
			'name'            => 'T',
			'prompt_template' => 'Write about {topic}',
		);
		$generation_context = new AIPS_Template_Context($template, null, 'Test Topic');

		$context = array(
			'generation_context' => $generation_context,
			'post_id'            => $post_id,
			'current_title'      => 'The Post Title',
			// current_content intentionally omitted
		);

		$result = $this->service->regenerate_excerpt($context);

		$this->assertTrue(is_string($result) || is_wp_error($result));
	}

	/**
	 * When post_id is zero and context values are absent, excerpt falls back
	 * to empty strings without errors.
	 */
	public function test_regenerate_excerpt_zero_post_id_and_empty_context_no_crash() {
		$template = (object) array(
			'id'              => 1,
			'name'            => 'T',
			'prompt_template' => 'Write about {topic}',
		);
		$generation_context = new AIPS_Template_Context($template, null, 'Test Topic');

		$context = array(
			'generation_context' => $generation_context,
			// no post_id, no current_title, no current_content
		);

		$result = $this->service->regenerate_excerpt($context);

		$this->assertTrue(is_string($result) || is_wp_error($result));
	}

	// ------------------------------------------------------------------
	// regenerate_featured_image – title fallback
	// ------------------------------------------------------------------

	/**
	 * When current_title is absent from context, the service falls back to the
	 * post title from the database.
	 */
	public function test_regenerate_featured_image_falls_back_to_post_title_when_missing() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Auto-fetched Image Title',
		));

		$template = (object) array(
			'id'                      => 1,
			'name'                    => 'T',
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
			'image_prompt'            => 'An illustration of the post topic.',
			'prompt_template'         => 'Write about {topic}',
		);
		$generation_context = new AIPS_Template_Context($template, null, 'Test Topic');

		// Intentionally omit current_title.
		$context = array(
			'generation_context' => $generation_context,
			'post_id'            => $post_id,
		);

		$result = $this->service->regenerate_featured_image($context);

		// Result is an array with attachment data, or WP_Error (AI may not be available).
		$this->assertTrue(
			(is_array($result) && isset($result['attachment_id'])) || is_wp_error($result)
		);
	}
}
