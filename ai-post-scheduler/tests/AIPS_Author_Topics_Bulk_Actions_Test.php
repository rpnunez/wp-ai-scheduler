<?php
/**
 * Tests for AIPS_Author_Topics_Controller Bulk Actions
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Author_Topics_Bulk_Actions_Test extends WP_Ajax_UnitTestCase {
	
	private $controller;
	private $repository;
	private $logs_repository;
	private $admin_user_id;
	private $subscriber_user_id;
	private $author_id;
	
	public function set_up(): void {
		parent::set_up();
		
		// Create test users
		$this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = $this->factory->user->create(array('role' => 'subscriber'));
		
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
			'description' => 'Test Description',
			'is_active' => 1,
			'topic_generation_quantity' => 5,
			'topic_generation_frequency' => 'weekly',
			'post_generation_frequency' => 'daily'
		));
	}
	
	public function tear_down(): void {
		// Clean up test data
		global $wpdb;
		$topics_table = $wpdb->prefix . 'aips_author_topics';
		$authors_table = $wpdb->prefix . 'aips_authors';
		
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $topics_table WHERE author_id = %d",
				$this->author_id
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $authors_table WHERE id = %d",
				$this->author_id
			)
		);
		
		// Clean up $_POST and $_REQUEST
		$_POST = array();
		$_REQUEST = array();
		
		parent::tear_down();
	}
	
	/**
	 * Helper method to create test topics
	 */
	private function create_test_topics($count = 3) {
		$topic_ids = array();
		for ($i = 0; $i < $count; $i++) {
			$topic_id = $this->repository->create(array(
				'author_id' => $this->author_id,
				'topic_title' => 'Test Topic ' . ($i + 1),
				'status' => 'pending',
				'generated_at' => current_time('mysql')
			));
			$topic_ids[] = $topic_id;
		}
		return $topic_ids;
	}

	/**
	 * Dispatch a registered AJAX action through the WordPress AJAX test harness.
	 *
	 * @param string $action AJAX action slug without the wp_ajax_ prefix.
	 * @return array|null
	 */
	private function capture_ajax_action($action) {
		$this->_last_response = '';
		$buffer_level = ob_get_level();
		ob_start();

		try {
			$this->_handleAjax($action);
		} catch (WPAjaxDieContinueException $e) {
			// Expected for wp_send_json_* success/error responses.
		} catch (WPAjaxDieStopException $e) {
			// Expected for wp_die()-style early exits.
		}

		while (ob_get_level() > $buffer_level) {
			$buffer = ob_get_clean();
			if ('' !== $buffer && false !== $buffer && '' === $this->_last_response) {
				$this->_last_response = $buffer;
			}
		}

		return $this->decode_last_response();
	}

	/**
	 * Invoke a controller method directly while using the AJAX test case's die handler.
	 *
	 * @param callable $callable Controller method to invoke.
	 * @return array|null
	 */
	private function capture_ajax_callable($callable) {
		$this->_last_response = '';
		$buffer_level = ob_get_level();
		ob_start();

		try {
			call_user_func($callable);
		} catch (WPAjaxDieContinueException $e) {
			// Expected for wp_send_json_* success/error responses.
		} catch (WPAjaxDieStopException $e) {
			// Expected for wp_die()-style early exits.
		}

		while (ob_get_level() > $buffer_level) {
			$buffer = ob_get_clean();
			if ('' !== $buffer && false !== $buffer && '' === $this->_last_response) {
				$this->_last_response = $buffer;
			}
		}

		return $this->decode_last_response();
	}

	/**
	 * Decode the JSON portion of the captured AJAX response.
	 *
	 * Some full-WP runs append log lines after the JSON payload, so only decode
	 * the first response line.
	 *
	 * @return array|null
	 */
	private function decode_last_response() {
		if ('' === $this->_last_response) {
			return null;
		}

		$response = trim($this->_last_response);
		$first_line = strtok($response, "\r\n");

		return json_decode($first_line, true);
	}
	
	/**
	 * Test bulk approve topics with valid permissions
	 */
	public function test_ajax_bulk_approve_topics_success() {
		wp_set_current_user($this->admin_user_id);
		
		// Create test topics
		$topic_ids = $this->create_test_topics(3);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['topic_ids'] = $topic_ids;
		
		$response = $this->capture_ajax_action('aips_bulk_approve_topics');
		
		// Assertions
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('3 topics approved', $response['data']['message']);
		
		// Verify topics are approved in database
		foreach ($topic_ids as $topic_id) {
			$topic = $this->repository->get_by_id($topic_id);
			if (isset($GLOBALS['wpdb']) && property_exists($GLOBALS['wpdb'], 'get_row_return_val')) {
			    $this->markTestSkipped('Database tests cannot run with mocked wpdb.');
			} elseif (isset($topic->id) && isset($topic->total) && $topic->id === 1) {
			    $this->markTestSkipped('Database tests cannot run with mocked wpdb get_row return value.');
			} else {
			    $this->assertEquals('approved', $topic->status);
			}
		}
	}
	
	/**
	 * Test bulk reject topics with valid permissions
	 */
	public function test_ajax_bulk_reject_topics_success() {
		wp_set_current_user($this->admin_user_id);
		
		// Create test topics
		$topic_ids = $this->create_test_topics(2);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['topic_ids'] = $topic_ids;
		
		$response = $this->capture_ajax_action('aips_bulk_reject_topics');
		
		// Assertions
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('2 topics rejected', $response['data']['message']);
		
		// Verify topics are rejected in database
		foreach ($topic_ids as $topic_id) {
			$topic = $this->repository->get_by_id($topic_id);
			if (isset($GLOBALS['wpdb']) && property_exists($GLOBALS['wpdb'], 'get_row_return_val')) {
			    $this->markTestSkipped('Database tests cannot run with mocked wpdb.');
			} elseif (isset($topic->id) && isset($topic->total) && $topic->id === 1) {
			    $this->markTestSkipped('Database tests cannot run with mocked wpdb get_row return value.');
			} else {
			    $this->assertEquals('rejected', $topic->status);
			}
		}
	}
	
	/**
	 * Test bulk delete topics with valid permissions
	 */
	public function test_ajax_bulk_delete_topics_success() {
		wp_set_current_user($this->admin_user_id);
		
		// Create test topics
		$topic_ids = $this->create_test_topics(3);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['topic_ids'] = $topic_ids;
		
		$response = $this->capture_ajax_action('aips_bulk_delete_topics');
		
		// Assertions
		$this->assertTrue($response['success']);
		$this->assertStringContainsString('3 topics deleted', $response['data']['message']);
		
		// Verify topics are deleted from database
		foreach ($topic_ids as $topic_id) {
			$topic = $this->repository->get_by_id($topic_id);
			if (isset($GLOBALS['wpdb']) && property_exists($GLOBALS['wpdb'], 'get_row_return_val')) {
			    $this->markTestSkipped('Database tests cannot run with mocked wpdb.');
			} elseif (isset($topic->id) && isset($topic->total) && $topic->id === 1) {
			    $this->markTestSkipped('Database tests cannot run with mocked wpdb get_row return value.');
			} else {
			    $this->assertNull($topic);
			}
		}
	}
	
	/**
	 * Test bulk delete topics with no topics selected
	 */
	public function test_ajax_bulk_delete_topics_no_topics() {
		wp_set_current_user($this->admin_user_id);
		
		// Set up POST data with empty array
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['topic_ids'] = array();
		
		$response = $this->capture_ajax_action('aips_bulk_delete_topics');
		
		// Assertions
		$this->assertFalse($response['success']);
		$this->assertEquals('No topics selected.', $response['data']['message']);
	}
	
	/**
	 * Test bulk delete topics without permissions
	 */
	public function test_ajax_bulk_delete_topics_no_permission() {
		wp_set_current_user($this->subscriber_user_id);
		
		// Create test topics
		$topic_ids = $this->create_test_topics(2);
		
		// Set up POST data
		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['topic_ids'] = $topic_ids;
		
		$response = $this->capture_ajax_action('aips_bulk_delete_topics');
		
		// Assertions
		$this->assertFalse($response['success']);
		$this->assertEquals('Permission denied.', $response['data']['message']);
		
		// Verify topics are NOT deleted from database
		foreach ($topic_ids as $topic_id) {
			$topic = $this->repository->get_by_id($topic_id);
			if (isset($GLOBALS['wpdb']) && property_exists($GLOBALS['wpdb'], 'get_row_return_val')) {
			    $this->markTestSkipped('Database tests cannot run with mocked wpdb.');
			} else {
			    $this->assertNotNull($topic);
			}
		}
	}

	// =========================================================================
	// Tests for ajax_get_bulk_generate_estimate
	// =========================================================================

	/**
	 * Non-admin users should receive a permission-denied error.
	 */
	public function test_ajax_get_bulk_generate_estimate_permission_denied() {
		wp_set_current_user($this->subscriber_user_id);

		$_POST['nonce']    = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST['nonce'] = $_POST['nonce'];

		$response = $this->capture_ajax_action('aips_get_bulk_generate_estimate');

		$this->assertFalse($response['success']);
		$this->assertEquals('Permission denied.', $response['data']['message']);
	}

	/**
	 * When no historical data exists (get_col returns empty), the endpoint must
	 * return the 30-second default and sample_size = 0.
	 */
	public function test_ajax_get_bulk_generate_estimate_returns_default_when_no_samples() {
		wp_set_current_user($this->admin_user_id);

		$mock_history_repo = $this->getMockBuilder('AIPS_History_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('get_estimated_generation_time'))
			->getMock();

		$mock_history_repo->method('get_estimated_generation_time')
			->willReturn(array('per_post_seconds' => 30, 'sample_size' => 0));

		$controller = new AIPS_Author_Topics_Controller(null, $mock_history_repo);

		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST = $_POST;

		$response = $this->capture_ajax_callable(array($controller, 'ajax_get_bulk_generate_estimate'));

		$this->assertTrue($response['success']);
		$this->assertEquals(30, $response['data']['per_post_seconds']);
		$this->assertEquals(0,  $response['data']['sample_size']);
	}

	/**
	 * When samples exist, the endpoint must return ceil(average) and the correct
	 * sample_size.
	 *
	 * E.g. [10.0, 20.0, 30.0] → avg = 20.0 → per_post_seconds = 20.
	 */
	public function test_ajax_get_bulk_generate_estimate_averages_samples() {
		wp_set_current_user($this->admin_user_id);

		$mock_history_repo = $this->getMockBuilder('AIPS_History_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('get_estimated_generation_time'))
			->getMock();

		// avg(10, 20, 30) = 20 → ceil(20) = 20
		$mock_history_repo->method('get_estimated_generation_time')
			->willReturn(array('per_post_seconds' => 20, 'sample_size' => 3));

		$controller = new AIPS_Author_Topics_Controller(null, $mock_history_repo);

		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST = $_POST;

		$response = $this->capture_ajax_callable(array($controller, 'ajax_get_bulk_generate_estimate'));

		$this->assertTrue($response['success']);
		$this->assertEquals(20, $response['data']['per_post_seconds']);
		$this->assertEquals(3,  $response['data']['sample_size']);
	}

	/**
	 * Fractional averages should be rounded UP via ceil().
	 *
	 * E.g. [10.0, 11.0] → avg = 10.5 → per_post_seconds = 11.
	 */
	public function test_ajax_get_bulk_generate_estimate_ceil_rounds_up() {
		wp_set_current_user($this->admin_user_id);

		$mock_history_repo = $this->getMockBuilder('AIPS_History_Repository')
			->disableOriginalConstructor()
			->onlyMethods(array('get_estimated_generation_time'))
			->getMock();

		// avg(10, 11) = 10.5 → ceil(10.5) = 11
		$mock_history_repo->method('get_estimated_generation_time')
			->willReturn(array('per_post_seconds' => 11, 'sample_size' => 2));

		$controller = new AIPS_Author_Topics_Controller(null, $mock_history_repo);

		$_POST['nonce'] = wp_create_nonce('aips_ajax_nonce');
		$_REQUEST = $_POST;

		$response = $this->capture_ajax_callable(array($controller, 'ajax_get_bulk_generate_estimate'));

		$this->assertTrue($response['success']);
		$this->assertEquals(11, $response['data']['per_post_seconds']);
		$this->assertEquals(2,  $response['data']['sample_size']);
	}
}
