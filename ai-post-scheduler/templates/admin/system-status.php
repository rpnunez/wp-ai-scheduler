<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('System Status', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Monitor system health, PHP configuration, WordPress environment, and plugin compatibility.', 'ai-post-scheduler'); ?></p>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="aips-status-page">
            <?php foreach ($system_info as $section => $checks) : ?>
                <?php if (empty($checks)) continue; ?>

                <!-- Section Panel -->
                <div class="aips-content-panel" style="margin-bottom: 20px;">
                    <div class="aips-panel-header">
                        <h2><?php echo esc_html(ucfirst($section)); ?></h2>
                    </div>
                    <div class="aips-panel-body no-padding">
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
                                                <a href="#" class="aips-toggle-log-details" data-target="log-details-<?php echo esc_attr($key); ?>" style="font-size: 13px;">
                                                    <?php esc_html_e('Show Details', 'ai-post-scheduler'); ?>
                                                </a>
                                                <div id="log-details-<?php echo esc_attr($key); ?>" class="aips-log-details" style="display:none; margin-top: 10px;">
                                                    <textarea class="aips-form-input" rows="10" readonly style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea(implode("\n", $check['details'])); ?></textarea>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
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
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Database Management -->
            <div class="aips-content-panel" style="margin-bottom: 20px;">
                <div class="aips-panel-header">
                    <h2>
                        <span class="dashicons dashicons-database" style="margin-right: 5px;"></span>
                        <?php esc_html_e('Database Management', 'ai-post-scheduler'); ?>
                    </h2>
                </div>
                <div class="aips-panel-body">
                    <p><?php esc_html_e("Use these tools to repair, reinstall, or wipe the plugin's database tables. Destructive actions require confirmation.", 'ai-post-scheduler'); ?></p>

                    <div class="aips-btn-group" style="margin-bottom: 16px;">
                        <button type="button" class="aips-btn aips-btn-secondary aips-repair-db">
                            <span class="dashicons dashicons-hammer"></span>
                            <?php esc_html_e('Repair DB Tables', 'ai-post-scheduler'); ?>
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
                        <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" id="aips-backup-db" value="1">
                            <?php esc_html_e('Back up data before reinstalling (data will be restored afterwards)', 'ai-post-scheduler'); ?>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Data Management -->
            <div class="aips-content-panel" style="margin-bottom: 20px;">
                <div class="aips-panel-header">
                    <h2>
                        <span class="dashicons dashicons-migrate" style="margin-right: 5px;"></span>
                        <?php esc_html_e('Data Management', 'ai-post-scheduler'); ?>
                    </h2>
                </div>
                <div class="aips-panel-body">

                    <!-- Export -->
                    <h3 style="margin-top: 0;"><?php esc_html_e('Export', 'ai-post-scheduler'); ?></h3>
                    <p><?php esc_html_e('Download a backup of all plugin data in the selected format.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-btn-group">
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

                    <hr style="margin: 20px 0;">

                    <!-- Import -->
                    <h3 style="margin-top: 0;"><?php esc_html_e('Import', 'ai-post-scheduler'); ?></h3>
                    <p><?php esc_html_e('Restore plugin data from a previously exported file. This will overwrite existing data.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-btn-group">
                        <select id="aips-import-format" class="aips-form-select">
                            <?php foreach ($import_formats as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="aips-import-file" class="screen-reader-text">
                            <?php esc_html_e('Import file', 'ai-post-scheduler'); ?>
                        </label>
                        <input type="file" id="aips-import-file" accept=".sql,.json" style="display: inline-block; vertical-align: middle;">
                        <button type="button" class="aips-btn aips-btn-secondary aips-import-data">
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e('Import Data', 'ai-post-scheduler'); ?>
                        </button>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Toggle log details
jQuery(document).ready(function($) {
    $('.aips-toggle-log-details').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $('#' + target).slideToggle();
        var text = $('#' + target).is(':visible')
            ? <?php echo wp_json_encode( __( 'Hide Details', 'ai-post-scheduler' ) ); ?>
            : <?php echo wp_json_encode( __( 'Show Details', 'ai-post-scheduler' ) ); ?>;
        $(this).text(text);
    });
});
</script>
