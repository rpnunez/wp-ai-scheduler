<?php
/**
 * Studio Admin Template
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var AIPS_Studio_Controller $studio_controller */
/** @var string $active_section */
/** @var array<string, array{total:int, active:int}> $stats */
/** @var AIPS_Admin_Page_Context $page_context */

?>

<div class="wrap aips-wrap aips-studio-wrap">
	<div class="aips-page-container">

		<!-- Page Header -->
		<?php
		if (isset($page_context) && $page_context instanceof AIPS_Admin_Page_Context) {
			AIPS_Admin_UI_Primitives::render_page_header($page_context);
		} else {
			AIPS_Admin_UI_Primitives::render_page_header(array(
				'title'       => __('Studio', 'ai-post-scheduler'),
				'icon'        => 'dashicons-art',
				'description' => __('Craft templates, define brand voices, build article structures, and configure modular post slices.', 'ai-post-scheduler'),
			));
		}
		?>

		<main class="aips-hub-main">
			<div class="aips-studio-stage">
				<div class="aips-studio-section-canvas">
					<?php $studio_controller->render_section_content($active_section); ?>
				</div>
			</div>
		</main>

	</div>
</div>
