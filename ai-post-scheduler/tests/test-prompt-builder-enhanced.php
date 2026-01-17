<?php
/**
 * Test case for AIPS_Prompt_Builder Enhanced Methods
 *
 * Tests the new methods for building separated instructions, context, and prompts
 * for enhanced AI Engine API usage.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

class Test_AIPS_Prompt_Builder_Enhanced extends WP_UnitTestCase {

    private $prompt_builder;
    private $template_processor;
    private $structure_manager;

    public function setUp(): void {
        parent::setUp();
        
        // Create mock template processor
        $this->template_processor = new class {
            public function process($template, $topic) {
                return str_replace('{{topic}}', $topic ?: '', $template);
            }
        };
        
        // Create mock structure manager
        $this->structure_manager = new class {
            public function build_prompt($structure_id, $topic) {
                return 'Structured prompt for ' . $topic;
            }
        };
        
        $this->prompt_builder = new AIPS_Prompt_Builder(
            $this->template_processor,
            $this->structure_manager
        );
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test build_content_options returns expected structure
     */
    public function test_build_content_options_returns_array_with_required_keys() {
        $template = (object) array(
            'id' => 1,
            'name' => 'Test Template',
            'prompt_template' => 'Write about {{topic}}',
        );
        
        $result = $this->prompt_builder->build_content_options($template, 'AI trends');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('prompt', $result);
        $this->assertArrayHasKey('instructions', $result);
        $this->assertArrayHasKey('context', $result);
    }

    /**
     * Test build_content_options includes prompt with topic
     */
    public function test_build_content_options_prompt_includes_topic() {
        $template = (object) array(
            'id' => 1,
            'name' => 'Test Template',
            'prompt_template' => 'Write about {{topic}}',
        );
        
        $result = $this->prompt_builder->build_content_options($template, 'AI trends');
        
        $this->assertStringContainsString('AI trends', $result['prompt']);
    }

    /**
     * Test build_content_options includes voice instructions when voice provided
     */
    public function test_build_content_options_includes_voice_instructions() {
        $template = (object) array(
            'id' => 1,
            'name' => 'Test Template',
            'prompt_template' => 'Write about {{topic}}',
        );
        
        $voice = (object) array(
            'name' => 'Professional Writer',
            'content_instructions' => 'Write in a professional tone',
        );
        
        $result = $this->prompt_builder->build_content_options($template, 'AI trends', $voice);
        
        $this->assertStringContainsString('Write in a professional tone', $result['instructions']);
        $this->assertStringContainsString('Professional Writer', $result['instructions']);
    }

    /**
     * Test build_content_options includes template context
     */
    public function test_build_content_options_includes_template_context() {
        $template = (object) array(
            'id' => 1,
            'name' => 'Tech Blog Template',
            'description' => 'For technology blog posts',
            'prompt_template' => 'Write about {{topic}}',
            'post_category' => 'Technology',
        );
        
        $result = $this->prompt_builder->build_content_options($template, 'AI trends');
        
        $this->assertStringContainsString('Tech Blog Template', $result['context']);
        $this->assertStringContainsString('For technology blog posts', $result['context']);
        $this->assertStringContainsString('AI trends', $result['context']);
    }

    /**
     * Test build_voice_instructions returns empty string when no voice
     */
    public function test_build_voice_instructions_returns_empty_without_voice() {
        $result = $this->prompt_builder->build_voice_instructions(null, 'test topic');
        
        $this->assertEquals('', $result);
    }

    /**
     * Test build_voice_instructions includes content_instructions
     */
    public function test_build_voice_instructions_includes_content_instructions() {
        $voice = (object) array(
            'name' => 'Casual Writer',
            'content_instructions' => 'Be casual and friendly',
        );
        
        $result = $this->prompt_builder->build_voice_instructions($voice, 'test topic');
        
        $this->assertStringContainsString('Be casual and friendly', $result);
    }

    /**
     * Test build_template_context includes topic
     */
    public function test_build_template_context_includes_topic() {
        $template = (object) array(
            'id' => 1,
            'name' => 'Test',
        );
        
        $result = $this->prompt_builder->build_template_context($template, 'Machine Learning');
        
        $this->assertStringContainsString('Machine Learning', $result);
    }

    /**
     * Test build_title_options returns expected structure
     */
    public function test_build_title_options_returns_required_keys() {
        $template = (object) array(
            'id' => 1,
            'name' => 'Test',
            'title_prompt' => 'Make it catchy',
        );
        
        $result = $this->prompt_builder->build_title_options($template, 'AI', null, 'Article content');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('prompt', $result);
        $this->assertArrayHasKey('instructions', $result);
        $this->assertArrayHasKey('context', $result);
    }

    /**
     * Test build_title_options uses voice title prompt as instructions
     */
    public function test_build_title_options_uses_voice_title_prompt() {
        $template = (object) array(
            'id' => 1,
            'title_prompt' => 'Template title instructions',
        );
        
        $voice = (object) array(
            'title_prompt' => 'Voice title style: be creative',
        );
        
        $result = $this->prompt_builder->build_title_options($template, 'AI', $voice, 'Article content');
        
        $this->assertStringContainsString('Voice title style', $result['instructions']);
    }

    /**
     * Test build_title_options includes content in context
     */
    public function test_build_title_options_includes_content_in_context() {
        $template = (object) array(
            'id' => 1,
        );
        
        $content = 'This is the generated article content about AI.';
        $result = $this->prompt_builder->build_title_options($template, 'AI', null, $content);
        
        $this->assertStringContainsString($content, $result['context']);
    }

    /**
     * Test build_excerpt_options returns expected structure
     */
    public function test_build_excerpt_options_returns_required_keys() {
        $result = $this->prompt_builder->build_excerpt_options(null, 'AI', 'My Title', 'Content');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('prompt', $result);
        $this->assertArrayHasKey('instructions', $result);
        $this->assertArrayHasKey('context', $result);
    }

    /**
     * Test build_excerpt_options uses voice excerpt instructions
     */
    public function test_build_excerpt_options_uses_voice_excerpt_instructions() {
        $voice = (object) array(
            'excerpt_instructions' => 'Keep it short and impactful',
        );
        
        $result = $this->prompt_builder->build_excerpt_options($voice, 'AI', 'Title', 'Content');
        
        $this->assertStringContainsString('Keep it short and impactful', $result['instructions']);
    }

    /**
     * Test build_excerpt_options includes title and content in context
     */
    public function test_build_excerpt_options_includes_title_and_content_in_context() {
        $title = 'Amazing AI Article';
        $content = 'This is the article body.';
        
        $result = $this->prompt_builder->build_excerpt_options(null, 'AI', $title, $content);
        
        $this->assertStringContainsString($title, $result['context']);
        $this->assertStringContainsString($content, $result['context']);
    }

    /**
     * Test original build_content_prompt still works (backward compatibility)
     */
    public function test_build_content_prompt_backward_compatible() {
        $template = (object) array(
            'id' => 1,
            'prompt_template' => 'Write about {{topic}}',
        );
        
        $result = $this->prompt_builder->build_content_prompt($template, 'backward compatibility');
        
        $this->assertIsString($result);
        $this->assertStringContainsString('backward compatibility', $result);
    }
}
