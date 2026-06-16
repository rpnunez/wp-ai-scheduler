<?php
/**
 * Campaign Detail Admin Template
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

$campaigns_url       = admin_url('admin.php?page=aips-campaigns');
$schedule_url        = add_query_arg(array('page' => 'aips-schedule', 'campaign_id' => absint($campaign->id)), admin_url('admin.php'));
$generated_posts_url = add_query_arg(array('page' => 'aips-generated-posts', 'campaign_id' => absint($campaign->id)), admin_url('admin.php'));
$primary_schedule_id = !empty($campaign->primary_schedule_id) ? absint($campaign->primary_schedule_id) : 0;
$status_label        = __('Paused', 'ai-post-scheduler');
$status_class        = 'aips-badge-warning';

if ((int) $campaign->is_archived === 1) {
	$status_label = __('Archived', 'ai-post-scheduler');
	$status_class = 'aips-badge-secondary';
} elseif ((int) $campaign->is_active === 1) {
	$status_label = __('Active', 'ai-post-scheduler');
	$status_class = 'aips-badge-success';
}

$date_format = get_option('date_format');
$time_format = get_option('time_format');

$format_campaign_datetime = static function($timestamp, $empty_label) use ($date_format, $time_format) {
	if (empty($timestamp)) {
		return $empty_label;
	}

	return AIPS_DateTime::formatRelativeOrAbsolute($timestamp, $date_format . ' ' . $time_format);
};

$activity_summary = static function($details) {
	$details = is_string($details) ? $details : '';
	$decoded = json_decode($details, true);
	if (is_array($decoded)) {
		foreach (array('message', 'event_type', 'action', 'status') as $key) {
			if (!empty($decoded[$key]) && is_scalar($decoded[$key])) {
				return (string) $decoded[$key];
			}
		}
	}

	$details = wp_strip_all_tags($details);
	return $details ? wp_html_excerpt($details, 160, '&hellip;') : __('No details recorded.', 'ai-post-scheduler');
};
?>
<div class="wrap aips-wrap aips-admin-page">
	<div class="aips-page-container aips-campaigns-page">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php echo esc_html($campaign->name); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Campaign overview, health, activity, and settings.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<a href="<?php echo esc_url($campaigns_url); ?>" class="aips-btn aips-btn-secondary"><?php esc_html_e('Back to Campaigns', 'ai-post-scheduler'); ?></a>
				</div>
			</div>
		</div>

		<?php if (!empty($detail_notice) && !empty($detail_notice['message'])) : ?>
			<div class="notice notice-<?php echo 'error' === $detail_notice['type'] ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html($detail_notice['message']); ?></p></div>
		<?php endif; ?>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Campaign Health', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body">
				<div class="aips-stats-grid">
					<div class="aips-stat-card">
						<span class="aips-stat-label"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></span>
						<span class="aips-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
					</div>
					<div class="aips-stat-card">
						<span class="aips-stat-label"><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></span>
						<strong><?php echo esc_html($format_campaign_datetime($campaign->next_run, __('Not scheduled', 'ai-post-scheduler'))); ?></strong>
					</div>
					<div class="aips-stat-card">
						<span class="aips-stat-label"><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></span>
						<strong><?php echo esc_html($format_campaign_datetime($campaign->last_run, __('Never', 'ai-post-scheduler'))); ?></strong>
					</div>
					<div class="aips-stat-card">
						<span class="aips-stat-label"><?php esc_html_e('Schedules', 'ai-post-scheduler'); ?></span>
						<strong><?php echo esc_html((int) $campaign->linked_schedule_count); ?></strong>
					</div>
					<div class="aips-stat-card">
						<span class="aips-stat-label"><?php esc_html_e('Failed Generations', 'ai-post-scheduler'); ?></span>
						<strong><?php echo esc_html((int) $campaign_health['failed_generation_count']); ?></strong>
					</div>
					<div class="aips-stat-card">
						<span class="aips-stat-label"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></span>
						<strong><?php echo esc_html((int) $campaign_health['pending_review_count']); ?></strong>
					</div>
				</div>
			</div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Quick Actions', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body">
				<div class="aips-page-actions">
					<button type="button" class="aips-btn aips-btn-primary aips-campaign-run-now" data-schedule-id="<?php echo esc_attr($primary_schedule_id); ?>" <?php disabled(!$primary_schedule_id || (int) $campaign->is_archived === 1); ?>><?php esc_html_e('Run Now', 'ai-post-scheduler'); ?></button>
					<?php if ((int) $campaign->is_archived === 1) : ?>
						<button type="button" class="aips-btn aips-btn-secondary aips-restore-campaign" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>"><?php esc_html_e('Restore', 'ai-post-scheduler'); ?></button>
					<?php else : ?>
						<button type="button" class="aips-btn aips-btn-secondary aips-toggle-campaign" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>" data-is-active="<?php echo esc_attr((int) $campaign->is_active); ?>"><?php echo (int) $campaign->is_active === 1 ? esc_html__('Pause', 'ai-post-scheduler') : esc_html__('Resume', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-btn aips-btn-secondary aips-duplicate-campaign" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>"><?php esc_html_e('Duplicate', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-btn aips-btn-danger aips-archive-campaign" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>"><?php esc_html_e('Archive', 'ai-post-scheduler'); ?></button>
					<?php endif; ?>
					<a class="aips-btn aips-btn-secondary" href="<?php echo esc_url($schedule_url); ?>"><?php esc_html_e('View Schedule', 'ai-post-scheduler'); ?></a>
					<a class="aips-btn aips-btn-secondary" href="<?php echo esc_url($generated_posts_url); ?>"><?php esc_html_e('View Generated Posts', 'ai-post-scheduler'); ?></a>
				</div>
			</div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Generated Content', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body">
				<?php if (empty($recent_generated_posts)) : ?>
					<p class="aips-muted"><?php esc_html_e('No posts have been generated by this campaign yet.', 'ai-post-scheduler'); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e('Post', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Generated', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($recent_generated_posts as $post_item) : ?>
								<?php
								$post_title = $post_item->post_title ? $post_item->post_title : $post_item->generated_title;
								$edit_link  = get_edit_post_link((int) $post_item->post_id, 'raw');
								$view_link  = get_permalink((int) $post_item->post_id);
								?>
								<tr>
									<td><strong><?php echo esc_html($post_title ? $post_title : __('Untitled', 'ai-post-scheduler')); ?></strong></td>
									<td><?php echo esc_html(get_post_status_object($post_item->post_status) ? get_post_status_object($post_item->post_status)->label : $post_item->post_status); ?></td>
									<td><?php echo esc_html($format_campaign_datetime(!empty($post_item->completed_at) ? $post_item->completed_at : $post_item->created_at, __('Unknown', 'ai-post-scheduler'))); ?></td>
									<td><?php echo esc_html($post_item->template_name ? $post_item->template_name : __('Unknown template', 'ai-post-scheduler')); ?></td>
									<td>
										<?php if ($edit_link) : ?><a href="<?php echo esc_url($edit_link); ?>"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></a><?php endif; ?>
										<?php if ($edit_link && $view_link) : ?> | <?php endif; ?>
										<?php if ($view_link) : ?><a href="<?php echo esc_url($view_link); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View', 'ai-post-scheduler'); ?></a><?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<?php if (!empty($campaign_warnings)) : ?>
			<div class="aips-content-panel">
				<div class="aips-filter-bar"><strong><?php esc_html_e('Campaign Warnings', 'ai-post-scheduler'); ?></strong></div>
				<div class="aips-panel-body">
					<ul class="aips-warning-list">
						<?php foreach ($campaign_warnings as $warning) : ?>
							<li class="notice notice-warning inline"><p><?php echo esc_html($warning['message']); ?></p></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Settings', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body">
				<form method="post">
					<?php wp_nonce_field('aips_campaign_detail_save_' . absint($campaign->id), 'aips_campaign_detail_nonce'); ?>
					<p>
						<label for="campaign_name"><strong><?php esc_html_e('Campaign Name', 'ai-post-scheduler'); ?></strong></label><br />
						<input type="text" id="campaign_name" name="campaign_name" class="regular-text" value="<?php echo esc_attr($campaign->name); ?>" required />
					</p>
					<p>
						<label for="content_goal"><strong><?php esc_html_e('Content Goal', 'ai-post-scheduler'); ?></strong></label><br />
						<textarea id="content_goal" name="content_goal" rows="3" class="large-text"><?php echo esc_textarea($campaign->content_goal); ?></textarea>
					</p>
					<p><button type="submit" class="aips-btn aips-btn-primary"><?php esc_html_e('Save Campaign', 'ai-post-scheduler'); ?></button></p>
				</form>
			</div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Owned Resources', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body">
				<p><strong><?php esc_html_e('Templates:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html((int) count($templates)); ?></p>
				<p><strong><?php esc_html_e('Schedules:', 'ai-post-scheduler'); ?></strong> <?php echo esc_html((int) count($schedules)); ?></p>
				<?php if (!empty($templates)) : ?>
					<ul>
						<?php foreach ($templates as $template) : ?>
							<li><a href="<?php echo esc_url(add_query_arg(array('page' => 'aips-templates', 'edit' => absint($template->id)), admin_url('admin.php'))); ?>"><?php echo esc_html($template->name); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Recent Activity', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body">
				<?php if (empty($recent_activity)) : ?>
					<p class="aips-muted"><?php esc_html_e('No campaign activity has been recorded yet.', 'ai-post-scheduler'); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e('When', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Source', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Details', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($recent_activity as $activity) : ?>
								<tr>
									<td><?php echo esc_html($format_campaign_datetime($activity->activity_timestamp, __('Unknown', 'ai-post-scheduler'))); ?></td>
									<td><?php echo 'history_log' === $activity->activity_source ? esc_html__('History Log', 'ai-post-scheduler') : esc_html__('History', 'ai-post-scheduler'); ?></td>
									<td><code><?php echo esc_html($activity->activity_type); ?></code></td>
									<td><?php echo esc_html($activity_summary($activity->activity_details)); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
