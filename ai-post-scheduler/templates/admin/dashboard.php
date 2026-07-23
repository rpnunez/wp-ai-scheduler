<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<?php if (!class_exists('Meow_MWAI_Core')): ?>
	<div class="notice notice-error">
		<p><?php esc_html_e('AI Engine plugin is not installed or activated. This plugin requires AI Engine to function.', 'ai-post-scheduler'); ?></p>
	</div>
	<?php endif; ?>

	<div class="aips-page-container" id="aips-dashboard-panel">
		<div class="aips-dashboard-spinner-overlay"><span class="spinner is-active"></span></div>

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Analytics Dashboard', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Observe your AI content generation pipelines, success rates, and upcoming schedules.', 'ai-post-scheduler'); ?></p>
				</div>
				
				<!-- Date Filter Popover & Quick Links -->
				<div class="aips-header-controls">
					<!-- Date Range Filter Trigger Button -->
					<div class="aips-date-filter-container">
						<button type="button" class="aips-btn aips-btn-secondary id-aips-date-filter-btn" id="aips-date-filter-trigger">
							<span class="dashicons dashicons-calendar-alt"></span>
							<span class="aips-date-range-label"><?php echo esc_html($date_from) . ' – ' . esc_html($date_to); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2"></span>
						</button>
						
						<!-- Popover Panel -->
						<div class="aips-date-popover" id="aips-date-popover-panel">
							<form method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>" id="aips-dashboard-date-form">
								<input type="hidden" name="page" value="ai-post-scheduler" />
								<div class="aips-popover-body">
									<div class="aips-form-group">
										<label class="aips-form-label" for="aips-input-date-from"><?php esc_html_e('Start Date', 'ai-post-scheduler'); ?></label>
										<input type="date" id="aips-input-date-from" name="date_from" class="aips-form-input" value="<?php echo esc_attr($date_from); ?>" max="<?php echo esc_attr($date_to); ?>" required />
									</div>
									<div class="aips-form-group">
										<label class="aips-form-label" for="aips-input-date-to"><?php esc_html_e('End Date', 'ai-post-scheduler'); ?></label>
										<input type="date" id="aips-input-date-to" name="date_to" class="aips-form-input" value="<?php echo esc_attr($date_to); ?>" min="<?php echo esc_attr($date_from); ?>" required />
									</div>
								</div>
								<div class="aips-popover-footer">
									<button type="button" class="aips-btn aips-btn-ghost aips-btn-sm" id="aips-date-popover-cancel"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
									<button type="submit" class="aips-btn aips-btn-primary aips-btn-sm" id="aips-date-popover-apply"><?php esc_html_e('Apply', 'ai-post-scheduler'); ?></button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- 1. SUMMARY SECTION -->
		<div class="aips-dashboard-section-title">
			<span class="dashicons dashicons-chart-bar"></span>
			<h2><?php esc_html_e('Overview Summary', 'ai-post-scheduler'); ?></h2>
			<span class="aips-section-meta"><?php echo sprintf(__('Activity from %s to %s', 'ai-post-scheduler'), esc_html($date_from), esc_html($date_to)); ?></span>
		</div>

		<div class="aips-grid aips-grid-cols-4 aips-summary-grid">
			<!-- Stats Box: Completed Posts -->
			<div class="aips-stat-card glass-morphic aips-stat-success">
				<div class="aips-stat-icon-wrap">
					<span class="dashicons dashicons-admin-post"></span>
				</div>
				<div class="aips-stat-content">
					<span class="aips-stat-label"><?php esc_html_e('Posts Completed', 'ai-post-scheduler'); ?></span>
					<strong class="aips-stat-value"><?php echo esc_html($completed_in_period); ?></strong>
					<span class="aips-stat-sub-meta">
						<?php echo sprintf(__('Success Rate: %s%%', 'ai-post-scheduler'), esc_html($success_rate_in_period)); ?>
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
					<strong class="aips-stat-value"><?php echo esc_html($failed_in_period + $partial_in_period); ?></strong>
					<span class="aips-stat-sub-meta">
						<?php echo sprintf(__('%d Failed, %d Partial', 'ai-post-scheduler'), esc_html($failed_in_period), esc_html($partial_in_period)); ?>
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
					<strong class="aips-stat-value"><?php echo esc_html($ai_calls_in_period); ?></strong>
					<span class="aips-stat-sub-meta">
						<?php echo sprintf(__('Error Rate: %s%% (%d errors)', 'ai-post-scheduler'), esc_html($ai_error_rate_in_period), esc_html($ai_errors_in_period)); ?>
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
					<strong class="aips-stat-value"><?php echo esc_html($topics_created_in_period); ?></strong>
					<span class="aips-stat-sub-meta">
						<?php echo sprintf(__('%d Pending Review', 'ai-post-scheduler'), esc_html($topics_pending_in_period)); ?>
					</span>
				</div>
			</div>
		</div>

		<!-- 2. DETAIL SECTION -->
		<div class="aips-dashboard-section-title aips-margin-top-large">
			<span class="dashicons dashicons-list-view"></span>
			<h2><?php esc_html_e('Analytics Detail', 'ai-post-scheduler'); ?></h2>
			<span class="aips-section-meta"><?php esc_html_e('Explore records and analytical details for the selected period.', 'ai-post-scheduler'); ?></span>
		</div>

		<div class="aips-dashboard-detail-grid">
			<!-- Left: Tabbed Details Lists -->
			<div class="aips-content-panel detail-tabs-panel glass-morphic">
				<!-- Tab Headers -->
				<div class="aips-tab-headers" role="tablist">
					<button class="aips-tab-btn active" role="tab" aria-selected="true" aria-controls="tab-posts" id="btn-tab-posts">
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e('Generated Posts', 'ai-post-scheduler'); ?>
					</button>
					<button class="aips-tab-btn" role="tab" aria-selected="false" aria-controls="tab-topics" id="btn-tab-topics">
						<span class="dashicons dashicons-welcome-write-blog"></span>
						<?php esc_html_e('Author Topics', 'ai-post-scheduler'); ?>
					</button>
					<button class="aips-tab-btn" role="tab" aria-selected="false" aria-controls="tab-topic-posts" id="btn-tab-topic-posts">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e('Posts by Topic', 'ai-post-scheduler'); ?>
					</button>
					<button class="aips-tab-btn" role="tab" aria-selected="false" aria-controls="tab-schedules" id="btn-tab-schedules">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Schedules Executed', 'ai-post-scheduler'); ?>
					</button>
				</div>

				<!-- Tab Panels -->
				<div class="aips-tab-content">
					
					<!-- Panel 1: Generated Posts -->
					<div class="aips-tab-panel active" id="tab-posts" role="tabpanel" aria-labelledby="btn-tab-posts">
						<?php if (!empty($recent_posts)): ?>
						<div class="aips-table-wrap">
							<table class="aips-table">
								<thead>
									<tr>
										<th><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Source/Template', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Generated Date', 'ai-post-scheduler'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($recent_posts as $item): ?>
									<tr>
										<td>
											<?php if ($item->post_id): ?>
											<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" class="cell-primary">
												<?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
											</a>
											<?php else: ?>
											<div class="cell-primary"><?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?></div>
											<?php endif; ?>
										</td>
										<td>
											<span class="cell-primary"><?php echo esc_html($item->template_name ?: __('Custom Author Workflow', 'ai-post-scheduler')); ?></span>
											<span class="cell-meta"><?php echo esc_html(ucfirst(str_replace('_', ' ', $item->creation_method))); ?></span>
										</td>
										<td>
											<?php
											$status_class = 'neutral';
											if ($item->status === 'completed') {
												$status_class = 'success';
											} elseif ($item->status === 'failed') {
												$status_class = 'error';
											} elseif ($item->status === 'pending') {
												$status_class = 'warning';
											}
											?>
											<span class="aips-badge aips-badge-<?php echo esc_attr($status_class); ?>">
												<?php echo esc_html(ucfirst($item->status)); ?>
											</span>
										</td>
										<td class="cell-meta"><?php echo esc_html($item->created_at_formatted); ?></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php else: ?>
						<div class="aips-empty-state">
							<span class="dashicons dashicons-admin-post aips-empty-state-icon" aria-hidden="true"></span>
							<h3 class="aips-empty-state-title"><?php esc_html_e('No Generated Posts', 'ai-post-scheduler'); ?></h3>
							<p class="aips-empty-state-description"><?php esc_html_e('No content has been generated in the selected date range.', 'ai-post-scheduler'); ?></p>
						</div>
						<?php endif; ?>
					</div>

					<!-- Panel 2: Author Topics -->
					<div class="aips-tab-panel" id="tab-topics" role="tabpanel" aria-labelledby="btn-tab-topics">
						<?php if (!empty($recent_topics)): ?>
						<div class="aips-table-wrap">
							<table class="aips-table">
								<thead>
									<tr>
										<th><?php esc_html_e('Topic Title', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Author', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Score', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Generated Date', 'ai-post-scheduler'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($recent_topics as $item): ?>
									<tr>
										<td>
											<div class="cell-primary"><?php echo esc_html($item->topic_title); ?></div>
										</td>
										<td>
											<div class="cell-primary"><?php echo esc_html($item->author_name ?: __('Unknown Author', 'ai-post-scheduler')); ?></div>
										</td>
										<td>
											<strong class="topic-score-badge"><?php echo esc_html($item->score); ?></strong>
										</td>
										<td>
											<?php
											$topic_class = 'neutral';
											if ($item->status === 'approved') {
												$topic_class = 'success';
											} elseif ($item->status === 'rejected') {
												$topic_class = 'error';
											} elseif ($item->status === 'pending') {
												$topic_class = 'warning';
											}
											?>
											<span class="aips-badge aips-badge-<?php echo esc_attr($topic_class); ?>">
												<?php echo esc_html(ucfirst($item->status)); ?>
											</span>
										</td>
										<td class="cell-meta"><?php echo esc_html($item->generated_at_formatted); ?></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php else: ?>
						<div class="aips-empty-state">
							<span class="dashicons dashicons-lightbulb aips-empty-state-icon" aria-hidden="true"></span>
							<h3 class="aips-empty-state-title"><?php esc_html_e('No Topics Created', 'ai-post-scheduler'); ?></h3>
							<p class="aips-empty-state-description"><?php esc_html_e('No author topics were generated in the selected date range.', 'ai-post-scheduler'); ?></p>
						</div>
						<?php endif; ?>
					</div>

					<!-- Panel 3: Posts by Topic -->
					<div class="aips-tab-panel" id="tab-topic-posts" role="tabpanel" aria-labelledby="btn-tab-topic-posts">
						<?php if (!empty($posts_by_topic)): ?>
						<div class="aips-table-wrap">
							<table class="aips-table">
								<thead>
									<tr>
										<th><?php esc_html_e('Generated Post', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Derived Topic', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Author', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Generated Date', 'ai-post-scheduler'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($posts_by_topic as $item): ?>
									<tr>
										<td>
											<?php if ($item->post_id): ?>
											<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" class="cell-primary">
												<?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
											</a>
											<?php else: ?>
											<div class="cell-primary"><?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?></div>
											<?php endif; ?>
										</td>
										<td>
											<div class="cell-primary"><?php echo esc_html($item->topic_title); ?></div>
										</td>
										<td>
											<div class="cell-primary"><?php echo esc_html($item->author_name ?: __('Unknown Author', 'ai-post-scheduler')); ?></div>
										</td>
										<td class="cell-meta"><?php echo esc_html($item->completed_at_formatted); ?></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php else: ?>
						<div class="aips-empty-state">
							<span class="dashicons dashicons-admin-links aips-empty-state-icon" aria-hidden="true"></span>
							<h3 class="aips-empty-state-title"><?php esc_html_e('No Topic-based Posts', 'ai-post-scheduler'); ?></h3>
							<p class="aips-empty-state-description"><?php esc_html_e('No posts were generated from approved author topics in the selected date range.', 'ai-post-scheduler'); ?></p>
						</div>
						<?php endif; ?>
					</div>

					<!-- Panel 4: Schedules Executed -->
					<div class="aips-tab-panel" id="tab-schedules" role="tabpanel" aria-labelledby="btn-tab-schedules">
						<?php if (!empty($executed_schedules)): ?>
						<div class="aips-table-wrap">
							<table class="aips-table">
								<thead>
									<tr>
										<th><?php esc_html_e('Schedule Title / Context', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Execution Method', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Generated Date', 'ai-post-scheduler'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($executed_schedules as $item): ?>
									<tr>
										<td>
											<?php
											$display_title = $item->schedule_title ?: ($item->template_name ?: ($item->author_name ?: __('General Schedule', 'ai-post-scheduler')));
											?>
											<div class="cell-primary"><?php echo esc_html($display_title); ?></div>
											<span class="cell-meta"><?php echo esc_html(sprintf(__('Record #%d', 'ai-post-scheduler'), $item->id)); ?></span>
										</td>
										<td>
											<?php
											$method_labels = array(
												'scheduled'        => __('Template Automation', 'ai-post-scheduler'),
												'author_topic_gen' => __('Author Topic Cron', 'ai-post-scheduler'),
												'author_post_gen'  => __('Author Post Cron', 'ai-post-scheduler'),
												'batch_job'        => __('Batch Processor Slice', 'ai-post-scheduler'),
											);
											$method_label = isset($method_labels[$item->creation_method]) ? $method_labels[$item->creation_method] : ucfirst(str_replace('_', ' ', $item->creation_method));
											?>
											<span class="aips-badge aips-badge-neutral"><?php echo esc_html($method_label); ?></span>
										</td>
										<td>
											<?php
											$exec_class = 'neutral';
											if ($item->status === 'completed') {
												$exec_class = 'success';
											} elseif ($item->status === 'failed') {
												$exec_class = 'error';
											} elseif ($item->status === 'processing') {
												$exec_class = 'warning';
											}
											?>
											<span class="aips-badge aips-badge-<?php echo esc_attr($exec_class); ?>">
												<?php echo esc_html(ucfirst($item->status)); ?>
											</span>
										</td>
										<td class="cell-meta"><?php echo esc_html($item->created_at_formatted); ?></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php else: ?>
						<div class="aips-empty-state">
							<span class="dashicons dashicons-yes aips-empty-state-icon" aria-hidden="true"></span>
							<h3 class="aips-empty-state-title"><?php esc_html_e('No Schedules Executed', 'ai-post-scheduler'); ?></h3>
							<p class="aips-empty-state-description"><?php esc_html_e('No automated schedules have run in the selected date range.', 'ai-post-scheduler'); ?></p>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Right: Dynamic Analytics Charts -->
			<div class="aips-dashboard-charts-column">
				
				<!-- Chart Box: AI Calls & AI Errors -->
				<div class="aips-content-panel glass-morphic">
					<div class="aips-panel-header">
						<h2 class="aips-panel-title"><?php esc_html_e('AI Calls & Errors by Day', 'ai-post-scheduler'); ?></h2>
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
						<h2 class="aips-panel-title"><?php esc_html_e('Post Generations by Day', 'ai-post-scheduler'); ?></h2>
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
						<h2 class="aips-panel-title"><?php esc_html_e('Topic Generations by Day', 'ai-post-scheduler'); ?></h2>
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
						<h2 class="aips-panel-title"><?php esc_html_e('AI Error Rate (%)', 'ai-post-scheduler'); ?></h2>
					</div>
					<div class="aips-panel-body">
						<div class="aips-dashboard-chart-wrap">
							<canvas id="aips-chart-error-rate" aria-label="<?php esc_attr_e('AI Error Rate', 'ai-post-scheduler'); ?>" role="img"></canvas>
						</div>
					</div>
				</div>

			</div>
		</div>

		<!-- Future Prediction / Outlook Card -->
		<div class="aips-content-panel outlook-panel glass-morphic">
			<div class="aips-panel-header">
				<div class="aips-panel-header-title-wrap">
					<span class="dashicons dashicons-clock aips-outlook-icon"></span>
					<h2 class="aips-panel-title"><?php esc_html_e('Next Month Outlook (Next 30 Days)', 'ai-post-scheduler'); ?></h2>
				</div>
				<span class="aips-badge aips-badge-info">
					<?php echo sprintf(esc_html(_n('%d Scheduled Run', '%d Scheduled Runs', $upcoming_runs_count, 'ai-post-scheduler')), $upcoming_runs_count); ?>
				</span>
			</div>
			<div class="aips-panel-body">
				<p class="outlook-description">
					<?php esc_html_e('Here is the queue of automated runs scheduled to run over the next 30 days. Maintain active schedules and topics to keep the queue running.', 'ai-post-scheduler'); ?>
				</p>
				
				<?php if (!empty($upcoming_5)): ?>
				<div class="aips-table-wrap">
					<table class="aips-table aips-dashboard-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Schedule / Job Name', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Upcoming Run Time', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($upcoming_5 as $item): ?>
							<tr>
								<td>
									<div class="cell-primary"><?php echo esc_html(isset($item['title']) ? $item['title'] : __('Unknown', 'ai-post-scheduler')); ?></div>
									<?php if (!empty($item['subtitle'])): ?>
									<div class="cell-meta"><?php echo esc_html($item['subtitle']); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$type_key   = isset($item['type']) ? $item['type'] : '';
									$type_labels = array(
										'template_schedule' => __('Template', 'ai-post-scheduler'),
										'author_topic_gen'  => __('Topic Gen', 'ai-post-scheduler'),
										'author_post_gen'   => __('Post Gen', 'ai-post-scheduler'),
									);
									$type_label = isset($type_labels[$type_key]) ? $type_labels[$type_key] : esc_html__('Schedule', 'ai-post-scheduler');
									?>
									<span class="aips-badge aips-badge-neutral"><?php echo esc_html($type_label); ?></span>
								</td>
								<td>
									<strong class="upcoming-time-highlight"><?php echo esc_html(isset($item['next_run_formatted']) ? $item['next_run_formatted'] : '—'); ?></strong>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php else: ?>
				<div class="aips-empty-state">
					<span class="dashicons dashicons-calendar-alt aips-empty-state-icon" aria-hidden="true"></span>
					<p class="aips-empty-state-description"><?php esc_html_e('No automated runs scheduled for the next 30 days. Check your schedule configurations.', 'ai-post-scheduler'); ?></p>
				</div>
				<?php endif; ?>
			</div>
		</div>

	</div>
</div>

<script type="text/html" id="aips-tmpl-dashboard-posts-row">
	<tr>
		<td>
			{{title_html}}
		</td>
		<td>
			<span class="cell-primary">{{template_name}}</span>
			<span class="cell-meta">{{creation_method}}</span>
		</td>
		<td>
			<span class="aips-badge aips-badge-{{status_badge}}">
				{{status}}
			</span>
		</td>
		<td class="cell-meta">
			{{created_at_formatted}}
			{{actions_html}}
		</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-dashboard-topics-row">
	<tr data-id="{{id}}">
		<td>
			<div class="cell-primary">{{topic_title}}</div>
		</td>
		<td>
			<div class="cell-primary">{{author_name}}</div>
		</td>
		<td>
			<strong class="topic-score-badge">{{score}}</strong>
		</td>
		<td>
			<span class="aips-badge aips-badge-{{status_badge}} status-badge">
				{{status}}
			</span>
		</td>
		<td class="cell-meta">
			{{generated_at_formatted}}
			<span class="actions-container">
				{{actions_html}}
			</span>
		</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-dashboard-topic-posts-row">
	<tr>
		<td>
			{{title_html}}
		</td>
		<td>
			<div class="cell-primary">{{topic_title}}</div>
		</td>
		<td>
			<div class="cell-primary">{{author_name}}</div>
		</td>
		<td class="cell-meta">{{completed_at_formatted}}</td>
	</tr>
</script>

<script type="text/html" id="aips-tmpl-dashboard-schedules-row">
	<tr data-id="{{id}}">
		<td>
			<div class="cell-primary">{{display_title}}</div>
		</td>
		<td>
			<span class="aips-badge aips-badge-neutral">{{method_label}}</span>
		</td>
		<td>
			<span class="aips-badge aips-badge-{{status_badge}}">
				{{status}}
			</span>
		</td>
		<td class="cell-meta">
			{{created_at_formatted}}
			<span class="actions-container">
				{{actions_html}}
			</span>
		</td>
	</tr>
</script>

<script>
window.aipsDashboardChartData = <?php echo wp_json_encode($chart_data); ?>;
</script>
