<?php
/**
 * Campaign Detail Admin Template
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Helper: render a human-readable frequency label.
 */
if (!function_exists('aips_frequency_label')) {
	function aips_frequency_label($frequency) {
		if (empty($frequency)) {
			return __('—', 'ai-post-scheduler');
		}
		$schedules = wp_get_schedules();
		if (isset($schedules[$frequency])) {
			return $schedules[$frequency]['display'];
		}
		return ucfirst(str_replace('_', ' ', $frequency));
	}
}

/**
 * Helper: format a future/past timestamp as a relative countdown string.
 * Returns e.g. "In 2 hours 15 minutes", "In 6 days 4 hours", or "Past due".
 */
if (!function_exists('aips_next_run_relative')) {
	function aips_next_run_relative($timestamp) {
		$diff = $timestamp - time();
		if ($diff <= 0) {
			return __('Past due', 'ai-post-scheduler');
		}

		$units = array(
			array(
				'seconds'  => DAY_IN_SECONDS,
				'singular' => '%s day',
				'plural'   => '%s days',
			),
			array(
				'seconds'  => HOUR_IN_SECONDS,
				'singular' => '%s hour',
				'plural'   => '%s hours',
			),
			array(
				'seconds'  => MINUTE_IN_SECONDS,
				'singular' => '%s minute',
				'plural'   => '%s minutes',
			),
		);
		$parts = array();

		foreach ($units as $unit) {
			if ($diff < $unit['seconds']) {
				continue;
			}

			$value = (int) floor($diff / $unit['seconds']);
			if ($value <= 0) {
				continue;
			}

			$parts[] = sprintf(
				_n($unit['singular'], $unit['plural'], $value, 'ai-post-scheduler'),
				number_format_i18n($value)
			);
			$diff -= $value * $unit['seconds'];

			if (count($parts) === 2) {
				break;
			}
		}

		if (empty($parts)) {
			$parts[] = sprintf(_n('%s minute', '%s minutes', 1, 'ai-post-scheduler'), '1');
		}

		/* translators: %s = human-readable time difference, e.g. "6 days 4 hours" */
		return sprintf(__('In %s', 'ai-post-scheduler'), implode(' ', $parts));
	}
}

/**
 * Helper: normalize a DB date/time value to an AIPS_DateTime instance.
 */
