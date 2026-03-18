<?php
$content = file_get_contents('ai-post-scheduler/templates/admin/settings.php');

$search = <<<EOT
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
EOT;

$replace = <<<EOT
        <!-- Plugin Settings Form -->
        <div class="aips-content-panel aips-settings-panel">
            <div class="aips-topics-tabs aips-page-tabs" id="aips-settings-tabs" style="border-bottom: 1px solid #c3c4c7; padding: 0 15px;">
                <button type="button" class="aips-tab-link active" data-tab="general">
                    <?php esc_html_e('General', 'ai-post-scheduler'); ?>
                </button>
                <button type="button" class="aips-tab-link" data-tab="ai">
                    <?php esc_html_e('AI & APIs', 'ai-post-scheduler'); ?>
                </button>
                <button type="button" class="aips-tab-link" data-tab="resilience">
                    <?php esc_html_e('Resilience & Rate Limiting', 'ai-post-scheduler'); ?>
                </button>
                <button type="button" class="aips-tab-link" data-tab="notifications">
                    <?php esc_html_e('Notifications', 'ai-post-scheduler'); ?>
                </button>
                <button type="button" class="aips-tab-link" data-tab="advanced">
                    <?php esc_html_e('Advanced', 'ai-post-scheduler'); ?>
                </button>
            </div>

            <div class="aips-panel-body">
                <form method="post" action="options.php" id="aips-settings-form">
                    <?php settings_fields('aips_settings'); ?>

                    <div id="tab-general" class="aips-tab-content aips-settings-tab-content active" style="display: block;">
                        <?php do_settings_sections('aips-settings'); ?>
                    </div>

                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
EOT;

$content = str_replace($search, $replace, $content);
file_put_contents('ai-post-scheduler/templates/admin/settings.php', $content);
echo "Settings template patched.\n";
