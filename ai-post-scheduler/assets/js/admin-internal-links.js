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
			var self = this;

			// Tab navigation
			$(document).on('click', '.aips-tab-link', function (e) {
				e.preventDefault();
				var tab = $(this).data('tab');
				$('.aips-tab-link').removeClass('active');
				$(this).addClass('active');
				$('.aips-tab-content').hide().attr('aria-hidden', 'true');
				$('#' + tab + '-tab').show().attr('aria-hidden', 'false');
			});

			// Status filter
			$(document).on('change', '#aips-il-status-filter', function () {
				self.currentStatus = $(this).val();
				self.currentPage   = 1;
				self.loadSuggestions();
			});

			// Search
			var searchTimer;
			$(document).on('input', '#aips-il-search', function () {
				clearTimeout(searchTimer);
				var val = $(this).val().trim();
				$('#aips-il-search-clear').toggle(val.length > 0);
				searchTimer = setTimeout(function () {
					self.currentSearch = val;
					self.currentPage   = 1;
					self.loadSuggestions();
				}, 400);
			});

			$(document).on('click', '#aips-il-search-clear', function () {
				$('#aips-il-search').val('').trigger('input');
			});

			// Start indexing
			$(document).on('click', '#aips-start-indexing-btn', function () {
				self.startIndexing();
			});

			// Clear index
			$(document).on('click', '#aips-clear-index-btn', function () {
				if (!window.confirm(aipsInternalLinksL10n.confirmClearIndex)) {
					return;
				}
				self.clearIndex();
			});

			// Generate for post
			$(document).on('click', '#aips-generate-for-post-btn', function () {
				self.generateForPost();
			});

			// Re-index post
			$(document).on('click', '#aips-reindex-post-btn', function () {
				self.reindexPost();
			});

			// Row actions (delegated)
			$(document).on('click', '.aips-il-accept-btn', function () {
				self.updateStatus($(this).data('id'), 'accepted', $(this).closest('tr'));
			});

			$(document).on('click', '.aips-il-reject-btn', function () {
				self.updateStatus($(this).data('id'), 'rejected', $(this).closest('tr'));
			});

			$(document).on('click', '.aips-il-delete-btn', function () {
				if (!window.confirm(aipsInternalLinksL10n.confirmDelete)) {
					return;
				}
				self.deleteSuggestion($(this).data('id'), $(this).closest('tr'));
			});

			$(document).on('click', '.aips-il-edit-anchor-btn', function () {
				var $btn = $(this);
				$('#aips-anchor-modal-id').val($btn.data('id'));
				$('#aips-anchor-modal-text').val($btn.data('anchor'));
				$('#aips-anchor-modal').show();
				$('#aips-anchor-modal-text').focus();
			});

			// Insert Link button (accepted rows)
			$(document).on('click', '.aips-il-insert-btn', function () {
				self.openInsertModal($(this).data('id'));
			});

			// Insert modal: Insert button on a single suggestion entry
			$(document).on('click', '.aips-il-modal-insert-btn', function () {
				self.findInsertLocations(parseInt($(this).data('id'), 10));
			});

			// Insert modal: Apply button on a location result
			$(document).on('click', '.aips-il-apply-location-btn', function () {
				var $btn    = $(this);
				var sid     = parseInt($btn.data('suggestion-id'), 10);
				var match   = $btn.data('match');
				var replace = $btn.data('replace');
				self.applyInsertion(sid, match, replace, $btn);
			});

			// Anchor modal
			$(document).on('click', '.aips-modal-close', function () {
				$('#aips-anchor-modal').hide();
			});

			$(document).on('click', '#aips-anchor-modal-save', function () {
				self.saveAnchorText();
			});

			// Pagination
			$(document).on('click', '.aips-page-btn', function () {
				var page = parseInt($(this).data('page'), 10);
				if (page && page !== self.currentPage) {
					self.currentPage = page;
					self.loadSuggestions();
				}
			});
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
			if (item.status === 'pending') {
				actions +=
					'<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-il-accept-btn" data-id="' + item.id + '">' +
					'<span class="dashicons dashicons-yes" aria-hidden="true"></span>' +
					'<span class="screen-reader-text">' + aipsInternalLinksL10n.accepted + '</span>' +
					'</button> ' +
					'<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger aips-il-reject-btn" data-id="' + item.id + '">' +
					'<span class="dashicons dashicons-no" aria-hidden="true"></span>' +
					'<span class="screen-reader-text">' + aipsInternalLinksL10n.rejected + '</span>' +
					'</button> ';
			}

			if (item.status === 'accepted') {
				actions +=
					'<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-il-insert-btn" data-id="' + item.id + '" title="' + (aipsInternalLinksL10n.insertLink || 'Insert Link') + '">' +
					'<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>' +
					'<span class="screen-reader-text">' + (aipsInternalLinksL10n.insertLink || 'Insert Link') + '</span>' +
					'</button> ';
			}

			actions +=
				'<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-il-edit-anchor-btn" data-id="' + item.id + '" data-anchor="' + anchor + '">' +
				'<span class="dashicons dashicons-edit" aria-hidden="true"></span>' +
				'<span class="screen-reader-text">' + aipsInternalLinksL10n.anchorUpdated + '</span>' +
				'</button> ' +
				'<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-btn-danger aips-il-delete-btn" data-id="' + item.id + '">' +
				'<span class="dashicons dashicons-trash" aria-hidden="true"></span>' +
				'<span class="screen-reader-text">' + aipsInternalLinksL10n.confirmDelete + '</span>' +
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
		 * Start background indexing.
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
				$btn.prop('disabled', false);
			});
		},

		/**
		 * Clear the full index and all suggestions.
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
				$btn.prop('disabled', false);
				self.showGenerateFeedback('Request failed.', 'error');
			});
		},

		/**
		 * Re-index a single post by ID.
		 */
		reindexPost: function () {
			var self      = this;
			var postId    = parseInt($('#aips-gen-post-id').val(), 10);
			var $btn      = $('#aips-reindex-post-btn');
			var $feedback = $('#aips-gen-feedback');

			if (!postId) {
				self.showGenerateFeedback('Please enter a valid post ID.', 'error');
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
				$btn.prop('disabled', false);
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

		// -----------------------------------------------------------------------
		// Insert Link modal
		// -----------------------------------------------------------------------

		/**
		 * Open the Insert Link modal and load post content + accepted suggestions.
		 *
		 * @param {number} suggestionId Suggestion row ID.
		 */
		openInsertModal: function (suggestionId) {
			var self = this;

			// Reset modal state.
			$('#aips-insert-suggestions-list').html(
				'<span class="spinner is-active" style="float:none;vertical-align:middle;"></span>'
			);
			$('#aips-insert-post-content').html(
				'<span class="spinner is-active" style="float:none;vertical-align:middle;"></span>'
			);
			$('#aips-insert-post-title').text('');
			$('#aips-insert-locations-section').hide();
			$('#aips-insert-locations-list').html('');
			$('#aips-insert-modal').show();

			$.post(aipsAjax.ajaxUrl, {
				action:        'aips_internal_links_get_post_for_insertion',
				nonce:         aipsInternalLinksL10n.nonce,
				suggestion_id: suggestionId,
			}, function (response) {
				if (!response.success) {
					$('#aips-insert-suggestions-list').html(
						'<p class="aips-notice aips-notice-error">' +
						AIPS.Templates.escape((response.data && response.data.message) || aipsInternalLinksL10n.loadingFailed) +
						'</p>'
					);
					$('#aips-insert-post-content').text('');
					return;
				}

				var data = response.data;

				// Post title in sub-header.
				$('#aips-insert-post-title').text(data.post_title || '');

				// Render suggestions list.
				self.renderInsertSuggestions(data.suggestions || []);

				// Render post content (plain-text, HTML stripped for readability).
				var plainContent = $('<div>').html(data.post_content || '').text();
				$('#aips-insert-post-content').text(plainContent || aipsInternalLinksL10n.noContent);
			}).fail(function () {
				$('#aips-insert-suggestions-list').html(
					'<p class="aips-notice aips-notice-error">' +
					AIPS.Templates.escape(aipsInternalLinksL10n.loadingFailed) + '</p>'
				);
				$('#aips-insert-post-content').text('');
			});
		},

		/**
		 * Render the accepted suggestions list inside the Insert modal.
		 *
		 * @param {Array} suggestions Array of suggestion objects from the server.
		 */
		renderInsertSuggestions: function (suggestions) {
			var $list = $('#aips-insert-suggestions-list');

			if (!suggestions || suggestions.length === 0) {
				$list.html(
					'<p style="color:#888;margin:0;">' +
					AIPS.Templates.escape(aipsInternalLinksL10n.noInsertSuggestions) + '</p>'
				);
				return;
			}

			var html = '<ul style="margin:0;padding:0;list-style:none;">';

			$.each(suggestions, function (i, s) {
				var score   = Math.round(parseFloat(s.similarity_score) * 100) + '%';
				var title   = AIPS.Templates.escape(s.target_post_title || '#' + s.target_post_id);
				var anchor  = AIPS.Templates.escape(s.anchor_text || s.target_post_title || '');
				var target  = AIPS.Templates.escape(s.target_url || '');

				html +=
					'<li style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid #f0f0f0;">' +
					'<div style="flex:1;min-width:0;">' +
					'<strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + title + '">' + title + '</strong>' +
					'<span style="font-size:12px;color:#888;">Anchor: ' + anchor + ' &nbsp;|&nbsp; ' + score + '</span>' +
					(target ? '<br><a href="' + target + '" target="_blank" rel="noopener noreferrer" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;max-width:300px;">' + target + '</a>' : '') +
					'</div>' +
					'<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-il-modal-insert-btn" data-id="' + parseInt(s.id, 10) + '">' +
					'<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span> ' +
					AIPS.Templates.escape(aipsInternalLinksL10n.insertBtn) +
					'</button>' +
					'</li>';
			});

			html += '</ul>';
			$list.html(html);
		},

		/**
		 * Call the AI to find insertion locations for a specific suggestion.
		 *
		 * @param {number} suggestionId Suggestion row ID.
		 */
		findInsertLocations: function (suggestionId) {
			var self = this;

			$('#aips-insert-locations-section').show();
			$('#aips-insert-locations-list').html('');
			$('#aips-insert-locations-spinner').addClass('is-active');

			$.post(aipsAjax.ajaxUrl, {
				action:        'aips_internal_links_find_insert_locations',
				nonce:         aipsInternalLinksL10n.nonce,
				suggestion_id: suggestionId,
			}, function (response) {
				$('#aips-insert-locations-spinner').removeClass('is-active');

				if (!response.success) {
					$('#aips-insert-locations-list').html(
						'<p class="aips-notice aips-notice-error">' +
						AIPS.Templates.escape((response.data && response.data.message) || aipsInternalLinksL10n.locationsFailed) +
						'</p>'
					);
					return;
				}

				var locations = response.data.locations || [];
				self.renderInsertLocations(suggestionId, locations);
			}).fail(function () {
				$('#aips-insert-locations-spinner').removeClass('is-active');
				$('#aips-insert-locations-list').html(
					'<p class="aips-notice aips-notice-error">' +
					AIPS.Templates.escape(aipsInternalLinksL10n.locationsFailed) + '</p>'
				);
			});
		},

		/**
		 * Render the AI-generated insertion location options.
		 *
		 * @param {number} suggestionId Suggestion row ID (passed through to apply).
		 * @param {Array}  locations    Array of location objects {reason, match_snippet, replacement_snippet}.
		 */
		renderInsertLocations: function (suggestionId, locations) {
			var $list = $('#aips-insert-locations-list');

			if (!locations || locations.length === 0) {
				$list.html(
					'<p style="color:#888;margin:0;">' +
					AIPS.Templates.escape(aipsInternalLinksL10n.noLocations) + '</p>'
				);
				return;
			}

			var html = '';

			$.each(locations, function (i, loc) {
				var num     = i + 1;
				var reason  = AIPS.Templates.escape(loc.reason || '');
				var match   = AIPS.Templates.escape(loc.match_snippet || '');
				var replace = AIPS.InternalLinks.formatReplacementPreview(loc.replacement_snippet || '');

				html +=
					'<div class="aips-insert-location-card" style="' +
					'border:1px solid #c3c4c7;border-radius:4px;padding:14px 16px;margin-bottom:10px;background:#fff;">' +

					'<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">' +
					'<div style="flex:1;min-width:0;">' +

					'<p style="margin:0 0 6px;font-size:13px;font-weight:600;color:#1d2327;">Option ' + num + '</p>' +

					(reason
						? '<p style="margin:0 0 8px;font-size:12px;color:#555;">' +
						  '<strong>' + AIPS.Templates.escape(aipsInternalLinksL10n.reasonLabel) + ':</strong> ' + reason + '</p>'
						: '') +

					'<div style="margin-bottom:8px;">' +
					'<p style="margin:0 0 3px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#888;">' +
					AIPS.Templates.escape(aipsInternalLinksL10n.originalSnippetLabel) + '</p>' +
					'<blockquote style="margin:0;padding:6px 10px;background:#f6f7f7;border-left:3px solid #c3c4c7;font-size:12px;color:#444;font-style:italic;">' +
					match + '</blockquote>' +
					'</div>' +

					'<div>' +
					'<p style="margin:0 0 3px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#888;">' +
					AIPS.Templates.escape(aipsInternalLinksL10n.withLinkLabel) + '</p>' +
					'<blockquote style="margin:0;padding:6px 10px;background:#f0f6fc;border-left:3px solid #2271b1;font-size:12px;color:#444;font-style:italic;">' +
					replace + '</blockquote>' +
					'</div>' +

					'</div>' + // flex inner

					'<div style="flex-shrink:0;">' +
					'<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-il-apply-location-btn"' +
					' data-suggestion-id="' + parseInt(suggestionId, 10) + '"' +
					' data-match="' + AIPS.Templates.escape(loc.match_snippet || '') + '"' +
					' data-replace="' + AIPS.Templates.escape(loc.replacement_snippet || '') + '">' +
					AIPS.Templates.escape(aipsInternalLinksL10n.applyBtn) +
					'</button>' +
					'</div>' +
					'</div>' + // outer flex

					'</div>'; // card
			});

			$list.html(html);
		},

		/**
		 * Format a plain-text replacement snippet for safe preview in the modal.
		 *
		 * Highlights the [[anchor text]] marker without treating the snippet as
		 * trusted HTML.
		 *
		 * @param {string} replacementSnippet Plain-text replacement snippet.
		 * @return {string} Safe HTML preview string.
		 */
		formatReplacementPreview: function (replacementSnippet) {
			var escaped = AIPS.Templates.escape(replacementSnippet || '');

			return escaped.replace(/\[\[(.*?)\]\]/g, '<mark style="background:#fff1c6;padding:0 2px;border-radius:2px;">$1</mark>');
		},

		/**
		 * Apply a chosen insertion location to the post content.
		 *
		 * @param {number} suggestionId      Suggestion row ID.
		 * @param {string} matchSnippet      Exact text excerpt from the post.
		 * @param {string} replacementSnippet Plain-text snippet with [[anchor marker]].
		 * @param {jQuery} $btn              The Apply button element.
		 */
		applyInsertion: function (suggestionId, matchSnippet, replacementSnippet, $btn) {
			var self = this;

			$btn.prop('disabled', true).text(aipsInternalLinksL10n.applying);

			$.post(aipsAjax.ajaxUrl, {
				action:               'aips_internal_links_apply_insertion',
				nonce:                aipsInternalLinksL10n.nonce,
				suggestion_id:        suggestionId,
				match_snippet:        matchSnippet,
				replacement_snippet:  replacementSnippet,
			}, function (response) {
				$btn.prop('disabled', false).text(aipsInternalLinksL10n.applyBtn);

				if (response.success) {
					$('#aips-insert-modal').hide();
					AIPS.Utilities.showToast(response.data.message || aipsInternalLinksL10n.applied, 'success');
					self.loadSuggestions();
					self.refreshStatus();
				} else {
					AIPS.Utilities.showToast(
						(response.data && response.data.message) || aipsInternalLinksL10n.applyFailed,
						'error'
					);
				}
			}).fail(function () {
				$btn.prop('disabled', false).text(aipsInternalLinksL10n.applyBtn);
				AIPS.Utilities.showToast(aipsInternalLinksL10n.applyFailed, 'error');
			});
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
