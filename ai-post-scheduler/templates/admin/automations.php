<?php
/**
 * Automations Admin Template
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var AIPS_Automations_Controller $automations_controller */
/** @var string $active_tab */
/** @var array<int, array<string, mixed>> $tab_actions */
?>
<div class="wrap aips-wrap aips-automations-wrap">
	<div class="aips-page-container">

		<!-- Page Header -->
		<?php
		if (isset($page_context) && $page_context instanceof AIPS_Admin_Page_Context) {
			AIPS_Admin_UI_Primitives::render_page_header($page_context);
		} else {
			AIPS_Admin_UI_Primitives::render_page_header(array(
				'title'       => __('Automations', 'ai-post-scheduler'),
				'icon'        => 'dashicons-rest-api',
				'description' => __('Orchestrate generation schedules, goal-based campaigns, authors, data sources, monetization, and SEO linking.', 'ai-post-scheduler'),
				'actions'     => !empty($tab_actions) ? $tab_actions : array(),
			));
		}
		?>

			<main class="aips-hub-main">
				<div class="aips-automations-stage">
					<?php $automations_controller->render_tab_content($active_tab); ?>
				</div>
			</main>
	</div>
</div>
