<?php
/**
 * Class AIPS_Admin_Menu_Test
 *
 * @package AI_Post_Scheduler
 */


if (!function_exists('add_menu_page')) {
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null) {
        global $menu;
        $menu[] = array($menu_title, $capability, $menu_slug, $page_title, 'menu-top', $menu_slug, $icon_url);
    }
}
if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '', $position = null) {
        global $submenu;
        $submenu[$parent_slug][] = array($menu_title, $capability, $menu_slug, $page_title);
    }
}
if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user($id, $name = '') {}
}


if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $key));
    }
}

class Test_AIPS_Admin_Menu extends WP_UnitTestCase {



	private $admin_menu;

	public function setUp(): void {
		parent::setUp();

		// Reset global menu arrays before each test
		global $menu, $submenu;
		$menu = array();
		$submenu = array();

		// Mock current_user_can to allow adding menus
		wp_set_current_user($this->factory->user->create(array('role' => 'administrator')));

		$this->admin_menu = new AIPS_Admin_Menu();
	}

	public function test_add_menu_pages_registers_main_menu() {
		// Suppress developer mode option to ensure predictable submenu count
		update_option('aips_developer_mode', 0);

		$this->admin_menu->add_menu_pages();

		global $menu;
		$main_menu_slug = 'ai-post-scheduler';
		$found = false;

		if (is_array($menu)) {
			foreach ($menu as $item) {
				if ($item[2] === $main_menu_slug) {
					$found = true;
					break;
				}
			}
		}
		$this->assertTrue($found, 'Main menu page was not registered.');
	}

	public function test_add_menu_pages_registers_submenus() {
		update_option('aips_developer_mode', 0);

		$this->admin_menu->add_menu_pages();

		global $submenu;
		$this->assertArrayHasKey('ai-post-scheduler', $submenu, 'Submenu array for ai-post-scheduler not found.');

		$expected_submenus = array(
			'ai-post-scheduler',
			'aips-templates',
			'aips-voices',
			'aips-structures',
			'aips-authors',
			'aips-research',
			'aips-schedule',
			'aips-schedule-calendar',
			'aips-generated-posts',
			'aips-history',
			'aips-settings',
			'aips-status',
			'aips-seeder'
		);

		$registered_submenus = array();
		foreach ($submenu['ai-post-scheduler'] as $item) {
			$registered_submenus[] = $item[2];
		}

		foreach ($expected_submenus as $expected_slug) {
			$this->assertContains($expected_slug, $registered_submenus, "Submenu $expected_slug was not registered.");
		}
	}

	public function test_add_menu_pages_registers_dev_tools_when_enabled() {
		update_option('aips_developer_mode', 1);

		$this->admin_menu->add_menu_pages();

		global $submenu;
		$this->assertArrayHasKey('ai-post-scheduler', $submenu);

		$registered_submenus = array();
		foreach ($submenu['ai-post-scheduler'] as $item) {
			$registered_submenus[] = $item[2];
		}

		$this->assertContains('aips-dev-tools', $registered_submenus, 'Dev Tools submenu should be registered when aips_developer_mode is 1.');
	}

	public function test_fix_author_topics_parent_file() {
		$_GET['page'] = 'aips-author-topics';

		$result = $this->admin_menu->fix_author_topics_parent_file('some-parent-file');
		$this->assertEquals('ai-post-scheduler', $result, 'parent_file filter should return ai-post-scheduler when page is aips-author-topics.');

		$_GET['page'] = 'other-page';
		$result = $this->admin_menu->fix_author_topics_parent_file('some-parent-file');
		$this->assertEquals('some-parent-file', $result, 'parent_file filter should return the original parent file when page is not aips-author-topics.');
	}

	public function test_fix_author_topics_submenu_file() {
		$_GET['page'] = 'aips-author-topics';

		$result = $this->admin_menu->fix_author_topics_submenu_file('some-submenu-file');
		$this->assertEquals('aips-authors', $result, 'submenu_file filter should return aips-authors when page is aips-author-topics.');

		$_GET['page'] = 'other-page';
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-submenu-file');
		$this->assertEquals('some-submenu-file', $result, 'submenu_file filter should return the original submenu file when page is not aips-author-topics.');
	}
}
