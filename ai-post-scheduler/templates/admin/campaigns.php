<?php
/**
 * Campaigns Admin Template
 *
 * Displays scheduled campaigns using the shared plugin admin layout.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

$campaign_wizard_url = admin_url('admin.php?page=aips-campaign-wizard');

// Get campaigns data
$campaigns_repo = AIPS_Campaigns_Repository::instance();
$campaigns      = $campaigns_repo->get_all_campaigns();
$stats          = $campaigns_repo->get_summary_stats();
$campaign_count = is_array($campaigns) ? count($campaigns) : 0;
$schedules      = wp_get_schedules();
?>
<div class="wrap aips-wrap aips-admin-page">
	<div class="aips-page-container aips-campaigns-page">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Campaigns', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Manage recurring content campaigns, monitor performance, and control scheduled generation runs.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<a href="<?php echo esc_url($campaign_wizard_url); ?>" class="aips-btn aips-btn-primary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('Create New Campaign', 'ai-post-scheduler'); ?>
					</a>
				</div>
			</div>
		</div>

		<div class="aips-stats-grid">
			<div class="aips-stat-card">
				<div class="aips-stat-content">
					<span class="aips-stat-number"><?php echo esc_html($stats['total']); ?></span>
					<span class="aips-stat-label"><?php esc_html_e('Total Campaigns', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
			<div class="aips-stat-card">
				<div class="aips-stat-content">
					<span class="aips-stat-number aips-campaigns-stat-active"><?php echo esc_html($stats['active']); ?></span>
					<span class="aips-stat-label"><?php esc_html_e('Active Campaigns', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
			<div class="aips-stat-card">
				<div class="aips-stat-content">
					<span class="aips-stat-number aips-campaigns-stat-muted"><?php echo esc_html($stats['archived']); ?></span>
					<span class="aips-stat-label"><?php esc_html_e('Archived', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
		</div>

		<div class="aips-content-panel" id="aips-campaigns-list">
			<?php if (!empty($campaigns)) : ?>
				<div class="aips-filter-bar">
					<div class="aips-filter-left">
						<strong><?php esc_html_e('All Campaigns', 'ai-post-scheduler'); ?></strong>
					</div>
				</div>

				<div class="aips-panel-body no-padding">
					<table class="aips-table aips-campaigns-table">
						<thead>
							<tr>
								<th class="column-campaign"><?php esc_html_e('Campaign', 'ai-post-scheduler'); ?></th>
								<th class="column-type"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
								<th class="column-frequency"><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
								<th class="column-generated"><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></th>
								<th class="column-success-rate"><?php esc_html_e('Success Rate', 'ai-post-scheduler'); ?></th>
								<th class="column-last-run"><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></th>
								<th class="column-next-run"><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
								<th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
								<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($campaigns as $campaign) : ?>
								<?php
								$total_posts   = (int) $campaign->posts_generated + (int) $campaign->posts_failed;
								$success_rate  = $total_posts > 0 ? round(((int) $campaign->posts_generated / $total_posts) * 100, 1) : 0;
								$status_class  = $campaign->is_active ? 'success' : 'neutral';
								$status_icon   = $campaign->is_active ? 'yes-alt' : 'minus';
								$status_label  = $campaign->is_active ? __('Active', 'ai-post-scheduler') : __('Paused', 'ai-post-scheduler');
								$mode_label    = $campaign->campaign_mode === 'author' ? __('Author-Based', 'ai-post-scheduler') : __('Template-Based', 'ai-post-scheduler');
								$freq_label    = isset($schedules[$campaign->frequency]) ? $schedules[$campaign->frequency]['display'] : ucwords(str_replace('_', ' ', (string) $campaign->frequency));
								$campaign_name = $campaign->title ?: $campaign->template_name ?: __('Untitled Campaign', 'ai-post-scheduler');
								?>
								<tr data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
									<td class="column-campaign">
										<div class="cell-primary"><?php echo esc_html($campaign_name); ?></div>
										<?php if (!empty($campaign->author_name)) : ?>
											<div class="cell-meta">
												<?php
												printf(
													esc_html__('Author: %s', 'ai-post-scheduler'),
													esc_html($campaign->author_name)
												);
												?>
											</div>
										<?php endif; ?>
									</td>
									<td class="column-type">
										<span class="aips-badge aips-badge-info"><?php echo esc_html($mode_label); ?></span>
									</td>
									<td class="column-frequency"><?php echo esc_html($freq_label); ?></td>
									<td class="column-generated">
										<div class="cell-primary"><?php echo esc_html($campaign->posts_generated); ?></div>
										<div class="cell-meta">
											<?php
											printf(
												esc_html(_n('%s attempt', '%s attempts', $total_posts, 'ai-post-scheduler')),
												number_format_i18n($total_posts)
											);
											?>
										</div>
									</td>
									<td class="column-success-rate">
										<span class="aips-campaign-success-rate aips-campaign-success-rate-<?php echo esc_attr($success_rate >= 80 ? 'good' : ($success_rate >= 50 ? 'warn' : 'poor')); ?>">
											<?php echo esc_html($success_rate); ?>%
										</span>
									</td>
									<td class="column-last-run">
										<?php
										if ($campaign->last_run && $campaign->last_run > 0) {
											echo esc_html(human_time_diff($campaign->last_run, time()) . ' ago');
										} else {
											echo esc_html__('Never', 'ai-post-scheduler');
										}
										?>
									</td>
									<td class="column-next-run">
										<?php
										if ($campaign->next_run && $campaign->next_run > 0) {
											$diff = $campaign->next_run - time();
											if ($diff > 0) {
												echo '<span class="aips-next-run-countdown">' . esc_html(sprintf(__('In %s', 'ai-post-scheduler'), human_time_diff(time(), $campaign->next_run))) . '</span>';
											} else {
												echo '<span class="aips-next-run-countdown aips-campaign-next-run-overdue">' . esc_html__('Past due', 'ai-post-scheduler') . '</span>';
											}
										} else {
											echo esc_html__('Not scheduled', 'ai-post-scheduler');
										}
										?>
									</td>
									<td class="column-status">
										<span class="aips-badge aips-badge-<?php echo esc_attr($status_class); ?>">
											<span class="dashicons dashicons-<?php echo esc_attr($status_icon); ?>"></span>
											<?php echo esc_html($status_label); ?>
										</span>
									</td>
									<td class="column-actions">
										<div class="cell-actions">
											<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-toggle-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>" data-is-active="<?php echo esc_attr($campaign->is_active); ?>">
												<span class="dashicons dashicons-controls-<?php echo esc_attr($campaign->is_active ? 'pause' : 'play'); ?>"></span>
												<?php echo $campaign->is_active ? esc_html__('Pause', 'ai-post-scheduler') : esc_html__('Resume', 'ai-post-scheduler'); ?>
											</button>
											<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-duplicate-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
												<span class="dashicons dashicons-admin-page"></span>
												<?php esc_html_e('Duplicate', 'ai-post-scheduler'); ?>
											</button>
											<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-archive-campaign" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
												<span class="dashicons dashicons-archive"></span>
												<?php esc_html_e('Archive', 'ai-post-scheduler'); ?>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="tablenav">
					<span class="aips-table-footer-count">
						<?php
						printf(
							esc_html(
								_n(
									'%s campaign',
									'%s campaigns',
									$campaign_count,
									'ai-post-scheduler'
								)
							),
							number_format_i18n($campaign_count)
						);
						?>
					</span>
				</div>
			<?php else : ?>
				<div class="aips-panel-body">
					<div class="aips-empty-state">
						<div class="dashicons dashicons-megaphone aips-empty-state-icon" aria-hidden="true"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Campaigns Yet', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('Campaigns let you automate recurring content generation from your templates and author strategies. Create your first campaign to start scheduling runs.', 'ai-post-scheduler'); ?></p>
						<div class="aips-empty-state-actions">
							<a href="<?php echo esc_url($campaign_wizard_url); ?>" class="aips-btn aips-btn-primary">
								<span class="dashicons dashicons-plus-alt"></span>
								<?php esc_html_e('Create Your First Campaign', 'ai-post-scheduler'); ?>
							</a>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<style>
.aips-campaigns-page .aips-stat-number.aips-campaigns-stat-active {
	color: var(--aips-success);
}

.aips-campaigns-page .aips-stat-number.aips-campaigns-stat-muted {
	color: var(--aips-gray-400);
}

.aips-campaigns-page .aips-campaigns-table .column-campaign {
	width: 24%;
}

.aips-campaigns-page .aips-campaigns-table .column-actions {
	width: 240px;
}

.aips-campaigns-page .aips-campaigns-table .cell-actions {
	flex-wrap: wrap;
	align-items: stretch;
}

.aips-campaigns-page .aips-campaigns-table .cell-actions .aips-btn {
	flex: 1 1 auto;
}

.aips-campaigns-page .aips-campaign-success-rate {
	font-weight: var(--aips-font-medium);
}

.aips-campaigns-page .aips-campaign-success-rate-good {
	color: var(--aips-success);
}

.aips-campaigns-page .aips-campaign-success-rate-warn {
	color: var(--aips-warning-dark);
}

.aips-campaigns-page .aips-campaign-success-rate-poor {
	color: var(--aips-error);
}

.aips-campaigns-page .aips-campaign-next-run-overdue {
	color: var(--aips-error);
}

@media (max-width: 782px) {
	.aips-campaigns-page .aips-campaigns-table .column-actions {
		width: auto;
	}

	.aips-campaigns-page .aips-campaigns-table .cell-actions .aips-btn {
		width: 100%;
	}
}
</style>
