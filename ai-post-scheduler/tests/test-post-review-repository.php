<?php
/**
 * Tests for Post Review Repository
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Review_Repository extends WP_UnitTestCase {
	
	private $repository;
	private $history_repository;
	private $test_post_ids = array();
	private $test_history_ids = array();
	
	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_Post_Review_Repository();
		$this->history_repository = new AIPS_History_Repository();
	}
	
	public function tearDown(): void {
		// Clean up test posts
		foreach ($this->test_post_ids as $post_id) {
			wp_delete_post($post_id, true);
		}
		
		// Clean up test history
		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		foreach ($this->test_history_ids as $history_id) {
			$wpdb->delete($history_table, array('id' => $history_id), array('%d'));
		}
		
		parent::tearDown();
	}
	
	/**
	 * Helper method to create a test post with history.
	 */
	private function create_test_post_with_history($post_status = 'draft', $template_id = 1) {
		// Create a draft post
		$post_id = wp_insert_post(array(
			'post_title' => 'Test Draft Post ' . uniqid(),
			'post_content' => 'Test content',
			'post_status' => $post_status,
			'post_type' => 'post',
		));
		
		$this->test_post_ids[] = $post_id;
		
		// Create history record
		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		$wpdb->insert($history_table, array(
			'post_id' => $post_id,
			'template_id' => $template_id,
			'status' => 'completed',
			'generated_title' => 'Test Generated Title',
			'generated_content' => 'Test generated content',
			'created_at' => current_time('mysql'),
			'completed_at' => current_time('mysql'),
		));
		
		$history_id = $wpdb->insert_id;
		$this->test_history_ids[] = $history_id;
		
		return array('post_id' => $post_id, 'history_id' => $history_id);
	}
	
	/**
	 * Test getting draft posts.
	 */
	public function test_get_draft_posts() {
		// Create draft posts
		$this->create_test_post_with_history('draft', 1);
		$this->create_test_post_with_history('draft', 1);
		
		// Create a published post (should not be included)
		$this->create_test_post_with_history('publish', 1);
		
		$result = $this->repository->get_draft_posts();
		
		$this->assertIsArray($result);
		$this->assertArrayHasKey('items', $result);
		$this->assertArrayHasKey('total', $result);
		$this->assertGreaterThanOrEqual(2, $result['total']);
		$this->assertGreaterThanOrEqual(2, count($result['items']));
	}
	
	/**
	 * Test draft count.
	 */
	public function test_get_draft_count() {
		// Create draft posts
		$this->create_test_post_with_history('draft', 1);
		$this->create_test_post_with_history('draft', 1);
		
		// Create a published post (should not be counted)
		$this->create_test_post_with_history('publish', 1);
		
		$count = $this->repository->get_draft_count();
		
		$this->assertIsInt($count);
		$this->assertGreaterThanOrEqual(2, $count);
	}
	
	/**
	 * Test pagination.
	 */
	public function test_draft_posts_pagination() {
		// Create multiple draft posts
		for ($i = 0; $i < 5; $i++) {
			$this->create_test_post_with_history('draft', 1);
		}
		
		$result = $this->repository->get_draft_posts(array(
			'per_page' => 2,
			'page' => 1,
		));
		
		$this->assertEquals(2, count($result['items']));
		$this->assertGreaterThanOrEqual(5, $result['total']);
	}
	
	/**
	 * Test search functionality.
	 */
	public function test_draft_posts_search() {
		// Create post with specific title
		$test_data = $this->create_test_post_with_history('draft', 1);
		wp_update_post(array(
			'ID' => $test_data['post_id'],
			'post_title' => 'Unique Test Title XYZ123',
		));
		
		$result = $this->repository->get_draft_posts(array(
			'search' => 'XYZ123',
		));
		
		$this->assertGreaterThanOrEqual(1, $result['total']);
		$found = false;
		foreach ($result['items'] as $item) {
			if ($item->post_id === $test_data['post_id']) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Search did not find the test post');
	}
	
	/**
	 * Test template filter.
	 */
	public function test_draft_posts_template_filter() {
		// Create posts with different templates
		$this->create_test_post_with_history('draft', 1);
		$this->create_test_post_with_history('draft', 2);
		
		$result = $this->repository->get_draft_posts(array(
			'template_id' => 1,
		));
		
		$this->assertGreaterThanOrEqual(1, $result['total']);
		foreach ($result['items'] as $item) {
			$this->assertEquals(1, $item->template_id);
		}
	}
}
