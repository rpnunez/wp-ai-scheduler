<?php
if (!defined('ABSPATH')) {
    exit;
}

// Generate Text Report for Clipboard
$report_text = "System Status Report\nGenerated: " . date('Y-m-d H:i:s') . "\n\n";
foreach ($system_info as $section => $checks) {
    if (empty($checks)) continue;
    $report_text .= "## " . ucfirst($section) . "\n";
    foreach ($checks as $key => $check) {
        $report_text .= "- " . $check['label'] . ": " . $check['value'] . " (" . $check['status'] . ")\n";
        if (!empty($check['details'])) {
            $report_text .= "  Details:\n  " . implode("\n  ", $check['details']) . "\n";
        }
    }
    $report_text .= "\n";
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('System Status', 'ai-post-scheduler'); ?></h1>

    <button type="button" class="button button-secondary aips-copy-btn page-title-action" data-clipboard-target="#aips-full-system-report">
        <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
        <?php esc_html_e('Copy System Report', 'ai-post-scheduler'); ?>
    </button>
    <textarea id="aips-full-system-report" style="display:none;"><?php echo esc_textarea($report_text); ?></textarea>

    <hr class="wp-header-end">

    <div class="aips-status-page">
        <?php foreach ($system_info as $section => $checks) : ?>
            <?php if (empty($checks)) continue; ?>

            <h2 class="title"><?php echo esc_html(ucfirst($section)); ?></h2>

            <table class="widefat striped health-check-table" cellspacing="0">
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
                            <td><?php echo esc_html($check['label']); ?></td>
                            <td>
                                <?php echo esc_html($check['value']); ?>
                                <?php if (!empty($check['details'])) : ?>
                                    <br>
                                    <a href="#" class="aips-toggle-log-details" data-target="log-details-<?php echo esc_attr($key); ?>">
                                        <?php esc_html_e('Show Details', 'ai-post-scheduler'); ?>
                                    </a>
                                    <div id="log-details-<?php echo esc_attr($key); ?>" class="aips-log-details" style="display:none; margin-top: 10px;">
                                        <p style="text-align: right; margin-bottom: 5px;">
                                            <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="<?php echo esc_attr(implode("\n", $check['details'])); ?>">
                                                <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy Log', 'ai-post-scheduler'); ?>
                                            </button>
                                        </p>
                                        <textarea class="large-text code" rows="10" readonly><?php echo esc_textarea(implode("\n", $check['details'])); ?></textarea>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($check['status'] === 'ok') : ?>
                                    <span class="dashicons dashicons-yes" style="color: #46b450;"></span>
                                <?php elseif ($check['status'] === 'warning') : ?>
                                    <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                                <?php elseif ($check['status'] === 'error') : ?>
                                    <span class="dashicons dashicons-no" style="color: #dc3232;"></span>
                                <?php else : ?>
                                    <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
        <?php endforeach; ?>

        <h2 class="title"><?php esc_html_e('Database Actions', 'ai-post-scheduler'); ?></h2>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div class="card" style="flex: 1; min-width: 300px;">
                <h3><?php esc_html_e('Repair & Maintenance', 'ai-post-scheduler'); ?></h3>
                <p><?php esc_html_e('Use these tools to fix database issues or reset the plugin.', 'ai-post-scheduler'); ?></p>

                <p>
                    <button type="button" class="button button-primary aips-repair-db"><?php esc_html_e('Repair DB Tables', 'ai-post-scheduler'); ?></button>
                    <span class="description"><?php esc_html_e('Runs the database migration script to fix missing tables or columns.', 'ai-post-scheduler'); ?></span>
                </p>

                <hr>

                <p>
                    <label>
                        <input type="checkbox" id="aips-backup-db">
                        <?php esc_html_e('Backup and Restore Data (Experimental)', 'ai-post-scheduler'); ?>
                    </label>
                </p>
                <p>
                    <button type="button" class="button button-secondary aips-reinstall-db"><?php esc_html_e('Reinstall DB Tables', 'ai-post-scheduler'); ?></button>
                    <span class="description"><?php esc_html_e('Drops and recreates all plugin tables. Use with caution.', 'ai-post-scheduler'); ?></span>
                </p>

                <hr>

                <p>
                    <button type="button" class="button button-link-delete aips-wipe-db"><?php esc_html_e('Wipe Plugin Data', 'ai-post-scheduler'); ?></button>
                    <span class="description"><?php esc_html_e('Permanently deletes all data from the plugin tables.', 'ai-post-scheduler'); ?></span>
                </p>
            </div>

            <div class="card" style="flex: 1; min-width: 300px;">
                <h3><?php esc_html_e('Data Management', 'ai-post-scheduler'); ?></h3>
                <p><?php esc_html_e('Export or import all plugin data for backup or migration purposes.', 'ai-post-scheduler'); ?></p>

                <h4><?php esc_html_e('Export Data', 'ai-post-scheduler'); ?></h4>
                <p>
                    <select id="aips-export-format" class="regular-text">
                        <option value="mysql"><?php esc_html_e('MySQL Dump (.sql)', 'ai-post-scheduler'); ?></option>
                        <option value="json"><?php esc_html_e('JSON (.json)', 'ai-post-scheduler'); ?></option>
                    </select>
                </p>
                <p>
                    <button type="button" class="button button-primary aips-export-data"><?php esc_html_e('Export Data', 'ai-post-scheduler'); ?></button>
                    <span class="description"><?php esc_html_e('Download all plugin data in the selected format.', 'ai-post-scheduler'); ?></span>
                </p>

                <hr>

                <h4><?php esc_html_e('Import Data', 'ai-post-scheduler'); ?></h4>
                <p>
                    <select id="aips-import-format" class="regular-text">
                        <option value="mysql"><?php esc_html_e('MySQL Dump (.sql)', 'ai-post-scheduler'); ?></option>
                        <option value="json"><?php esc_html_e('JSON (.json)', 'ai-post-scheduler'); ?></option>
                    </select>
                </p>
                <p>
                    <input type="file" id="aips-import-file" accept=".sql,.json">
                </p>
                <p>
                    <button type="button" class="button button-secondary aips-import-data"><?php esc_html_e('Import Data', 'ai-post-scheduler'); ?></button>
                    <span class="description"><?php esc_html_e('Import data from a previously exported file. This will overwrite existing data!', 'ai-post-scheduler'); ?></span>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.aips-toggle-log-details').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $('#' + target).toggle();
    });
});
</script>
