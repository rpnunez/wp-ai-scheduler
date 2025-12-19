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
