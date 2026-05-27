<?php
/**
 * Tests for campaign AJAX routing.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Campaign_Ajax_Routing extends WP_UnitTestCase {

	public function test_campaign_wizard_actions_route_to_campaigns_controller() {
		$this->assertSame('AIPS_Campaigns_Controller', AIPS_Ajax_Registry::get_controller_for('aips_campaign_wizard_save_draft'));
		$this->assertSame('AIPS_Campaigns_Controller', AIPS_Ajax_Registry::get_controller_for('aips_campaign_wizard_validate_step'));
		$this->assertSame('AIPS_Campaigns_Controller', AIPS_Ajax_Registry::get_controller_for('aips_campaign_wizard_finalize'));
	}

	public function test_campaign_lifecycle_actions_route_to_campaigns_controller() {
		$this->assertSame('AIPS_Campaigns_Controller', AIPS_Ajax_Registry::get_controller_for('aips_toggle_campaign'));
		$this->assertSame('AIPS_Campaigns_Controller', AIPS_Ajax_Registry::get_controller_for('aips_duplicate_campaign'));
		$this->assertSame('AIPS_Campaigns_Controller', AIPS_Ajax_Registry::get_controller_for('aips_archive_campaign'));
		$this->assertSame('AIPS_Campaigns_Controller', AIPS_Ajax_Registry::get_controller_for('aips_restore_campaign'));
		$this->assertSame('AIPS_Campaigns_Controller', AIPS_Ajax_Registry::get_controller_for('aips_delete_campaign'));
	}
}
