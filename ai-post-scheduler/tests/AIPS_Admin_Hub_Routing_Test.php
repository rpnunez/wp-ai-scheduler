<?php
/**
 * Tests for hub routing and legacy page mappings.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Admin_Hub_Routing_Test extends WP_UnitTestCase {

	public function test_prompt_sections_routes_to_structures_subtab() {
		$url = AIPS_Admin_Menu_Helper::get_page_url('prompt_sections');

		$this->assertStringContainsString('page=aips-content-setup', $url);
		$this->assertStringContainsString('tab=structures', $url);
		$this->assertStringContainsString('subtab=aips-structure-sections', $url);
	}

	public function test_post_slices_routes_to_content_setup_post_slices_tab() {
		$url = AIPS_Admin_Menu_Helper::get_page_url('post_slices');

		$this->assertStringContainsString('page=aips-content-setup', $url);
		$this->assertStringContainsString('tab=post_slices', $url);
	}

	public function test_operations_insights_routes_to_operations_hub_tab() {
		$url = AIPS_Admin_Menu_Helper::get_page_url('operations_insights');

		$this->assertStringContainsString('page=aips-operations', $url);
		$this->assertStringContainsString('tab=insights', $url);
	}

	public function test_visible_slug_mapping_for_legacy_pages() {
		$this->assertEquals('aips-operations', AIPS_Admin_Hub_Registry::get_visible_slug_for_page('aips-operations-insights'));
		$this->assertEquals('aips-content-setup', AIPS_Admin_Hub_Registry::get_visible_slug_for_page('aips-post-slices'));
		$this->assertEquals('aips-content-setup', AIPS_Admin_Hub_Registry::get_visible_slug_for_page('aips-sections'));
	}
}
