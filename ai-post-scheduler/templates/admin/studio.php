<?php
/**
 * Studio Admin Template (Rail Navigation & Workspace)
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

$sections      = AIPS_Studio_Controller::get_sections();
$tabs          = $studio_controller->get_tabs($active_section);
$effective_tab = empty($active_section) ? 'launchpad' : $active_section;

$rail_items = array();
foreach ($tabs as $tab_key => $tab) {
	$rail_items[] = array(
		'key'         => $tab_key,
		'label'       => $tab['label'],
		'icon'        => $tab['icon'],
		'description' => isset($tab['description']) ? $tab['description'] : '',
		'url'         => $studio_controller->get_section_url('launchpad' === $tab_key ? '' : $tab_key),
		'active'      => ($effective_tab === $tab_key),
	);
}
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

		<!-- Vertical Sidebar Rail Layout -->
		<div class="aips-rail-layout">
			<?php
			AIPS_Admin_UI_Primitives::render_rail(array(
				'aria_label' => __('Studio Navigation', 'ai-post-scheduler'),
				'items'      => $rail_items,
			));
			?>

			<main class="aips-rail-main">
				<div class="aips-studio-stage">
					<?php if (empty($active_section) || 'launchpad' === $active_section) : ?>
						<!-- LAUNCHPAD GRID VIEW -->
						<div class="aips-launchpad-grid">
							<?php foreach ($sections as $sec_key => $sec_data) : ?>
								<?php
								$sec_stats = isset($stats[$sec_key]) ? $stats[$sec_key] : array('total' => 0, 'active' => 0);
								$sec_url   = $studio_controller->get_section_url($sec_key);
								?>
								<div class="aips-launchpad-card aips-card-<?php echo esc_attr($sec_key); ?>">
									<div class="aips-card-header">
										<div class="aips-card-icon-wrap">
											<span class="dashicons <?php echo esc_attr($sec_data['icon']); ?>" aria-hidden="true"></span>
										</div>
										<div class="aips-card-title-group">
											<h2 class="aips-card-title"><?php echo esc_html($sec_data['label']); ?></h2>
											<div class="aips-card-badges">
												<span class="aips-badge aips-badge-primary">
													<?php echo sprintf(esc_html__('%d Total', 'ai-post-scheduler'), (int) $sec_stats['total']); ?>
												</span>
												<?php if ($sec_stats['active'] > 0) : ?>
													<span class="aips-badge aips-badge-success">
														<?php echo sprintf(esc_html__('%d Active', 'ai-post-scheduler'), (int) $sec_stats['active']); ?>
													</span>
												<?php endif; ?>
											</div>
										</div>
									</div>

									<p class="aips-card-desc"><?php echo esc_html($sec_data['description']); ?></p>

									<div class="aips-card-footer">
										<a href="<?php echo esc_url($sec_url); ?>" class="aips-btn aips-btn-primary aips-card-btn-open">
											<span><?php echo sprintf(esc_html__('Manage %s', 'ai-post-scheduler'), esc_html($sec_data['label'])); ?></span>
											<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
										</a>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<!-- FOCUSED SECTION WORKSPACE VIEW -->
						<div class="aips-studio-section-canvas">
							<?php $studio_controller->render_section_content($active_section); ?>
						</div>
					<?php endif; ?>
				</div>
			</main>
		</div>

	</div>
</div>
