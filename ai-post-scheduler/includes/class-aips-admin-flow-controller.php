<?php
/**
 * Admin campaign flow controller.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Admin_Flow_Controller extends AIPS_Campaigns_Controller {

	const PAGE_SLUG = AIPS_Campaigns_Controller::PAGE_SLUG;

	public function render_page() {
		$this->render_wizard_page();
	}
}
