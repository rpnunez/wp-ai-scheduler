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
}

