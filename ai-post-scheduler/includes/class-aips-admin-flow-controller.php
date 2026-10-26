<?php
/**
 * Admin campaign flow controller.
 *
 * @deprecated 3.8.0 Use AIPS_Campaigns_Controller directly. Scheduled for removal in 4.0.0.
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Flow_Controller
 *
 * Compatibility alias / wrapper for legacy campaign flow routing.
 *
 * @deprecated 3.8.0 Use AIPS_Campaigns_Controller directly. Scheduled for removal in 4.0.0.
 */
class AIPS_Admin_Flow_Controller extends AIPS_Campaigns_Controller {

	const PAGE_SLUG = AIPS_Campaigns_Controller::PAGE_SLUG;

	/**
	 * Constructor.
	 *
	 * Emits a deprecation notice when instantiated.
	 *
	 * @param mixed ...$args Forwarded arguments.
	 */
	public function __construct(...$args) {
		if (function_exists('_deprecated_constructor')) {
			_deprecated_constructor(__CLASS__, '3.8.0', 'AIPS_Campaigns_Controller');
		}
		parent::__construct(...$args);
	}

	public function render_page() {
		$this->render_wizard_page();
	}
}
