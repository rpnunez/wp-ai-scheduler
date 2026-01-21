<?php
/**
 * Tests for AIPS_Feedback_Repository
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Feedback_Repository_Test extends WP_UnitTestCase {
	
	private $repository;
	private $authors_repository;
	private $topics_repository;
	private $test_author_id;
	private $test_topic_id;
	
	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_Feedback_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		
		// Create test author
		$author_data = array(
			'name' => 'Test Author',
			'field_niche' => 'PHP Testing',
			'is_active' => 1
		);
		$this->test_author_id = $this->authors_repository->create($author_data);
		
		// Create test topic
		$topic_data = array(
			'author_id' => $this->test_author_id,
			'topic_title' => 'Test Topic',
			'status' => 'pending'
		);
		$this->test_topic_id = $this->topics_repository->create($topic_data);
	}
	
	public function tearDown(): void {
		// Clean up test data
		global $wpdb;
		$feedback_table = $wpdb->prefix . 'aips_topic_feedback';
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$authors_table = $wpdb->prefix . 'aips_authors';
		
		$wpdb->query("DELETE FROM $feedback_table WHERE author_topic_id IN (SELECT id FROM $topics_table WHERE author_id = {$this->test_author_id})");
		$wpdb->query("DELETE FROM $topics_table WHERE author_id = {$this->test_author_id}");
		$wpdb->query("DELETE FROM $authors_table WHERE id = {$this->test_author_id}");
		
		parent::tearDown();
	}
	
	public function test_create_feedback() {
		$data = array(
			'author_topic_id' => $this->test_topic_id,
			'action' => 'approved',
			'user_id' => 1,
			'reason' => 'Great topic!',
			'notes' => 'Looking forward to this post'
		);
		
		$id = $this->repository->create($data);
		
		$this->assertIsInt($id);
		$this->assertGreaterThan(0, $id);
	}
	
	public function test_get_by_id() {
		$data = array(
			'author_topic_id' => $this->test_topic_id,
			'action' => 'rejected',
			'user_id' => 1,
			'reason' => 'Not relevant'
		);
		
		$id = $this->repository->create($data);
		$feedback = $this->repository->get_by_id($id);
		
		$this->assertNotNull($feedback);
		$this->assertEquals('rejected', $feedback->action);
		$this->assertEquals('Not relevant', $feedback->reason);
	}
	
	public function test_get_by_topic() {
		$data1 = array(
			'author_topic_id' => $this->test_topic_id,
			'action' => 'approved',
			'user_id' => 1,
			'reason' => 'Good topic'
		);
		
		$data2 = array(
			'author_topic_id' => $this->test_topic_id,
			'action' => 'rejected',
			'user_id' => 1,
			'reason' => 'Changed mind'
		);
		
		$this->repository->create($data1);
		$this->repository->create($data2);
		
		$feedback = $this->repository->get_by_topic($this->test_topic_id);
		
		$this->assertCount(2, $feedback);
	}
	
	public function test_get_by_author() {
		$data = array(
			'author_topic_id' => $this->test_topic_id,
			'action' => 'approved',
			'user_id' => 1,
			'reason' => 'Excellent topic'
		);
		
		$this->repository->create($data);
		
		$feedback = $this->repository->get_by_author($this->test_author_id);
		
		$this->assertGreaterThanOrEqual(1, count($feedback));
		$this->assertEquals('Test Topic', $feedback[0]->topic_title);
	}
	
	public function test_record_approval() {
		$id = $this->repository->record_approval($this->test_topic_id, 1, 'Good content', 'Extra notes');
		
		$this->assertIsInt($id);
		$this->assertGreaterThan(0, $id);
		
		$feedback = $this->repository->get_by_id($id);
		$this->assertEquals('approved', $feedback->action);
		$this->assertEquals('Good content', $feedback->reason);
	}
	
	public function test_record_rejection() {
		$id = $this->repository->record_rejection($this->test_topic_id, 1, 'Not relevant', 'Off topic');
		
		$this->assertIsInt($id);
		$this->assertGreaterThan(0, $id);
		
		$feedback = $this->repository->get_by_id($id);
		$this->assertEquals('rejected', $feedback->action);
		$this->assertEquals('Not relevant', $feedback->reason);
	}
	
	public function test_delete_feedback() {
		$id = $this->repository->record_approval($this->test_topic_id, 1, 'Test');
		
		$result = $this->repository->delete($id);
		$this->assertNotFalse($result);
		
		$feedback = $this->repository->get_by_id($id);
		$this->assertNull($feedback);
	}
	
	public function test_delete_by_topic() {
		$this->repository->record_approval($this->test_topic_id, 1, 'Test 1');
		$this->repository->record_rejection($this->test_topic_id, 1, 'Test 2');
		
		$result = $this->repository->delete_by_topic($this->test_topic_id);
		$this->assertNotFalse($result);
		
		$feedback = $this->repository->get_by_topic($this->test_topic_id);
		$this->assertEmpty($feedback);
	}
	
	public function test_get_statistics() {
		$this->repository->record_approval($this->test_topic_id, 1, 'Approved 1');
		$this->repository->record_approval($this->test_topic_id, 1, 'Approved 2');
		$this->repository->record_rejection($this->test_topic_id, 1, 'Rejected 1');
		
		$stats = $this->repository->get_statistics($this->test_author_id);
		
		$this->assertEquals(3, $stats['total']);
		$this->assertEquals(2, $stats['approved']);
		$this->assertEquals(1, $stats['rejected']);
	}
	
	public function test_feedback_timestamp() {
		$id = $this->repository->record_approval($this->test_topic_id, 1, 'Test');
		$feedback = $this->repository->get_by_id($id);
		
		$this->assertNotEmpty($feedback->created_at);
		$this->assertNotFalse(strtotime($feedback->created_at));
	}
	
	public function test_optional_reason() {
		$id = $this->repository->record_approval($this->test_topic_id, 1, '');
		$feedback = $this->repository->get_by_id($id);
		
		$this->assertIsInt($id);
		$this->assertEquals('', $feedback->reason);
	}
}
