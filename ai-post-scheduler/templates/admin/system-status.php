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
                        <span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span>
                        <?php esc_html_e('Run Onboarding Wizard', 'ai-post-scheduler'); ?>
                    </a>
                </div>
            </div>
        </div>
<?php endif; ?>

        <!-- Content -->
        <div class="aips-status-page">
            <div class="aips-status-data-controls">
                <button type="button" class="aips-btn aips-btn-ghost aips-btn-sm aips-status-sections-toggle" data-mode="expand">
                    <?php esc_html_e('Expand all', 'ai-post-scheduler'); ?>
                </button>
            </div>

            <?php $section_index = 0; ?>
            <?php foreach ($system_info as $section => $checks) : ?>
                <?php if (empty($checks)) continue; ?>
                <?php
                $section_title = ucwords(str_replace(array('_', '-'), ' ', (string) $section));
                $is_expanded = $section_index < 3;
                $section_body_id = 'aips-status-section-body-' . sanitize_title((string) $section) . '-' . (string) $section_index;
                ?>

                <!-- Section Panel -->
                <div class="aips-content-panel aips-status-data-panel">
                    <div class="aips-panel-header">
                        <h2><?php echo esc_html($section_title); ?></h2>
                        <button
                            type="button"
                            class="aips-btn aips-btn-ghost aips-btn-sm aips-panel-collapse-toggle"
                            data-target="<?php echo esc_attr($section_body_id); ?>"
                            aria-expanded="<?php echo esc_attr($is_expanded ? 'true' : 'false'); ?>"
                        >
                            <span class="dashicons <?php echo esc_attr($is_expanded ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'); ?>" aria-hidden="true"></span>
                            <span class="aips-panel-collapse-label"><?php echo esc_html($is_expanded ? __('Collapse', 'ai-post-scheduler') : __('Expand', 'ai-post-scheduler')); ?></span>
                        </button>
                    </div>
                    <div
                        id="<?php echo esc_attr($section_body_id); ?>"
                        class="aips-panel-body no-padding aips-status-data-panel-body"
                        <?php if (!$is_expanded) : ?>style="display:none;" aria-hidden="true"<?php else : ?>aria-hidden="false"<?php endif; ?>
                    >
                        <table class="aips-table aips-health-check-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Check', 'ai-post-scheduler'); ?></th>
                                    <th><?php esc_html_e('Value', 'ai-post-scheduler'); ?></th>
                                    <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checks as $key => $check) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($check['label']); ?></strong></td>
                                        <td>
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
                                                    <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>
                                                    <?php esc_html_e('Reset Circuit', 'ai-post-scheduler'); ?>
                                                </button>
                                                <span class="aips-reset-circuit-result" style="display:none; margin-left: 8px;"></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($check['status'] === 'ok') : ?>
                                                <span class="aips-badge aips-badge-success">
                                                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                                    <?php esc_html_e('OK', 'ai-post-scheduler'); ?>
                                                </span>
                                            <?php elseif ($check['status'] === 'warning') : ?>
                                                <span class="aips-badge aips-badge-warning">
                                                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                                    <?php esc_html_e('Warning', 'ai-post-scheduler'); ?>
                                                </span>
                                            <?php elseif ($check['status'] === 'error') : ?>
                                                <span class="aips-badge aips-badge-error">
                                                    <span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
                                                    <?php esc_html_e('Error', 'ai-post-scheduler'); ?>
                                                </span>
                                            <?php else : ?>
                                                <span class="aips-badge aips-badge-info">
                                                    <span class="dashicons dashicons-info" aria-hidden="true"></span>
                                                    <?php esc_html_e('Info', 'ai-post-scheduler'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php $section_index++; ?>
            <?php endforeach; ?>

            <div class="aips-status-actions-sections">
            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2><span class="dashicons dashicons-chart-area" aria-hidden="true"></span> <?php esc_html_e('Unified Operations Health', 'ai-post-scheduler'); ?></h2>
                </div>
                <div class="aips-panel-body">
                    <p><?php esc_html_e('Single-page visibility for telemetry, cron health, queue depth, failed jobs, and dependency status. Use the one-click operations below for safe recovery workflows.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-btn-group aips-action-group">
                        <button type="button" class="aips-btn aips-btn-secondary aips-status-op" data-op="aips_status_reschedule_missed_cron"><?php esc_html_e('Reschedule Missed Cron Hooks', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-secondary aips-status-op" data-op="aips_status_retry_failed_slices"><?php esc_html_e('Retry Failed Slices', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-secondary aips-status-op" data-op="aips_status_repair_campaign_data"><?php esc_html_e('Repair Campaign Data', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-secondary aips-status-op" data-op="aips_status_clear_partial_generations"><?php esc_html_e('Clear Stuck Partial Generations', 'ai-post-scheduler'); ?></button>
                        <button type="button" class="aips-btn aips-btn-secondary aips-status-op" data-op="aips_status_cleanup_stale_jobs_cache"><?php esc_html_e('Cleanup Stale Batch Jobs/Cache', 'ai-post-scheduler'); ?></button>
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
                        <button type="button" class="aips-btn aips-btn-secondary aips-rebuild-cache-btn"><?php esc_html_e('Rebuild Caches', 'ai-post-scheduler'); ?></button>
                    </div>
                    </div>
                </div>

            <!-- Tools Row: Cron + AI Engine -->
            <div class="aips-status-tools-row">
                <!-- Cron Status -->
                <div class="aips-content-panel">
                    <div class="aips-panel-header">
                        <h2>
                            <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                            <?php esc_html_e('Cron Status', 'ai-post-scheduler'); ?>
                        </h2>
                    </div>
                    <div class="aips-panel-body">
                        <?php
                        $next_scheduled = wp_next_scheduled('aips_generate_scheduled_posts');
                        if ($next_scheduled) : ?>
                            <p class="aips-status-message aips-status-success">
                                <span class="aips-badge aips-badge-success">
                                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
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
                                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                    <?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
                                </span>
                                <?php esc_html_e('Cron job is not scheduled. Try deactivating and reactivating the plugin.', 'ai-post-scheduler'); ?>
                            </p>
                        <?php endif; ?>

                        <p><?php esc_html_e('If duplicate or stacked cron events have accumulated (which can trigger excessive AI calls), flush and re-register all plugin events with one click.', 'ai-post-scheduler'); ?></p>

                        <div class="aips-btn-group aips-action-group">
                            <button type="button" class="aips-btn aips-btn-secondary aips-flush-cron">
                                <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>
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
                            <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                            <?php esc_html_e('AI Engine Status', 'ai-post-scheduler'); ?>
                        </h2>
                    </div>
                    <div class="aips-panel-body">
                        <?php if (class_exists('Meow_MWAI_Core')): ?>
                            <p class="aips-status-message aips-status-success">
                                <span class="aips-badge aips-badge-success">
                                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                    <?php esc_html_e('Connected', 'ai-post-scheduler'); ?>
                                </span>
                                <?php esc_html_e('AI Engine is installed and active.', 'ai-post-scheduler'); ?>
                            </p>
                            <div class="aips-test-connection-wrapper">
                                <button type="button" id="aips-test-connection" class="aips-btn aips-btn-secondary">
                                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                    <?php esc_html_e('Test Connection', 'ai-post-scheduler'); ?>
                                </button>
                                <span class="spinner aips-spinner-inline"></span>
                                <span id="aips-connection-result" class="aips-connection-result"></span>
                            </div>
                        <?php else: ?>
                            <p class="aips-status-message aips-status-error">
                                <span class="aips-badge aips-badge-error">
                                    <span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
                                    <?php esc_html_e('Not Found', 'ai-post-scheduler'); ?>
                                </span>
                                <?php esc_html_e('AI Engine is not installed or not activated. Please install and activate the AI Engine plugin.', 'ai-post-scheduler'); ?>
                            </p>
                            <p class="aips-ai-engine-download-wrap">
                                <a href="https://wordpress.org/plugins/ai-engine/" target="_blank" rel="noopener" class="aips-btn aips-btn-primary">
                                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
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
                            <span class="dashicons dashicons-database" aria-hidden="true"></span>
                            <?php esc_html_e('Database Management', 'ai-post-scheduler'); ?>
                        </h2>
                    </div>
                    <div class="aips-panel-body">
                        <p><?php esc_html_e("Use these tools to repair, reinstall, or wipe the plugin's database tables. Destructive actions require confirmation.", 'ai-post-scheduler'); ?></p>

                        <div class="aips-btn-group aips-db-actions">
                            <button type="button" class="aips-btn aips-btn-secondary aips-repair-db">
                                <span class="dashicons dashicons-hammer" aria-hidden="true"></span>
                                <?php esc_html_e('Repair DB Tables', 'ai-post-scheduler'); ?>
                            </button>

                            <button type="button" class="aips-btn aips-btn-secondary aips-fix-datetime-db">
                                <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                                <?php esc_html_e('Fix Date/Time Values in DB', 'ai-post-scheduler'); ?>
                            </button>

                            <button type="button" class="aips-btn aips-btn-secondary aips-reinstall-db">
                                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                                <?php esc_html_e('Reinstall DB Tables', 'ai-post-scheduler'); ?>
                            </button>

                            <button type="button" class="aips-btn aips-btn-danger aips-wipe-db">
                                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
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
                            <span class="dashicons dashicons-migrate" aria-hidden="true"></span>
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
                            <span class="dashicons dashicons-download" aria-hidden="true"></span>
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
                            <span class="dashicons dashicons-upload" aria-hidden="true"></span>
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
                        <span class="dashicons dashicons-bell" aria-hidden="true"></span>
                        <?php esc_html_e('Notifications Maintenance', 'ai-post-scheduler'); ?>
                    </h2>
                </div>
                <div class="aips-panel-body">
                    <p><?php esc_html_e('Run a one-time hygiene command to clean legacy notification options, unschedule deprecated cron hooks, and normalize notification channel preferences.', 'ai-post-scheduler'); ?></p>

                    <div class="aips-btn-group aips-action-group">
                        <button type="button" class="aips-btn aips-btn-secondary aips-notifications-hygiene">
                            <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
                            <?php esc_html_e('Run Notifications Hygiene', 'ai-post-scheduler'); ?>
                        </button>
                    </div>

                    <div class="aips-notifications-hygiene-result"></div>
                </div>
            </div>
            </div>
    <?php if (empty($embedded)) : ?>
        </div>
    </div>
    <?php endif; ?>
</div>
