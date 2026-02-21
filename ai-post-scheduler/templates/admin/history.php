<?php
if (!defined('ABSPATH')) {
    exit;
}

// This template is included by AIPS_History::render_page() which passes
// $history_handler, $history, and $stats. Ensure default variables are set.
$current_page  = isset($current_page) ? absint($current_page) : (isset($_GET['paged']) ? absint($_GET['paged']) : 1);
$status_filter = isset($status_filter) ? $status_filter : (isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '');
$search_query  = isset($search_query) ? $search_query : (isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '');

if (isset($history_handler)) {
    $history = $history_handler->get_history(array(
        'page'   => $current_page,
        'status' => $status_filter,
        'search' => $search_query,
    ));
}

?>
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
                    <button class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-filter-btn" title="<?php esc_attr_e('Filter', 'ai-post-scheduler'); ?>">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
                    </button>
                </div>
                <div class="aips-filter-right">
                    <input type="search" id="aips-history-search-input" name="s" class="aips-form-input" placeholder="<?php esc_attr_e('Search history...', 'ai-post-scheduler'); ?>" value="<?php echo esc_attr($search_query); ?>">
                    <button type="submit" id="aips-history-search-btn" class="aips-btn aips-btn-sm aips-btn-secondary" title="<?php esc_attr_e('Search', 'ai-post-scheduler'); ?>">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Search', 'ai-post-scheduler'); ?>
                    </button>
                    <?php if (!empty($search_query)): ?>
                        <a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="aips-btn aips-btn-sm"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="aips-panel-toolbar">
                <div class="aips-toolbar-left">
                    <button class="aips-btn aips-btn-sm aips-btn-danger aips-btn-danger-solid" id="aips-delete-selected-btn" disabled>
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Delete Selected', 'ai-post-scheduler'); ?>
                    </button>
                    <button class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-reload-history-btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Reload', 'ai-post-scheduler'); ?>
                    </button>
                </div>
                <div class="aips-toolbar-right">
                    <button class="aips-btn aips-btn-sm aips-btn-danger aips-btn-danger-solid aips-clear-history" data-status="failed">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php esc_html_e('Clear Failed', 'ai-post-scheduler'); ?>
                    </button>
                    <button class="aips-btn aips-btn-sm aips-btn-danger aips-btn-danger-solid aips-clear-history" data-status="">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear All', 'ai-post-scheduler'); ?>
                    </button>
                </div>
            </div>
            
            <div class="aips-panel-body no-padding">
    <?php if (!empty($history['items'])): ?>
    <table class="aips-table aips-history-table">
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
                <?php include AIPS_PLUGIN_DIR . 'templates/partials/history-row.php'; ?>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="aips-history-pagination-row">
                <td colspan="6" class="aips-history-pagination-cell">
                    <?php if (isset($history_handler)): ?>
                    <?php $history_handler->render_pagination_html($history, $status_filter, $search_query); ?>
                    <?php else: ?>
                    <div class="aips-history-pagination">
                        <span class="aips-history-pagination-info"><?php printf(esc_html__('%d items', 'ai-post-scheduler'), $history['total']); ?></span>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
        </tfoot>
    </table>
    
    <?php else: ?>
    <div class="aips-empty-state">
        <div class="aips-empty-icon">
            <span class="dashicons dashicons-backup" aria-hidden="true"></span>
        </div>
        <h3 class="aips-empty-title"><?php esc_html_e('No History Yet', 'ai-post-scheduler'); ?></h3>
        <p class="aips-empty-description"><?php esc_html_e('Generated posts will appear here once you create content.', 'ai-post-scheduler'); ?></p>
    </div>
    <?php endif; ?>

            </div><!-- .aips-panel-body -->
        </div><!-- .aips-content-panel -->
    </div><!-- .aips-page-container -->
</div><!-- .wrap -->

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

<?php include AIPS_PLUGIN_DIR . 'templates/partials/view-session-modal.php'; ?>
