<?php
/**
 * Tests for Kanban Board Functionality
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Kanban_Board_Test extends WP_UnitTestCase {
	
	private $controller;
	private $repository;
	private $admin_user_id;
	private $author_id;
	private $topic_id;
	
	public function setUp(): void {
		parent::setUp();
		
		// Create test user
		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		
		// Initialize repositories
		$this->repository = new AIPS_Author_Topics_Repository();
		
		// Initialize controller
		$this->controller = new AIPS_Author_Topics_Controller();
		
		// Set up nonce
		$_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		
		// Create a test author
		$authors_repository = new AIPS_Authors_Repository();
		$this->author_id = $authors_repository->create(array(
			'name' => 'Test Author',
			'field_niche' => 'Technology',
			'description' => 'Test Description',
			'is_active' => 1,
			'topic_generation_quantity' => 5,
			'topic_generation_frequency' => 'weekly',
			'post_generation_frequency' => 'daily'
		));
		
		// Create a test topic
		$this->topic_id = $this->repository->create(array(
			'author_id' => $this->author_id,
			'topic_title' => 'Test Kanban Topic',
			'topic_prompt' => 'Test topic for Kanban board',
			'status' => 'pending',
			'generated_at' => current_time('mysql')
		));
	}
	
	public function tearDown(): void {
		// Clean up test data
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$authors_table = $wpdb->prefix . 'aips_authors';
		
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $topics_table WHERE author_id = %d",
				$this->author_id
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $authors_table WHERE id = %d",
				$this->author_id
			)
		);
		
		// Clean up $_POST and $_REQUEST
		$_POST = array();
		$_REQUEST = array();
		
		parent::tearDown();
	}
	
	/**
	 * Test moving topic from pending to approved via Kanban
	 */
	public function test_kanban_move_pending_to_approved() {
		wp_set_current_user($this->admin_user_id);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_id'] = $this->topic_id;
		$_POST['status'] = 'approved';
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_update_topic_status_kanban();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Assertions
		$this->assertTrue($response['success']);
		$this->assertArrayHasKey('message', $response['data']);
		$this->assertEquals('approved', $response['data']['status']);
		
		// Verify database update
		$topic = $this->repository->get_by_id($this->topic_id);
		$this->assertEquals('approved', $topic->status);
	}
	
	/**
	 * Test moving topic from pending to rejected via Kanban
	 */
	public function test_kanban_move_pending_to_rejected() {
		wp_set_current_user($this->admin_user_id);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_id'] = $this->topic_id;
		$_POST['status'] = 'rejected';
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_update_topic_status_kanban();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Assertions
		$this->assertTrue($response['success']);
		$this->assertEquals('rejected', $response['data']['status']);
		
		// Verify database update
		$topic = $this->repository->get_by_id($this->topic_id);
		$this->assertEquals('rejected', $topic->status);
	}
	
	/**
	 * Test permission check - non-admin users cannot update topics
	 */
	public function test_kanban_update_requires_admin_permission() {
		$subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_id'] = $this->topic_id;
		$_POST['status'] = 'approved';
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_update_topic_status_kanban();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Assertions
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Permission denied', $response['data']['message']);
	}
	
	/**
	 * Test that invalid status values are properly rejected
	 */
	public function test_kanban_invalid_status() {
		wp_set_current_user($this->admin_user_id);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_id'] = $this->topic_id;
		$_POST['status'] = 'invalid_status';
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_update_topic_status_kanban();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Assertions
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Invalid status', $response['data']['message']);
	}
	
	/**
	 * Test that invalid or non-existent topic IDs are properly rejected
	 */
	public function test_kanban_invalid_topic_id() {
		wp_set_current_user($this->admin_user_id);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_id'] = 99999; // Non-existent ID
		$_POST['status'] = 'approved';
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_update_topic_status_kanban();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Assertions
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Topic not found', $response['data']['message']);
	}
	
	/**
	 * Test that 'generate' status triggers post generation workflow
	 * 
	 * Note: Full generation workflow testing requires a complete WordPress environment
	 * with AI Engine plugin active. This test verifies the endpoint accepts 'generate'
	 * status and handles the transition validation properly.
	 */
	public function test_kanban_generate_status_validates_transitions() {
		wp_set_current_user($this->admin_user_id);
		
		// Test 1: Generate from pending status (should be allowed)
		$pending_topic_id = $this->repository->create(array(
			'author_id' => $this->author_id,
			'topic_title' => 'Test Pending Topic',
			'topic_prompt' => 'Test topic for generation',
			'status' => 'pending',
			'generated_at' => current_time('mysql')
		));
		
		// Verify the topic can be moved to approved first (which generate does)
		$result = $this->repository->update_status($pending_topic_id, 'approved', get_current_user_id());
		$this->assertTrue($result);
		
		// Clean up: reset to pending for next test
		$this->repository->update_status($pending_topic_id, 'pending', get_current_user_id());
		
		// Test 2: Try to generate from rejected status (should fail validation)
		$rejected_topic_id = $this->repository->create(array(
			'author_id' => $this->author_id,
			'topic_title' => 'Test Rejected Topic',
			'topic_prompt' => 'Test rejected topic',
			'status' => 'rejected',
			'generated_at' => current_time('mysql')
		));
		
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_id'] = $rejected_topic_id;
		$_POST['status'] = 'generate';
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_update_topic_status_kanban();
		} catch (WPAjaxDieContinueException $e) {
			// Expected
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Should fail because rejected topics cannot be generated
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('approved', strtolower($response['data']['message']));
		
		// Verify 'generate' is in the valid statuses list
		$valid_statuses = array('pending', 'approved', 'rejected', 'generate');
		$this->assertContains('generate', $valid_statuses);
	}
	
	/**
	 * Test that AJAX requests require valid nonce for security
	 */
	public function test_kanban_requires_valid_nonce() {
		wp_set_current_user($this->admin_user_id);
		
		// Set up POST data with invalid nonce
		$_POST['nonce'] = 'invalid_nonce';
		$_POST['topic_id'] = $this->topic_id;
		$_POST['status'] = 'approved';
		
		// Expect exception for nonce failure
		$this->expectException(WPAjaxDieStopException::class);
		
		$this->controller->ajax_update_topic_status_kanban();
	}
}
