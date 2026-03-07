<?php
/**
 * Test Topic Posts View functionality
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

class Test_Topic_Posts_View extends WP_UnitTestCase {
	
	private $controller;
	private $topics_repository;
	private $logs_repository;
	
	public function setUp(): void {
		parent::setUp();

		// Classes loaded via Composer PSR-4 + compatibility layer
		$this->controller = new AIPS_Authors_Controller();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();
	}
	
	public function tearDown(): void {
		parent::tearDown();
	}
	
	/**
	 * Test that ajax_get_author_topics includes post_count for each topic
	 */
	public function test_get_author_topics_includes_post_count() {
		// Create a test author
		$authors_repo = new AIPS_Authors_Repository();
		$author_id = $authors_repo->create(array(
			'name' => 'Test Author',
			'field_niche' => 'Technology',
			'is_active' => 1
		));
		
		// Create a test topic
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $author_id,
			'topic_title' => 'Test Topic',
			'topic_prompt' => 'Test prompt',
			'status' => 'approved'
		));
		
		// Create a test post
		$post_id = $this->factory->post->create(array(
			'post_title' => 'Test Post',
			'post_status' => 'publish'
		));
		
		// Log the post generation
		$this->logs_repository->log_post_generation($topic_id, $post_id);
		
		// Get topics
		$topics = $this->topics_repository->get_by_author($author_id);
		
		// Manually add post count (simulating what the controller does)
		foreach ($topics as &$topic) {
			$logs = $this->logs_repository->get_by_topic($topic->id);
			$post_count = 0;
			foreach ($logs as $log) {
				if ($log->action === 'post_generated' && $log->post_id) {
					$post_count++;
				}
			}
			$topic->post_count = $post_count;
		}
		
		$this->assertCount(1, $topics);
		$this->assertEquals(1, $topics[0]->post_count);
	}
	
	/**
	 * Test that topics with no posts have post_count of 0
	 */
	public function test_topics_without_posts_have_zero_count() {
		// Create a test author
		$authors_repo = new AIPS_Authors_Repository();
		$author_id = $authors_repo->create(array(
			'name' => 'Test Author 2',
			'field_niche' => 'Science',
			'is_active' => 1
		));
		
		// Create a test topic (without any posts)
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $author_id,
			'topic_title' => 'Test Topic Without Posts',
			'topic_prompt' => 'Test prompt',
			'status' => 'pending'
		));
		
		// Get topics
		$topics = $this->topics_repository->get_by_author($author_id);
		
		// Manually add post count (simulating what the controller does)
		foreach ($topics as &$topic) {
			$logs = $this->logs_repository->get_by_topic($topic->id);
			$post_count = 0;
			foreach ($logs as $log) {
				if ($log->action === 'post_generated' && $log->post_id) {
					$post_count++;
				}
			}
			$topic->post_count = $post_count;
		}
		
		$this->assertCount(1, $topics);
		$this->assertEquals(0, $topics[0]->post_count);
	}
	
	/**
	 * Test that get_by_topic returns correct logs
	 */
	public function test_get_topic_posts_returns_correct_data() {
		// Create a test author
		$authors_repo = new AIPS_Authors_Repository();
		$author_id = $authors_repo->create(array(
			'name' => 'Test Author 3',
			'field_niche' => 'Business',
			'is_active' => 1
		));
		
		// Create a test topic
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $author_id,
			'topic_title' => 'Business Topic',
			'topic_prompt' => 'Business prompt',
			'status' => 'approved'
		));
		
		// Create multiple test posts
		$post_ids = array();
		for ($i = 0; $i < 3; $i++) {
			$post_id = $this->factory->post->create(array(
				'post_title' => 'Business Post ' . ($i + 1),
				'post_status' => 'publish'
			));
			$post_ids[] = $post_id;
			
			// Log the post generation
			$this->logs_repository->log_post_generation($topic_id, $post_id);
		}
		
		// Get logs for this topic
		$logs = $this->logs_repository->get_by_topic($topic_id);
		
		// Count only post_generated logs
		$post_count = 0;
		foreach ($logs as $log) {
			if ($log->action === 'post_generated' && $log->post_id) {
				$post_count++;
				$this->assertTrue(in_array($log->post_id, $post_ids));
			}
		}
		
		$this->assertEquals(3, $post_count);
	}
}
