<?php
/**
 * Test AIPS_Admin_Assets Matrix
 *
 * Table-driven characterization test suite asserting route-to-asset mapping,
 * feature isolation (no asset leakage across subtabs/hubs), and context resolution.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_AIPS_Admin_Assets_Matrix extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create(array(
			'role' => 'administrator',
		));
		wp_set_current_user($this->admin_user_id);

		// Reset globals
		$_GET = array();
		unset($GLOBALS['current_screen']);

		// Reset WP scripts and styles queues
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
	}

	public function tearDown(): void {
		$_GET = array();
		unset($GLOBALS['current_screen']);
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();

		parent::tearDown();
	}

	/**
	 * Test that context resolver correctly maps hook and query parameters to flags.
	 */
	public function test_context_resolver_matrix() {
		$resolver = new AIPS_Admin_Asset_Context_Resolver();

		// Case 1: Dashboard
		$_GET = array('page' => 'ai-post-scheduler');
		$ctx = $resolver->resolve('toplevel_page_ai-post-scheduler');
		$this->assertTrue($ctx['is_plugin_page']);
		$this->assertTrue($ctx['is_dashboard']);
		$this->assertFalse($ctx['is_schedule']);
		$this->assertFalse($ctx['is_authors']);

		// Case 2: Schedule automations tab
		$_GET = array('page' => 'aips-automations', 'tab' => 'schedules');
		$ctx = $resolver->resolve('admin_page_aips-automations');
		$this->assertTrue($ctx['is_plugin_page']);
		$this->assertTrue($ctx['is_schedule']);
		$this->assertFalse($ctx['is_dashboard']);
		$this->assertFalse($ctx['is_campaigns']);

		// Case 3: Campaigns automations tab
		$_GET = array('page' => 'aips-automations', 'tab' => 'campaigns');
		$ctx = $resolver->resolve('admin_page_aips-automations');
		$this->assertTrue($ctx['is_plugin_page']);
		$this->assertTrue($ctx['is_campaigns']);
		$this->assertFalse($ctx['is_schedule']);

		// Case 4: Authors automations tab
		$_GET = array('page' => 'aips-automations', 'tab' => 'authors');
		$ctx = $resolver->resolve('admin_page_aips-automations');
		$this->assertTrue($ctx['is_plugin_page']);
		$this->assertTrue($ctx['is_authors']);
		$this->assertFalse($ctx['is_dashboard']);

		// Case 5: Studio
		$_GET = array('page' => 'aips-studio');
		$ctx = $resolver->resolve('admin_page_aips-studio');
		$this->assertTrue($ctx['is_plugin_page']);
		$this->assertTrue($ctx['is_studio']);
		$this->assertFalse($ctx['is_dashboard']);

		// Case 6: Settings
		$_GET = array('page' => 'aips-settings');
		$ctx = $resolver->resolve('admin_page_aips-settings');
		$this->assertTrue($ctx['is_plugin_page']);
		$this->assertTrue($ctx['is_settings']);
		$this->assertFalse($ctx['is_dashboard']);

		// Case 7: Non-plugin screen
		$_GET = array('page' => 'other-plugin');
		$ctx = $resolver->resolve('toplevel_page_other-plugin');
		$this->assertFalse($ctx['is_plugin_page']);
		$this->assertFalse($ctx['is_dashboard']);
	}

	/**
	 * Data provider for route-to-asset matrix.
	 *
	 * @return array<string, array{0: string, 1: array<string, string>, 2: string, 3: string}>
	 */
	public function route_asset_matrix_provider() {
		return array(
			'Dashboard' => array(
				'toplevel_page_ai-post-scheduler',
				array('page' => 'ai-post-scheduler'),
				'aips-dashboard-script',
				'aips-admin-settings',
			),
			'Automations Schedules' => array(
				'admin_page_aips-automations',
				array('page' => 'aips-automations', 'tab' => 'schedules'),
				'aips-admin-script',
				'aips-dashboard-script',
			),
			'Automations Campaigns' => array(
				'admin_page_aips-automations',
				array('page' => 'aips-automations', 'tab' => 'campaigns'),
				'aips-admin-campaigns',
				'aips-dashboard-script',
			),
			'Automations Authors' => array(
				'admin_page_aips-automations',
				array('page' => 'aips-automations', 'tab' => 'authors'),
				'aips-authors-script',
				'aips-dashboard-script',
			),
			'Studio Templates' => array(
				'admin_page_aips-studio',
				array('page' => 'aips-studio'),
				'aips-admin-integrations',
				'aips-dashboard-script',
			),
			'Research' => array(
				'admin_page_aips-research',
				array('page' => 'aips-research'),
				'aips-admin-research',
				'aips-dashboard-script',
			),
			'Settings' => array(
				'admin_page_aips-settings',
				array('page' => 'aips-settings'),
				'aips-admin-settings',
				'aips-dashboard-script',
			),
		);
	}

	/**
	 * Test table-driven asset enqueueing and isolation.
	 *
	 * @dataProvider route_asset_matrix_provider
	 *
	 * @param string                $hook           Admin page hook.
	 * @param array<string, string> $get_params     $_GET query params.
	 * @param string                $expected_handle Expected enqueued script handle.
	 * @param string                $forbidden_handle Script handle that should NOT be enqueued.
	 */
	public function test_route_asset_enqueue_isolation($hook, $get_params, $expected_handle, $forbidden_handle) {
		$_GET = $get_params;

		$admin_assets = new AIPS_Admin_Assets();
		$admin_assets->enqueue_admin_assets($hook);

		$this->assertTrue(
			wp_script_is($expected_handle, 'enqueued') || wp_script_is($expected_handle, 'queue'),
			"Expected script '{$expected_handle}' to be enqueued for hook '{$hook}'"
		);

		$this->assertFalse(
			wp_script_is($forbidden_handle, 'enqueued') || wp_script_is($forbidden_handle, 'queue'),
			"Forbidden script '{$forbidden_handle}' leaked into hook '{$hook}'"
		);
	}

	/**
	 * Test native post screen enqueues only history modal opener assets and no plugin admin scripts.
	 */
	public function test_native_post_screen_enqueue() {
		set_current_screen('post.php');
		$screen = get_current_screen();
		$screen->post_type = 'post';

		$_GET = array('post' => '123', 'action' => 'edit');

		$admin_assets = new AIPS_Admin_Assets();
		$admin_assets->enqueue_admin_assets('post.php');

		// History script is enqueued for modal opener
		$this->assertTrue(wp_script_is('aips-admin-history', 'enqueued'));

		// Plugin admin script & dashboard scripts should NOT be enqueued
		$this->assertFalse(wp_script_is('aips-admin-script', 'enqueued'));
		$this->assertFalse(wp_script_is('aips-dashboard-script', 'enqueued'));
	}

	/**
	 * Test non-plugin admin screen enqueues nothing.
	 */
	public function test_non_plugin_screen_enqueues_nothing() {
		set_current_screen('edit-tags.php');
		$_GET = array('taxonomy' => 'category');

		$admin_assets = new AIPS_Admin_Assets();
		$admin_assets->enqueue_admin_assets('edit-tags.php');

		$this->assertFalse(wp_script_is('aips-admin-script', 'enqueued'));
		$this->assertFalse(wp_script_is('aips-admin-history', 'enqueued'));
		$this->assertFalse(wp_script_is('aips-dashboard-script', 'enqueued'));
	}

	/**
	 * Test asset version helper and dev mode filter.
	 */
	public function test_get_asset_version_and_filter() {
		$version = AIPS_Admin_Assets::get_asset_version();
		$this->assertEquals(AIPS_VERSION, $version);

		$admin_assets = new AIPS_Admin_Assets();
		$filtered = $admin_assets->filter_asset_version_for_dev('https://example.com/some.js', 'some-handle');
		$this->assertEquals('https://example.com/some.js', $filtered);
	}

	/**
	 * Test History modal scaffold rendering.
	 */
	public function test_render_history_modal_scaffold() {
		$admin_assets = new AIPS_Admin_Assets();
		ob_start();
		$admin_assets->render_history_modal_scaffold();
		$output = ob_get_clean();

		$this->assertStringContainsString('id="aips-history-modal"', $output);
		$this->assertStringContainsString('id="aips-history-modal-content"', $output);
	}
}
