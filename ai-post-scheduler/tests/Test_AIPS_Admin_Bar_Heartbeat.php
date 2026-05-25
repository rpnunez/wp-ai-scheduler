<?php
/**
 * Test AIPS_Admin_Bar heartbeat behavior
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Bar_Heartbeat extends WP_UnitTestCase {

	/**
	 * @var AIPS_Admin_Bar
	 */
	private $admin_bar;

	/**
	 * @var AIPS_Notifications_Repository
	 */
	private $repository;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
		$this->admin_bar  = new AIPS_Admin_Bar();
		$this->repository = new AIPS_Notifications_Repository();

		// Clean up any existing notifications
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_notifications");

		// Clear cache
		AIPS_Cache_Factory::instance()->flush();

		// Set up a user with manage_options capability
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_notifications");
		AIPS_Cache_Factory::instance()->flush();
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	/**
	 * Test that heartbeat_received returns unchanged response if user lacks capabilities.
	 */
	public function test_heartbeat_received_no_capability() {
		// Set user without manage_options
		$user_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($user_id);

		$response = array('existing' => 'data');
		$data     = array('aips_check_notifications' => true);

		$result = $this->admin_bar->heartbeat_received($response, $data);

		$this->assertSame($response, $result);
	}

	/**
	 * Test that heartbeat_received returns unchanged response if check flag is not set.
	 */
	public function test_heartbeat_received_flag_not_set() {
		$response = array('existing' => 'data');
		$data     = array();

		$result = $this->admin_bar->heartbeat_received($response, $data);

		$this->assertSame($response, $result);
	}

	/**
	 * Test that heartbeat_received appends notifications and updates cache.
	 */
	public function test_heartbeat_received_appends_notifications_and_caches() {
		// Create a mock notification
		$notif_id = $this->repository->create(
			'test_notification',
			'Test notification message',
			'https://example.com'
		);

		$this->assertGreaterThan(0, $notif_id);

		$response = array('existing' => 'data');
		$data     = array('aips_check_notifications' => true);

		$result = $this->admin_bar->heartbeat_received($response, $data);

		$this->assertArrayHasKey('aips_notifications', $result);
		$this->assertSame(1, $result['aips_notifications']['unread_count']);
		$this->assertCount(1, $result['aips_notifications']['items']);

		$item = $result['aips_notifications']['items'][0];
		$this->assertSame($notif_id, $item['id']);
		$this->assertSame('test_notification', $item['type']);
		$this->assertSame('Test notification message', $item['message']);
		$this->assertSame('https://example.com/', $item['url']); // WP trailing slashes URL

		// Verify cache was updated
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		$cache     = AIPS_Cache_Factory::instance();
		$this->assertTrue($cache->has($cache_key, 'aips_admin_bar'));
		$this->assertSame(1, $cache->get($cache_key, 'aips_admin_bar'));
	}
}
