<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap">
    <h1><?php esc_html_e('AI Post Scheduler Settings', 'ai-post-scheduler'); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('aips_settings');
        do_settings_sections('aips-settings');
        submit_button();
        ?>
    </form>
    
    <hr>
    
    <div class="aips-card">
        <h2><?php esc_html_e('Cron Status', 'ai-post-scheduler'); ?></h2>
        <?php
        $next_scheduled = wp_next_scheduled('aips_generate_scheduled_posts');
        if ($next_scheduled) {
            echo '<p class="aips-cron-active"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ';
            printf(
                esc_html__('Next scheduled check: %s', 'ai-post-scheduler'),
                esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled))
            );
            echo '</p>';
        } else {
            echo '<p class="aips-cron-inactive"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
            esc_html_e('Cron job is not scheduled. Try deactivating and reactivating the plugin.', 'ai-post-scheduler');
            echo '</p>';
        }
        ?>
    </div>
    
    <div class="aips-card">
        <h2><?php esc_html_e('AI Engine Status', 'ai-post-scheduler'); ?></h2>
        <?php if (class_exists('Meow_MWAI_Core')): ?>
        <p class="aips-status-ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e('AI Engine is installed and active.', 'ai-post-scheduler'); ?></p>
        <div class="aips-test-connection-wrapper">
            <button type="button" id="aips-test-connection" class="button button-secondary">
                <?php esc_html_e('Test Connection', 'ai-post-scheduler'); ?>
            </button>
            <span class="spinner"></span>
            <span id="aips-connection-result" class="aips-connection-result"></span>
        </div>
        <?php else: ?>
        <p class="aips-status-error"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e('AI Engine is not installed or not activated.', 'ai-post-scheduler'); ?></p>
        <p><?php esc_html_e('Please install and activate the AI Engine plugin by Meow Apps for this plugin to work.', 'ai-post-scheduler'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="aips-card">
        <h2><?php esc_html_e('Template Variables', 'ai-post-scheduler'); ?></h2>
        <p><?php esc_html_e('You can use these variables in your prompt templates:', 'ai-post-scheduler'); ?></p>

        <div class="aips-search-box" style="margin-bottom: 10px; text-align: right;">
            <label class="screen-reader-text" for="aips-variable-search"><?php esc_html_e('Search Variables:', 'ai-post-scheduler'); ?></label>
            <input type="search" id="aips-variable-search" class="regular-text" placeholder="<?php esc_attr_e('Search variables...', 'ai-post-scheduler'); ?>">
            <button type="button" id="aips-variable-search-clear" class="button" style="display: none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
        </div>

        <table id="aips-variables-table" class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Variable', 'ai-post-scheduler'); ?></th>
                    <th><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
                    <th><?php esc_html_e('Example', 'ai-post-scheduler'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="aips-variable-code-cell">
                            <code>{{date}}</code>
                            <button type="button" class="aips-copy-btn" data-clipboard-text="{{date}}" aria-label="<?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>">
                                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                            </button>
                        </div>
                    </td>
                    <td><?php esc_html_e('Current date', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(date('F j, Y')); ?></td>
                </tr>
                <tr>
                    <td>
                        <div class="aips-variable-code-cell">
                            <code>{{year}}</code>
                            <button type="button" class="aips-copy-btn" data-clipboard-text="{{year}}" aria-label="<?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>">
                                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                            </button>
                        </div>
                    </td>
                    <td><?php esc_html_e('Current year', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(date('Y')); ?></td>
                </tr>
                <tr>
                    <td>
                        <div class="aips-variable-code-cell">
                            <code>{{month}}</code>
                            <button type="button" class="aips-copy-btn" data-clipboard-text="{{month}}" aria-label="<?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>">
                                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                            </button>
                        </div>
                    </td>
                    <td><?php esc_html_e('Current month', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(date('F')); ?></td>
                </tr>
                <tr>
                    <td>
                        <div class="aips-variable-code-cell">
                            <code>{{day}}</code>
                            <button type="button" class="aips-copy-btn" data-clipboard-text="{{day}}" aria-label="<?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>">
                                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                            </button>
                        </div>
                    </td>
                    <td><?php esc_html_e('Current day of week', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(date('l')); ?></td>
                </tr>
                <tr>
                    <td>
                        <div class="aips-variable-code-cell">
                            <code>{{time}}</code>
                            <button type="button" class="aips-copy-btn" data-clipboard-text="{{time}}" aria-label="<?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>">
                                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                            </button>
                        </div>
                    </td>
                    <td><?php esc_html_e('Current time', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(current_time('H:i')); ?></td>
                </tr>
                <tr>
                    <td>
                        <div class="aips-variable-code-cell">
                            <code>{{site_name}}</code>
                            <button type="button" class="aips-copy-btn" data-clipboard-text="{{site_name}}" aria-label="<?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>">
                                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                            </button>
                        </div>
                    </td>
                    <td><?php esc_html_e('Site name', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(get_bloginfo('name')); ?></td>
                </tr>
                <tr>
                    <td>
                        <div class="aips-variable-code-cell">
                            <code>{{site_description}}</code>
                            <button type="button" class="aips-copy-btn" data-clipboard-text="{{site_description}}" aria-label="<?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>">
                                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                            </button>
                        </div>
                    </td>
                    <td><?php esc_html_e('Site description', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(get_bloginfo('description')); ?></td>
                </tr>
                <tr>
                    <td>
                        <div class="aips-variable-code-cell">
                            <code>{{topic}}</code>
                            <button type="button" class="aips-copy-btn" data-clipboard-text="{{topic}}" aria-label="<?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>">
                                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                            </button>
                        </div>
                    </td>
                    <td><?php esc_html_e('Topic (from Scheduler/Planner)', 'ai-post-scheduler'); ?></td>
                    <td><?php esc_html_e('Your Topic', 'ai-post-scheduler'); ?></td>
                </tr>
                <tr>
                    <td>
                        <div class="aips-variable-code-cell">
                            <code>{{random_number}}</code>
                            <button type="button" class="aips-copy-btn" data-clipboard-text="{{random_number}}" aria-label="<?php esc_attr_e('Copy variable', 'ai-post-scheduler'); ?>">
                                <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                            </button>
                        </div>
                    </td>
                    <td><?php esc_html_e('Random number (1-1000)', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(rand(1, 1000)); ?></td>
                </tr>
            </tbody>
        </table>

        <div id="aips-variable-search-no-results" class="aips-empty-state" style="display: none;">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            <h3><?php esc_html_e('No Variables Found', 'ai-post-scheduler'); ?></h3>
            <p><?php esc_html_e('No template variables match your search criteria.', 'ai-post-scheduler'); ?></p>
            <button type="button" class="button button-primary aips-clear-variable-search-btn">
                <?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
            </button>
        </div>
    </div>
</div>
