<?php
if (!defined('ABSPATH')) {
	exit;
}

$provider = new AIPS_System_Info_Provider();
$system_info = $provider->get_system_info();
$markdown_report = $provider->get_markdown_report();
?>
<div class="aips-status-page aips-system-info-page">
	<!-- System Info Header & Copy Report -->
	<div class="aips-system-health-panel">
		<div class="aips-system-health-header aips-flex-header">
			<div>
				<h2><span class="dashicons dashicons-info"></span> <?php esc_html_e('System Info', 'ai-post-scheduler'); ?></h2>
				<p><?php esc_html_e('Environment, database, filesystem, and AI integration specifications for this WordPress installation.', 'ai-post-scheduler'); ?></p>
			</div>
			<div class="aips-system-info-actions">
				<textarea id="aips-system-report-raw" class="aips-clipboard-hidden" readonly><?php echo esc_textarea($markdown_report); ?></textarea>
				<button type="button" class="aips-btn aips-btn-secondary aips-copy-system-report">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e('Copy System Report', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- System Info Grid -->
	<div class="aips-status-grid">
		<?php foreach ($system_info as $section => $checks) : ?>
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
										<a href="#" class="aips-toggle-log-details" data-target="log-details-info-<?php echo esc_attr($section . '-' . $key); ?>">
											<?php esc_html_e('Show Details', 'ai-post-scheduler'); ?>
										</a>
										<div id="log-details-info-<?php echo esc_attr($section . '-' . $key); ?>" class="aips-log-details" style="display:none; margin-top:6px;">
											<textarea class="aips-form-input" rows="6" readonly><?php echo esc_textarea(implode("\n", (array) $check['details'])); ?></textarea>
										</div>
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
