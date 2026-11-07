<?php
/**
 * Dashboard Upcoming Outlook Meta Box Partial
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
<div class="aips-outlook-content">
	<div class="aips-outlook-badge-wrap aips-outlook-header-row">
		<span class="aips-section-meta"><?php esc_html_e('Next 30 Days schedule pipeline', 'ai-post-scheduler'); ?></span>
		<span class="aips-badge aips-badge-info">
			<?php echo sprintf(esc_html(_n('%d Scheduled Run', '%d Scheduled Runs', (int) ($upcoming_runs_count ?? 0), 'ai-post-scheduler')), (int) ($upcoming_runs_count ?? 0)); ?>
		</span>
	</div>
	
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
							'blueprint'         => __('Blueprint', 'ai-post-scheduler'),
							'author_workflow'   => __('Blueprint', 'ai-post-scheduler'),
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
		<p class="aips-empty-state-description"><?php esc_html_e('No automated runs scheduled for the next 30 days.', 'ai-post-scheduler'); ?></p>
	</div>
	<?php endif; ?>
</div>
