<?php
/**
 * Studio Admin Template (Launchpad Grid & Focused Workspace)
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

$sections = AIPS_Studio_Controller::SECTIONS;
?>

<div class="wrap aips-wrap aips-studio-wrap">
	<div class="aips-page-container">

		<?php if (empty($active_section)) : ?>
			<!-- STUDIO LAUNCHPAD VIEW -->
			<div class="aips-page-header">
				<div class="aips-page-header-top">
					<div>
						<h1 class="aips-page-title">
							<span class="dashicons dashicons-art" style="font-size:28px;width:28px;height:28px;vertical-align:middle;margin-right:8px;color:#2271b1;"></span>
							<?php esc_html_e('Content Studio', 'ai-post-scheduler'); ?>
						</h1>
						<p class="aips-page-description">
							<?php esc_html_e('Design and fine-tune your generative AI building blocks — templates, brand voices, article structures, and modular content slices.', 'ai-post-scheduler'); ?>
						</p>
					</div>
				</div>
			</div>

			<div class="aips-launchpad-grid">
				<?php foreach ($sections as $sec_key => $sec_data) : ?>
					<?php
					$sec_stats = isset($stats[$sec_key]) ? $stats[$sec_key] : array('total' => 0, 'active' => 0);
					$sec_url = $studio_controller->get_section_url($sec_key);
					?>
					<div class="aips-launchpad-card aips-card-<?php echo esc_attr($sec_key); ?>">
						<div class="aips-card-header">
							<div class="aips-card-icon-wrap">
								<span class="dashicons <?php echo esc_attr($sec_data['icon']); ?>"></span>
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
								<span class="dashicons dashicons-arrow-right-alt2"></span>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

		<?php else : ?>
			<!-- FOCUSED SECTION WORKSPACE VIEW -->
			<?php $current_sec = $sections[$active_section]; ?>
			<div class="aips-workspace-header">
				<div class="aips-workspace-header-top">
					<div class="aips-breadcrumb-trail">
						<a href="<?php echo esc_url($studio_controller->get_section_url('')); ?>" class="aips-breadcrumb-root">
							<span class="dashicons dashicons-art"></span>
							<?php esc_html_e('Studio', 'ai-post-scheduler'); ?>
						</a>
						<span class="aips-breadcrumb-sep">/</span>
						<span class="aips-breadcrumb-current"><?php echo esc_html($current_sec['label']); ?></span>
					</div>

					<!-- Section Quick Switcher -->
					<div class="aips-workspace-controls">
						<div class="aips-switcher-pills">
							<a href="<?php echo esc_url($studio_controller->get_section_url('')); ?>" class="aips-pill-btn" title="<?php esc_attr_e('Launchpad Overview', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-grid-view"></span>
							</a>
							<?php foreach ($sections as $k => $s) : ?>
								<a href="<?php echo esc_url($studio_controller->get_section_url($k)); ?>" class="aips-pill-btn<?php echo $k === $active_section ? ' active' : ''; ?>">
									<span class="dashicons <?php echo esc_attr($s['icon']); ?>"></span>
									<span class="aips-pill-label"><?php echo esc_html($s['label']); ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Section Content -->
			<div class="aips-studio-section-canvas">
				<?php $studio_controller->render_section_content($active_section); ?>
			</div>

		<?php endif; ?>

	</div>
</div>
