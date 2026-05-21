<?php
/**
 * History Modal Content Template
 *
 * Thin renderer for the prepared history modal view data.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (empty($container) || !is_array($container)) {
	echo '<p>' . esc_html__('No history data available.', 'ai-post-scheduler') . '</p>';
	return;
}

$display_logs = !empty($display_logs) && is_array($display_logs) ? $display_logs : array();
$filter_counts = !empty($filter_counts) && is_array($filter_counts) ? $filter_counts : array('all' => count($display_logs));

$type_labels = array(
	2 => AIPS_History_Type::get_label(2),
	3 => AIPS_History_Type::get_label(3),
	4 => AIPS_History_Type::get_label(4),
	5 => AIPS_History_Type::get_label(5),
	6 => AIPS_History_Type::get_label(6),
	8 => AIPS_History_Type::get_label(8),
	1 => AIPS_History_Type::get_label(1),
	7 => AIPS_History_Type::get_label(7),
	9 => AIPS_History_Type::get_label(9),
	10 => AIPS_History_Type::get_label(10),
);
?>

<div class="aips-history-log-renderer aips-json-viewer-enabled">
	<div class="aips-history-modal-toolbar">
		<label class="aips-history-json-toggle">
			<input type="checkbox" class="aips-json-viewer-toggle" checked>
			<span><?php esc_html_e('JSON Viewer', 'ai-post-scheduler'); ?></span>
		</label>
	</div>

	<h4 style="margin:0 0 8px;"><?php esc_html_e('Summary', 'ai-post-scheduler'); ?></h4>
	<div class="aips-history-modal-summary">
		<table class="aips-table" style="width:100%;margin-bottom:20px;">
			<tbody>
				<tr>
					<td style="font-weight:600;width:140px;"><?php esc_html_e('Container ID', 'ai-post-scheduler'); ?></td>
					<td><?php echo esc_html($container['id']); ?></td>
				</tr>
				<?php if (!empty($container['what_happened'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('What happened', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html($container['what_happened']); ?></td>
					</tr>
				<?php endif; ?>
				<?php if (!empty($container['generated_title'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Title', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html($container['generated_title']); ?></td>
					</tr>
				<?php endif; ?>
				<?php if (!empty($container['template_name'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Template', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html($container['template_name']); ?></td>
					</tr>
				<?php endif; ?>
				<?php if (!empty($container['creation_method'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Method', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html(ucfirst(str_replace('_', ' ', $container['creation_method']))); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<td style="font-weight:600;"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></td>
					<td><span class="aips-badge <?php echo esc_attr($container['status_class']); ?>"><?php echo esc_html($container['status']); ?></span></td>
				</tr>
				<?php if (!empty($container['outcome_label'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Outcome', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html($container['outcome_label']); ?></td>
					</tr>
				<?php endif; ?>
				<?php if (!empty($container['created_at'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Created', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html($container['created_at']); ?></td>
					</tr>
				<?php endif; ?>
				<?php if (!empty($container['completed_at'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Completed', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html($container['completed_at']); ?></td>
					</tr>
				<?php endif; ?>
				<?php if (!empty($container['duration_label'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Duration', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html($container['duration_label']); ?></td>
					</tr>
				<?php endif; ?>
				<?php if (!empty($container['error_message'])): ?>
					<tr>
						<td style="font-weight:600;color:#d32f2f;"><?php esc_html_e('Error', 'ai-post-scheduler'); ?></td>
						<td style="color:#d32f2f;"><?php echo esc_html($container['error_message']); ?></td>
					</tr>
				<?php endif; ?>
				<?php if (!empty($container['related_entities'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Related entities', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html($container['related_entities']); ?></td>
					</tr>
				<?php endif; ?>
				<?php if (!empty($container['what_changed'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('What changed', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html($container['what_changed']); ?></td>
					</tr>
				<?php endif; ?>
				<?php if (!empty($container['post_id']) && !empty($container['post_url'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Post', 'ai-post-scheduler'); ?></td>
						<td>
							<a href="<?php echo esc_url($container['post_url']); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html(sprintf(__('View Post (ID: %d)', 'ai-post-scheduler'), $container['post_id'])); ?>
							</a>
							<?php if (!empty($container['post_edit_url'])): ?>
								&nbsp;|&nbsp;
								<a href="<?php echo esc_url($container['post_edit_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php elseif (!empty($container['post_id'])): ?>
					<tr>
						<td style="font-weight:600;"><?php esc_html_e('Post ID', 'ai-post-scheduler'); ?></td>
						<td><?php echo esc_html($container['post_id']); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if (!empty($display_logs)): ?>
		<div class="aips-history-log-type-filter" style="margin:16px 0;padding:12px;background:#f5f5f5;border-radius:4px;">
			<span style="font-weight:600;margin-right:8px;"><?php esc_html_e('Filter:', 'ai-post-scheduler'); ?></span>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-log-type-filter-btn" data-type-id="all" style="margin-right:4px;">
				<?php echo esc_html(sprintf(__('All (%d)', 'ai-post-scheduler'), isset($filter_counts['all']) ? (int) $filter_counts['all'] : 0)); ?>
			</button>

			<?php foreach ($type_labels as $type_id => $type_label): ?>
				<?php if (empty($filter_counts[(string) $type_id])) { continue; } ?>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-log-type-filter-btn" data-type-id="<?php echo esc_attr($type_id); ?>" style="margin-right:4px;">
					<?php echo esc_html(sprintf(__('%s (%d)', 'ai-post-scheduler'), $type_label, (int) $filter_counts[(string) $type_id])); ?>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<details class="aips-history-advanced-details" style="margin-top:16px;">
		<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Advanced Details', 'ai-post-scheduler'); ?></summary>

		<?php if (empty($display_logs)): ?>
			<p style="margin-top:12px;color:#666;"><?php esc_html_e('No log entries found for this container.', 'ai-post-scheduler'); ?></p>
		<?php else: ?>
			<h3 style="margin:12px 0 8px;"><?php esc_html_e('Log Entries', 'ai-post-scheduler'); ?> <span class="aips-badge aips-badge-neutral"><?php echo esc_html(count($display_logs)); ?></span></h3>

			<table class="aips-table aips-history-logs-table" style="width:100%;margin-top:12px;">
				<thead>
					<tr>
						<th style="width:150px;"><?php esc_html_e('Timestamp', 'ai-post-scheduler'); ?></th>
						<th style="width:130px;"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
						<th style="width:150px;"><?php esc_html_e('Log Type', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Details', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($display_logs as $display_log): ?>
						<tr data-type-ids="<?php echo esc_attr(implode(',', $display_log['type_ids'])); ?>">
							<td style="white-space:nowrap;font-size:12px;"><?php echo esc_html($display_log['timestamp']); ?></td>
							<td><span class="aips-badge <?php echo esc_attr($display_log['type_class']); ?>"><?php echo esc_html($display_log['type_label']); ?></span></td>
							<td style="font-size:12px;font-family:monospace;"><?php echo esc_html($display_log['log_type']); ?></td>
							<td>
								<div class="<?php echo count($display_log['sections']) > 1 ? 'aips-ai-log-pair' : ''; ?>">
									<?php foreach ($display_log['sections'] as $section): ?>
										<div class="<?php echo !empty($section['show_header']) ? 'aips-ai-log-section' : ''; ?>">
											<?php if (!empty($section['show_header'])): ?>
												<div class="aips-ai-log-section-header">
													<strong><?php echo esc_html($section['label']); ?></strong>
													<?php if (!empty($section['timestamp'])): ?>
														<span class="aips-ai-log-section-time"><?php echo esc_html($section['timestamp']); ?></span>
													<?php endif; ?>
												</div>
											<?php endif; ?>

											<?php if (!empty($section['message_html'])): ?>
												<p style="margin:0 0 6px;"><?php echo $section['message_html']; ?></p>
											<?php endif; ?>

											<?php if (!empty($section['has_extra'])): ?>
												<div class="aips-history-log-detail-actions">
													<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-log-toggle" data-target="#<?php echo esc_attr($section['detail_id']); ?>" style="font-size:11px;">
														<?php esc_html_e('Show details', 'ai-post-scheduler'); ?>
													</button>
													<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-log-copy" data-copy-target="#<?php echo esc_attr($section['detail_id']); ?>" style="font-size:11px;margin-left:4px;">
														<?php esc_html_e('Copy', 'ai-post-scheduler'); ?>
													</button>
												</div>
												<div id="<?php echo esc_attr($section['detail_id']); ?>" class="aips-history-log-detail-panel" style="display:none;margin-top:8px;">
													<div class="aips-json-tree-mode"><?php echo $section['tree_html']; ?></div>
													<div class="aips-json-raw-mode">
														<pre class="aips-history-log-raw-json"><code><?php echo esc_html($section['raw_json']); ?></code></pre>
													</div>
												</div>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</details>
</div>
