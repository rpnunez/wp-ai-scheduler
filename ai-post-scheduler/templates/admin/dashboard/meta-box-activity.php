<?php
/**
 * Dashboard Analytics Activity Detail Meta Box Partial
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
<div class="aips-content-panel detail-tabs-panel" style="border:none; box-shadow:none; padding:0; background:transparent;">
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
	<div class="aips-dashboard-tab-panels">

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
