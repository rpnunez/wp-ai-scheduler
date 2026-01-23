<?php
/**
 * Tests for AIPS_Author_Topics_Controller Log Retrieval
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Author_Topics_Logs_Test extends WP_UnitTestCase {

	private $controller;
	private $repository;
	private $logs_repository;
	private $admin_user_id;
	private $author_id;
    private $topic_id;

	public function setUp(): void {
		parent::setUp();

		// Create test users
		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));

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
            'is_active' => 1
		));

        // Create a test topic
        $this->topic_id = $this->repository->create(array(
            'author_id' => $this->author_id,
            'topic_title' => 'Test Topic',
            'status' => 'pending'
        ));
	}

	public function tearDown(): void {
		global $wpdb;
        // Clean up
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aips_author_topic_logs");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aips_author_topics");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aips_authors");

		// Clean up $_POST and $_REQUEST
		$_POST = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Test retrieving logs for a topic
	 */
	public function test_ajax_get_topic_logs_success() {
		wp_set_current_user($this->admin_user_id);

		// Create some logs
        $this->logs_repository->log_edit($this->topic_id, $this->admin_user_id, 'Log 1');
        // Sleep slightly to ensure timestamp difference if ordering depends on it (though usually safe in same sec if ID ordered)
        sleep(1);
        $this->logs_repository->log_approval($this->topic_id, $this->admin_user_id);

		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_POST['topic_id'] = $this->topic_id;

		// Capture output
		ob_start();
		try {
			$this->controller->ajax_get_topic_logs();
		} catch (WPAjaxDieContinueException $e) {
			// Expected exception
		}
		$output = ob_get_clean();

		// Parse JSON response
		$response = json_decode($output, true);

		// Assertions
		$this->assertTrue($response['success']);
		$this->assertCount(2, $response['data']['logs']);

        // Check log structure
        // Assuming logs are returned in DESC order (latest first)
        $first_log = $response['data']['logs'][0];
        $this->assertArrayHasKey('action', $first_log);
        $this->assertArrayHasKey('user_name', $first_log);
        $this->assertEquals('topic_approved', $first_log['action']);
	}

    /**
     * Test retrieving logs with invalid topic ID
     */
    public function test_ajax_get_topic_logs_invalid_id() {
        wp_set_current_user($this->admin_user_id);

        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');

        // Test 0
        $_POST['topic_id'] = 0;

        ob_start();
		try {
			$this->controller->ajax_get_topic_logs();
		} catch (WPAjaxDieContinueException $e) { }
		$output = ob_get_clean();
		$response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid topic ID.', $response['data']['message']);
    }

    /**
     * Test empty logs
     */
    public function test_ajax_get_topic_logs_empty() {
        wp_set_current_user($this->admin_user_id);

        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_POST['topic_id'] = $this->topic_id; // No logs added

        ob_start();
		try {
			$this->controller->ajax_get_topic_logs();
		} catch (WPAjaxDieContinueException $e) { }
		$output = ob_get_clean();
		$response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertEmpty($response['data']['logs']);
    }
}
