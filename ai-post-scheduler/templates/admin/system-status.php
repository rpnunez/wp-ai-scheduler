<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap aips-redesign">
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
        </div>
    </div>
</div>
