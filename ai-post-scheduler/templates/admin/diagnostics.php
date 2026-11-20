<?php
/**
 * Diagnostics Admin Template
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var AIPS_Diagnostics_Controller $diagnostics_controller */
/** @var string $active_tab */
?>
<div class="wrap aips-wrap aips-diagnostics-wrap">
	<div class="aips-page-container">
		<?php
		if (isset($page_context) && $page_context instanceof AIPS_Admin_Page_Context) {
			AIPS_Admin_UI_Primitives::render_page_header($page_context);
		} else {
			AIPS_Admin_UI_Primitives::render_page_header(array(
				'title'       => __('Diagnostics', 'ai-post-scheduler'),
				'icon'        => 'dashicons-admin-tools',
				'description' => __('Review system health, generation operations, telemetry, seeding utilities, and developer tools from one place.', 'ai-post-scheduler'),
			));
		}
		?>

			<main class="aips-hub-main">
				<div class="aips-diagnostics-stage">
					<?php $diagnostics_controller->render_tab_content($active_tab); ?>
				</div>
			</main>
	</div>
</div>