if (!function_exists('aips_datetime_from_db_value')) {
	function aips_datetime_from_db_value($value) {
		if (empty($value) || '0000-00-00 00:00:00' === $value) {
			return null;
		}

		if (is_numeric($value)) {
			$timestamp = (int) $value;
			if ($timestamp > 0 && $timestamp < AIPS_Date_Time_DB_Repair::MIN_VALID_TIMESTAMP) {
				return null;
			}
			return AIPS_DateTime::fromTimestampOrNull($timestamp);
		}

		return AIPS_DateTime::fromMysqlOrNull((string) $value);
	}
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

$format_campaign_datetime = static function($timestamp, $empty_label) {
	if (empty($timestamp)) {
		return $empty_label;
	}

	return AIPS_DateTime::formatRelativeOrAbsolute($timestamp, get_option('date_format') . ' ' . get_option('time_format'));
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
			<div class="aips-filter-bar" style="display: flex; justify-content: space-between; align-items: center;">
				<strong><?php esc_html_e('Campaign Templates', 'ai-post-scheduler'); ?></strong>
				<?php if (!empty($available_templates)) : ?>
					<div class="aips-add-existing-template-container" style="display: flex; gap: 8px; align-items: center;">
						<select id="aips-add-existing-template-select" style="max-width: 250px;">
							<option value=""><?php esc_html_e('Select existing template...', 'ai-post-scheduler'); ?></option>
							<?php foreach ($available_templates as $avail_temp) : ?>
								<option value="<?php echo esc_attr($avail_temp->id); ?>"><?php echo esc_html($avail_temp->name); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="aips-btn aips-btn-secondary aips-btn-sm aips-link-existing-template-btn" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>">
							<span class="dashicons dashicons-plus" style="margin-top: 3px;"></span>
							<?php esc_html_e('Add Existing', 'ai-post-scheduler'); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
			<div class="aips-panel-body no-padding">
				<?php if (empty($templates)) : ?>
					<p class="aips-muted" style="padding: 20px;"><?php esc_html_e('No templates associated with this campaign.', 'ai-post-scheduler'); ?></p>
				<?php else : ?>
					<table class="aips-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Template Name', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Post Status', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($templates as $template) : ?>
								<tr>
									<td class="column-name">
										<div class="cell-primary">
											<strong><?php echo esc_html($template->name); ?></strong>
										</div>
									</td>
									<td>
										<span class="aips-badge aips-badge-neutral">
											<?php echo esc_html(ucfirst($template->post_status)); ?>
										</span>
									</td>
									<td>
										<?php if ($template->is_active): ?>
										<span class="aips-badge aips-badge-success">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
										</span>
										<?php else: ?>
										<span class="aips-badge aips-badge-neutral">
											<span class="dashicons dashicons-minus"></span>
											<?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
										</span>
										<?php endif; ?>
									</td>
									<td>
										<div class="cell-actions">
											<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url(add_query_arg(array('page' => 'aips-templates', 'edit' => absint($template->id)), admin_url('admin.php'))); ?>">
												<span class="dashicons dashicons-edit"></span>
												<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
											</a>
											<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-unlink-template-btn" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>" data-template-id="<?php echo esc_attr(absint($template->id)); ?>" data-template-name="<?php echo esc_attr($template->name); ?>" style="margin-left: 6px;">
												<span class="dashicons dashicons-no-alt"></span>
												<?php esc_html_e('Remove', 'ai-post-scheduler'); ?>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<div class="aips-content-panel" style="margin-top: 20px;">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Campaign Schedules', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body no-padding">
				<?php if (empty($schedules)) : ?>
					<p class="aips-muted" style="padding: 20px;"><?php esc_html_e('No schedules associated with this campaign.', 'ai-post-scheduler'); ?></p>
				<?php else : ?>
					<table class="aips-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Schedule Title', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($schedules as $schedule) : ?>
								<?php
								$next_run_dt = aips_datetime_from_db_value($schedule->next_run);
								$next_run_ts = $next_run_dt ? $next_run_dt->timestamp() : 0;
								$is_active = $schedule->is_active;
								$status = $schedule->status;

								switch ($status) {
									case 'failed':
										$badge_cls = 'aips-badge-error';
										$icon_cls  = 'dashicons-warning';
										$status_lbl = __('Failed', 'ai-post-scheduler');
										break;
									case 'inactive':
										$badge_cls = 'aips-badge-neutral';
										$icon_cls  = 'dashicons-minus';
										$status_lbl = __('Paused', 'ai-post-scheduler');
										break;
									default:
										$badge_cls = 'aips-badge-success';
										$icon_cls  = 'dashicons-yes-alt';
										$status_lbl = __('Active', 'ai-post-scheduler');
								}
								?>
								<tr>
									<td>
										<div class="cell-primary">
											<strong><?php echo esc_html($schedule->title ? $schedule->title : __('Untitled Schedule', 'ai-post-scheduler')); ?></strong>
										</div>
									</td>
									<td>
										<?php echo esc_html($schedule->template_name ? $schedule->template_name : __('Unknown Template', 'ai-post-scheduler')); ?>
									</td>
									<td>
										<span class="aips-badge aips-badge-info">
											<?php echo esc_html(aips_frequency_label($schedule->frequency)); ?>
										</span>
									</td>
									<td>
										<?php if (!$is_active): ?>
											<span class="aips-muted"><?php esc_html_e('N/A', 'ai-post-scheduler'); ?></span>
										<?php elseif ($next_run_ts): ?>
											<div class="cell-primary aips-next-run-countdown" title="<?php echo esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run_ts)); ?>">
												<?php echo esc_html(aips_next_run_relative($next_run_ts)); ?>
											</div>
											<div class="cell-meta aips-muted" style="font-size:11px;">
												<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run_ts)); ?>
											</div>
										<?php else: ?>
											<span class="aips-muted">—</span>
										<?php endif; ?>
									</td>
									<td>
										<span class="aips-badge <?php echo esc_attr($badge_cls); ?>">
											<span class="dashicons <?php echo esc_attr($icon_cls); ?>"></span>
											<?php echo esc_html($status_lbl); ?>
										</span>
									</td>
									<td>
										<div class="cell-actions">
											<button class="aips-btn aips-btn-sm aips-btn-secondary aips-unified-run-now"
												data-id="<?php echo esc_attr($schedule->id); ?>"
												data-type="template_schedule"
												aria-label="<?php esc_attr_e('Run now', 'ai-post-scheduler'); ?>"
												title="<?php esc_attr_e('Run Now', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-controls-play"></span>
												<?php esc_html_e('Run Now', 'ai-post-scheduler'); ?>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
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
