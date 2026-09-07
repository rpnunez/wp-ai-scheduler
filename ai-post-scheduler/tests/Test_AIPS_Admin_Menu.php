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

		$_GET['page'] = 'aips-campaign-detail';
		$result = $this->admin_menu->fix_author_topics_parent_file('some-other-file');
		$this->assertEquals('ai-post-scheduler', $result);

		$_GET['page'] = AIPS_Campaigns_Controller::PAGE_SLUG;
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
		$this->assertEquals('aips-automations', $result);

		$_GET['page'] = 'aips-campaign-detail';
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-other-file');
		$this->assertEquals('aips-automations', $result);

		$_GET['page'] = AIPS_Campaigns_Controller::PAGE_SLUG;
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-other-file');
		$this->assertEquals('aips-automations', $result);

		$_GET['page'] = 'some-other-page';
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-other-file');
		$this->assertEquals('some-other-file', $result);

		unset($_GET['page']);
	}

	/**
	 * Regression test: hidden Author Topics page must remain URL-accessible.
	 */
	public function test_author_topics_is_registered_as_hidden_page() {
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
	 * Diagnostics consolidates operational tools into one visible submenu.
	 */
	public function test_diagnostics_submenu_replaces_visible_diagnostics_tools() {
		global $submenu, $_registered_pages;

		$submenu           = array();
		$_registered_pages = array();

		$this->admin_menu->add_menu_pages();

		$submenu_pages = isset($submenu['ai-post-scheduler']) ? wp_list_pluck($submenu['ai-post-scheduler'], 2) : array();

		$this->assertContains(
			'aips-diagnostics',
			$submenu_pages,
			'Diagnostics should be visible in the primary submenu.'
		);

		foreach (array('aips-operations-insights', 'aips-status') as $hidden_page) {
			$this->assertNotContains(
				$hidden_page,
				$submenu_pages,
				$hidden_page . ' should be hidden from the primary submenu.'
			);
			$this->assertArrayHasKey(
				'admin_page_' . $hidden_page,
				$_registered_pages,
				$hidden_page . ' should remain registered for direct admin.php?page= access.'
			);
		}
	}

	/**
	 * Diagnostics rendering is delegated to a controller and template.
	 */
	public function test_diagnostics_controller_and_template_exist() {
		$this->assertTrue(class_exists('AIPS_Diagnostics_Controller'));
		$this->assertFileExists(AIPS_PLUGIN_DIR . 'templates/admin/diagnostics.php');
	}

	/**
	 * The standalone Seeder admin page has been retired; the Seed Configuration
	 * UI now lives inside Diagnostics -> Dev Tools.
	 */
	public function test_standalone_seeder_page_is_never_registered() {
		global $submenu, $_registered_pages;

		$submenu           = array();
		$_registered_pages = array();

		update_option('aips_developer_mode', true);
		AIPS_Config::get_instance()->flush_option_cache();

		$this->admin_menu->add_menu_pages();

		$this->assertArrayNotHasKey(
			'admin_page_aips-seeder',
			$_registered_pages,
			'The standalone Seeder page must not be registered; Seeder UI lives under Dev Tools.'
		);

		update_option('aips_developer_mode', false);
		AIPS_Config::get_instance()->flush_option_cache();
	}

	/**
	 * Cache Monitor is an internal introspection tool and must be disabled by default.
	 */
	public function test_cache_monitor_page_is_not_registered_by_default() {
		global $submenu, $_registered_pages;

		$submenu           = array();
		$_registered_pages = array();

		update_option('aips_cache_monitor_enabled', false);
		AIPS_Config::get_instance()->flush_option_cache();

		$this->admin_menu->add_menu_pages();

		$this->assertArrayNotHasKey(
			'admin_page_aips-cache-monitor',
			$_registered_pages,
			'Cache Monitor should not be registered when aips_cache_monitor_enabled is disabled.'
		);
	}

	/**
	 * Cache Monitor becomes reachable once explicitly enabled.
	 */
	public function test_cache_monitor_page_is_registered_when_enabled() {
		global $submenu, $_registered_pages;

		$submenu           = array();
		$_registered_pages = array();

		update_option('aips_cache_monitor_enabled', true);
		AIPS_Config::get_instance()->flush_option_cache();

		$this->admin_menu->add_menu_pages();

		$this->assertArrayHasKey(
			'admin_page_aips-cache-monitor',
			$_registered_pages,
			'Cache Monitor should be registered for direct admin.php?page= access when enabled.'
		);

		update_option('aips_cache_monitor_enabled', false);
		AIPS_Config::get_instance()->flush_option_cache();
	}

	/**
	 * Automations consolidates core automation pages into one visible submenu.
	 */
	public function test_automations_submenu_replaces_visible_automation_tools() {
		global $submenu, $_registered_pages;

		$submenu           = array();
		$_registered_pages = array();

		$this->admin_menu->add_menu_pages();

		$submenu_pages = isset($submenu['ai-post-scheduler']) ? wp_list_pluck($submenu['ai-post-scheduler'], 2) : array();

		$this->assertContains(
			'aips-automations',
			$submenu_pages,
			'Automations should be visible in the primary submenu.'
		);

		foreach (array('aips-schedule', 'aips-campaigns', 'aips-templates', 'aips-authors', 'aips-sources', 'aips-internal-links', 'aips-taxonomy') as $hidden_page) {
			$this->assertNotContains(
				$hidden_page,
				$submenu_pages,
				$hidden_page . ' should be hidden from the primary submenu.'
			);
			$this->assertArrayHasKey(
				'admin_page_' . $hidden_page,
				$_registered_pages,
				$hidden_page . ' should remain registered for direct admin.php?page= access.'
			);
		}
	}

	/**
	 * Automations rendering is delegated to a controller and template.
	 */
	public function test_automations_controller_and_template_exist() {
		$this->assertTrue(class_exists('AIPS_Automations_Controller'));
		$this->assertFileExists(AIPS_PLUGIN_DIR . 'templates/admin/automations.php');
	}

}
