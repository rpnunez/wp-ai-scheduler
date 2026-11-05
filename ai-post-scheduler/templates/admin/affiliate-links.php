<?php
/**
 * Affiliate Links Admin Partial Template
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aips_all_categories = get_categories( array( 'hide_empty' => false ) );
?>

<!-- Sub Navigation View Switcher -->
<div class="aips-subnav-bar" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; justify-content: space-between;">
	<div class="aips-btn-group" role="tablist">
		<button type="button" class="aips-btn aips-btn-sm aips-afl-view-tab aips-btn-primary" data-view="offers">
			<span class="dashicons dashicons-money-alt" style="margin-right:4px;"></span><?php esc_html_e( 'Affiliate Offers', 'ai-post-scheduler' ); ?>
		</button>
		<button type="button" class="aips-btn aips-btn-sm aips-afl-view-tab aips-btn-secondary" data-view="suggestions">
			<span class="dashicons dashicons-lightbulb" style="margin-right:4px;"></span><?php esc_html_e( 'Retroactive Suggestions Queue', 'ai-post-scheduler' ); ?>
		</button>
	</div>
	<div id="aips-afl-suggestions-top-actions" style="display:none;">
		<button type="button" id="aips-afl-scan-posts-btn" class="aips-btn aips-btn-sm aips-btn-secondary">
			<span class="dashicons dashicons-search" style="margin-right:4px;"></span><?php esc_html_e( 'Scan Published Posts', 'ai-post-scheduler' ); ?>
		</button>
	</div>
</div>

<!-- View 1: Offers Panel -->
<div id="aips-afl-offers-view" class="aips-content-panel">
	<!-- Filter Bar -->
	<div class="aips-filter-bar">
		<div class="aips-filter-left">
			<label class="screen-reader-text" for="aips-afl-search"><?php esc_html_e( 'Search mappings:', 'ai-post-scheduler' ); ?></label>
			<input type="search" id="aips-afl-search" class="aips-form-input aips-min-w-240" placeholder="<?php esc_attr_e( 'Search by tag, label or keyword…', 'ai-post-scheduler' ); ?>">
			<button type="button" id="aips-afl-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" title="<?php esc_attr_e('Clear', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Clear', 'ai-post-scheduler'); ?>" style="display:none;"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></button>
		</div>
	</div>

	<div class="aips-panel-body no-padding">
		<table class="aips-table" id="aips-afl-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tag / Offer', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Label', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Affiliate URL', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Keywords / Guidance', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'CTA Position', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'AI Injection', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Enabled', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ai-post-scheduler' ); ?></th>
				</tr>
			</thead>
			<tbody id="aips-afl-tbody">
				<tr class="aips-table-loading">
					<td colspan="8">
						<span class="spinner is-active aips-spinner-inline"></span>
						<?php esc_html_e( 'Loading…', 'ai-post-scheduler' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div id="aips-afl-empty-state" class="aips-empty-state" style="display:none;padding:48px 20px;">
		<div class="dashicons dashicons-money-alt aips-empty-state-icon" aria-hidden="true"></div>
		<h3 class="aips-empty-state-title"><?php esc_html_e( 'No Affiliate Mappings Yet', 'ai-post-scheduler' ); ?></h3>
		<p class="aips-empty-state-description"><?php esc_html_e( 'Map post tags, categories, and keywords to affiliate links to automatically inject recommendations and CTAs into generated posts.', 'ai-post-scheduler' ); ?></p>
		<div class="aips-empty-state-actions">
			<button type="button" class="aips-btn aips-btn-primary" id="aips-afl-empty-add-btn">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add Mapping', 'ai-post-scheduler' ); ?>
			</button>
		</div>
	</div>

	<div id="aips-afl-search-empty-state" class="aips-empty-state" style="display:none;padding:48px 20px;">
		<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
		<h3 class="aips-empty-state-title"><?php esc_html_e( 'No Mappings Found', 'ai-post-scheduler' ); ?></h3>
		<p class="aips-empty-state-description"><?php esc_html_e( 'No affiliate mappings match your search term.', 'ai-post-scheduler' ); ?></p>
		<div class="aips-empty-state-actions">
			<button type="button" class="aips-btn aips-btn-secondary" id="aips-afl-clear-search-btn">
				<span class="dashicons dashicons-dismiss"></span>
				<?php esc_html_e( 'Clear Search', 'ai-post-scheduler' ); ?>
			</button>
		</div>
	</div>

	<!-- Pagination -->
	<div class="aips-panel-footer" id="aips-afl-pagination" style="display:none;">
		<div class="aips-pagination">
			<button type="button" id="aips-afl-prev" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e( '&laquo; Prev', 'ai-post-scheduler' ); ?></button>
			<span id="aips-afl-page-info" class="aips-page-info"></span>
			<button type="button" id="aips-afl-next" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e( 'Next &raquo;', 'ai-post-scheduler' ); ?></button>
		</div>
	</div>
</div>

<!-- View 2: Suggestions Panel -->
<div id="aips-afl-suggestions-view" class="aips-content-panel" style="display:none;">
	<div class="aips-filter-bar">
		<div class="aips-filter-left" style="display:flex;gap:10px;align-items:center;">
			<label for="aips-sug-filter-status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'ai-post-scheduler' ); ?></label>
			<select id="aips-sug-filter-status" class="aips-form-select">
				<option value="pending"><?php esc_html_e( 'Pending Review', 'ai-post-scheduler' ); ?></option>
				<option value="applied"><?php esc_html_e( 'Applied', 'ai-post-scheduler' ); ?></option>
				<option value="rejected"><?php esc_html_e( 'Rejected', 'ai-post-scheduler' ); ?></option>
				<option value=""><?php esc_html_e( 'All Statuses', 'ai-post-scheduler' ); ?></option>
			</select>

			<div class="aips-bulk-actions" style="margin-left: 15px; display:inline-flex; gap:6px;">
				<select id="aips-sug-bulk-action" class="aips-form-select">
					<option value=""><?php esc_html_e( 'Bulk Actions', 'ai-post-scheduler' ); ?></option>
					<option value="apply"><?php esc_html_e( 'Approve & Inject', 'ai-post-scheduler' ); ?></option>
					<option value="reject"><?php esc_html_e( 'Reject', 'ai-post-scheduler' ); ?></option>
				</select>
				<button type="button" id="aips-sug-bulk-apply-btn" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e( 'Apply', 'ai-post-scheduler' ); ?></button>
			</div>
		</div>
		<div class="aips-filter-right">
			<button type="button" id="aips-sug-refresh-btn" class="aips-btn aips-btn-sm aips-btn-ghost" title="<?php esc_attr_e( 'Refresh list', 'ai-post-scheduler' ); ?>">
				<span class="dashicons dashicons-update"></span>
			</button>
		</div>
	</div>

	<div class="aips-panel-body no-padding">
		<table class="aips-table" id="aips-sug-table">
			<thead>
				<tr>
					<th style="width:36px;"><input type="checkbox" id="aips-sug-select-all" aria-label="<?php esc_attr_e( 'Select all', 'ai-post-scheduler' ); ?>"></th>
					<th><?php esc_html_e( 'Post Title', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Matched Offer', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Anchor Text / Mode', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Context Preview', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ai-post-scheduler' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ai-post-scheduler' ); ?></th>
				</tr>
			</thead>
			<tbody id="aips-sug-tbody">
				<tr class="aips-table-loading">
					<td colspan="7">
						<span class="spinner is-active aips-spinner-inline"></span>
						<?php esc_html_e( 'Loading suggestions…', 'ai-post-scheduler' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div id="aips-sug-empty-state" class="aips-empty-state" style="display:none;padding:48px 20px;">
		<div class="dashicons dashicons-yes-alt aips-empty-state-icon" aria-hidden="true"></div>
		<h3 class="aips-empty-state-title"><?php esc_html_e( 'No Suggestions in Queue', 'ai-post-scheduler' ); ?></h3>
		<p class="aips-empty-state-description"><?php esc_html_e( 'Click "Scan Published Posts" to analyze existing articles and stage affiliate link opportunities.', 'ai-post-scheduler' ); ?></p>
	</div>

	<!-- Suggestions Pagination -->
	<div class="aips-panel-footer" id="aips-sug-pagination" style="display:none;">
		<div class="aips-pagination">
			<button type="button" id="aips-sug-prev" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e( '&laquo; Prev', 'ai-post-scheduler' ); ?></button>
			<span id="aips-sug-page-info" class="aips-page-info"></span>
			<button type="button" id="aips-sug-next" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e( 'Next &raquo;', 'ai-post-scheduler' ); ?></button>
		</div>
	</div>
</div>

<!-- Create / Edit Modal -->
<div id="aips-afl-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-afl-modal-title">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 id="aips-afl-modal-title" class="aips-modal-title"><?php esc_html_e( 'Affiliate Link Mapping', 'ai-post-scheduler' ); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-post-scheduler' ); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<input type="hidden" id="aips-afl-id" value="">
			<input type="hidden" id="aips-afl-original-url" value="">

			<!-- Basic Fields -->
			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-tag"><?php esc_html_e( 'Tag / Offer Key', 'ai-post-scheduler' ); ?> <span class="required">*</span></label>
				<input type="text" id="aips-afl-tag" class="aips-form-input" placeholder="<?php esc_attr_e( 'e.g. VPS, Namecheap, Hosting', 'ai-post-scheduler' ); ?>">
				<p class="aips-form-help"><?php esc_html_e( 'Primary tag name to match (case-insensitive).', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-label"><?php esc_html_e( 'Label / Provider Name', 'ai-post-scheduler' ); ?></label>
				<input type="text" id="aips-afl-label" class="aips-form-input" placeholder="<?php esc_attr_e( 'e.g. Namecheap VPS & Hosting', 'ai-post-scheduler' ); ?>">
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-url"><?php esc_html_e( 'Affiliate Destination URL', 'ai-post-scheduler' ); ?> <span class="required">*</span></label>
				<input type="url" id="aips-afl-url" class="aips-form-input" placeholder="https://…">
				<p class="aips-form-help"><?php esc_html_e( 'Full tracking / referral URL including affiliate parameters.', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-keywords"><?php esc_html_e( 'Keywords / Semantic Matches', 'ai-post-scheduler' ); ?></label>
				<input type="text" id="aips-afl-keywords" class="aips-form-input" placeholder="<?php esc_attr_e( 'e.g. web hosting, cloud server, vps hosting, cheap domains', 'ai-post-scheduler' ); ?>">
				<p class="aips-form-help"><?php esc_html_e( 'Comma-separated keywords used for relevance scoring and semantic offer matching.', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-categories"><?php esc_html_e( 'Target Categories', 'ai-post-scheduler' ); ?></label>
				<select id="aips-afl-categories" class="aips-form-select" multiple size="4" style="height:auto; min-height:85px; width:100%;">
					<?php foreach ( $aips_all_categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="aips-form-help"><?php esc_html_e( 'Select categories to restrict this offer to (hold Ctrl / Cmd to multi-select). Leave unselected to apply to all categories.', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-prompt-guidance"><?php esc_html_e( 'Prompt Guidance / Instructions', 'ai-post-scheduler' ); ?></label>
				<textarea id="aips-afl-prompt-guidance" class="aips-form-input" rows="3" placeholder="<?php esc_attr_e( 'e.g. Naturally highlight their 99.9% uptime guarantee and 24/7 support when recommending.', 'ai-post-scheduler' ); ?>"></textarea>
				<p class="aips-form-help"><?php esc_html_e( 'Specific talking points instructed to the AI model during generation-time prompt assembly.', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label aips-toggle-label">
					<input type="checkbox" id="aips-afl-enabled" checked>
					<?php esc_html_e( 'Enabled', 'ai-post-scheduler' ); ?>
				</label>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label aips-toggle-label">
					<input type="checkbox" id="aips-afl-fallback">
					<?php esc_html_e( 'Use as Global Fallback Offer', 'ai-post-scheduler' ); ?>
				</label>
				<p class="aips-form-help"><?php esc_html_e( 'If enabled, this offer can be chosen when no specific tag or category match is found.', 'ai-post-scheduler' ); ?></p>
			</div>

			<hr class="aips-divider">

			<!-- CTA Configuration -->
			<h3 class="aips-heading-sm aips-mb-md"><?php esc_html_e( 'CTA Block Configuration (Optional Fallback / Append)', 'ai-post-scheduler' ); ?></h3>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-cta-html"><?php esc_html_e( 'CTA HTML', 'ai-post-scheduler' ); ?></label>
				<textarea id="aips-afl-cta-html" class="aips-form-input" rows="3" placeholder="<?php esc_attr_e( '<p>Check out <a href="{{affiliate_url}}">this deal</a>.</p>', 'ai-post-scheduler' ); ?>"></textarea>
				<p class="aips-form-help"><?php esc_html_e( 'Use {{affiliate_url}} as the placeholder for the affiliate URL.', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-cta-position"><?php esc_html_e( 'Insertion Position', 'ai-post-scheduler' ); ?></label>
				<select id="aips-afl-cta-position" class="aips-form-select">
					<option value="append"><?php esc_html_e( 'Append to post', 'ai-post-scheduler' ); ?></option>
					<option value="prepend"><?php esc_html_e( 'Prepend to post', 'ai-post-scheduler' ); ?></option>
					<option value="after_heading"><?php esc_html_e( 'After heading', 'ai-post-scheduler' ); ?></option>
					<option value="after_text"><?php esc_html_e( 'After text snippet', 'ai-post-scheduler' ); ?></option>
				</select>
			</div>

			<div class="aips-form-group" id="aips-afl-heading-group" style="display:none;">
				<label class="aips-form-label" for="aips-afl-cta-heading"><?php esc_html_e( 'Heading Text to Match', 'ai-post-scheduler' ); ?></label>
				<input type="text" id="aips-afl-cta-heading" class="aips-form-input" placeholder="<?php esc_attr_e( 'e.g. Getting Started', 'ai-post-scheduler' ); ?>">
				<p class="aips-form-help"><?php esc_html_e( 'CTA will be inserted after the first heading that contains this text (case-insensitive).', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group" id="aips-afl-text-group" style="display:none;">
				<label class="aips-form-label" for="aips-afl-cta-match-text"><?php esc_html_e( 'Text Snippet to Match', 'ai-post-scheduler' ); ?></label>
				<input type="text" id="aips-afl-cta-match-text" class="aips-form-input" placeholder="<?php esc_attr_e( 'e.g. In conclusion', 'ai-post-scheduler' ); ?>">
				<p class="aips-form-help"><?php esc_html_e( 'CTA will be inserted after each occurrence of this text (case-insensitive).', 'ai-post-scheduler' ); ?></p>
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label" for="aips-afl-max-insertions"><?php esc_html_e( 'Max Insertions per Post', 'ai-post-scheduler' ); ?></label>
				<input type="number" id="aips-afl-max-insertions" class="aips-form-input aips-w-xs" value="1" min="1" max="20">
			</div>

			<div class="aips-form-group">
				<label class="aips-form-label aips-toggle-label">
					<input type="checkbox" id="aips-afl-ai-injection" checked>
					<?php esc_html_e( 'Use AI in-content injection', 'ai-post-scheduler' ); ?>
				</label>
				<p class="aips-form-help"><?php esc_html_e( 'Instruct the AI prompt builder to embed natural in-text hyperlinks during generation.', 'ai-post-scheduler' ); ?></p>
			</div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="aips-btn aips-btn-ghost aips-modal-close"><?php esc_html_e( 'Cancel', 'ai-post-scheduler' ); ?></button>
			<button type="button" id="aips-afl-save-btn" class="aips-btn aips-btn-primary"><?php esc_html_e( 'Save Mapping', 'ai-post-scheduler' ); ?></button>
		</div>
	</div>
</div>

<!-- URL Change Replacement Confirmation Modal -->
<div id="aips-afl-replace-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-afl-replace-title">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content aips-modal-large" style="max-width:550px;">
		<div class="aips-modal-header">
			<h2 id="aips-afl-replace-title" class="aips-modal-title"><?php esc_html_e( 'Update Existing Published Posts?', 'ai-post-scheduler' ); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-post-scheduler' ); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<p id="aips-afl-replace-message" style="font-size:14px;line-height:1.6;"></p>
			<div class="aips-notice aips-notice-info" style="margin:15px 0 0 0;padding:12px;background:#f0f6fc;border-left:4px solid #2271b1;">
				<p style="margin:0;font-size:13px;"><?php esc_html_e( 'A background process will update published post content and the link index without interrupting site visitors.', 'ai-post-scheduler' ); ?></p>
			</div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" id="aips-afl-replace-skip-btn" class="aips-btn aips-btn-secondary"><?php esc_html_e( 'No, Save Offer Only', 'ai-post-scheduler' ); ?></button>
			<button type="button" id="aips-afl-replace-confirm-btn" class="aips-btn aips-btn-primary"><?php esc_html_e( 'Yes, Update All Posts', 'ai-post-scheduler' ); ?></button>
		</div>
	</div>
</div>

<script type="text/javascript">
(function($) {
	'use strict';

	var aflState = {
		activeView: 'offers',
		page: 1,
		perPage: 20,
		totalPages: 1,
		search: '',
	};

	var sugState = {
		page: 1,
		perPage: 20,
		totalPages: 1,
		status: 'pending',
	};

	var pendingSavePayload = null;
	var nonce = '<?php echo esc_js( wp_create_nonce( 'aips_ajax_nonce' ) ); ?>';

	function aflRequest(action, data, cb) {
		$.post(ajaxurl, $.extend({ action: action, nonce: nonce }, data), cb).fail(function() {
			alert('<?php echo esc_js( __( 'Request failed. Please try again.', 'ai-post-scheduler' ) ); ?>');
		});
	}

	// View switching
	$(document).on('click', '.aips-afl-view-tab', function() {
		var view = $(this).data('view');
		$('.aips-afl-view-tab').removeClass('aips-btn-primary').addClass('aips-btn-secondary');
		$(this).removeClass('aips-btn-secondary').addClass('aips-btn-primary');
		aflState.activeView = view;

		if (view === 'offers') {
			$('#aips-afl-offers-view').show();
			$('#aips-afl-suggestions-view').hide();
			$('#aips-afl-suggestions-top-actions').hide();
			$('#aips-afl-add-btn').show();
			loadList();
		} else {
			$('#aips-afl-offers-view').hide();
			$('#aips-afl-suggestions-view').show();
			$('#aips-afl-suggestions-top-actions').show();
			$('#aips-afl-add-btn').hide();
			loadSuggestions();
		}
	});

	function loadList() {
		$('#aips-afl-tbody').html('<tr><td colspan="8"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;vertical-align:middle;"></span><?php echo esc_js( __( 'Loading…', 'ai-post-scheduler' ) ); ?></td></tr>');
		aflRequest('aips_affiliate_links_list', { page: aflState.page, per_page: aflState.perPage, search: aflState.search }, function(res) {
			if (!res.success) { return; }
			var d = res.data;
			aflState.totalPages = d.total_pages || 1;
			renderRows(d.items || []);
			renderPagination(d.page, d.total_pages, d.total);
		});
	}

	var positionLabels = {
		append: '<?php echo esc_js( __( 'Append', 'ai-post-scheduler' ) ); ?>',
		prepend: '<?php echo esc_js( __( 'Prepend', 'ai-post-scheduler' ) ); ?>',
		after_heading: '<?php echo esc_js( __( 'After Heading', 'ai-post-scheduler' ) ); ?>',
		after_text: '<?php echo esc_js( __( 'After Text', 'ai-post-scheduler' ) ); ?>',
	};

	function renderRows(items) {
		var tbody = $('#aips-afl-tbody');
		var table = $('#aips-afl-table');
		var emptyState = $('#aips-afl-empty-state');
		var searchEmptyState = $('#aips-afl-search-empty-state');

		if (!items.length) {
			table.hide();
			tbody.empty();
			if (aflState.search && aflState.search.trim().length > 0) {
				emptyState.hide();
				searchEmptyState.show();
			} else {
				searchEmptyState.hide();
				emptyState.show();
			}
			return;
		}

		table.show();
		emptyState.hide();
		searchEmptyState.hide();

		var rows = items.map(function(item) {
			var enabledToggle = '<label class="aips-toggle" title="<?php echo esc_js( __( 'Toggle enabled', 'ai-post-scheduler' ) ); ?>">'
				+ '<input type="checkbox" class="aips-afl-toggle" data-id="' + item.id + '"' + (item.enabled == 1 ? ' checked' : '') + ' aria-label="<?php echo esc_js( __( 'Toggle enabled', 'ai-post-scheduler' ) ); ?>">'
				+ '<span class="screen-reader-text"><?php echo esc_js( __( 'Toggle enabled', 'ai-post-scheduler' ) ); ?></span>'
				+ '<span class="aips-toggle-slider"></span></label>';

			var aiIcon = item.use_ai_injection == 1
				? '<span class="dashicons dashicons-yes-alt aips-text-success" title="<?php echo esc_js( __( 'AI injection enabled', 'ai-post-scheduler' ) ); ?>"></span>'
				: '<span class="aips-text-muted">—</span>';

			var fallbackBadge = item.is_global_fallback == 1
				? ' <span class="aips-badge aips-badge-info" style="font-size:10px;padding:2px 5px;background:#e7f3fe;color:#185abc;border-radius:3px;margin-left:4px;"><?php echo esc_js( __( 'Fallback', 'ai-post-scheduler' ) ); ?></span>'
				: '';

			var keywordsSnippet = item.keywords ? item.keywords : '—';
			if (keywordsSnippet.length > 25) {
				keywordsSnippet = keywordsSnippet.substring(0, 25) + '…';
			}

			var shortUrl = item.affiliate_url.length > 35 ? item.affiliate_url.substring(0, 35) + '…' : item.affiliate_url;
			return '<tr data-id="' + item.id + '">'
				+ '<td><code>' + $('<span>').text('#' + item.tag).html() + '</code>' + fallbackBadge + '</td>'
				+ '<td><strong>' + $('<span>').text(item.label || item.tag).html() + '</strong></td>'
				+ '<td><a href="' + $('<span>').text(item.affiliate_url).html() + '" target="_blank" rel="noopener">' + $('<span>').text(shortUrl).html() + '</a></td>'
				+ '<td class="aips-text-muted" style="font-size:12px;">' + $('<span>').text(keywordsSnippet).html() + '</td>'
				+ '<td>' + (positionLabels[item.cta_position] || item.cta_position) + '</td>'
				+ '<td style="text-align:center;">' + aiIcon + '</td>'
				+ '<td>' + enabledToggle + '</td>'
				+ '<td>'
					+ '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-afl-edit-btn" data-id="' + item.id + '"><?php echo esc_js( __( 'Edit', 'ai-post-scheduler' ) ); ?></button> '
					+ '<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger aips-afl-delete-btn" data-id="' + item.id + '"><?php echo esc_js( __( 'Delete', 'ai-post-scheduler' ) ); ?></button>'
				+ '</td>'
			+ '</tr>';
		});
		tbody.html(rows.join(''));
	}

	function renderPagination(page, totalPages, total) {
		var pag = $('#aips-afl-pagination');
		if (totalPages <= 1) { pag.hide(); return; }
		pag.show();
		$('#aips-afl-page-info').text('<?php echo esc_js( __( 'Page', 'ai-post-scheduler' ) ); ?> ' + page + ' <?php echo esc_js( __( 'of', 'ai-post-scheduler' ) ); ?> ' + totalPages + ' (' + total + ')');
		$('#aips-afl-prev').prop('disabled', page <= 1);
		$('#aips-afl-next').prop('disabled', page >= totalPages);
	}

	function openModal(item) {
		var isEdit = !!item;
		$('#aips-afl-modal-title').text(isEdit ? '<?php echo esc_js( __( 'Edit Mapping', 'ai-post-scheduler' ) ); ?>' : '<?php echo esc_js( __( 'Add Mapping', 'ai-post-scheduler' ) ); ?>');
		$('#aips-afl-id').val(isEdit ? item.id : '');
		$('#aips-afl-original-url').val(isEdit ? item.affiliate_url : '');
		$('#aips-afl-tag').val(isEdit ? item.tag : '');
		$('#aips-afl-label').val(isEdit ? item.label : '');
		$('#aips-afl-url').val(isEdit ? item.affiliate_url : '');
		$('#aips-afl-keywords').val(isEdit ? (item.keywords || '') : '');
		$('#aips-afl-prompt-guidance').val(isEdit ? (item.prompt_guidance || '') : '');
		$('#aips-afl-fallback').prop('checked', isEdit && item.is_global_fallback == 1);
		$('#aips-afl-enabled').prop('checked', !isEdit || item.enabled == 1);

		// Categories
		$('#aips-afl-categories option').prop('selected', false);
		if (isEdit && item.category_ids) {
			var catIds = [];
			try {
				catIds = JSON.parse(item.category_ids || '[]');
			} catch (e) {
				catIds = (item.category_ids || '').split(',').map(function(s) { return s.trim(); }).filter(Boolean);
			}
			catIds.forEach(function(cid) {
				$('#aips-afl-categories option[value="' + cid + '"]').prop('selected', true);
			});
		}

		$('#aips-afl-cta-html').val(isEdit ? item.cta_html : '');
		$('#aips-afl-cta-position').val(isEdit ? item.cta_position : 'append').trigger('change');
		$('#aips-afl-cta-heading').val(isEdit ? item.cta_heading : '');
		$('#aips-afl-cta-match-text').val(isEdit ? item.cta_match_text : '');
		$('#aips-afl-max-insertions').val(isEdit ? item.cta_max_insertions : 1);
		$('#aips-afl-ai-injection').prop('checked', !isEdit || item.use_ai_injection == 1);
		$('#aips-afl-modal').show();
		$('#aips-afl-tag').focus();
	}

	function closeModal() {
		$('#aips-afl-modal').hide();
		$('#aips-afl-replace-modal').hide();
	}

	$(document).on('change', '#aips-afl-cta-position', function() {
		var val = $(this).val();
		$('#aips-afl-heading-group').toggle(val === 'after_heading');
		$('#aips-afl-text-group').toggle(val === 'after_text');
	});

	$(document).on('click', '#aips-afl-add-btn, #aips-afl-empty-add-btn', function() { openModal(null); });
	$(document).on('click', '.aips-modal-close, .aips-modal-overlay, .aips-modal-backdrop', closeModal);

	$(document).on('click', '.aips-afl-edit-btn', function() {
		var id = $(this).data('id');
		aflRequest('aips_affiliate_links_get', { id: id }, function(res) {
			if (res.success) { openModal(res.data.item); }
		});
	});

	$(document).on('click', '.aips-afl-delete-btn', function() {
		if (!confirm('<?php echo esc_js( __( 'Delete this mapping? This cannot be undone.', 'ai-post-scheduler' ) ); ?>')) { return; }
		var id = $(this).data('id');
		aflRequest('aips_affiliate_links_delete', { id: id }, function(res) {
			if (res.success) { loadList(); }
			else { alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Delete failed.', 'ai-post-scheduler' ) ); ?>'); }
		});
	});

	$(document).on('change', '.aips-afl-toggle', function() {
		var id      = $(this).data('id');
		var enabled = $(this).is(':checked') ? 1 : 0;
		aflRequest('aips_affiliate_links_toggle', { id: id, enabled: enabled }, function(res) {
			if (!res.success) { loadList(); }
		});
	});

	function executeSaveOffer(data) {
		var btn = $('#aips-afl-save-btn').prop('disabled', true).text('<?php echo esc_js( __( 'Saving…', 'ai-post-scheduler' ) ); ?>');
		var action = data.id ? 'aips_affiliate_links_update' : 'aips_affiliate_links_create';
		aflRequest(action, data, function(res) {
			btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save Mapping', 'ai-post-scheduler' ) ); ?>');
			if (res.success) {
				closeModal();
				loadList();
			} else {
				alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Save failed.', 'ai-post-scheduler' ) ); ?>');
			}
		});
	}

	$(document).on('click', '#aips-afl-save-btn', function() {
		var id = $('#aips-afl-id').val();
		var originalUrl = $('#aips-afl-original-url').val();
		var selectedCategories = $('#aips-afl-categories').val() || [];

		var data = {
			tag:                $('#aips-afl-tag').val(),
			label:              $('#aips-afl-label').val(),
			affiliate_url:      $('#aips-afl-url').val(),
			keywords:           $('#aips-afl-keywords').val(),
			prompt_guidance:    $('#aips-afl-prompt-guidance').val(),
			category_ids:       selectedCategories,
			is_global_fallback: $('#aips-afl-fallback').is(':checked') ? 1 : 0,
			enabled:            $('#aips-afl-enabled').is(':checked') ? 1 : 0,
			cta_html:           $('#aips-afl-cta-html').val(),
			cta_position:       $('#aips-afl-cta-position').val(),
			cta_heading:        $('#aips-afl-cta-heading').val(),
			cta_match_text:     $('#aips-afl-cta-match-text').val(),
			cta_max_insertions: $('#aips-afl-max-insertions').val(),
			use_ai_injection:   $('#aips-afl-ai-injection').is(':checked') ? 1 : 0,
		};

		if (!data.tag) { alert('<?php echo esc_js( __( 'Tag is required.', 'ai-post-scheduler' ) ); ?>'); return; }
		if (!data.affiliate_url) { alert('<?php echo esc_js( __( 'Affiliate URL is required.', 'ai-post-scheduler' ) ); ?>'); return; }

		if (id) {
			data.id = id;
			// Check if URL changed and check affected posts count
			if (originalUrl && originalUrl.trim() !== data.affiliate_url.trim()) {
				pendingSavePayload = { data: data, old_url: originalUrl, new_url: data.affiliate_url };
				aflRequest('aips_affiliate_links_count_affected_posts', { url: originalUrl }, function(res) {
					if (res.success && res.data.count > 0) {
						var count = res.data.count;
						var msg = '<?php echo esc_js( __( 'This affiliate URL is currently present in %d published post(s). Would you like to automatically replace the old URL with the new URL across all affected posts via background process?', 'ai-post-scheduler' ) ); ?>'.replace('%d', count);
						$('#aips-afl-replace-message').text(msg);
						$('#aips-afl-replace-modal').show();
						return;
					}
					executeSaveOffer(data);
				});
				return;
			}
		}

		executeSaveOffer(data);
	});

	// URL Replacement Modal actions
	$(document).on('click', '#aips-afl-replace-skip-btn', function() {
		if (pendingSavePayload && pendingSavePayload.data) {
			executeSaveOffer(pendingSavePayload.data);
			pendingSavePayload = null;
		}
		$('#aips-afl-replace-modal').hide();
	});

	$(document).on('click', '#aips-afl-replace-confirm-btn', function() {
		if (!pendingSavePayload) { return; }
		var payload = pendingSavePayload;
		var btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Starting Batch…', 'ai-post-scheduler' ) ); ?>');

		aflRequest('aips_affiliate_url_replace_start', { old_url: payload.old_url, new_url: payload.new_url }, function(res) {
			btn.prop('disabled', false).text('<?php echo esc_js( __( 'Yes, Update All Posts', 'ai-post-scheduler' ) ); ?>');
			$('#aips-afl-replace-modal').hide();
			executeSaveOffer(payload.data);
			pendingSavePayload = null;
			alert('<?php echo esc_js( __( 'Background URL update process started! Check progress in Automations.', 'ai-post-scheduler' ) ); ?>');
		});
	});

	// Suggestions Queue Logic
	function loadSuggestions() {
		$('#aips-sug-tbody').html('<tr><td colspan="7"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;vertical-align:middle;"></span><?php echo esc_js( __( 'Loading suggestions…', 'ai-post-scheduler' ) ); ?></td></tr>');
		aflRequest('aips_affiliate_suggestions_list', {
			page: sugState.page,
			per_page: sugState.perPage,
			status: $('#aips-sug-filter-status').val()
		}, function(res) {
			if (!res.success) { return; }
			var d = res.data;
			sugState.totalPages = d.total_pages || 1;
			renderSuggestionRows(d.items || []);
			renderSuggestionPagination(d.page, d.total_pages, d.total);
		});
	}

	function renderSuggestionRows(items) {
		var tbody = $('#aips-sug-tbody');
		var table = $('#aips-sug-table');
		var emptyState = $('#aips-sug-empty-state');

		if (!items.length) {
			table.hide();
			tbody.empty();
			emptyState.show();
			return;
		}

		table.show();
		emptyState.hide();

		var rows = items.map(function(sug) {
			var postTitle = sug.post_title || ('Post #' + sug.post_id);
			var editUrl = 'post.php?post=' + sug.post_id + '&action=edit';

			var statusBadges = {
				pending: '<span class="aips-badge aips-badge-warning" style="background:#fef3d6;color:#8f6b00;padding:3px 6px;border-radius:3px;font-size:11px;"><?php echo esc_js( __( 'Pending', 'ai-post-scheduler' ) ); ?></span>',
				applied: '<span class="aips-badge aips-badge-success" style="background:#e6f4ea;color:#137333;padding:3px 6px;border-radius:3px;font-size:11px;"><?php echo esc_js( __( 'Applied', 'ai-post-scheduler' ) ); ?></span>',
				rejected: '<span class="aips-badge aips-badge-secondary" style="background:#f1f3f4;color:#5f6368;padding:3px 6px;border-radius:3px;font-size:11px;"><?php echo esc_js( __( 'Rejected', 'ai-post-scheduler' ) ); ?></span>'
			};

			var actionsHtml = '';
			if (sug.status === 'pending') {
				actionsHtml = '<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-sug-apply-btn" data-id="' + sug.id + '"><?php echo esc_js( __( 'Approve', 'ai-post-scheduler' ) ); ?></button> '
					+ '<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger aips-sug-reject-btn" data-id="' + sug.id + '"><?php echo esc_js( __( 'Reject', 'ai-post-scheduler' ) ); ?></button>';
			} else {
				actionsHtml = '<span class="aips-text-muted">—</span>';
			}

			var targetUrl = sug.affiliate_url || sug.target_url || '';
			var snippetText = sug.before_snippet || sug.after_snippet || sug.context_snippet || '';
			var snippet = snippetText ? ('<code style="font-size:11px;display:block;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + $('<span>').text(snippetText).html() + '</code>') : '—';
			var shortUrl = targetUrl.length > 30 ? targetUrl.substring(0, 30) + '…' : targetUrl;

			return '<tr data-id="' + sug.id + '">'
				+ '<td><input type="checkbox" class="aips-sug-cb" value="' + sug.id + '"></td>'
				+ '<td><a href="' + editUrl + '" target="_blank"><strong>' + $('<span>').text(postTitle).html() + '</strong></a></td>'
				+ '<td><a href="' + $('<span>').text(targetUrl).html() + '" target="_blank" rel="noopener">' + $('<span>').text(shortUrl).html() + '</a></td>'
				+ '<td><code>' + $('<span>').text(sug.anchor_text || sug.match_type || sug.insertion_mode || '—').html() + '</code></td>'
				+ '<td>' + snippet + '</td>'
				+ '<td>' + (statusBadges[sug.status] || sug.status) + '</td>'
				+ '<td>' + actionsHtml + '</td>'
			+ '</tr>';
		});
		tbody.html(rows.join(''));
	}

	function renderSuggestionPagination(page, totalPages, total) {
		var pag = $('#aips-sug-pagination');
		if (totalPages <= 1) { pag.hide(); return; }
		pag.show();
		$('#aips-sug-page-info').text('<?php echo esc_js( __( 'Page', 'ai-post-scheduler' ) ); ?> ' + page + ' <?php echo esc_js( __( 'of', 'ai-post-scheduler' ) ); ?> ' + totalPages + ' (' + total + ')');
		$('#aips-sug-prev').prop('disabled', page <= 1);
		$('#aips-sug-next').prop('disabled', page >= totalPages);
	}

	$(document).on('change', '#aips-sug-filter-status', function() {
		sugState.page = 1;
		loadSuggestions();
	});

	$(document).on('click', '#aips-sug-refresh-btn', function() {
		loadSuggestions();
	});

	$(document).on('click', '#aips-sug-prev', function() { if (sugState.page > 1) { sugState.page--; loadSuggestions(); } });
	$(document).on('click', '#aips-sug-next', function() { if (sugState.page < sugState.totalPages) { sugState.page++; loadSuggestions(); } });

	$(document).on('change', '#aips-sug-select-all', function() {
		$('.aips-sug-cb').prop('checked', $(this).is(':checked'));
	});

	$(document).on('click', '.aips-sug-apply-btn', function() {
		var id = $(this).data('id');
		var btn = $(this).prop('disabled', true);
		aflRequest('aips_affiliate_suggestions_apply', { id: id, suggestion_id: id }, function(res) {
			if (res.success) { loadSuggestions(); }
			else { alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Failed to apply suggestion.', 'ai-post-scheduler' ) ); ?>'); btn.prop('disabled', false); }
		});
	});

	$(document).on('click', '.aips-sug-reject-btn', function() {
		var id = $(this).data('id');
		var btn = $(this).prop('disabled', true);
		aflRequest('aips_affiliate_suggestions_reject', { id: id, suggestion_id: id }, function(res) {
			if (res.success) { loadSuggestions(); }
			else { alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Failed to reject suggestion.', 'ai-post-scheduler' ) ); ?>'); btn.prop('disabled', false); }
		});
	});

	$(document).on('click', '#aips-sug-bulk-apply-btn', function() {
		var action = $('#aips-sug-bulk-action').val();
		if (!action) { return; }
		var ids = [];
		$('.aips-sug-cb:checked').each(function() { ids.push($(this).val()); });
		if (!ids.length) { alert('<?php echo esc_js( __( 'Please select at least one suggestion.', 'ai-post-scheduler' ) ); ?>'); return; }

		aflRequest('aips_affiliate_suggestions_bulk', { bulk_action: action, ids: ids, suggestion_ids: ids }, function(res) {
			if (res.success) {
				$('#aips-sug-select-all').prop('checked', false);
				loadSuggestions();
			} else {
				alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Bulk operation failed.', 'ai-post-scheduler' ) ); ?>');
			}
		});
	});

	$(document).on('click', '#aips-afl-scan-posts-btn', function() {
		var btn = $(this).prop('disabled', true).text('<?php echo esc_js( __( 'Starting Scan…', 'ai-post-scheduler' ) ); ?>');
		aflRequest('aips_affiliate_scan_start', {}, function(res) {
			btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-right:4px;"></span><?php echo esc_js( __( 'Scan Published Posts', 'ai-post-scheduler' ) ); ?>');
			if (res.success) {
				alert('<?php echo esc_js( __( 'Retroactive affiliate scan started in the background! As matching posts are analyzed, suggestions will populate here.', 'ai-post-scheduler' ) ); ?>');
				loadSuggestions();
			} else {
				alert(res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Could not start scan.', 'ai-post-scheduler' ) ); ?>');
			}
		});
	});

	// Pagination
	$(document).on('click', '#aips-afl-prev', function() { if (aflState.page > 1) { aflState.page--; loadList(); } });
	$(document).on('click', '#aips-afl-next', function() { if (aflState.page < aflState.totalPages) { aflState.page++; loadList(); } });

	// Search
	var searchTimer;
	$(document).on('input', '#aips-afl-search', function() {
		var val = $(this).val();
		$('#aips-afl-search-clear').toggle(val.length > 0);
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function() { aflState.search = val; aflState.page = 1; loadList(); }, 350);
	});
	$(document).on('click', '#aips-afl-search-clear, #aips-afl-clear-search-btn', function() {
		$('#aips-afl-search').val('');
		$('#aips-afl-search-clear').hide();
		aflState.search = '';
		aflState.page   = 1;
		loadList();
	});

	// Escape key closes modal
	$(document).on('keydown', function(e) { if (e.key === 'Escape') { closeModal(); } });

	// Initial load
	loadList();

}(jQuery));
</script>
