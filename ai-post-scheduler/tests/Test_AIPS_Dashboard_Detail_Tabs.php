<?php
/**
 * Guards the Dashboard "Analytics Detail" tab panels against being hidden by
 * the shared `.aips-tab-content` display:none rules.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Dashboard_Detail_Tabs extends WP_UnitTestCase {

	public function test_detail_panels_wrapper_does_not_use_shared_tab_content_class() {
		$source = file_get_contents(AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php');

		$this->assertStringContainsString('class="aips-dashboard-tab-panels"', $source);
		$this->assertStringNotContainsString('class="aips-tab-content"', $source);
	}

	public function test_detail_panels_wrapper_is_visible_by_css() {
		$css = file_get_contents(AIPS_PLUGIN_DIR . 'assets/css/admin.css');

		$this->assertMatchesRegularExpression('/\.aips-dashboard-tab-panels\s*\{[^}]*display:\s*block/', $css);
	}

	public function test_each_tab_button_controls_an_existing_panel() {
		$source = file_get_contents(AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php');

		preg_match_all('/aria-controls="([^"]+)"/', $source, $matches);
		$this->assertNotEmpty($matches[1]);
		foreach ($matches[1] as $panel_id) {
			$this->assertStringContainsString('id="' . $panel_id . '"', $source);
		}
	}
}
