<?php
/**
 * Tests for AIPS_Admin_Menu
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
		parent::tearDown();
	}

	/**
	 * Test that admin menu hooks are correctly registered
	 */
	public function test_hooks_registered() {
		if ( ! function_exists( 'has_action' ) || ! function_exists( 'has_filter' ) ) {
			$this->markTestSkipped( 'WordPress environment required for hook testing.' );
			return;
		}

		$this->assertNotFalse(
			has_action('admin_menu', array($this->admin_menu, 'add_menu_pages')),
			'admin_menu hook should be registered'
		);
		$this->assertNotFalse(
			has_filter('parent_file', array($this->admin_menu, 'fix_author_topics_parent_file')),
			'parent_file hook should be registered'
		);
		$this->assertNotFalse(
			has_filter('submenu_file', array($this->admin_menu, 'fix_author_topics_submenu_file')),
			'submenu_file hook should be registered'
		);
	}

	/**
	 * Test that the parent_file filter returns correct values
	 */
	public function test_fix_author_topics_parent_file() {
		$_GET['page'] = 'aips-author-topics';
		$result = $this->admin_menu->fix_author_topics_parent_file('some-other-file');
		$this->assertEquals('ai-post-scheduler', $result);

		$_GET['page'] = 'some-other-page';
		$result = $this->admin_menu->fix_author_topics_parent_file('some-other-file');
		$this->assertEquals('some-other-file', $result);

		unset($_GET['page']);
	}

	/**
	 * Test that the submenu_file filter returns correct values
	 */
	public function test_fix_author_topics_submenu_file() {
		$_GET['page'] = 'aips-author-topics';
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-other-file');
		$this->assertEquals('aips-authors', $result);

		$_GET['page'] = 'some-other-page';
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-other-file');
		$this->assertEquals('some-other-file', $result);

		unset($_GET['page']);
	}
}
