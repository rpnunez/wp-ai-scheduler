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
                <div class="aips-btn-group" style="gap: 8px;">
                    <a class="aips-btn aips-btn-primary" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('onboarding')); ?>">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                        <?php esc_html_e('Run Onboarding Wizard', 'ai-post-scheduler'); ?>
                    </a>
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

                    <hr style="margin: 20px 0;">

                    <!-- Import -->
                    <h3 style="margin-top: 0;"><?php esc_html_e('Import', 'ai-post-scheduler'); ?></h3>
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
                        <input type="file" id="aips-import-file" style="display: inline-block; vertical-align: middle;">
                        <button type="button" class="aips-btn aips-btn-secondary aips-import-data">
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e('Import Data', 'ai-post-scheduler'); ?>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Notifications Maintenance -->
            <div class="aips-content-panel" style="margin-bottom: 20px;">
                <div class="aips-panel-header">
                    <h2>
                        <span class="dashicons dashicons-bell" style="margin-right: 5px;"></span>
                        <?php esc_html_e('Notifications Maintenance', 'ai-post-scheduler'); ?>
                    </h2>
                </div>
                <div class="aips-panel-body">
                    <p><?php esc_html_e('Run a one-time hygiene command to clean legacy notification options, unschedule deprecated cron hooks, and normalize notification channel preferences.', 'ai-post-scheduler'); ?></p>

                    <div class="aips-btn-group" style="margin-bottom: 8px;">
                        <button type="button" class="aips-btn aips-btn-secondary aips-notifications-hygiene">
                            <span class="dashicons dashicons-broom"></span>
                            <?php esc_html_e('Run Notifications Hygiene', 'ai-post-scheduler'); ?>
                        </button>
                    </div>

                    <div class="aips-notifications-hygiene-result" style="display:none;"></div>
                </div>
            </div>

            <!-- Scheduler Management -->
            <div class="aips-content-panel" style="margin-bottom: 20px;">
                <div class="aips-panel-header">
                    <h2>
                        <span class="dashicons dashicons-clock" style="margin-right: 5px;"></span>
                        <?php esc_html_e('Scheduler Management', 'ai-post-scheduler'); ?>
                    </h2>
                </div>
                <div class="aips-panel-body">
                    <p><?php esc_html_e('Use this tool to reset the plugin\'s WP-Cron events. If duplicate or stacked cron events have accumulated (which can trigger excessive AI calls), flush and re-register all events with one click.', 'ai-post-scheduler'); ?></p>

                    <div class="aips-btn-group" style="margin-bottom: 8px;">
                        <button type="button" class="aips-btn aips-btn-secondary aips-flush-cron">
                            <span class="dashicons dashicons-controls-repeat"></span>
                            <?php esc_html_e('Flush WP-Cron Events', 'ai-post-scheduler'); ?>
                        </button>
                    </div>

                    <div class="aips-flush-cron-result" style="display:none;"></div>
                </div>
            </div>

            <!-- Operator Runbook -->
            <div class="aips-content-panel" style="margin-bottom: 20px;">
                <div class="aips-panel-header">
                    <h2>
                        <span class="dashicons dashicons-media-document" style="margin-right: 5px;"></span>
                        <?php esc_html_e('Operator Runbook: Queue &amp; Generation Incidents', 'ai-post-scheduler'); ?>
                    </h2>
                </div>
                <div class="aips-panel-body">
                    <p><?php esc_html_e('Use the following procedures to investigate and recover from common queue and generation incidents. Follow each section in order and stop when the issue is resolved.', 'ai-post-scheduler'); ?></p>

                    <!-- RB-1 -->
                    <h3 style="margin-top: 16px;">
                        <span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
                        <?php esc_html_e('RB-1 — Stuck or Missing Generations', 'ai-post-scheduler'); ?>
                    </h3>
                    <ol>
                        <li><?php esc_html_e('Check the "Queue Health" section above for stuck-job count and age.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Check "Scheduler Health" → WP-Cron events. If any hook shows 0 or duplicate instances, click "Flush WP-Cron Events" above.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Open History, filter by status = pending or partial, and note the correlation IDs.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('In History detail view, look for the last log entry to identify where the run stopped (ai_request, error, partial completion).', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('If AI Engine is unreachable, verify the API key in AI Engine settings and confirm the API quota has not been exhausted.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Use "Partial Generation Recovery" in the History detail view to resume any partially completed post.', 'ai-post-scheduler'); ?></li>
                    </ol>

                    <!-- RB-2 -->
                    <h3 style="margin-top: 16px;">
                        <span class="dashicons dashicons-warning" style="vertical-align: middle;"></span>
                        <?php esc_html_e('RB-2 — High Failure Rate / Retry Saturation', 'ai-post-scheduler'); ?>
                    </h3>
                    <ol>
                        <li><?php esc_html_e('Check "Queue Health" → Retry Saturation percentage. A value above 50 % is a strong signal of an upstream API problem.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Check "Generation Metrics" → Recent Outcomes for repeated error messages. Common causes: rate limit exceeded, model unavailable, invalid prompt.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Review AI Engine logs (Settings → AI Engine → Logs) for raw API error responses.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('If a specific template is failing, open that template and test with a simplified prompt to rule out prompt-level errors.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('If the issue is transient API congestion, temporarily pause active schedules and resume after the outage window.', 'ai-post-scheduler'); ?></li>
                    </ol>

                    <!-- RB-3 -->
                    <h3 style="margin-top: 16px;">
                        <span class="dashicons dashicons-block-default" style="vertical-align: middle;"></span>
                        <?php esc_html_e('RB-3 — Circuit Breaker is Open', 'ai-post-scheduler'); ?>
                    </h3>
                    <ol>
                        <li><?php esc_html_e('The circuit breaker opens after a configured number of consecutive AI failures to prevent runaway retry storms.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Check the underlying cause: review "Generation Metrics" → Recent Outcomes and AI Engine logs before resetting.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Once the root cause is resolved (API key valid, quota restored, model available), click the "Reset Circuit Breaker" button below.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('After resetting, monitor "Queue Health" for a few minutes to confirm failure rate returns to normal before enabling more schedules.', 'ai-post-scheduler'); ?></li>
                    </ol>
                    <?php if ( class_exists( 'AIPS_AI_Service' ) ) : ?>
                    <div class="notice notice-warning inline" style="margin-top: 8px;">
                        <p>
                            <?php esc_html_e('Circuit breaker reset is not available from this screen yet. Resolve the underlying AI service issue first, then use the plugin’s implemented recovery/reset workflow when available.', 'ai-post-scheduler'); ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- RB-4 -->
                    <h3 style="margin-top: 16px;">
                        <span class="dashicons dashicons-database" style="vertical-align: middle;"></span>
                        <?php esc_html_e('RB-4 — Backlog Not Draining', 'ai-post-scheduler'); ?>
                    </h3>
                    <ol>
                        <li><?php esc_html_e('Check "Queue Health" → Queue Backlog. A growing pending count indicates jobs are being created faster than they are consumed.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Verify WP-Cron is running: many hosts disable WP-Cron for busy sites. Consider adding a server-side cron to trigger wp-cron.php directly.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Check active schedule frequency. If you have many high-frequency schedules, the queue may be draining slower than it fills — consider reducing frequency or post quantity.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Use "Scheduler Health" → Active Schedules to audit and disable schedules that are no longer needed.', 'ai-post-scheduler'); ?></li>
                    </ol>

                    <!-- RB-5 -->
                    <h3 style="margin-top: 16px;">
                        <span class="dashicons dashicons-image-filter" style="vertical-align: middle;"></span>
                        <?php esc_html_e('RB-5 — High Image Generation Failure Rate', 'ai-post-scheduler'); ?>
                    </h3>
                    <ol>
                        <li><?php esc_html_e('Check "Generation Metrics" → Image Generation Failure Rate. Values above 30 % warrant investigation.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Verify the image generation model is enabled in AI Engine settings and the API key has image-generation permissions.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Check if image prompts in templates contain content that might be rejected by the moderation layer.', 'ai-post-scheduler'); ?></li>
                        <li><?php esc_html_e('Use "Partial Generation Recovery" to regenerate featured images for posts where generation failed.', 'ai-post-scheduler'); ?></li>
                    </ol>

                    <p style="margin-top: 16px; color: #666; font-size: 13px;">
                        <?php
                        echo wp_kses(
                            sprintf(
                                /* translators: %s: link to docs/RUNBOOK.md on GitHub */
                                __( 'Full runbook with escalation procedures: <a href="%s" target="_blank" rel="noopener noreferrer">docs/RUNBOOK.md</a>', 'ai-post-scheduler' ),
                                esc_url( 'https://github.com/rpnunez/wp-ai-scheduler/blob/main/docs/RUNBOOK.md' )
                            ),
                            array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
                        );
                        ?>
                    </p>
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
