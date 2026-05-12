<?php
/**
 * Tests for admin hub routing helpers.
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Admin_Hub_Routing_Test extends WP_UnitTestCase {

	public function test_admin_menu_helper_builds_operations_insights_hub_url() {
		$url = AIPS_Admin_Menu_Helper::get_page_url('operations_insights');

		$this->assertStringContainsString('admin.php?page=aips-operations', $url);
		$this->assertStringContainsString('tab=insights', $url);
	}

	public function test_hub_registry_maps_operations_legacy_page_to_visible_hub_slug() {
		$this->assertSame(
			'aips-operations',
			AIPS_Admin_Hub_Registry::get_visible_slug_for_page('aips-operations-insights')
		);
	}
}
