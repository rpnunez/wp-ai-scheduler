<?php
/**
 * Tests for Generation Queue functionality
 *
 * @package AI_Post_Scheduler
 */

class Test_Generation_Queue extends WP_UnitTestCase {

	private $topics_repository;
	private $authors_repository;

	public function setUp(): void {
		parent::setUp();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test get_all_approved_for_queue method returns approved topics with author info
	 */
	public function test_get_all_approved_for_queue_returns_approved_topics() {
		// Create a test author
		$author_id = $this->authors_repository->create(array(
			'name' => 'Test Author',
			'field_niche' => 'Testing',
			'is_active' => 1,
		));

		$this->assertNotFalse($author_id, 'Failed to create test author');

		// Create some topics with different statuses
		$approved_topic_id = $this->topics_repository->create(array(
			'author_id' => $author_id,
			'topic_title' => 'Approved Topic',
			'status' => 'approved',
			'reviewed_at' => current_time('mysql'),
		));

		$pending_topic_id = $this->topics_repository->create(array(
			'author_id' => $author_id,
			'topic_title' => 'Pending Topic',
			'status' => 'pending',
		));

		$rejected_topic_id = $this->topics_repository->create(array(
			'author_id' => $author_id,
			'topic_title' => 'Rejected Topic',
			'status' => 'rejected',
		));

		// Get all approved topics for queue
		$queue_topics = $this->topics_repository->get_all_approved_for_queue();

		// Should return at least one approved topic
		$this->assertNotEmpty($queue_topics, 'Queue should contain approved topics');

		// Find our test topic in the results
		$found = false;
		foreach ($queue_topics as $topic) {
			if ($topic->id == $approved_topic_id) {
				$found = true;
				$this->assertEquals('Approved Topic', $topic->topic_title);
				$this->assertEquals('Test Author', $topic->author_name);
				$this->assertEquals('Testing', $topic->field_niche);
				break;
			}
		}

		$this->assertTrue($found, 'Approved topic should be in queue');

		// Verify pending and rejected topics are not in queue
		foreach ($queue_topics as $topic) {
			$this->assertNotEquals($pending_topic_id, $topic->id, 'Pending topic should not be in queue');
			$this->assertNotEquals($rejected_topic_id, $topic->id, 'Rejected topic should not be in queue');
		}
	}

	/**
	 * Test queue returns topics ordered by reviewed_at (FIFO)
	 */
	public function test_queue_topics_ordered_by_reviewed_at() {
		// Create a test author
		$author_id = $this->authors_repository->create(array(
			'name' => 'Queue Test Author',
			'field_niche' => 'Order Testing',
			'is_active' => 1,
		));

		$this->assertNotFalse($author_id, 'Failed to create test author');

		// Create topics approved at different times
		$first_topic_id = $this->topics_repository->create(array(
			'author_id' => $author_id,
			'topic_title' => 'First Approved',
			'status' => 'approved',
			'reviewed_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
		));

		$second_topic_id = $this->topics_repository->create(array(
			'author_id' => $author_id,
			'topic_title' => 'Second Approved',
			'status' => 'approved',
			'reviewed_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
		));

		// Get queue topics
		$queue_topics = $this->topics_repository->get_all_approved_for_queue();

		// Find positions of our test topics
		$first_position = -1;
		$second_position = -1;

		foreach ($queue_topics as $index => $topic) {
			if ($topic->id == $first_topic_id) {
				$first_position = $index;
			}
			if ($topic->id == $second_topic_id) {
				$second_position = $index;
			}
		}

		// Verify first topic comes before second topic
		$this->assertNotEquals(-1, $first_position, 'First topic should be in queue');
		$this->assertNotEquals(-1, $second_position, 'Second topic should be in queue');
		$this->assertLessThan($second_position, $first_position, 'First approved topic should come before second in FIFO order');
	}
}
