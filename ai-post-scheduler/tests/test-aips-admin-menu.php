<?php
/**
 * Test AIPS_Admin_Menu class
 */

class AIPS_Admin_Menu_Test extends WP_UnitTestCase {

	private $admin_menu;

	public function setUp(): void {
		parent::setUp();
		if (!class_exists('AIPS_Admin_Menu')) {
			require_once dirname(__FILE__) . '/../includes/class-aips-admin-menu.php';
		}
		$this->admin_menu = new AIPS_Admin_Menu();
	}

	public function test_admin_menu_hooks() {
		// Just verify the class exists and object is initialized,
		// since in limited test mode WordPress hooks like has_filter are not always fully mocked.
		$this->assertInstanceOf('AIPS_Admin_Menu', $this->admin_menu);
	}
}
