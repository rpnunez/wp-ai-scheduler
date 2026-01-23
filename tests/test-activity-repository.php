<?php
/**
 * Tests for Activity Repository
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Activity_Repository extends WP_UnitTestCase {
	
	private $repository;
	
	public function setUp(): void {
		parent::setUp();
		$this->repository = new AIPS_Activity_Repository();
		
		// Ensure table exists
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_activity';
		
		// Clear any existing test data
		$wpdb->query("DELETE FROM {$table_name}");
	}
	
	public function tearDown(): void {
		// Clean up test data
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_activity';
		$wpdb->query("DELETE FROM {$table_name}");
		
		parent::tearDown();
	}
	
	/**
	 * Test creating an activity record.
	 */
	public function test_create_activity() {
		$activity_id = $this->repository->create(array(
			'event_type' => 'post_published',
			'event_status' => 'success',
			'post_id' => 123,
			'template_id' => 1,
			'message' => 'Test post published',
		));
		
		$this->assertIsInt($activity_id);
		$this->assertGreaterThan(0, $activity_id);
	}
	
	/**
	 * Test creating activity with metadata.
	 */
	public function test_create_activity_with_metadata() {
		$metadata = array(
			'post_status' => 'draft',
			'frequency' => 'daily',
		);
		
		$activity_id = $this->repository->create(array(
			'event_type' => 'post_draft',
			'event_status' => 'draft',
			'post_id' => 456,
			'message' => 'Draft saved',
			'metadata' => $metadata,
		));
		
		$this->assertIsInt($activity_id);
		$this->assertGreaterThan(0, $activity_id);
	}
	
	/**
	 * Test retrieving recent activity.
	 */
	public function test_get_recent_activity() {
		// Create multiple activities
		for ($i = 1; $i <= 5; $i++) {
			$this->repository->create(array(
				'event_type' => 'post_published',
				'event_status' => 'success',
				'post_id' => $i,
				'message' => "Post {$i} published",
			));
		}
		
		$activities = $this->repository->get_recent(array('limit' => 10));
		
		$this->assertIsArray($activities);
		$this->assertCount(5, $activities);
		
		// Check ordering (most recent first)
		$this->assertEquals('Post 5 published', $activities[0]->message);
		$this->assertEquals('Post 1 published', $activities[4]->message);
	}
	
	/**
	 * Test filtering activity by type.
	 */
	public function test_get_activity_by_type() {
		// Create different types
		$this->repository->create(array(
			'event_type' => 'post_published',
			'event_status' => 'success',
			'message' => 'Published post',
		));
		
		$this->repository->create(array(
			'event_type' => 'schedule_failed',
			'event_status' => 'failed',
			'message' => 'Schedule failed',
		));
		
		$this->repository->create(array(
			'event_type' => 'post_draft',
			'event_status' => 'draft',
			'message' => 'Draft saved',
		));
		
		$failed = $this->repository->get_by_type('schedule_failed');
		
		$this->assertCount(1, $failed);
		$this->assertEquals('schedule_failed', $failed[0]->event_type);
	}
	
	/**
	 * Test getting failed schedules.
	 */
	public function test_get_failed_schedules() {
		$this->repository->create(array(
			'event_type' => 'schedule_failed',
			'event_status' => 'failed',
			'schedule_id' => 1,
			'message' => 'Schedule 1 failed',
		));
		
		$this->repository->create(array(
			'event_type' => 'schedule_failed',
			'event_status' => 'failed',
			'schedule_id' => 2,
			'message' => 'Schedule 2 failed',
		));
		
		$failed = $this->repository->get_failed_schedules();
		
		$this->assertCount(2, $failed);
	}
	
	/**
	 * Test getting draft posts.
	 */
	public function test_get_draft_posts() {
		$this->repository->create(array(
			'event_type' => 'post_draft',
			'event_status' => 'draft',
			'post_id' => 10,
			'message' => 'Draft 1',
		));
		
		$this->repository->create(array(
			'event_type' => 'post_draft',
			'event_status' => 'draft',
			'post_id' => 11,
			'message' => 'Draft 2',
		));
		
		$drafts = $this->repository->get_draft_posts();
		
		$this->assertCount(2, $drafts);
		$this->assertEquals('draft', $drafts[0]->event_status);
	}
	
	/**
	 * Test activity count.
	 */
	public function test_get_count() {
		// Create test data
		for ($i = 0; $i < 3; $i++) {
			$this->repository->create(array(
				'event_type' => 'post_published',
				'event_status' => 'success',
				'message' => 'Test',
			));
		}
		
		$total_count = $this->repository->get_count();
		$type_count = $this->repository->get_count('post_published');
		
		$this->assertEquals(3, $total_count);
		$this->assertEquals(3, $type_count);
	}
	
	/**
	 * Test deleting old records.
	 */
	public function test_delete_old_records() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'aips_activity';
		
		// Create an old record
		$wpdb->insert($table_name, array(
			'event_type' => 'post_published',
			'event_status' => 'success',
			'message' => 'Old post',
			'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
		));
		
		// Create a recent record
		$this->repository->create(array(
			'event_type' => 'post_published',
			'event_status' => 'success',
			'message' => 'Recent post',
		));
		
		$deleted = $this->repository->delete_old_records(30);
		
		$this->assertGreaterThan(0, $deleted);
		
		$remaining = $this->repository->get_count();
		$this->assertEquals(1, $remaining);
	}
}
