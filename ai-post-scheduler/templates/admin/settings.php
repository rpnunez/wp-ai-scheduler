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
                    <h1 class="aips-page-title"><?php esc_html_e('Settings', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Configure plugin settings, check system status, and manage AI Engine connection.', 'ai-post-scheduler'); ?></p>
                </div>
            </div>
        </div>

        <!-- Plugin Settings Form -->
        <div class="aips-content-panel">
            <div class="aips-panel-header">
                <h2><?php esc_html_e('Plugin Configuration', 'ai-post-scheduler'); ?></h2>
            </div>
            <div class="aips-panel-body">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('aips_settings');
                    do_settings_sections('aips-settings');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>

        <!-- Cron Status -->
        <div class="aips-content-panel" style="margin-top: 20px;">
            <div class="aips-panel-header">
                <h2>
                    <span class="dashicons dashicons-clock" style="margin-right: 5px;"></span>
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
            </div>
        </div>

        <!-- AI Engine Status -->
        <div class="aips-content-panel" style="margin-top: 20px;">
            <div class="aips-panel-header">
                <h2>
                    <span class="dashicons dashicons-admin-plugins" style="margin-right: 5px;"></span>
                    <?php esc_html_e('AI Engine Status', 'ai-post-scheduler'); ?>
                </h2>
            </div>
            <div class="aips-panel-body">
                <?php if (class_exists('Meow_MWAI_Core')): ?>
                    <p class="aips-status-message aips-status-success">
                        <span class="aips-badge aips-badge-success">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Connected', 'ai-post-scheduler'); ?>
                        </span>
                        <?php esc_html_e('AI Engine is installed and active.', 'ai-post-scheduler'); ?>
                    </p>
                    <div class="aips-test-connection-wrapper" style="margin-top: 15px;">
                        <button type="button" id="aips-test-connection" class="aips-btn aips-btn-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Test Connection', 'ai-post-scheduler'); ?>
                        </button>
                        <span class="spinner" style="float: none;"></span>
                        <span id="aips-connection-result" class="aips-connection-result"></span>
                    </div>
                <?php else: ?>
                    <p class="aips-status-message aips-status-error">
                        <span class="aips-badge aips-badge-error">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e('Not Found', 'ai-post-scheduler'); ?>
                        </span>
                        <?php esc_html_e('AI Engine is not installed or not activated. Please install and activate the AI Engine plugin.', 'ai-post-scheduler'); ?>
                    </p>
                    <p style="margin-top: 10px;">
                        <a href="https://wordpress.org/plugins/ai-engine/" target="_blank" rel="noopener" class="aips-btn aips-btn-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Download AI Engine', 'ai-post-scheduler'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
