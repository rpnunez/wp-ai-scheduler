<?php
/**
 * Tests for Post Review Email Notifications
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Review_Notifications extends WP_UnitTestCase {
	
	private $notifications;
	private $repository;
	private $test_post_ids = array();
	private $test_history_ids = array();
	
	public function setUp(): void {
		parent::setUp();
		$this->notifications = new AIPS_Post_Review_Notifications();
		$this->repository = new AIPS_Post_Review_Repository();
	}
	
	public function tearDown(): void {
		// Clean up test posts
		foreach ($this->test_post_ids as $post_id) {
			wp_delete_post($post_id, true);
		}
		
		// Clean up test history
		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		foreach ($this->test_history_ids as $history_id) {
			$wpdb->delete($history_table, array('id' => $history_id), array('%d'));
		}
		
		// Clean up test options
		delete_option('aips_review_notifications_enabled');
		delete_option('aips_review_notifications_email');
		
		parent::tearDown();
	}
	
	/**
	 * Helper method to create a test post with history.
	 */
	private function create_test_post_with_history($post_status = 'draft', $template_id = 1) {
		// Create a draft post
		$post_id = wp_insert_post(array(
			'post_title' => 'Test Draft Post ' . uniqid(),
			'post_content' => 'Test content',
			'post_status' => $post_status,
			'post_type' => 'post',
		));
		
		$this->test_post_ids[] = $post_id;
		
		// Create history record
		global $wpdb;
		$history_table = $wpdb->prefix . 'aips_history';
		$wpdb->insert($history_table, array(
			'post_id' => $post_id,
			'template_id' => $template_id,
			'status' => 'completed',
			'generated_title' => 'Test Generated Title',
			'generated_content' => 'Test generated content',
			'created_at' => current_time('mysql'),
			'completed_at' => current_time('mysql'),
		));
		
		$history_id = $wpdb->insert_id;
		$this->test_history_ids[] = $history_id;
		
		return array('post_id' => $post_id, 'history_id' => $history_id);
	}
	
	/**
	 * Test that notifications are not sent when disabled.
	 */
	public function test_notifications_not_sent_when_disabled() {
		update_option('aips_review_notifications_enabled', 0);
		update_option('aips_review_notifications_email', 'test@example.com');
		
		// Create draft posts
		$this->create_test_post_with_history('draft', 1);
		
		// Reset email log
		$GLOBALS['phpmailer']->mock_sent = array();
		
		// Call the notification method
		$this->notifications->send_review_notification_email();
		
		// Should not send email when disabled
		$this->assertEmpty($GLOBALS['phpmailer']->mock_sent);
	}
	
	/**
	 * Test that notifications are not sent when no draft posts exist.
	 */
	public function test_notifications_not_sent_when_no_drafts() {
		update_option('aips_review_notifications_enabled', 1);
		update_option('aips_review_notifications_email', 'test@example.com');
		
		// No draft posts created
		
		// Reset email log
		$GLOBALS['phpmailer']->mock_sent = array();
		
		// Call the notification method
		$this->notifications->send_review_notification_email();
		
		// Should not send email when no drafts
		$this->assertEmpty($GLOBALS['phpmailer']->mock_sent);
	}
	
	/**
	 * Test that email message is built correctly.
	 */
	public function test_email_message_format() {
		// Create draft posts
		$this->create_test_post_with_history('draft', 1);
		$this->create_test_post_with_history('draft', 1);
		
		$draft_posts = $this->repository->get_draft_posts(array(
			'per_page' => 10,
			'page' => 1,
		));
		
		$draft_count = $this->repository->get_draft_count();
		
		// Use reflection to call private method
		$reflection = new ReflectionClass($this->notifications);
		$method = $reflection->getMethod('build_email_message');
		$method->setAccessible(true);
		
		$message = $method->invoke($this->notifications, $draft_posts, $draft_count);
		
		// Verify message contains expected content
		$this->assertStringContainsString('Posts Awaiting Review', $message);
		// Check for the correct plural/singular form based on count
		if ($draft_count === 1) {
			$this->assertStringContainsString('Post Awaiting Review', $message);
		} else {
			$this->assertStringContainsString('Posts Awaiting Review', $message);
		}
		$this->assertStringContainsString('Review Posts', $message);
		$this->assertStringContainsString(admin_url('admin.php?page=aips-generated-posts#aips-pending-review'), $message);
	}
	
	/**
	 * Test that cron job is scheduled correctly.
	 */
	public function test_cron_job_scheduling() {
		// Clear any existing schedule
		wp_clear_scheduled_hook('aips_send_review_notifications');
		
		// Schedule the job manually (simulating plugin activation)
		wp_schedule_event(time(), 'daily', 'aips_send_review_notifications');
		
		// Verify it's scheduled
		$timestamp = wp_next_scheduled('aips_send_review_notifications');
		$this->assertNotFalse($timestamp);
		$this->assertGreaterThanOrEqual(time(), $timestamp);
		
		// Clean up
		wp_clear_scheduled_hook('aips_send_review_notifications');
	}
	
	/**
	 * Test that cron job is cleared correctly.
	 */
	public function test_cron_job_clearing() {
		// Schedule the job first (simulating plugin activation)
		wp_schedule_event(time(), 'daily', 'aips_send_review_notifications');
		
		// Verify it's scheduled
		$this->assertNotFalse(wp_next_scheduled('aips_send_review_notifications'));
		
		// Clear it (simulating plugin deactivation)
		wp_clear_scheduled_hook('aips_send_review_notifications');
		
		// Verify it's cleared
		$this->assertFalse(wp_next_scheduled('aips_send_review_notifications'));
	}
	
	/**
	 * Test that invalid email address prevents sending.
	 */
	public function test_invalid_email_prevents_sending() {
		update_option('aips_review_notifications_enabled', 1);
		update_option('aips_review_notifications_email', 'not-an-email');
		
		// Create draft posts
		$this->create_test_post_with_history('draft', 1);
		
		// Reset email log
		$GLOBALS['phpmailer']->mock_sent = array();
		
		// Call the notification method
		$this->notifications->send_review_notification_email();
		
		// Should not send email with invalid address
		$this->assertEmpty($GLOBALS['phpmailer']->mock_sent);
	}
}
