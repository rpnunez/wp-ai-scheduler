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
			<td><?php echo esc_html((int) $campaign->active_schedule_count); ?></td>
			<td><?php echo esc_html((int) $campaign->generated_posts_count); ?></td>
			<td><?php echo esc_html($campaign->last_run_at ? $campaign->last_run_at : __('Never', 'ai-post-scheduler')); ?></td>
			<td><?php echo esc_html($campaign->next_run_at ? $campaign->next_run_at : __('None', 'ai-post-scheduler')); ?></td>
			<td><span class="aips-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
			<td>
				<div class="row-actions visible" style="display:flex; gap:6px; flex-wrap:wrap;">
					<a href="<?php echo esc_url(add_query_arg(array('page' => AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG, 'campaign_id' => absint($campaign->id)), admin_url('admin.php'))); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
						<?php esc_html_e('View', 'ai-post-scheduler'); ?>
					</a>

					<?php if (!$archived) : ?>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-run-campaign-all-btn" data-campaign-id="<?php echo esc_attr($campaign->id); ?>" title="<?php esc_attr_e('Run all active templates in this campaign now', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-controls-play" style="font-size:14px;vertical-align:middle;margin-right:2px;"></span><?php esc_html_e('Run All', 'ai-post-scheduler'); ?>
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

		<div class="aips-campaigns-header-bar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
			<div>
				<h2 style="margin:0;"><?php esc_html_e('Campaign Management', 'ai-post-scheduler'); ?></h2>
				<p class="description" style="margin:4px 0 0;"><?php esc_html_e('Group templates, schedules, and generation workflows inside campaign containers.', 'ai-post-scheduler'); ?></p>
			</div>
			<div class="aips-header-actions" style="display:flex; gap:10px;">
				<button type="button" class="aips-btn aips-btn-primary" id="aips-open-quick-create-modal">
					<span class="dashicons dashicons-plus-alt2" style="margin-right:4px;vertical-align:middle;"></span><?php esc_html_e('Quick Create Campaign', 'ai-post-scheduler'); ?>
				</button>
				<a href="<?php echo esc_url($campaign_wizard_url); ?>" class="aips-btn aips-btn-secondary">
					<span class="dashicons dashicons-forms" style="margin-right:4px;vertical-align:middle;"></span><?php esc_html_e('Campaign Wizard', 'ai-post-scheduler'); ?>
				</a>
			</div>
		</div>

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
						<p style="margin-top:16px;">
							<button type="button" class="aips-btn aips-btn-primary" id="aips-open-quick-create-modal-empty">
								<?php esc_html_e('Quick Create Campaign', 'ai-post-scheduler'); ?>
							</button>
						</p>
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

<!-- Quick Create Campaign Modal -->
<div id="aips-quick-create-campaign-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-quick-create-title">
	<div class="aips-modal-backdrop"></div>
	<div class="aips-modal-content" style="max-width:560px;">
		<div class="aips-modal-header">
			<h2 id="aips-quick-create-title" class="aips-modal-title"><?php esc_html_e('Quick Create Campaign', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<form id="aips-quick-create-campaign-form">
			<div class="aips-modal-body">
				<p class="description" style="margin-top:0;margin-bottom:16px;">
					<?php esc_html_e('Quickly initialize a new campaign container. You can attach templates, configure directives, and set up schedules immediately in the detail workspace.', 'ai-post-scheduler'); ?>
				</p>
				<div class="aips-form-row">
					<label for="aips-quick-campaign-name"><strong><?php esc_html_e('Campaign Name', 'ai-post-scheduler'); ?></strong> <span style="color:#d63638;">*</span></label>
					<input type="text" id="aips-quick-campaign-name" name="name" class="regular-text" required placeholder="<?php esc_attr_e('e.g., Tech Product Launches 2026', 'ai-post-scheduler'); ?>" style="width:100%;">
				</div>
				<div class="aips-form-row" style="margin-top:16px;">
					<label for="aips-quick-campaign-goal"><strong><?php esc_html_e('Content Goal / High-Level Strategy', 'ai-post-scheduler'); ?></strong></label>
					<textarea id="aips-quick-campaign-goal" name="content_goal" rows="4" class="large-text" placeholder="<?php esc_attr_e('e.g., Focus on in-depth reviews and authoritative buyer guides comparing tech accessories...', 'ai-post-scheduler'); ?>" style="width:100%;"></textarea>
				</div>
			</div>
			<div class="aips-modal-footer" style="display:flex; justify-content:flex-end; gap:8px; padding:16px 20px; border-top:1px solid #ddd; background:#f9f9f9;">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-cancel"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
				<button type="submit" class="aips-btn aips-btn-primary" id="aips-quick-create-submit-btn">
					<?php esc_html_e('Create Campaign & Open Workspace', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</form>
	</div>
</div>
