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
<div class="wrap aips-wrap">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Internal Links', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Automatically discover related content and generate internal link suggestions using semantic similarity.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<button type="button" id="aips-start-indexing-btn" class="aips-btn aips-btn-secondary">
						<span class="dashicons dashicons-database-import"></span>
						<?php esc_html_e('Index Posts', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" id="aips-clear-index-btn" class="aips-btn aips-btn-ghost aips-btn-danger">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e('Clear Index', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Status Cards -->
		<div class="aips-stats-row" style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">

			<div class="aips-content-panel" style="flex:1;min-width:200px;">
				<div class="aips-panel-body" style="padding:20px;">
					<p class="aips-stat-label" style="margin:0 0 4px;font-size:12px;text-transform:uppercase;color:#888;"><?php esc_html_e('Posts Indexed', 'ai-post-scheduler'); ?></p>
					<p class="aips-stat-value" style="margin:0;font-size:28px;font-weight:700;" id="aips-stat-indexed"><?php echo esc_html($indexed); ?> <span style="font-size:14px;color:#888;">/ <?php echo esc_html($total_posts); ?></span></p>
					<div style="margin-top:8px;background:#e8e8e8;border-radius:4px;height:6px;overflow:hidden;">
						<div id="aips-index-progress-bar" style="width:<?php echo esc_attr($percent); ?>%;background:#2271b1;height:100%;border-radius:4px;transition:width .3s;"></div>
					</div>
				</div>
			</div>

			<div class="aips-content-panel" style="flex:1;min-width:160px;">
				<div class="aips-panel-body" style="padding:20px;">
					<p class="aips-stat-label" style="margin:0 0 4px;font-size:12px;text-transform:uppercase;color:#888;"><?php esc_html_e('Pending', 'ai-post-scheduler'); ?></p>
					<p class="aips-stat-value" style="margin:0;font-size:28px;font-weight:700;color:#d67500;" id="aips-stat-pending"><?php echo esc_html($count_pending); ?></p>
				</div>
			</div>

			<div class="aips-content-panel" style="flex:1;min-width:160px;">
				<div class="aips-panel-body" style="padding:20px;">
					<p class="aips-stat-label" style="margin:0 0 4px;font-size:12px;text-transform:uppercase;color:#888;"><?php esc_html_e('Accepted', 'ai-post-scheduler'); ?></p>
					<p class="aips-stat-value" style="margin:0;font-size:28px;font-weight:700;color:#00a32a;" id="aips-stat-accepted"><?php echo esc_html($count_accepted); ?></p>
				</div>
			</div>

			<div class="aips-content-panel" style="flex:1;min-width:160px;">
				<div class="aips-panel-body" style="padding:20px;">
					<p class="aips-stat-label" style="margin:0 0 4px;font-size:12px;text-transform:uppercase;color:#888;"><?php esc_html_e('Rejected', 'ai-post-scheduler'); ?></p>
					<p class="aips-stat-value" style="margin:0;font-size:28px;font-weight:700;color:#d63638;" id="aips-stat-rejected"><?php echo esc_html($count_rejected); ?></p>
				</div>
			</div>

		</div><!-- /.aips-stats-row -->

		<!-- Tabs -->
		<div class="aips-tab-nav">
			<a href="#suggestions" class="aips-tab-link active" data-tab="suggestions"><?php esc_html_e('Suggestions', 'ai-post-scheduler'); ?></a>
			<a href="#generate" class="aips-tab-link" data-tab="generate"><?php esc_html_e('Generate for Post', 'ai-post-scheduler'); ?></a>
		</div>

		<!-- Suggestions Tab -->
		<div id="suggestions-tab" class="aips-tab-content active" role="tabpanel" aria-hidden="false">
			<div class="aips-content-panel">

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
						</select>
					</div>
					<div class="aips-filter-right">
						<label class="screen-reader-text" for="aips-il-search"><?php esc_html_e('Search posts:', 'ai-post-scheduler'); ?></label>
						<input type="search" id="aips-il-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search by post title…', 'ai-post-scheduler'); ?>">
						<button type="button" id="aips-il-search-clear" class="aips-btn aips-btn-sm aips-btn-secondary" style="display:none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
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
									<span class="spinner is-active" style="float:none;margin:0 8px 0 0;vertical-align:middle;"></span>
									<?php esc_html_e('Loading…', 'ai-post-scheduler'); ?>
								</td>
							</tr>
						</tbody>
					</table>

					<!-- Pagination -->
					<div class="aips-panel-toolbar" id="aips-il-pagination" style="justify-content:flex-end;padding:10px 16px;display:none;">
						<div class="aips-pagination" id="aips-il-page-controls"></div>
					</div>
				</div><!-- /.aips-panel-body -->
			</div><!-- /.aips-content-panel -->
		</div><!-- /#suggestions-tab -->

		<!-- Generate for Post Tab -->
		<div id="generate-tab" class="aips-tab-content" role="tabpanel" aria-hidden="true" style="display:none;">
			<div class="aips-content-panel">
				<div class="aips-panel-body" style="padding:24px;">
					<h2 style="margin-top:0;"><?php esc_html_e('Generate Suggestions for a Post', 'ai-post-scheduler'); ?></h2>
					<p><?php esc_html_e('Enter a post ID to generate internal link suggestions for it. The post will be indexed if it has not been indexed yet.', 'ai-post-scheduler'); ?></p>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="aips-gen-post-id"><?php esc_html_e('Post ID', 'ai-post-scheduler'); ?></label>
								</th>
								<td>
									<input type="number" id="aips-gen-post-id" class="aips-form-input" min="1" style="width:200px;" placeholder="<?php esc_attr_e('e.g. 42', 'ai-post-scheduler'); ?>">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="aips-gen-max-suggestions"><?php esc_html_e('Max Suggestions', 'ai-post-scheduler'); ?></label>
								</th>
								<td>
									<input type="number" id="aips-gen-max-suggestions" class="aips-form-input" min="1" max="20" value="5" style="width:80px;">
									<p class="description"><?php esc_html_e('Maximum number of link suggestions to generate (1–20).', 'ai-post-scheduler'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="aips-gen-threshold"><?php esc_html_e('Similarity Threshold', 'ai-post-scheduler'); ?></label>
								</th>
								<td>
									<input type="number" id="aips-gen-threshold" class="aips-form-input" min="0" max="1" step="0.05" value="0.70" style="width:100px;">
									<p class="description"><?php esc_html_e('Minimum cosine similarity score (0–1). Higher values return fewer but more relevant results.', 'ai-post-scheduler'); ?></p>
								</td>
							</tr>
						</tbody>
					</table>

					<div id="aips-gen-feedback" class="aips-notice" style="display:none;margin:16px 0;"></div>

					<button type="button" id="aips-generate-for-post-btn" class="aips-btn aips-btn-primary">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e('Generate Suggestions', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" id="aips-reindex-post-btn" class="aips-btn aips-btn-secondary" style="margin-left:8px;">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e('Re-index Post', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div><!-- /#generate-tab -->

	</div><!-- /.aips-page-container -->
</div><!-- /.wrap -->

<!-- Insert Link Modal -->
<div id="aips-insert-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-insert-modal-title">
	<div class="aips-modal-content" style="max-width:860px;width:94%;">
		<div class="aips-modal-header">
			<h2 id="aips-insert-modal-title"><?php esc_html_e('Insert Link', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="aips-modal-body" style="padding:0;">

			<!-- Suggested Links Section -->
			<div style="padding:20px 24px;border-bottom:1px solid #e0e0e0;">
				<h3 style="margin:0 0 12px;font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#555;">
					<?php esc_html_e('Suggested Links', 'ai-post-scheduler'); ?>
				</h3>
				<div id="aips-insert-suggestions-list">
					<span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
				</div>
			</div>

			<!-- AI Insertion Locations Section (hidden until Insert is clicked) -->
			<div id="aips-insert-locations-section" style="display:none;padding:20px 24px;border-bottom:1px solid #e0e0e0;">
				<h3 style="margin:0 0 12px;font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#555;">
					<?php esc_html_e('Insertion Locations', 'ai-post-scheduler'); ?>
					<span id="aips-insert-locations-spinner" class="spinner" style="float:none;vertical-align:middle;margin-left:6px;"></span>
				</h3>
				<div id="aips-insert-locations-list"></div>
			</div>

			<!-- Post Content Preview Section -->
			<div style="padding:20px 24px;">
				<h3 style="margin:0 0 12px;font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#555;">
					<?php esc_html_e('Post Content Preview', 'ai-post-scheduler'); ?>
					<span id="aips-insert-post-title" style="font-weight:400;font-size:13px;color:#777;margin-left:6px;text-transform:none;letter-spacing:0;"></span>
				</h3>
				<p style="margin:0 0 10px;font-size:12px;color:#777;"><?php esc_html_e('Applied links are highlighted in green. Hover over a highlighted link to edit or remove it.', 'ai-post-scheduler'); ?></p>
				<div id="aips-insert-post-content-wrap" style="max-height:320px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:14px 16px;background:#fafafa;">
					<div id="aips-insert-post-content" style="font-size:13px;line-height:1.9;color:#333;white-space:pre-wrap;word-break:break-word;">
						<span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
					</div>
				</div>
			</div>

		</div><!-- /.aips-modal-body -->
		<div class="aips-modal-footer" style="padding:12px 20px;border-top:1px solid #ddd;display:flex;justify-content:space-between;align-items:center;">
			<span id="aips-pending-count" style="font-size:12px;color:#555;"></span>
			<div style="display:flex;gap:8px;">
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
<tr class="aips-table-loading"><td colspan="6"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;vertical-align:middle;"></span>{{message}}</td></tr>
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

<!-- Single pagination button -->
<script type="text/html" id="aips-tmpl-il-page-btn">
<button type="button" class="aips-btn aips-btn-sm {{classes}} aips-page-btn" data-page="{{page}}">{{label}}</button>
</script>

<!-- Indexed / total stat display -->
<script type="text/html" id="aips-tmpl-il-indexed-stat">
{{indexed}} <span style="font-size:14px;color:#888;">/ {{total}}</span>
</script>

<!-- Error notice paragraph -->
<script type="text/html" id="aips-tmpl-il-notice-error">
<p class="aips-notice aips-notice-error">{{message}}</p>
</script>

<!-- Muted info paragraph -->
<script type="text/html" id="aips-tmpl-il-notice-muted">
<p style="color:#888;margin:0;">{{message}}</p>
</script>

<!-- Spinner only (used for loading states in modals) -->
<script type="text/html" id="aips-tmpl-il-spinner">
<span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
</script>

<!-- Insert modal: single accepted suggestion item -->
<script type="text/html" id="aips-tmpl-il-insert-suggestion">
<li class="aips-il-suggestion-item" data-suggestion-id="{{suggestionId}}" style="padding:10px 0 14px;border-bottom:1px solid #f0f0f0;">
	<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
		<div style="flex:1;min-width:0;">
			<strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{title}}">{{title}}</strong>
			<span style="font-size:12px;color:#888;">{{anchorLabel}}: {{anchor}} &nbsp;|&nbsp; {{score}}</span>
			{{targetLinkHtml}}
		</div>
		<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-il-modal-insert-btn" data-id="{{suggestionId}}">
			<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span> {{insertBtn}}
		</button>
	</div>
	<div class="aips-il-inline-locations" style="display:none;margin-top:12px;padding:12px 14px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:4px;">
		<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;">
			<h4 style="margin:0;font-size:13px;font-weight:600;color:#1d2327;">{{insertionLocationsLabel}}</h4>
			<div style="display:flex;align-items:center;gap:8px;">
				<span class="aips-il-inline-count" style="font-size:11px;color:#666;"></span>
				<span class="aips-il-inline-spinner spinner" style="float:none;margin:0;"></span>
			</div>
		</div>
		<div class="aips-il-inline-locations-list"></div>
	</div>
</li>
</script>

<!-- Insert modal: target post link -->
<script type="text/html" id="aips-tmpl-il-insert-target-link">
<br><a href="{{url}}" target="_blank" rel="noopener noreferrer" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;max-width:300px;">{{url}}</a>
</script>

<!-- Insert location card -->
<script type="text/html" id="aips-tmpl-il-location-card">
<div class="aips-insert-location-card" style="border:1px solid #c3c4c7;border-radius:4px;padding:14px 16px;margin-bottom:10px;background:#fff;">
	<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
		<div style="flex:1;min-width:0;">
			<p style="margin:0 0 6px;font-size:13px;font-weight:600;color:#1d2327;">{{optionLabel}} {{num}}</p>
			{{reasonHtml}}
			<div>
				<p style="margin:0 0 3px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#888;">{{withLinkLabel}}</p>
				<blockquote style="margin:0;padding:6px 10px;background:#f0f6fc;border-left:3px solid #2271b1;font-size:12px;color:#444;font-style:italic;">{{preview}}</blockquote>
			</div>
		</div>
		<div style="flex-shrink:0;">
			<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-il-apply-location-btn" data-suggestion-id="{{suggestionId}}" data-match="{{matchRaw}}" data-replace="{{replaceRaw}}">{{applyBtn}}</button>
		</div>
	</div>
</div>
</script>

<!-- Insert location: optional reason line -->
<script type="text/html" id="aips-tmpl-il-location-reason">
<p style="margin:0 0 8px;font-size:12px;color:#555;"><strong>{{reasonLabel}}:</strong> {{reason}}</p>
</script>

<!-- No insertion locations found -->
<script type="text/html" id="aips-tmpl-il-no-locations">
<p style="color:#888;margin:0 0 4px;">{{zeroReturned}}</p>
<p style="color:#888;margin:0;">{{noLocations}}</p>
</script>

<!-- Suggestions list wrapper -->
<script type="text/html" id="aips-tmpl-il-suggestions-list">
<ul style="margin:0;padding:0;list-style:none;">{{items}}</ul>
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
<span class="aips-il-preview-insertion" data-suggestion-id="{{suggestionId}}" data-match="{{matchEsc}}" style="display:inline;position:relative;">{{before}}<mark class="aips-il-preview-link" style="background:rgba(0,163,42,0.12);color:#00a32a;font-weight:600;padding:1px 4px;border-radius:3px;border-bottom:2px solid #00a32a;cursor:default;">{{anchor}}</mark>{{after}}<span class="aips-il-preview-actions" style="display:none;white-space:nowrap;margin-left:4px;vertical-align:middle;"> <button type="button" class="aips-il-preview-edit-btn aips-btn aips-btn-sm aips-btn-secondary" data-suggestion-id="{{suggestionId}}" style="padding:2px 6px;vertical-align:middle;"><span class="dashicons dashicons-edit" aria-hidden="true" style="font-size:13px;width:13px;height:13px;line-height:13px;margin-top:1px;"></span><span class="screen-reader-text">{{editLabel}}</span></button> <button type="button" class="aips-il-preview-undo-btn aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger" data-suggestion-id="{{suggestionId}}" style="padding:2px 6px;vertical-align:middle;"><span class="dashicons dashicons-undo" aria-hidden="true" style="font-size:13px;width:13px;height:13px;line-height:13px;margin-top:1px;"></span><span class="screen-reader-text">{{undoLabel}}</span></button></span></span>
</script>

<!-- Edit Anchor Text Modal -->
<div id="aips-anchor-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-anchor-modal-title">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-anchor-modal-title"><?php esc_html_e('Edit Anchor Text', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="aips-modal-body">
			<input type="hidden" id="aips-anchor-modal-id">
			<input type="hidden" id="aips-anchor-modal-context" value="table">
			<label for="aips-anchor-modal-text"><?php esc_html_e('Anchor Text', 'ai-post-scheduler'); ?></label>
			<input type="text" id="aips-anchor-modal-text" class="aips-form-input" style="width:100%;margin-top:6px;">
		</div>
		<div class="aips-modal-footer" style="padding:12px 20px;border-top:1px solid #ddd;display:flex;justify-content:flex-end;gap:8px;">
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" id="aips-anchor-modal-save" class="aips-btn aips-btn-primary"><?php esc_html_e('Save', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>
