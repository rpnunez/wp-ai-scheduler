<?php
/**
 * Test Template Preview Functionality
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Templates_Controller_Preview extends WP_UnitTestCase {

	private $controller;
	private $admin_user;

	public function setUp(): void {
		parent::setUp();
		
		// Create admin user for permissions
		$this->admin_user = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_user);
		
		// Initialize controller
		$this->controller = new AIPS_Templates_Controller();
	}

	public function tearDown(): void {
		$_POST = array();
		parent::tearDown();
		wp_set_current_user(0);
	}

	/**
	 * Test that preview endpoint requires valid nonce
	 */
	public function test_preview_requires_nonce() {
		$_POST['prompt_template'] = 'Test content prompt';
		$_POST['nonce'] = 'invalid_nonce';
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_preview_template_prompts();
		} catch (WPAjaxDieStopException $e) {
			$this->assertTrue(true);
			ob_get_clean();
			return;
		} catch (WPAjaxDieContinueException $e) {
			$this->assertTrue(true);
			ob_get_clean();
			return;
		}
		ob_get_clean();

		$this->fail('Expected WPAjaxDieStopException or WPAjaxDieContinueException was not thrown.');
	}

	/**
	 * Test that preview requires content prompt
	 */
	public function test_preview_requires_content_prompt() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['prompt_template'] = '';
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_preview_template_prompts();
		} catch (WPAjaxDieStopException $e) {
		} catch (WPAjaxDieContinueException $e) {
		}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		$this->assertFalse($response['success']);
		$this->assertArrayHasKey('message', $response['data']);
	}

	/**
	 * Test successful preview generation
	 */
	public function test_preview_generates_prompts() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['prompt_template'] = 'Write a blog post about {{topic}}';
		$_POST['title_prompt'] = 'Create a catchy title';
		$_POST['voice_id'] = 0;
		$_POST['article_structure_id'] = 0;
		$_POST['image_prompt'] = '';
		$_POST['generate_featured_image'] = 0;
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_preview_template_prompts();
		} catch (WPAjaxDieStopException $e) {
		} catch (WPAjaxDieContinueException $e) {
		}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		
		$this->assertTrue($response['success'], 'Preview should succeed');
		$this->assertArrayHasKey('prompts', $response['data']);
		$this->assertArrayHasKey('metadata', $response['data']);
		
		// Check prompts structure
		$prompts = $response['data']['prompts'];
		$this->assertArrayHasKey('content', $prompts);
		$this->assertArrayHasKey('title', $prompts);
		$this->assertArrayHasKey('excerpt', $prompts);
		$this->assertArrayHasKey('image', $prompts);
		
		// Content prompt should have processed the topic variable
		$this->assertStringContainsString('Example Topic', $prompts['content']);
	}

	/**
	 * Test preview with voice
	 */
	public function test_preview_with_voice() {
		// Create a test voice
		$voice_service = new AIPS_Voices();
		$voice_id = $voice_service->save(array(
			'name' => 'Test Voice',
			'title_prompt' => 'Use a professional tone',
			'content_instructions' => 'Write in a formal style',
		));

		$GLOBALS['wpdb']->get_row_return_val = (object) array(
			'id' => $voice_id,
			'name' => 'Test Voice',
			'title_prompt' => 'Use a professional tone',
			'content_instructions' => 'Write in a formal style',
			'excerpt_instructions' => '',
			'is_active' => 1
		);

		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['prompt_template'] = 'Write about {{topic}}';
		$_POST['title_prompt'] = '';
		$_POST['voice_id'] = $voice_id;
		$_POST['article_structure_id'] = 0;
		$_POST['generate_featured_image'] = 0;
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_preview_template_prompts();
		} catch (WPAjaxDieStopException $e) {
		} catch (WPAjaxDieContinueException $e) {
		}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		
		$this->assertTrue($response['success']);
		$this->assertEquals('Test Voice', $response['data']['metadata']['voice']);
		
		// Voice instructions should be included in content prompt
		$this->assertStringContainsString('formal style', $response['data']['prompts']['content']);
		
		// Clean up
		$voice_service->delete($voice_id);
	}

	/**
	 * Test preview with image prompt
	 */
	public function test_preview_with_image() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['prompt_template'] = 'Write about {{topic}}';
		$_POST['title_prompt'] = '';
		$_POST['voice_id'] = 0;
		$_POST['article_structure_id'] = 0;
		$_POST['generate_featured_image'] = 1;
		$_POST['featured_image_source'] = 'ai_prompt';
		$_POST['image_prompt'] = 'A beautiful landscape with {{topic}}';
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_preview_template_prompts();
		} catch (WPAjaxDieStopException $e) {
		} catch (WPAjaxDieContinueException $e) {
		}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		
		$this->assertTrue($response['success']);
		$this->assertNotEmpty($response['data']['prompts']['image']);
		$this->assertStringContainsString('Example Topic', $response['data']['prompts']['image']);
	}

	/**
	 * Test preview permission check
	 */
	public function test_preview_permission_denied() {
		// Set current user to subscriber
		$subscriber = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber);

		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['prompt_template'] = 'Test content';
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_preview_template_prompts();
		} catch (WPAjaxDieStopException $e) {
		} catch (WPAjaxDieContinueException $e) {
		}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Permission denied', $response['data']['message']);
	}

	public function test_preview_validates_prompt_template_syntax() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['prompt_template'] = 'Write about {{topic'; // Invalid syntax
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_preview_template_prompts();
		} catch (WPAjaxDieStopException $e) {
		} catch (WPAjaxDieContinueException $e) {
		}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		$this->assertArrayHasKey('success', $response);
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('unclosed', $response['data']['message']);
	}

	public function test_preview_validates_image_prompt_syntax() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['prompt_template'] = 'Write about {{topic}}';
		$_POST['generate_featured_image'] = 1;
		$_POST['featured_image_source'] = 'ai_prompt';
		$_POST['image_prompt'] = 'Draw a {{topic missing closing braces'; // Invalid syntax
		$_REQUEST = $_POST;

		ob_start();
		try {
			$this->controller->ajax_preview_template_prompts();
		} catch (WPAjaxDieStopException $e) {
		} catch (WPAjaxDieContinueException $e) {
		}
		$output = ob_get_clean();

		$response = json_decode($output, true);
		$this->assertArrayHasKey('success', $response);
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('unclosed', $response['data']['message']);
	}
}
