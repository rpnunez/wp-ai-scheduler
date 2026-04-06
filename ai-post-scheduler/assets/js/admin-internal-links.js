/**
 * Internal Links Admin Module
 *
 * Handles all UI interactions for the Internal Links admin page, including
 * suggestion listing, filtering, pagination, status management, and
 * per-post suggestion generation.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

(function ($) {
'use strict';

window.AIPS = window.AIPS || {};
var AIPS = window.AIPS;

/**
 * @namespace AIPS.InternalLinks
 */
AIPS.InternalLinks = {

/** Current page number */
currentPage: 1,

/** Active status filter */
currentStatus: '',

/** Active search string */
currentSearch: '',

/** Per-page row count */
perPage: 20,

/** Debounce timer handle for search input */
_searchTimer: null,

/**
 * Bootstrap the module.
 */
init: function () {
this.bindEvents();
this.loadSuggestions();
},

/**
 * Bind all UI event listeners.
 */
bindEvents: function () {
// Tab navigation
$(document).on('click', '.aips-tab-link', this.onTabClick.bind(this));

// Status filter
$(document).on('change', '#aips-il-status-filter', this.onStatusFilterChange.bind(this));

// Search
$(document).on('input', '#aips-il-search', this.onSearchInput.bind(this));
$(document).on('click', '#aips-il-search-clear', this.onSearchClear.bind(this));

// Index management
$(document).on('click', '#aips-start-indexing-btn', this.onStartIndexingClick.bind(this));
$(document).on('click', '#aips-clear-index-btn', this.onClearIndexClick.bind(this));

// Per-post generation
$(document).on('click', '#aips-generate-for-post-btn', this.onGenerateForPostClick.bind(this));
$(document).on('click', '#aips-reindex-post-btn', this.onReindexPostClick.bind(this));

// Row actions (delegated)
$(document).on('click', '.aips-il-accept-btn', this.onAcceptClick.bind(this));
$(document).on('click', '.aips-il-reject-btn', this.onRejectClick.bind(this));
$(document).on('click', '.aips-il-delete-btn', this.onDeleteClick.bind(this));
$(document).on('click', '.aips-il-edit-anchor-btn', this.onEditAnchorClick.bind(this));

// Anchor modal
$(document).on('click', '.aips-modal-close', this.onModalClose.bind(this));
$(document).on('click', '#aips-anchor-modal-save', this.onAnchorModalSave.bind(this));

// Pagination
$(document).on('click', '.aips-page-btn', this.onPageClick.bind(this));
},

// -----------------------------------------------------------------------
// Event handlers
// -----------------------------------------------------------------------

/**
 * Switch the visible tab panel.
 *
 * @param {Event} e Click event from a `.aips-tab-link` element.
 */
onTabClick: function (e) {
e.preventDefault();
var tab = $(e.currentTarget).data('tab');
$('.aips-tab-link').removeClass('active');
$(e.currentTarget).addClass('active');
$('.aips-tab-content').hide().attr('aria-hidden', 'true');
$('#' + tab + '-tab').show().attr('aria-hidden', 'false');
},

/**
 * Reload the suggestions table when the status filter changes.
 *
 * @param {Event} e Change event from `#aips-il-status-filter`.
 */
onStatusFilterChange: function (e) {
this.currentStatus = $(e.currentTarget).val();
this.currentPage   = 1;
this.loadSuggestions();
},

/**
 * Debounced live search: reload suggestions 400 ms after the user stops typing.
 *
 * @param {Event} e Input event from `#aips-il-search`.
 */
onSearchInput: function (e) {
var self = this;
var val  = $(e.currentTarget).val().trim();

$('#aips-il-search-clear').toggle(val.length > 0);

clearTimeout(self._searchTimer);
self._searchTimer = setTimeout(function () {
self.currentSearch = val;
self.currentPage   = 1;
self.loadSuggestions();
}, 400);
},

/**
 * Clear the search field and reload the suggestions table.
 *
 * @param {Event} e Click event from `#aips-il-search-clear`.
 */
onSearchClear: function (e) {
$('#aips-il-search').val('').trigger('input');
},

/**
 * Start background indexing when the "Start Indexing" button is clicked.
 *
 * @param {Event} e Click event from `#aips-start-indexing-btn`.
 */
onStartIndexingClick: function (e) {
this.startIndexing();
},

/**
 * Ask for confirmation then clear the full index.
 *
 * @param {Event} e Click event from `#aips-clear-index-btn`.
 */
onClearIndexClick: function (e) {
if (!window.confirm(aipsInternalLinksL10n.confirmClearIndex)) {
return;
}
this.clearIndex();
},

/**
 * Generate suggestions for the entered post ID.
 *
 * @param {Event} e Click event from `#aips-generate-for-post-btn`.
 */
onGenerateForPostClick: function (e) {
this.generateForPost();
},

/**
 * Re-index the entered post ID.
 *
 * @param {Event} e Click event from `#aips-reindex-post-btn`.
 */
onReindexPostClick: function (e) {
this.reindexPost();
},

/**
 * Accept a suggestion row.
 *
 * @param {Event} e Click event from an `.aips-il-accept-btn` element.
 */
onAcceptClick: function (e) {
var $btn = $(e.currentTarget);
this.updateStatus($btn.data('id'), 'accepted', $btn.closest('tr'));
},

/**
 * Reject a suggestion row.
 *
 * @param {Event} e Click event from an `.aips-il-reject-btn` element.
 */
onRejectClick: function (e) {
var $btn = $(e.currentTarget);
this.updateStatus($btn.data('id'), 'rejected', $btn.closest('tr'));
},

/**
 * Ask for confirmation then delete a suggestion row.
 *
 * @param {Event} e Click event from an `.aips-il-delete-btn` element.
 */
onDeleteClick: function (e) {
if (!window.confirm(aipsInternalLinksL10n.confirmDelete)) {
return;
}
var $btn = $(e.currentTarget);
this.deleteSuggestion($btn.data('id'), $btn.closest('tr'));
},

/**
 * Open the anchor-text edit modal for the clicked suggestion.
 *
 * @param {Event} e Click event from an `.aips-il-edit-anchor-btn` element.
 */
onEditAnchorClick: function (e) {
var $btn = $(e.currentTarget);
$('#aips-anchor-modal-id').val($btn.data('id'));
$('#aips-anchor-modal-text').val($btn.data('anchor'));
$('#aips-anchor-modal').show();
$('#aips-anchor-modal-text').focus();
},

/**
 * Close any visible modal.
 *
 * @param {Event} e Click event from an `.aips-modal-close` element.
 */
onModalClose: function (e) {
$('#aips-anchor-modal').hide();
},

/**
 * Save the edited anchor text from the modal.
 *
 * @param {Event} e Click event from `#aips-anchor-modal-save`.
 */
onAnchorModalSave: function (e) {
this.saveAnchorText();
},

/**
 * Navigate to the clicked page.
 *
 * @param {Event} e Click event from a `.aips-page-btn` element.
 */
onPageClick: function (e) {
var page = parseInt($(e.currentTarget).data('page'), 10);
if (page && page !== this.currentPage) {
this.currentPage = page;
this.loadSuggestions();
}
},

// -----------------------------------------------------------------------
// Data loading
// -----------------------------------------------------------------------

/**
 * Load and render the suggestions table.
 */
loadSuggestions: function () {
var self   = this;
var $tbody = $('#aips-suggestions-tbody');

$tbody.html(
'<tr class="aips-table-loading"><td colspan="6">' +
'<span class="spinner is-active" style="float:none;margin:0 8px 0 0;vertical-align:middle;"></span>' +
aipsInternalLinksL10n.loading + '</td></tr>'
);

$.post(aipsAjax.ajaxUrl, {
action:   'aips_internal_links_get_suggestions',
nonce:    aipsInternalLinksL10n.nonce,
page:     self.currentPage,
per_page: self.perPage,
status:   self.currentStatus,
search:   self.currentSearch,
}, function (response) {
if (!response.success) {
$tbody.html(
'<tr><td colspan="6" class="aips-table-empty">' +
aipsInternalLinksL10n.errorLoading + '</td></tr>'
);
return;
}

var data = response.data;

if (!data.items || data.items.length === 0) {
$tbody.html(
'<tr><td colspan="6" class="aips-table-empty">' +
aipsInternalLinksL10n.noSuggestions + '</td></tr>'
);
self.renderPagination(0, 0);
return;
}

$tbody.html('');
$.each(data.items, function (i, item) {
$tbody.append(self.renderRow(item));
});

self.renderPagination(data.total, data.total_pages);
}).fail(function () {
$tbody.html(
'<tr><td colspan="6" class="aips-table-empty">' +
aipsInternalLinksL10n.errorLoading + '</td></tr>'
);
});
},

/**
 * Render a single suggestions table row.
 *
 * @param {Object} item Suggestion data object.
 * @return {string} HTML string for the row.
 */
renderRow: function (item) {
var statusLabel = this.getStatusLabel(item.status);
var statusClass = 'aips-status-' + item.status;
var score       = Math.round(parseFloat(item.similarity_score) * 100) + '%';
var anchor      = AIPS.Templates.escape(item.anchor_text || '');

var sourceTitle = AIPS.Templates.escape(item.source_post_title || '(#' + item.source_post_id + ')');
var targetTitle = AIPS.Templates.escape(item.target_post_title || '(#' + item.target_post_id + ')');

var sourceLink = item.source_edit_url
? '<a href="' + AIPS.Templates.escape(item.source_edit_url) + '" target="_blank" rel="noopener noreferrer">' + sourceTitle + '</a>'
: sourceTitle;

var targetLink = item.target_edit_url
? '<a href="' + AIPS.Templates.escape(item.target_edit_url) + '" target="_blank" rel="noopener noreferrer">' + targetTitle + '</a>'
: targetTitle;

var actions = '';
var acceptActionLabel = aipsInternalLinksL10n.acceptAction || 'Accept suggestion';
var rejectActionLabel = aipsInternalLinksL10n.rejectAction || 'Reject suggestion';
if (item.status === 'pending') {
actions +=
'<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-il-accept-btn" data-id="' + item.id + '">' +
'<span class="dashicons dashicons-yes" aria-hidden="true"></span>' +
'<span class="screen-reader-text">' + acceptActionLabel + '</span>' +
'</button> ' +
'<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger aips-il-reject-btn" data-id="' + item.id + '">' +
'<span class="dashicons dashicons-no" aria-hidden="true"></span>' +
'<span class="screen-reader-text">' + rejectActionLabel + '</span>' +
'</button> ';
}

actions +=
'<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-il-edit-anchor-btn" data-id="' + item.id + '" data-anchor="' + anchor + '">' +
'<span class="dashicons dashicons-edit" aria-hidden="true"></span>' +
'<span class="screen-reader-text">Edit anchor text</span>' +
'</button> ' +
'<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger aips-il-delete-btn" data-id="' + item.id + '">' +
'<span class="dashicons dashicons-trash" aria-hidden="true"></span>' +
'<span class="screen-reader-text">Delete suggestion</span>' +
'</button>';

return '<tr data-id="' + item.id + '">' +
'<td class="cell-primary">' + sourceLink + '</td>' +
'<td>' + targetLink + '</td>' +
'<td>' + score + '</td>' +
'<td class="aips-il-anchor-cell">' + anchor + '</td>' +
'<td><span class="aips-badge ' + statusClass + '">' + statusLabel + '</span></td>' +
'<td class="cell-actions">' + actions + '</td>' +
'</tr>';
},

/**
 * Render pagination controls.
 *
 * @param {number} total       Total item count.
 * @param {number} totalPages  Total page count.
 */
renderPagination: function (total, totalPages) {
var self     = this;
var $wrap    = $('#aips-il-page-controls');
var $toolbar = $('#aips-il-pagination');

if (totalPages <= 1) {
$toolbar.hide();
$wrap.html('');
return;
}

$toolbar.show();
var html = '';

if (self.currentPage > 1) {
html += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-page-btn" data-page="' + (self.currentPage - 1) + '">&laquo;</button> ';
}

var start = Math.max(1, self.currentPage - 2);
var end   = Math.min(totalPages, self.currentPage + 2);

for (var p = start; p <= end; p++) {
var active = p === self.currentPage ? ' aips-btn-primary' : ' aips-btn-secondary';
html += '<button type="button" class="aips-btn aips-btn-sm' + active + ' aips-page-btn" data-page="' + p + '">' + p + '</button> ';
}

if (self.currentPage < totalPages) {
html += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-page-btn" data-page="' + (self.currentPage + 1) + '">&raquo;</button>';
}

$wrap.html(html);
},

// -----------------------------------------------------------------------
// Actions
// -----------------------------------------------------------------------

/**
 * Start background indexing of unindexed posts.
 */
startIndexing: function () {
var self = this;
var $btn = $('#aips-start-indexing-btn');

$btn.prop('disabled', true).text(aipsInternalLinksL10n.loading);

$.post(aipsAjax.ajaxUrl, {
action: 'aips_internal_links_start_indexing',
nonce:  aipsInternalLinksL10n.nonce,
}, function (response) {
$btn.prop('disabled', false).html(
'<span class="dashicons dashicons-database-import" aria-hidden="true"></span> ' +
$('<span>').text(self.originalIndexText).html()
);

if (response.success) {
AIPS.Utilities.showToast(response.data.message, 'success');
setTimeout(function () { self.refreshStatus(); }, 2000);
} else {
AIPS.Utilities.showToast(
(response.data && response.data.message) || aipsInternalLinksL10n.indexingNotAvailable,
'error'
);
}
}).fail(function () {
$btn.prop('disabled', false).html(
'<span class="dashicons dashicons-database-import" aria-hidden="true"></span> ' +
$('<span>').text(self.originalIndexText).html()
);
});
},

/**
 * Clear the full embeddings index and all suggestions.
 */
clearIndex: function () {
var self = this;

$.post(aipsAjax.ajaxUrl, {
action: 'aips_internal_links_clear_index',
nonce:  aipsInternalLinksL10n.nonce,
}, function (response) {
if (response.success) {
AIPS.Utilities.showToast(response.data.message, 'success');
self.loadSuggestions();
self.refreshStatus();
} else {
AIPS.Utilities.showToast(
(response.data && response.data.message) || 'Error.',
'error'
);
}
});
},

/**
 * Generate suggestions for a specific post.
 */
generateForPost: function () {
var self        = this;
var postId      = parseInt($('#aips-gen-post-id').val(), 10);
var maxSugg     = parseInt($('#aips-gen-max-suggestions').val(), 10);
var threshold   = parseFloat($('#aips-gen-threshold').val());
var $btn        = $('#aips-generate-for-post-btn');
var $feedback   = $('#aips-gen-feedback');

if (!postId) {
self.showGenerateFeedback(aipsInternalLinksL10n.invalidPostId, 'error');
return;
}

$btn.prop('disabled', true).text(aipsInternalLinksL10n.generating);
$feedback.hide();

$.post(aipsAjax.ajaxUrl, {
action:          'aips_internal_links_generate_suggestions',
nonce:           aipsInternalLinksL10n.nonce,
post_id:         postId,
max_suggestions: maxSugg || 5,
threshold:       threshold || 0.70,
}, function (response) {
$btn.prop('disabled', false).html(
'<span class="dashicons dashicons-search" aria-hidden="true"></span> ' +
$('<span>').text(self.originalGenerateText).html()
);

if (response.success) {
self.showGenerateFeedback(response.data.message, 'success');
self.loadSuggestions();
} else {
self.showGenerateFeedback(
(response.data && response.data.message) || 'Error.',
'error'
);
}
}).fail(function () {
$btn.prop('disabled', false).html(
'<span class="dashicons dashicons-search" aria-hidden="true"></span> ' +
$('<span>').text(self.originalGenerateText).html()
);
self.showGenerateFeedback(aipsInternalLinksL10n.requestFailed, 'error');
});
},

/**
 * Re-index a single post by ID (refresh its stored embedding).
 */
reindexPost: function () {
var self      = this;
var postId    = parseInt($('#aips-gen-post-id').val(), 10);
var $btn      = $('#aips-reindex-post-btn');
var $feedback = $('#aips-gen-feedback');

if (!postId) {
self.showGenerateFeedback(aipsInternalLinksL10n.invalidPostId, 'error');
return;
}

$btn.prop('disabled', true).text(aipsInternalLinksL10n.reindexing);
$feedback.hide();

$.post(aipsAjax.ajaxUrl, {
action:  'aips_internal_links_reindex_post',
nonce:   aipsInternalLinksL10n.nonce,
post_id: postId,
}, function (response) {
$btn.prop('disabled', false).html(
'<span class="dashicons dashicons-update" aria-hidden="true"></span> ' +
$('<span>').text(self.originalReindexText).html()
);

if (response.success) {
self.showGenerateFeedback(response.data.message, 'success');
self.loadSuggestions();
self.refreshStatus();
} else {
self.showGenerateFeedback(
(response.data && response.data.message) || 'Error.',
'error'
);
}
}).fail(function () {
$btn.prop('disabled', false).html(
'<span class="dashicons dashicons-update" aria-hidden="true"></span> ' +
$('<span>').text(self.originalReindexText).html()
);
});
},

/**
 * Update the status of a suggestion row.
 *
 * @param {number} id     Suggestion ID.
 * @param {string} status New status.
 * @param {jQuery} $row   Table row element.
 */
updateStatus: function (id, status, $row) {
var self = this;

$.post(aipsAjax.ajaxUrl, {
action: 'aips_internal_links_update_status',
nonce:  aipsInternalLinksL10n.nonce,
id:     id,
status: status,
}, function (response) {
if (response.success) {
// Reload to reflect updated status and re-render actions
self.loadSuggestions();
self.refreshStatus();
AIPS.Utilities.showToast(aipsInternalLinksL10n.statusUpdated, 'success');
} else {
AIPS.Utilities.showToast(aipsInternalLinksL10n.statusUpdateFailed, 'error');
}
});
},

/**
 * Delete a suggestion row.
 *
 * @param {number} id   Suggestion ID.
 * @param {jQuery} $row Table row element.
 */
deleteSuggestion: function (id, $row) {
var self = this;

$.post(aipsAjax.ajaxUrl, {
action: 'aips_internal_links_delete',
nonce:  aipsInternalLinksL10n.nonce,
id:     id,
}, function (response) {
if (response.success) {
$row.fadeOut(200, function () { $(this).remove(); });
self.refreshStatus();
} else {
AIPS.Utilities.showToast(aipsInternalLinksL10n.errorDeleting, 'error');
}
});
},

/**
 * Save the edited anchor text from the modal.
 */
saveAnchorText: function () {
var self       = this;
var id         = parseInt($('#aips-anchor-modal-id').val(), 10);
var anchorText = $('#aips-anchor-modal-text').val().trim();

if (!id) { return; }

$.post(aipsAjax.ajaxUrl, {
action:      'aips_internal_links_update_anchor',
nonce:       aipsInternalLinksL10n.nonce,
id:          id,
anchor_text: anchorText,
}, function (response) {
$('#aips-anchor-modal').hide();

if (response.success) {
// Update cell in table
$('tr[data-id="' + id + '"] .aips-il-anchor-cell').text(anchorText);
// Update data attribute on edit button
$('tr[data-id="' + id + '"] .aips-il-edit-anchor-btn').data('anchor', anchorText);
AIPS.Utilities.showToast(aipsInternalLinksL10n.anchorUpdated, 'success');
} else {
AIPS.Utilities.showToast(aipsInternalLinksL10n.anchorUpdateFailed, 'error');
}
});
},

// -----------------------------------------------------------------------
// Status refresh
// -----------------------------------------------------------------------

/**
 * Refresh the indexing / status stats without reloading the full table.
 */
refreshStatus: function () {
$.post(aipsAjax.ajaxUrl, {
action: 'aips_internal_links_get_status',
nonce:  aipsInternalLinksL10n.nonce,
}, function (response) {
if (!response.success) { return; }

var idx    = response.data.indexing;
var counts = response.data.link_counts;

if (idx) {
$('#aips-stat-indexed').html(
idx.indexed + ' <span style="font-size:14px;color:#888;">/ ' + idx.total_posts + '</span>'
);
$('#aips-index-progress-bar').css('width', idx.percent + '%');
}

if (counts) {
$('#aips-stat-pending').text(counts.pending || 0);
$('#aips-stat-accepted').text(counts.accepted || 0);
$('#aips-stat-rejected').text(counts.rejected || 0);
}
});
},

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

/**
 * Return the human-readable label for a status slug.
 *
 * @param {string} status Status slug.
 * @return {string} Localized label.
 */
getStatusLabel: function (status) {
var map = {
pending:  aipsInternalLinksL10n.pending,
accepted: aipsInternalLinksL10n.accepted,
rejected: aipsInternalLinksL10n.rejected,
inserted: aipsInternalLinksL10n.inserted,
};
return map[status] || status;
},

/**
 * Show a feedback message in the Generate tab.
 *
 * @param {string} message Message text.
 * @param {string} type    'success' or 'error'.
 */
showGenerateFeedback: function (message, type) {
var $el = $('#aips-gen-feedback');
$el.removeClass('aips-notice-success aips-notice-error')
.addClass('aips-notice-' + type)
.text(message)
.show();
},
};

$(document).ready(function () {
// Store original button texts before any modification
AIPS.InternalLinks.originalGenerateText = $('#aips-generate-for-post-btn').text().trim();
AIPS.InternalLinks.originalReindexText  = $('#aips-reindex-post-btn').text().trim();
AIPS.InternalLinks.originalIndexText    = $('#aips-start-indexing-btn').text().trim();

AIPS.InternalLinks.init();
});

})(jQuery);
