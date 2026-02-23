<?php
if (!defined('ABSPATH')) {
    exit;
}

// Generate plain-text system report
$report_text = "### AI Post Scheduler System Report ###\n\n";
if (!empty($system_info)) {
    foreach ($system_info as $section => $checks) {
        if (empty($checks)) continue;
        $report_text .= "### " . ucfirst($section) . " ###\n";
        foreach ($checks as $key => $check) {
            $value = is_array($check['value']) ? implode(', ', $check['value']) : $check['value'];
            $status = isset($check['status']) ? strtoupper($check['status']) : 'INFO';
            $report_text .= $check['label'] . ": " . $value . " (" . $status . ")\n";
            if (!empty($check['details'])) {
                if (is_array($check['details'])) {
                    $report_text .= "Details:\n" . implode("\n", $check['details']) . "\n";
                } else {
                    $report_text .= "Details: " . $check['details'] . "\n";
                }
            }
        }
        $report_text .= "\n";
    }
}
$report_text .= "Generated at: " . current_time('mysql') . "\n";
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
                <div class="aips-page-actions">
                    <button type="button" class="aips-btn aips-btn-secondary aips-copy-btn" data-clipboard-target="#aips-system-report-raw">
                        <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy System Report', 'ai-post-scheduler'); ?>
                    </button>
                    <textarea id="aips-system-report-raw" style="display:none;"><?php echo esc_textarea($report_text); ?></textarea>
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
