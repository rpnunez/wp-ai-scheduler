<?php
/**
 * Tests for AIPS_Admin_Menu.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Menu extends WP_UnitTestCase {

	/**
	 * @var AIPS_Admin_Menu
	 */
	private $admin_menu;

	public function setUp(): void {
		parent::setUp();
		$this->admin_menu = new AIPS_Admin_Menu();
	}

	public function tearDown(): void {
		unset($_GET['page']);
		parent::tearDown();
	}

	public function test_hooks_registered() {
		if (!function_exists('has_action') || !function_exists('has_filter')) {
			$this->markTestSkipped('WordPress environment required for hook testing.');
			return;
		}

		$this->assertNotFalse(
			has_action('admin_menu', array($this->admin_menu, 'add_menu_pages'))
		);
		$this->assertNotFalse(
			has_filter('parent_file', array($this->admin_menu, 'filter_parent_file'))
		);
		$this->assertNotFalse(
			has_filter('submenu_file', array($this->admin_menu, 'filter_submenu_file'))
		);
	}

	public function test_filter_parent_file_returns_plugin_parent_for_hidden_hub_pages() {
		$_GET['page'] = 'aips-author-topics';
		$this->assertSame('ai-post-scheduler', $this->admin_menu->filter_parent_file('tools.php'));

		$_GET['page'] = 'aips-post-slices';
		$this->assertSame('ai-post-scheduler', $this->admin_menu->filter_parent_file('tools.php'));

		$_GET['page'] = 'some-other-page';
		$this->assertSame('tools.php', $this->admin_menu->filter_parent_file('tools.php'));
	}

	public function test_filter_submenu_file_returns_visible_hub_slug_for_hidden_pages() {
		$_GET['page'] = 'aips-author-topics';
		$this->assertSame('aips-automation', $this->admin_menu->filter_submenu_file('tools.php'));

		$_GET['page'] = 'aips-post-slices';
		$this->assertSame('aips-content-setup', $this->admin_menu->filter_submenu_file('tools.php'));

		$_GET['page'] = 'some-other-page';
		$this->assertSame('tools.php', $this->admin_menu->filter_submenu_file('tools.php'));
	}

	public function test_hidden_pages_remain_registered_for_direct_access() {
		global $submenu, $_registered_pages;

		$submenu           = array();
		$_registered_pages = array();

		$this->admin_menu->add_menu_pages();

		$this->assertArrayHasKey('admin_page_aips-author-topics', $_registered_pages);
		$this->assertArrayHasKey('admin_page_aips-post-slices', $_registered_pages);

		$submenu_pages = isset($submenu['ai-post-scheduler']) ? wp_list_pluck($submenu['ai-post-scheduler'], 2) : array();

		$this->assertNotContains('aips-author-topics', $submenu_pages);
		$this->assertNotContains('aips-post-slices', $submenu_pages);
	}
}
