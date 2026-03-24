<?php
/**
 * Test case for AIPS_Admin_Menu
 *
 * Tests the extraction and functionality of AIPS_Admin_Menu class.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

class Test_AIPS_Admin_Menu extends WP_UnitTestCase {

    private $admin_menu;

    public function setUp(): void {
        parent::setUp();
        $this->admin_menu = new AIPS_Admin_Menu();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test admin menu instantiation
     */
    public function test_admin_menu_instantiation() {
        $this->assertInstanceOf('AIPS_Admin_Menu', $this->admin_menu);
    }

    /**
     * Test hooks are registered
     */
    public function test_hooks_are_registered() {
        if (!function_exists('has_action') || !function_exists('has_filter')) {
            $this->markTestSkipped('WordPress environment not available.');
        }
        $this->assertEquals(
            10,
            has_action('admin_menu', array($this->admin_menu, 'add_menu_pages'))
        );
        $this->assertEquals(
            10,
            has_filter('parent_file', array($this->admin_menu, 'fix_author_topics_parent_file'))
        );
        $this->assertEquals(
            10,
            has_filter('submenu_file', array($this->admin_menu, 'fix_author_topics_submenu_file'))
        );
    }
}
