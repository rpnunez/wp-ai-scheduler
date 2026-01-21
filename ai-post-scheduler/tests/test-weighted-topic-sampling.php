<?php
/**
 * Tests for weighted topic sampling
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Weighted_Topic_Sampling_Test extends WP_UnitTestCase {
	
	private $topics_repository;
	private $feedback_repository;
	private $authors_repository;
	private $test_author_id;
	
	public function setUp(): void {
		parent::setUp();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->feedback_repository = new AIPS_Feedback_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();
		
		// Create test author
		$author_data = array(
			'name' => 'Test Author',
			'field_niche' => 'PHP Testing',
			'is_active' => 1
		);
		$this->test_author_id = $this->authors_repository->create($author_data);
	}
	
	public function tearDown(): void {
		// Clean up test data
		global $wpdb;
		$feedback_table = $wpdb->prefix . 'aips_topic_feedback';
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$authors_table = $wpdb->prefix . 'aips_authors';
		
		$wpdb->query($wpdb->prepare("DELETE FROM $feedback_table WHERE author_topic_id IN (SELECT id FROM $topics_table WHERE author_id = %d)", $this->test_author_id));
		$wpdb->query($wpdb->prepare("DELETE FROM $topics_table WHERE author_id = %d", $this->test_author_id));
		$wpdb->query($wpdb->prepare("DELETE FROM $authors_table WHERE id = %d", $this->test_author_id));
		
		parent::tearDown();
	}
	
	public function test_weighted_sampling_returns_approved_topics() {
		// Create approved topics
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Test Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		$config = array(
			'base' => 50,
			'alpha' => 10,
			'beta' => 15,
			'gamma' => 5
		);
		
		$topics = $this->topics_repository->get_approved_for_generation_weighted($this->test_author_id, 1, $config);
		
		$this->assertCount(1, $topics);
		$this->assertEquals('Test Topic', $topics[0]->topic_title);
		$this->assertObjectHasProperty('computed_score', $topics[0]);
	}
	
	public function test_weighted_sampling_with_no_topics() {
		$config = array(
			'base' => 50,
			'alpha' => 10,
			'beta' => 15,
			'gamma' => 5
		);
		
		$topics = $this->topics_repository->get_approved_for_generation_weighted($this->test_author_id, 1, $config);
		
		$this->assertEmpty($topics);
	}
	
	public function test_score_calculation_with_approvals() {
		// Create topic with approvals
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Popular Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		// Add approval feedback
		$this->feedback_repository->record_approval($topic_id, 1, 'Good topic');
		$this->feedback_repository->record_approval($topic_id, 1, 'Another approval');
		
		$config = array(
			'base' => 50,
			'alpha' => 10,
			'beta' => 15,
			'gamma' => 5
		);
		
		$topics = $this->topics_repository->get_approved_for_generation_weighted($this->test_author_id, 1, $config);
		
		$this->assertCount(1, $topics);
		// Score should be: base + (alpha * 2 approvals) = 50 + 20 = 70
		$this->assertGreaterThanOrEqual(70, $topics[0]->computed_score);
	}
	
	public function test_score_calculation_with_rejections() {
		// Create topic with rejections
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Rejected Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		// Add rejection feedback
		$this->feedback_repository->record_rejection($topic_id, 1, 'Not good');
		
		$config = array(
			'base' => 50,
			'alpha' => 10,
			'beta' => 15,
			'gamma' => 5
		);
		
		$topics = $this->topics_repository->get_approved_for_generation_weighted($this->test_author_id, 1, $config);
		
		$this->assertCount(1, $topics);
		// Score should be: base - (beta * 1 rejection) = 50 - 15 = 35
		$this->assertLessThanOrEqual(35, $topics[0]->computed_score);
	}
	
	public function test_score_calculation_with_mixed_feedback() {
		// Create topic with mixed feedback
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Mixed Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		// Add mixed feedback
		$this->feedback_repository->record_approval($topic_id, 1, 'Good');
		$this->feedback_repository->record_approval($topic_id, 1, 'Great');
		$this->feedback_repository->record_rejection($topic_id, 1, 'Not so good');
		
		$config = array(
			'base' => 50,
			'alpha' => 10,
			'beta' => 15,
			'gamma' => 5
		);
		
		$topics = $this->topics_repository->get_approved_for_generation_weighted($this->test_author_id, 1, $config);
		
		$this->assertCount(1, $topics);
		// Score should be: base + (alpha * 2) - (beta * 1) = 50 + 20 - 15 = 55
		$this->assertGreaterThanOrEqual(50, $topics[0]->computed_score);
	}
	
	public function test_weighted_sampling_multiple_topics() {
		// Create multiple approved topics
		for ($i = 1; $i <= 5; $i++) {
			$this->topics_repository->create(array(
				'author_id' => $this->test_author_id,
				'topic_title' => "Topic {$i}",
				'status' => 'approved',
				'reviewed_at' => current_time('mysql')
			));
		}
		
		$config = array(
			'base' => 50,
			'alpha' => 10,
			'beta' => 15,
			'gamma' => 5
		);
		
		$topics = $this->topics_repository->get_approved_for_generation_weighted($this->test_author_id, 3, $config);
		
		$this->assertCount(3, $topics);
		
		// Check that all topics have scores
		foreach ($topics as $topic) {
			$this->assertObjectHasProperty('computed_score', $topic);
			$this->assertGreaterThan(0, $topic->computed_score);
		}
	}
	
	public function test_minimum_score_applied() {
		// Create topic with rejections to potentially get negative score
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Low Score Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		// Add many rejections
		for ($i = 0; $i < 10; $i++) {
			$this->feedback_repository->record_rejection($topic_id, 1, 'Bad');
		}
		
		$config = array(
			'base' => 10,
			'alpha' => 1,
			'beta' => 50,
			'gamma' => 1
		);
		
		$topics = $this->topics_repository->get_approved_for_generation_weighted($this->test_author_id, 1, $config);
		
		$this->assertCount(1, $topics);
		// Minimum score should be 1
		$this->assertGreaterThanOrEqual(1, $topics[0]->computed_score);
	}
	
	public function test_recency_penalty_applied() {
		// Create old topic
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		
		$topic_id = $this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Old Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		// Manually update reviewed_at to 60 days ago
		$wpdb->update(
			$topics_table,
			array('reviewed_at' => date('Y-m-d H:i:s', strtotime('-60 days'))),
			array('id' => $topic_id)
		);
		
		$config = array(
			'base' => 50,
			'alpha' => 10,
			'beta' => 15,
			'gamma' => 5
		);
		
		$topics = $this->topics_repository->get_approved_for_generation_weighted($this->test_author_id, 1, $config);
		
		$this->assertCount(1, $topics);
		// Score should be penalized for being old
		// Base 50 - gamma * (60/30) = 50 - 5*2 = 40
		$this->assertLessThan(50, $topics[0]->computed_score);
	}
	
	public function test_uses_default_config_when_not_provided() {
		// Create topic
		$this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Test Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		// Call without config (should use defaults from AIPS_Config)
		$topics = $this->topics_repository->get_approved_for_generation_weighted($this->test_author_id, 1);
		
		$this->assertCount(1, $topics);
		$this->assertObjectHasProperty('computed_score', $topics[0]);
	}
	
	public function test_only_returns_approved_topics() {
		// Create mixed status topics
		$this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Approved Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql')
		));
		
		$this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Pending Topic',
			'status' => 'pending'
		));
		
		$this->topics_repository->create(array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Rejected Topic',
			'status' => 'rejected',
			'reviewed_at' => current_time('mysql')
		));
		
		$config = array(
			'base' => 50,
			'alpha' => 10,
			'beta' => 15,
			'gamma' => 5
		);
		
		$topics = $this->topics_repository->get_approved_for_generation_weighted($this->test_author_id, 10, $config);
		
		$this->assertCount(1, $topics);
		$this->assertEquals('Approved Topic', $topics[0]->topic_title);
	}
}
