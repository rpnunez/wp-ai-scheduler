<?php
if (!defined('ABSPATH')) {
	exit;
}

$status_service = AIPS_Container::get_instance()->has(AIPS_System_Status_Diagnostics_Service::class)
	? AIPS_Container::get_instance()->make(AIPS_System_Status_Diagnostics_Service::class)
	: new AIPS_System_Status_Diagnostics_Service();

$system_info = $status_service->get_system_info();

// Exclude static system info sections which now live in the System Info tab
$exclude_sections = array('environment', 'plugin', 'database', 'filesystem');
$operational_info = array_diff_key($system_info, array_flip($exclude_sections));
?>
<div class="aips-status-page aips-operational-status-page">
	<!-- Operational Status Header -->
	<div class="aips-system-health-panel">
		<div class="aips-system-health-header">
			<h2><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('Operational Status', 'ai-post-scheduler'); ?></h2>
			<p><?php esc_html_e('Live runtime telemetry, generation pipeline throughput, scheduler status, and error logs.', 'ai-post-scheduler'); ?></p>
		</div>
	</div>

	<!-- Diagnostics Grid -->
	<div class="aips-status-grid">
		<?php foreach ($operational_info as $section => $checks) : ?>
			<?php if (empty($checks)) continue; ?>
			<?php $section_title = ucwords(str_replace(array('_', '-'), ' ', (string) $section)); ?>

			<div class="aips-content-panel aips-status-card">
				<div class="aips-panel-header">
					<h2><?php echo esc_html($section_title); ?></h2>
				</div>
				<div class="aips-panel-body no-padding">
					<div class="aips-status-kv">
						<?php foreach ($checks as $key => $check) : ?>
							<div class="aips-status-kv-row">
								<span class="aips-status-kv-label"><?php echo esc_html($check['label']); ?></span>
								<span class="aips-status-kv-value">
									<?php echo esc_html($check['value']); ?>
									<?php if (!empty($check['details'])) : ?>
										<br>
										<a href="#" class="aips-toggle-log-details" data-target="log-details-op-<?php echo esc_attr($key); ?>">
											<?php esc_html_e('Show Details', 'ai-post-scheduler'); ?>
										</a>
										<div id="log-details-op-<?php echo esc_attr($key); ?>" class="aips-log-details" style="display:none; margin-top:6px;">
											<textarea class="aips-form-input" rows="10" readonly><?php echo esc_textarea(implode("\n", (array) $check['details'])); ?></textarea>
										</div>
									<?php endif; ?>
									<?php if (!empty($check['cb_open'])) : ?>
										<br>
										<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-reset-circuit-breaker">
											<span class="dashicons dashicons-controls-repeat"></span>
											<?php esc_html_e('Reset Circuit', 'ai-post-scheduler'); ?>
										</button>
										<span class="aips-reset-circuit-result"></span>
									<?php endif; ?>
								</span>
								<span class="aips-status-kv-status">
									<?php if ($check['status'] === 'ok') : ?>
										<span class="aips-badge aips-badge-success">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e('OK', 'ai-post-scheduler'); ?>
										</span>
									<?php elseif ($check['status'] === 'warning') : ?>
										<span class="aips-badge aips-badge-warning">
											<span class="dashicons dashicons-warning"></span>
											<?php esc_html_e('Warning', 'ai-post-scheduler'); ?>
										</span>
									<?php elseif ($check['status'] === 'error') : ?>
										<span class="aips-badge aips-badge-error">
											<span class="dashicons dashicons-dismiss"></span>
											<?php esc_html_e('Error', 'ai-post-scheduler'); ?>
										</span>
									<?php else : ?>
										<span class="aips-badge aips-badge-info">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e('Info', 'ai-post-scheduler'); ?>
										</span>
									<?php endif; ?>
								</span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
