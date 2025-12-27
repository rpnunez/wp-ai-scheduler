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
            echo '<p class="aips-cron-active"><span class="dashicons dashicons-yes-alt"></span> ';
            printf(
                esc_html__('Next scheduled check: %s', 'ai-post-scheduler'),
                esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled))
            );
            echo '</p>';
        } else {
            echo '<p class="aips-cron-inactive"><span class="dashicons dashicons-warning"></span> ';
            esc_html_e('Cron job is not scheduled. Try deactivating and reactivating the plugin.', 'ai-post-scheduler');
            echo '</p>';
        }
        ?>
    </div>
    
    <div class="aips-card">
        <h2><?php esc_html_e('AI Engine Status', 'ai-post-scheduler'); ?></h2>
        <?php if (class_exists('Meow_MWAI_Core')): ?>
        <p class="aips-status-ok"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('AI Engine is installed and active.', 'ai-post-scheduler'); ?></p>
        <?php else: ?>
        <p class="aips-status-error"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('AI Engine is not installed or not activated.', 'ai-post-scheduler'); ?></p>
        <p><?php esc_html_e('Please install and activate the AI Engine plugin by Meow Apps for this plugin to work.', 'ai-post-scheduler'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="aips-card">
        <h2><?php esc_html_e('Template Variables', 'ai-post-scheduler'); ?></h2>
        <p><?php esc_html_e('You can use these variables in your prompt templates:', 'ai-post-scheduler'); ?></p>
        <table class="widefat striped">
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
                        <code>{{date}}</code>
                        <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{date}}" aria-label="<?php esc_attr_e('Copy {{date}}', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </td>
                    <td><?php esc_html_e('Current date', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(date('F j, Y')); ?></td>
                </tr>
                <tr>
                    <td>
                        <code>{{year}}</code>
                        <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{year}}" aria-label="<?php esc_attr_e('Copy {{year}}', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </td>
                    <td><?php esc_html_e('Current year', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(date('Y')); ?></td>
                </tr>
                <tr>
                    <td>
                        <code>{{month}}</code>
                        <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{month}}" aria-label="<?php esc_attr_e('Copy {{month}}', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </td>
                    <td><?php esc_html_e('Current month', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(date('F')); ?></td>
                </tr>
                <tr>
                    <td>
                        <code>{{day}}</code>
                        <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{day}}" aria-label="<?php esc_attr_e('Copy {{day}}', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </td>
                    <td><?php esc_html_e('Current day of week', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(date('l')); ?></td>
                </tr>
                <tr>
                    <td>
                        <code>{{time}}</code>
                        <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{time}}" aria-label="<?php esc_attr_e('Copy {{time}}', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </td>
                    <td><?php esc_html_e('Current time', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(current_time('H:i')); ?></td>
                </tr>
                <tr>
                    <td>
                        <code>{{site_name}}</code>
                        <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{site_name}}" aria-label="<?php esc_attr_e('Copy {{site_name}}', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </td>
                    <td><?php esc_html_e('Site name', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(get_bloginfo('name')); ?></td>
                </tr>
                <tr>
                    <td>
                        <code>{{site_description}}</code>
                        <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{site_description}}" aria-label="<?php esc_attr_e('Copy {{site_description}}', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </td>
                    <td><?php esc_html_e('Site description', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(get_bloginfo('description')); ?></td>
                </tr>
                <tr>
                    <td>
                        <code>{{random_number}}</code>
                        <button type="button" class="button button-small aips-copy-btn" data-clipboard-text="{{random_number}}" aria-label="<?php esc_attr_e('Copy {{random_number}}', 'ai-post-scheduler'); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </td>
                    <td><?php esc_html_e('Random number (1-1000)', 'ai-post-scheduler'); ?></td>
                    <td><?php echo esc_html(rand(1, 1000)); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
