<?php
if (!defined('ABSPATH')) {
	exit;
}

// Get campaigns data
$campaigns_repo = AIPS_Campaigns_Repository::instance();
$campaigns = $campaigns_repo->get_all_campaigns();
$stats = $campaigns_repo->get_summary_stats();

?>
<div class="wrap aips-admin-page">
	<h1 class="wp-heading-inline"><?php esc_html_e('Campaigns', 'ai-post-scheduler'); ?></h1>
	<a href="<?php echo esc_url(admin_url('admin.php?page=aips-campaign-wizard')); ?>" class="page-title-action"><?php esc_html_e('Create New Campaign', 'ai-post-scheduler'); ?></a>
	<hr class="wp-header-end">

	<div class="aips-campaigns-stats" style="margin: 20px 0;">
		<div class="aips-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
			<div class="aips-stat-card" style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
				<h3 style="margin: 0 0 10px; font-size: 14px; color: #666;"><?php esc_html_e('Total Campaigns', 'ai-post-scheduler'); ?></h3>
				<p style="margin: 0; font-size: 32px; font-weight: 600;"><?php echo esc_html($stats['total']); ?></p>
			</div>
			<div class="aips-stat-card" style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
				<h3 style="margin: 0 0 10px; font-size: 14px; color: #666;"><?php esc_html_e('Active Campaigns', 'ai-post-scheduler'); ?></h3>
				<p style="margin: 0; font-size: 32px; font-weight: 600; color: #46b450;"><?php echo esc_html($stats['active']); ?></p>
			</div>
			<div class="aips-stat-card" style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
				<h3 style="margin: 0 0 10px; font-size: 14px; color: #666;"><?php esc_html_e('Archived', 'ai-post-scheduler'); ?></h3>
				<p style="margin: 0; font-size: 32px; font-weight: 600; color: #999;"><?php echo esc_html($stats['archived']); ?></p>
			</div>
		</div>
	</div>

	<div id="aips-campaigns-list">
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Campaign', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Success Rate', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
					<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($campaigns)) : ?>
					<tr>
						<td colspan="9" style="text-align: center; padding: 40px 20px;">
							<p><?php esc_html_e('No campaigns found.', 'ai-post-scheduler'); ?></p>
							<a href="<?php echo esc_url(admin_url('admin.php?page=aips-campaign-wizard')); ?>" class="button button-primary"><?php esc_html_e('Create Your First Campaign', 'ai-post-scheduler'); ?></a>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ($campaigns as $campaign) :
						$total_posts = $campaign->posts_generated + $campaign->posts_failed;
						$success_rate = $total_posts > 0 ? round(($campaign->posts_generated / $total_posts) * 100, 1) : 0;
						$status_class = $campaign->is_active ? 'active' : 'paused';
						?>
						<tr data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
							<td>
								<strong><?php echo esc_html($campaign->title ?: $campaign->template_name ?: __('Untitled Campaign', 'ai-post-scheduler')); ?></strong>
								<?php if ($campaign->author_name) : ?>
									<br><small><?php printf(esc_html__('Author: %s', 'ai-post-scheduler'), esc_html($campaign->author_name)); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$mode_label = $campaign->campaign_mode === 'author' ? __('Author-Based', 'ai-post-scheduler') : __('Template-Based', 'ai-post-scheduler');
								echo esc_html($mode_label);
								?>
							</td>
							<td>
								<?php
								$schedules = wp_get_schedules();
								$freq_label = isset($schedules[$campaign->frequency]) ? $schedules[$campaign->frequency]['display'] : esc_html($campaign->frequency);
								echo esc_html($freq_label);
								?>
							</td>
							<td><?php echo esc_html($campaign->posts_generated); ?></td>
							<td>
								<span style="color: <?php echo $success_rate >= 80 ? '#46b450' : ($success_rate >= 50 ? '#ffb900' : '#dc3232'); ?>">
									<?php echo esc_html($success_rate); ?>%
								</span>
							</td>
							<td>
								<?php
								if ($campaign->last_run && $campaign->last_run > 0) {
									echo esc_html(human_time_diff($campaign->last_run, time()) . ' ago');
								} else {
									echo esc_html__('Never', 'ai-post-scheduler');
								}
								?>
							</td>
							<td>
								<?php
								if ($campaign->next_run && $campaign->next_run > 0) {
									$diff = $campaign->next_run - time();
									if ($diff > 0) {
										echo esc_html(sprintf(__('In %s', 'ai-post-scheduler'), human_time_diff(time(), $campaign->next_run)));
									} else {
										echo '<strong>' . esc_html__('Past due', 'ai-post-scheduler') . '</strong>';
									}
								} else {
									echo esc_html__('Not scheduled', 'ai-post-scheduler');
								}
								?>
							</td>
							<td>
								<span class="aips-campaign-status aips-status-<?php echo esc_attr($status_class); ?>">
									<?php echo $campaign->is_active ? esc_html__('Active', 'ai-post-scheduler') : esc_html__('Paused', 'ai-post-scheduler'); ?>
								</span>
							</td>
							<td class="aips-campaign-actions">
								<button type="button" class="button button-small aips-toggle-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>" data-is-active="<?php echo esc_attr($campaign->is_active); ?>">
									<?php echo $campaign->is_active ? esc_html__('Pause', 'ai-post-scheduler') : esc_html__('Resume', 'ai-post-scheduler'); ?>
								</button>
								<button type="button" class="button button-small aips-duplicate-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
									<?php esc_html_e('Duplicate', 'ai-post-scheduler'); ?>
								</button>
								<button type="button" class="button button-small button-link-delete aips-archive-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
									<?php esc_html_e('Archive', 'ai-post-scheduler'); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<style>
.aips-status-active {
	color: #46b450;
	font-weight: 600;
}
.aips-status-paused {
	color: #999;
}
.aips-campaign-actions {
	white-space: nowrap;
}
.aips-campaign-actions .button {
	margin-right: 5px;
}
</style>
