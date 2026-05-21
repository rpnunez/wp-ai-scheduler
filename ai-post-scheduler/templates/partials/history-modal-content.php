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
		<div class="aips-history-modal-heading">
			<h4 class="aips-history-modal-title"><?php esc_html_e('Summary', 'ai-post-scheduler'); ?></h4>
			<p class="aips-history-modal-subtitle"><?php esc_html_e('Human-readable context first, then the full technical log trail below.', 'ai-post-scheduler'); ?></p>
		</div>
		<label class="aips-history-json-toggle">
			<input type="checkbox" class="aips-json-viewer-toggle" checked>
			<span><?php esc_html_e('JSON Viewer', 'ai-post-scheduler'); ?></span>
		</label>
	</div>

	<div class="aips-history-modal-summary">
		<div class="aips-history-summary-panel">
			<div class="aips-history-summary-main">
				<?php foreach ($container['summary_lines'] as $summary_line): ?>
					<div class="aips-history-summary-line">
						<span class="aips-history-summary-line-label"><?php echo esc_html($summary_line['label']); ?></span>
						<span class="aips-history-summary-line-value"><?php echo esc_html($summary_line['value']); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if (!empty($container['summary_meta'])): ?>
				<div class="aips-history-summary-meta">
					<?php foreach ($container['summary_meta'] as $summary_meta_item): ?>
						<div class="aips-history-summary-meta-item">
							<div class="aips-history-summary-label"><?php echo esc_html($summary_meta_item['label']); ?></div>
							<div class="aips-history-summary-value"><?php echo esc_html($summary_meta_item['value']); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if (!empty($container['detail_cards'])): ?>
		<div class="aips-history-summary-grid">
			<?php foreach ($container['detail_cards'] as $detail_card): ?>
				<div class="aips-history-summary-item<?php echo !empty($detail_card['class']) ? ' ' . esc_attr($detail_card['class']) : ''; ?>">
					<div class="aips-history-summary-label"><?php echo esc_html($detail_card['label']); ?></div>
					<div class="aips-history-summary-value"><?php echo esc_html($detail_card['value']); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if (!empty($display_logs)): ?>
		<div class="aips-history-log-type-filter">
			<span class="aips-history-log-type-filter-label"><?php esc_html_e('Filter:', 'ai-post-scheduler'); ?></span>
			<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-log-type-filter-btn" data-type-id="all">
				<?php echo esc_html(sprintf(__('All (%d)', 'ai-post-scheduler'), isset($filter_counts['all']) ? (int) $filter_counts['all'] : 0)); ?>
			</button>

			<?php foreach ($type_labels as $type_id => $type_label): ?>
				<?php if (empty($filter_counts[(string) $type_id])) { continue; } ?>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-log-type-filter-btn" data-type-id="<?php echo esc_attr($type_id); ?>">
					<?php echo esc_html(sprintf(__('%s (%d)', 'ai-post-scheduler'), $type_label, (int) $filter_counts[(string) $type_id])); ?>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<section class="aips-history-advanced-details">
		<div class="aips-history-advanced-details-heading">
			<?php esc_html_e('Log Entries', 'ai-post-scheduler'); ?> <span class="aips-badge aips-badge-neutral"><?php echo esc_html(count($display_logs)); ?></span>
		</div>

		<?php if (empty($display_logs)): ?>
			<p class="aips-history-no-logs"><?php esc_html_e('No log entries found for this container.', 'ai-post-scheduler'); ?></p>
		<?php else: ?>
			<table class="aips-table aips-history-logs-table">
				<thead>
					<tr>
						<th class="aips-history-col-timestamp"><?php esc_html_e('Timestamp', 'ai-post-scheduler'); ?></th>
						<th class="aips-history-col-type"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
						<th class="aips-history-col-logtype"><?php esc_html_e('Log Type', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Details', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($display_logs as $display_log): ?>
						<tr data-type-ids="<?php echo esc_attr(implode(',', $display_log['type_ids'])); ?>">
							<td class="aips-history-log-timestamp"><?php echo esc_html($display_log['timestamp']); ?></td>
							<td><span class="aips-badge <?php echo esc_attr($display_log['type_class']); ?>"><?php echo esc_html($display_log['type_label']); ?></span></td>
							<td class="aips-history-log-type-code"><?php echo esc_html($display_log['log_type']); ?></td>
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
												<p class="aips-history-log-message"><?php echo $section['message_html']; ?></p>
											<?php endif; ?>

											<?php if (!empty($section['has_extra'])): ?>
												<div class="aips-history-log-detail-actions">
													<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-log-toggle" data-target="#<?php echo esc_attr($section['detail_id']); ?>">
														<?php esc_html_e('Show details', 'ai-post-scheduler'); ?>
													</button>
													<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-log-copy" data-copy-target="#<?php echo esc_attr($section['detail_id']); ?>">
														<?php esc_html_e('Copy', 'ai-post-scheduler'); ?>
													</button>
												</div>
												<div id="<?php echo esc_attr($section['detail_id']); ?>" class="aips-history-log-detail-panel" style="display:none;">
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
	</section>
</div>
