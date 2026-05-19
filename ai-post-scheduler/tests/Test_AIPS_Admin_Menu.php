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

	/**
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * @var int
	 */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();
		$this->admin_menu         = new AIPS_Admin_Menu();
		$this->admin_user_id     = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->get_var_return_val = null;
		wp_set_current_user( 0 );

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

	/**
	 * Regression test: hidden Author Topics page must remain URL-accessible.
	 */
	public function test_author_topics_is_registered_as_hidden_page() {
		if ( ! function_exists( 'add_menu_page' ) || ! function_exists( 'add_submenu_page' ) ) {
			$this->markTestSkipped( 'WordPress admin menu functions are not available in limited mode.' );
		}

		global $submenu, $_registered_pages;

		$submenu           = array();
		$_registered_pages = array();

		$this->admin_menu->add_menu_pages();

		$this->assertArrayHasKey(
			'admin_page_aips-author-topics',
			$_registered_pages,
			'Author Topics page should be registered for direct admin.php?page= access.'
		);

		$submenu_pages = isset($submenu['ai-post-scheduler']) ? wp_list_pluck($submenu['ai-post-scheduler'], 2) : array();

		$this->assertNotContains(
			'aips-author-topics',
			$submenu_pages,
			'Author Topics page should remain hidden from the visible submenu.'
		);
	}

	/**
	 * Test that Content menu label shows the pending review bubble for admins.
	 */
	public function test_content_menu_label_shows_pending_review_count_for_admins() {
		global $wpdb;

		wp_set_current_user( $this->admin_user_id );
		$wpdb->get_var_return_val = 2;

		$label = $this->invoke_private_method( 'get_content_menu_label' );

		$this->assertStringContainsString( 'Content', $label );
		$this->assertStringContainsString( 'update-plugins', $label );
		$this->assertStringContainsString( 'plugin-count">2</span>', $label );
	}

	/**
	 * Test that non-admin users do not fetch or render pending review counts.
	 */
	public function test_pending_review_count_is_zero_without_manage_options() {
		global $wpdb;

		$wpdb->get_var_return_val = 5;
		wp_set_current_user( $this->subscriber_user_id );

		$count = $this->invoke_private_method( 'get_pending_review_count' );
		$label = $this->invoke_private_method( 'get_content_menu_label' );

		$this->assertSame( 0, $count );
		$this->assertSame( 'Content', $label );
	}

	/**
	 * Invoke a private method on the admin menu instance.
	 *
	 * @param string $method Method name.
	 * @return mixed
	 */
	private function invoke_private_method( $method ) {
		$reflection = new ReflectionMethod( $this->admin_menu, $method );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->admin_menu );
	}
}
