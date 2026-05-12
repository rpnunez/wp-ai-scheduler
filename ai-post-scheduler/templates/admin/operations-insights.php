<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-admin-wrap">
	<h1><?php esc_html_e('Operations Insights', 'ai-post-scheduler'); ?></h1>
	<p><?php echo esc_html(sprintf(__('Last %d days of generation operations data.', 'ai-post-scheduler'), $days)); ?></p>

	<?php if (!$telemetry_enabled) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e('Telemetry is disabled. Retry insights are limited to history log data only.', 'ai-post-scheduler'); ?></p></div>
	<?php endif; ?>

	<h2><?php esc_html_e('Success / Failure Trend', 'ai-post-scheduler'); ?></h2>
	<table class="widefat striped"><thead><tr><th><?php esc_html_e('Day', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Success', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Failure', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
	<?php foreach ($history_trend as $row) : ?>
		<tr><td><?php echo esc_html($row['metric_date']); ?></td><td><?php echo esc_html((string) $row['success_count']); ?></td><td><?php echo esc_html((string) $row['failure_count']); ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>

	<h2><?php esc_html_e('Average Duration by Flow Type', 'ai-post-scheduler'); ?></h2>
	<table class="widefat striped"><thead><tr><th><?php esc_html_e('Flow', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Avg Seconds', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Samples', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
	<?php foreach ($duration_by_flow as $row) : ?>
		<tr><td><?php echo esc_html($row['flow_type']); ?></td><td><?php echo esc_html(number_format((float) $row['avg_duration_seconds'], 2)); ?></td><td><?php echo esc_html((string) $row['sample_count']); ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>

	<h2><?php esc_html_e('Retry Counts by Hook/Service', 'ai-post-scheduler'); ?></h2>
	<table class="widefat striped"><thead><tr><th><?php esc_html_e('Service', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Retries', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
	<?php foreach ($retry_counts as $row) : ?>
		<tr><td><?php echo esc_html($row['service_key']); ?></td><td><?php echo esc_html((string) $row['retry_count']); ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>

	<h2><?php esc_html_e('Most Common Failure Reasons', 'ai-post-scheduler'); ?></h2>
	<table class="widefat striped"><thead><tr><th><?php esc_html_e('Reason', 'ai-post-scheduler'); ?></th><th><?php esc_html_e('Count', 'ai-post-scheduler'); ?></th></tr></thead><tbody>
	<?php foreach ($failure_reasons as $row) : ?>
		<tr><td><?php echo esc_html($row['reason']); ?></td><td><?php echo esc_html((string) $row['failure_count']); ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>

	<h2><?php esc_html_e('Recommended Actions', 'ai-post-scheduler'); ?></h2>
	<ul>
		<?php foreach ($recommended_actions as $action) : ?>
			<li><?php echo esc_html($action); ?></li>
		<?php endforeach; ?>
	</ul>

	<form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<input type="hidden" name="action" value="aips_operations_insights_export" />
		<input type="hidden" name="days" value="<?php echo esc_attr((string) $days); ?>" />
		<?php wp_nonce_field('aips_operations_insights_export'); ?>
		<button class="button" name="format" value="csv"><?php esc_html_e('Export CSV', 'ai-post-scheduler'); ?></button>
		<button class="button button-primary" name="format" value="json"><?php esc_html_e('Export JSON', 'ai-post-scheduler'); ?></button>
	</form>
</div>
