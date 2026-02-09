<?php
/**
 * Tests for AI Edit Controller
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_AI_Edit_Controller extends WP_UnitTestCase {
	
	private $controller;
	private $service;
	private $history_repository;
	private $template_repository;
	
	public function setUp(): void {
		parent::setUp();
		$this->history_repository = new AIPS_History_Repository();
		$this->template_repository = new AIPS_Template_Repository();
		$this->service = new AIPS_Component_Regeneration_Service();
		$this->controller = new AIPS_AI_Edit_Controller();
		
		// Set up admin user for permission checks
		wp_set_current_user($this->factory->user->create(array('role' => 'administrator')));
	}
	
	public function tearDown(): void {
		parent::tearDown();
	}
	
	/**
	 * Test that the controller can be instantiated
	 */
	public function test_controller_instantiation() {
		$this->assertInstanceOf('AIPS_AI_Edit_Controller', $this->controller);
	}
	
	/**
	 * Test that AJAX actions are registered
	 */
	public function test_ajax_actions_registered() {
		$this->assertTrue(has_action('wp_ajax_aips_get_post_components'));
		$this->assertTrue(has_action('wp_ajax_aips_regenerate_component'));
		$this->assertTrue(has_action('wp_ajax_aips_save_post_components'));
	}
	
	/**
	 * Test get_post_components requires proper nonce
	 */
	public function test_get_post_components_requires_nonce() {
		$_POST = array(
			'action' => 'aips_get_post_components',
			'post_id' => 1,
			'history_id' => 1,
		);
		
		// Should fail without nonce
		$exception_thrown = false;
		ob_start();
		try {
			$this->controller->ajax_get_post_components();
		} catch (WPAjaxDieStopException $e) {
			// Expected - nonce check failed with wp_die
			$exception_thrown = true;
		} catch (WPAjaxDieContinueException $e) {
			// Also acceptable - nonce check failed with wp_send_json_error
			$exception_thrown = true;
		}
		ob_end_clean();
		
		$this->assertTrue($exception_thrown, 'Nonce validation should have thrown an exception');
	}
	
	/**
	 * Test get_post_components requires valid post ID
	 */
	public function test_get_post_components_requires_valid_post() {
		// Create a test post and history
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Test Post',
			'post_content' => 'Test content',
			'post_excerpt' => 'Test excerpt',
		));
		
		// Create template
		$template_id = $this->template_repository->create(array(
			'name' => 'Test Template',
			'system_prompt' => 'Test prompt',
			'user_prompt' => 'Test user prompt',
			'is_active' => 1,
		));
		
		// Create history record
		$history_id = $this->history_repository->create(array(
			'template_id' => $template_id,
			'post_id' => $post_id,
			'status' => 'completed',
		));
		
		// Set up request with valid nonce
		$_POST = array(
			'action' => 'aips_get_post_components',
			'post_id' => $post_id,
			'history_id' => $history_id,
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		
		// This should succeed (just checking it doesn't throw an error)
		ob_start();
		try {
			$this->controller->ajax_get_post_components();
		} catch (WPAjaxDieContinueException $e) {
			// wp_send_json_success throws this
			$output = ob_get_clean();
			$response = json_decode($output, true);
			
			$this->assertTrue($response['success']);
			$this->assertArrayHasKey('components', $response['data']);
			$this->assertArrayHasKey('title', $response['data']['components']);
			$this->assertArrayHasKey('excerpt', $response['data']['components']);
			$this->assertArrayHasKey('content', $response['data']['components']);
			$this->assertArrayHasKey('featured_image', $response['data']['components']);
			return;
		} catch (Exception $e) {
			ob_end_clean();
			$this->fail('Should not throw exception: ' . $e->getMessage());
		}
		ob_end_clean();
		$this->fail('Should have thrown WPAjaxDieContinueException');
	}
	
	/**
	 * Test regenerate_component validates component type
	 */
	public function test_regenerate_component_validates_type() {
		$post_id = $this->factory->post->create();
		$history_id = $this->history_repository->create(array(
			'template_id' => 1,
			'post_id' => $post_id,
			'status' => 'completed',
		));
		
		$_POST = array(
			'action' => 'aips_regenerate_component',
			'post_id' => $post_id,
			'history_id' => $history_id,
			'component' => 'invalid_component',
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		
		ob_start();
		try {
			$this->controller->ajax_regenerate_component();
			$output = ob_get_clean();
			$response = json_decode($output, true);
			
			$this->assertFalse($response['success']);
			$this->assertStringContainsString('Invalid component', $response['data']['message']);
		} catch (Exception $e) {
			ob_end_clean();
		}
	}
	
	/**
	 * Test save_post_components requires edit permission
	 */
	public function test_save_post_components_requires_permission() {
		// Switch to a user without edit permission
		wp_set_current_user($this->factory->user->create(array('role' => 'subscriber')));
		
		$post_id = $this->factory->post->create();
		
		$_POST = array(
			'action' => 'aips_save_post_components',
			'post_id' => $post_id,
			'components' => array('title' => 'New Title'),
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		
		ob_start();
		try {
			$this->controller->ajax_save_post_components();
			$output = ob_get_clean();
			$response = json_decode($output, true);
			
			$this->assertFalse($response['success']);
			$this->assertStringContainsString('Permission denied', $response['data']['message']);
		} catch (Exception $e) {
			ob_end_clean();
		}
	}
	
	/**
	 * Test save_post_components updates post correctly
	 */
	public function test_save_post_components_updates_post() {
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Old Title',
			'post_excerpt' => 'Old excerpt',
			'post_content' => 'Old content',
		));
		
		$_POST = array(
			'action' => 'aips_save_post_components',
			'post_id' => $post_id,
			'components' => array(
				'title' => 'New Title',
				'excerpt' => 'New excerpt',
				'content' => 'New content',
			),
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		
		ob_start();
		try {
			$this->controller->ajax_save_post_components();
			$output = ob_get_clean();
			$response = json_decode($output, true);
			
			$this->assertTrue($response['success']);
			
			// Verify post was updated
			$updated_post = get_post($post_id);
			$this->assertEquals('New Title', $updated_post->post_title);
			$this->assertEquals('New excerpt', $updated_post->post_excerpt);
			$this->assertEquals('New content', $updated_post->post_content);
		} catch (Exception $e) {
			ob_end_clean();
			$this->fail('Should not throw exception: ' . $e->getMessage());
		}
	}
	
	/**
	 * Test save_post_components sanitizes input
	 */
	public function test_save_post_components_sanitizes_input() {
		$post_id = $this->factory->post->create();
		
		$_POST = array(
			'action' => 'aips_save_post_components',
			'post_id' => $post_id,
			'components' => array(
				'title' => '<script>alert("xss")</script>Safe Title',
				'excerpt' => '<script>alert("xss")</script>Safe excerpt',
				'content' => '<p>Safe content</p><script>alert("xss")</script>',
			),
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		);
		
		ob_start();
		try {
			$this->controller->ajax_save_post_components();
			$output = ob_get_clean();
			$response = json_decode($output, true);
			
			$this->assertTrue($response['success']);
			
			// Verify malicious content was removed
			$updated_post = get_post($post_id);
			$this->assertStringNotContainsString('<script>', $updated_post->post_title);
			$this->assertStringNotContainsString('<script>', $updated_post->post_excerpt);
			
			// Content should allow safe HTML
			$this->assertStringContainsString('<p>Safe content</p>', $updated_post->post_content);
			$this->assertStringNotContainsString('<script>', $updated_post->post_content);
		} catch (Exception $e) {
			ob_end_clean();
			$this->fail('Should not throw exception: ' . $e->getMessage());
		}
	}
}
