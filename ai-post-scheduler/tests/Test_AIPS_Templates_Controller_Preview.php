<?php
/**
 * Test Template Preview Functionality
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Templates_Controller_Preview extends WP_Ajax_UnitTestCase {

	private $controller;
	private $admin_user;

	public function set_up(): void {
		parent::set_up();
		
		// Create admin user for permissions
		$this->admin_user = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_user);
		
		// Initialize controller
		$this->controller = new AIPS_Templates_Controller();
	}

	public function tear_down(): void {
		$_POST = array();
		$_REQUEST = array();
		wp_set_current_user(0);
		parent::tear_down();
	}

	private function capture_ajax_callable($callable) {
		$this->_last_response = '';

		ob_start();
		try {
			call_user_func($callable);
		} catch (WPAjaxDieContinueException $e) {
			// Expected for wp_send_json_* responses.
		} catch (WPAjaxDieStopException $e) {
			// Expected for wp_die()-style exits.
		}
		$output = ob_get_clean();

		if ('' !== trim($output) && '' === $this->_last_response) {
			$this->_last_response = $output;
		}

		if ('' === $this->_last_response) {
			return null;
		}

		return json_decode(strtok(trim($this->_last_response), "\r\n"), true);
	}

	/**
	 * Test that preview endpoint requires valid nonce
	 */
	public function test_preview_requires_nonce() {
		$_POST['prompt_template'] = 'Test content prompt';
		$_POST['nonce'] = 'invalid_nonce';
		$_REQUEST['nonce'] = $_POST['nonce'];

		$response = $this->capture_ajax_callable(array($this->controller, 'ajax_preview_template_prompts'));

		$this->assertFalse($response['success']);
		$this->assertSame('error', $response['data']['code']);
	}

	/**
	 * Test that preview requires content prompt
	 */
	public function test_preview_requires_content_prompt() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['prompt_template'] = '';

		$response = $this->capture_ajax_callable(array($this->controller, 'ajax_preview_template_prompts'));
		$this->assertFalse($response['success']);
		$this->assertArrayHasKey('message', $response['data']);
	}

	/**
	 * Test successful preview generation
	 */
	public function test_preview_generates_prompts() {
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['prompt_template'] = 'Write a blog post about {{topic}}';
		$_POST['title_prompt'] = 'Create a catchy title';
		$_POST['voice_id'] = 0;
		$_POST['article_structure_id'] = 0;
		$_POST['image_prompt'] = '';
		$_POST['generate_featured_image'] = 0;

		$response = $this->capture_ajax_callable(array($this->controller, 'ajax_preview_template_prompts'));
		
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

		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['prompt_template'] = 'Write about {{topic}}';
		$_POST['title_prompt'] = '';
		$_POST['voice_id'] = $voice_id;
		$_POST['article_structure_id'] = 0;
		$_POST['generate_featured_image'] = 0;

		$response = $this->capture_ajax_callable(array($this->controller, 'ajax_preview_template_prompts'));
		
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
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['prompt_template'] = 'Write about {{topic}}';
		$_POST['title_prompt'] = '';
		$_POST['voice_id'] = 0;
		$_POST['article_structure_id'] = 0;
		$_POST['generate_featured_image'] = 1;
		$_POST['featured_image_source'] = 'ai_prompt';
		$_POST['image_prompt'] = 'A beautiful landscape with {{topic}}';

		$response = $this->capture_ajax_callable(array($this->controller, 'ajax_preview_template_prompts'));
		
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
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['prompt_template'] = 'Test content';

		$response = $this->capture_ajax_callable(array($this->controller, 'ajax_preview_template_prompts'));
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Permission denied', $response['data']['message']);
	}
}
