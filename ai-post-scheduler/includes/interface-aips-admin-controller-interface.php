<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shared contract for admin-entry controllers.
 *
 * Existing controllers may ignore `$embedded` until migrated.
 */
interface AIPS_Admin_Controller_Interface {

	/**
	 * Render the controller page output.
	 *
	 * @param bool $embedded Whether to render in embedded mode.
	 * @return void
	 */
	public function render_page($embedded = false);
}
