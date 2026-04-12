<?php
if (!defined('ABSPATH')) {
    exit;
}

// This template is included by AIPS_History::render_page() which passes
// $history_handler, $history, and $stats. Ensure default variables are set.
$current_page  = isset($current_page) ? absint($current_page) : (isset($_GET['paged']) ? absint($_GET['paged']) : 1);
$status_filter = isset($status_filter) ? $status_filter : (isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '');
$search_query  = isset($search_query) ? $search_query : (isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '');

if (isset($history_handler)) {
    if (!isset($history) || !is_array($history)) {
        $history = $history_handler->get_history(array(
            'page'   => $current_page,
            'status' => $status_filter,
            'search' => $search_query,
            'fields' => 'list',
        ));
    }
    if (!isset($stats)) {
        $stats = $history_handler->get_stats();
    }
}

if (!isset($stats) || !is_array($stats)) {
    $stats = array(
        'total' => 0,
        'completed' => 0,
        'failed' => 0,
        'success_rate' => 0,
    );
}

$items       = isset($history['items']) ? $history['items'] : array();
$total_items = isset($history['total']) ? (int) $history['total'] : 0;
?>
<div class="wrap aips-wrap">
    <div class="aips-page-container">
        <!-- Page Header -->
        <div class="aips-page-header">
            <div class="aips-page-header-top">
                <div>
                    <h1 class="aips-page-title"><?php esc_html_e('History', 'ai-post-scheduler'); ?></h1>
                    <p class="aips-page-description"><?php esc_html_e('View generation history containers and inspect every logged step, AI call, and error for each run.', 'ai-post-scheduler'); ?></p>
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

        <?php
        $has_active_filter = !empty($status_filter) || !empty($search_query);
        $show_panel        = $total_items > 0 || $has_active_filter;
        ?>
        <?php if ($show_panel): ?>
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
                    <button class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-filter-btn">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
                    </button>
                </div>
                <div class="aips-filter-right">
                    <label class="screen-reader-text" for="aips-history-search-input"><?php esc_html_e('Search History:', 'ai-post-scheduler'); ?></label>
                    <input type="search" id="aips-history-search-input" class="aips-form-input" placeholder="<?php esc_attr_e('Search history...', 'ai-post-scheduler'); ?>" value="<?php echo esc_attr($search_query); ?>">
                    <button type="button" id="aips-history-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="<?php echo $has_active_filter ? '' : 'display: none;'; ?>"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
                </div>
            </div>

            <!-- Toolbar (bulk actions) -->
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

            <!-- History Containers Table -->
            <div class="aips-panel-body no-padding">
                <table class="aips-table aips-history-table">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column">
                                <label class="screen-reader-text" for="aips-cb-select-all"><?php esc_html_e('Select All', 'ai-post-scheduler'); ?></label>
                                <input id="aips-cb-select-all" type="checkbox">
                            </td>
                            <th class="column-title"><?php esc_html_e('Title / Topic', 'ai-post-scheduler'); ?></th>
                            <th class="column-template"><?php esc_html_e('Template', 'ai-post-scheduler'); ?></th>
                            <th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
                            <th class="column-date"><?php esc_html_e('Created', 'ai-post-scheduler'); ?></th>
                            <th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="aips-history-tbody">
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <?php include AIPS_PLUGIN_DIR . 'templates/partials/history-row.php'; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center;padding:40px;">
                                    <span class="dashicons dashicons-search" style="font-size:32px;color:#ccc;vertical-align:middle;margin-right:8px;" aria-hidden="true"></span>
                                    <?php esc_html_e('No history containers match your current filters.', 'ai-post-scheduler'); ?>
                                    <?php if ($has_active_filter): ?>
                                        <br><br>
                                        <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-clear-history-search-btn">
                                            <span class="dashicons dashicons-dismiss"></span>
                                            <?php esc_html_e('Clear Filters', 'ai-post-scheduler'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="aips-history-pagination-row">
                            <td colspan="6" class="aips-history-pagination-cell">
                                <?php if (isset($history_handler)): ?>
                                    <?php $history_handler->render_pagination_html($history, $status_filter, $search_query); ?>
                                <?php else: ?>
                                    <div class="aips-history-pagination">
                                        <span class="aips-history-pagination-info"><?php printf(esc_html__('%d items', 'ai-post-scheduler'), $total_items); ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <!-- No Search Results State (client-side live filter) -->
                <div id="aips-history-search-no-results" class="aips-empty-state" style="display: none; padding: 60px 20px;">
                    <div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
                    <h3 class="aips-empty-state-title"><?php esc_html_e('No History Found', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-state-description"><?php esc_html_e('No history containers match your search criteria. Try a different search term or filter.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-empty-state-actions">
                        <button type="button" class="aips-btn aips-btn-primary aips-clear-history-search-btn">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
                        </button>
                    </div>
                </div>
            </div><!-- .aips-panel-body -->
        </div><!-- .aips-content-panel -->

        <?php else: ?>
        <div class="aips-content-panel">
            <div class="aips-panel-body">
                <div class="aips-empty-state">
                    <div class="dashicons dashicons-backup aips-empty-state-icon" aria-hidden="true"></div>
                    <h3 class="aips-empty-state-title"><?php esc_html_e('No History Yet', 'ai-post-scheduler'); ?></h3>
                    <p class="aips-empty-state-description"><?php esc_html_e('Generation history containers will appear here once you start creating AI-powered content.', 'ai-post-scheduler'); ?></p>
                    <div class="aips-empty-state-actions">
                        <a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>" class="aips-btn aips-btn-primary">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php esc_html_e('Manage Schedules', 'ai-post-scheduler'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- .aips-page-container -->
</div><!-- .wrap.aips-wrap -->

<!-- History Logs Modal -->
<div id="aips-history-logs-modal" class="aips-modal" style="display: none;">
    <div class="aips-modal-content aips-modal-large">
        <button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
        <h3 id="aips-history-logs-modal-title"><?php esc_html_e('History Details', 'ai-post-scheduler'); ?></h3>
        <div id="aips-history-logs-content">
            <p><?php esc_html_e('Loading logs...', 'ai-post-scheduler'); ?></p>
        </div>
    </div>
</div>

<?php include AIPS_PLUGIN_DIR . 'templates/partials/view-session-modal.php'; ?>

<!-- =====================================================================
     AIPS.Templates HTML blocks for admin-history.js
     These <script type="text/html"> elements are read by AIPS.Templates.render()
     and AIPS.Templates.renderRaw(). They are never executed as JavaScript.
     ===================================================================== -->

<!-- Template: generic loading message inside the logs modal -->
<script type="text/html" id="aips-tmpl-history-loading-msg">
	<p>{{text}}</p>
</script>

<!-- Template: error notice shown when the AJAX request fails -->
<script type="text/html" id="aips-tmpl-history-error-msg">
	<p class="notice notice-error">{{message}}</p>
</script>

<!-- Template: tbody loading placeholder row (shown while reloading the table) -->
<script type="text/html" id="aips-tmpl-history-tbody-loading">
	<tr><td colspan="6" style="text-align:center;padding:20px;">{{text}}</td></tr>
</script>

<!-- Template: tbody empty-state row (no containers match current filters) -->
<script type="text/html" id="aips-tmpl-history-tbody-empty">
	<tr>
		<td colspan="6" style="text-align:center;padding:40px;">
			<span class="dashicons dashicons-search" style="font-size:32px;color:#ccc;vertical-align:middle;margin-right:8px;" aria-hidden="true"></span>
			{{message}}
		</td>
	</tr>
</script>

<!-- Template: container summary section wrapper; {{rows}} is injected raw -->
<script type="text/html" id="aips-tmpl-history-modal-summary">
	<div class="aips-history-modal-summary">
		<table class="aips-table" style="width:100%;margin-bottom:20px;"><tbody>{{rows}}</tbody></table>
	</div>
</script>

<!-- Template: a plain label/value summary row (values are auto-escaped by render()) -->
<script type="text/html" id="aips-tmpl-history-summary-row">
	<tr><th>{{label}}</th><td>{{value}}</td></tr>
</script>

<!-- Template: summary row that shows the status badge; use renderRaw() with pre-escaped values -->
<script type="text/html" id="aips-tmpl-history-summary-status-row">
	<tr><th>{{label}}</th><td><span class="aips-badge {{statusClass}}">{{status}}</span></td></tr>
</script>

<!-- Template: summary row for an error message with error colouring -->
<script type="text/html" id="aips-tmpl-history-summary-error-row">
	<tr><th>{{label}}</th><td style="color:#d63638;">{{message}}</td></tr>
</script>

<!-- Template: log-entries section heading with item count badge; use renderRaw() -->
<script type="text/html" id="aips-tmpl-history-logs-heading">
	<h3>{{heading}} <span class="aips-badge aips-badge-neutral">{{count}}</span></h3>
</script>

<!-- Template: "no log entries" paragraph shown when the container has no logs -->
<script type="text/html" id="aips-tmpl-history-no-logs">
	<p>{{message}}</p>
</script>

<!-- Template: logs table shell; {{colTimestamp}} etc. are pre-escaped, {{rows}} is raw HTML -->
<script type="text/html" id="aips-tmpl-history-logs-table">
	<table class="aips-table aips-history-logs-table" style="width:100%;">
		<thead>
			<tr>
				<th style="width:150px;">{{colTimestamp}}</th>
				<th style="width:130px;">{{colType}}</th>
				<th style="width:150px;">{{colLogType}}</th>
				<th>{{colDetails}}</th>
			</tr>
		</thead>
		<tbody>{{rows}}</tbody>
	</table>
</script>

<!-- Template: a single log table row; {{detailsHtml}} is raw, all others are pre-escaped -->
<script type="text/html" id="aips-tmpl-history-log-row">
	<tr>
		<td style="white-space:nowrap;font-size:12px;">{{timestamp}}</td>
		<td><span class="aips-badge {{typeClass}}">{{typeLabel}}</span></td>
		<td style="font-size:12px;font-family:monospace;">{{logType}}</td>
		<td>{{detailsHtml}}</td>
	</tr>
</script>

<!-- Template: the main message paragraph inside a log-row details cell -->
<script type="text/html" id="aips-tmpl-history-log-message">
	<p style="margin:0 0 6px;">{{message}}</p>
</script>

<!-- Template: collapsible extra-details block inside a log row; use render() for auto-escaping -->
<script type="text/html" id="aips-tmpl-history-log-detail-block">
	<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-log-toggle" data-target="#{{rowId}}" style="font-size:11px;">{{showLabel}}</button>
	<div id="{{rowId}}" style="display:none;margin-top:8px;">
		<pre style="max-height:200px;overflow:auto;white-space:pre-wrap;font-size:11px;background:#f6f7f7;padding:8px;border-radius:4px;">{{details}}</pre>
	</div>
</script>
