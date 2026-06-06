<?php
/**
 * Automations Admin Template
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap aips-automations-wrap">
	<div class="aips-page-container">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Automations', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Manage schedules, campaigns, templates, authors, sources, internal links, and taxonomy from one place.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>

		<div class="aips-tab-nav">
			<?php foreach ($tabs as $tab_key => $tab) : ?>
				<?php $tab_classes = 'aips-tab-link' . ($active_tab === $tab_key ? ' active' : ''); ?>
				<a href="<?php echo esc_url($automations_controller->get_tab_url($tab_key)); ?>" class="<?php echo esc_attr($tab_classes); ?>">
					<?php echo esc_html($tab['label']); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="aips-automations-tab-content">
			<?php $automations_controller->render_tab_content($active_tab); ?>
		</div>
	</div>
</div>
