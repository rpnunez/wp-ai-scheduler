<?php
/**
 * Tests for scheduling priority bump functionality
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Scheduling_Priority_Bump_Test extends WP_UnitTestCase {
	
	private $post_generator;
	private $topics_repository;
	private $authors_repository;
	private $test_author_id;
	
	public function setUp(): void {
		parent::setUp();
		$this->post_generator = new AIPS_Author_Post_Generator();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();
		
		// Create test author
		$author_data = array(
			'name' => 'Test Author',
			'field_niche' => 'Testing',
			'is_active' => 1,
			'post_status' => 'future' // Important: set to future for scheduling
		);
		$this->test_author_id = $this->authors_repository->create($author_data);
	}
	
	public function tearDown(): void {
		// Clean up test data
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$authors_table = $wpdb->prefix . 'aips_authors';
		
		// Delete test posts
		$posts = get_posts(array(
			'post_type' => 'post',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'meta_key' => '_aips_test_post',
			'meta_value' => '1'
		));
		foreach ($posts as $post) {
			wp_delete_post($post->ID, true);
		}
		
		$wpdb->query($wpdb->prepare("DELETE FROM $topics_table WHERE author_id = %d", $this->test_author_id));
		$wpdb->query($wpdb->prepare("DELETE FROM $authors_table WHERE id = %d", $this->test_author_id));
		
		parent::tearDown();
	}
	
	public function test_scheduling_priority_bump_only_affects_future_posts() {
		// Create a topic with high score
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'High Score Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		$topic = $this->topics_repository->get_by_id($topic_id);
		$topic->computed_score = 100; // High score
		
		// Create a 'draft' post (should not be affected)
		$post_id = wp_insert_post(array(
			'post_title' => 'Test Draft Post',
			'post_content' => 'Test content',
			'post_status' => 'draft',
			'meta_input' => array('_aips_test_post' => '1')
		));
		
		$original_date = get_post_field('post_date', $post_id);
		
		// Apply scheduling bump through reflection (since method is private)
		$reflection = new ReflectionClass($this->post_generator);
		$method = $reflection->getMethod('apply_scheduling_priority_bump');
		$method->setAccessible(true);
		$method->invoke($this->post_generator, $post_id, $topic);
		
		// Post date should not change for draft posts
		$new_date = get_post_field('post_date', $post_id);
		$this->assertEquals($original_date, $new_date);
		
		wp_delete_post($post_id, true);
	}
	
	public function test_scheduling_priority_bump_adjusts_future_post_date() {
		// Set custom config for predictable results
		update_option('aips_topic_scoring_base', 50);
		update_option('aips_topic_scoring_alpha', 10);
		update_option('aips_topic_scoring_beta', 15);
		update_option('aips_topic_scoring_gamma', 5);
		update_option('aips_topic_scheduling_priority_bump', 3600);
		
		// Create a topic with high score
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'High Score Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		$topic = $this->topics_repository->get_by_id($topic_id);
		$topic->computed_score = 100; // High score (base is 50, so diff is +50)
		
		// Create a 'future' post scheduled 1 day from now
		$future_date = date('Y-m-d H:i:s', strtotime('+1 day'));
		$post_id = wp_insert_post(array(
			'post_title' => 'Test Future Post',
			'post_content' => 'Test content',
			'post_status' => 'future',
			'post_date' => $future_date,
			'post_date_gmt' => get_gmt_from_date($future_date),
			'meta_input' => array('_aips_test_post' => '1')
		));
		
		$original_timestamp = strtotime(get_post_field('post_date', $post_id));
		
		// Apply scheduling bump
		$reflection = new ReflectionClass($this->post_generator);
		$method = $reflection->getMethod('apply_scheduling_priority_bump');
		$method->setAccessible(true);
		$method->invoke($this->post_generator, $post_id, $topic);
		
		// Post date should be adjusted earlier
		$new_timestamp = strtotime(get_post_field('post_date', $post_id));
		$this->assertLessThan($original_timestamp, $new_timestamp, 'Post date should be adjusted earlier for high-scoring topics');
		
		// Cleanup
		delete_option('aips_topic_scoring_base');
		delete_option('aips_topic_scoring_alpha');
		delete_option('aips_topic_scoring_beta');
		delete_option('aips_topic_scoring_gamma');
		delete_option('aips_topic_scheduling_priority_bump');
		wp_delete_post($post_id, true);
	}
	
	public function test_scheduling_priority_bump_respects_minimum_time() {
		// Create a topic with extremely high score
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Very High Score Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		$topic = $this->topics_repository->get_by_id($topic_id);
		$topic->computed_score = 500; // Extremely high score
		
		// Create a 'future' post scheduled 5 minutes from now
		$future_date = date('Y-m-d H:i:s', strtotime('+5 minutes'));
		$post_id = wp_insert_post(array(
			'post_title' => 'Test Near-Future Post',
			'post_content' => 'Test content',
			'post_status' => 'future',
			'post_date' => $future_date,
			'post_date_gmt' => get_gmt_from_date($future_date),
			'meta_input' => array('_aips_test_post' => '1')
		));
		
		// Apply scheduling bump
		$reflection = new ReflectionClass($this->post_generator);
		$method = $reflection->getMethod('apply_scheduling_priority_bump');
		$method->setAccessible(true);
		$method->invoke($this->post_generator, $post_id, $topic);
		
		// Post date should not be in the past
		$new_timestamp = strtotime(get_post_field('post_date', $post_id));
		$this->assertGreaterThanOrEqual(time(), $new_timestamp, 'Post date should never be in the past');
		
		wp_delete_post($post_id, true);
	}
	
	public function test_scheduling_priority_bump_with_low_score() {
		// Set custom config
		update_option('aips_topic_scoring_base', 50);
		update_option('aips_topic_scheduling_priority_bump', 3600);
		
		// Create a topic with low score
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Low Score Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		$topic = $this->topics_repository->get_by_id($topic_id);
		$topic->computed_score = 25; // Low score (base is 50, so diff is -25)
		
		// Create a 'future' post scheduled 2 days from now
		$future_date = date('Y-m-d H:i:s', strtotime('+2 days'));
		$post_id = wp_insert_post(array(
			'post_title' => 'Test Future Post',
			'post_content' => 'Test content',
			'post_status' => 'future',
			'post_date' => $future_date,
			'post_date_gmt' => get_gmt_from_date($future_date),
			'meta_input' => array('_aips_test_post' => '1')
		));
		
		$original_timestamp = strtotime(get_post_field('post_date', $post_id));
		
		// Apply scheduling bump
		$reflection = new ReflectionClass($this->post_generator);
		$method = $reflection->getMethod('apply_scheduling_priority_bump');
		$method->setAccessible(true);
		$method->invoke($this->post_generator, $post_id, $topic);
		
		// Post date should be adjusted later for low-scoring topics
		$new_timestamp = strtotime(get_post_field('post_date', $post_id));
		$this->assertGreaterThan($original_timestamp, $new_timestamp, 'Post date should be adjusted later for low-scoring topics');
		
		// Cleanup
		delete_option('aips_topic_scoring_base');
		delete_option('aips_topic_scheduling_priority_bump');
		wp_delete_post($post_id, true);
	}
	
	public function test_scheduling_priority_bump_without_computed_score() {
		// Create a topic without computed score
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Topic Without Score',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		$topic = $this->topics_repository->get_by_id($topic_id);
		// Don't set computed_score
		
		// Create a 'future' post
		$future_date = date('Y-m-d H:i:s', strtotime('+1 day'));
		$post_id = wp_insert_post(array(
			'post_title' => 'Test Future Post',
			'post_content' => 'Test content',
			'post_status' => 'future',
			'post_date' => $future_date,
			'post_date_gmt' => get_gmt_from_date($future_date),
			'meta_input' => array('_aips_test_post' => '1')
		));
		
		$original_date = get_post_field('post_date', $post_id);
		
		// Apply scheduling bump
		$reflection = new ReflectionClass($this->post_generator);
		$method = $reflection->getMethod('apply_scheduling_priority_bump');
		$method->setAccessible(true);
		$method->invoke($this->post_generator, $post_id, $topic);
		
		// Post date should not change if topic has no computed score
		$new_date = get_post_field('post_date', $post_id);
		$this->assertEquals($original_date, $new_date);
		
		wp_delete_post($post_id, true);
	}
}
