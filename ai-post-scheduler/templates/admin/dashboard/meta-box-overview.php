<?php
/**
 * Dashboard Overview Meta Box Partial
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
<div class="aips-grid aips-grid-cols-4 aips-summary-grid">
	<!-- Stats Box: Completed Posts -->
	<div class="aips-stat-card glass-morphic aips-stat-success">
		<div class="aips-stat-icon-wrap">
			<span class="dashicons dashicons-admin-post"></span>
		</div>
		<div class="aips-stat-content">
			<span class="aips-stat-label"><?php esc_html_e('Posts Completed', 'ai-post-scheduler'); ?></span>
			<strong class="aips-stat-value"><?php echo esc_html($completed_in_period ?? 0); ?></strong>
			<span class="aips-stat-sub-meta">
				<?php echo sprintf(__('Success Rate: %s%%', 'ai-post-scheduler'), esc_html($success_rate_in_period ?? 0)); ?>
			</span>
		</div>
	</div>

	<!-- Stats Box: Partial & Failed -->
	<div class="aips-stat-card glass-morphic aips-stat-danger">
		<div class="aips-stat-icon-wrap">
			<span class="dashicons dashicons-warning"></span>
		</div>
		<div class="aips-stat-content">
			<span class="aips-stat-label"><?php esc_html_e('Unsuccessful Attempts', 'ai-post-scheduler'); ?></span>
			<strong class="aips-stat-value"><?php echo esc_html(($failed_in_period ?? 0) + ($partial_in_period ?? 0)); ?></strong>
			<span class="aips-stat-sub-meta">
				<?php echo sprintf(__('%d Failed, %d Partial', 'ai-post-scheduler'), esc_html($failed_in_period ?? 0), esc_html($partial_in_period ?? 0)); ?>
			</span>
		</div>
	</div>

	<!-- Stats Box: AI Activity -->
	<div class="aips-stat-card glass-morphic aips-stat-info">
		<div class="aips-stat-icon-wrap">
			<span class="dashicons dashicons-cloud"></span>
		</div>
		<div class="aips-stat-content">
			<span class="aips-stat-label"><?php esc_html_e('AI Requests & Calls', 'ai-post-scheduler'); ?></span>
			<strong class="aips-stat-value"><?php echo esc_html($ai_calls_in_period ?? 0); ?></strong>
			<span class="aips-stat-sub-meta">
				<?php echo sprintf(__('Error Rate: %s%% (%d AI call errors)', 'ai-post-scheduler'), esc_html($ai_error_rate_in_period ?? 0), esc_html($ai_errors_in_period ?? 0)); ?>
			</span>
		</div>
	</div>

	<!-- Stats Box: Topics Generated -->
	<div class="aips-stat-card glass-morphic aips-stat-warning">
		<div class="aips-stat-icon-wrap">
			<span class="dashicons dashicons-lightbulb"></span>
		</div>
		<div class="aips-stat-content">
			<span class="aips-stat-label"><?php esc_html_e('Author Topics Created', 'ai-post-scheduler'); ?></span>
			<strong class="aips-stat-value"><?php echo esc_html($topics_created_in_period ?? 0); ?></strong>
			<span class="aips-stat-sub-meta">
				<?php echo sprintf(__('%d Pending Review', 'ai-post-scheduler'), esc_html($topics_pending_in_period ?? 0)); ?>
			</span>
		</div>
	</div>
</div>
