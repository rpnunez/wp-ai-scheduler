<?php
if (!defined('ABSPATH')) {
    exit;
}

// Allow this template to be included either via AIPS_History::render_page()
// (where $history is already an array) or directly with an AIPS_History
// instance (e.g. $history or $History).
if (isset($history) && $history instanceof AIPS_History) {
    $history_handler = $history;
} elseif (isset($History) && $History instanceof AIPS_History) {
    $history_handler = $History;
}

// Also handle the case where $stats might be an AIPS_History object
if (isset($stats) && $stats instanceof AIPS_History) {
    if (!isset($history_handler)) {
        $history_handler = $stats;
    }
    $stats = null; // Will be set below
}

// Ensure default variables are set
$current_page  = isset($current_page) ? absint($current_page) : (isset($_GET['paged']) ? absint($_GET['paged']) : 1);
$status_filter = isset($status_filter) ? $status_filter : (isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '');
$search_query  = isset($search_query) ? $search_query : (isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '');

if (isset($history_handler)) {
    $history = $history_handler->get_history(array(
        'page'   => $current_page,
        'status' => $status_filter,
        'search' => $search_query,
    ));

    if (!isset($stats)) {
        $stats = $history_handler->get_stats();
    }
}

$history_base_page = isset($history_base_page) ? $history_base_page : 'aips-history';
$history_base_args = isset($history_base_args) && is_array($history_base_args) ? $history_base_args : array();
$history_base_url = add_query_arg($history_base_args, admin_url('admin.php?page=' . $history_base_page));

