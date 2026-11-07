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
	<ul class="aips-status-list">
		<li class="aips-status-list-item">
			<span><?php esc_html_e('AI Provider', 'ai-post-scheduler'); ?></span>
			<?php if (!empty($ai_provider_available)): ?>
				<span class="aips-badge aips-badge-success"><?php esc_html_e('Connected', 'ai-post-scheduler'); ?></span>
			<?php else: ?>
				<span class="aips-badge aips-badge-error"><?php esc_html_e('Not Found', 'ai-post-scheduler'); ?></span>
			<?php endif; ?>
		</li>
		<li class="aips-status-list-item">
			<span><?php esc_html_e('Topics in Queue', 'ai-post-scheduler'); ?></span>
			<strong><?php echo esc_html($topics_in_queue ?? 0); ?></strong>
		</li>
		<li class="aips-status-list-item">
			<span><?php esc_html_e('Partial Generations', 'ai-post-scheduler'); ?></span>
			<strong><?php echo esc_html($partial_generations ?? 0); ?></strong>
		</li>
	</ul>
	<div class="aips-status-action-buttons">
		<a href="<?php echo esc_url(admin_url('admin.php?page=aips-diagnostics')); ?>" class="button button-secondary button-small aips-status-btn-full">
			<?php esc_html_e('Diagnostics', 'ai-post-scheduler'); ?>
		</a>
		<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings')); ?>" class="button button-secondary button-small aips-status-btn-full">
			<?php esc_html_e('Settings', 'ai-post-scheduler'); ?>
		</a>
	</div>
</div>
