<?php
/**
 * Tests for AIPS_Author_Topics_Controller Bulk Actions
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Author_Topics_Bulk_Actions_Test extends WP_UnitTestCase {
	
	private $controller;
	private $repository;
	private $logs_repository;
	private $admin_user_id;
	private $subscriber_user_id;
	private $author_id;
	
	public function setUp(): void {
		parent::setUp();
		
		// Create test users
		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));
		
		// Initialize repositories
		$this->repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();
		
		// Initialize controller
		$this->controller = new AIPS_Author_Topics_Controller();
		
		// Set up nonce
		$_REQUEST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		
		// Create a test author
		$authors_repository = new AIPS_Authors_Repository();
		$this->author_id = $authors_repository->create(array(
			'name' => 'Test Author',
			'field_niche' => 'Test Field',
			'description' => 'Test Description',
			'is_active' => 1,
			'topic_generation_quantity' => 5,
			'topic_generation_frequency' => 'weekly',
			'post_generation_frequency' => 'daily'
		));
	}
	
	public function tearDown(): void {
		// Clean up test data
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$authors_table = $wpdb->prefix . 'aips_authors';
		
		$wpdb->query("DELETE FROM $topics_table WHERE author_id = {$this->author_id}");
		$wpdb->query("DELETE FROM $authors_table WHERE id = {$this->author_id}");
		
		// Clean up $_POST and $_REQUEST
		$_POST = array();
		$_REQUEST = array();
		
		parent::tearDown();
	}
	
	/**
	 * Helper method to create test topics
	 */
	private function create_test_topics($count = 3) {
		$topic_ids = array();
		for ($i = 0; $i < $count; $i++) {
			$topic_id = $this->repository->create(array(
				'author_id' => $this->author_id,
				'topic_title' => 'Test Topic ' . ($i + 1),
				'status' => 'pending',
				'generated_at' => current_time('mysql')
			));
			$topic_ids[] = $topic_id;
		}
		return $topic_ids;
	}
	
	/**
	 * Test bulk approve topics with valid permissions
	 */
	public function test_ajax_bulk_approve_topics_success() {
		wp_set_current_user($this->admin_user_id);
		
		// Create test topics
		$topic_ids = $this->create_test_topics(3);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_ids'] = $topic_ids;
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_bulk_approve_topics();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Assertions
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('3 topics approved', $response['data']['message']);
		
		// Verify topics are approved in database
		foreach ($topic_ids as $topic_id) {
			$topic = $this->repository->get_by_id($topic_id);
			$this->assertEquals('approved', $topic->status);
		}
	}
	
	/**
	 * Test bulk reject topics with valid permissions
	 */
	public function test_ajax_bulk_reject_topics_success() {
		wp_set_current_user($this->admin_user_id);
		
		// Create test topics
		$topic_ids = $this->create_test_topics(2);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_ids'] = $topic_ids;
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_bulk_reject_topics();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Assertions
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('2 topics rejected', $response['data']['message']);
		
		// Verify topics are rejected in database
		foreach ($topic_ids as $topic_id) {
			$topic = $this->repository->get_by_id($topic_id);
			$this->assertEquals('rejected', $topic->status);
		}
	}
	
	/**
	 * Test bulk delete topics with valid permissions
	 */
	public function test_ajax_bulk_delete_topics_success() {
		wp_set_current_user($this->admin_user_id);
		
		// Create test topics
		$topic_ids = $this->create_test_topics(3);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_ids'] = $topic_ids;
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_bulk_delete_topics();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Assertions
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('3 topics deleted', $response['data']['message']);
		
		// Verify topics are deleted from database
		foreach ($topic_ids as $topic_id) {
			$topic = $this->repository->get_by_id($topic_id);
			$this->assertNull($topic);
		}
	}
	
	/**
	 * Test bulk delete topics with no topics selected
	 */
	public function test_ajax_bulk_delete_topics_no_topics() {
		wp_set_current_user($this->admin_user_id);
		
		// Set up POST data with empty array
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_ids'] = array();
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_bulk_delete_topics();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Assertions
		$this->assertFalse($response['success']);
		$this->assertEquals('No topics selected.', $response['data']['message']);
	}
	
	/**
	 * Test bulk delete topics without permissions
	 */
	public function test_ajax_bulk_delete_topics_no_permission() {
		wp_set_current_user($this->subscriber_user_id);
		
		// Create test topics
		$topic_ids = $this->create_test_topics(2);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_ids'] = $topic_ids;
		
		// Capture output
		ob_start();
		try {
			$this->controller->ajax_bulk_delete_topics();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		$output = ob_get_clean();
		
		// Parse JSON response
		$response = json_decode($output, true);
		
		// Assertions
		$this->assertFalse($response['success']);
		$this->assertEquals('Permission denied.', $response['data']['message']);
		
		// Verify topics are NOT deleted from database
		foreach ($topic_ids as $topic_id) {
			$topic = $this->repository->get_by_id($topic_id);
			$this->assertNotNull($topic);
		}
	}
}
