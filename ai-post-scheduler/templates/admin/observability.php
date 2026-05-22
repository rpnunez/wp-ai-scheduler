<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Observability', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Monitor system health, operational trends, and request telemetry from one shared workspace.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>

		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Observability sections', 'ai-post-scheduler'); ?>">
			<?php foreach ($tabs as $tab_slug => $tab) : ?>
				<?php
				$classes = array('nav-tab');
				if ($tab_slug === $current_tab) {
					$classes[] = 'nav-tab-active';
				}
				?>
				<a class="<?php echo esc_attr(implode(' ', $classes)); ?>" href="<?php echo esc_url($tab['url']); ?>">
					<?php echo esc_html($tab['label']); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php foreach ($notices as $notice) : ?>
			<div class="notice notice-<?php echo esc_attr($notice['type']); ?> inline">
				<p><?php echo esc_html($notice['message']); ?></p>
			</div>
		<?php endforeach; ?>

		<div class="aips-observability-tab-panel">
			<?php $this->render_tab_content($current_tab); ?>
		</div>
	</div>
</div>
