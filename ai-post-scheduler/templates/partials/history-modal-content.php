<?php
/**
 * History Modal Content Template
 *
 * Server-side rendering of the History modal body.
 * Used by both the History admin page and the AJAX endpoint for direct modal opening.
 *
 * This template contains the same UI as the client-side rendered version,
 * allowing consistent display whether rendered via JS or server-side.
 *
 * Available variables (passed by the calling context):
 * - $container: Array with history container data
 * - $logs: Array of log entries
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// Ensure required data is available
if (empty($container) || !is_array($container)) {
	echo '<p>' . esc_html__('No history data available.', 'ai-post-scheduler') . '</p>';
	return;
}

$logs = !empty($logs) ? $logs : array();
?>

<!-- History Modal Content - Server-Side Rendered -->

<?php
// Build summary section similar to renderLogsModalContent JS method
?>
<h4 style="margin:0 0 8px;"><?php esc_html_e('Summary', 'ai-post-scheduler'); ?></h4>
<div class="aips-history-modal-summary">
	<table class="aips-table" style="width:100%;margin-bottom:20px;">
		<tbody>
			<!-- Container ID -->
			<tr>
				<td style="font-weight:600;width:140px;"><?php esc_html_e('Container ID', 'ai-post-scheduler'); ?></td>
				<td><?php echo esc_html($container['id']); ?></td>
			</tr>

			<?php if (!empty($container['generated_title'])): ?>
			<!-- Title -->
			<tr>
				<td style="font-weight:600;"><?php esc_html_e('Title', 'ai-post-scheduler'); ?></td>
				<td><?php echo esc_html($container['generated_title']); ?></td>
			</tr>
			<?php endif; ?>

			<?php if (!empty($container['template_name'])): ?>
			<!-- Template -->
			<tr>
				<td style="font-weight:600;"><?php esc_html_e('Template', 'ai-post-scheduler'); ?></td>
				<td><?php echo esc_html($container['template_name']); ?></td>
			</tr>
			<?php endif; ?>

			<?php if (!empty($container['creation_method'])): ?>
			<!-- Creation Method -->
			<tr>
				<td style="font-weight:600;"><?php esc_html_e('Method', 'ai-post-scheduler'); ?></td>
				<td><?php echo esc_html(ucfirst(str_replace('_', ' ', $container['creation_method']))); ?></td>
			</tr>
			<?php endif; ?>

			<!-- Status -->
			<tr>
				<td style="font-weight:600;"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></td>
				<td>
					<?php
					$status_class = $container['status'] === 'completed' ? 'aips-badge-success'
						: ($container['status'] === 'failed' ? 'aips-badge-error' : 'aips-badge-neutral');
					?>
					<span class="aips-badge <?php echo esc_attr($status_class); ?>">
						<?php echo esc_html($container['status']); ?>
					</span>
				</td>
			</tr>

			<?php if (!empty($container['created_at'])): ?>
			<!-- Created -->
			<tr>
				<td style="font-weight:600;"><?php esc_html_e('Created', 'ai-post-scheduler'); ?></td>
				<td><?php echo esc_html($container['created_at']); ?></td>
			</tr>
			<?php endif; ?>

			<?php if (!empty($container['completed_at'])): ?>
			<!-- Completed -->
			<tr>
				<td style="font-weight:600;"><?php esc_html_e('Completed', 'ai-post-scheduler'); ?></td>
				<td><?php echo esc_html($container['completed_at']); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ($container['duration_seconds'] !== null): ?>
			<!-- Duration -->
			<tr>
				<td style="font-weight:600;"><?php esc_html_e('Duration', 'ai-post-scheduler'); ?></td>
				<td>
					<?php
					$duration = $container['duration_seconds'];
					if ($duration < 60) {
						printf(esc_html__('%d seconds', 'ai-post-scheduler'), $duration);
					} else {
						printf(esc_html__('%d min %d sec', 'ai-post-scheduler'), 
							intdiv($duration, 60), 
							$duration % 60
						);
					}
					?>
				</td>
			</tr>
			<?php endif; ?>

			<?php if (!empty($container['error_message'])): ?>
			<!-- Error -->
			<tr>
				<td style="font-weight:600;color:#d32f2f;"><?php esc_html_e('Error', 'ai-post-scheduler'); ?></td>
				<td style="color:#d32f2f;"><?php echo esc_html($container['error_message']); ?></td>
			</tr>
			<?php endif; ?>

			<?php if (!empty($container['post_id']) && !empty($container['post_url'])): ?>
			<!-- Post Links -->
			<tr>
				<td style="font-weight:600;"><?php esc_html_e('Post', 'ai-post-scheduler'); ?></td>
				<td>
					<a href="<?php echo esc_url($container['post_url']); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html(sprintf(__('View Post (ID: %d)', 'ai-post-scheduler'), $container['post_id'])); ?>
					</a>
					<?php if (!empty($container['post_edit_url'])): ?>
					&nbsp;|&nbsp;
					<a href="<?php echo esc_url($container['post_edit_url']); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
					</a>
					<?php endif; ?>
				</td>
			</tr>
			<?php elseif (!empty($container['post_id'])): ?>
			<!-- Post ID (no URL) -->
			<tr>
				<td style="font-weight:600;"><?php esc_html_e('Post ID', 'ai-post-scheduler'); ?></td>
				<td><?php echo esc_html($container['post_id']); ?></td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<?php
// Log type filter toolbar
$log_type_counts = array('all' => count($logs));
foreach ($logs as $log) {
	$type_id = (string) $log['history_type_id'];
	$log_type_counts[$type_id] = ($log_type_counts[$type_id] ?? 0) + 1;
}

if (!empty($logs)):
?>
<div class="aips-history-log-type-filter" style="margin:16px 0;padding:12px;background:#f5f5f5;border-radius:4px;">
	<span style="font-weight:600;margin-right:8px;"><?php esc_html_e('Filter:', 'ai-post-scheduler'); ?></span>
	<button class="aips-btn aips-btn-sm aips-btn-primary aips-log-type-filter-btn" data-type-id="all" style="margin-right:4px;">
		<?php printf(esc_html__('All (%d)', 'ai-post-scheduler'), $log_type_counts['all']); ?>
	</button>

	<?php
	// Get type order for consistent display
	$type_order = array(2, 3, 4, 5, 6, 8, 1, 7, 9, 10);
	foreach ($type_order as $type_id) {
		if (empty($log_type_counts[$type_id])) {
			continue;
		}
		$type_label = AIPS_History_Type::get_label($type_id);
		?>
		<button class="aips-btn aips-btn-sm aips-btn-ghost aips-log-type-filter-btn" data-type-id="<?php echo esc_attr($type_id); ?>" style="margin-right:4px;">
			<?php printf(esc_html__('%s (%d)', 'ai-post-scheduler'), $type_label, $log_type_counts[$type_id]); ?>
		</button>
		<?php
	}
	?>
</div>
<?php endif; ?>

<?php
// Log entries section
?>
<details class="aips-history-advanced-details" style="margin-top:16px;">
	<summary style="cursor:pointer;font-weight:600;">
		<?php esc_html_e('Advanced Details', 'ai-post-scheduler'); ?>
	</summary>

	<?php if (empty($logs)): ?>
		<p style="margin-top:12px;color:#666;">
			<?php esc_html_e('No log entries found for this container.', 'ai-post-scheduler'); ?>
		</p>
	<?php else: ?>
		<h3 style="margin:12px 0 8px;"><?php esc_html_e('Log Entries', 'ai-post-scheduler'); ?> <span class="aips-badge aips-badge-neutral"><?php echo count($logs); ?></span></h3>
		
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
				<?php foreach ($logs as $i => $log): ?>
					<?php
					// Determine type class (matches JS typeClass method)
					$type_class_map = array(
						1 => 'aips-badge-neutral',   // LOG
						2 => 'aips-badge-error',     // ERROR
						3 => 'aips-badge-warning',   // WARNING
						4 => 'aips-badge-info',      // INFO
						5 => 'aips-badge-ai',        // AI_REQUEST
						6 => 'aips-badge-ai',        // AI_RESPONSE
						7 => 'aips-badge-neutral',   // DEBUG
						8 => 'aips-badge-success',   // ACTIVITY
						9 => 'aips-badge-neutral',   // SESSION_METADATA
						10 => 'aips-badge-neutral',  // METRIC
					);
					$type_class = isset($type_class_map[$log['history_type_id']]) 
						? $type_class_map[$log['history_type_id']] 
						: 'aips-badge-neutral';
					?>
					<tr data-type-id="<?php echo esc_attr($log['history_type_id']); ?>">
						<td style="white-space:nowrap;font-size:12px;">
							<?php echo esc_html($log['timestamp']); ?>
						</td>
						<td>
							<span class="aips-badge <?php echo esc_attr($type_class); ?>">
								<?php echo esc_html($log['type_label']); ?>
							</span>
						</td>
						<td style="font-size:12px;font-family:monospace;">
							<?php echo esc_html($log['log_type']); ?>
						</td>
						<td>
							<?php
							$message = !empty($log['details']['message']) ? $log['details']['message'] : '';
							if ($message) {
								echo '<p style="margin:0 0 6px;">' . esc_html($message) . '</p>';
							}

							// Check for extra details
							$extra = array();
							foreach ($log['details'] as $key => $val) {
								if ($key !== 'message' && $key !== 'timestamp') {
									$extra[$key] = $val;
								}
							}

							if (!empty($extra)):
								$detail_id = 'aips-log-detail-' . $i;
								?>
								<button class="aips-btn aips-btn-sm aips-btn-ghost aips-log-toggle" 
										data-target="#<?php echo esc_attr($detail_id); ?>" 
										style="margin-top:4px;">
									<?php esc_html_e('Show details', 'ai-post-scheduler'); ?>
								</button>
								<div id="<?php echo esc_attr($detail_id); ?>" style="display:none;margin-top:8px;padding:8px;background:#f5f5f5;border-radius:3px;">
									<pre style="margin:0;font-size:11px;overflow-x:auto;max-height:200px;overflow-y:auto;"><code><?php echo esc_html(json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
									<button class="aips-btn aips-btn-xs aips-btn-ghost" data-copy-target="#<?php echo esc_attr($detail_id); ?>" style="margin-top:6px;">
										<?php esc_html_e('Copy', 'ai-post-scheduler'); ?>
									</button>
								</div>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</details>
