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
                                        <textarea class="large-text code" rows="10" readonly id="textarea-<?php echo esc_attr($key); ?>"><?php echo esc_textarea(implode("\n", $check['details'])); ?></textarea>
                                        <button type="button" class="button button-small aips-copy-log" data-target="textarea-<?php echo esc_attr($key); ?>" style="margin-top: 5px;">
                                            <span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span> <?php esc_html_e('Copy to Clipboard', 'ai-post-scheduler'); ?>
                                        </button>
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

    $('.aips-copy-log').on('click', function(e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        var copyText = document.getElementById(targetId);

        copyText.select();
        copyText.setSelectionRange(0, 99999); /* For mobile devices */

        try {
            document.execCommand("copy");
            var $btn = $(this);
            var originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes" style="margin-top: 3px;"></span> ' + '<?php esc_js(__('Copied!', 'ai-post-scheduler')); ?>');
            setTimeout(function() {
                $btn.html(originalText);
            }, 2000);
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }
    });
});
</script>
