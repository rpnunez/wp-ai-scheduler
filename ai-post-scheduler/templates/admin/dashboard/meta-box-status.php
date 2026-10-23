<?php
/**
 * Dashboard System Status & AI Provider Health Meta Box Partial
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

if (is_array($data)) {
	extract($data);
}
?>
<div class="aips-dashboard-status-content">
	<ul class="aips-status-list" style="margin: 0; padding: 0; list-style: none;">
		<li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f1;">
			<span><?php esc_html_e('AI Provider', 'ai-post-scheduler'); ?></span>
			<?php if (!empty($ai_provider_available)): ?>
				<span class="aips-badge aips-badge-success"><?php esc_html_e('Connected', 'ai-post-scheduler'); ?></span>
			<?php else: ?>
				<span class="aips-badge aips-badge-error"><?php esc_html_e('Not Found', 'ai-post-scheduler'); ?></span>
			<?php endif; ?>
		</li>
		<li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f1;">
			<span><?php esc_html_e('Topics in Queue', 'ai-post-scheduler'); ?></span>
			<strong><?php echo esc_html($topics_in_queue ?? 0); ?></strong>
		</li>
		<li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f1;">
			<span><?php esc_html_e('Partial Generations', 'ai-post-scheduler'); ?></span>
			<strong><?php echo esc_html($partial_generations ?? 0); ?></strong>
		</li>
	</ul>
	<div style="margin-top: 14px; display: flex; gap: 8px;">
		<a href="<?php echo esc_url(admin_url('admin.php?page=aips-diagnostics')); ?>" class="button button-secondary button-small" style="width: 100%; text-align: center;">
			<?php esc_html_e('Diagnostics', 'ai-post-scheduler'); ?>
		</a>
		<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings')); ?>" class="button button-secondary button-small" style="width: 100%; text-align: center;">
			<?php esc_html_e('Settings', 'ai-post-scheduler'); ?>
		</a>
	</div>
</div>
