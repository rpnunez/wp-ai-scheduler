<?php
/**
 * Campaigns Admin Template
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

$campaign_wizard_url = admin_url('admin.php?page=' . AIPS_Campaigns_Controller::PAGE_SLUG);
$campaigns_repo = AIPS_Campaigns_Repository::instance();
$active_campaigns = $campaigns_repo->get_campaigns(false);
$archived_campaigns = $campaigns_repo->get_campaigns(true);
$stats = $campaigns_repo->get_summary_stats();
$is_embedded_campaigns_view = !empty($embedded);

$render_campaign_rows = static function($campaigns, $archived = false) {
	foreach ($campaigns as $campaign) :
		$is_running = !$archived && (int) $campaign->active_schedule_count > 0;
		$status_label = $archived ? __('Archived', 'ai-post-scheduler') : ($is_running ? __('Active', 'ai-post-scheduler') : __('Paused', 'ai-post-scheduler'));
		$status_class = $archived ? 'neutral' : ($is_running ? 'success' : 'neutral');
		?>
		<tr data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
			<td>
				<div class="cell-primary"><a href="<?php echo esc_url(add_query_arg(array('page' => AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG, 'campaign_id' => absint($campaign->id)), admin_url('admin.php'))); ?>"><?php echo esc_html($campaign->name); ?></a></div>
				<div class="cell-meta"><?php echo esc_html($campaign->content_goal); ?></div>
			</td>
			<td><?php echo esc_html((int) $campaign->linked_template_count); ?></td>
			<td><?php echo esc_html((int) $campaign->linked_schedule_count); ?></td>
			<td>
				<a href="<?php echo esc_url(add_query_arg(array('page' => 'aips-generated-posts', 'campaign_id' => absint($campaign->id)), admin_url('admin.php'))); ?>">
					<?php echo esc_html((int) $campaign->generated_posts_count); ?>
				</a>
			</td>
			<td><?php echo !empty($campaign->last_run) ? esc_html(AIPS_DateTime::formatRelativeOrAbsolute($campaign->last_run, get_option('date_format') . ' ' . get_option('time_format'))) : esc_html__('Never', 'ai-post-scheduler'); ?></td>
			<td><?php echo !empty($campaign->next_run) ? esc_html(AIPS_DateTime::formatRelativeOrAbsolute($campaign->next_run, get_option('date_format') . ' ' . get_option('time_format'))) : esc_html__('Not scheduled', 'ai-post-scheduler'); ?></td>
			<td>
				<span class="aips-badge aips-badge-<?php echo esc_attr($status_class); ?>">
					<?php echo esc_html($status_label); ?>
				</span>
			</td>
			<td>
				<div class="cell-actions">
					<?php if (!$archived) : ?>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-toggle-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>" data-is-active="<?php echo esc_attr($is_running ? 1 : 0); ?>">
							<?php echo $is_running ? esc_html__('Pause', 'ai-post-scheduler') : esc_html__('Resume', 'ai-post-scheduler'); ?>
						</button>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-duplicate-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
							<?php esc_html_e('Duplicate', 'ai-post-scheduler'); ?>
						</button>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-archive-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
							<?php esc_html_e('Archive', 'ai-post-scheduler'); ?>
						</button>
					<?php else : ?>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-restore-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
							<?php esc_html_e('Restore', 'ai-post-scheduler'); ?>
						</button>
					<?php endif; ?>

					<?php if (!empty($campaign->can_delete)) : ?>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
							<?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
						</button>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	endforeach;
};
?>
<?php if (!$is_embedded_campaigns_view) : ?>
<div class="wrap aips-wrap aips-admin-page">
	<div class="aips-page-container aips-campaigns-page">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Campaigns', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Manage campaign ownership, scheduling state, and historical attribution in one place.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<a href="<?php echo esc_url($campaign_wizard_url); ?>" class="aips-btn aips-btn-primary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('Add New Campaign', 'ai-post-scheduler'); ?>
					</a>
				</div>
			</div>
		</div>
<?php endif; ?>

		<div class="aips-stats-grid">
			<div class="aips-stat-card"><div class="aips-stat-content"><span class="aips-stat-number"><?php echo esc_html($stats['total']); ?></span><span class="aips-stat-label"><?php esc_html_e('Total Campaigns', 'ai-post-scheduler'); ?></span></div></div>
			<div class="aips-stat-card"><div class="aips-stat-content"><span class="aips-stat-number"><?php echo esc_html($stats['active']); ?></span><span class="aips-stat-label"><?php esc_html_e('Campaigns', 'ai-post-scheduler'); ?></span></div></div>
			<div class="aips-stat-card"><div class="aips-stat-content"><span class="aips-stat-number"><?php echo esc_html($stats['archived']); ?></span><span class="aips-stat-label"><?php esc_html_e('Archived', 'ai-post-scheduler'); ?></span></div></div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Campaigns', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body no-padding">
				<?php if (!empty($active_campaigns)) : ?>
					<table class="aips-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Campaign', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Templates', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Schedules', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Generated Posts', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('State', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody><?php $render_campaign_rows($active_campaigns, false); ?></tbody>
					</table>
				<?php else : ?>
					<div class="aips-empty-state">
						<div class="dashicons dashicons-megaphone aips-empty-state-icon" aria-hidden="true"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Campaigns Yet', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('Create your first campaign to group templates, schedules, and generated posts under a single parent record.', 'ai-post-scheduler'); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-filter-bar"><strong><?php esc_html_e('Archived', 'ai-post-scheduler'); ?></strong></div>
			<div class="aips-panel-body no-padding">
				<?php if (!empty($archived_campaigns)) : ?>
					<table class="aips-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Campaign', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Templates', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Schedules', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Generated Posts', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('State', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody><?php $render_campaign_rows($archived_campaigns, true); ?></tbody>
					</table>
				<?php else : ?>
					<div class="aips-empty-state">
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Archived Campaigns', 'ai-post-scheduler'); ?></h3>
					</div>
				<?php endif; ?>
			</div>
		</div>
<?php if (!$is_embedded_campaigns_view) : ?>
	</div>
</div>
<?php endif; ?>
