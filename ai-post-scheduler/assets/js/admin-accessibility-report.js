/**
 * Accessibility Report Modal JavaScript
 *
 * Displays the Accessibility Report for a generated post in a stylized modal.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

(function ($) {
'use strict';

window.AIPS = window.AIPS || {};
var AIPS = window.AIPS;

/**
 * AIPS.AccessibilityReport — module for rendering the Accessibility Report modal.
 */
AIPS.AccessibilityReport = {

/**
 * Initialise the module.
 */
init: function () {
this.bindEvents();
},

/**
 * Register event listeners.
 */
bindEvents: function () {
$(document).on('click', '.aips-view-accessibility-report', this.openModal.bind(this));
$(document).on('click', '#aips-accessibility-report-modal .aips-modal-close', this.closeModal.bind(this));
$(document).on('click', '#aips-accessibility-report-modal', this.closeModalOnOverlay.bind(this));
$(document).on('keydown', this.onKeydown.bind(this));
},

/**
 * Open the report modal and fetch report data via AJAX.
 *
 * @param {Event} e Click event.
 */
openModal: function (e) {
e.preventDefault();
e.stopPropagation();

var $btn = $(e.currentTarget);
var postId = parseInt($btn.data('post-id'), 10) || 0;
var historyId = parseInt($btn.data('history-id'), 10) || 0;
var l10n = window.aipsAccessibilityReportL10n;

var $modal = $('#aips-accessibility-report-modal');
var $content = $('#aips-accessibility-report-content');
var $subtitle = $('#aips-accessibility-report-modal-subtitle');
var T = AIPS.Templates;

$subtitle.text('');
$content.html(T.render('aips-tmpl-access-report-loading', {
text: l10n.loading
}));

$modal.fadeIn(200);

this.fetchReport(postId, historyId)
.done(function (response) {
if (!response || !response.success) {
$content.html(T.render('aips-tmpl-access-report-error', {
message: response && response.data && response.data.message
? response.data.message
: l10n.errorLoading
}));
return;
}

if (!response.data || !response.data.report) {
$content.html(T.render('aips-tmpl-access-report-empty', {
message: l10n.noReport
}));
return;
}

if (response.data && response.data.post_title) {
$subtitle.text(response.data.post_title);
}

$content.html(AIPS.AccessibilityReport.renderReport(response.data.report));
})
.fail(function () {
$content.html(T.render('aips-tmpl-access-report-error', {
message: l10n.errorLoading
}));
});
},

/**
 * Fetch the report from the server.
 *
 * @param {number} postId WordPress post ID.
 * @param {number} historyId History container ID.
 * @return {jqXHR} AJAX promise.
 */
fetchReport: function (postId, historyId) {
return $.ajax({
url: aipsAjax.ajaxUrl,
type: 'POST',
data: {
action: 'aips_get_accessibility_report',
nonce: aipsAjax.nonce,
post_id: postId,
history_id: historyId
}
});
},

/**
 * Render the report HTML.
 *
 * @param {Object} report Accessibility report payload.
 * @return {string} HTML string.
 */
renderReport: function (report) {
var l10n = window.aipsAccessibilityReportL10n;
var warnings = (report && report.warnings && Array.isArray(report.warnings)) ? report.warnings : [];
var headingOk = !!(report && report.heading_hierarchy_ok);
var missingAlt = parseInt(report && report.missing_alt_images, 10) || 0;
var longParagraphs = parseInt(report && report.long_paragraphs, 10) || 0;
var score = parseInt(report && report.plain_language_score, 10);
if (isNaN(score)) {
score = null;
}
var target = parseInt(report && report.plain_language_target, 10) || 0;

var badLinkText = parseInt(report && report.non_descriptive_links, 10) || 0;
var badLinkHref = parseInt(report && report.invalid_links, 10) || 0;
var excessiveBreaks = parseInt(report && report.excessive_line_breaks, 10) || 0;
var multipleH1 = parseInt(report && report.multiple_h1_count, 10) || 0;

var status = (warnings.length || !headingOk || missingAlt || longParagraphs || badLinkText || badLinkHref || excessiveBreaks || multipleH1) ? 'warning' : 'success';
var statusBadge = this.renderBadge(status, status === 'success'
? l10n.statusOk
: l10n.statusIssues
);

var cards = '';
cards += this.renderCard({
title: l10n.headings,
icon: 'editor-alignleft',
status: (headingOk && multipleH1 <= 1) ? 'success' : 'warning',
lines: [
(headingOk ? 'No heading level skips detected.' : 'Heading levels skip (e.g. H2 to H4).'),
(multipleH1 > 1 ? ('Multiple H1 headings detected: ' + multipleH1 + '.') : '')
].filter(Boolean)
});

cards += this.renderCard({
title: l10n.images,
icon: 'format-image',
status: missingAlt > 0 ? 'warning' : 'success',
lines: [
(missingAlt > 0 ? (missingAlt + ' image(s) missing alt text.') : 'All images include alt text.')
]
});

cards += this.renderCard({
title: l10n.readability,
icon: 'editor-paragraph',
status: (longParagraphs > 0 || (score !== null && target && score < target)) ? 'warning' : 'success',
lines: [
(longParagraphs > 0 ? (longParagraphs + ' long paragraph(s) detected.') : 'Paragraph length looks good.'),
(score !== null ? ('Plain-language score: ' + score + (target ? (' (target ' + target + '+)') : '') + '.') : 'Plain-language score unavailable.')
]
});

cards += this.renderCard({
title: l10n.links,
icon: 'admin-links',
status: (badLinkText || badLinkHref) ? 'warning' : 'success',
lines: [
(badLinkText ? (badLinkText + ' non-descriptive link(s) (e.g. "click here").') : 'Link text looks descriptive.'),
(badLinkHref ? (badLinkHref + ' invalid link(s) (missing/placeholder href).') : 'All links have valid href.')
]
});

cards += this.renderCard({
title: l10n.formatting,
icon: 'editor-kitchensink',
status: excessiveBreaks ? 'warning' : 'success',
lines: [
(excessiveBreaks ? (excessiveBreaks + ' occurrence(s) of excessive <br> usage.') : 'No excessive line breaks detected.')
]
});

return AIPS.Templates.renderRaw('aips-tmpl-access-report-shell', {
report_title: l10n.reportTitle,
status_badge: statusBadge,
cards_html: cards,
findings_title: l10n.findings,
findings_html: this.renderFindings(warnings, l10n.noFindings)
});
},

/**
 * Render findings list.
 *
 * @param {Array<string>} warnings Warning messages.
 * @param {string} noFindingsText Empty-state text.
 * @return {string} HTML string.
 */
renderFindings: function (warnings, noFindingsText) {
if (warnings.length) {
var itemsHtml = '';
warnings.forEach(function (warningText) {
itemsHtml += AIPS.Templates.render('aips-tmpl-access-report-finding-item', {
text: warningText
});
});

return AIPS.Templates.renderRaw('aips-tmpl-access-report-findings-list', {
items_html: itemsHtml
});
}

return AIPS.Templates.render('aips-tmpl-access-report-findings-empty', {
message: noFindingsText
});
},

/**
 * Render a status badge.
 *
 * @param {string} status success|warning|error|info
 * @param {string} text Label.
 * @return {string} HTML string.
 */
renderBadge: function (status, text) {
var icon = 'info';
if (status === 'success') {
icon = 'yes-alt';
} else if (status === 'warning') {
icon = 'warning';
} else if (status === 'error') {
icon = 'dismiss';
}

return AIPS.Templates.render('aips-tmpl-access-report-badge', {
status: status,
icon: icon,
text: text
});
},

/**
 * Render a report card.
 *
 * @param {Object} args Card args.
 * @return {string} HTML string.
 */
renderCard: function (args) {
var lines = (args && args.lines && Array.isArray(args.lines)) ? args.lines : [];
var linesHtml = '';

if (lines.length) {
var lineItems = '';
lines.forEach(function (line) {
lineItems += AIPS.Templates.render('aips-tmpl-access-report-card-line', {
text: line
});
});

linesHtml = AIPS.Templates.renderRaw('aips-tmpl-access-report-card-lines', {
items_html: lineItems
});
}

return AIPS.Templates.renderRaw('aips-tmpl-access-report-card', {
status: args.status || 'neutral',
icon: args.icon || 'info',
title: args.title || '',
lines_html: linesHtml
});
},

/**
 * Close modal.
 *
 * @param {Event} e Click event.
 */
closeModal: function (e) {
if (e) {
e.preventDefault();
}
$('#aips-accessibility-report-modal').fadeOut(200);
},

/**
 * Close modal when clicking overlay.
 *
 * @param {Event} e Click event.
 */
closeModalOnOverlay: function (e) {
if ($(e.target).is('#aips-accessibility-report-modal') || $(e.target).is('#aips-accessibility-report-modal .aips-modal-overlay')) {
this.closeModal(e);
}
},

/**
 * Close modal on Escape.
 *
 * @param {Event} e Key event.
 */
onKeydown: function (e) {
if (e.key === 'Escape' && $('#aips-accessibility-report-modal').is(':visible')) {
this.closeModal(e);
}
}
};

$(document).ready(function () {
if (AIPS.AccessibilityReport && typeof AIPS.AccessibilityReport.init === 'function') {
AIPS.AccessibilityReport.init();
}
});

})(jQuery);
