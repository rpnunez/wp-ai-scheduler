<?php
/**
 * Tests for AIPS_Topic_Penalty_Service
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Topic_Penalty_Service_Test extends WP_UnitTestCase {
	
	private $penalty_service;
	private $topics_repository;
	private $authors_repository;
	private $test_author_id;
	private $test_topic_id;
	
	public function setUp(): void {
		parent::setUp();
		$this->penalty_service = new AIPS_Topic_Penalty_Service();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();
		
		// Create test author
		$author_data = array(
			'name' => 'Test Author',
			'field_niche' => 'Testing',
			'is_active' => 1
		);
		$this->test_author_id = $this->authors_repository->create($author_data);
		
		// Create test topic with initial score
		$topic_data = array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Test Topic',
			'status' => 'pending',
			'score' => 50
		);
		$this->test_topic_id = $this->topics_repository->create($topic_data);
	}
	
	public function tearDown(): void {
		// Clean up
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$authors_table = $wpdb->prefix . 'aips_authors';
		
		$wpdb->query($wpdb->prepare("DELETE FROM $topics_table WHERE author_id = %d", $this->test_author_id));
		$wpdb->query($wpdb->prepare("DELETE FROM $authors_table WHERE id = %d", $this->test_author_id));
		
		parent::tearDown();
	}
	
	public function test_apply_duplicate_penalty() {
		$result = $this->penalty_service->apply_penalty($this->test_topic_id, 'duplicate');
		
		$this->assertTrue($result);
		
		// Verify score was reduced
		$topic = $this->topics_repository->get_by_id($this->test_topic_id);
		$this->assertEquals(40, $topic->score); // 50 - 10 = 40
	}
	
	public function test_apply_policy_penalty() {
		$result = $this->penalty_service->apply_penalty($this->test_topic_id, 'policy');
		
		$this->assertTrue($result);
		
		// Verify hard penalty was applied
		$topic = $this->topics_repository->get_by_id($this->test_topic_id);
		$this->assertEquals(0, $topic->score); // 50 - 50 = 0
	}
	
	public function test_apply_tone_penalty() {
		$result = $this->penalty_service->apply_penalty($this->test_topic_id, 'tone');
		
		$this->assertTrue($result);
		
		$topic = $this->topics_repository->get_by_id($this->test_topic_id);
		$this->assertEquals(45, $topic->score); // 50 - 5 = 45
	}
	
	public function test_apply_reward() {
		$result = $this->penalty_service->apply_reward($this->test_topic_id, 'other');
		
		$this->assertTrue($result);
		
		$topic = $this->topics_repository->get_by_id($this->test_topic_id);
		$this->assertEquals(60, $topic->score); // 50 + 10 = 60
	}
	
	public function test_score_bounds() {
		// Set topic score to 95
		$this->topics_repository->update($this->test_topic_id, array('score' => 95));
		
		// Apply reward (should cap at 100)
		$this->penalty_service->apply_reward($this->test_topic_id, 'other');
		$topic = $this->topics_repository->get_by_id($this->test_topic_id);
		$this->assertEquals(100, $topic->score);
		
		// Set topic score to 5
		$this->topics_repository->update($this->test_topic_id, array('score' => 5));
		
		// Apply penalty (should floor at 0)
		$this->penalty_service->apply_penalty($this->test_topic_id, 'duplicate');
		$topic = $this->topics_repository->get_by_id($this->test_topic_id);
		$this->assertEquals(0, $topic->score);
	}
	
	public function test_policy_flag_creation() {
		// Apply policy penalty
		$this->penalty_service->apply_penalty($this->test_topic_id, 'policy');
		
		// Check that author was flagged
		$flags = $this->penalty_service->get_author_policy_flags($this->test_author_id);
		
		$this->assertIsArray($flags);
		$this->assertCount(1, $flags);
		$this->assertEquals($this->test_topic_id, $flags[0]['topic_id']);
		$this->assertEquals('pending_review', $flags[0]['status']);
	}
	
	public function test_clear_policy_flags() {
		// Apply policy penalty to create flag
		$this->penalty_service->apply_penalty($this->test_topic_id, 'policy');
		
		// Verify flag exists
		$flags = $this->penalty_service->get_author_policy_flags($this->test_author_id);
		$this->assertCount(1, $flags);
		
		// Clear flags
		$result = $this->penalty_service->clear_author_policy_flags($this->test_author_id);
		$this->assertTrue($result);
		
		// Verify flags are cleared
		$flags = $this->penalty_service->get_author_policy_flags($this->test_author_id);
		$this->assertEmpty($flags);
	}
	
	public function test_get_penalty_weight() {
		$this->assertEquals(-10, $this->penalty_service->get_penalty_weight('duplicate'));
		$this->assertEquals(-50, $this->penalty_service->get_penalty_weight('policy'));
		$this->assertEquals(-5, $this->penalty_service->get_penalty_weight('tone'));
		$this->assertEquals(-15, $this->penalty_service->get_penalty_weight('irrelevant'));
		$this->assertEquals(-5, $this->penalty_service->get_penalty_weight('other'));
	}
	
	public function test_custom_penalty_weights() {
		$custom_weights = array(
			'duplicate' => -20,
			'custom_reason' => -30
		);
		
		$this->penalty_service->set_penalty_weights($custom_weights);
		
		$this->assertEquals(-20, $this->penalty_service->get_penalty_weight('duplicate'));
		$this->assertEquals(-30, $this->penalty_service->get_penalty_weight('custom_reason'));
		// Original weights should still exist
		$this->assertEquals(-50, $this->penalty_service->get_penalty_weight('policy'));
	}
}
