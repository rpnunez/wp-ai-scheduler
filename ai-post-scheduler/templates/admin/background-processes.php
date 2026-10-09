<?php
/**
 * Diagnostics tab: Background Processes
 *
 * Lists every background process, whether running or not, with live progress
 * (kept current by background-processes.js through the Heartbeat API) and
 * start / pause / resume / stop controls.
 *
 * Variables from AIPS_Diagnostics_Controller::render_background_processes_tab():
 *
 * @var array[] $bg_processes Process snapshots.
 * @var object[] $bg_runs     Recent managed runs, newest first.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

$bg_status_labels = AIPS_Background_Process_Base::get_status_labels();
?>
<div class="aips-status-page aips-background-processes-page">

	<div class="aips-system-health-panel">
		<div class="aips-system-health-header">
			<h2><span class="dashicons dashicons-update"></span> <?php esc_html_e('Background Processes', 'ai-post-scheduler'); ?></h2>
			<p><?php esc_html_e('Long-running jobs that work through your content in small slices. Pause or stop any of them at any time; AI-backed jobs also follow the embeddings rate limits and cooldown from Settings.', 'ai-post-scheduler'); ?></p>
		</div>
	</div>

	<div class="aips-content-panel">
		<div class="aips-panel-header">
			<h2><?php esc_html_e('Processes', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-btn aips-btn-secondary aips-btn-sm" data-aips-bg-pause-all>
				<span class="dashicons dashicons-controls-pause"></span>
				<?php esc_html_e('Pause all', 'ai-post-scheduler'); ?>
			</button>
		</div>
		<div class="aips-panel-body no-padding">
			<table class="aips-table aips-bg-table">
				<thead>
					<tr>
						<th><?php esc_html_e('Process', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Progress', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('AI calls', 'ai-post-scheduler'); ?></th>
						<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($bg_processes as $snapshot) : ?>
						<tr data-aips-bg-key="<?php echo esc_attr($snapshot['key']); ?>" data-aips-bg-status="<?php echo esc_attr($snapshot['status']); ?>" class="<?php echo !empty($snapshot['is_active']) ? 'aips-bg-active' : ''; ?>">
							<td>
								<strong><?php echo esc_html($snapshot['label']); ?></strong>
								<?php if (!empty($snapshot['uses_ai'])) : ?>
									<span class="aips-badge aips-badge-info"><?php esc_html_e('Uses AI', 'ai-post-scheduler'); ?></span>
								<?php endif; ?>
								<span class="aips-bg-message"><?php echo esc_html($snapshot['description']); ?></span>
							</td>
							<td>
								<span class="aips-bg-chip" data-aips-bg-field="status_label"><?php echo esc_html($snapshot['status_label']); ?></span>
								<span class="aips-bg-message" data-aips-bg-field="next_run"></span>
								<span class="aips-bg-message" data-aips-bg-field="message"><?php echo esc_html($snapshot['message']); ?></span>
							</td>
							<td class="aips-bg-progress">
								<span data-aips-bg-field="processed"><?php echo esc_html(number_format_i18n($snapshot['processed'])); ?></span>
								/
								<span data-aips-bg-field="total"><?php echo esc_html(number_format_i18n($snapshot['total'])); ?></span>
								(<span data-aips-bg-field="percent"><?php echo esc_html((string) $snapshot['percent']); ?>%</span>)
								<div class="aips-bg-progress-bar"><span data-aips-bg-bar style="width:<?php echo esc_attr((string) $snapshot['percent']); ?>%"></span></div>
							</td>
							<td>
								<?php if (!empty($snapshot['uses_ai'])) : ?>
									<span data-aips-bg-field="ai_calls_used"><?php echo esc_html(number_format_i18n($snapshot['ai_calls_used'])); ?></span>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td>
								<div class="aips-bg-actions">
									<button type="button" class="aips-btn aips-btn-primary aips-btn-sm" data-aips-bg-action="start" <?php echo empty($snapshot['can_start']) ? 'hidden' : ''; ?>>
										<?php esc_html_e('Start', 'ai-post-scheduler'); ?>
									</button>
									<button type="button" class="aips-btn aips-btn-secondary aips-btn-sm" data-aips-bg-action="pause" <?php echo empty($snapshot['can_pause']) ? 'hidden' : ''; ?>>
										<?php esc_html_e('Pause', 'ai-post-scheduler'); ?>
									</button>
									<button type="button" class="aips-btn aips-btn-primary aips-btn-sm" data-aips-bg-action="resume" <?php echo empty($snapshot['can_resume']) ? 'hidden' : ''; ?>>
										<?php esc_html_e('Resume', 'ai-post-scheduler'); ?>
									</button>
									<button type="button" class="aips-btn aips-btn-ghost aips-btn-danger aips-btn-sm" data-aips-bg-action="cancel" <?php echo empty($snapshot['can_cancel']) ? 'hidden' : ''; ?>>
										<?php esc_html_e('Stop', 'ai-post-scheduler'); ?>
									</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="aips-content-panel">
		<div class="aips-panel-header">
			<h2><?php esc_html_e('Recent runs', 'ai-post-scheduler'); ?></h2>
		</div>
		<div class="aips-panel-body no-padding">
			<?php if (empty($bg_runs)) : ?>
				<p class="aips-bg-message" style="padding:12px 16px;"><?php esc_html_e('No runs recorded yet.', 'ai-post-scheduler'); ?></p>
			<?php else : ?>
				<table class="aips-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Process', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Started', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Processed', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Failed', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('AI calls', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Note', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($bg_runs as $run) : ?>
							<?php
							$run_label = $run->process_key;
							foreach ($bg_processes as $snapshot) {
								if ($snapshot['key'] === $run->process_key) {
									$run_label = $snapshot['label'];
									break;
								}
							}
							?>
							<tr>
								<td><?php echo esc_html($run_label); ?></td>
								<td><?php echo esc_html($run->started_at ? wp_date('Y-m-d H:i', $run->started_at) : '—'); ?></td>
								<td><?php echo esc_html(isset($bg_status_labels[$run->status]) ? $bg_status_labels[$run->status] : $run->status); ?></td>
								<td><?php echo esc_html(number_format_i18n($run->processed)); ?> / <?php echo esc_html(number_format_i18n($run->total)); ?></td>
								<td><?php echo esc_html(number_format_i18n($run->failed)); ?></td>
								<td><?php echo esc_html(number_format_i18n($run->ai_calls_used)); ?></td>
								<td><?php echo esc_html((string) $run->message); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
