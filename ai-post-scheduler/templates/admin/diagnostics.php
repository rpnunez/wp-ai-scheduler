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
/** @var array<string, array{label:string, icon:string, description?:string}> $tabs */

$rail_items = array();
foreach ($tabs as $tab_key => $tab) {
	$rail_items[] = array(
		'key'         => $tab_key,
		'label'       => $tab['label'],
		'icon'        => !empty($tab['icon']) ? $tab['icon'] : 'dashicons-admin-generic',
		'description' => !empty($tab['description']) ? $tab['description'] : '',
		'url'         => $diagnostics_controller->get_tab_url($tab_key),
		'active'      => ($active_tab === $tab_key),
	);
}
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

		<!-- Vertical Sidebar Rail Layout -->
		<div class="aips-rail-layout">
			<?php
			AIPS_Admin_UI_Primitives::render_rail(array(
				'aria_label' => __('Diagnostics Navigation', 'ai-post-scheduler'),
				'items'      => $rail_items,
			));
			?>

			<main class="aips-rail-main">
				<div class="aips-diagnostics-stage">
					<?php $diagnostics_controller->render_tab_content($active_tab); ?>
				</div>
			</main>
		</div><!-- /.aips-rail-layout -->
	</div>
</div>
