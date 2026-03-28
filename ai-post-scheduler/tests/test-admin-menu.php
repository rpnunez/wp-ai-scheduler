<?php
/**
 * Class AIPS_Admin_Menu_Test
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Admin_Menu_Test extends WP_UnitTestCase {

	public function test_admin_menu_hooks() {
		$admin_menu = new AIPS_Admin_Menu();
		$this->assertIsObject($admin_menu);

		$this->assertTrue(method_exists($admin_menu, 'add_menu_pages'));
		$this->assertTrue(method_exists($admin_menu, 'render_dashboard_page'));
		$this->assertTrue(method_exists($admin_menu, 'render_settings_page'));
	}

    public function test_admin_menu_fix_submenu() {
        $admin_menu = new AIPS_Admin_Menu();
        // Setup a fake GET page variable
        $_GET['page'] = 'aips-author-topics';
        $result = $admin_menu->fix_author_topics_submenu_file('some-file.php');
        $this->assertEquals('aips-authors', $result);
        unset($_GET['page']);

        $result2 = $admin_menu->fix_author_topics_submenu_file('another-file.php');
        $this->assertEquals('another-file.php', $result2);
    }

    public function test_admin_menu_fix_parent() {
        $admin_menu = new AIPS_Admin_Menu();
        $_GET['page'] = 'aips-author-topics';
        $result = $admin_menu->fix_author_topics_parent_file('some-parent.php');
        $this->assertEquals('ai-post-scheduler', $result);
        unset($_GET['page']);

        $result2 = $admin_menu->fix_author_topics_parent_file('another-parent.php');
        $this->assertEquals('another-parent.php', $result2);
    }
}
