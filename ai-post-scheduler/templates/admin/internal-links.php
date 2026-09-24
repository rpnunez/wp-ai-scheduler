<?php
/**
 * Internal Links Admin Page
 *
 * Displays the Internal Links management interface, including indexing
 * status and a paginated table of link suggestions.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// $summary and $service are injected by AIPS_Internal_Links_Controller::render_page()
$indexing     = isset($summary['indexing']) ? $summary['indexing'] : array();
$link_counts  = isset($summary['link_counts']) ? $summary['link_counts'] : array();

$total_posts  = isset($indexing['total_posts']) ? (int) $indexing['total_posts'] : 0;
$indexed      = isset($indexing['indexed']) ? (int) $indexing['indexed'] : 0;
$unindexed    = isset($indexing['unindexed']) ? (int) $indexing['unindexed'] : 0;
$percent      = isset($indexing['percent']) ? (int) $indexing['percent'] : 0;

$count_pending  = isset($link_counts['pending'])  ? (int) $link_counts['pending']  : 0;
$count_accepted = isset($link_counts['accepted']) ? (int) $link_counts['accepted'] : 0;
$count_rejected = isset($link_counts['rejected']) ? (int) $link_counts['rejected'] : 0;
$count_inserted = isset($link_counts['inserted']) ? (int) $link_counts['inserted'] : 0;
?>

		<!-- Status Cards -->
		<div class="aips-stats-grid">

			<div class="aips-stat-card">
				<div class="aips-stat-header">
					<span class="aips-stat-label"><?php esc_html_e('Posts Indexed', 'ai-post-scheduler'); ?></span>
					<span class="dashicons dashicons-admin-links aips-stat-icon" aria-hidden="true"></span>
				</div>
				<div class="aips-stat-value-wrap">
					<span class="aips-stat-value" id="aips-stat-indexed"><?php echo esc_html((string) $indexed); ?></span>
					<span class="aips-stat-total">/ <span id="aips-stat-total"><?php echo esc_html((string) $total_posts); ?></span></span>
				</div>
				<div class="aips-progress-bar">
					<div id="aips-index-progress-bar" class="aips-progress-fill" data-progress="<?php echo esc_attr((string) $percent); ?>" style="width:<?php echo esc_attr((string) $percent); ?>%;"></div>
				</div>
			</div>

			<div class="aips-stat-card">
				<div class="aips-stat-header">
					<span class="aips-stat-label"><?php esc_html_e('Pending', 'ai-post-scheduler'); ?></span>
					<span class="dashicons dashicons-clock aips-stat-icon" aria-hidden="true"></span>
				</div>
				<div class="aips-stat-value-wrap">
					<span class="aips-stat-value aips-text-warning" id="aips-stat-pending"><?php echo esc_html((string) $count_pending); ?></span>
				</div>
			</div>

			<div class="aips-stat-card">
				<div class="aips-stat-header">
					<span class="aips-stat-label"><?php esc_html_e('Accepted', 'ai-post-scheduler'); ?></span>
					<span class="dashicons dashicons-yes aips-stat-icon" aria-hidden="true"></span>
				</div>
				<div class="aips-stat-value-wrap">
					<span class="aips-stat-value aips-text-success" id="aips-stat-accepted"><?php echo esc_html((string) $count_accepted); ?></span>
				</div>
			</div>

			<div class="aips-stat-card">
				<div class="aips-stat-header">
					<span class="aips-stat-label"><?php esc_html_e('Rejected', 'ai-post-scheduler'); ?></span>
					<span class="dashicons dashicons-no aips-stat-icon" aria-hidden="true"></span>
				</div>
				<div class="aips-stat-value-wrap">
					<span class="aips-stat-value aips-text-danger" id="aips-stat-rejected"><?php echo esc_html((string) $count_rejected); ?></span>
				</div>
			</div>

		</div><!-- /.aips-stats-grid -->

		<!-- Tabs & Content Panel -->
		<div class="aips-content-panel aips-panel-with-tabs">
			<div class="aips-tab-nav aips-panel-tab-nav">
				<a href="#suggestions" class="aips-tab-link active" data-tab="suggestions"><?php esc_html_e('Suggestions', 'ai-post-scheduler'); ?></a>
				<a href="#generate" class="aips-tab-link" data-tab="generate"><?php esc_html_e('Generate for Post', 'ai-post-scheduler'); ?></a>
			</div>

			<!-- Suggestions Tab -->
			<div id="suggestions-tab" class="aips-tab-content active" role="tabpanel" aria-hidden="false">
				<!-- Filter Bar -->
				<div class="aips-filter-bar">
					<div class="aips-filter-left">
						<label class="screen-reader-text" for="aips-il-status-filter"><?php esc_html_e('Filter by status:', 'ai-post-scheduler'); ?></label>
						<select id="aips-il-status-filter" class="aips-form-select">
							<option value=""><?php esc_html_e('All Statuses', 'ai-post-scheduler'); ?></option>
							<option value="pending"><?php esc_html_e('Pending', 'ai-post-scheduler'); ?></option>
							<option value="accepted"><?php esc_html_e('Accepted', 'ai-post-scheduler'); ?></option>
							<option value="rejected"><?php esc_html_e('Rejected', 'ai-post-scheduler'); ?></option>
							<option value="inserted"><?php esc_html_e('Inserted', 'ai-post-scheduler'); ?></option>
							<option value="reverted"><?php esc_html_e('Undone', 'ai-post-scheduler'); ?></option>
						</select>
						<label class="screen-reader-text" for="aips-il-origin-filter"><?php esc_html_e('Filter by direction:', 'ai-post-scheduler'); ?></label>
						<select id="aips-il-origin-filter" class="aips-form-select">
							<option value=""><?php esc_html_e('All Directions', 'ai-post-scheduler'); ?></option>
							<option value="outbound"><?php esc_html_e('Outbound (from a post)', 'ai-post-scheduler'); ?></option>
							<option value="inbound"><?php esc_html_e('Inbound (to an orphan)', 'ai-post-scheduler'); ?></option>
						</select>
					</div>
					<div class="aips-filter-right">
						<label class="screen-reader-text" for="aips-il-search"><?php esc_html_e('Search posts:', 'ai-post-scheduler'); ?></label>
						<input type="search" id="aips-il-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search by post title…', 'ai-post-scheduler'); ?>">
						<button type="button" id="aips-il-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost aips-hidden" title="<?php esc_attr_e('Clear', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Clear', 'ai-post-scheduler'); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></button>
					</div>
				</div>

				<div class="aips-panel-body no-padding">
					<table class="aips-table aips-internal-links-table" id="aips-suggestions-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Source Post', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Target Post', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Similarity', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Anchor Text', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
								<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody id="aips-suggestions-tbody">
							<tr class="aips-table-loading">
								<td colspan="6">
									<span class="spinner is-active"></span>
									<?php esc_html_e('Loading…', 'ai-post-scheduler'); ?>
								</td>
							</tr>
						</tbody>
					</table>

					<!-- Pagination -->
					<div class="aips-panel-toolbar aips-il-pagination-toolbar aips-hidden" id="aips-il-pagination">
						<div class="aips-pagination" id="aips-il-page-controls"></div>
					</div>
				</div><!-- /.aips-panel-body -->
			</div><!-- /#suggestions-tab -->

			<!-- Generate for Post Tab -->
			<div id="generate-tab" class="aips-tab-content aips-hidden" role="tabpanel" aria-hidden="true">
				<div class="aips-panel-body">
					<h3 class="aips-panel-title"><?php esc_html_e('Generate Suggestions for a Post', 'ai-post-scheduler'); ?></h3>
					<p class="description"><?php esc_html_e('Enter a post ID to generate internal link suggestions for it. The post will be indexed if it has not been indexed yet.', 'ai-post-scheduler'); ?></p>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="aips-gen-post-id"><?php esc_html_e('Post ID', 'ai-post-scheduler'); ?></label>
								</th>
								<td>
									<input type="number" id="aips-gen-post-id" class="aips-form-input aips-input-sm" min="1" placeholder="<?php esc_attr_e('e.g. 42', 'ai-post-scheduler'); ?>">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="aips-gen-max-suggestions"><?php esc_html_e('Max Suggestions', 'ai-post-scheduler'); ?></label>
								</th>
								<td>
									<input type="number" id="aips-gen-max-suggestions" class="aips-form-input aips-input-xs" min="1" max="20" value="5">
									<p class="description"><?php esc_html_e('Maximum number of link suggestions to generate (1–20).', 'ai-post-scheduler'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="aips-gen-threshold"><?php esc_html_e('Similarity Threshold', 'ai-post-scheduler'); ?></label>
								</th>
								<td>
									<input type="number" id="aips-gen-threshold" class="aips-form-input aips-input-xs" min="0" max="1" step="0.05" value="0.70">
									<p class="description"><?php esc_html_e('Minimum cosine similarity score (0–1). Higher values return fewer but more relevant results.', 'ai-post-scheduler'); ?></p>
								</td>
							</tr>
						</tbody>
					</table>

					<div id="aips-gen-feedback" class="aips-notice aips-hidden"></div>

					<div class="aips-btn-group aips-mt-4">
						<button type="button" id="aips-generate-for-post-btn" class="aips-btn aips-btn-primary">
							<span class="dashicons dashicons-search" aria-hidden="true"></span>
							<?php esc_html_e('Generate Suggestions', 'ai-post-scheduler'); ?>
						</button>
						<button type="button" id="aips-reindex-post-btn" class="aips-btn aips-btn-secondary">
							<span class="dashicons dashicons-update" aria-hidden="true"></span>
							<?php esc_html_e('Re-index Post', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>
			</div><!-- /#generate-tab -->
		</div><!-- /.aips-panel-with-tabs -->

<!-- Insert Link Modal -->
<div id="aips-insert-modal" class="aips-modal-backdrop aips-hidden" role="dialog" aria-modal="true" aria-labelledby="aips-insert-modal-title">
	<div class="aips-modal aips-modal-lg">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title" id="aips-insert-modal-title"><?php esc_html_e('Insert Link', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</div>
		<div class="aips-modal-body no-padding">

			<!-- Suggested Links Section -->
			<div class="aips-modal-section">
				<h3 class="aips-modal-section-title">
					<?php esc_html_e('Suggested Links', 'ai-post-scheduler'); ?>
				</h3>
				<div id="aips-insert-suggestions-list">
					<span class="spinner is-active"></span>
				</div>
			</div>

			<!-- AI Insertion Locations Section (hidden until Insert is clicked) -->
			<div id="aips-insert-locations-section" class="aips-modal-section aips-hidden">
				<h3 class="aips-modal-section-title">
					<?php esc_html_e('Insertion Locations', 'ai-post-scheduler'); ?>
					<span id="aips-insert-locations-spinner" class="spinner"></span>
				</h3>
				<div id="aips-insert-locations-list"></div>
			</div>

			<!-- Post Content Preview Section -->
			<div class="aips-modal-section">
				<h3 class="aips-modal-section-title">
					<?php esc_html_e('Post Content Preview', 'ai-post-scheduler'); ?>
					<span id="aips-insert-post-title" class="aips-text-muted"></span>
				</h3>
				<p class="description"><?php esc_html_e('Applied links are highlighted. Hover over a highlighted link to edit or remove it.', 'ai-post-scheduler'); ?></p>
				<div id="aips-insert-post-content-wrap" class="aips-content-preview-box">
					<div class="aips-modal-content-body">
						<span class="spinner is-active"></span>
					</div>
				</div>
			</div>

		</div><!-- /.aips-modal-body -->
		<div class="aips-modal-footer">
			<span id="aips-pending-count" class="aips-text-muted"></span>
			<div class="aips-btn-group">
				<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Close', 'ai-post-scheduler'); ?></button>
				<button type="button" id="aips-update-post-btn" class="aips-btn aips-btn-primary" disabled>
					<span class="dashicons dashicons-saved" aria-hidden="true"></span>
					<?php esc_html_e('Update Post with Inserted Links', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>
</div><!-- /#aips-insert-modal -->

<!-- =====================================================================
     AIPS.Templates engine blocks for admin-internal-links.js
     ===================================================================== -->

<!-- Loading row for the suggestions table -->
<script type="text/html" id="aips-tmpl-il-tbody-loading">
<tr class="aips-table-loading"><td colspan="6"><span class="spinner is-active"></span>{{message}}</td></tr>
</script>

<!-- Generic message row (empty state / error) -->
<script type="text/html" id="aips-tmpl-il-tbody-message">
<tr><td colspan="6" class="aips-table-empty">{{message}}</td></tr>
</script>

<!-- Linked post title (source or target column) -->
<script type="text/html" id="aips-tmpl-il-post-link">
<a href="{{url}}" target="_blank" rel="noopener noreferrer">{{title}}</a>
</script>

<!-- Action buttons: pending status -->
<script type="text/html" id="aips-tmpl-il-actions-pending">
<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-il-accept-btn" data-id="{{id}}"><span class="dashicons dashicons-yes" aria-hidden="true"></span><span class="screen-reader-text">{{acceptLabel}}</span></button> <button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger aips-il-reject-btn" data-id="{{id}}"><span class="dashicons dashicons-no" aria-hidden="true"></span><span class="screen-reader-text">{{rejectLabel}}</span></button>
</script>

<!-- Action button: accepted status — Insert Link -->
<script type="text/html" id="aips-tmpl-il-actions-accepted">
<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-il-insert-btn" data-id="{{id}}" title="{{insertLabel}}"><span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span><span class="screen-reader-text">{{insertLabel}}</span></button>
</script>

<!-- Action buttons: edit anchor + delete (shown for all statuses) -->
<script type="text/html" id="aips-tmpl-il-actions-edit-delete">
 <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-il-edit-anchor-btn" data-id="{{id}}" data-anchor="{{anchor}}"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">{{editLabel}}</span></button> <button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger aips-il-delete-btn" data-id="{{id}}"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">{{deleteLabel}}</span></button>
</script>

<!-- Full suggestion table row -->
<script type="text/html" id="aips-tmpl-il-suggestion-row">
<tr data-id="{{id}}">
	<td class="cell-primary">{{source}}</td>
	<td>{{target}}</td>
	<td>{{score}}</td>
	<td class="aips-il-anchor-cell">{{anchor}}</td>
	<td><span class="aips-badge {{statusClass}}">{{statusLabel}}</span></td>
	<td class="cell-actions">{{actions}}</td>
</tr>
</script>

<!-- Inbound suggestion badge -->
<script type="text/html" id="aips-tmpl-il-origin-badge">
<span class="aips-badge aips-badge-info" title="<?php esc_attr_e('Suggested from the Link Report to give this post inbound links', 'ai-post-scheduler'); ?>"><?php esc_html_e('Inbound', 'ai-post-scheduler'); ?></span>
</script>

<!-- Single pagination button -->
<script type="text/html" id="aips-tmpl-il-page-btn">
<button type="button" class="aips-btn aips-btn-sm {{classes}} aips-page-btn" data-page="{{page}}">{{label}}</button>
</script>

<!-- Indexed / total stat display -->
<script type="text/html" id="aips-tmpl-il-indexed-stat">
{{indexed}} <span class="aips-text-muted">/ {{total}}</span>
</script>

<!-- Error notice paragraph -->
<script type="text/html" id="aips-tmpl-il-notice-error">
<p class="aips-notice aips-notice-error">{{message}}</p>
</script>

<!-- Muted info paragraph -->
<script type="text/html" id="aips-tmpl-il-notice-muted">
<p class="aips-text-muted">{{message}}</p>
</script>

<!-- Spinner only (used for loading states in modals) -->
<script type="text/html" id="aips-tmpl-il-spinner">
<span class="spinner is-active"></span>
</script>

<!-- Insert modal: single accepted suggestion item -->
<script type="text/html" id="aips-tmpl-il-insert-suggestion">
<li class="aips-il-suggestion-item" data-suggestion-id="{{suggestionId}}">
	<div class="aips-il-suggestion-row">
		<div class="aips-il-suggestion-info">
			<strong class="aips-il-suggestion-title" title="{{title}}">{{title}}</strong>
			<span class="aips-il-suggestion-meta">{{anchorLabel}}: {{anchor}} &nbsp;|&nbsp; {{score}}</span>
			{{targetLinkHtml}}
		</div>
		<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-il-modal-insert-btn" data-id="{{suggestionId}}">
			<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span> {{insertBtn}}
		</button>
	</div>
	<div class="aips-il-inline-locations aips-hidden">
		<div class="aips-il-inline-header">
			<h4 class="aips-il-inline-title">{{insertionLocationsLabel}}</h4>
			<div class="aips-il-inline-status">
				<span class="aips-il-inline-count"></span>
				<span class="aips-il-inline-spinner spinner"></span>
			</div>
		</div>
		<div class="aips-il-inline-locations-list"></div>
	</div>
</li>
</script>

<!-- Insert modal: target post link -->
<script type="text/html" id="aips-tmpl-il-insert-target-link">
<br><a href="{{url}}" target="_blank" rel="noopener noreferrer" class="aips-il-target-link">{{url}}</a>
</script>

<!-- Insert location card -->
<script type="text/html" id="aips-tmpl-il-location-card">
<div class="aips-insert-location-card">
	<div class="aips-insert-location-header">
		<div class="aips-insert-location-info">
			<p class="aips-insert-location-title">{{optionLabel}} {{num}}</p>
			{{reasonHtml}}
			<div>
				<p class="aips-insert-location-label">{{withLinkLabel}}</p>
				<blockquote class="aips-insert-location-preview">{{preview}}</blockquote>
			</div>
		</div>
		<div class="aips-insert-location-action">
			<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-il-apply-location-btn" data-suggestion-id="{{suggestionId}}" data-match="{{matchRaw}}" data-replace="{{replaceRaw}}">{{applyBtn}}</button>
		</div>
	</div>
</div>
</script>

<!-- Insert location: optional reason line -->
<script type="text/html" id="aips-tmpl-il-location-reason">
<p class="aips-insert-location-reason"><strong>{{reasonLabel}}:</strong> {{reason}}</p>
</script>

<!-- No insertion locations found -->
<script type="text/html" id="aips-tmpl-il-no-locations">
<p class="aips-text-muted">{{zeroReturned}}</p>
<p class="aips-text-muted">{{noLocations}}</p>
</script>

<!-- Suggestions list wrapper -->
<script type="text/html" id="aips-tmpl-il-suggestions-list">
<ul class="aips-suggestions-list">{{items}}</ul>
</script>

<!-- Button: Start Indexing (restored after AJAX) -->
<script type="text/html" id="aips-tmpl-il-btn-start-indexing">
<span class="dashicons dashicons-database-import" aria-hidden="true"></span> {{label}}
</script>

<!-- Button: Generate Suggestions (restored after AJAX) -->
<script type="text/html" id="aips-tmpl-il-btn-generate">
<span class="dashicons dashicons-search" aria-hidden="true"></span> {{label}}
</script>

<!-- Button: Re-index Post (restored after AJAX) -->
<script type="text/html" id="aips-tmpl-il-btn-reindex">
<span class="dashicons dashicons-update" aria-hidden="true"></span> {{label}}
</script>

<!-- Preview insertion: green-highlighted link with inline hover actions -->
<script type="text/html" id="aips-tmpl-il-preview-insertion">
<span class="aips-il-preview-insertion" data-suggestion-id="{{suggestionId}}" data-match="{{matchEsc}}">{{before}}<mark class="aips-il-preview-link">{{anchor}}</mark>{{after}}<span class="aips-il-preview-actions aips-hidden"> <button type="button" class="aips-il-preview-edit-btn aips-btn aips-btn-xs aips-btn-secondary" data-suggestion-id="{{suggestionId}}"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">{{editLabel}}</span></button> <button type="button" class="aips-il-preview-undo-btn aips-btn aips-btn-xs aips-btn-ghost aips-btn-danger" data-suggestion-id="{{suggestionId}}"><span class="dashicons dashicons-undo" aria-hidden="true"></span><span class="screen-reader-text">{{undoLabel}}</span></button></span></span>
</script>

<!-- Edit Anchor Text Modal -->
<div id="aips-anchor-modal" class="aips-modal-backdrop aips-hidden" role="dialog" aria-modal="true" aria-labelledby="aips-anchor-modal-title">
	<div class="aips-modal">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title" id="aips-anchor-modal-title"><?php esc_html_e('Edit Anchor Text', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</div>
		<div class="aips-modal-body">
			<input type="hidden" id="aips-anchor-modal-id">
			<input type="hidden" id="aips-anchor-modal-context" value="table">
			<label for="aips-anchor-modal-text"><?php esc_html_e('Anchor Text', 'ai-post-scheduler'); ?></label>
			<input type="text" id="aips-anchor-modal-text" class="aips-form-input aips-w-full">
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" id="aips-anchor-modal-save" class="aips-btn aips-btn-primary"><?php esc_html_e('Save', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>
