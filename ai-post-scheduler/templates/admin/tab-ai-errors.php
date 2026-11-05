<?php
/**
 * Admin Template: Diagnostics > AI Errors Tab
 *
 * Displays a unified, searchable, filterable log of AI call failures and errors.
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
$initial_search    = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
?>

<div class="aips-tab-pane aips-tab-ai-errors" id="aips-tab-ai-errors-pane">
	<!-- Tab Header -->
	<div class="aips-tab-header">
		<div>
			<h2 class="aips-tab-title"><?php esc_html_e('AI Call Errors & Failures', 'ai-post-scheduler'); ?></h2>
			<p class="aips-tab-desc"><?php esc_html_e('Unified error log for tracking AI generation errors, API timeouts, rate limit hits, and provider failure details across all generation runs.', 'ai-post-scheduler'); ?></p>
		</div>
	</div>

	<!-- Filter Bar -->
	<div class="aips-filter-bar">
		<div class="aips-filter-left">
			<!-- Date Range -->
			<div class="aips-filter-group aips-filter-dates">
				<label for="aips-ai-errors-from"><?php esc_html_e('From:', 'ai-post-scheduler'); ?></label>
				<input type="date" id="aips-ai-errors-from" class="aips-form-input aips-filter-date" value="<?php echo esc_attr($initial_date_from); ?>" />
				<label for="aips-ai-errors-to"><?php esc_html_e('To:', 'ai-post-scheduler'); ?></label>
				<input type="date" id="aips-ai-errors-to" class="aips-form-input aips-filter-date" value="<?php echo esc_attr($initial_date_to); ?>" />
			</div>

			<!-- Component Filter -->
			<div class="aips-filter-group">
				<select id="aips-ai-errors-component" class="aips-form-select">
					<option value=""><?php esc_html_e('All Components', 'ai-post-scheduler'); ?></option>
					<option value="content" <?php selected($initial_component, 'content'); ?>><?php esc_html_e('Content', 'ai-post-scheduler'); ?></option>
					<option value="title" <?php selected($initial_component, 'title'); ?>><?php esc_html_e('Title', 'ai-post-scheduler'); ?></option>
					<option value="excerpt" <?php selected($initial_component, 'excerpt'); ?>><?php esc_html_e('Excerpt', 'ai-post-scheduler'); ?></option>
					<option value="featured_image" <?php selected($initial_component, 'featured_image'); ?>><?php esc_html_e('Featured Image Prompt', 'ai-post-scheduler'); ?></option>
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
		<div id="aips-ai-errors-pagination-bottom" class="aips-pagination"></div>
	</div>
</div>

<!-- AI Error Details Modal -->
<div id="aips-ai-error-modal" class="aips-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="aips-ai-error-modal-title">
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
			<div id="aips-ai-error-modal-loading" class="aips-loading-center" style="display: none;">
				<span class="dashicons dashicons-update aips-spin"></span>
				<p><?php esc_html_e('Loading error details…', 'ai-post-scheduler'); ?></p>
			</div>

			<!-- Content body -->
			<div id="aips-ai-error-modal-inner">
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

<!-- Empty state template -->
<script type="text/html" id="aips-tmpl-ai-errors-empty">
	<tr>
		<td colspan="5" class="aips-list-table-empty">
			<p class="aips-empty-state-text">{{message}}</p>
		</td>
	</tr>
</script>

<!-- Loading spinner template -->
<script type="text/html" id="aips-tmpl-ai-errors-loading">
	<tr>
		<td colspan="5" class="aips-list-table-empty">
			<span class="dashicons dashicons-update aips-spin"></span>
			<span>{{message}}</span>
		</td>
	</tr>
</script>

<!-- Error notification template -->
<script type="text/html" id="aips-tmpl-ai-errors-error">
	<tr>
		<td colspan="5" class="aips-list-table-empty">
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
