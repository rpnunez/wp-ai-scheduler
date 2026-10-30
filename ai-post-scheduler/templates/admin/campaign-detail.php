<?php
/**
 * Campaign Detail Workspace Template
 *
 * Full container-centric admin workspace displaying campaign health,
 * linked templates, schedules, directives/defaults, generated posts, and scoped history.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

$campaigns_url = admin_url('admin.php?page=' . AIPS_Campaigns_Controller::PAGE_SLUG);
$campaign_repo = AIPS_Campaigns_Repository::instance();

$tabs = array(
	'overview'   => array('label' => __('Overview', 'ai-post-scheduler'), 'icon' => 'dashicons-dashboard'),
	'templates'  => array('label' => __('Templates', 'ai-post-scheduler'), 'icon' => 'dashicons-media-document'),
	'schedules'  => array('label' => __('Schedules', 'ai-post-scheduler'), 'icon' => 'dashicons-calendar-alt'),
	'directives' => array('label' => __('Directives & Defaults', 'ai-post-scheduler'), 'icon' => 'dashicons-admin-settings'),
	'posts'      => array('label' => __('Generated Posts', 'ai-post-scheduler'), 'icon' => 'dashicons-admin-post'),
	'history'    => array('label' => __('History', 'ai-post-scheduler'), 'icon' => 'dashicons-backup'),
);

$is_active_campaign = (int) $campaign->is_active === 1 && (int) $campaign->is_archived === 0;
$status_label       = (int) $campaign->is_archived === 1
	? __('Archived', 'ai-post-scheduler')
	: ($is_active_campaign ? __('Active', 'ai-post-scheduler') : __('Paused', 'ai-post-scheduler'));
$status_class       = (int) $campaign->is_archived === 1
	? 'neutral'
	: ($is_active_campaign ? 'success' : 'neutral');

$format_campaign_datetime = static function($value, $fallback = '') {
	return AIPS_DateTime::format_display_datetime($value, $fallback);
};

$activity_summary = static function($details) {
	if (empty($details)) {
		return '';
	}
	if (is_string($details)) {
		$decoded = json_decode($details, true);
		if (is_array($decoded)) {
			$details = $decoded;
		} else {
			return $details;
		}
	}
	if (isset($details['message'])) {
		return (string) $details['message'];
	}
	if (isset($details['post_id'])) {
		return sprintf(__('Post ID: %d', 'ai-post-scheduler'), (int) $details['post_id']);
	}
	return wp_json_encode($details);
};

$linked_template_ids = array_map(function($t) {
	return (int) $t->id;
}, $templates);

$unlinked_templates = array_filter($all_templates, function($t) use ($linked_template_ids) {
	return !in_array((int) $t->id, $linked_template_ids, true);
});
?>
<div class="wrap aips-wrap aips-admin-page aips-campaign-detail-workspace">
	<div class="aips-page-container aips-campaigns-page">
		<!-- Top Navigation Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top aips-flex-between">
				<div>
					<div class="aips-flex-row">
						<h1 class="aips-page-title"><?php echo esc_html($campaign->name); ?></h1>
						<span class="aips-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
					</div>
					<?php if (!empty($campaign->content_goal)) : ?>
						<p class="aips-page-description"><?php echo esc_html($campaign->content_goal); ?></p>
					<?php endif; ?>
				</div>
				<div class="aips-page-actions aips-btn-group">
					<button type="button" class="aips-btn aips-btn-primary aips-run-campaign-all-btn" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
						<span class="dashicons dashicons-controls-play"></span>
						<?php esc_html_e('Run All Templates Now', 'ai-post-scheduler'); ?>
					</button>
					<a href="<?php echo esc_url($campaigns_url); ?>" class="aips-btn aips-btn-secondary"><?php esc_html_e('Back to Campaigns', 'ai-post-scheduler'); ?></a>
				</div>
			</div>
		</div>

		<?php if (!empty($detail_notice) && !empty($detail_notice['message'])) : ?>
			<div class="notice notice-<?php echo 'error' === $detail_notice['type'] ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html($detail_notice['message']); ?></p></div>
		<?php endif; ?>

		<!-- 6-Tab Container Rail Navigation -->
		<div class="aips-tab-nav aips-panel-tab-nav aips-campaign-tabs">
			<?php foreach ($tabs as $tab_slug => $tab_data) : ?>
				<?php
				$tab_url = add_query_arg(array(
					'page'        => AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG,
					'campaign_id' => absint($campaign->id),
					'tab'         => $tab_slug,
				), admin_url('admin.php'));
				?>
				<a href="<?php echo esc_url($tab_url); ?>" class="aips-tab-link<?php echo $active_tab === $tab_slug ? ' active' : ''; ?>">
					<span class="dashicons <?php echo esc_attr($tab_data['icon']); ?>"></span>
					<span><?php echo esc_html($tab_data['label']); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<!-- ========================================== -->
		<!-- TAB 1: OVERVIEW                            -->
		<!-- ========================================== -->
		<?php if ('overview' === $active_tab) : ?>
			<div class="aips-tab-pane">
				<!-- Health & Metric Cards -->
				<div class="aips-content-panel">
					<div class="aips-filter-bar"><strong><?php esc_html_e('Campaign Health & Statistics', 'ai-post-scheduler'); ?></strong></div>
					<div class="aips-panel-body">
						<div class="aips-stats-grid">
							<div class="aips-stat-card">
								<span class="aips-stat-label"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></span>
								<span class="aips-stat-number"><?php echo esc_html($status_label); ?></span>
							</div>
							<div class="aips-stat-card">
								<span class="aips-stat-label"><?php esc_html_e('Linked Templates', 'ai-post-scheduler'); ?></span>
								<span class="aips-stat-number"><?php echo esc_html(count($templates)); ?></span>
							</div>
							<div class="aips-stat-card">
								<span class="aips-stat-label"><?php esc_html_e('Schedules', 'ai-post-scheduler'); ?></span>
								<span class="aips-stat-number"><?php echo esc_html(count($schedules)); ?></span>
							</div>
							<div class="aips-stat-card">
								<span class="aips-stat-label"><?php esc_html_e('Active Schedules', 'ai-post-scheduler'); ?></span>
								<span class="aips-stat-number"><?php echo esc_html((int) ($stats['active_schedules'] ?? 0)); ?></span>
							</div>
							<div class="aips-stat-card">
								<span class="aips-stat-label"><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></span>
								<span class="aips-stat-number"><?php echo esc_html((int) ($stats['generated_posts'] ?? 0)); ?></span>
							</div>
							<div class="aips-stat-card">
								<span class="aips-stat-label"><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></span>
								<span class="aips-stat-desc"><?php echo esc_html($format_campaign_datetime($stats['last_run'] ?? null, __('Never', 'ai-post-scheduler'))); ?></span>
							</div>
							<div class="aips-stat-card">
								<span class="aips-stat-label"><?php esc_html_e('Next Scheduled Run', 'ai-post-scheduler'); ?></span>
								<span class="aips-stat-desc"><?php echo esc_html($format_campaign_datetime($stats['next_run'] ?? null, __('None', 'ai-post-scheduler'))); ?></span>
							</div>
							<div class="aips-stat-card">
								<span class="aips-stat-label"><?php esc_html_e('Active Alerts', 'ai-post-scheduler'); ?></span>
								<span class="aips-stat-number"><?php echo esc_html(count($campaign_warnings)); ?></span>
							</div>
						</div>
					</div>
				</div>

				<!-- Quick Actions Bar -->
				<div class="aips-content-panel">
					<div class="aips-filter-bar"><strong><?php esc_html_e('Quick Actions', 'ai-post-scheduler'); ?></strong></div>
					<div class="aips-panel-body">
						<div class="aips-campaign-detail-lifecycle-actions">
							<button type="button" class="aips-btn aips-btn-primary aips-run-campaign-all-btn" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
								<span class="dashicons dashicons-controls-play"></span>
								<?php esc_html_e('Run All Templates Now', 'ai-post-scheduler'); ?>
							</button>

							<?php if ((int) $campaign->is_archived === 1) : ?>
								<button type="button" class="aips-btn aips-btn-secondary aips-restore-campaign" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>"><?php esc_html_e('Restore Campaign', 'ai-post-scheduler'); ?></button>
							<?php else : ?>
								<button type="button" class="aips-btn aips-btn-secondary aips-toggle-campaign" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>" data-is-active="<?php echo esc_attr((int) $campaign->is_active); ?>"><?php echo (int) $campaign->is_active === 1 ? esc_html__('Pause Campaign', 'ai-post-scheduler') : esc_html__('Resume Campaign', 'ai-post-scheduler'); ?></button>
								<button type="button" class="aips-btn aips-btn-secondary aips-duplicate-campaign" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>"><?php esc_html_e('Duplicate Campaign', 'ai-post-scheduler'); ?></button>
								<button type="button" class="aips-btn aips-btn-secondary aips-archive-campaign" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>"><?php esc_html_e('Archive Campaign', 'ai-post-scheduler'); ?></button>
							<?php endif; ?>

							<?php if (!empty($campaign->can_delete)) : ?>
								<button type="button" class="aips-btn aips-btn-danger aips-delete-campaign" data-campaign-id="<?php echo esc_attr(absint($campaign->id)); ?>"><?php esc_html_e('Delete Campaign', 'ai-post-scheduler'); ?></button>
							<?php endif; ?>
						</div>
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

				<!-- Recent Activity -->
				<div class="aips-content-panel">
					<div class="aips-filter-bar"><strong><?php esc_html_e('Recent Campaign Activity', 'ai-post-scheduler'); ?></strong></div>
					<div class="aips-panel-body no-padding">
						<?php if (empty($recent_activity)) : ?>
							<div class="aips-empty-state"><p class="aips-muted"><?php esc_html_e('No campaign activity has been recorded yet.', 'ai-post-scheduler'); ?></p></div>
						<?php else : ?>
							<table class="aips-table">
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
		<?php endif; ?>

		<!-- ========================================== -->
		<!-- TAB 2: TEMPLATES                           -->
		<!-- ========================================== -->
		<?php if ('templates' === $active_tab) : ?>
			<div class="aips-tab-pane">
				<div class="aips-content-panel">
					<div class="aips-filter-bar aips-flex-between">
						<strong><?php esc_html_e('Linked Templates in this Campaign', 'ai-post-scheduler'); ?></strong>
						<div class="aips-flex-row">
							<button type="button" class="aips-btn aips-btn-primary" id="aips-open-link-template-modal">
								<span class="dashicons dashicons-plus-alt2"></span>
								<?php esc_html_e('Link Existing Template', 'ai-post-scheduler'); ?>
							</button>
							<a href="<?php echo esc_url(admin_url('admin.php?page=aips-studio&tab=templates&action=new')); ?>" class="aips-btn aips-btn-secondary" target="_blank" rel="noopener noreferrer">
								<span class="dashicons dashicons-external"></span>
								<?php esc_html_e('Create New Template', 'ai-post-scheduler'); ?>
							</a>
						</div>
					</div>
					<div class="aips-panel-body no-padding">
						<?php if (empty($templates)) : ?>
							<div class="aips-empty-state">
								<div class="dashicons dashicons-media-document aips-empty-state-icon" aria-hidden="true"></div>
								<h3 class="aips-empty-state-title"><?php esc_html_e('No Templates Linked', 'ai-post-scheduler'); ?></h3>
								<p class="aips-empty-state-description">
									<?php esc_html_e('Link existing prompt templates to this campaign. Templates can be shared across multiple campaigns and customize execution with optional overrides.', 'ai-post-scheduler'); ?>
								</p>
								<p class="aips-empty-state-actions">
									<button type="button" class="aips-btn aips-btn-primary" id="aips-open-link-template-modal-empty">
										<?php esc_html_e('Link Your First Template', 'ai-post-scheduler'); ?>
									</button>
								</p>
							</div>
						<?php else : ?>
							<table class="aips-table">
								<thead>
									<tr>
										<th><?php esc_html_e('Template Name', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Post Type', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Structure', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Overrides', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($templates as $t) : ?>
										<?php
										$raw_ovr = !empty($t->campaign_overrides) ? $t->campaign_overrides : (!empty($t->overrides) ? $t->overrides : array());
										$overrides = !empty($raw_ovr) ? (is_array($raw_ovr) ? $raw_ovr : json_decode($raw_ovr, true)) : array();
										$override_keys = is_array($overrides) ? array_keys(array_filter($overrides)) : array();
										$has_overrides = !empty($override_keys);
										$edit_template_url = admin_url('admin.php?page=aips-studio&tab=templates&action=edit&template_id=' . absint($t->id));
										?>
										<tr data-template-id="<?php echo esc_attr($t->id); ?>">
											<td>
												<div class="cell-primary">
													<a href="<?php echo esc_url($edit_template_url); ?>" target="_blank" rel="noopener noreferrer">
														<strong><?php echo esc_html($t->name); ?></strong>
													</a>
												</div>
												<?php if (!empty($t->description)) : ?>
													<div class="cell-meta"><?php echo esc_html($t->description); ?></div>
												<?php endif; ?>
											</td>
											<td><code><?php echo esc_html($t->post_type ?? 'post'); ?></code></td>
											<td>
												<?php
												$struct_id = !empty($overrides['article_structure_id']) ? $overrides['article_structure_id'] : ($campaign->default_article_structure_id ?: ($t->article_structure_id ?? null));
												$struct_label = __('Default', 'ai-post-scheduler');
												if (!empty($struct_id)) {
													foreach ($article_structures as $as) {
														if ((int) $as->id === (int) $struct_id) {
															$struct_label = $as->name;
															break;
														}
													}
												}
												echo esc_html($struct_label);
												?>
											</td>
											<td>
												<?php if ($has_overrides) : ?>
													<span class="aips-badge aips-badge-info" title="<?php echo esc_attr(implode(', ', $override_keys)); ?>">
														<?php echo esc_html(sprintf(_n('%d override', '%d overrides', count($override_keys), 'ai-post-scheduler'), count($override_keys))); ?>
													</span>
												<?php else : ?>
													<span class="aips-badge aips-badge-secondary"><?php esc_html_e('None (Inherits Campaign)', 'ai-post-scheduler'); ?></span>
												<?php endif; ?>
											</td>
											<td>
												<div class="cell-actions">
													<button type="button"
															class="aips-btn aips-btn-sm aips-btn-secondary aips-edit-overrides-btn"
															data-template-id="<?php echo esc_attr($t->id); ?>"
															data-template-name="<?php echo esc_attr($t->name); ?>"
															data-overrides="<?php echo esc_attr(wp_json_encode($overrides ?: new stdClass())); ?>"
															title="<?php esc_attr_e('Configure per-template overrides for this campaign', 'ai-post-scheduler'); ?>">
														<?php esc_html_e('Overrides', 'ai-post-scheduler'); ?>
													</button>
													<button type="button"
															class="aips-btn aips-btn-sm aips-btn-danger aips-unlink-template-btn"
															data-template-id="<?php echo esc_attr($t->id); ?>"
															data-template-name="<?php echo esc_attr($t->name); ?>"
															title="<?php esc_attr_e('Unlink template from this campaign (preserves template)', 'ai-post-scheduler'); ?>">
														<?php esc_html_e('Unlink', 'ai-post-scheduler'); ?>
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
			</div>
		<?php endif; ?>

		<!-- ========================================== -->
		<!-- TAB 3: SCHEDULES                           -->
		<!-- ========================================== -->
		<?php if ('schedules' === $active_tab) : ?>
			<div class="aips-tab-pane">
				<div class="aips-content-panel">
					<div class="aips-filter-bar aips-flex-between">
						<strong><?php esc_html_e('Campaign Schedules', 'ai-post-scheduler'); ?></strong>
						<div class="aips-flex-row">
							<button type="button" class="aips-btn aips-btn-primary aips-run-campaign-all-btn" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
								<span class="dashicons dashicons-controls-play"></span>
								<?php esc_html_e('Run All Templates Now', 'ai-post-scheduler'); ?>
							</button>
							<a href="<?php echo esc_url(admin_url('admin.php?page=aips-automations&tab=schedules&campaign_id=' . absint($campaign->id))); ?>" class="aips-btn aips-btn-secondary">
								<span class="dashicons dashicons-plus-alt2"></span>
								<?php esc_html_e('Add Schedule', 'ai-post-scheduler'); ?>
							</a>
						</div>
					</div>
					<div class="aips-panel-body no-padding">
						<?php if (empty($schedules)) : ?>
							<div class="aips-empty-state">
								<div class="dashicons dashicons-calendar-alt aips-empty-state-icon" aria-hidden="true"></div>
								<h3 class="aips-empty-state-title"><?php esc_html_e('No Schedules Configured', 'ai-post-scheduler'); ?></h3>
								<p class="aips-empty-state-description">
									<?php esc_html_e('Automate content creation for this campaign by creating a schedule. A schedule can run all linked templates in a batch, rotate through them, or trigger an individual template.', 'ai-post-scheduler'); ?>
								</p>
								<p class="aips-empty-state-actions">
									<a href="<?php echo esc_url(admin_url('admin.php?page=aips-automations&tab=schedules&campaign_id=' . absint($campaign->id))); ?>" class="aips-btn aips-btn-primary">
										<?php esc_html_e('Create Schedule for Campaign', 'ai-post-scheduler'); ?>
									</a>
								</p>
							</div>
						<?php else : ?>
							<table class="aips-table">
								<thead>
									<tr>
										<th><?php esc_html_e('Schedule Title', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Execution Mode', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Interval', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($schedules as $sched) : ?>
										<?php
										$sched_is_active = (int) ($sched->is_active ?? 0) === 1;
										$sched_mode_label = __('Single Template', 'ai-post-scheduler');
										if (($sched->rotation_pattern ?? '') === 'campaign_all') {
											$sched_mode_label = __('All Templates (Batch)', 'ai-post-scheduler');
										} elseif (($sched->rotation_pattern ?? '') === 'campaign_rotate') {
											$sched_mode_label = __('Rotate Templates', 'ai-post-scheduler');
										}
										?>
										<tr data-schedule-id="<?php echo esc_attr($sched->id); ?>">
											<td>
												<strong><?php echo esc_html($sched->title ?: __('Untitled Schedule', 'ai-post-scheduler')); ?></strong>
											</td>
											<td>
												<span class="aips-badge <?php echo ($sched->rotation_pattern ?? '') === 'campaign_all' ? 'aips-badge-primary' : 'aips-badge-info'; ?>">
													<?php echo esc_html($sched_mode_label); ?>
												</span>
											</td>
											<td><code><?php echo esc_html($sched->interval_setting ?? 'daily'); ?></code></td>
											<td><?php echo esc_html($format_campaign_datetime($sched->next_run ?? null, __('Not scheduled', 'ai-post-scheduler'))); ?></td>
											<td><?php echo esc_html($format_campaign_datetime($sched->last_run ?? null, __('Never', 'ai-post-scheduler'))); ?></td>
											<td>
												<span class="aips-status-badge <?php echo $sched_is_active ? 'success' : 'neutral'; ?>">
													<?php echo $sched_is_active ? esc_html__('Active', 'ai-post-scheduler') : esc_html__('Paused', 'ai-post-scheduler'); ?>
												</span>
											</td>
											<td>
												<div class="cell-actions">
													<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-campaign-run-now" data-schedule-id="<?php echo esc_attr($sched->id); ?>">
														<?php esc_html_e('Run Now', 'ai-post-scheduler'); ?>
													</button>
													<a href="<?php echo esc_url(admin_url('admin.php?page=aips-automations&tab=schedules&edit=' . absint($sched->id))); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
														<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
													</a>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- ========================================== -->
		<!-- TAB 4: DIRECTIVES & DEFAULTS               -->
		<!-- ========================================== -->
		<?php if ('directives' === $active_tab) : ?>
			<div class="aips-tab-pane">
				<div class="aips-content-panel">
					<div class="aips-filter-bar"><strong><?php esc_html_e('Campaign Strategy Directives & Defaults', 'ai-post-scheduler'); ?></strong></div>
					<div class="aips-panel-body">
						<form method="post" id="aips-campaign-directives-form">
							<?php wp_nonce_field('aips_campaign_detail_save_' . absint($campaign->id), 'aips_campaign_detail_nonce'); ?>
							<input type="hidden" name="detail_action" value="save">
							<input type="hidden" name="current_tab" value="directives">

							<div class="aips-form-row">
								<label for="campaign_name"><strong><?php esc_html_e('Campaign Name', 'ai-post-scheduler'); ?></strong> <span class="aips-required">*</span></label>
								<input type="text" id="campaign_name" name="campaign_name" class="regular-text aips-w-full" value="<?php echo esc_attr($campaign->name); ?>" required />
							</div>

							<div class="aips-form-row">
								<label for="content_goal"><strong><?php esc_html_e('Content Goal / High-Level Strategy', 'ai-post-scheduler'); ?></strong></label>
								<p class="description">
									<?php esc_html_e('The primary business objective or theme. Injected into prompts as the campaign content goal.', 'ai-post-scheduler'); ?>
								</p>
								<textarea id="content_goal" name="content_goal" rows="3" class="large-text aips-w-full"><?php echo esc_textarea($campaign->content_goal); ?></textarea>
							</div>

							<div class="aips-form-row">
								<label for="prompt_directives"><strong><?php esc_html_e('Prompt Directives', 'ai-post-scheduler'); ?></strong></label>
								<p class="description">
									<?php esc_html_e('Custom instructions or constraints injected into AI prompts for all templates in this campaign (e.g., editorial guidelines, key product mentions, tone notes).', 'ai-post-scheduler'); ?>
								</p>
								<textarea id="prompt_directives" name="prompt_directives" rows="5" class="large-text aips-w-full"><?php echo esc_textarea($campaign->prompt_directives ?? ''); ?></textarea>
							</div>

							<h3 class="aips-wizard-section-heading">
								<?php esc_html_e('Campaign Defaults (Inherited by Templates unless Overridden)', 'ai-post-scheduler'); ?>
							</h3>

							<div class="aips-campaign-defaults-grid">
								<!-- Default Article Structure -->
								<div class="aips-form-row">
									<label for="default_article_structure_id"><strong><?php esc_html_e('Default Article Structure', 'ai-post-scheduler'); ?></strong></label>
									<select id="default_article_structure_id" name="default_article_structure_id" class="aips-w-full">
										<option value=""><?php esc_html_e('None (Use Template Default)', 'ai-post-scheduler'); ?></option>
										<?php foreach ($article_structures as $as) : ?>
											<option value="<?php echo esc_attr($as->id); ?>" <?php selected((int) ($campaign->default_article_structure_id ?? 0), (int) $as->id); ?>>
												<?php echo esc_html($as->name); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<!-- Default Post Status -->
								<div class="aips-form-row">
									<label for="default_post_status"><strong><?php esc_html_e('Default Post Status', 'ai-post-scheduler'); ?></strong></label>
									<select id="default_post_status" name="default_post_status" class="aips-w-full">
										<option value=""><?php esc_html_e('Inherit (Use Template Default)', 'ai-post-scheduler'); ?></option>
										<option value="draft" <?php selected($campaign->default_post_status ?? '', 'draft'); ?>><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
										<option value="publish" <?php selected($campaign->default_post_status ?? '', 'publish'); ?>><?php esc_html_e('Publish', 'ai-post-scheduler'); ?></option>
										<option value="pending" <?php selected($campaign->default_post_status ?? '', 'pending'); ?>><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></option>
										<option value="private" <?php selected($campaign->default_post_status ?? '', 'private'); ?>><?php esc_html_e('Private', 'ai-post-scheduler'); ?></option>
									</select>
								</div>

								<!-- Default Voice -->
								<div class="aips-form-row">
									<label for="default_voice_id"><strong><?php esc_html_e('Default Voice', 'ai-post-scheduler'); ?></strong></label>
									<select id="default_voice_id" name="default_voice_id" class="aips-w-full">
										<option value=""><?php esc_html_e('Inherit (Use Template Default)', 'ai-post-scheduler'); ?></option>
										<?php foreach ($voices as $voice) : ?>
											<option value="<?php echo esc_attr($voice->id); ?>" <?php selected((int) ($campaign->default_voice_id ?? 0), (int) $voice->id); ?>>
												<?php echo esc_html($voice->name); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<!-- Default Author -->
								<div class="aips-form-row">
									<label for="default_author_id"><strong><?php esc_html_e('Default Author', 'ai-post-scheduler'); ?></strong></label>
									<select id="default_author_id" name="default_author_id" class="aips-w-full">
										<option value=""><?php esc_html_e('Inherit (Use Template Default)', 'ai-post-scheduler'); ?></option>
										<?php foreach ($authors as $author) : ?>
											<option value="<?php echo esc_attr($author->ID); ?>" <?php selected((int) ($campaign->default_author_id ?? 0), (int) $author->ID); ?>>
												<?php echo esc_html($author->display_name); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<!-- Default Categories -->
								<div class="aips-form-row">
									<label for="default_categories"><strong><?php esc_html_e('Default Categories', 'ai-post-scheduler'); ?></strong></label>
									<input type="text" id="default_categories" name="default_categories" class="regular-text aips-w-full" value="<?php echo esc_attr($campaign->default_categories ?? ''); ?>" placeholder="<?php esc_attr_e('e.g., Technology, Reviews', 'ai-post-scheduler'); ?>">
								</div>

								<!-- Default Tags -->
								<div class="aips-form-row">
									<label for="default_tags"><strong><?php esc_html_e('Default Tags', 'ai-post-scheduler'); ?></strong></label>
									<input type="text" id="default_tags" name="default_tags" class="regular-text aips-w-full" value="<?php echo esc_attr($campaign->default_tags ?? ''); ?>" placeholder="<?php esc_attr_e('e.g., ai, automation, 2026', 'ai-post-scheduler'); ?>">
								</div>
							</div>

							<p class="submit">
								<button type="submit" class="aips-btn aips-btn-primary">
									<span class="dashicons dashicons-saved"></span>
									<?php esc_html_e('Save Directives & Defaults', 'ai-post-scheduler'); ?>
								</button>
							</p>
						</form>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- ========================================== -->
		<!-- TAB 5: GENERATED POSTS                     -->
		<!-- ========================================== -->
		<?php if ('posts' === $active_tab) : ?>
			<div class="aips-tab-pane">
				<div class="aips-content-panel">
					<div class="aips-filter-bar"><strong><?php esc_html_e('Posts Generated under this Campaign', 'ai-post-scheduler'); ?></strong></div>
					<div class="aips-panel-body no-padding">
						<?php if (empty($recent_generated_posts)) : ?>
							<div class="aips-empty-state">
								<div class="dashicons dashicons-admin-post aips-empty-state-icon" aria-hidden="true"></div>
								<h3 class="aips-empty-state-title"><?php esc_html_e('No Posts Generated Yet', 'ai-post-scheduler'); ?></h3>
								<p class="aips-empty-state-description">
									<?php esc_html_e('Posts generated by schedules or manual runs tied to this campaign will appear here.', 'ai-post-scheduler'); ?>
								</p>
								<p class="aips-empty-state-actions">
									<button type="button" class="aips-btn aips-btn-primary aips-run-campaign-all-btn" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
										<?php esc_html_e('Run All Templates Now', 'ai-post-scheduler'); ?>
									</button>
								</p>
							</div>
						<?php else : ?>
							<table class="aips-table">
								<thead>
									<tr>
										<th><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Template Used', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Generated Date', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($recent_generated_posts as $post_item) : ?>
										<?php
										$post_title = $post_item->post_title ? $post_item->post_title : $post_item->generated_title;
										$edit_link  = get_edit_post_link((int) $post_item->post_id, 'raw');
										$view_link  = get_permalink((int) $post_item->post_id);
										$status_obj = get_post_status_object($post_item->post_status);
										$status_str = $status_obj ? $status_obj->label : $post_item->post_status;
										?>
										<tr>
											<td><strong><?php echo esc_html($post_title ? $post_title : __('Untitled', 'ai-post-scheduler')); ?></strong></td>
											<td><span class="aips-status-badge neutral"><?php echo esc_html($status_str); ?></span></td>
											<td><?php echo esc_html($post_item->template_name ? $post_item->template_name : __('Unknown template', 'ai-post-scheduler')); ?></td>
											<td><?php echo esc_html($format_campaign_datetime(!empty($post_item->completed_at) ? $post_item->completed_at : $post_item->created_at, __('Unknown', 'ai-post-scheduler'))); ?></td>
											<td>
												<div class="cell-actions">
													<?php if ($edit_link) : ?>
														<a href="<?php echo esc_url($edit_link); ?>" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Edit in WP', 'ai-post-scheduler'); ?></a>
													<?php endif; ?>
													<?php if ($view_link) : ?>
														<a href="<?php echo esc_url($view_link); ?>" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View', 'ai-post-scheduler'); ?></a>
													<?php endif; ?>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- ========================================== -->
		<!-- TAB 6: HISTORY (SCOPED EMBEDDED)           -->
		<!-- ========================================== -->
		<?php if ('history' === $active_tab) : ?>
			<div class="aips-tab-pane">
				<div class="aips-campaign-scoped-history">
					<?php
					$history_handler = new AIPS_History();
					$history_handler->render_page(array(
						'campaign_id' => $campaign->id,
						'is_embedded' => true,
					));
					?>
				</div>
			</div>
		<?php endif; ?>

	</div>
</div>

<!-- ============================================================ -->
<!-- Link Template Modal                                          -->
<!-- ============================================================ -->
<div id="aips-link-template-modal" class="aips-modal" hidden role="dialog" aria-modal="true" aria-labelledby="aips-link-template-title">
	<div class="aips-modal-backdrop"></div>
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-link-template-title" class="aips-modal-title"><?php esc_html_e('Link Template to Campaign', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<form id="aips-link-template-form">
			<input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->id); ?>">
			<div class="aips-modal-body">
				<p class="description">
					<?php esc_html_e('Select an existing template to add into this campaign container. It will inherit campaign directives and strategy goals.', 'ai-post-scheduler'); ?>
				</p>
				<div class="aips-form-row">
					<label for="aips-select-template-to-link"><strong><?php esc_html_e('Choose Template', 'ai-post-scheduler'); ?></strong> <span class="aips-required">*</span></label>
					<select id="aips-select-template-to-link" name="template_id" required class="aips-w-full">
						<option value=""><?php esc_html_e('-- Select a Template --', 'ai-post-scheduler'); ?></option>
						<?php foreach ($unlinked_templates as $ut) : ?>
							<option value="<?php echo esc_attr($ut->id); ?>"><?php echo esc_html($ut->name); ?> (<?php echo esc_html($ut->post_type ?? 'post'); ?>)</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="aips-form-row">
					<details>
						<summary class="aips-toggle-summary">
							<?php esc_html_e('+ Configure Per-Template Overrides (Optional)', 'ai-post-scheduler'); ?>
						</summary>
						<div class="aips-grid-2col">
							<div>
								<label class="aips-label-sm"><strong><?php esc_html_e('Article Structure', 'ai-post-scheduler'); ?></strong></label>
								<select name="override_structure_id" class="aips-w-full">
									<option value=""><?php esc_html_e('Inherit Campaign Default', 'ai-post-scheduler'); ?></option>
									<?php foreach ($article_structures as $as) : ?>
										<option value="<?php echo esc_attr($as->id); ?>"><?php echo esc_html($as->name); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div>
								<label class="aips-label-sm"><strong><?php esc_html_e('Post Status', 'ai-post-scheduler'); ?></strong></label>
								<select name="override_post_status" class="aips-w-full">
									<option value=""><?php esc_html_e('Inherit Campaign Default', 'ai-post-scheduler'); ?></option>
									<option value="draft"><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
									<option value="publish"><?php esc_html_e('Publish', 'ai-post-scheduler'); ?></option>
									<option value="pending"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></option>
								</select>
							</div>
						</div>
					</details>
				</div>
			</div>
			<div class="aips-modal-footer">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-cancel"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
				<button type="submit" class="aips-btn aips-btn-primary" id="aips-link-template-submit-btn">
					<?php esc_html_e('Link Template', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</form>
	</div>
</div>

<!-- ============================================================ -->
<!-- Edit Overrides Modal                                         -->
<!-- ============================================================ -->
<div id="aips-template-overrides-modal" class="aips-modal" hidden role="dialog" aria-modal="true" aria-labelledby="aips-overrides-title">
	<div class="aips-modal-backdrop"></div>
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-overrides-title" class="aips-modal-title"><?php esc_html_e('Configure Template Overrides', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<form id="aips-template-overrides-form">
			<input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->id); ?>">
			<input type="hidden" name="template_id" id="aips-override-template-id" value="">
			<div class="aips-modal-body">
				<p class="description">
					<?php esc_html_e('Override campaign-level defaults specifically for template: ', 'ai-post-scheduler'); ?><strong id="aips-override-template-name-label"></strong>.
					<?php esc_html_e('Leave options unselected or blank to inherit the Campaign default.', 'ai-post-scheduler'); ?>
				</p>
				<div class="aips-grid-2col">
					<div class="aips-form-row">
						<label for="aips-override-structure-id"><strong><?php esc_html_e('Article Structure', 'ai-post-scheduler'); ?></strong></label>
						<select id="aips-override-structure-id" name="article_structure_id" class="aips-w-full">
							<option value=""><?php esc_html_e('Inherit Campaign Default', 'ai-post-scheduler'); ?></option>
							<?php foreach ($article_structures as $as) : ?>
								<option value="<?php echo esc_attr($as->id); ?>"><?php echo esc_html($as->name); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="aips-form-row">
						<label for="aips-override-post-status"><strong><?php esc_html_e('Post Status', 'ai-post-scheduler'); ?></strong></label>
						<select id="aips-override-post-status" name="post_status" class="aips-w-full">
							<option value=""><?php esc_html_e('Inherit Campaign Default', 'ai-post-scheduler'); ?></option>
							<option value="draft"><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
							<option value="publish"><?php esc_html_e('Publish', 'ai-post-scheduler'); ?></option>
							<option value="pending"><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></option>
							<option value="private"><?php esc_html_e('Private', 'ai-post-scheduler'); ?></option>
						</select>
					</div>
					<div class="aips-form-row">
						<label for="aips-override-voice-id"><strong><?php esc_html_e('Voice', 'ai-post-scheduler'); ?></strong></label>
						<select id="aips-override-voice-id" name="voice_id" class="aips-w-full">
							<option value=""><?php esc_html_e('Inherit Campaign Default', 'ai-post-scheduler'); ?></option>
							<?php foreach ($voices as $voice) : ?>
								<option value="<?php echo esc_attr($voice->id); ?>"><?php echo esc_html($voice->name); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="aips-form-row">
						<label for="aips-override-author-id"><strong><?php esc_html_e('Author', 'ai-post-scheduler'); ?></strong></label>
						<select id="aips-override-author-id" name="author_id" class="aips-w-full">
							<option value=""><?php esc_html_e('Inherit Campaign Default', 'ai-post-scheduler'); ?></option>
							<?php foreach ($authors as $author) : ?>
								<option value="<?php echo esc_attr($author->ID); ?>"><?php echo esc_html($author->display_name); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="aips-form-row">
					<label for="aips-override-categories"><strong><?php esc_html_e('Custom Categories (Optional)', 'ai-post-scheduler'); ?></strong></label>
					<input type="text" id="aips-override-categories" name="categories" class="regular-text aips-w-full" placeholder="<?php esc_attr_e('Leave blank to inherit campaign categories', 'ai-post-scheduler'); ?>">
				</div>
				<div class="aips-form-row">
					<label for="aips-override-tags"><strong><?php esc_html_e('Custom Tags (Optional)', 'ai-post-scheduler'); ?></strong></label>
					<input type="text" id="aips-override-tags" name="tags" class="regular-text aips-w-full" placeholder="<?php esc_attr_e('Leave blank to inherit campaign tags', 'ai-post-scheduler'); ?>">
				</div>
			</div>
			<div class="aips-modal-footer">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-cancel"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
				<button type="submit" class="aips-btn aips-btn-primary" id="aips-save-overrides-submit-btn">
					<?php esc_html_e('Save Overrides', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</form>
	</div>
</div>
