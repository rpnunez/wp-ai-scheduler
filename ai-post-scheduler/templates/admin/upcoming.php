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

		<?php if ($details_event) : ?>
			<div class="aips-content-panel" style="margin-bottom: 12px;">
				<div class="aips-panel-body">
					<h2><?php esc_html_e('Event Details', 'ai-post-scheduler'); ?></h2>
					<p><strong><?php esc_html_e('Event', 'ai-post-scheduler'); ?>:</strong> <?php echo esc_html($details_event['event_label']); ?></p>
					<p><strong><?php esc_html_e('Hook', 'ai-post-scheduler'); ?>:</strong> <code><?php echo esc_html($details_event['hook']); ?></code></p>
					<p><strong><?php esc_html_e('Run Time', 'ai-post-scheduler'); ?>:</strong> <?php echo esc_html($details_event['run_time']); ?></p>
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
							<th><?php esc_html_e('Run Time', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($events)) : ?>
							<tr><td colspan="3"><?php esc_html_e('No upcoming scheduled events found.', 'ai-post-scheduler'); ?></td></tr>
						<?php else : ?>
							<?php foreach ($events as $event) : ?>
								<?php
								$run_url = wp_nonce_url(add_query_arg(array(
									'action' => 'aips_run_upcoming_event',
									'hook'   => $event['hook'],
									'ts'     => $event['timestamp'],
									'hash'   => $event['hash'],
								), admin_url('admin-post.php')), 'aips_run_upcoming_event');
								$details_url = add_query_arg(array('page' => 'aips-upcoming', 'details' => rawurlencode($event['hook']), 'details_ts' => $event['timestamp']), admin_url('admin.php'));
								?>
								<tr>
									<td>
										<strong><?php echo esc_html($event['event_label']); ?></strong><br />
										<code><?php echo esc_html($event['hook']); ?></code>
									</td>
									<td><?php echo esc_html($event['run_time']); ?></td>
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
