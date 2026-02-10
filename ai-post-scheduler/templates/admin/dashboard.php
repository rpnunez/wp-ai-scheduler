<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap">
    <?php if (!class_exists('Meow_MWAI_Core')): ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('AI Engine plugin is not installed or activated. This plugin requires Meow Apps AI Engine to function.', 'ai-post-scheduler'); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('Dashboard', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('Overview of your AI content generation activity and quick actions.', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="aips-page-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aips-templates')); ?>" class="aips-btn aips-btn-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Status Summary -->
        <div class="aips-status-summary">
            <div class="aips-summary-card highlight">
                <div class="dashicons dashicons-edit aips-summary-icon" aria-hidden="true"></div>
                <div class="aips-summary-content">
                    <span class="aips-summary-number"><?php echo esc_html($total_generated); ?></span>
                    <span class="aips-summary-label"><?php esc_html_e('Posts Generated', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
            
            <div class="aips-summary-card">
                <div class="dashicons dashicons-clock aips-summary-icon" aria-hidden="true"></div>
                <div class="aips-summary-content">
                    <span class="aips-summary-number"><?php echo esc_html($pending_scheduled); ?></span>
                    <span class="aips-summary-label"><?php esc_html_e('Active Schedules', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
            
            <div class="aips-summary-card">
                <div class="dashicons dashicons-media-document aips-summary-icon" aria-hidden="true"></div>
                <div class="aips-summary-content">
                    <span class="aips-summary-number"><?php echo esc_html($total_templates); ?></span>
                    <span class="aips-summary-label"><?php esc_html_e('Active Templates', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
            
            <?php if ($failed_count > 0): ?>
            <div class="aips-summary-card error">
                <div class="dashicons dashicons-warning aips-summary-icon" aria-hidden="true"></div>
                <div class="aips-summary-content">
                    <span class="aips-summary-number"><?php echo esc_html($failed_count); ?></span>
                    <span class="aips-summary-label"><?php esc_html_e('Failed Generations', 'ai-post-scheduler'); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Content Panels -->
        <div class="aips-grid aips-grid-cols-2">
            <!-- Upcoming Scheduled Posts -->
            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('Upcoming Scheduled Posts', 'ai-post-scheduler'); ?></h2>
                </div>
                <div class="aips-panel-body <?php echo empty($upcoming) ? '' : 'no-padding'; ?>">
                    <?php if (!empty($upcoming)): ?>
                    <table class="aips-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
                                <th><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming as $item): ?>
                            <tr>
                                <td>
                                    <div class="cell-primary"><?php echo esc_html($item->template_name ?: __('Unknown Template', 'ai-post-scheduler')); ?></div>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->next_run))); ?></td>
                                <td>
                                    <span class="aips-badge aips-badge-info"><?php echo esc_html(ucfirst($item->frequency)); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="aips-empty-state">
                        <div class="dashicons dashicons-calendar-alt aips-empty-state-icon" aria-hidden="true"></div>
                        <h3 class="aips-empty-state-title"><?php esc_html_e('No Schedules Yet', 'ai-post-scheduler'); ?></h3>
                        <p class="aips-empty-state-description"><?php esc_html_e('Get started by creating your first schedule to automate content generation.', 'ai-post-scheduler'); ?></p>
                        <div class="aips-empty-state-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=aips-schedule')); ?>" class="aips-btn aips-btn-primary">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php esc_html_e('Create Schedule', 'ai-post-scheduler'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="aips-content-panel">
                <div class="aips-panel-header">
                    <h2 class="aips-panel-title"><?php esc_html_e('Recent Activity', 'ai-post-scheduler'); ?></h2>
                    <?php if (!empty($recent_posts)): ?>
                    <a href="<?php echo esc_url(add_query_arg('tab', 'history', admin_url('admin.php?page=aips-templates'))); ?>" class="aips-btn aips-btn-ghost aips-btn-sm">
                        <?php esc_html_e('View All', 'ai-post-scheduler'); ?> &rarr;
                    </a>
                    <?php endif; ?>
                </div>
                <div class="aips-panel-body <?php echo empty($recent_posts) ? '' : 'no-padding'; ?>">
                    <?php if (!empty($recent_posts)): ?>
                    <table class="aips-table">
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
                                    <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" class="cell-primary">
                                        <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
                                    </a>
                                    <?php else: ?>
                                    <div class="cell-primary"><?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = 'neutral';
                                    if ($item->status === 'completed') {
                                        $status_class = 'success';
                                    } elseif ($item->status === 'failed') {
                                        $status_class = 'error';
                                    } elseif ($item->status === 'pending') {
                                        $status_class = 'warning';
                                    }
                                    ?>
                                    <span class="aips-badge aips-badge-<?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html(ucfirst($item->status)); ?>
                                    </span>
                                </td>
                                <td class="cell-meta"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item->created_at))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="aips-empty-state">
                        <div class="dashicons dashicons-admin-post aips-empty-state-icon" aria-hidden="true"></div>
                        <h3 class="aips-empty-state-title"><?php esc_html_e('No Posts Yet', 'ai-post-scheduler'); ?></h3>
                        <p class="aips-empty-state-description"><?php esc_html_e('Start generating content by creating templates and schedules.', 'ai-post-scheduler'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Card -->
        <div class="aips-content-panel">
            <div class="aips-panel-header">
                <h2 class="aips-panel-title"><?php esc_html_e('Quick Actions', 'ai-post-scheduler'); ?></h2>
            </div>
            <div class="aips-panel-body">
                <div class="aips-quick-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aips-templates')); ?>" class="aips-btn aips-btn-secondary">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e('Manage Templates', 'ai-post-scheduler'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aips-schedule')); ?>" class="aips-btn aips-btn-secondary">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e('Manage Schedules', 'ai-post-scheduler'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aips-generated-posts')); ?>" class="aips-btn aips-btn-secondary">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('View Generated Posts', 'ai-post-scheduler'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aips-authors')); ?>" class="aips-btn aips-btn-secondary">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php esc_html_e('Manage Authors', 'ai-post-scheduler'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings')); ?>" class="aips-btn aips-btn-secondary">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e('Plugin Settings', 'ai-post-scheduler'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
