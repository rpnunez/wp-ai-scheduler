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

		/** Plain-text copy of the post content in the Insert modal */
		_previewPlainText: '',

		/** Post ID currently loaded in the Insert modal */
		_previewPostId: 0,

		/** Pending insertions not yet saved: [{suggestionId, matchSnippet, replacementSnippet, anchorText}] */
		_pendingInsertions: [],

		/** Map of suggestionId → {anchorText, targetUrl, title} for the Insert modal */
		_suggestionDataMap: {},

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

			// Insert Link button (accepted rows)
			$(document).on('click', '.aips-il-insert-btn', function () {
				self.openInsertModal($(this).data('id'));
			});

			// Insert modal: Insert button on a single suggestion entry
			$(document).on('click', '.aips-il-modal-insert-btn', function () {
				self.findInsertLocations(parseInt($(this).data('id'), 10));
			});

			// Insert modal: Apply button on a location result — preview only, no immediate save
			$(document).on('click', '.aips-il-apply-location-btn', function () {
				var $btn    = $(this);
				var sid     = parseInt($btn.data('suggestion-id'), 10);
				var match   = $btn.data('match');
				var replace = $btn.data('replace');
				self.applyInsertionPreview(sid, match, replace, $btn);
			});

			// Insert modal: Update Post button — commit all pending insertions to DB
			$(document).on('click', '#aips-update-post-btn', this.onUpdatePostClick.bind(this));

			// Preview insertion: hover to show actions
			$(document).on('mouseenter', '.aips-il-preview-insertion', this.onPreviewHoverIn.bind(this));
			$(document).on('mouseleave', '.aips-il-preview-insertion', this.onPreviewHoverOut.bind(this));

			// Preview insertion: edit anchor / undo (remove) buttons
			$(document).on('click', '.aips-il-preview-edit-btn', this.onPreviewEditClick.bind(this));
			$(document).on('click', '.aips-il-preview-undo-btn', this.onPreviewUndoClick.bind(this));

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
			$('#aips-anchor-modal-context').val('table');
			$('#aips-anchor-modal').show();
			$('#aips-anchor-modal-text').focus();
		},

		/**
		 * Close the modal associated with the clicked close control.
		 * Resets any pending preview insertions when the Insert modal is closed.
		 *
		 * @param {Event} e Click event from an `.aips-modal-close` element.
		 */
		onModalClose: function (e) {
			var $modal = $(e.currentTarget).closest('.aips-modal');

			if ($modal.length) {
				// Reset pending insertions when the Insert modal is closed.
				if ($modal.is('#aips-insert-modal')) {
					this._pendingInsertions = [];
					this._previewPlainText  = '';
					this._previewPostId     = 0;
					$('#aips-pending-count').text('');
				}

				$modal.hide();
				return;
			}

			$('#aips-anchor-modal, #aips-insert-modal').hide();
		},

		/**
		 * Save the edited anchor text from the modal.
		 * When the modal was opened from a preview insertion, update the local
		 * state without making an AJAX call; otherwise, persist to the database.
		 *
		 * @param {Event} e Click event from `#aips-anchor-modal-save`.
		 */
		onAnchorModalSave: function (e) {
			var context = $('#aips-anchor-modal-context').val();
			if (context === 'preview') {
				var id         = parseInt($('#aips-anchor-modal-id').val(), 10);
				var anchorText = $('#aips-anchor-modal-text').val().trim();
				if (!id) { return; }
				this.editPreviewAnchor(id, anchorText);
			} else {
				this.saveAnchorText();
			}
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

		/**
		 * Commit all pending preview insertions to the post.
		 *
		 * @param {Event} e Click event from `#aips-update-post-btn`.
		 */
		onUpdatePostClick: function (e) {
			this.saveAllInsertions();
		},

		/**
		 * Show the hover action buttons when entering a preview insertion span.
		 *
		 * @param {Event} e Mouseenter event on an `.aips-il-preview-insertion` element.
		 */
		onPreviewHoverIn: function (e) {
			$(e.currentTarget).find('.aips-il-preview-actions').stop(true).show();
		},

		/**
		 * Hide the hover action buttons when leaving a preview insertion span.
		 *
		 * @param {Event} e Mouseleave event on an `.aips-il-preview-insertion` element.
		 */
		onPreviewHoverOut: function (e) {
			$(e.currentTarget).find('.aips-il-preview-actions').stop(true).hide();
		},

		/**
		 * Open the anchor edit modal for a preview insertion link.
		 *
		 * @param {Event} e Click event from an `.aips-il-preview-edit-btn` element.
		 */
		onPreviewEditClick: function (e) {
			e.stopPropagation();
			var sid = parseInt($(e.currentTarget).data('suggestion-id'), 10);
			var currentAnchor = '';

			for (var i = 0; i < this._pendingInsertions.length; i++) {
				if (this._pendingInsertions[i].suggestionId === sid) {
					currentAnchor = this._pendingInsertions[i].anchorText;
					break;
				}
			}

			$('#aips-anchor-modal-id').val(sid);
			$('#aips-anchor-modal-text').val(currentAnchor);
			$('#aips-anchor-modal-context').val('preview');
			$('#aips-anchor-modal').show();
			$('#aips-anchor-modal-text').focus();
		},

		/**
		 * Remove a preview insertion and re-render the content preview.
		 *
		 * @param {Event} e Click event from an `.aips-il-preview-undo-btn` element.
		 */
		onPreviewUndoClick: function (e) {
			e.stopPropagation();
			var sid = parseInt($(e.currentTarget).data('suggestion-id'), 10);
			this.undoInsertionPreview(sid);
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

			$tbody.html(AIPS.Templates.render('aips-tmpl-il-tbody-loading', {
				message: aipsInternalLinksL10n.loading,
			}));

			$.post(aipsAjax.ajaxUrl, {
				action:   'aips_internal_links_get_suggestions',
				nonce:    aipsInternalLinksL10n.nonce,
				page:     self.currentPage,
				per_page: self.perPage,
				status:   self.currentStatus,
				search:   self.currentSearch,
			}, function (response) {
				if (!response.success) {
					$tbody.html(AIPS.Templates.render('aips-tmpl-il-tbody-message', {
						message: aipsInternalLinksL10n.errorLoading,
					}));
					return;
				}

				var data = response.data;

				if (!data.items || data.items.length === 0) {
					$tbody.html(AIPS.Templates.render('aips-tmpl-il-tbody-message', {
						message: aipsInternalLinksL10n.noSuggestions,
					}));
					self.renderPagination(0, 0);
					return;
				}

				$tbody.html('');
				$.each(data.items, function (i, item) {
					$tbody.append(self.renderRow(item));
				});

				self.renderPagination(data.total, data.total_pages);
			}).fail(function () {
				$tbody.html(AIPS.Templates.render('aips-tmpl-il-tbody-message', {
					message: aipsInternalLinksL10n.errorLoading,
				}));
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

			var sourceTitle = item.source_post_title || '(#' + item.source_post_id + ')';
			var targetTitle = item.target_post_title || '(#' + item.target_post_id + ')';

			var source = item.source_edit_url
				? AIPS.Templates.render('aips-tmpl-il-post-link', {
					url:   item.source_edit_url,
					title: sourceTitle,
				})
				: AIPS.Templates.escape(sourceTitle);

			var target = item.target_edit_url
				? AIPS.Templates.render('aips-tmpl-il-post-link', {
					url:   item.target_edit_url,
					title: targetTitle,
				})
				: AIPS.Templates.escape(targetTitle);

			var actions = '';

			if (item.status === 'pending') {
				actions += AIPS.Templates.render('aips-tmpl-il-actions-pending', {
					id:           item.id,
					acceptLabel:  aipsInternalLinksL10n.acceptAction,
					rejectLabel:  aipsInternalLinksL10n.rejectAction,
				});
			}

			if (item.status === 'accepted') {
				actions += AIPS.Templates.render('aips-tmpl-il-actions-accepted', {
					id:          item.id,
					insertLabel: aipsInternalLinksL10n.insertLink,
				});
			}

			actions += AIPS.Templates.render('aips-tmpl-il-actions-edit-delete', {
				id:          item.id,
				anchor:      item.anchor_text || '',
				editLabel:   aipsInternalLinksL10n.editAnchorText,
				deleteLabel: aipsInternalLinksL10n.deleteSuggestion,
			});

			return AIPS.Templates.renderRaw('aips-tmpl-il-suggestion-row', {
				id:          item.id,
				source:      source,
				target:      target,
				score:       score,
				anchor:      anchor,
				statusClass: statusClass,
				statusLabel: AIPS.Templates.escape(statusLabel),
				actions:     actions,
			});
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
				html += AIPS.Templates.render('aips-tmpl-il-page-btn', {
					page:    self.currentPage - 1,
					classes: 'aips-btn-secondary',
					label:   '\u00ab',
				}) + ' ';
			}

			var start = Math.max(1, self.currentPage - 2);
			var end   = Math.min(totalPages, self.currentPage + 2);

			for (var p = start; p <= end; p++) {
				var classes = p === self.currentPage ? 'aips-btn-primary' : 'aips-btn-secondary';
				html += AIPS.Templates.render('aips-tmpl-il-page-btn', {
					page:    p,
					classes: classes,
					label:   p,
				}) + ' ';
			}

			if (self.currentPage < totalPages) {
				html += AIPS.Templates.render('aips-tmpl-il-page-btn', {
					page:    self.currentPage + 1,
					classes: 'aips-btn-secondary',
					label:   '\u00bb',
				});
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
				$btn.prop('disabled', false).html(AIPS.Templates.render('aips-tmpl-il-btn-start-indexing', {
					label: self.originalIndexText,
				}));

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
				$btn.prop('disabled', false).html(AIPS.Templates.render('aips-tmpl-il-btn-start-indexing', {
					label: self.originalIndexText,
				}));
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
				$btn.prop('disabled', false).html(AIPS.Templates.render('aips-tmpl-il-btn-generate', {
					label: self.originalGenerateText,
				}));

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
				$btn.prop('disabled', false).html(AIPS.Templates.render('aips-tmpl-il-btn-generate', {
					label: self.originalGenerateText,
				}));
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
				$btn.prop('disabled', false).html(AIPS.Templates.render('aips-tmpl-il-btn-reindex', {
					label: self.originalReindexText,
				}));

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
				$btn.prop('disabled', false).html(AIPS.Templates.render('aips-tmpl-il-btn-reindex', {
					label: self.originalReindexText,
				}));
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
					$('#aips-stat-indexed').html(AIPS.Templates.render('aips-tmpl-il-indexed-stat', {
						indexed: idx.indexed,
						total:   idx.total_posts,
					}));
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
			var spinnerHtml = AIPS.Templates.get('aips-tmpl-il-spinner');

			// Reset modal state.
			this._previewPostId      = 0;
			this._previewPlainText   = '';
			this._pendingInsertions  = [];
			this._suggestionDataMap  = {};

			$('#aips-insert-suggestions-list').html(spinnerHtml);
			$('#aips-insert-post-content').html(spinnerHtml);
			$('#aips-insert-post-title').text('');
			$('#aips-update-post-btn').prop('disabled', true);
			$('#aips-pending-count').text('');
			$('#aips-insert-modal').show();

			$.post(aipsAjax.ajaxUrl, {
				action:        'aips_internal_links_get_post_for_insertion',
				nonce:         aipsInternalLinksL10n.nonce,
				suggestion_id: suggestionId,
			}, function (response) {
				if (!response.success) {
					$('#aips-insert-suggestions-list').html(
						AIPS.Templates.render('aips-tmpl-il-notice-error', {
							message: (response.data && response.data.message) || aipsInternalLinksL10n.loadingFailed,
						})
					);
					$('#aips-insert-post-content').html('');
					return;
				}

				var data = response.data;

				self._previewPostId = data.post_id || 0;

				// Store plain-text version for the preview renderer.
				self._previewPlainText = $('<div>').html(data.post_content || '').text();

				// Post title in sub-header.
				$('#aips-insert-post-title').text(data.post_title || '');

				// Render suggestions list (also populates _suggestionDataMap).
				self.renderInsertSuggestions(data.suggestions || []);

				// Render initial post content preview.
				self.renderPreviewContent();
			}).fail(function () {
				$('#aips-insert-suggestions-list').html(
					AIPS.Templates.render('aips-tmpl-il-notice-error', {
						message: aipsInternalLinksL10n.loadingFailed,
					})
				);
				$('#aips-insert-post-content').html('');
			});
		},

		/**
		 * Render the accepted suggestions list inside the Insert modal and
		 * populate the suggestion data map for use during preview insertion.
		 *
		 * @param {Array} suggestions Array of suggestion objects from the server.
		 */
		renderInsertSuggestions: function (suggestions) {
			var self  = this;
			var $list = $('#aips-insert-suggestions-list');

			if (!suggestions || suggestions.length === 0) {
				$list.html(AIPS.Templates.render('aips-tmpl-il-notice-muted', {
					message: aipsInternalLinksL10n.noInsertSuggestions,
				}));
				return;
			}

			var items = '';

			$.each(suggestions, function (i, s) {
				var suggestionId = parseInt(s.id, 10);
				var score        = Math.round(parseFloat(s.similarity_score) * 100) + '%';
				var title        = s.target_post_title || '#' + s.target_post_id;
				var anchor       = s.anchor_text || s.target_post_title || '';
				var targetUrl    = s.target_url || '';

				// Populate the suggestion data map so applyInsertionPreview can
				// retrieve anchor text and target URL without extra data attributes.
				self._suggestionDataMap[suggestionId] = {
					anchorText: anchor,
					targetUrl:  targetUrl,
					title:      title,
				};

				var targetLinkHtml = targetUrl
					? AIPS.Templates.render('aips-tmpl-il-insert-target-link', { url: targetUrl })
					: '';

				items += AIPS.Templates.renderRaw('aips-tmpl-il-insert-suggestion', {
					suggestionId:            suggestionId,
					title:                   AIPS.Templates.escape(title),
					anchorLabel:             AIPS.Templates.escape(aipsInternalLinksL10n.anchorLabel),
					anchor:                  AIPS.Templates.escape(anchor),
					score:                   score,
					targetLinkHtml:          targetLinkHtml,
					insertBtn:               AIPS.Templates.escape(aipsInternalLinksL10n.insertBtn),
					insertionLocationsLabel: AIPS.Templates.escape(aipsInternalLinksL10n.insertionLocationsLabel),
				});
			});

			$list.html(AIPS.Templates.renderRaw('aips-tmpl-il-suggestions-list', { items: items }));
		},

		/**
		 * Call the AI to find insertion locations for a specific suggestion.
		 *
		 * @param {number} suggestionId Suggestion row ID.
		 */
		findInsertLocations: function (suggestionId) {
			var self = this;
			var $item = $('.aips-il-suggestion-item[data-suggestion-id="' + suggestionId + '"]');
			var $panel = $item.find('.aips-il-inline-locations');
			var $list = $item.find('.aips-il-inline-locations-list');
			var $spinner = $item.find('.aips-il-inline-spinner');
			var $count = $item.find('.aips-il-inline-count');
			var $button = $item.find('.aips-il-modal-insert-btn');

			$panel.show();
			$list.html(AIPS.Templates.render('aips-tmpl-il-notice-muted', {
				message: aipsInternalLinksL10n.findingLocations,
			}));
			$count.text('');
			$spinner.addClass('is-active');
			$button.prop('disabled', true);

			$.post(aipsAjax.ajaxUrl, {
				action:        'aips_internal_links_find_insert_locations',
				nonce:         aipsInternalLinksL10n.nonce,
				suggestion_id: suggestionId,
			}, function (response) {
				$spinner.removeClass('is-active');
				$button.prop('disabled', false);

				if (!response.success) {
					$list.html(AIPS.Templates.render('aips-tmpl-il-notice-error', {
						message: (response.data && response.data.message) || aipsInternalLinksL10n.locationsFailed,
					}));
					$count.text('');
					return;
				}

				var locations = response.data.locations || [];
				var requestedCount = parseInt(response.data.requested_count, 10) || locations.length;
				var returnedCount = parseInt(response.data.returned_count, 10);

				if (isNaN(returnedCount)) {
					returnedCount = locations.length;
				}

				self.renderInsertLocations(suggestionId, locations, requestedCount, returnedCount);
			}).fail(function () {
				$spinner.removeClass('is-active');
				$button.prop('disabled', false);
				$list.html(AIPS.Templates.render('aips-tmpl-il-notice-error', {
					message: aipsInternalLinksL10n.locationsFailed,
				}));
				$count.text('');
			});
		},

		/**
		 * Render the AI-generated insertion location options.
		 *
		 * @param {number} suggestionId    Suggestion row ID (passed through to apply).
		 * @param {Array}  locations       Array of location objects {reason, match_snippet, replacement_snippet}.
		 * @param {number} requestedCount  Number requested from the server.
		 * @param {number} returnedCount   Number returned by the server.
		 */
		renderInsertLocations: function (suggestionId, locations, requestedCount, returnedCount) {
			var $item = $('.aips-il-suggestion-item[data-suggestion-id="' + suggestionId + '"]');
			var $list = $item.find('.aips-il-inline-locations-list');
			var $count = $item.find('.aips-il-inline-count');

			$count.text(
				AIPS.InternalLinks.formatCountLabel(returnedCount, requestedCount)
			);

			if (!locations || locations.length === 0) {
				$list.html(AIPS.Templates.render('aips-tmpl-il-no-locations', {
					zeroReturned: aipsInternalLinksL10n.zeroSuggestionsReturned,
					noLocations:  aipsInternalLinksL10n.noLocations,
				}));
				return;
			}

			var html = '';

			$.each(locations, function (i, loc) {
				var num     = i + 1;
				var reason  = loc.reason || '';
				var match   = AIPS.Templates.escape(loc.match_snippet || '');
				var replace = AIPS.InternalLinks.formatReplacementPreview(loc.replacement_snippet || '');

				var reasonHtml = reason
					? AIPS.Templates.render('aips-tmpl-il-location-reason', {
						reasonLabel: aipsInternalLinksL10n.reasonLabel,
						reason:      reason,
					})
					: '';

				html += AIPS.Templates.renderRaw('aips-tmpl-il-location-card', {
					optionLabel:          AIPS.Templates.escape(aipsInternalLinksL10n.optionLabel),
					num:                  num,
					reasonHtml:           reasonHtml,
					originalSnippetLabel: AIPS.Templates.escape(aipsInternalLinksL10n.originalSnippetLabel),
					match:                match,
					withLinkLabel:        AIPS.Templates.escape(aipsInternalLinksL10n.withLinkLabel),
					replace:              replace,
					suggestionId:         parseInt(suggestionId, 10),
					matchRaw:             AIPS.Templates.escape(loc.match_snippet || ''),
					replaceRaw:           AIPS.Templates.escape(loc.replacement_snippet || ''),
					applyBtn:             AIPS.Templates.escape(aipsInternalLinksL10n.applyBtn),
				});
			});

			$list.html(html);
		},

		/**
		 * Format the debug label showing how many insertion suggestions were returned.
		 *
		 * @param {number} returnedCount  Number of valid suggestions returned.
		 * @param {number} requestedCount Number of suggestions requested.
		 * @return {string} Human-readable summary.
		 */
		formatCountLabel: function (returnedCount, requestedCount) {
			var template = aipsInternalLinksL10n.returnedCountLabel || 'Returned %1$d of %2$d suggestions';

			return template
				.replace('%1$d', String(returnedCount))
				.replace('%2$d', String(requestedCount));
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
		 * Apply a chosen location to the post content preview (local only).
		 *
		 * Validates that the match snippet exists in the current plain-text
		 * preview and that this suggestion hasn't already been applied. Adds the
		 * insertion to _pendingInsertions and re-renders the content preview.
		 * No AJAX is made — use saveAllInsertions() to persist changes.
		 *
		 * @param {number} suggestionId      Suggestion row ID.
		 * @param {string} matchSnippet      Exact text excerpt from the post.
		 * @param {string} replacementSnippet Plain-text snippet with [[anchor marker]].
		 * @param {jQuery} $btn              The Apply button element.
		 */
		applyInsertionPreview: function (suggestionId, matchSnippet, replacementSnippet, $btn) {
			// Guard: duplicate application for the same suggestion.
			for (var i = 0; i < this._pendingInsertions.length; i++) {
				if (this._pendingInsertions[i].suggestionId === suggestionId) {
					AIPS.Utilities.showToast(
						aipsInternalLinksL10n.alreadyApplied || 'Already applied.',
						'info'
					);
					return;
				}
			}

			// Guard: match snippet must be present in the plain-text preview.
			if (this._previewPlainText.indexOf(matchSnippet) === -1) {
				AIPS.Utilities.showToast(
					aipsInternalLinksL10n.snippetNotFound || 'Text not found in content preview.',
					'error'
				);
				return;
			}

			// Extract anchor text from [[anchor]] in the replacement snippet.
			var markerMatch = replacementSnippet.match(/\[\[([\s\S]*?)\]\]/);
			var anchorText  = markerMatch ? markerMatch[1] : '';

			if (!anchorText && this._suggestionDataMap[suggestionId]) {
				anchorText = this._suggestionDataMap[suggestionId].anchorText || '';
			}

			// Store the pending insertion.
			this._pendingInsertions.push({
				suggestionId:        suggestionId,
				matchSnippet:        matchSnippet,
				replacementSnippet:  replacementSnippet,
				anchorText:          anchorText,
			});

			// Re-render the preview and update UI chrome.
			this.renderPreviewContent();
			this.updatePendingCount();

			$('#aips-update-post-btn').prop('disabled', false);

			// Mark this Apply button as applied.
			$btn.prop('disabled', true).html(
				'<span class="dashicons dashicons-yes" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span> ' +
				AIPS.Templates.escape(aipsInternalLinksL10n.applied || 'Applied')
			);
		},

		/**
		 * Render the post content preview div with all pending insertions highlighted.
		 *
		 * Starts from the stored plain-text copy, HTML-escapes it, then
		 * substitutes each pending insertion's match snippet with a styled
		 * <span> element that shows the anchor in green with hover action buttons.
		 */
		renderPreviewContent: function () {
			var html = AIPS.Templates.escape(this._previewPlainText || '');

			if (!html) {
				$('#aips-insert-post-content').html(
					AIPS.Templates.render('aips-tmpl-il-notice-muted', {
						message: aipsInternalLinksL10n.noContent,
					})
				);
				return;
			}

			for (var i = 0; i < this._pendingInsertions.length; i++) {
				var ins          = this._pendingInsertions[i];
				var escapedMatch = AIPS.Templates.escape(ins.matchSnippet);
				var idx          = html.indexOf(escapedMatch);

				if (idx === -1) { continue; }

				// Split replacement snippet into before / anchor / after parts.
				var repSnippet  = ins.replacementSnippet || '';
				var parts       = repSnippet.match(/^([\s\S]*?)\[\[([\s\S]*?)\]\]([\s\S]*)$/);
				var before      = parts ? parts[1] : '';
				var anchor      = parts ? parts[2] : (ins.anchorText || '');
				var after       = parts ? parts[3] : '';

				var insertionHtml = AIPS.Templates.render('aips-tmpl-il-preview-insertion', {
					suggestionId: ins.suggestionId,
					matchEsc:     ins.matchSnippet,
					before:       before,
					anchor:       anchor,
					after:        after,
					editLabel:    aipsInternalLinksL10n.editInsertedLink || 'Edit anchor',
					undoLabel:    aipsInternalLinksL10n.removeInsertedLink || 'Remove link',
				});

				// Replace only the first occurrence (avoids regex/$ issues).
				html = html.substring(0, idx) + insertionHtml + html.substring(idx + escapedMatch.length);
			}

			$('#aips-insert-post-content').html(html);
		},

		/**
		 * Remove a pending preview insertion and re-render.
		 *
		 * Re-enables the Apply button(s) for the removed suggestion so the user
		 * can re-apply a different location if desired.
		 *
		 * @param {number} suggestionId Suggestion row ID to remove.
		 */
		undoInsertionPreview: function (suggestionId) {
			var idx = -1;

			for (var i = 0; i < this._pendingInsertions.length; i++) {
				if (this._pendingInsertions[i].suggestionId === suggestionId) {
					idx = i;
					break;
				}
			}

			if (idx === -1) { return; }

			this._pendingInsertions.splice(idx, 1);
			this.renderPreviewContent();
			this.updatePendingCount();

			// Re-enable Apply buttons that belong to this suggestion.
			$('.aips-il-apply-location-btn[data-suggestion-id="' + suggestionId + '"]')
				.prop('disabled', false)
				.text(aipsInternalLinksL10n.applyBtn);

			if (this._pendingInsertions.length === 0) {
				$('#aips-update-post-btn').prop('disabled', true);
			}
		},

		/**
		 * Update the anchor text for an already-applied preview insertion.
		 *
		 * Updates both the display label and the replacement_snippet's [[marker]]
		 * so that saveAllInsertions() sends the correct anchor to the server.
		 *
		 * @param {number} suggestionId Suggestion row ID.
		 * @param {string} newAnchor    New anchor text.
		 */
		editPreviewAnchor: function (suggestionId, newAnchor) {
			for (var i = 0; i < this._pendingInsertions.length; i++) {
				if (this._pendingInsertions[i].suggestionId === suggestionId) {
					this._pendingInsertions[i].anchorText         = newAnchor;
					this._pendingInsertions[i].replacementSnippet = this._pendingInsertions[i].replacementSnippet.replace(
						/\[\[[\s\S]*?\]\]/,
						'[[' + newAnchor + ']]'
					);
					break;
				}
			}

			this.renderPreviewContent();
			$('#aips-anchor-modal').hide();
			AIPS.Utilities.showToast(aipsInternalLinksL10n.anchorUpdated, 'success');
		},

		/**
		 * Update the pending-insertions count label in the modal footer.
		 */
		updatePendingCount: function () {
			var n = this._pendingInsertions.length;

			if (n === 0) {
				$('#aips-pending-count').text('');
				return;
			}

			var template = n === 1
				? (aipsInternalLinksL10n.pendingCountSingle || '%d pending insertion')
				: (aipsInternalLinksL10n.pendingCountPlural || '%d pending insertions');

			$('#aips-pending-count').text(template.replace('%d', String(n)));
		},

		/**
		 * Commit all pending preview insertions to the post via a single AJAX call.
		 *
		 * On success the modal is closed, suggestions are reloaded, and status
		 * counters are refreshed. On partial success (some errors), the success
		 * toast is shown alongside a separate warning for the failed items.
		 */
		saveAllInsertions: function () {
			var self = this;

			if (this._pendingInsertions.length === 0) { return; }

			var $btn            = $('#aips-update-post-btn');
			var originalBtnHtml = $btn.html();

			$btn.prop('disabled', true).html(
				'<span class="dashicons dashicons-update" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span> ' +
				AIPS.Templates.escape(aipsInternalLinksL10n.updating || 'Updating…')
			);

			var insertions = [];

			for (var i = 0; i < this._pendingInsertions.length; i++) {
				var ins = this._pendingInsertions[i];
				insertions.push({
					suggestion_id:       ins.suggestionId,
					match_snippet:       ins.matchSnippet,
					replacement_snippet: ins.replacementSnippet,
				});
			}

			$.post(aipsAjax.ajaxUrl, {
				action:     'aips_internal_links_apply_bulk_insertions',
				nonce:      aipsInternalLinksL10n.nonce,
				insertions: JSON.stringify(insertions),
			}, function (response) {
				$btn.prop('disabled', false).html(originalBtnHtml);

				if (response.success) {
					self._pendingInsertions = [];
					$('#aips-insert-modal').hide();
					AIPS.Utilities.showToast(
						response.data.message || aipsInternalLinksL10n.applied,
						'success'
					);

					if (response.data.errors && response.data.errors.length > 0) {
						AIPS.Utilities.showToast(response.data.errors.join(' '), 'warning');
					}

					self.loadSuggestions();
					self.refreshStatus();
				} else {
					AIPS.Utilities.showToast(
						(response.data && response.data.message) || aipsInternalLinksL10n.updateFailed,
						'error'
					);
				}
			}).fail(function () {
				$btn.prop('disabled', false).html(originalBtnHtml);
				AIPS.Utilities.showToast(aipsInternalLinksL10n.updateFailed, 'error');
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
