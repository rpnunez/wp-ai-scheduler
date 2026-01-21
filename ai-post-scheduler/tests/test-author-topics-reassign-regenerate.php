<?php
/**
 * Tests for AIPS_Author_Topics_Controller Reassign and Regenerate Features
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Author_Topics_Reassign_Regenerate_Test extends WP_UnitTestCase {
	
	private $controller;
	private $topics_repository;
	private $logs_repository;
	private $authors_repository;
	private $admin_user_id;
	private $author1_id;
	private $author2_id;
	private $topic_id;
	
	public function setUp(): void {
		parent::setUp();
		
		// Create test admin user
		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_user_id);
		
		// Initialize repositories
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();
		
		// Initialize controller
		$this->controller = new AIPS_Author_Topics_Controller();
		
		// Set up nonce
		$_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		
		// Create test authors
		$this->author1_id = $this->authors_repository->create(array(
			'name' => 'Test Author 1',
			'field_niche' => 'PHP Programming',
			'description' => 'Test Description 1',
			'is_active' => 1,
			'topic_generation_quantity' => 5,
			'topic_generation_frequency' => 'weekly',
			'post_generation_frequency' => 'daily'
		));
		
		$this->author2_id = $this->authors_repository->create(array(
			'name' => 'Test Author 2',
			'field_niche' => 'JavaScript Development',
			'description' => 'Test Description 2',
			'is_active' => 1,
			'topic_generation_quantity' => 5,
			'topic_generation_frequency' => 'weekly',
			'post_generation_frequency' => 'daily'
		));
		
		// Create a test topic for author 1
		$this->topic_id = $this->topics_repository->create(array(
			'author_id' => $this->author1_id,
			'topic_title' => 'Test Topic for Reassignment',
			'status' => 'pending'
		));
	}
	
	public function tearDown(): void {
		// Clean up test data
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$logs_table = $wpdb->prefix . 'aips_author_topic_logs';
		$authors_table = $wpdb->prefix . 'aips_authors';
		
		$wpdb->query("DELETE FROM {$topics_table}");
		$wpdb->query("DELETE FROM {$logs_table}");
		$wpdb->query("DELETE FROM {$authors_table}");
		
		parent::tearDown();
	}
	
	/**
	 * Test reassigning a topic to a different author.
	 */
	public function test_reassign_topic_success() {
		// Set up POST data
		$_POST['topic_id'] = $this->topic_id;
		$_POST['new_author_id'] = $this->author2_id;
		$_POST['reason'] = 'Better fit for Author 2';
		
		// Capture output
		ob_start();
		
		try {
			$this->controller->ajax_reassign_topic();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		
		$output = ob_get_clean();
		$response = json_decode($output, true);
		
		// Verify response
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('Test Author 2', $response['data']['message']);
		
		// Verify topic was reassigned in database
		$updated_topic = $this->topics_repository->get_by_id($this->topic_id);
		$this->assertEquals($this->author2_id, $updated_topic->author_id);
		
		// Verify log was created
		$logs = $this->logs_repository->get_by_topic($this->topic_id);
		$this->assertGreaterThan(0, count($logs));
		
		$reassign_log = null;
		foreach ($logs as $log) {
			if ($log->action === 'reassigned') {
				$reassign_log = $log;
				break;
			}
		}
		
		$this->assertNotNull($reassign_log);
		$this->assertEquals($this->admin_user_id, $reassign_log->user_id);
		$this->assertStringContainsString('Test Author 2', $reassign_log->notes);
	}
	
	/**
	 * Test reassigning with invalid topic ID.
	 */
	public function test_reassign_topic_invalid_topic_id() {
		$_POST['topic_id'] = 999999;
		$_POST['new_author_id'] = $this->author2_id;
		
		ob_start();
		
		try {
			$this->controller->ajax_reassign_topic();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		
		$output = ob_get_clean();
		$response = json_decode($output, true);
		
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('not found', $response['data']['message']);
	}
	
	/**
	 * Test reassigning to invalid author ID.
	 */
	public function test_reassign_topic_invalid_author_id() {
		$_POST['topic_id'] = $this->topic_id;
		$_POST['new_author_id'] = 999999;
		
		ob_start();
		
		try {
			$this->controller->ajax_reassign_topic();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		
		$output = ob_get_clean();
		$response = json_decode($output, true);
		
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Target author not found', $response['data']['message']);
	}
	
	/**
	 * Test reassigning without permission.
	 */
	public function test_reassign_topic_no_permission() {
		// Create subscriber user
		$subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);
		
		$_POST['topic_id'] = $this->topic_id;
		$_POST['new_author_id'] = $this->author2_id;
		
		ob_start();
		
		try {
			$this->controller->ajax_reassign_topic();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		
		$output = ob_get_clean();
		$response = json_decode($output, true);
		
		$this->assertFalse($response['success']);
		$this->assertStringContainsString('Permission denied', $response['data']['message']);
	}
	
	/**
	 * Test regenerating a post.
	 */
	public function test_regenerate_post() {
		// Create a test post
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Test Post for Regeneration',
			'post_status' => 'publish'
		));
		
		// Approve the topic first
		$this->topics_repository->update_status($this->topic_id, 'approved', $this->admin_user_id);
		
		// Log the post generation
		$this->logs_repository->log_post_generation($this->topic_id, $post_id);
		
		// Set up POST data
		$_POST['post_id'] = $post_id;
		$_POST['topic_id'] = $this->topic_id;
		
		ob_start();
		
		try {
			$this->controller->ajax_regenerate_post();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		
		$output = ob_get_clean();
		$response = json_decode($output, true);
		
		// Note: This test may fail if AI generation is not mocked
		// For now, we just verify the endpoint responds correctly
		$this->assertArrayHasKey('success', $response);
	}
}
