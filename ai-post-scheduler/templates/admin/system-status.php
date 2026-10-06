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

                <div class="aips-refresh-system-layout">
                    <div class="aips-refresh-system-action">
                        <button type="button" class="aips-btn aips-btn-primary aips-refresh-system aips-refresh-system-lg">
                            <span class="dashicons dashicons-update"></span>
                            <span class="aips-refresh-system-label"><?php esc_html_e('Refresh System', 'ai-post-scheduler'); ?></span>
                        </button>
                        <span class="spinner aips-spinner-inline"></span>
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
                    <?php endif; ?>
                </div>

                <div class="aips-refresh-system-results" style="display:none;"></div>

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

            <!-- Database Storage & Table Status -->
            <div class="aips-content-panel aips-db-tables-panel" id="aips-db-tables-panel">
                <div class="aips-panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>
                        <span class="dashicons dashicons-database"></span>
                        <?php esc_html_e('Database Storage & Table Status', 'ai-post-scheduler'); ?>
                    </h2>
                    <div>
                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-refresh-tables-btn">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Refresh Sizes', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                </div>
                <div class="aips-panel-body no-padding">
                    <div style="padding: 12px 16px 0;">
                        <p class="description">
                            <?php esc_html_e('Monitor table disk usage, records, and overhead. Large tables such as telemetry and generation history can be pruned safely to reclaim MySQL disk space.', 'ai-post-scheduler'); ?>
                        </p>
                    </div>
                    <div class="aips-table-wrap" style="overflow-x: auto;">
                        <table class="aips-table aips-db-status-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Table Name', 'ai-post-scheduler'); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e('Records', 'ai-post-scheduler'); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e('Data Size', 'ai-post-scheduler'); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e('Index Size', 'ai-post-scheduler'); ?></th>
                                    <th style="text-align: center;"><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e('Overhead', 'ai-post-scheduler'); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="aips-db-tables-tbody">
                                <?php if (!empty($tables_status)) : ?>
                                    <?php
                                    $tot_records  = 0;
                                    $tot_data     = 0;
                                    $tot_index    = 0;
                                    $tot_overhead = 0;
                                    ?>
                                    <?php foreach ($tables_status as $table) : ?>
                                        <?php
                                        $tot_records  += isset($table['records']) ? (int) $table['records'] : 0;
                                        $tot_data     += isset($table['data_size']) ? (int) $table['data_size'] : 0;
                                        $tot_index    += isset($table['index_size']) ? (int) $table['index_size'] : 0;
                                        $tot_overhead += isset($table['overhead']) ? (int) $table['overhead'] : 0;
                                        $short         = isset($table['short_name']) ? $table['short_name'] : '';
                                        ?>
                                        <tr id="aips-tbl-row-<?php echo esc_attr($short); ?>" data-table="<?php echo esc_attr($short); ?>">
                                            <td>
                                                <strong><code><?php echo esc_html($table['table']); ?></code></strong>
                                                <?php if ('aips_telemetry' === $short && !AIPS_Telemetry::is_enabled()) : ?>
                                                    <span class="aips-badge aips-badge-info" style="margin-left: 6px; font-size: 11px;">
                                                        <?php esc_html_e('Collection Inactive', 'ai-post-scheduler'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;" class="aips-cell-records">
                                                <?php echo esc_html(isset($table['formatted_records']) ? $table['formatted_records'] : number_format_i18n($table['records'])); ?>
                                            </td>
                                            <td style="text-align: right;" class="aips-cell-data">
                                                <?php echo esc_html(isset($table['formatted_data_size']) ? $table['formatted_data_size'] : size_format($table['data_size'], 2)); ?>
                                            </td>
                                            <td style="text-align: right;" class="aips-cell-index">
                                                <?php echo esc_html(isset($table['formatted_index_size']) ? $table['formatted_index_size'] : size_format($table['index_size'], 2)); ?>
                                            </td>
                                            <td style="text-align: center;" class="aips-cell-type">
                                                <code><?php echo esc_html(isset($table['type']) ? $table['type'] : 'InnoDB'); ?></code>
                                            </td>
                                            <td style="text-align: right;" class="aips-cell-overhead">
                                                <?php
                                                $oh_formatted = isset($table['formatted_overhead']) ? $table['formatted_overhead'] : size_format($table['overhead'], 2);
                                                if (!empty($table['overhead']) && $table['overhead'] > 1048576) {
                                                    echo '<span style="color:#d63638; font-weight:600;">' . esc_html($oh_formatted) . '</span>';
                                                } else {
                                                    echo esc_html($oh_formatted);
                                                }
                                                ?>
                                            </td>
                                            <td style="text-align: right;" class="aips-cell-actions">
                                                <div class="aips-btn-group" style="justify-content: flex-end; gap: 4px;">
                                                    <?php if ('aips_telemetry' === $short) : ?>
                                                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-prune-telemetry-btn" title="<?php esc_attr_e('Prune telemetry records older than configured retention period', 'ai-post-scheduler'); ?>">
                                                            <span class="dashicons dashicons-clock"></span>
                                                            <?php esc_html_e('Prune Old', 'ai-post-scheduler'); ?>
                                                        </button>
                                                        <button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-purge-telemetry-btn" title="<?php esc_attr_e('Instantly truncate table and reclaim all disk space', 'ai-post-scheduler'); ?>">
                                                            <span class="dashicons dashicons-trash"></span>
                                                            <?php esc_html_e('Purge All', 'ai-post-scheduler'); ?>
                                                        </button>
                                                    <?php elseif ('aips_history_log' === $short) : ?>
                                                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-prune-history-logs-btn" title="<?php esc_attr_e('Prune generation logs older than configured retention period', 'ai-post-scheduler'); ?>">
                                                            <span class="dashicons dashicons-clock"></span>
                                                            <?php esc_html_e('Prune Old', 'ai-post-scheduler'); ?>
                                                        </button>
                                                    <?php elseif ('aips_embeddings' === $short || 'aips_post_embeddings' === $short) : ?>
                                                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-clean-orphaned-embeddings-btn" title="<?php esc_attr_e('Delete embeddings for posts that no longer exist', 'ai-post-scheduler'); ?>">
                                                            <span class="dashicons dashicons-admin-links"></span>
                                                            <?php esc_html_e('Clean Orphans', 'ai-post-scheduler'); ?>
                                                        </button>
                                                    <?php endif; ?>

                                                    <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-optimize-table-btn" data-table="<?php echo esc_attr($short); ?>" title="<?php esc_attr_e('Run OPTIMIZE TABLE to reclaim free space and defragment indexes', 'ai-post-scheduler'); ?>">
                                                        <span class="dashicons dashicons-performance"></span>
                                                        <?php esc_html_e('Optimize', 'ai-post-scheduler'); ?>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="7" class="aips-table-empty">
                                            <?php esc_html_e('No plugin database tables found.', 'ai-post-scheduler'); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($tables_status)) : ?>
                                <tfoot>
                                    <tr style="font-weight: 600; background: #f6f7f7;">
                                        <td><?php esc_html_e('Total Plugin Storage', 'ai-post-scheduler'); ?></td>
                                        <td style="text-align: right;" id="aips-tot-records"><?php echo esc_html(number_format_i18n($tot_records)); ?></td>
                                        <td style="text-align: right;" id="aips-tot-data"><?php echo esc_html(size_format($tot_data, 2)); ?></td>
                                        <td style="text-align: right;" id="aips-tot-index"><?php echo esc_html(size_format($tot_index, 2)); ?></td>
                                        <td style="text-align: center;">—</td>
                                        <td style="text-align: right;" id="aips-tot-overhead"><?php echo esc_html(size_format($tot_overhead, 2)); ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>
                        </table>
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

                    <div>
                        <label class="aips-backup-label">
                            <input type="checkbox" id="aips-backup-db" value="1">
                            <?php esc_html_e('Back up data before reinstalling (data will be restored afterwards)', 'ai-post-scheduler'); ?>
                        </label>
                    </div>
                </div>
            </div>
    <?php if (empty($embedded)) : ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script type="text/html" id="aips-tmpl-confirm-word-dialog">
	<div class="aips-confirm-dialog">
		<div class="aips-confirm-header">
			<h3 id="{{headingId}}" class="aips-confirm-heading">{{heading}}</h3>
		</div>
		<div class="aips-confirm-body">
			<p class="aips-confirm-message">{{message}}</p>
			<div style="margin-top: 14px; padding: 10px 12px; background: #f9f9f9; border: 1px solid #e2e4e7; border-radius: 4px;">
				<label for="{{inputId}}" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">
					<?php esc_html_e('Please type', 'ai-post-scheduler'); ?> <code style="color: #d63638; font-weight: bold; font-size: 14px;">{{requiredWord}}</code> <?php esc_html_e('to confirm:', 'ai-post-scheduler'); ?>
				</label>
				<input type="text" id="{{inputId}}" class="aips-form-input aips-confirm-word-input" style="width: 100%; font-size: 14px; text-transform: uppercase;" autocomplete="off" placeholder="{{requiredWord}}">
			</div>
		</div>
		<div class="aips-confirm-footer">
			<button type="button" class="aips-btn aips-btn-secondary aips-word-cancel-btn">{{cancelLabel}}</button>
			<button type="button" class="{{confirmClass}} aips-word-confirm-btn" disabled>{{confirmLabel}}</button>
		</div>
	</div>
</script>
