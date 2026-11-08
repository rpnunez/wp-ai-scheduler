<?php
/**
 * Admin Template: Diagnostics > AI Errors Tab
 *
 * Displays a unified, searchable, filterable log of AI call failures and errors
 * with smart categorization, aggregate trend insights, live API ping, and export capabilities.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

$initial_date_from = filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
$initial_date_to   = filter_input(INPUT_GET, 'date_to', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
$initial_component = filter_input(INPUT_GET, 'component', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
$initial_category  = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
$initial_search    = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
$categories        = AIPS_AI_Error_Classifier::get_categories();
$retention_days    = (int) AIPS_Config::get_instance()->get_option('aips_ai_error_log_retention_value', 30);
?>

<div class="aips-tab-pane aips-tab-ai-errors" id="aips-tab-ai-errors-pane">
	<!-- Tab Header -->
	<div class="aips-tab-header">
		<div>
			<h2 class="aips-tab-title"><?php esc_html_e('AI Call Errors & Failures', 'ai-post-scheduler'); ?></h2>
			<p class="aips-tab-desc"><?php esc_html_e('Unified intelligence console for tracking AI generation errors, API timeouts, rate limit hits, and provider failure details across all generation runs.', 'ai-post-scheduler'); ?></p>
		</div>
	</div>

	<!-- 4-Card Summary Header -->
	<div class="aips-ai-errors-stats-grid" id="aips-ai-errors-stats-grid">
		<!-- Card 1: Total Errors & Volume -->
		<div class="aips-card aips-ai-errors-stat-card">
			<div class="aips-ai-errors-stat-icon-wrap aips-stat-icon-danger">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="aips-ai-errors-stat-info">
				<span class="aips-ai-errors-stat-label"><?php esc_html_e('Total AI Errors', 'ai-post-scheduler'); ?></span>
				<div class="aips-ai-errors-stat-val" id="aips-stat-total-errors">&mdash;</div>
				<span class="aips-ai-errors-stat-sub" id="aips-stat-total-sub"><?php esc_html_e('Matching current filters', 'ai-post-scheduler'); ?></span>
			</div>
		</div>

		<!-- Card 2: Top Error Categories with 1-click Filter Pills -->
		<div class="aips-card aips-ai-errors-stat-card aips-ai-errors-cat-card">
			<div class="aips-ai-errors-stat-info aips-w-full">
				<div class="aips-ai-errors-stat-header-flex">
					<span class="aips-ai-errors-stat-label"><?php esc_html_e('Error Categories', 'ai-post-scheduler'); ?></span>
					<span class="aips-ai-errors-stat-hint"><?php esc_html_e('Click to filter', 'ai-post-scheduler'); ?></span>
				</div>
				<div class="aips-ai-errors-category-pills" id="aips-stat-category-pills">
					<span class="aips-ai-errors-pill-placeholder"><?php esc_html_e('Loading categories…', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
		</div>

		<!-- Card 3: Most Affected Component -->
		<div class="aips-card aips-ai-errors-stat-card">
			<div class="aips-ai-errors-stat-icon-wrap aips-stat-icon-info">
				<span class="dashicons dashicons-admin-generic"></span>
			</div>
			<div class="aips-ai-errors-stat-info">
				<span class="aips-ai-errors-stat-label"><?php esc_html_e('Top Failing Stage', 'ai-post-scheduler'); ?></span>
				<div class="aips-ai-errors-stat-val" id="aips-stat-top-component">&mdash;</div>
				<span class="aips-ai-errors-stat-sub" id="aips-stat-top-component-sub">&mdash;</span>
			</div>
		</div>

		<!-- Card 4: Live Provider Connection Ping -->
		<div class="aips-card aips-ai-errors-stat-card aips-ai-errors-ping-card">
			<div class="aips-ai-errors-stat-info aips-w-full">
				<div class="aips-ai-errors-stat-header-flex">
					<span class="aips-ai-errors-stat-label"><?php esc_html_e('AI Provider Health', 'ai-post-scheduler'); ?></span>
					<span id="aips-ai-errors-ping-badge" class="aips-badge aips-badge-light"><?php esc_html_e('Ready', 'ai-post-scheduler'); ?></span>
				</div>
				<div class="aips-ai-errors-ping-action-wrap">
					<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-ai-errors-ping-btn">
						<span class="dashicons dashicons-update"></span>
						<span class="aips-btn-text"><?php esc_html_e('Ping Connection', 'ai-post-scheduler'); ?></span>
					</button>
					<span id="aips-ai-errors-ping-msg" class="aips-ai-errors-ping-msg"></span>
				</div>
			</div>
		</div>
	</div>

	<!-- Filter Bar -->
	<div class="aips-filter-bar">
		<div class="aips-filter-left">
			<!-- Quick Date Presets -->
			<div class="aips-filter-group aips-filter-presets">
				<div class="aips-btn-group aips-date-preset-group" role="group">
					<button type="button" class="aips-btn aips-btn-sm aips-date-preset-btn" data-preset="today"><?php esc_html_e('Today', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-btn aips-btn-sm aips-date-preset-btn" data-preset="7d"><?php esc_html_e('7 Days', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-btn aips-btn-sm aips-date-preset-btn" data-preset="30d"><?php esc_html_e('30 Days', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-btn aips-btn-sm aips-date-preset-btn active" data-preset="all"><?php esc_html_e('All Time', 'ai-post-scheduler'); ?></button>
				</div>
			</div>

			<!-- Date Range Custom Inputs -->
			<div class="aips-filter-group aips-filter-dates">
				<label for="aips-ai-errors-from"><?php esc_html_e('From:', 'ai-post-scheduler'); ?></label>
				<input type="date" id="aips-ai-errors-from" class="aips-form-input aips-filter-date" value="<?php echo esc_attr($initial_date_from); ?>" />
				<label for="aips-ai-errors-to"><?php esc_html_e('To:', 'ai-post-scheduler'); ?></label>
				<input type="date" id="aips-ai-errors-to" class="aips-form-input aips-filter-date" value="<?php echo esc_attr($initial_date_to); ?>" />
			</div>

			<!-- Category Filter Dropdown -->
			<div class="aips-filter-group">
				<select id="aips-ai-errors-category" class="aips-form-select">
					<option value=""><?php esc_html_e('All Categories', 'ai-post-scheduler'); ?></option>
					<?php foreach ($categories as $cat) : ?>
						<option value="<?php echo esc_attr($cat['slug']); ?>" <?php selected($initial_category, $cat['slug']); ?>>
							<?php echo esc_html($cat['label']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Component Filter -->
			<div class="aips-filter-group">
				<select id="aips-ai-errors-component" class="aips-form-select">
					<option value=""><?php esc_html_e('All Components', 'ai-post-scheduler'); ?></option>
					<option value="content" <?php selected($initial_component, 'content'); ?>><?php esc_html_e('Content', 'ai-post-scheduler'); ?></option>
					<option value="title" <?php selected($initial_component, 'title'); ?>><?php esc_html_e('Title', 'ai-post-scheduler'); ?></option>
					<option value="slug" <?php selected($initial_component, 'slug'); ?>><?php esc_html_e('Post Slug', 'ai-post-scheduler'); ?></option>
					<option value="excerpt" <?php selected($initial_component, 'excerpt'); ?>><?php esc_html_e('Excerpt', 'ai-post-scheduler'); ?></option>
					<option value="featured_image" <?php selected($initial_component, 'featured_image'); ?>><?php esc_html_e('Featured Image Prompt', 'ai-post-scheduler'); ?></option>
					<option value="post_images" <?php selected($initial_component, 'post_images'); ?>><?php esc_html_e('Post Images', 'ai-post-scheduler'); ?></option>
					<option value="topics" <?php selected($initial_component, 'topics'); ?>><?php esc_html_e('Topics', 'ai-post-scheduler'); ?></option>
					<option value="research" <?php selected($initial_component, 'research'); ?>><?php esc_html_e('Research', 'ai-post-scheduler'); ?></option>
					<option value="taxonomy" <?php selected($initial_component, 'taxonomy'); ?>><?php esc_html_e('Taxonomy', 'ai-post-scheduler'); ?></option>
				</select>
			</div>

			<!-- Scope Filter -->
			<div class="aips-filter-group">
				<select id="aips-ai-errors-scope" class="aips-form-select">
					<option value="ai_only" selected><?php esc_html_e('AI Call Errors Only', 'ai-post-scheduler'); ?></option>
					<option value="all"><?php esc_html_e('All Error Logs', 'ai-post-scheduler'); ?></option>
				</select>
			</div>
		</div>

		<div class="aips-filter-right">
			<!-- Search -->
			<div class="aips-filter-group aips-filter-search-wrap">
				<input type="search" id="aips-ai-errors-search" class="aips-form-input aips-search-input" placeholder="<?php esc_attr_e('Search errors, prompts, titles…', 'ai-post-scheduler'); ?>" value="<?php echo esc_attr($initial_search); ?>" />
			</div>

			<!-- Action Buttons -->
			<div class="aips-filter-group aips-filter-actions">
				<button type="button" class="aips-btn aips-btn-primary" id="aips-ai-errors-filter-btn">
					<span class="dashicons dashicons-filter"></span>
					<span><?php esc_html_e('Filter', 'ai-post-scheduler'); ?></span>
				</button>
				<button type="button" class="aips-btn aips-btn-secondary" id="aips-ai-errors-reset-btn">
					<?php esc_html_e('Reset', 'ai-post-scheduler'); ?>
				</button>

				<!-- Export Buttons Dropdown -->
				<div class="aips-dropdown aips-export-dropdown" id="aips-ai-errors-export-dropdown">
					<button type="button" class="aips-btn aips-btn-secondary aips-dropdown-toggle" id="aips-ai-errors-export-toggle">
						<span class="dashicons dashicons-download"></span>
						<span><?php esc_html_e('Export', 'ai-post-scheduler'); ?></span>
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<div class="aips-dropdown-menu aips-is-hidden" id="aips-ai-errors-export-menu">
						<button type="button" class="aips-dropdown-item aips-export-btn" data-format="csv">
							<span class="dashicons dashicons-media-spreadsheet"></span>
							<span><?php esc_html_e('Export CSV', 'ai-post-scheduler'); ?></span>
						</button>
						<button type="button" class="aips-dropdown-item aips-export-btn" data-format="json">
							<span class="dashicons dashicons-media-code"></span>
							<span><?php esc_html_e('Export JSON', 'ai-post-scheduler'); ?></span>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Results Summary Bar -->
	<div class="aips-ai-errors-summary-bar">
		<span id="aips-ai-errors-count-text"><?php esc_html_e('Loading records…', 'ai-post-scheduler'); ?></span>
		<div id="aips-ai-errors-pagination-top" class="aips-pagination"></div>
	</div>

	<!-- Error Logs Table -->
	<div class="aips-table-container aips-ai-errors-table-wrap">
		<table class="wp-list-table widefat striped fixed table-view-list" id="aips-ai-errors-table">
			<thead>
				<tr>
					<th scope="col" class="column-date"><?php esc_html_e('Date / Time', 'ai-post-scheduler'); ?></th>
					<th scope="col" class="column-category"><?php esc_html_e('Category', 'ai-post-scheduler'); ?></th>
					<th scope="col" class="column-component"><?php esc_html_e('Component', 'ai-post-scheduler'); ?></th>
					<th scope="col" class="column-error"><?php esc_html_e('Error Summary', 'ai-post-scheduler'); ?></th>
					<th scope="col" class="column-run"><?php esc_html_e('Associated Run / Post', 'ai-post-scheduler'); ?></th>
					<th scope="col" class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
				</tr>
			</thead>
			<tbody id="aips-ai-errors-tbody">
				<!-- Dynamically populated by AIPS.Templates -->
			</tbody>
		</table>
	</div>

	<!-- Bottom Pagination -->
	<div class="aips-ai-errors-summary-bar aips-ai-errors-summary-bar-bottom">
		<div class="aips-ai-errors-retention-note">
			<?php
			printf(
				esc_html__('Retention: Logs older than %d days are pruned during maintenance.', 'ai-post-scheduler'),
				$retention_days
			);
			?>
			<a href="<?php echo esc_url(admin_url('admin.php?page=aips-settings')); ?>" class="aips-link-underline">
				<?php esc_html_e('Configure in Settings ↗', 'ai-post-scheduler'); ?>
			</a>
			&bull;
			<a href="<?php echo esc_url(admin_url('admin.php?page=aips-diagnostics&tab=system-health')); ?>" class="aips-link-underline">
				<?php esc_html_e('Prune in System Health ↗', 'ai-post-scheduler'); ?>
			</a>
		</div>
		<div id="aips-ai-errors-pagination-bottom" class="aips-pagination"></div>
	</div>
</div>

<!-- AI Error Details Modal -->
<div id="aips-ai-error-modal" class="aips-modal aips-is-hidden" role="dialog" aria-modal="true" aria-labelledby="aips-ai-error-modal-title">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<div class="aips-modal-title-group">
				<h2 id="aips-ai-error-modal-title" class="aips-modal-title">
					<?php esc_html_e('AI Error Details', 'ai-post-scheduler'); ?>
				</h2>
				<span id="aips-ai-error-modal-badge" class="aips-badge aips-ai-error-modal-header-badge"></span>
			</div>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>

		<div class="aips-modal-body">
			<!-- Loading state inside modal -->
			<div id="aips-ai-error-modal-loading" class="aips-loading-center aips-is-hidden">
				<span class="dashicons dashicons-update aips-spin"></span>
				<p><?php esc_html_e('Loading error details…', 'ai-post-scheduler'); ?></p>
			</div>

			<!-- Content body -->
			<div id="aips-ai-error-modal-inner">
				<!-- Root Cause & Resolution Guidance Alert Banner -->
				<div id="aips-ai-error-modal-resolution-banner" class="aips-ai-error-resolution-banner">
					<div class="aips-ai-error-resolution-header">
						<span id="aips-ai-error-res-icon" class="dashicons dashicons-warning"></span>
						<span id="aips-ai-error-res-title" class="aips-ai-error-res-title"></span>
					</div>
					<div id="aips-ai-error-res-cause" class="aips-ai-error-res-cause"></div>
					<div class="aips-ai-error-resolution-footer">
						<div id="aips-ai-error-res-action" class="aips-ai-error-res-action"></div>
						<a id="aips-ai-error-res-link" href="#" target="_blank" rel="noopener noreferrer" class="aips-btn aips-btn-sm aips-btn-secondary aips-is-hidden">
							<span id="aips-ai-error-res-link-text"></span>
						</a>
					</div>
				</div>

				<!-- Associated Run Card -->
				<div class="aips-card aips-ai-error-run-card">
					<div class="aips-ai-error-run-card-info">
						<div class="aips-ai-error-run-card-label">
							<?php esc_html_e('Associated History Container', 'ai-post-scheduler'); ?>
						</div>
						<div id="aips-ai-error-modal-run-title" class="aips-ai-error-run-card-title"></div>
						<div id="aips-ai-error-modal-run-meta" class="aips-ai-error-run-card-meta"></div>
					</div>
					<div class="aips-ai-error-run-card-action">
						<button type="button" class="aips-btn aips-btn-secondary aips-open-history-modal" id="aips-ai-error-open-history-btn" data-history-id="">
							<span class="dashicons dashicons-backup"></span>
							<span><?php esc_html_e('Open History Container', 'ai-post-scheduler'); ?></span>
						</button>
					</div>
				</div>

				<!-- Raw Error Message Notice -->
				<div class="aips-ai-error-modal-section">
					<div class="aips-ai-error-modal-section-title">
						<?php esc_html_e('Error Message:', 'ai-post-scheduler'); ?>
					</div>
					<div id="aips-ai-error-modal-message" class="notice notice-error inline aips-ai-error-modal-notice"></div>
				</div>

				<!-- Request Options & Parameters -->
				<div class="aips-ai-error-modal-section">
					<div class="aips-ai-error-modal-section-header">
						<div class="aips-ai-error-modal-section-title">
							<?php esc_html_e('AI Request Parameters:', 'ai-post-scheduler'); ?>
						</div>
					</div>
					<div id="aips-ai-error-modal-options" class="aips-ai-error-code-block"></div>
				</div>

				<!-- AI Prompt Sent -->
				<div class="aips-ai-error-modal-section">
					<div class="aips-ai-error-modal-section-header">
						<div class="aips-ai-error-modal-section-title">
							<?php esc_html_e('Prompt Sent to AI:', 'ai-post-scheduler'); ?>
						</div>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-ai-error-copy-prompt-btn">
							<span class="dashicons dashicons-clipboard"></span>
							<span class="aips-btn-text"><?php esc_html_e('Copy Prompt', 'ai-post-scheduler'); ?></span>
						</button>
					</div>
					<pre id="aips-ai-error-modal-prompt" class="aips-ai-error-code-block"></pre>
				</div>

				<!-- Diagnostic Payload -->
				<div class="aips-ai-error-modal-section">
					<div class="aips-ai-error-modal-section-header">
						<div class="aips-ai-error-modal-section-title">
							<?php esc_html_e('Full Diagnostic Payload:', 'ai-post-scheduler'); ?>
						</div>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-ai-error-copy-diagnostic-btn">
							<span class="dashicons dashicons-admin-tools"></span>
							<span class="aips-btn-text"><?php esc_html_e('Copy Diagnostic Info', 'ai-post-scheduler'); ?></span>
						</button>
					</div>
					<pre id="aips-ai-error-modal-diagnostic" class="aips-ai-error-diagnostic-block"></pre>
				</div>
			</div>
		</div>

		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-primary" id="aips-ai-error-copy-report-btn">
				<span class="dashicons dashicons-media-document"></span>
				<span class="aips-btn-text"><?php esc_html_e('Copy Support Report', 'ai-post-scheduler'); ?></span>
			</button>
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>

<!-- =========================================================================
     AIPS.Templates: Client-side rendering templates
     ========================================================================= -->

<!-- Error row template -->
<script type="text/html" id="aips-tmpl-ai-error-row">
	<tr class="aips-ai-error-row" data-log-id="{{id}}">
		<td class="column-date">
			<span title="{{formatted_date}}">{{relative_date}}</span>
		</td>
		<td class="column-category">
			<span class="aips-badge {{category_badge}}" title="{{category_label}}">
				<span class="dashicons {{category_icon}} aips-badge-icon"></span>
				<span>{{category_short}}</span>
			</span>
		</td>
		<td class="column-component">
			<span class="aips-badge aips-badge-info">{{component_label}}</span>
		</td>
		<td class="column-error">
			<div class="aips-ai-error-cell-msg">{{error_message}}</div>
		</td>
		<td class="column-run">
			<div class="aips-ai-error-cell-title" title="{{post_title}}">{{post_title}}</div>
			<div class="aips-ai-error-cell-run-id">{{run_label}}</div>
		</td>
		<td class="column-actions">
			<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-ai-error-view-btn" data-log-id="{{id}}">
				{{view_details_label}}
			</button>
		</td>
	</tr>
</script>

<!-- Category summary pill template -->
<script type="text/html" id="aips-tmpl-ai-errors-category-pill">
	<button type="button" class="aips-ai-errors-cat-pill {{badge_class}} {{active_class}}" data-category="{{slug}}" title="{{label}}">
		<span class="dashicons {{icon}} aips-pill-icon"></span>
		<span class="aips-pill-label">{{short_label}}</span>
		<span class="aips-pill-count">{{count}}</span>
	</button>
</script>

<!-- Empty state for categories summary -->
<script type="text/html" id="aips-tmpl-ai-errors-no-categories">
	<span class="aips-text-muted">{{message}}</span>
</script>

<!-- Empty state template -->
<script type="text/html" id="aips-tmpl-ai-errors-empty">
	<tr>
		<td colspan="6" class="aips-list-table-empty">
			<p class="aips-empty-state-text">{{message}}</p>
		</td>
	</tr>
</script>

<!-- Loading spinner template -->
<script type="text/html" id="aips-tmpl-ai-errors-loading">
	<tr>
		<td colspan="6" class="aips-list-table-empty">
			<span class="dashicons dashicons-update aips-spin"></span>
			<span>{{message}}</span>
		</td>
	</tr>
</script>

<!-- Error notification template -->
<script type="text/html" id="aips-tmpl-ai-errors-error">
	<tr>
		<td colspan="6" class="aips-list-table-empty">
			<p class="aips-empty-state-text aips-text-danger">{{message}}</p>
		</td>
	</tr>
</script>

<!-- Pagination active button template -->
<script type="text/html" id="aips-tmpl-ai-errors-pagination-btn">
	<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-ai-errors-page-btn" data-page="{{page}}">
		{{label}}
	</button>
</script>

<!-- Pagination disabled button template -->
<script type="text/html" id="aips-tmpl-ai-errors-pagination-disabled">
	<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" disabled>
		{{label}}
	</button>
</script>

<!-- Pagination info indicator template -->
<script type="text/html" id="aips-tmpl-ai-errors-pagination-info">
	<span class="aips-pagination-info">{{current}} / {{total}}</span>
</script>
