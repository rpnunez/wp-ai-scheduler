<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap">
    <h1><?php esc_html_e('AI Post Scheduler', 'ai-post-scheduler'); ?></h1>
    
    <?php if (!class_exists('Meow_MWAI_Core')): ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('AI Engine plugin is not installed or activated. This plugin requires Meow Apps AI Engine to function.', 'ai-post-scheduler'); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="aips-dashboard">
        <div class="aips-stats-grid">
            <div class="aips-stat-card">
                <div class="aips-stat-icon dashicons dashicons-edit"></div>
                <div class="aips-stat-content">
                    <span class="aips-stat-number"><?php echo esc_html($total_generated); ?></span>
                    <span class="aips-stat-label"><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
            
            <div class="aips-stat-card">
                <div class="aips-stat-icon dashicons dashicons-clock"></div>
                <div class="aips-stat-content">
                    <span class="aips-stat-number"><?php echo esc_html($pending_scheduled); ?></span>
                    <span class="aips-stat-label"><?php esc_html_e('Active Schedules', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
            
            <div class="aips-stat-card">
                <div class="aips-stat-icon dashicons dashicons-media-document"></div>
                <div class="aips-stat-content">
                    <span class="aips-stat-number"><?php echo esc_html($total_templates); ?></span>
                    <span class="aips-stat-label"><?php esc_html_e('Active Templates', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
            
            <div class="aips-stat-card aips-stat-warning">
                <div class="aips-stat-icon dashicons dashicons-warning"></div>
                <div class="aips-stat-content">
                    <span class="aips-stat-number"><?php echo esc_html($failed_count); ?></span>
                    <span class="aips-stat-label"><?php esc_html_e('Failed Generations', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="aips-dashboard-columns">
            <div class="aips-dashboard-column">
                <div class="aips-card">
                    <h2><?php esc_html_e('Upcoming Scheduled Posts', 'ai-post-scheduler'); ?></h2>
                    <?php if (!empty($upcoming)): ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming as $item): ?>
                            <tr>
                                <td><?php echo esc_html($item->template_name ?: __('Unknown Template', 'ai-post-scheduler')); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->next_run))); ?></td>
                                <td>
                                    <?php
                                    if (!empty($item->last_run)) {
                                        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->last_run)));
                                    } else {
                                        esc_html_e('Never', 'ai-post-scheduler');
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html(ucfirst($item->frequency)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="aips-no-data"><?php esc_html_e('No scheduled posts yet.', 'ai-post-scheduler'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aips-schedule')); ?>" class="button button-primary">
                        <?php esc_html_e('Create Schedule', 'ai-post-scheduler'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="aips-dashboard-column">
                <div class="aips-card">
                    <h2><?php esc_html_e('Recent Activity', 'ai-post-scheduler'); ?></h2>
                    <?php if (!empty($recent_posts)): ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Title', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Date', 'ai-post-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_posts as $item): ?>
                            <tr>
                                <td>
                                    <?php if ($item->post_id): ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>">
                                        <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
                                    </a>
                                    <?php else: ?>
                                    <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="aips-status aips-status-<?php echo esc_attr($item->status); ?>">
                                        <?php echo esc_html(ucfirst($item->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item->created_at))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=aips-history')); ?>">
                            <?php esc_html_e('View All History', 'ai-post-scheduler'); ?> &rarr;
                        </a>
                    </p>
                    <?php else: ?>
                    <p class="aips-no-data"><?php esc_html_e('No posts generated yet.', 'ai-post-scheduler'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="aips-card aips-quick-actions">
            <h2><?php esc_html_e('Quick Actions', 'ai-post-scheduler'); ?></h2>
            <div class="aips-button-group">
                <a href="<?php echo esc_url(admin_url('admin.php?page=aips-templates')); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=aips-schedule')); ?>" class="button button-secondary button-large">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e('Add Schedule', 'ai-post-scheduler'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings')); ?>" class="button button-secondary button-large">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('Settings', 'ai-post-scheduler'); ?>
                </a>
            </div>
        </div>
    </div>
</div>
