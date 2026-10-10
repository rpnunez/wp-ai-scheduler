<?php
if (!defined('ABSPATH')) {
	exit;
}

$diagnostics_service = AIPS_Container::get_instance()->has(AIPS_System_Diagnostics_Service::class)
	? AIPS_Container::get_instance()->make(AIPS_System_Diagnostics_Service::class)
	: new AIPS_System_Diagnostics_Service();

$refresh_task_groups = $diagnostics_service->get_refresh_task_groups();

$active_ai_provider          = AIPS_AI_Provider_Factory::create();
$ai_provider_label           = $active_ai_provider->get_label();
$ai_provider_available       = $active_ai_provider->is_available();
$ai_provider_unavailable_msg = $ai_provider_available ? '' : $active_ai_provider->get_unavailable_reason();
?>
<div class="aips-status-page aips-system-health-page">
	<!-- System Health Panel -->
	<div class="aips-system-health-panel">
		<div class="aips-system-health-header">
			<h2><span class="dashicons dashicons-heart"></span> <?php esc_html_e('System Health & Tools', 'ai-post-scheduler'); ?></h2>
			<p><?php esc_html_e('One-click recovery, cache rebuilding, and database maintenance operations.', 'ai-post-scheduler'); ?></p>
		</div>

		<!-- System Health Card -->
		<div class="aips-health-card aips-refresh-task-selector">
			<?php if (!empty($refresh_task_groups)) : ?>
			<div class="aips-health-section aips-maintenance-tasks-section">
				<div class="aips-health-section-header">
					<div class="aips-health-section-title-wrap">
						<h3 class="aips-health-section-title">
							<span class="dashicons dashicons-admin-tools"></span>
							<?php esc_html_e('Maintenance & Recovery Tasks', 'ai-post-scheduler'); ?>
						</h3>
					</div>
					<div class="aips-health-section-action">
						<span class="spinner aips-spinner-inline"></span>
						<button type="button" class="aips-btn aips-btn-primary aips-refresh-system">
							<span class="dashicons dashicons-update"></span>
							<span class="aips-refresh-system-label"><?php esc_html_e('Refresh System', 'ai-post-scheduler'); ?></span>
						</button>
					</div>
				</div>
				<div class="aips-health-section-body">
					<?php foreach ($refresh_task_groups as $task_group) : ?>
						<div class="aips-status-op-group">
							<span class="aips-status-op-group-label"><?php echo esc_html($task_group['label']); ?></span>
							<div class="aips-checkbox-group aips-refresh-task-list">
								<?php foreach ($task_group['tasks'] as $task) : ?>
									<?php $task_input_id = 'aips-refresh-task-' . $task['step']; ?>
									<label class="aips-checkbox-label" for="<?php echo esc_attr($task_input_id); ?>">
										<input type="checkbox" id="<?php echo esc_attr($task_input_id); ?>" class="aips-refresh-task" name="aips_refresh_tasks[]" value="<?php echo esc_attr($task['step']); ?>" checked>
										<span><?php echo esc_html($task['label']); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="aips-health-section-footer">
					<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-toggle-refresh-tasks"><?php esc_html_e('Toggle All', 'ai-post-scheduler'); ?></button>
				</div>
			</div>
			<?php endif; ?>

			<?php $cache_subsystems = AIPS_Cache_Policy::get_subsystems(); ?>
			<div class="aips-health-section aips-cache-subsystems-section">
				<div class="aips-health-section-header">
					<div class="aips-health-section-title-wrap">
						<h3 class="aips-health-section-title">
							<span class="dashicons dashicons-database"></span>
							<?php esc_html_e('Cache Subsystems', 'ai-post-scheduler'); ?>
						</h3>
					</div>
					<div class="aips-health-section-action">
						<span class="spinner aips-spinner-inline"></span>
						<button type="button" class="aips-btn aips-btn-primary aips-rebuild-cache-btn">
							<span class="dashicons dashicons-update"></span>
							<span class="aips-rebuild-cache-label"><?php esc_html_e('Rebuild Cache', 'ai-post-scheduler'); ?></span>
						</button>
					</div>
				</div>
				<div class="aips-health-section-body">
					<div class="aips-status-op-group">
						<span class="aips-status-op-group-label"><?php esc_html_e('Subsystem Caches', 'ai-post-scheduler'); ?></span>
						<div class="aips-checkbox-group aips-cache-subsystem-list">
							<?php foreach ($cache_subsystems as $key => $info) : ?>
								<?php $cache_input_id = 'aips-cache-subsystem-' . $key; ?>
								<label class="aips-checkbox-label" for="<?php echo esc_attr($cache_input_id); ?>">
									<input type="checkbox" id="<?php echo esc_attr($cache_input_id); ?>" class="aips-cache-subsystem-task" name="aips_cache_subsystems[]" value="<?php echo esc_attr($key); ?>" checked>
									<span><?php echo esc_html($info['label']); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="aips-health-section-footer">
					<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-toggle-cache-tasks"><?php esc_html_e('Toggle All', 'ai-post-scheduler'); ?></button>
				</div>
			</div>
		</div>

		<div class="aips-refresh-system-results" style="display:none;"></div>
		<div class="aips-status-op-result" style="display:none;"></div>
	</div>

	<!-- Tools Row: Cron + AI Engine -->
	<div class="aips-status-tools-row">
		<!-- Cron Status -->
		<div class="aips-content-panel">
			<div class="aips-panel-header">
				<h2>
					<span class="dashicons dashicons-clock"></span>
					<?php esc_html_e('Cron Status', 'ai-post-scheduler'); ?>
				</h2>
			</div>
			<div class="aips-panel-body">
				<?php
				$next_scheduled = wp_next_scheduled('aips_generate_scheduled_posts');
				if ($next_scheduled) : ?>
					<p class="aips-status-message aips-status-success">
						<span class="aips-badge aips-badge-success">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
						</span>
						<?php
						printf(
							esc_html__('Next scheduled check: %s', 'ai-post-scheduler'),
							'<strong>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled)) . '</strong>'
						);
						?>
					</p>
				<?php else : ?>
					<p class="aips-status-message aips-status-error">
						<span class="aips-badge aips-badge-warning">
							<span class="dashicons dashicons-warning"></span>
							<?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
						</span>
						<?php esc_html_e('Cron job is not scheduled. Try deactivating and reactivating the plugin.', 'ai-post-scheduler'); ?>
					</p>
				<?php endif; ?>

				<p><?php esc_html_e('If duplicate or stacked cron events have accumulated (which can trigger excessive AI calls), flush and re-register all plugin events with one click.', 'ai-post-scheduler'); ?></p>

				<div class="aips-btn-group aips-action-group">
					<button type="button" class="aips-btn aips-btn-secondary aips-flush-cron">
						<span class="dashicons dashicons-controls-repeat"></span>
						<?php esc_html_e('Flush WP-Cron Events', 'ai-post-scheduler'); ?>
					</button>
				</div>

				<div class="aips-flush-cron-result"></div>
			</div>
		</div>

		<!-- AI Provider Status -->
		<div class="aips-content-panel">
			<div class="aips-panel-header">
				<h2>
					<span class="dashicons dashicons-admin-plugins"></span>
					<?php esc_html_e('AI Provider Status', 'ai-post-scheduler'); ?>
				</h2>
			</div>
			<div class="aips-panel-body">
				<?php if (!empty($ai_provider_available)): ?>
					<p class="aips-status-message aips-status-success">
						<span class="aips-badge aips-badge-success">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e('Configured', 'ai-post-scheduler'); ?>
						</span>
						<?php
						printf(
							/* translators: %s: active AI provider label. */
							esc_html__('%s is selected and locally configured. Use Test Connection to verify live access.', 'ai-post-scheduler'),
							esc_html($ai_provider_label)
						);
						?>
					</p>
					<div class="aips-test-connection-wrapper">
						<button type="button" id="aips-test-connection" class="aips-btn aips-btn-secondary">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e('Test Connection', 'ai-post-scheduler'); ?>
						</button>
						<span class="spinner aips-spinner-inline"></span>
						<span id="aips-connection-result" class="aips-connection-result"></span>
					</div>
				<?php else: ?>
					<p class="aips-status-message aips-status-error">
						<span class="aips-badge aips-badge-error">
							<span class="dashicons dashicons-dismiss"></span>
							<?php esc_html_e('Not Available', 'ai-post-scheduler'); ?>
						</span>
						<?php
						if (!empty($ai_provider_unavailable_msg)) {
							echo esc_html($ai_provider_unavailable_msg);
						} else {
							esc_html_e('No AI provider is available. Install the Meow Apps AI Engine plugin or configure a WordPress AI Client connector.', 'ai-post-scheduler');
						}
						?>
					</p>
					<p class="aips-ai-engine-download-wrap">
						<a href="https://wordpress.org/plugins/ai-engine/" target="_blank" rel="noopener" class="aips-btn aips-btn-primary">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e('Download AI Engine', 'ai-post-scheduler'); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Database Management -->
	<div class="aips-content-panel">
		<div class="aips-panel-header">
			<h2>
				<span class="dashicons dashicons-database"></span>
				<?php esc_html_e('Database Management', 'ai-post-scheduler'); ?>
			</h2>
		</div>
		<div class="aips-panel-body">
			<p><?php esc_html_e("Use these tools to repair, reinstall, or wipe the plugin's database tables. Destructive actions require confirmation.", 'ai-post-scheduler'); ?></p>

			<div class="aips-btn-group aips-db-actions">
				<button type="button" class="aips-btn aips-btn-secondary aips-repair-db">
					<span class="dashicons dashicons-hammer"></span>
					<?php esc_html_e('Repair DB Tables', 'ai-post-scheduler'); ?>
				</button>

				<button type="button" class="aips-btn aips-btn-secondary aips-fix-datetime-db">
					<span class="dashicons dashicons-clock"></span>
					<?php esc_html_e('Fix Date/Time Values in DB', 'ai-post-scheduler'); ?>
				</button>

				<button type="button" class="aips-btn aips-btn-secondary aips-reinstall-db">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e('Reinstall DB Tables', 'ai-post-scheduler'); ?>
				</button>

				<button type="button" class="aips-btn aips-btn-danger aips-wipe-db">
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e('Wipe Plugin Data', 'ai-post-scheduler'); ?>
				</button>
			</div>

			<div class="aips-backup-option-wrap">
				<label class="aips-backup-label">
					<input type="checkbox" id="aips-backup-db" value="1">
					<?php esc_html_e('Back up data before reinstalling (data will be restored afterwards)', 'ai-post-scheduler'); ?>
				</label>
			</div>
		</div>
	</div>
</div>
