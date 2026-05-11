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
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$repository  = AIPS_Prompt_Section_Repository::instance();
		$post_slices = $repository->get_all(false);

		require AIPS_PLUGIN_DIR . 'templates/admin/post-slices.php';
	}
}
