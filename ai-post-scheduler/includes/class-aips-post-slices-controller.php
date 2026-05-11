<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Post Slices admin page controller.
 */
class AIPS_Post_Slices_Controller {

	/**
	 * Render the Post Slices page.
	 *
	 * @return void
	 */
	public function render_page() {
		require AIPS_PLUGIN_DIR . 'templates/admin/post-slices.php';
	}
}