// Determine if this is being displayed in a tab or as a standalone page
$is_history_tab = isset($is_history_tab) ? $is_history_tab : false;
?>
<?php if (!$is_history_tab): ?>
<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('History', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('View generation history, activity logs, and track all AI post generation events with detailed error tracking.', 'ai-post-scheduler'); ?></p>
                </div>
                <div class="aips-page-actions">
                    <button class="aips-btn aips-btn-secondary" id="aips-export-history-btn">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export CSV', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Summary -->
        <div class="aips-stats-grid aips-grid-4">
            <div class="aips-stat-card">
                <div class="aips-stat-icon">
                    <span class="dashicons dashicons-backup"></span>
                </div>
                <div class="aips-stat-content">
                    <div class="aips-stat-value" id="aips-stat-total"><?php echo esc_html(number_format($stats['total'])); ?></div>
                    <div class="aips-stat-label"><?php esc_html_e('Total Generated', 'ai-post-scheduler'); ?></div>
                </div>
            </div>
            <div class="aips-stat-card">
                <div class="aips-stat-icon aips-stat-icon-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="aips-stat-content">
                    <div class="aips-stat-value" id="aips-stat-completed"><?php echo esc_html(number_format($stats['completed'])); ?></div>
                    <div class="aips-stat-label"><?php esc_html_e('Completed', 'ai-post-scheduler'); ?></div>
                </div>
            </div>
            <div class="aips-stat-card">
                <div class="aips-stat-icon aips-stat-icon-error">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <div class="aips-stat-content">
                    <div class="aips-stat-value" id="aips-stat-failed"><?php echo esc_html(number_format($stats['failed'])); ?></div>
                    <div class="aips-stat-label"><?php esc_html_e('Failed', 'ai-post-scheduler'); ?></div>
                </div>
            </div>
            <div class="aips-stat-card">
                <div class="aips-stat-icon aips-stat-icon-info">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="aips-stat-content">
                    <div class="aips-stat-value" id="aips-stat-success-rate"><?php echo esc_html($stats['success_rate']); ?>%</div>
                    <div class="aips-stat-label"><?php esc_html_e('Success Rate', 'ai-post-scheduler'); ?></div>
                </div>
            </div>
        </div>
<?php endif; ?>
<div class="aips-history-tab">
    <?php if ($is_history_tab): ?>
    <h2><?php esc_html_e('Generation History', 'ai-post-scheduler'); ?></h2>
    
    <div class="aips-history-stats">
        <div class="aips-stat-inline">
            <span class="aips-stat-label"><?php esc_html_e('Total:', 'ai-post-scheduler'); ?></span>
            <span class="aips-stat-value" id="aips-stat-total"><?php echo esc_html($stats['total']); ?></span>
        </div>
        <div class="aips-stat-inline aips-stat-success">
            <span class="aips-stat-label"><?php esc_html_e('Completed:', 'ai-post-scheduler'); ?></span>
            <span class="aips-stat-value" id="aips-stat-completed"><?php echo esc_html($stats['completed']); ?></span>
        </div>
        <div class="aips-stat-inline aips-stat-error">
            <span class="aips-stat-label"><?php esc_html_e('Failed:', 'ai-post-scheduler'); ?></span>
            <span class="aips-stat-value" id="aips-stat-failed"><?php echo esc_html($stats['failed']); ?></span>
        </div>
        <div class="aips-stat-inline">
            <span class="aips-stat-label"><?php esc_html_e('Success Rate:', 'ai-post-scheduler'); ?></span>
            <span class="aips-stat-value" id="aips-stat-success-rate"><?php echo esc_html($stats['success_rate']); ?>%</span>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$is_history_tab): ?>
        <!-- Content Panel -->
        <div class="aips-content-panel">
            <!-- Filter Bar -->
            <div class="aips-filter-bar">
                <div class="aips-filter-left">
                    <select id="aips-filter-status" class="aips-form-select">
                        <option value=""><?php esc_html_e('All Statuses', 'ai-post-scheduler'); ?></option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php esc_html_e('Completed', 'ai-post-scheduler'); ?></option>
                        <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php esc_html_e('Failed', 'ai-post-scheduler'); ?></option>
                        <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php esc_html_e('Processing', 'ai-post-scheduler'); ?></option>
                    </select>
                    <button class="aips-btn aips-btn-sm" id="aips-filter-btn"><?php esc_html_e('Filter', 'ai-post-scheduler'); ?></button>
                </div>
                <div class="aips-filter-right">
                    <input type="search" id="aips-history-search-input" name="s" class="aips-form-input" placeholder="<?php esc_attr_e('Search history...', 'ai-post-scheduler'); ?>" value="<?php echo esc_attr($search_query); ?>">
                    <button type="submit" id="aips-history-search-btn" class="aips-btn aips-btn-sm">
                        <span class="dashicons dashicons-search"></span>
                    </button>
                    <?php if (!empty($search_query)): ?>
                        <a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="aips-btn aips-btn-sm"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="aips-panel-toolbar">
                <div class="aips-toolbar-left">
                    <button class="aips-btn aips-btn-sm aips-btn-danger" id="aips-delete-selected-btn" disabled>
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Delete Selected', 'ai-post-scheduler'); ?>
                    </button>
                    <button class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-reload-history-btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Reload', 'ai-post-scheduler'); ?>
                    </button>
                </div>
                <div class="aips-toolbar-right">
                    <button class="aips-btn aips-btn-sm aips-btn-danger aips-clear-history" data-status="failed">
                        <?php esc_html_e('Clear Failed', 'ai-post-scheduler'); ?>
                    </button>
                    <button class="aips-btn aips-btn-sm aips-btn-danger aips-clear-history" data-status="">
                        <?php esc_html_e('Clear All', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
            
            <div class="aips-panel-body no-padding">
    <?php else: ?>
    
    <p class="search-box">
        <label class="screen-reader-text" for="aips-history-search-input"><?php esc_html_e('Search History:', 'ai-post-scheduler'); ?></label>
        <input type="search" id="aips-history-search-input" name="s" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>">
        <input type="submit" id="aips-history-search-btn" class="button" value="<?php esc_attr_e('Search History', 'ai-post-scheduler'); ?>">
        <?php if (!empty($_GET['s'])): ?>
            <a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="button"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
        <?php endif; ?>
    </p>

    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="aips-filter-status">
                <option value=""><?php esc_html_e('All Statuses', 'ai-post-scheduler'); ?></option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php esc_html_e('Completed', 'ai-post-scheduler'); ?></option>
                <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php esc_html_e('Failed', 'ai-post-scheduler'); ?></option>
                <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php esc_html_e('Processing', 'ai-post-scheduler'); ?></option>
            </select>
            <button class="button" id="aips-filter-btn"><?php esc_html_e('Filter', 'ai-post-scheduler'); ?></button>
            <button class="button" id="aips-delete-selected-btn" disabled><?php esc_html_e('Delete Selected', 'ai-post-scheduler'); ?></button>
        </div>
        <div class="alignright">
            <button class="button" id="aips-export-history-btn"><?php esc_html_e('Export CSV', 'ai-post-scheduler'); ?></button>
            <button class="button" id="aips-reload-history-btn"><?php esc_html_e('Reload', 'ai-post-scheduler'); ?></button>
            <button class="button aips-clear-history" data-status=""><?php esc_html_e('Clear All History', 'ai-post-scheduler'); ?></button>
            <button class="button aips-clear-history" data-status="failed"><?php esc_html_e('Clear Failed Only', 'ai-post-scheduler'); ?></button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($history['items'])): ?>
    <table class="<?php echo !$is_history_tab ? 'aips-table' : 'wp-list-table widefat fixed striped'; ?> aips-history-table">
        <thead>
            <tr>
                <td id="cb" class="manage-column column-cb check-column">
                    <label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e('Select All', 'ai-post-scheduler'); ?></label>
                    <input id="cb-select-all-1" type="checkbox">
                </td>
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
                <th scope="row" class="check-column">
                    <label class="screen-reader-text" for="cb-select-<?php echo esc_attr($item->id); ?>"><?php esc_html_e('Select Item', 'ai-post-scheduler'); ?></label>
                    <input id="cb-select-<?php echo esc_attr($item->id); ?>" type="checkbox" name="history[]" value="<?php echo esc_attr($item->id); ?>">
                </th>
                <td class="column-title">
                    <?php if ($item->post_id): ?>
                    <a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>">
                        <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
                    </a>
                    <?php else: ?>
                    <?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
                    <?php endif; ?>
                    <?php if ($item->status === 'failed' && $item->error_message): ?>
                    <div class="aips-error-message" style="font-size: 12px; color: #dc3232; margin-top: 4px;"><?php echo esc_html($item->error_message); ?></div>
                    <?php endif; ?>
                </td>
                <td class="column-template">
                    <span class="aips-meta-text"><?php echo esc_html($item->template_name ?: '-'); ?></span>
                </td>
                <td class="column-status">
                    <?php
                    $status_class = 'aips-badge ';
                    switch ($item->status) {
                        case 'completed':
                            $status_class .= 'aips-badge-success';
                            $icon = 'yes-alt';
                            break;
                        case 'failed':
                            $status_class .= 'aips-badge-error';
                            $icon = 'dismiss';
                            break;
                        case 'processing':
                            $status_class .= 'aips-badge-info';
                            $icon = 'update';
                            break;
                        default:
                            $status_class .= 'aips-badge-neutral';
                            $icon = 'minus';
                    }
                    ?>
                    <span class="<?php echo esc_attr($status_class); ?>">
                        <?php if (!$is_history_tab): ?><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span><?php endif; ?>
                        <?php echo esc_html(ucfirst($item->status)); ?>
                    </span>
                </td>
                <td class="column-date">
                    <span class="aips-meta-text"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at))); ?></span>
                </td>
                <td class="column-actions">
                    <div class="aips-btn-group">
                        <?php if ($item->post_id): ?>
                        <a href="<?php echo esc_url(get_permalink($item->post_id)); ?>" class="<?php echo !$is_history_tab ? 'aips-btn aips-btn-sm' : 'button button-small'; ?>" target="_blank" title="<?php esc_attr_e('View Post', 'ai-post-scheduler'); ?>">
                            <?php if (!$is_history_tab): ?><span class="dashicons dashicons-external"></span><?php else: ?><?php esc_html_e('View', 'ai-post-scheduler'); ?><?php endif; ?>
                        </a>
                        <?php endif; ?>
                        <button class="<?php echo !$is_history_tab ? 'aips-btn aips-btn-sm' : 'button button-small'; ?> aips-view-details" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('View Details', 'ai-post-scheduler'); ?>">
                            <?php if (!$is_history_tab): ?><span class="dashicons dashicons-info"></span><?php else: ?><?php esc_html_e('Details', 'ai-post-scheduler'); ?><?php endif; ?>
                        </button>
                        <?php if ($item->status === 'failed' && $item->template_id): ?>
                        <button class="<?php echo !$is_history_tab ? 'aips-btn aips-btn-sm' : 'button button-small'; ?> aips-retry-generation" data-id="<?php echo esc_attr($item->id); ?>" title="<?php esc_attr_e('Retry Generation', 'ai-post-scheduler'); ?>">
                            <?php if (!$is_history_tab): ?><span class="dashicons dashicons-update"></span><?php else: ?><?php esc_html_e('Retry', 'ai-post-scheduler'); ?><?php endif; ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if ($history['pages'] > 1): ?>
    <div class="<?php echo !$is_history_tab ? 'aips-panel-footer' : 'tablenav bottom'; ?>">
        <div class="tablenav-pages">
            <span class="displaying-num">
                 <?php printf(
                     esc_html__('%d items', 'ai-post-scheduler'),
                     $history['total']
                 ); ?>
             </span>
             <span class="pagination-links">
                 <?php
                $base_url = $history_base_url;
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
        <div class="aips-empty-icon">
            <span class="dashicons dashicons-backup" aria-hidden="true"></span>
        </div>
        <h3 class="aips-empty-title"><?php esc_html_e('No History Yet', 'ai-post-scheduler'); ?></h3>
        <p class="aips-empty-description"><?php esc_html_e('Generated posts will appear here once you create content.', 'ai-post-scheduler'); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!$is_history_tab): ?>
            </div><!-- .aips-panel-body -->
        </div><!-- .aips-content-panel -->
    </div><!-- .aips-page-container -->
