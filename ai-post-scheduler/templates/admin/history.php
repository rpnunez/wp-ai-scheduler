<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aips-wrap">
    <h1><?php esc_html_e('Generation History', 'ai-post-scheduler'); ?></h1>
    
    <div class="aips-history-stats">
        <div class="aips-stat-inline">
            <span class="aips-stat-label"><?php esc_html_e('Total:', 'ai-post-scheduler'); ?></span>
            <span class="aips-stat-value"><?php echo esc_html($stats['total']); ?></span>
        </div>
        <div class="aips-stat-inline aips-stat-success">
            <span class="aips-stat-label"><?php esc_html_e('Completed:', 'ai-post-scheduler'); ?></span>
            <span class="aips-stat-value"><?php echo esc_html($stats['completed']); ?></span>
        </div>
        <div class="aips-stat-inline aips-stat-error">
            <span class="aips-stat-label"><?php esc_html_e('Failed:', 'ai-post-scheduler'); ?></span>
            <span class="aips-stat-value"><?php echo esc_html($stats['failed']); ?></span>
        </div>
        <div class="aips-stat-inline">
            <span class="aips-stat-label"><?php esc_html_e('Success Rate:', 'ai-post-scheduler'); ?></span>
            <span class="aips-stat-value"><?php echo esc_html($stats['success_rate']); ?>%</span>
        </div>
    </div>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="aips-filter-status">
                <option value=""><?php esc_html_e('All Statuses', 'ai-post-scheduler'); ?></option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php esc_html_e('Completed', 'ai-post-scheduler'); ?></option>
                <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php esc_html_e('Failed', 'ai-post-scheduler'); ?></option>
                <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php esc_html_e('Processing', 'ai-post-scheduler'); ?></option>
            </select>
            <button class="button" id="aips-filter-btn"><?php esc_html_e('Filter', 'ai-post-scheduler'); ?></button>
        </div>
        <div class="alignright">
            <button class="button aips-clear-history" data-status=""><?php esc_html_e('Clear All History', 'ai-post-scheduler'); ?></button>
            <button class="button aips-clear-history" data-status="failed"><?php esc_html_e('Clear Failed Only', 'ai-post-scheduler'); ?></button>
        </div>
    </div>
    
    <?php if (!empty($history['items'])): ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="column-title"><?php esc_html_e('Title', 'ai-post-scheduler'); ?></th>
                <th class="column-template"><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
                <th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                <th class="column-date"><?php esc_html_e('Created', 'ai-post-scheduler'); ?></th>
                <th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history['items'] as $item): ?>
            <tr>
                <td class="column-title">
                    <?php if ($item->post_id): ?>
                    <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>">
                        <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
                    </a>
                    <?php else: ?>
                    <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
                    <?php endif; ?>
                    <?php if ($item->status === 'failed' && $item->error_message): ?>
                    <div class="aips-error-message"><?php echo esc_html($item->error_message); ?></div>
                    <?php endif; ?>
                </td>
                <td class="column-template">
                    <?php echo esc_html($item->template_name ?: '-'); ?>
                </td>
                <td class="column-status">
                    <span class="aips-status aips-status-<?php echo esc_attr($item->status); ?>">
                        <?php echo esc_html(ucfirst($item->status)); ?>
                    </span>
                </td>
                <td class="column-date">
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at))); ?>
                </td>
                <td class="column-actions">
                    <?php if ($item->post_id): ?>
                    <a href="<?php echo esc_url(get_permalink($item->post_id)); ?>" class="button button-small" target="_blank">
                        <?php esc_html_e('View', 'ai-post-scheduler'); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($item->status === 'failed' && $item->template_id): ?>
                    <button class="button button-small aips-retry-generation" data-id="<?php echo esc_attr($item->id); ?>">
                        <?php esc_html_e('Retry', 'ai-post-scheduler'); ?>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if ($history['pages'] > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php printf(
                    esc_html__('%d items', 'ai-post-scheduler'),
                    $history['total']
                ); ?>
            </span>
            <span class="pagination-links">
                <?php
                $base_url = admin_url('admin.php?page=aips-history');
                if ($status_filter) {
                    $base_url .= '&status=' . urlencode($status_filter);
                }
                
                if ($history['current_page'] > 1): ?>
                <a class="prev-page button" href="<?php echo esc_url($base_url . '&paged=' . ($history['current_page'] - 1)); ?>">
                    <span class="screen-reader-text"><?php esc_html_e('Previous page', 'ai-post-scheduler'); ?></span>
                    <span aria-hidden="true">&lsaquo;</span>
                </a>
                <?php endif; ?>
                
                <span class="paging-input">
                    <span class="tablenav-paging-text">
                        <?php echo esc_html($history['current_page']); ?>
                        <?php esc_html_e('of', 'ai-post-scheduler'); ?>
                        <span class="total-pages"><?php echo esc_html($history['pages']); ?></span>
                    </span>
                </span>
                
                <?php if ($history['current_page'] < $history['pages']): ?>
                <a class="next-page button" href="<?php echo esc_url($base_url . '&paged=' . ($history['current_page'] + 1)); ?>">
                    <span class="screen-reader-text"><?php esc_html_e('Next page', 'ai-post-scheduler'); ?></span>
                    <span aria-hidden="true">&rsaquo;</span>
                </a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="aips-empty-state">
        <span class="dashicons dashicons-backup"></span>
        <h3><?php esc_html_e('No History Yet', 'ai-post-scheduler'); ?></h3>
        <p><?php esc_html_e('Generated posts will appear here.', 'ai-post-scheduler'); ?></p>
    </div>
    <?php endif; ?>
</div>
