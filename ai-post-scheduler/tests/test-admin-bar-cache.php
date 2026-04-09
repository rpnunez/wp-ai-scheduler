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
		$this->admin_bar  = new AIPS_Admin_Bar();
		$this->repository = new AIPS_Notifications_Repository();

		// Clean up any existing notifications
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_notifications");

		// Clear cache
		wp_cache_flush();

		// Set up a user with manage_options capability
		$user_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);
	}

	public function tearDown(): void {
		// Clean up
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}aips_notifications");
		wp_cache_flush();
		parent::tearDown();
	}

	/**
	 * Test that cache is set with 60-second TTL
	 */
	public function test_cache_set_with_ttl() {
		// Create a mock WP_Admin_Bar
		$wp_admin_bar = $this->getMockBuilder('WP_Admin_Bar')
			->disableOriginalConstructor()
			->onlyMethods(array('add_node', 'add_group'))
			->getMock();

		// Clear cache before test
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		wp_cache_delete($cache_key, 'aips_admin_bar');

		// Call add_toolbar_node which should set cache
		$this->admin_bar->add_toolbar_node($wp_admin_bar);

		// Verify cache was set
		$cached_value = wp_cache_get($cache_key, 'aips_admin_bar');
		$this->assertNotFalse($cached_value, 'Cache should be set after add_toolbar_node');
		$this->assertSame(0, $cached_value, 'Cache value should be 0 when no notifications');
	}

	/**
	 * Test that get_unread() is not called when count is 0
	 */
	public function test_get_unread_skipped_when_count_zero() {
		// Create a partial mock of AIPS_Notifications_Repository
		$mock_repo = $this->getMockBuilder('AIPS_Notifications_Repository')
			->setMethods(array('count_unread', 'get_unread'))
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
		$property = $reflection->getProperty('repository');
		$property->setAccessible(true);

		$admin_bar = new AIPS_Admin_Bar();
		$property->setValue($admin_bar, $mock_repo);

		// Clear cache to force repository call
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		wp_cache_delete($cache_key, 'aips_admin_bar');

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
			->setMethods(array('count_unread', 'get_unread'))
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
		$property = $reflection->getProperty('repository');
		$property->setAccessible(true);

		$admin_bar = new AIPS_Admin_Bar();
		$property->setValue($admin_bar, $mock_repo);

		// Clear cache to force repository call
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		wp_cache_delete($cache_key, 'aips_admin_bar');

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
	 * Test cache invalidation in ajax_mark_read
	 */
	public function test_cache_invalidated_on_mark_read() {
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

		// Set cache
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		wp_cache_set($cache_key, 1, 'aips_admin_bar', 60);

		// Verify cache is set
		$this->assertSame(1, wp_cache_get($cache_key, 'aips_admin_bar'));

		// Set up AJAX request
		$_POST['id']      = $notif_id;
		$_POST['nonce']   = wp_create_nonce('aips_admin_bar_nonce');
		$_REQUEST['nonce'] = wp_create_nonce('aips_admin_bar_nonce');

		// Call ajax_mark_read and catch the die exception
		try {
			$this->admin_bar->ajax_mark_read();
			$this->fail('Expected WPAjaxDieContinueException');
		} catch (WPAjaxDieContinueException $e) {
			// Expected - AJAX handlers call wp_die
		}

		// Verify cache was deleted
		$cached_value = wp_cache_get($cache_key, 'aips_admin_bar');
		$this->assertFalse($cached_value, 'Cache should be invalidated after mark_read');
	}

	/**
	 * Test cache invalidation in ajax_mark_all_read
	 */
	public function test_cache_invalidated_on_mark_all_read() {
		// Skip if we can't test AJAX properly
		if (!function_exists('wp_send_json_success')) {
			$this->markTestSkipped('AJAX functions not available');
		}

		// Create notifications
		$this->repository->create(
			'test_notification',
			'Test notification 1'
		);
		$this->repository->create(
			'test_notification',
			'Test notification 2'
		);

		// Set cache
		$cache_key = 'aips_unread_count_' . get_current_user_id();
		wp_cache_set($cache_key, 2, 'aips_admin_bar', 60);

		// Verify cache is set
		$this->assertSame(2, wp_cache_get($cache_key, 'aips_admin_bar'));

		// Set up AJAX request
		$_POST['nonce']    = wp_create_nonce('aips_admin_bar_nonce');
		$_REQUEST['nonce'] = wp_create_nonce('aips_admin_bar_nonce');

		// Call ajax_mark_all_read and catch the die exception
		try {
			$this->admin_bar->ajax_mark_all_read();
			$this->fail('Expected WPAjaxDieContinueException');
		} catch (WPAjaxDieContinueException $e) {
			// Expected - AJAX handlers call wp_die
		}

		// Verify cache was deleted
		$cached_value = wp_cache_get($cache_key, 'aips_admin_bar');
		$this->assertFalse($cached_value, 'Cache should be invalidated after mark_all_read');
	}
}
