<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (empty($embedded)) : ?>
<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('System Status', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Monitor system health, PHP configuration, WordPress environment, and plugin compatibility.', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="aips-btn-group">
                    <a class="aips-btn aips-btn-primary" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('onboarding')); ?>">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                        <?php esc_html_e('Run Onboarding Wizard', 'ai-post-scheduler'); ?>
                    </a>
                </div>
            </div>
        </div>
<?php endif; ?>

        <!-- Content -->
        <div class="aips-status-page">
            <!-- System Health -->
            <div class="aips-system-health-panel">
                <div class="aips-system-health-header">
                    <h2><span class="dashicons dashicons-heart"></span> <?php esc_html_e('System Health', 'ai-post-scheduler'); ?></h2>
                    <p><?php esc_html_e('One-click recovery and cleanup operations. Refresh System runs every safe maintenance operation in a single request.', 'ai-post-scheduler'); ?></p>
                </div>

                <?php if (!empty($refresh_task_groups)) : ?>
                <div class="aips-refresh-task-selector">
                    <div class="aips-refresh-task-selector-header">
                        <span class="aips-status-op-group-label"><?php esc_html_e('Refresh tasks', 'ai-post-scheduler'); ?></span>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-toggle-refresh-tasks"><?php esc_html_e('Toggle All', 'ai-post-scheduler'); ?></button>
                    </div>
                    <?php foreach ($refresh_task_groups as $task_group) : ?>
                        <div class="aips-status-op-group">
                            <span class="aips-status-op-group-label"><?php echo esc_html($task_group['label']); ?></span>
                            <div class="aips-checkbox-group aips-refresh-task-list">
                                <?php foreach ($task_group['tasks'] as $task) : ?>
                                    <label class="aips-checkbox-label">
                                        <input type="checkbox" class="aips-refresh-task" name="aips_refresh_tasks[]" value="<?php echo esc_attr($task['step']); ?>" checked>
                                        <span><?php echo esc_html($task['label']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="aips-refresh-system-row">
                    <button type="button" class="aips-btn aips-btn-primary aips-refresh-system">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh System', 'ai-post-scheduler'); ?>
                    </button>
                    <span class="spinner aips-spinner-inline"></span>
                </div>
                <div class="aips-refresh-system-results" style="display:none;"></div>

                <div class="aips-status-op-group">
                    <span class="aips-status-op-group-label"><?php esc_html_e('Recovery', 'ai-post-scheduler'); ?></span>
                    <div class="aips-btn-group aips-action-group">
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-status-op" data-op="aips_status_reschedule_missed_cron"><?php esc_html_e('Reschedule Missed Cron Hooks', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-status-op" data-op="aips_status_retry_failed_slices"><?php esc_html_e('Retry Failed Slices', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-status-op" data-op="aips_status_repair_campaign_data"><?php esc_html_e('Repair Campaign Data', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-status-op" data-op="aips_status_clear_partial_generations"><?php esc_html_e('Clear Stuck Partial Generations', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-status-op" data-op="aips_status_cleanup_stale_jobs_cache"><?php esc_html_e('Cleanup Stale Batch Jobs/Cache', 'ai-post-scheduler'); ?></button>
                    </div>
                </div>

                <div class="aips-status-op-group">
                    <span class="aips-status-op-group-label"><?php esc_html_e('Cleanup & repair', 'ai-post-scheduler'); ?></span>
                    <div class="aips-btn-group aips-action-group">
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-status-op" data-op="aips_status_cache_maintenance"><?php esc_html_e('Prune Cache Data', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-status-op" data-op="aips_status_cleanup_notifications"><?php esc_html_e('Clean Old Notifications', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-status-op" data-op="aips_status_reset_resilience"><?php esc_html_e('Reset Resilience', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-status-op" data-op="aips_status_repair_datetime"><?php esc_html_e('Repair Schedule Timings', 'ai-post-scheduler'); ?></button>
                    </div>
                </div>

                <div class="aips-status-op-result"></div>

                <?php $cache_subsystems = AIPS_Cache_Policy::get_subsystems(); ?>
                <div class="aips-cache-rebuild-controls">
                    <label for="aips-cache-subsystem"><strong><?php esc_html_e('Rebuild caches:', 'ai-post-scheduler'); ?></strong></label>
                    <select id="aips-cache-subsystem">
                        <option value="all"><?php esc_html_e('All subsystems', 'ai-post-scheduler'); ?></option>
                        <?php foreach ($cache_subsystems as $key => $info) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-rebuild-cache-btn"><?php esc_html_e('Rebuild Caches', 'ai-post-scheduler'); ?></button>
                </div>
            </div>

            <!-- Diagnostics Grid -->
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
                                                <a href="#" class="aips-toggle-log-details" data-target="log-details-<?php echo esc_attr($key); ?>">
                                                    <?php esc_html_e('Show Details', 'ai-post-scheduler'); ?>
                                                </a>
                                                <div id="log-details-<?php echo esc_attr($key); ?>" class="aips-log-details">
                                                    <textarea class="aips-form-input" rows="10" readonly><?php echo esc_textarea(implode("\n", $check['details'])); ?></textarea>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($check['cb_open'])) : ?>
                                                <br>
                                                <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-reset-circuit-breaker" style="margin-top: 6px;">
                                                    <span class="dashicons dashicons-controls-repeat"></span>
                                                    <?php esc_html_e('Reset Circuit', 'ai-post-scheduler'); ?>
                                                </button>
                                                <span class="aips-reset-circuit-result" style="display:none; margin-left: 8px;"></span>
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

                <!-- AI Engine Status -->
                <div class="aips-content-panel">
                    <div class="aips-panel-header">
                        <h2>
                            <span class="dashicons dashicons-admin-plugins"></span>
                            <?php esc_html_e('AI Engine Status', 'ai-post-scheduler'); ?>
                        </h2>
                    </div>
                    <div class="aips-panel-body">
                        <?php if (class_exists('Meow_MWAI_Core')): ?>
                            <p class="aips-status-message aips-status-success">
                                <span class="aips-badge aips-badge-success">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('Connected', 'ai-post-scheduler'); ?>
                                </span>
                                <?php esc_html_e('AI Engine is installed and active.', 'ai-post-scheduler'); ?>
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
                                    <?php esc_html_e('Not Found', 'ai-post-scheduler'); ?>
                                </span>
                                <?php esc_html_e('AI Engine is not installed or not activated. Please install and activate the AI Engine plugin.', 'ai-post-scheduler'); ?>
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

            <!-- Tools Row: Database + Data Management -->
            <div class="aips-status-tools-row">
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

                        <div>
                            <label class="aips-backup-label">
                                <input type="checkbox" id="aips-backup-db" value="1">
                                <?php esc_html_e('Back up data before reinstalling (data will be restored afterwards)', 'ai-post-scheduler'); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Data Management -->
                <div class="aips-content-panel">
                    <div class="aips-panel-header">
                        <h2>
                            <span class="dashicons dashicons-migrate"></span>
                            <?php esc_html_e('Data Management', 'ai-post-scheduler'); ?>
                        </h2>
                    </div>
                    <div class="aips-panel-body">

                    <!-- Export -->
                    <h3 class="aips-panel-section-heading"><?php esc_html_e('Export', 'ai-post-scheduler'); ?></h3>
                    <p><?php esc_html_e('Download a backup of all plugin data in the selected format.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-btn-group">
                        <label for="aips-export-format" class="screen-reader-text">
                            <?php esc_html_e('Export format', 'ai-post-scheduler'); ?>
                        </label>
                        <select id="aips-export-format" class="aips-form-select">
                            <?php foreach ($export_formats as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="aips-btn aips-btn-secondary aips-export-data">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Export Data', 'ai-post-scheduler'); ?>
                        </button>
                    </div>

                    <hr class="aips-section-divider">

                    <!-- Import -->
                    <h3 class="aips-panel-section-heading"><?php esc_html_e('Import', 'ai-post-scheduler'); ?></h3>
                    <p><?php esc_html_e('Restore plugin data from a previously exported file. This will overwrite existing data.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-btn-group">
                        <label for="aips-import-format" class="screen-reader-text">
                            <?php esc_html_e('Import format', 'ai-post-scheduler'); ?>
                        </label>
                        <select id="aips-import-format" class="aips-form-select">
                            <?php foreach ($import_formats as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="aips-import-file" class="screen-reader-text">
                            <?php esc_html_e('Import file', 'ai-post-scheduler'); ?>
                        </label>
                        <input type="file" id="aips-import-file" class="aips-file-input">
                        <button type="button" class="aips-btn aips-btn-secondary aips-import-data">
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e('Import Data', 'ai-post-scheduler'); ?>
                        </button>
                    </div>

                    </div>
                </div>
            </div>

            <!-- Notifications Maintenance -->
            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2>
                        <span class="dashicons dashicons-bell"></span>
                        <?php esc_html_e('Notifications Maintenance', 'ai-post-scheduler'); ?>
                    </h2>
                </div>
                <div class="aips-panel-body">
                    <p><?php esc_html_e('Run a one-time hygiene command to clean legacy notification options, unschedule deprecated cron hooks, and normalize notification channel preferences.', 'ai-post-scheduler'); ?></p>

                    <div class="aips-btn-group aips-action-group">
                        <button type="button" class="aips-btn aips-btn-secondary aips-notifications-hygiene">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e('Run Notifications Hygiene', 'ai-post-scheduler'); ?>
                        </button>
                    </div>

                    <div class="aips-notifications-hygiene-result"></div>
                </div>
            </div>
    <?php if (empty($embedded)) : ?>
        </div>
    </div>
    <?php endif; ?>
</div>
