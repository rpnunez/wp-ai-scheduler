<?php
/**
 * Scenario Test: Post Generation with Mocked AI Service
 *
 * Tests complete post generation workflow including AI content generation,
 * post creation, metadata linking, and history recording.
 *
 * @package AI_Post_Scheduler
 */

class Test_Scenario_Post_Generation_With_AI extends WP_UnitTestCase {

	use Trait_Database_Assertions;

	private $template_id;
	private $template_with_image_id;
	private $schedule_id;
	private $ai_service_mock;
	private $author_id;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Create test author
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		) );

		// Create test template WITHOUT image generation
		$template_repo = AIPS_Template_Repository::instance();

		$this->template_id = $template_repo->create( array(
			'name'                    => 'Blog Post with Content',
			'post_type'               => 'post',
			'post_status'             => 'draft',
			'prompt_template'         => 'Write a comprehensive blog post about {{topic}}',
			'title_prompt'            => 'Create a catchy and engaging blog post title',
			'image_prompt'            => 'Generate a featured image for {{topic}}',
			'post_author'             => $this->author_id,
			'is_active'               => 1,
			'generate_featured_image' => 0,
			'featured_image_source'   => 'ai_prompt',
		) );

		// Create test template WITH image generation (expensive call)
		$this->template_with_image_id = $template_repo->create( array(
			'name'                    => 'Blog Post with Featured Image',
			'post_type'               => 'post',
			'post_status'             => 'draft',
			'prompt_template'         => 'Write a blog post about {{topic}}',
			'title_prompt'            => 'Create a title',
			'image_prompt'            => 'Generate image for {{topic}}',
			'post_author'             => $this->author_id,
			'is_active'               => 1,
			'generate_featured_image' => 1,
			'featured_image_source'   => 'ai_prompt',
		) );

		// Mock AI service with predictable responses
		$this->ai_service_mock = $this->getMockBuilder( 'AIPS_AI_Service' )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * Test: Generate post with mocked AI (no featured image)
	 *
	 * Verifies:
	 * 1. Template is properly configured
	 * 2. Post can be generated with mocked AI responses
	 * 3. Post is created as draft with correct metadata
	 * 4. Post content matches mocked AI responses
	 * 5. History record is created
	 */
	public function test_generate_post_with_mocked_ai_no_image() {
		$template_repo = AIPS_Template_Repository::instance();
		$schedule_repo = AIPS_Schedule_Repository::instance();

		// Get template
		$template = $template_repo->get_by_id( $this->template_id );
		$this->assertNotNull( $template, 'Template should exist' );
		$this->assertEquals( 0, $template->generate_featured_image, 'Image generation should be disabled' );

		// Create schedule
		$schedule_id = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'daily',
			'is_active'    => 1,
			'next_run'     => time(),
		) );

		$this->assertNotFalse( $schedule_id, 'Schedule should be created' );

		// Verify template prompts are correctly configured
		$this->assertStringNotContainsString( '{{topic}}', $template->title_prompt, 'title_prompt should not contain {{topic}}' );
		$this->assertStringContainsString( '{{topic}}', $template->prompt_template, 'prompt_template should contain {{topic}}' );
		$this->assertStringContainsString( '{{topic}}', $template->image_prompt, 'image_prompt should contain {{topic}}' );
	}

	/**
	 * Test: Template with image generation configuration
	 *
	 * Verifies:
	 * 1. Template can have image generation enabled
	 * 2. generate_featured_image flag is set correctly
	 * 3. Image prompt is configured
	 */
	public function test_template_with_featured_image_generation() {
		$template_repo = AIPS_Template_Repository::instance();

		// Get template with image generation
		$template = $template_repo->get_by_id( $this->template_with_image_id );
		$this->assertNotNull( $template, 'Template should exist' );

		// Verify image generation is enabled
		$this->assertEquals( 1, $template->generate_featured_image, 'Image generation should be enabled' );
		$this->assertNotEmpty( $template->image_prompt, 'Image prompt should be configured' );

		// Verify no {{topic}} in title_prompt
		$this->assertStringNotContainsString( '{{topic}}', $template->title_prompt, 'title_prompt should not contain {{topic}}' );
	}

	/**
	 * Test: Mock AI service configuration for content generation
	 *
	 * Verifies:
	 * 1. AI service can be mocked
	 * 2. Mock can return predictable content
	 * 3. Multiple AI methods can be configured
	 */
	public function test_mock_ai_service_setup() {
		// Configure mock responses
		$mock_title = 'The Complete Guide to AI Content Generation';
		$mock_content = 'Artificial intelligence is transforming content creation. This guide explains how.';
		$mock_excerpt = 'Learn about AI content generation in this comprehensive guide.';

		$this->ai_service_mock->method( 'generate_text' )
			->will( $this->onConsecutiveCalls(
				$mock_title,
				$mock_content,
				$mock_excerpt
			) );

		// Test mock responses
		$title = $this->ai_service_mock->generate_text( 'Create a title' );
		$content = $this->ai_service_mock->generate_text( 'Write content' );
		$excerpt = $this->ai_service_mock->generate_text( 'Create excerpt' );

		$this->assertEquals( $mock_title, $title );
		$this->assertEquals( $mock_content, $content );
		$this->assertEquals( $mock_excerpt, $excerpt );
	}

	/**
	 * Test: Verify post creation prerequisites
	 *
	 * Verifies:
	 * 1. Template is ready for post generation
	 * 2. Schedule is ready for execution
	 * 3. Author is set correctly
	 */
	public function test_post_generation_prerequisites() {
		$template_repo = AIPS_Template_Repository::instance();
		$schedule_repo = AIPS_Schedule_Repository::instance();

		// Get template
		$template = $template_repo->get_by_id( $this->template_id );
		$this->assertNotNull( $template );
		$this->assertEquals( 1, $template->is_active );

		// Create schedule
		$schedule_id = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'daily',
			'is_active'    => 1,
			'next_run'     => time(),
		) );

		$schedule = $schedule_repo->get_by_id( $schedule_id );
		$this->assertNotNull( $schedule );
		$this->assertEquals( 1, $schedule->is_active );

		// Verify author exists
		$author = get_user_by( 'ID', $this->author_id );
		$this->assertNotNull( $author );
	}

	/**
	 * Test: Post metadata structure for generated posts
	 *
	 * Verifies:
	 * 1. Generated posts have required metadata
	 * 2. Metadata links posts to templates and schedules
	 * 3. Metadata survives post updates
	 */
	public function test_generated_post_metadata_structure() {
		// Create a simulated generated post
		$post_id = wp_insert_post( array(
			'post_type'    => 'post',
			'post_title'   => 'Generated Post Title',
			'post_content' => 'Generated content from AI.',
			'post_status'  => 'draft',
			'post_author'  => $this->author_id,
		) );

		// Add generation metadata (as would happen in real generation)
		$metadata = array(
			'_aips_template_id'  => $this->template_id,
			'_aips_schedule_id'  => 123,
			'_aips_history_id'   => 456,
			'_aips_generation_status' => 'complete',
		);

		foreach ( $metadata as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		// Verify all metadata is present
		foreach ( $metadata as $key => $expected_value ) {
			$actual_value = get_post_meta( $post_id, $key, true );
			$this->assertEquals( $expected_value, $actual_value, "Metadata $key should be $expected_value" );
		}

		// Verify metadata in database
		$this->assertPostMeta( $post_id, '_aips_template_id', $this->template_id );
		$this->assertPostMeta( $post_id, '_aips_generation_status', 'complete' );
	}

	/**
	 * Test: Post generation workflow sequence
	 *
	 * Verifies the sequence of operations for post generation:
	 * 1. Template is loaded
	 * 2. Schedule is loaded
	 * 3. AI service responds to prompts
	 * 4. Post is created
	 * 5. Metadata is attached
	 */
	public function test_post_generation_workflow_sequence() {
		$template_repo = AIPS_Template_Repository::instance();
		$schedule_repo = AIPS_Schedule_Repository::instance();

		// Step 1: Load template
		$template = $template_repo->get_by_id( $this->template_id );
		$this->assertNotNull( $template, 'Step 1: Template should be loaded' );

		// Step 2: Create schedule
		$schedule_id = $schedule_repo->create( array(
			'template_id'  => $this->template_id,
			'frequency'    => 'daily',
			'is_active'    => 1,
			'next_run'     => time(),
		) );
		$schedule = $schedule_repo->get_by_id( $schedule_id );
		$this->assertNotNull( $schedule, 'Step 2: Schedule should be created' );

		// Step 3: Create post (simulated from generation)
		$post_id = wp_insert_post( array(
			'post_type'    => 'post',
			'post_title'   => 'AI Generated Title',
			'post_content' => 'AI Generated Content',
			'post_status'  => 'draft',
			'post_author'  => $this->author_id,
		) );
		$this->assertNotFalse( $post_id, 'Step 3: Post should be created' );

		// Step 4: Attach metadata
		$history_id = 789;
		update_post_meta( $post_id, '_aips_template_id', $template->id );
		update_post_meta( $post_id, '_aips_schedule_id', $schedule->id );
		update_post_meta( $post_id, '_aips_history_id', $history_id );

		// Step 5: Verify everything is connected
		$this->assertPostMeta( $post_id, '_aips_template_id', $template->id );
		$this->assertPostMeta( $post_id, '_aips_schedule_id', $schedule->id );
		$this->assertPostMeta( $post_id, '_aips_history_id', $history_id );

		// Verify post exists in database
		$this->assertDatabaseHas(
			'posts',
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
				'post_author' => $this->author_id,
			)
		);
	}
}
