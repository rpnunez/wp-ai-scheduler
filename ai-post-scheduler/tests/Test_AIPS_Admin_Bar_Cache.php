<?php
/**
 * Test AIPS_Admin_Bar cache behavior
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Bar_Cache extends WP_UnitTestCase {

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
		// Clean up
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_notifications");
		AIPS_Cache_Factory::instance()->flush();
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	/**
	 * Test that cache is set after add_toolbar_node executes
	 */
	public function test_cache_set_with_ttl() {
		// Create a mock WP_Admin_Bar
		$wp_admin_bar = $this->getMockBuilder('WP_Admin_Bar')
			->disableOriginalConstructor()
			->onlyMethods(array('add_node', 'add_group'))
			->getMock();

		$cache     = AIPS_Cache_Factory::instance();
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		$cache->delete($cache_key, 'aips_admin_bar');

		// Call add_toolbar_node which should set cache
		$this->admin_bar->add_toolbar_node($wp_admin_bar);

		// Verify cache was set
		$this->assertTrue($cache->has($cache_key, 'aips_admin_bar'), 'Cache should be set after add_toolbar_node');
		$this->assertSame(0, $cache->get($cache_key, 'aips_admin_bar'), 'Cache value should be 0 when no notifications');
	}

	/**
	 * Test that get_unread() is not called when count is 0
	 */
	public function test_get_unread_skipped_when_count_zero() {
		// Create a partial mock of AIPS_Notifications_Repository
		$mock_repo = $this->getMockBuilder('AIPS_Notifications_Repository')
			->onlyMethods(array('count_unread', 'get_unread'))
			->getMock();

		// Expect count_unread to be called and return 0
		$mock_repo->expects($this->once())
			->method('count_unread')
			->willReturn(0);

		// Expect get_unread to NEVER be called when count is 0
		$mock_repo->expects($this->never())
			->method('get_unread');

		// Create admin bar instance with mocked repository
		$reflection = new ReflectionClass('AIPS_Admin_Bar');
		$property   = $reflection->getProperty('repository');
		$property->setAccessible(true);

		$admin_bar = new AIPS_Admin_Bar();
		$property->setValue($admin_bar, $mock_repo);

		// Clear cache to force repository call
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		AIPS_Cache_Factory::instance()->delete($cache_key, 'aips_admin_bar');

		// Create a mock WP_Admin_Bar
		$wp_admin_bar = $this->getMockBuilder('WP_Admin_Bar')
			->disableOriginalConstructor()
			->onlyMethods(array('add_node', 'add_group'))
			->getMock();
		$wp_admin_bar->method('add_node')->willReturn(true);
		$wp_admin_bar->method('add_group')->willReturn(true);

		// Call add_toolbar_node
		$admin_bar->add_toolbar_node($wp_admin_bar);
	}

	/**
	 * Test that get_unread() IS called when count > 0
	 */
	public function test_get_unread_called_when_count_positive() {
		// Create a partial mock of AIPS_Notifications_Repository
		$mock_repo = $this->getMockBuilder('AIPS_Notifications_Repository')
			->onlyMethods(array('count_unread', 'get_unread'))
			->getMock();

		// Expect count_unread to be called and return 5
		$mock_repo->expects($this->once())
			->method('count_unread')
			->willReturn(5);

		// Expect get_unread to be called exactly once with limit 20
		$mock_repo->expects($this->once())
			->method('get_unread')
			->with(20)
			->willReturn(array());

		// Create admin bar instance with mocked repository
		$reflection = new ReflectionClass('AIPS_Admin_Bar');
		$property   = $reflection->getProperty('repository');
		$property->setAccessible(true);

		$admin_bar = new AIPS_Admin_Bar();
		$property->setValue($admin_bar, $mock_repo);

		// Clear cache to force repository call
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		AIPS_Cache_Factory::instance()->delete($cache_key, 'aips_admin_bar');

		// Create a mock WP_Admin_Bar
		$wp_admin_bar = $this->getMockBuilder('WP_Admin_Bar')
			->disableOriginalConstructor()
			->onlyMethods(array('add_node', 'add_group'))
			->getMock();
		$wp_admin_bar->method('add_node')->willReturn(true);
		$wp_admin_bar->method('add_group')->willReturn(true);

		// Call add_toolbar_node
		$admin_bar->add_toolbar_node($wp_admin_bar);
	}

	/**
	 * Test cache is updated after ajax_mark_read
	 */
	public function test_cache_updated_on_mark_read() {
		// Skip if we can't test AJAX properly
		if (!function_exists('wp_send_json_success')) {
			$this->markTestSkipped('AJAX functions not available');
		}

		// Create a notification
		$notif_id = $this->repository->create(
			'test_notification',
			'Test notification'
		);

		$this->assertGreaterThan(0, $notif_id, 'Notification should be created');

		// Seed cache with stale value
		$cache     = AIPS_Cache_Factory::instance();
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		$cache->set($cache_key, 1, MINUTE_IN_SECONDS, 'aips_admin_bar');

		$this->assertSame(1, $cache->get($cache_key, 'aips_admin_bar'), 'Cache should have stale value');

		// Declare that this test expects JSON output from wp_send_json_success.
		$this->expectOutputRegex('/.*/');

		// Set up AJAX request
		$orig_post    = $_POST;
		$orig_request = $_REQUEST;

		try {
			$_POST['id']       = $notif_id;
			$_POST['nonce']    = wp_create_nonce('aips_admin_bar_nonce');
			$_REQUEST['nonce'] = wp_create_nonce('aips_admin_bar_nonce');

			// Call ajax_mark_read and catch the die exception
			try {
				$this->admin_bar->ajax_mark_read();
				$this->fail('Expected WPAjaxDieContinueException');
			} catch (WPAjaxDieContinueException $e) {
				// Expected - AJAX handlers call wp_die
			}

			// Verify cache now holds the fresh count (0 after marking the only notification read)
			$this->assertTrue($cache->has($cache_key, 'aips_admin_bar'), 'Cache should be repopulated after mark_read');
			$this->assertSame(0, $cache->get($cache_key, 'aips_admin_bar'), 'Cache value should reflect new unread count');
		} finally {
			$_POST    = $orig_post;
			$_REQUEST = $orig_request;
		}
	}

	/**
	 * Test cache is updated after ajax_mark_all_read
	 */
	public function test_cache_updated_on_mark_all_read() {
		// Skip if we can't test AJAX properly
		if (!function_exists('wp_send_json_success')) {
			$this->markTestSkipped('AJAX functions not available');
		}

		// Create notifications
		$this->repository->create('test_notification', 'Test notification 1');
		$this->repository->create('test_notification', 'Test notification 2');

		// Seed cache with stale value
		$cache     = AIPS_Cache_Factory::instance();
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		$cache->set($cache_key, 2, MINUTE_IN_SECONDS, 'aips_admin_bar');

		$this->assertSame(2, $cache->get($cache_key, 'aips_admin_bar'), 'Cache should have stale value');

		// Declare that this test expects JSON output from wp_send_json_success.
		$this->expectOutputRegex('/.*/');

		// Set up AJAX request
		$orig_post    = $_POST;
		$orig_request = $_REQUEST;

		try {
			$_POST['nonce']    = wp_create_nonce('aips_admin_bar_nonce');
			$_REQUEST['nonce'] = wp_create_nonce('aips_admin_bar_nonce');

			// Call ajax_mark_all_read and catch the die exception
			try {
				$this->admin_bar->ajax_mark_all_read();
				$this->fail('Expected WPAjaxDieContinueException');
			} catch (WPAjaxDieContinueException $e) {
				// Expected - AJAX handlers call wp_die
			}

			// Verify cache now holds the fresh count (0 after marking all read)
			$this->assertTrue($cache->has($cache_key, 'aips_admin_bar'), 'Cache should be repopulated after mark_all_read');
			$this->assertSame(0, $cache->get($cache_key, 'aips_admin_bar'), 'Cache value should reflect new unread count');
		} finally {
			$_POST    = $orig_post;
			$_REQUEST = $orig_request;
		}
	}
}
