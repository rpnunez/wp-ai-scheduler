<?php
if (!defined('ABSPATH')) {
	exit;
}

$notice_type = isset($_GET['aips_notice']) ? sanitize_key(wp_unslash($_GET['aips_notice'])) : '';
$notice_msg  = isset($_GET['aips_message']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['aips_message']))) : '';
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Upcoming', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Upcoming content generation and automation events, ordered by next run time.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>

		<?php if (!empty($notice_type) && !empty($notice_msg)) : ?>
			<div class="notice notice-<?php echo esc_attr('success' === $notice_type ? 'success' : 'error'); ?>"><p><?php echo esc_html($notice_msg); ?></p></div>
		<?php endif; ?>

		<div style="margin-bottom:10px;font-size:12px;opacity:.85;">
			<?php printf(esc_html__('Updated: %s', 'ai-post-scheduler'), esc_html($generated_at)); ?>
			<?php if ($auto_refresh) : ?>
				· <?php esc_html_e('Auto-refresh every 60s is on', 'ai-post-scheduler'); ?>
			<?php endif; ?>
		</div>

		<div class="aips-author-topics-stats" style="margin-bottom:12px;">
			<div class="aips-stat-card aips-stat-generated">
				<span class="aips-stat-value"><?php echo esc_html(number_format_i18n($workload_cards['next_24h_posts'])); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Next 24h expected posts', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card aips-stat-approved">
				<span class="aips-stat-value"><?php echo esc_html(number_format_i18n($workload_cards['next_24h_topics'])); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Next 24h expected topic generations', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-stat-card">
				<span class="aips-stat-value"><?php echo esc_html(number_format_i18n($workload_cards['largest_run']['count'])); ?></span>
				<span class="aips-stat-label"><?php esc_html_e('Largest scheduled run', 'ai-post-scheduler'); ?></span>
				<div style="font-size:12px;opacity:0.85;"><?php echo esc_html($workload_cards['largest_run']['label']); ?></div>
			</div>
		</div>

		<div class="aips-filter-bar" style="margin-bottom:12px;">
			<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
				<input type="hidden" name="page" value="aips-upcoming" />
				<select name="category" class="aips-form-select">
					<option value="all" <?php selected($filter_state['category'], 'all'); ?>><?php esc_html_e('All Categories', 'ai-post-scheduler'); ?></option>
					<option value="generation" <?php selected($filter_state['category'], 'generation'); ?>><?php esc_html_e('Generation', 'ai-post-scheduler'); ?></option>
					<option value="research" <?php selected($filter_state['category'], 'research'); ?>><?php esc_html_e('Research', 'ai-post-scheduler'); ?></option>
					<option value="maintenance" <?php selected($filter_state['category'], 'maintenance'); ?>><?php esc_html_e('Maintenance', 'ai-post-scheduler'); ?></option>
					<option value="other" <?php selected($filter_state['category'], 'other'); ?>><?php esc_html_e('Other', 'ai-post-scheduler'); ?></option>
				</select>
				<select name="window" class="aips-form-select">
					<option value="all" <?php selected($filter_state['window'], 'all'); ?>><?php esc_html_e('Any Time', 'ai-post-scheduler'); ?></option>
					<option value="1h" <?php selected($filter_state['window'], '1h'); ?>><?php esc_html_e('Next 1 hour', 'ai-post-scheduler'); ?></option>
					<option value="6h" <?php selected($filter_state['window'], '6h'); ?>><?php esc_html_e('Next 6 hours', 'ai-post-scheduler'); ?></option>
					<option value="24h" <?php selected($filter_state['window'], '24h'); ?>><?php esc_html_e('Next 24 hours', 'ai-post-scheduler'); ?></option>
					<option value="7d" <?php selected($filter_state['window'], '7d'); ?>><?php esc_html_e('Next 7 days', 'ai-post-scheduler'); ?></option>
				</select>
				<input type="search" class="aips-form-input" name="s" value="<?php echo esc_attr($filter_state['search']); ?>" placeholder="<?php esc_attr_e('Search event or hook...', 'ai-post-scheduler'); ?>" />
				<label style="display:inline-flex;align-items:center;gap:4px;font-size:12px;"><input type="checkbox" name="auto_refresh" value="1" <?php checked($filter_state['auto_refresh'], 1); ?> /> <?php esc_html_e('Auto-refresh', 'ai-post-scheduler'); ?></label>
				<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e('Apply', 'ai-post-scheduler'); ?></button>
			</form>
		</div>

		<?php if ($details_event) : ?>
			<div class="aips-content-panel" style="margin-bottom: 12px;">
				<div class="aips-panel-body">
					<h2><?php esc_html_e('Event Details', 'ai-post-scheduler'); ?></h2>
					<p><strong><?php esc_html_e('Event', 'ai-post-scheduler'); ?>:</strong> <?php echo esc_html($details_event['event_label']); ?></p>
					<p><strong><?php esc_html_e('Hook', 'ai-post-scheduler'); ?>:</strong> <code><?php echo esc_html($details_event['hook']); ?></code></p>
					<p><strong><?php esc_html_e('Category', 'ai-post-scheduler'); ?>:</strong> <?php echo esc_html(ucfirst($details_event['category'])); ?></p>
					<p><strong><?php esc_html_e('Run Time', 'ai-post-scheduler'); ?>:</strong> <?php echo esc_html($details_event['run_time']); ?> (<?php echo esc_html($details_event['run_time_absolute']); ?>)</p>
					<p><strong><?php esc_html_e('Recurrence', 'ai-post-scheduler'); ?>:</strong> <?php echo esc_html($details_event['recurrence_label']); ?></p>
					<p><strong><?php esc_html_e('Expected Output', 'ai-post-scheduler'); ?>:</strong> <?php echo esc_html($details_event['estimate']['value'] . ' ' . $details_event['estimate']['type']); ?> — <?php echo esc_html($details_event['estimate']['details']); ?></p>
					<?php if (!empty($details_event['args'])) : ?>
						<p><strong><?php esc_html_e('Arguments', 'ai-post-scheduler'); ?>:</strong> <code><?php echo esc_html(wp_json_encode($details_event['args'])); ?></code></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="aips-content-panel">
			<div class="aips-panel-body no-padding">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e('Event', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Category', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Run Time', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Expected Output', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($events)) : ?>
							<tr><td colspan="5"><?php esc_html_e('No upcoming scheduled events found.', 'ai-post-scheduler'); ?></td></tr>
						<?php else : ?>
							<?php foreach ($events as $event) : ?>
								<?php
								$run_url = wp_nonce_url(add_query_arg(array(
									'action' => 'aips_run_upcoming_event',
									'hook'   => $event['hook'],
									'ts'     => $event['timestamp'],
									'hash'   => $event['hash'],
								), admin_url('admin-ajax.php')), 'aips_run_upcoming_event');
								$details_url = add_query_arg(array('page' => 'aips-upcoming', 'details' => rawurlencode($event['hook']), 'details_ts' => $event['timestamp'], 'category' => $filter_state['category'], 'window' => $filter_state['window'], 's' => $filter_state['search'], 'auto_refresh' => $filter_state['auto_refresh']), admin_url('admin.php'));
								?>
								<tr>
									<td>
										<strong><?php echo esc_html($event['event_label']); ?></strong><br />
										<code><?php echo esc_html($event['hook']); ?></code>
									</td>
									<td><?php echo esc_html(ucfirst($event['category'])); ?></td>
									<td><?php echo esc_html($event['run_time'] . ' (' . $event['run_time_absolute'] . ')'); ?></td>
									<td><?php echo esc_html($event['estimate']['value'] . ' ' . $event['estimate']['type']); ?></td>
									<td>
										<a href="<?php echo esc_url($run_url); ?>"><?php esc_html_e('Run Now', 'ai-post-scheduler'); ?></a>
										 |
										<a href="<?php echo esc_url($details_url); ?>"><?php esc_html_e('View Details', 'ai-post-scheduler'); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<?php if ($auto_refresh) : ?>
<script>
setTimeout(function(){
	window.location.reload();
}, 60000);
</script>
<?php endif; ?>
