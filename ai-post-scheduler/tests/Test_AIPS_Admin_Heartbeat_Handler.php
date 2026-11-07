<?php
/**
 * Unit Tests for AIPS_Admin_Heartbeat_Handler
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Heartbeat_Handler extends WP_UnitTestCase {

	/**
	 * Setup user context for testing.
	 */
	public function setUp(): void {
		parent::setUp();
		$admin_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_id);
	}

	/**
	 * Test handle_heartbeat ignores unauthorized requests.
	 */
	public function test_handle_heartbeat_unauthorized() {
		$subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);

		$data = array(
			'aips_link_report_backfill' => true,
			'aips_queue_monitor'        => true,
		);

		$response = AIPS_Admin_Heartbeat_Handler::handle_heartbeat(array(), $data);
		$this->assertEmpty($response);
	}

	/**
	 * Test handle_heartbeat responds to queue monitor request.
	 */
	public function test_handle_heartbeat_queue_monitor() {
		$data = array(
			'aips_queue_monitor' => true,
		);

		$response = AIPS_Admin_Heartbeat_Handler::handle_heartbeat(array(), $data);

		$this->assertArrayHasKey('aips_queue_monitor', $response);
		$this->assertArrayHasKey('in_flight', $response['aips_queue_monitor']);
		$this->assertArrayHasKey('queue_length', $response['aips_queue_monitor']);
		$this->assertArrayHasKey('timestamp', $response['aips_queue_monitor']);
	}

	/**
	 * Test handle_heartbeat responds to cooldown monitor request.
	 */
	public function test_handle_heartbeat_cooldown_monitor() {
		update_option('aips_indexer_cooldown_until', time() + 300);

		$data = array(
			'aips_cooldown_monitor' => true,
		);

		$response = AIPS_Admin_Heartbeat_Handler::handle_heartbeat(array(), $data);

		$this->assertArrayHasKey('aips_cooldown_monitor', $response);
		$this->assertTrue($response['aips_cooldown_monitor']['is_active']);
		$this->assertGreaterThan(0, $response['aips_cooldown_monitor']['remaining_seconds']);
	}

	/**
	 * Test get_link_report_backfill_data returns array.
	 */
	public function test_get_link_report_backfill_data() {
		$result = AIPS_Admin_Heartbeat_Handler::get_link_report_backfill_data();
		$this->assertIsArray($result);
	}
}
