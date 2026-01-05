<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('System Status', 'ai-post-scheduler'); ?></h1>
    <hr class="wp-header-end">

    <div class="aips-status-page">
        <?php foreach ($system_info as $section => $checks) : ?>
            <?php if (empty($checks)) continue; ?>

            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="title"><?php echo esc_html(ucfirst($section)); ?></h2>
                <?php if ($section === 'logs') : ?>
                    <button type="button" class="button button-secondary aips-clear-logs"><?php esc_html_e('Clear Logs', 'ai-post-scheduler'); ?></button>
                <?php endif; ?>
            </div>

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
        <div class="card">
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
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.aips-toggle-log-details').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $('#' + target).toggle();
    });

    $('.aips-clear-logs').on('click', function(e) {
        e.preventDefault();
        if (!confirm('<?php echo esc_js(__('Are you sure you want to clear the logs?', 'ai-post-scheduler')); ?>')) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aips_clear_logs',
                nonce: '<?php echo wp_create_nonce("aips_ajax_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message);
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('An error occurred.', 'ai-post-scheduler')); ?>');
                $button.prop('disabled', false);
            }
        });
    });
});
</script>
