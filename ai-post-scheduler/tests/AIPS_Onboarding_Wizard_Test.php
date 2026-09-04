<?php
/**
 * Tests for onboarding wizard wiring.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Onboarding_Wizard_Test extends WP_UnitTestCase {

	public function test_onboarding_wizard_class_exists() {
		$this->assertTrue(class_exists('AIPS_Onboarding_Wizard'));
		$this->assertEquals('aips-onboarding', AIPS_Onboarding_Wizard::PAGE_SLUG);
	}

	public function test_admin_menu_helper_slugs_for_onboarding_and_status() {
		$this->assertEquals('aips-onboarding', AIPS_Admin_Menu_Helper::get_slug('onboarding'));
		$this->assertEquals('aips-status', AIPS_Admin_Menu_Helper::get_slug('system_status'));
	}

	public function test_admin_menu_helper_builds_onboarding_url() {
		$url = AIPS_Admin_Menu_Helper::get_page_url('onboarding');
		$this->assertStringContainsString('admin.php?page=aips-onboarding', $url);
	}

	/**
	 * Regression test: the onboarding wizard must remain URL-accessible while hidden.
	 */
	public function test_onboarding_is_registered_as_hidden_page() {
		global $submenu, $_registered_pages, $_parent_pages;

		$original_user_id              = get_current_user_id();
		$had_submenu                    = array_key_exists('submenu', $GLOBALS);
		$had_registered_pages          = array_key_exists('_registered_pages', $GLOBALS);
		$had_parent_pages              = array_key_exists('_parent_pages', $GLOBALS);
		$original_submenu              = $had_submenu ? $submenu : null;
		$original_registered_pages     = $had_registered_pages ? $_registered_pages : null;
		$original_parent_pages         = $had_parent_pages ? $_parent_pages : null;

		$submenu           = array();
		$_registered_pages = array();
		$_parent_pages     = array();

		wp_set_current_user(self::factory()->user->create(array('role' => 'administrator')));

		$reflection = new ReflectionClass(AIPS_Onboarding_Wizard::class);
		$wizard     = $reflection->newInstanceWithoutConstructor();

		try {
			$wizard->register_page();

			$this->assertArrayHasKey(
				'admin_page_aips-onboarding',
				$_registered_pages,
				'Onboarding should be registered for direct admin.php?page= access.'
			);

			$submenu_pages = isset($submenu['ai-post-scheduler']) ? wp_list_pluck($submenu['ai-post-scheduler'], 2) : array();

			$this->assertNotContains(
				'aips-onboarding',
				$submenu_pages,
				'Onboarding should remain hidden from the visible submenu.'
			);
		} finally {
			remove_action('admin_page_aips-onboarding', array($wizard, 'render_page'));
			wp_set_current_user($original_user_id);

			if ($had_submenu) {
				$submenu = $original_submenu;
			} else {
				unset($GLOBALS['submenu']);
			}

			if ($had_registered_pages) {
				$_registered_pages = $original_registered_pages;
			} else {
				unset($GLOBALS['_registered_pages']);
			}

			if ($had_parent_pages) {
				$_parent_pages = $original_parent_pages;
			} else {
				unset($GLOBALS['_parent_pages']);
			}
		}
	}

	public function test_admin_menu_helper_routes_status_to_diagnostics_tab() {
		$url = AIPS_Admin_Menu_Helper::get_page_url('system_status');
		$this->assertStringContainsString('admin.php?page=aips-diagnostics', $url);
		$this->assertStringContainsString('tab=status', $url);
	}
}
