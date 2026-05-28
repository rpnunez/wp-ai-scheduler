<?php
/**
 * Tests for AIPS_Author_Topics_Controller Log Retrieval
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Author_Topics_Logs_Test extends WP_Ajax_UnitTestCase {

	private $controller;
	private $repository;
	private $logs_repository;
	private $admin_user_id;
	private $author_id;
    private $topic_id;

	public function set_up(): void {
		parent::set_up();

		// Create test users
		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));

		// Initialize repositories
		$this->repository = new AIPS_Author_Topics_Repository();
		$this->logs_repository = new AIPS_Author_Topic_Logs_Repository();

		// Initialize controller
		$this->controller = new AIPS_Author_Topics_Controller();

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

	public function tear_down(): void {
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'aips_author_topic_logs',
			array( 'author_topic_id' => $this->topic_id ),
			array( '%d' )
		);
		$wpdb->delete(
			$wpdb->prefix . 'aips_author_topics',
			array( 'id' => $this->topic_id ),
			array( '%d' )
		);
		$wpdb->delete(
			$wpdb->prefix . 'aips_authors',
			array( 'id' => $this->author_id ),
			array( '%d' )
		);

		// Clean up $_POST and $_REQUEST
		$_POST = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	private function capture_ajax_action($action) {
		$this->_last_response = '';
		$buffer_level = ob_get_level();
		ob_start();

		try {
			$this->_handleAjax($action);
		} catch (WPAjaxDieContinueException $e) {
			// Expected.
		} catch (WPAjaxDieStopException $e) {
			// Expected for early exits.
		}

		while (ob_get_level() > $buffer_level) {
			$buffer = ob_get_clean();
			if ('' !== $buffer && false !== $buffer && '' === $this->_last_response) {
				$this->_last_response = $buffer;
			}
		}

		if ('' === $this->_last_response) {
			return null;
		}

		return json_decode(strtok(trim($this->_last_response), "\r\n"), true);
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
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['topic_id'] = $this->topic_id;
		$response = $this->capture_ajax_action('aips_get_topic_logs');

		// Assertions
		$this->assertTrue($response['success']);
		$this->assertCount(2, $response['data']['logs']);

		$actions = array_column($response['data']['logs'], 'action');
		$this->assertContains('edited', $actions);
		$this->assertContains('approved', $actions);
		$this->assertArrayHasKey('user_name', $response['data']['logs'][0]);
	}

    /**
     * Test retrieving logs with invalid topic ID
     */
    public function test_ajax_get_topic_logs_invalid_id() {
        wp_set_current_user($this->admin_user_id);

        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        // Test 0
        $_POST['topic_id'] = 0;
		$response = $this->capture_ajax_action('aips_get_topic_logs');

        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid topic ID.', $response['data']['message']);
    }

    /**
     * Test empty logs
     */
    public function test_ajax_get_topic_logs_empty() {
        wp_set_current_user($this->admin_user_id);

        $_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['topic_id'] = $this->topic_id; // No logs added
		$response = $this->capture_ajax_action('aips_get_topic_logs');

        $this->assertTrue($response['success']);
        $this->assertEmpty($response['data']['logs']);
    }
}
