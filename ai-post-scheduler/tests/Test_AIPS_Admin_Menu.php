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
		// add_menu_page()/add_submenu_page() register nothing without manage_options.
		wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));
		$this->admin_menu = new AIPS_Admin_Menu();
	}

	public function tearDown(): void {
		wp_set_current_user(0);
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
			has_action('admin_init', array($this->admin_menu, 'maybe_redirect_legacy_pages')),
			'admin_init hook should be registered'
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

		$_GET['page'] = 'aips-source-data';
		$result = $this->admin_menu->fix_author_topics_parent_file('some-other-file');
		$this->assertEquals('ai-post-scheduler', $result);

		$_GET['page'] = 'aips-templates';
		$result = $this->admin_menu->fix_author_topics_parent_file('some-other-file');
		$this->assertEquals('some-other-file', $result);

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

		$_GET['page'] = 'aips-source-data';
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-other-file');
		$this->assertEquals('aips-automations', $result);

		$_GET['page'] = 'aips-campaign-detail';
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-other-file');
		$this->assertEquals('aips-campaigns', $result);

		$_GET['page'] = AIPS_Campaigns_Controller::PAGE_SLUG;
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-other-file');
		$this->assertEquals('aips-campaigns', $result);

		$_GET['page'] = 'aips-templates';
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-other-file');
		$this->assertEquals('some-other-file', $result);

		$_GET['page'] = 'some-other-page';
		$result = $this->admin_menu->fix_author_topics_submenu_file('some-other-file');
		$this->assertEquals('some-other-file', $result);

		unset($_GET['page']);
	}

	/**
	 * Retired standalone pages are no longer registered; live hidden pages remain.
	 */
	public function test_legacy_redirect_shims_are_not_registered() {
		global $submenu, $_registered_pages;

		$submenu           = array();
		$_registered_pages = array();

		$this->admin_menu->add_menu_pages();

		foreach (array('aips-status', 'aips-cannibalization', 'aips-content-intelligence') as $retired_page) {
			$this->assertArrayNotHasKey(
				'admin_page_' . $retired_page,
				$_registered_pages,
				$retired_page . ' should not be registered as a hidden submenu page.'
			);
		}

		$this->assertArrayHasKey('admin_page_aips-author-topics', $_registered_pages);
		$submenu_pages = isset($submenu['ai-post-scheduler']) ? wp_list_pluck($submenu['ai-post-scheduler'], 2) : array();
		$this->assertNotContains('aips-author-topics', $submenu_pages);
		$author_topics_url = AIPS_Admin_Menu_Helper::get_page_url('author_topics', array('author_id' => 42));
		$this->assertStringContainsString('page=aips-author-topics', $author_topics_url);
		$this->assertStringContainsString('author_id=42', $author_topics_url);
		$this->assertStringNotContainsString('page=aips-automations', $author_topics_url);
		$this->assertArrayHasKey('admin_page_aips-campaign-wizard', $_registered_pages);
		$this->assertArrayHasKey('admin_page_aips-campaign-detail', $_registered_pages);
		$this->assertArrayHasKey('admin_page_aips-source-data', $_registered_pages);
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
			$this->assertArrayNotHasKey(
				'admin_page_' . $hidden_page,
				$_registered_pages,
				$hidden_page . ' should not be registered as a hidden submenu page.'
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
	 * Cache Monitor remains a Diagnostics tab even when explicitly enabled.
	 */
	public function test_cache_monitor_page_is_not_registered_when_enabled() {
		global $submenu, $_registered_pages;

		$submenu           = array();
		$_registered_pages = array();

		update_option('aips_cache_monitor_enabled', true);
		AIPS_Config::get_instance()->flush_option_cache();

		$this->admin_menu->add_menu_pages();

		$this->assertArrayNotHasKey(
			'admin_page_aips-cache-monitor',
			$_registered_pages,
			'Cache Monitor should stay on the Diagnostics hub rather than register a hidden page.'
		);

		update_option('aips_cache_monitor_enabled', false);
		AIPS_Config::get_instance()->flush_option_cache();
	}

	/**
	 * Test that primary submenu contains exactly the 8 core items.
	 */
	public function test_primary_submenu_has_exact_core_items() {
		global $submenu;

		$submenu = array();
		$this->admin_menu->add_menu_pages();

		$submenu_pages = isset($submenu['ai-post-scheduler']) ? wp_list_pluck($submenu['ai-post-scheduler'], 2) : array();

		$expected_core_pages = array(
			'ai-post-scheduler',
			'aips-campaigns',
			'aips-automations',
			'aips-studio',
			'aips-research',
			'aips-generated-posts',
			'aips-history',
			'aips-settings',
			'aips-diagnostics',
		);

		$this->assertEquals($expected_core_pages, $submenu_pages, 'Primary submenu should contain the 8 hubs plus Campaigns.');
	}

	/**
	 * Studio rendering is delegated to a controller and template.
	 */
	public function test_studio_controller_and_template_exist() {
		$this->assertTrue(class_exists('AIPS_Studio_Controller'));
		$this->assertFileExists(AIPS_PLUGIN_DIR . 'templates/admin/studio.php');
	}

	/**
	 * Automations rendering is delegated to a controller and template.
	 */
	public function test_automations_controller_and_template_exist() {
		$this->assertTrue(class_exists('AIPS_Automations_Controller'));
		$this->assertFileExists(AIPS_PLUGIN_DIR . 'templates/admin/automations.php');
	}

	/**
	 * Test that redirect_to_hub preserves query parameters like search, filters, and pagination.
	 */
	public function test_redirect_to_hub_preserves_query_parameters() {
		$_GET['s']             = 'seo keywords';
		$_GET['paged']         = '3';
		$_GET['filter_status'] = 'active';
		$_GET['page']          = 'aips-voices';

		$redirect_target = '';
		$filter_callback = function($location) use (&$redirect_target) {
			$redirect_target = $location;
			throw new Exception('Redirect intercepted: ' . $location);
		};

		add_filter('wp_redirect', $filter_callback);

		try {
			$this->admin_menu->redirect_to_hub('aips-studio', 'voices');
		} catch (Exception $e) {
			// Expected exception to prevent exit.
		} finally {
			remove_filter('wp_redirect', $filter_callback);
		}

		$this->assertNotEmpty($redirect_target, 'Redirect target URL should not be empty.');

		// Compare decoded values; the space may be encoded as "+" or "%20".
		$args = array();
		parse_str((string) wp_parse_url($redirect_target, PHP_URL_QUERY), $args);
		$this->assertSame('aips-studio', $args['page']);
		$this->assertSame('voices', $args['tab']);
		$this->assertSame('seo keywords', $args['s']);
		$this->assertSame('3', $args['paged']);
		$this->assertSame('active', $args['filter_status']);

		unset($_GET['s'], $_GET['paged'], $_GET['filter_status'], $_GET['page']);
	}

	/**
	 * Verify that Monetization / Affiliate Links tab and URLs are integrated into Automations.
	 */
	public function test_automations_monetization_tab_and_urls() {
		$controller = new AIPS_Automations_Controller();
		$tabs = $controller->get_tabs();

		$this->assertArrayHasKey('monetization', $tabs, 'Monetization tab should be present in Automations tabs.');
		$this->assertSame('Monetization', $tabs['monetization']['label']);

		$url = AIPS_Admin_Menu_Helper::get_page_url('affiliate_links');
		$this->assertStringContainsString('page=aips-automations', $url);
		$this->assertStringContainsString('tab=monetization', $url);

		$context = $controller->get_page_context('monetization');
		$this->assertInstanceOf('AIPS_Admin_Page_Context', $context);

		$this->assertFalse(
			method_exists($this->admin_menu, 'render_prompt_sections_page'),
			'render_prompt_sections_page method must not exist on AIPS_Admin_Menu.'
		);
		$this->assertFalse(
			method_exists($this->admin_menu, 'render_voices_page'),
			'redirect-only render_voices_page must not exist on AIPS_Admin_Menu.'
		);
		$this->assertFileDoesNotExist(
			AIPS_PLUGIN_DIR . 'templates/admin/sections.php',
			'sections.php template must not exist.'
		);
	}

	/**
	 * Legacy bookmarks redirect to hub tabs before headers are sent.
	 *
	 * @dataProvider legacy_redirect_provider
	 */
	public function test_legacy_pages_redirect_to_canonical_hub_tabs($legacy_page, $expected_page, $expected_tab) {
		$user_id = self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);

		$_GET['s']             = 'seo keywords';
		$_GET['paged']         = '3';
		$_GET['filter_status'] = 'active';
		$_GET['page']          = $legacy_page;

		$redirect_target = '';
		$filter_callback = function($location) use (&$redirect_target) {
			$redirect_target = $location;
			throw new Exception('Redirect intercepted: ' . $location);
		};

		add_filter('wp_redirect', $filter_callback);

		try {
			$this->admin_menu->maybe_redirect_legacy_pages();
		} catch (Exception $e) {
			// Expected exception to prevent exit.
		} finally {
			remove_filter('wp_redirect', $filter_callback);
			unset($_GET['s'], $_GET['paged'], $_GET['filter_status'], $_GET['page']);
			wp_set_current_user(0);
		}

		$this->assertNotEmpty($redirect_target, 'Redirect target URL should not be empty.');
		$this->assertStringContainsString('page=' . $expected_page, $redirect_target);
		$this->assertStringContainsString('tab=' . $expected_tab, $redirect_target);
		$this->assertStringContainsString('s=seo+keywords', $redirect_target);
		$this->assertStringContainsString('paged=3', $redirect_target);
		$this->assertStringContainsString('filter_status=active', $redirect_target);
	}

	/**
	 * @return array<string, array{0:string,1:string,2:string}>
	 */
	public function legacy_redirect_provider() {
		return array(
			'voices'               => array('aips-voices', 'aips-studio', 'voices'),
			'status'               => array('aips-status', 'aips-diagnostics', 'health'),
			'cannibalization'      => array('aips-cannibalization', 'aips-generated-posts', 'aips-content-cannibalization'),
			'content_intelligence' => array('aips-content-intelligence', 'aips-generated-posts', 'aips-content-indexer'),
			'affiliate_links'      => array('aips-affiliate-links', 'aips-automations', 'monetization'),
		);
	}

	public function test_legacy_redirect_skips_live_hidden_pages_and_ajax() {
		$user_id = self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($user_id);

		$redirected = false;
		$filter_callback = function($location) use (&$redirected) {
			$redirected = true;
			return $location;
		};
		add_filter('wp_redirect', $filter_callback);

		$_GET['page'] = 'aips-author-topics';
		$this->admin_menu->maybe_redirect_legacy_pages();
		$this->assertFalse($redirected, 'Author Topics should keep rendering.');

		$_GET['page'] = 'aips-source-data';
		$this->admin_menu->maybe_redirect_legacy_pages();
		$this->assertFalse($redirected, 'Source Data should keep rendering.');

		$_GET['page'] = AIPS_Campaigns_Controller::PAGE_SLUG;
		$this->admin_menu->maybe_redirect_legacy_pages();
		$this->assertFalse($redirected, 'Campaign wizard should keep rendering.');

		$_GET['page'] = 'aips-voices';
		add_filter('wp_doing_ajax', '__return_true');
		$this->admin_menu->maybe_redirect_legacy_pages();
		remove_filter('wp_doing_ajax', '__return_true');
		$this->assertFalse($redirected, 'AJAX requests should not redirect.');

		remove_filter('wp_redirect', $filter_callback);
		unset($_GET['page']);
		wp_set_current_user(0);
	}

}