</div><!-- .wrap -->
<?php endif; ?>

<div id="aips-details-modal" class="aips-modal" style="display: none;">
    <div class="aips-modal-content aips-modal-large">
        <div class="aips-modal-header">
            <h2 id="aips-details-title"><?php esc_html_e('Generation Details', 'ai-post-scheduler'); ?></h2>
            <button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
        </div>
        <div class="aips-modal-body">
            <div id="aips-details-loading" class="aips-loading">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Loading details...', 'ai-post-scheduler'); ?>
            </div>
            <div id="aips-details-content" style="display: none;">
                <div class="aips-details-section">
                    <h3><?php esc_html_e('Summary', 'ai-post-scheduler'); ?></h3>
                    <div id="aips-details-summary"></div>
                </div>
                
                <div class="aips-details-section">
                    <h3><?php esc_html_e('Template Configuration', 'ai-post-scheduler'); ?></h3>
                    <div id="aips-details-template"></div>
                </div>
                
                <div class="aips-details-section" id="aips-details-voice-section" style="display: none;">
                    <h3><?php esc_html_e('Voice Configuration', 'ai-post-scheduler'); ?></h3>
                    <div id="aips-details-voice"></div>
                </div>
                
                <div class="aips-details-section">
                    <h3><?php esc_html_e('AI Calls', 'ai-post-scheduler'); ?></h3>
                    <div id="aips-details-ai-calls"></div>
                </div>
                
                <div class="aips-details-section" id="aips-details-errors-section" style="display: none;">
                    <h3><?php esc_html_e('Errors', 'ai-post-scheduler'); ?></h3>
                    <div id="aips-details-errors"></div>
                </div>
            </div>
        </div>
    </div>
</div>
