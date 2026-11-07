<?php
/**
 * Dashboard Analytics Charts Meta Box Partial
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
<div class="aips-dashboard-charts-column aips-dashboard-charts-meta-wrap">
	<!-- Chart Box: AI Calls & AI Errors -->
	<div class="aips-content-panel glass-morphic">
		<div class="aips-panel-header">
			<h3 class="aips-panel-title"><?php esc_html_e('AI Calls & Errors by Day', 'ai-post-scheduler'); ?></h3>
		</div>
		<div class="aips-panel-body">
			<div class="aips-dashboard-chart-wrap">
				<canvas id="aips-chart-ai-calls" aria-label="<?php esc_attr_e('AI Requests and Errors by Day', 'ai-post-scheduler'); ?>" role="img"></canvas>
			</div>
		</div>
	</div>

	<!-- Chart Box: Post Generations -->
	<div class="aips-content-panel glass-morphic">
		<div class="aips-panel-header">
			<h3 class="aips-panel-title"><?php esc_html_e('Post Generations by Day', 'ai-post-scheduler'); ?></h3>
		</div>
		<div class="aips-panel-body">
			<div class="aips-dashboard-chart-wrap">
				<canvas id="aips-chart-posts-by-day" aria-label="<?php esc_attr_e('Post Generations by Day', 'ai-post-scheduler'); ?>" role="img"></canvas>
			</div>
		</div>
	</div>

	<!-- Chart Box: Topic Generations -->
	<div class="aips-content-panel glass-morphic">
		<div class="aips-panel-header">
			<h3 class="aips-panel-title"><?php esc_html_e('Topic Generations by Day', 'ai-post-scheduler'); ?></h3>
		</div>
		<div class="aips-panel-body">
			<div class="aips-dashboard-chart-wrap">
				<canvas id="aips-chart-topics-by-day" aria-label="<?php esc_attr_e('Topic Generations by Day', 'ai-post-scheduler'); ?>" role="img"></canvas>
			</div>
		</div>
	</div>

	<!-- Chart Box: AI Error Rate -->
	<div class="aips-content-panel glass-morphic">
		<div class="aips-panel-header">
			<h3 class="aips-panel-title"><?php esc_html_e('AI Error Rate (%)', 'ai-post-scheduler'); ?></h3>
		</div>
		<div class="aips-panel-body">
			<div class="aips-dashboard-chart-wrap">
				<canvas id="aips-chart-error-rate" aria-label="<?php esc_attr_e('AI Error Rate', 'ai-post-scheduler'); ?>" role="img"></canvas>
			</div>
		</div>
	</div>
</div>
