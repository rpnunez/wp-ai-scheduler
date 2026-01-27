<?php
/**
 * Test Prompt Preview Service
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Preview_Service extends WP_UnitTestCase {

	private $service;

	public function setUp(): void {
		parent::setUp();
		$this->service = new AIPS_Prompt_Preview_Service();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test basic prompt preview generation
	 */
	public function test_preview_prompts_basic() {
		$template_data = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => '',
			'image_prompt' => '',
			'generate_featured_image' => 0,
		);

		$result = $this->service->preview_prompts($template_data);

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
	 * Test prompt preview with custom sample topic
	 */
	public function test_preview_prompts_custom_topic() {
		$template_data = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => '',
		);

		$result = $this->service->preview_prompts($template_data, 'Custom Topic');

		$this->assertEquals('Custom Topic', $result['metadata']['sample_topic']);
		$this->assertStringContainsString('Custom Topic', $result['prompts']['content']);
	}

	/**
	 * Test prompt preview with voice
	 */
	public function test_preview_prompts_with_voice() {
		// Create a test voice
		$voice_service = new AIPS_Voices();
		$voice_id = $voice_service->save(array(
			'name' => 'Test Voice',
			'title_prompt' => 'Use a professional tone',
			'content_instructions' => 'Write in a formal style',
		));

		$voice = $this->service->get_voice($voice_id);
		$this->assertNotNull($voice);
		$this->assertEquals('Test Voice', $voice->name);

		$template_data = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => '',
		);

		$result = $this->service->preview_prompts($template_data, null, $voice);

		$this->assertEquals('Test Voice', $result['metadata']['voice']);
		// Voice instructions should be included in content prompt
		$this->assertStringContainsString('formal style', $result['prompts']['content']);

		// Clean up
		$voice_service->delete($voice_id);
	}

	/**
	 * Test prompt preview with image enabled
	 */
	public function test_preview_prompts_with_image() {
		$template_data = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => '',
			'image_prompt' => 'A photo of {{topic}}',
			'generate_featured_image' => 1,
			'featured_image_source' => 'ai_prompt',
		);

		$result = $this->service->preview_prompts($template_data);

		$this->assertNotEmpty($result['prompts']['image']);
		$this->assertStringContainsString('Example Topic', $result['prompts']['image']);
	}

	/**
	 * Test get_voice with invalid ID
	 */
	public function test_get_voice_invalid_id() {
		$voice = $this->service->get_voice(0);
		$this->assertNull($voice);

		$voice = $this->service->get_voice(-1);
		$this->assertNull($voice);
	}

	/**
	 * Test prompt preview ensures consistency with actual generation
	 */
	public function test_preview_uses_same_prompt_builder() {
		$template_data = (object) array(
			'prompt_template' => 'Write about {{topic}}',
			'title_prompt' => 'Create title for {{topic}}',
		);

		// Preview should use the same prompt builder logic
		$result = $this->service->preview_prompts($template_data);

		// Both content and title should be processed
		$this->assertNotEmpty($result['prompts']['content']);
		$this->assertNotEmpty($result['prompts']['title']);
		$this->assertStringContainsString('Example Topic', $result['prompts']['content']);
	}
}
