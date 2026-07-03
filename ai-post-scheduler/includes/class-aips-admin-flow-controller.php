<?php
/**
 * Admin campaign flow controller.
 *
 * @TODO: CHeck if this file is being used -- I believe it is deprecated. - RN 06/21/2026
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Admin_Flow_Controller extends AIPS_Campaigns_Controller {

	const PAGE_SLUG = AIPS_Campaigns_Controller::PAGE_SLUG;

	public function render_page($embedded = false) {
		$this->render_wizard_page();
	}
}
