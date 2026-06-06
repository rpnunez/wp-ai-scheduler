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
		
		require_once AIPS_PLUGIN_DIR . 'includes/class-aips-authors-repository.php';
		require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topics-repository.php';
		require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topic-logs-repository.php';
		require_once AIPS_PLUGIN_DIR . 'includes/class-aips-feedback-repository.php';
		require_once AIPS_PLUGIN_DIR . 'includes/class-aips-authors-controller.php';
		require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topics-scheduler.php';
		require_once AIPS_PLUGIN_DIR . 'includes/class-aips-interval-calculator.php';
		
		$this->controller = new AIPS_Authors_Controller();
		$this->topics_repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();
	}
	
	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Capture JSON output produced by a controller AJAX method.
	 *
	 * @param callable $callable Controller method to invoke.
	 * @return array Decoded response array.
	 */
	private function call_ajax( callable $callable ) {
		$_REQUEST = array_merge( $_REQUEST, $_POST );
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected after wp_send_json_*.
		}

		return json_decode( ob_get_clean(), true );
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
	 * Test that posts_generated AJAX payload includes the latest post-generation timestamp.
	 */
	public function test_get_author_topics_includes_post_generated_timestamp() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$authors_repo = new AIPS_Authors_Repository();
		$author_id = $authors_repo->create(array(
			'name' => 'Timestamp Author',
			'field_niche' => 'Technology',
			'is_active' => 1
		));

		$topic_id = $this->topics_repository->create(array(
			'author_id' => $author_id,
			'topic_title' => 'Timestamp Topic',
			'topic_prompt' => 'Test prompt',
			'status' => 'approved'
		));

		$post_id = $this->factory->post->create(array(
			'post_title' => 'Timestamp Post',
			'post_status' => 'publish'
		));

		$this->logs_repository->log_post_generation($topic_id, $post_id);

		$_POST = array(
			'nonce'     => wp_create_nonce( 'aips_ajax_nonce' ),
			'author_id' => $author_id,
			'status'    => 'posts_generated',
		);
		$_REQUEST = $_POST;

		$response = $this->call_ajax( array( $this->controller, 'ajax_get_author_topics' ) );

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, $response['data']['topics'] );
		$this->assertSame( 1, $response['data']['topics'][0]['post_count'] );
		$this->assertNotEmpty( $response['data']['topics'][0]['post_generated_at'] );
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
		if (!class_exists('WP_Error')) {
			$this->markTestSkipped('Requires WP environment.');
		}
		global $wpdb;
		if (property_exists($wpdb, 'get_col_return_val')) {
			$this->markTestSkipped('Database tests cannot run with mocked wpdb.');
		}
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

	/**
	 * Test that generated post stats can be fetched in a single batch query.
	 */
	public function test_get_generated_post_stats_by_topic_ids_returns_counts_and_latest_timestamp() {
		$authors_repo = new AIPS_Authors_Repository();
		$author_id = $authors_repo->create(array(
			'name' => 'Batch Stats Author',
			'field_niche' => 'Engineering',
			'is_active' => 1,
		));

		$topic_id = $this->topics_repository->create(array(
			'author_id' => $author_id,
			'topic_title' => 'Batch Topic',
			'topic_prompt' => 'Batch prompt',
			'status' => 'approved',
		));

		$post_id = $this->factory->post->create(array(
			'post_title' => 'Batch Post',
			'post_status' => 'publish',
		));

		$this->logs_repository->log_post_generation($topic_id, $post_id);
		$this->logs_repository->log_post_generation($topic_id, $post_id);

		$stats = $this->logs_repository->get_generated_post_stats_by_topic_ids(array($topic_id));

		$this->assertArrayHasKey($topic_id, $stats);
		$this->assertSame(2, $stats[$topic_id]['post_count']);
		$this->assertNotEmpty($stats[$topic_id]['post_generated_at']);
	}
}
