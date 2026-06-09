import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseListView } from './base-list';
import { BaseModalView } from './base-modal';

/**
 * Internal Links View
 */
export const InternalLinksView = BaseListView.extend({
	el: 'body',

	listSelector: '#aips-suggestions-tbody',
	rowSelector: '#aips-suggestions-tbody tr',
	searchSelector: '#aips-il-search',
	selectAllSelector: '',
	checkboxSelector: '',
	bulkApplySelector: '',

	currentPage: 1,
	currentStatus: '',
	currentSearch: '',
	perPage: 20,
	_searchTimer: null,
	_previewPlainText: '',
	_previewPostId: 0,
	_pendingInsertions: [],
	_suggestionDataMap: {},

	events: _.extend({}, BaseListView.prototype.events, {
		// Tab navigation
		'click .aips-tab-link': 'onTabClick',

		// Status filter
		'change #aips-il-status-filter': 'onStatusFilterChange',

		// Search
		'input #aips-il-search': 'onSearchInput',
		'click #aips-il-search-clear': 'onSearchClear',

		// Index management
		'click #aips-start-indexing-btn': 'onStartIndexingClick',
		'click #aips-clear-index-btn': 'onClearIndexClick',

		// Per-post generation
		'click #aips-generate-for-post-btn': 'onGenerateForPostClick',
		'click #aips-reindex-post-btn': 'onReindexPostClick',

		// Row actions (delegated)
		'click .aips-il-accept-btn': 'onAcceptClick',
		'click .aips-il-reject-btn': 'onRejectClick',
		'click .aips-il-delete-btn': 'onDeleteClick',
		'click .aips-il-edit-anchor-btn': 'onEditAnchorClick',

		// Insert Link button (accepted rows)
		'click .aips-il-insert-btn': 'onInsertClick',

		// Insert modal: Insert button on a single suggestion entry
		'click .aips-il-modal-insert-btn': 'onModalInsertClick',

		// Insert modal: Apply button on a location result
		'click .aips-il-apply-location-btn': 'onApplyLocationClick',

		// Insert modal: Update Post button
		'click #aips-update-post-btn': 'onUpdatePostClick',

		// Preview insertion: hover & action buttons
		'mouseenter .aips-il-preview-insertion': 'onPreviewHoverIn',
		'mouseleave .aips-il-preview-insertion': 'onPreviewHoverOut',
		'click .aips-il-preview-edit-btn': 'onPreviewEditClick',
		'click .aips-il-preview-undo-btn': 'onPreviewUndoClick',

		// Anchor modal
		'click .aips-modal-close': 'onModalClose',
		'click #aips-anchor-modal-save': 'onAnchorModalSave',

		// Pagination
		'click .aips-page-btn': 'onPageClick'
	}),

	initialize() {
		BaseListView.prototype.initialize.apply(this, arguments);

		// Initialize modals if elements exist in DOM
		if ($('#aips-anchor-modal').length) {
			this.anchorModal = new BaseModalView({ el: '#aips-anchor-modal' });
		}
		if ($('#aips-insert-modal').length) {
			this.insertModal = new BaseModalView({ el: '#aips-insert-modal' });
		}

		if (this.isInternalLinksPage()) {
			this.originalGenerateText = this.$('#aips-generate-for-post-btn').text().trim();
			this.originalReindexText  = this.$('#aips-reindex-post-btn').text().trim();
			this.originalIndexText    = this.$('#aips-start-indexing-btn').text().trim();

			this.loadSuggestions();
		}
	},

	isInternalLinksPage() {
		return this.$('#aips-suggestions-tbody').length > 0;
	},

	onTabClick(e) {
		e.preventDefault();
		const tab = $(e.currentTarget).data('tab');
		this.$('.aips-tab-link').removeClass('active');
		$(e.currentTarget).addClass('active');
		this.$('.aips-tab-content').hide().attr('aria-hidden', 'true');
		this.$('#' + tab + '-tab').show().attr('aria-hidden', 'false');
	},

	onStatusFilterChange(e) {
		this.currentStatus = $(e.currentTarget).val();
		this.currentPage   = 1;
		this.loadSuggestions();
	},

	onSearchInput(e) {
		const val = $(e.currentTarget).val().trim();
		this.$('#aips-il-search-clear').toggle(val.length > 0);

		clearTimeout(this._searchTimer);
		this._searchTimer = setTimeout(() => {
			this.currentSearch = val;
			this.currentPage   = 1;
			this.loadSuggestions();
		}, 400);
	},

	onSearchClear(e) {
		this.$('#aips-il-search').val('').trigger('input');
	},

	onStartIndexingClick(e) {
		this.startIndexing();
	},

	onClearIndexClick(e) {
		const l10n = window.aipsInternalLinksL10n || {};
		if (!window.confirm(l10n.confirmClearIndex || 'Clear index?')) {
			return;
		}
		this.clearIndex();
	},

	onGenerateForPostClick(e) {
		this.generateForPost();
	},

	onReindexPostClick(e) {
		this.reindexPost();
	},

	onAcceptClick(e) {
		const $btn = $(e.currentTarget);
		this.updateStatus($btn.data('id'), 'accepted', $btn.closest('tr'));
	},

	onRejectClick(e) {
		const $btn = $(e.currentTarget);
		this.updateStatus($btn.data('id'), 'rejected', $btn.closest('tr'));
	},

	onDeleteClick(e) {
		const l10n = window.aipsInternalLinksL10n || {};
		if (!window.confirm(l10n.confirmDelete || 'Delete this suggestion?')) {
			return;
		}
		const $btn = $(e.currentTarget);
		this.deleteSuggestion($btn.data('id'), $btn.closest('tr'));
	},

	onEditAnchorClick(e) {
		const $btn = $(e.currentTarget);
		this.$('#aips-anchor-modal-id').val($btn.data('id'));
		this.$('#aips-anchor-modal-text').val($btn.data('anchor'));
		this.$('#aips-anchor-modal-context').val('table');
		
		if (this.anchorModal) {
			this.anchorModal.open();
		}
		this.$('#aips-anchor-modal-text').focus();
	},

	onInsertClick(e) {
		this.openInsertModal($(e.currentTarget).data('id'));
	},

	onModalInsertClick(e) {
		this.findInsertLocations(parseInt($(e.currentTarget).data('id'), 10));
	},

	onApplyLocationClick(e) {
		const $btn    = $(e.currentTarget);
		const sid     = parseInt($btn.data('suggestion-id'), 10);
		const match   = $btn.data('match');
		const replace = $btn.data('replace');
		this.applyInsertionPreview(sid, match, replace, $btn);
	},

	onModalClose(e) {
		if (e) e.preventDefault();
		const $modal = $(e.currentTarget).closest('.aips-modal');

		if ($modal.length) {
			if ($modal.is('#aips-insert-modal')) {
				this._pendingInsertions = [];
				this._previewPlainText  = '';
				this._previewPostId     = 0;
				this.$('#aips-pending-count').text('');
			}
			$modal.hide();
		}
	},

	onAnchorModalSave(e) {
		const context = this.$('#aips-anchor-modal-context').val();
		if (context === 'preview') {
			const id         = parseInt(this.$('#aips-anchor-modal-id').val(), 10);
			const anchorText = this.$('#aips-anchor-modal-text').val().trim();
			if (!id) return;
			this.editPreviewAnchor(id, anchorText);
		} else {
			this.saveAnchorText();
		}
	},

	onPageClick(e) {
		const page = parseInt($(e.currentTarget).data('page'), 10);
		if (page && page !== this.currentPage) {
			this.currentPage = page;
			this.loadSuggestions();
		}
	},

	onUpdatePostClick(e) {
		this.saveAllInsertions();
	},

	onPreviewHoverIn(e) {
		$(e.currentTarget).find('.aips-il-preview-actions').stop(true).show();
	},

	onPreviewHoverOut(e) {
		$(e.currentTarget).find('.aips-il-preview-actions').stop(true).hide();
	},

	onPreviewEditClick(e) {
		e.stopPropagation();
		const sid = parseInt($(e.currentTarget).data('suggestion-id'), 10);
		let currentAnchor = '';

		for (let i = 0; i < this._pendingInsertions.length; i++) {
			if (this._pendingInsertions[i].suggestionId === sid) {
				currentAnchor = this._pendingInsertions[i].anchorText;
				break;
			}
		}

		this.$('#aips-anchor-modal-id').val(sid);
		this.$('#aips-anchor-modal-text').val(currentAnchor);
		this.$('#aips-anchor-modal-context').val('preview');
		
		if (this.anchorModal) {
			this.anchorModal.open();
		}
		this.$('#aips-anchor-modal-text').focus();
	},

	onPreviewUndoClick(e) {
		e.stopPropagation();
		const sid = parseInt($(e.currentTarget).data('suggestion-id'), 10);
		this.undoInsertionPreview(sid);
	},

	loadSuggestions() {
		const $tbody = this.$('#aips-suggestions-tbody');
		const T = window.AIPS.Templates;
		const l10n = window.aipsInternalLinksL10n || {};

		if (T) {
			$tbody.html(T.render('aips-tmpl-il-tbody-loading', {
				message: l10n.loading || 'Loading...'
			}));
		}

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action:   'aips_internal_links_get_suggestions',
			nonce:    l10n.nonce,
			page:     this.currentPage,
			per_page: this.perPage,
			status:   this.currentStatus,
			search:   this.currentSearch
		}, (response) => {
			if (!response.success) {
				if (T) {
					$tbody.html(T.render('aips-tmpl-il-tbody-message', {
						message: l10n.errorLoading || 'Error loading suggestions.'
					}));
				}
				return;
			}

			const data = response.data;

			if (!data.items || data.items.length === 0) {
				if (T) {
					$tbody.html(T.render('aips-tmpl-il-tbody-message', {
						message: l10n.noSuggestions || 'No suggestions.'
					}));
				}
				this.renderPagination(0, 0);
				return;
			}

			$tbody.html('');
			$.each(data.items, (i, item) => {
				$tbody.append(this.renderRow(item));
			});

			this.renderPagination(data.total, data.total_pages);
		}).fail(() => {
			if (T) {
				$tbody.html(T.render('aips-tmpl-il-tbody-message', {
					message: l10n.errorLoading || 'Error loading suggestions.'
				}));
			}
		});
	},

	renderRow(item) {
		const T = window.AIPS.Templates;
		const l10n = window.aipsInternalLinksL10n || {};
		const statusLabel = this.getStatusLabel(item.status);
		const statusClass = 'aips-status-' + item.status;
		const score       = Math.round(parseFloat(item.similarity_score) * 100) + '%';
		const anchor      = T ? T.escape(item.anchor_text || '') : _.escape(item.anchor_text || '');

		const sourceTitle = item.source_post_title || '(#' + item.source_post_id + ')';
		const targetTitle = item.target_post_title || '(#' + item.target_post_id + ')';

		const source = item.source_edit_url && T
			? T.render('aips-tmpl-il-post-link', {
				url:   item.source_edit_url,
				title: sourceTitle
			})
			: (T ? T.escape(sourceTitle) : _.escape(sourceTitle));

		const target = item.target_edit_url && T
			? T.render('aips-tmpl-il-post-link', {
				url:   item.target_edit_url,
				title: targetTitle
			})
			: (T ? T.escape(targetTitle) : _.escape(targetTitle));

		let actions = '';

		if (item.status === 'pending' && T) {
			actions += T.render('aips-tmpl-il-actions-pending', {
				id:           item.id,
				acceptLabel:  l10n.acceptAction || 'Accept',
				rejectLabel:  l10n.rejectAction || 'Reject'
			});
		}

		if (item.status === 'accepted' && T) {
			actions += T.render('aips-tmpl-il-actions-accepted', {
				id:          item.id,
				insertLabel: l10n.insertLink || 'Insert Link'
			});
		}

		if (T) {
			actions += T.render('aips-tmpl-il-actions-edit-delete', {
				id:          item.id,
				anchor:      item.anchor_text || '',
				editLabel:   l10n.editAnchorText || 'Edit Anchor',
				deleteLabel: l10n.deleteSuggestion || 'Delete'
			});
		}

		if (T) {
			return T.renderRaw('aips-tmpl-il-suggestion-row', {
				id:          item.id,
				source:      source,
				target:      target,
				score:       score,
				anchor:      anchor,
				statusClass: statusClass,
				statusLabel: T.escape(statusLabel),
				actions:     actions
			});
		}
		return '';
	},

	renderPagination(total, totalPages) {
		const $wrap    = this.$('#aips-il-page-controls');
		const $toolbar = this.$('#aips-il-pagination');
		const T = window.AIPS.Templates;

		if (totalPages <= 1) {
			$toolbar.hide();
			$wrap.html('');
			return;
		}

		$toolbar.show();
		let html = '';

		if (this.currentPage > 1 && T) {
			html += T.render('aips-tmpl-il-page-btn', {
				page:    this.currentPage - 1,
				classes: 'aips-btn-secondary',
				label:   '\u00ab'
			}) + ' ';
		}

		const start = Math.max(1, this.currentPage - 2);
		const end   = Math.min(totalPages, this.currentPage + 2);

		for (let p = start; p <= end; p++) {
			const classes = p === this.currentPage ? 'aips-btn-primary' : 'aips-btn-secondary';
			if (T) {
				html += T.render('aips-tmpl-il-page-btn', {
					page:    p,
					classes: classes,
					label:   p
				}) + ' ';
			}
		}

		if (this.currentPage < totalPages && T) {
			html += T.render('aips-tmpl-il-page-btn', {
				page:    this.currentPage + 1,
				classes: 'aips-btn-secondary',
				label:   '\u00bb'
			});
		}

		$wrap.html(html);
	},

	startIndexing() {
		const $btn = this.$('#aips-start-indexing-btn');
		const l10n = window.aipsInternalLinksL10n || {};
		const T = window.AIPS.Templates;

		$btn.prop('disabled', true).text(l10n.loading || 'Loading...');

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_internal_links_start_indexing',
			nonce:  l10n.nonce
		}, (response) => {
			if (T) {
				$btn.prop('disabled', false).html(T.render('aips-tmpl-il-btn-start-indexing', {
					label: this.originalIndexText
				}));
			}

			if (response.success) {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(response.data.message, 'success');
				}
				setTimeout(() => { this.refreshStatus(); }, 2000);
			} else {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(
						(response.data && response.data.message) || (l10n.indexingNotAvailable || 'Indexing not available'),
						'error'
					);
				}
			}
		}).fail(() => {
			if (T) {
				$btn.prop('disabled', false).html(T.render('aips-tmpl-il-btn-start-indexing', {
					label: this.originalIndexText
				}));
			}
		});
	},

	clearIndex() {
		const l10n = window.aipsInternalLinksL10n || {};
		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_internal_links_clear_index',
			nonce:  l10n.nonce
		}, (response) => {
			if (response.success) {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(response.data.message, 'success');
				}
				this.loadSuggestions();
				this.refreshStatus();
			} else {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(
						(response.data && response.data.message) || 'Error.',
						'error'
					);
				}
			}
		});
	},

	generateForPost() {
		const postId      = parseInt(this.$('#aips-gen-post-id').val(), 10);
		const maxSugg     = parseInt(this.$('#aips-gen-max-suggestions').val(), 10);
		const threshold   = parseFloat(this.$('#aips-gen-threshold').val());
		const $btn        = this.$('#aips-generate-for-post-btn');
		const $feedback   = this.$('#aips-gen-feedback');
		const l10n = window.aipsInternalLinksL10n || {};
		const T = window.AIPS.Templates;

		if (!postId) {
			this.showGenerateFeedback(l10n.invalidPostId || 'Invalid post ID.', 'error');
			return;
		}

		$btn.prop('disabled', true).text(l10n.generating || 'Generating...');
		$feedback.hide();

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action:          'aips_internal_links_generate_suggestions',
			nonce:           l10n.nonce,
			post_id:         postId,
			max_suggestions: maxSugg || 5,
			threshold:       threshold || 0.70
		}, (response) => {
			if (T) {
				$btn.prop('disabled', false).html(T.render('aips-tmpl-il-btn-generate', {
					label: this.originalGenerateText
				}));
			}

			if (response.success) {
				this.showGenerateFeedback(response.data.message, 'success');
				this.loadSuggestions();
			} else {
				this.showGenerateFeedback(
					(response.data && response.data.message) || 'Error.',
					'error'
				);
			}
		}).fail(() => {
			if (T) {
				$btn.prop('disabled', false).html(T.render('aips-tmpl-il-btn-generate', {
					label: this.originalGenerateText
				}));
			}
			this.showGenerateFeedback(l10n.requestFailed || 'Request failed.', 'error');
		});
	},

	reindexPost() {
		const postId    = parseInt(this.$('#aips-gen-post-id').val(), 10);
		const $btn      = this.$('#aips-reindex-post-btn');
		const $feedback = this.$('#aips-gen-feedback');
		const l10n = window.aipsInternalLinksL10n || {};
		const T = window.AIPS.Templates;

		if (!postId) {
			this.showGenerateFeedback(l10n.invalidPostId || 'Invalid post ID.', 'error');
			return;
		}

		$btn.prop('disabled', true).text(l10n.reindexing || 'Reindexing...');
		$feedback.hide();

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action:  'aips_internal_links_reindex_post',
			nonce:   l10n.nonce,
			post_id: postId
		}, (response) => {
			if (T) {
				$btn.prop('disabled', false).html(T.render('aips-tmpl-il-btn-reindex', {
					label: this.originalReindexText
				}));
			}

			if (response.success) {
				this.showGenerateFeedback(response.data.message, 'success');
				this.loadSuggestions();
				this.refreshStatus();
			} else {
				this.showGenerateFeedback(
					(response.data && response.data.message) || 'Error.',
					'error'
				);
			}
		}).fail(() => {
			if (T) {
				$btn.prop('disabled', false).html(T.render('aips-tmpl-il-btn-reindex', {
					label: this.originalReindexText
				}));
			}
		});
	},

	updateStatus(id, status, $row) {
		const l10n = window.aipsInternalLinksL10n || {};
		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_internal_links_update_status',
			nonce:  l10n.nonce,
			id:     id,
			status: status
		}, (response) => {
			if (response.success) {
				this.loadSuggestions();
				this.refreshStatus();
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.statusUpdated || 'Status updated.', 'success');
				}
			} else {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.statusUpdateFailed || 'Failed to update status.', 'error');
				}
			}
		});
	},

	deleteSuggestion(id, $row) {
		const l10n = window.aipsInternalLinksL10n || {};
		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_internal_links_delete',
			nonce:  l10n.nonce,
			id:     id
		}, (response) => {
			if (response.success) {
				$row.fadeOut(200, () => { $row.remove(); });
				this.refreshStatus();
			} else {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.errorDeleting || 'Error deleting.', 'error');
				}
			}
		});
	},

	saveAnchorText() {
		const id         = parseInt(this.$('#aips-anchor-modal-id').val(), 10);
		const anchorText = this.$('#aips-anchor-modal-text').val().trim();
		const l10n = window.aipsInternalLinksL10n || {};

		if (!id) return;

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action:      'aips_internal_links_update_anchor',
			nonce:       l10n.nonce,
			id:          id,
			anchor_text: anchorText
		}, (response) => {
			if (this.anchorModal) {
				this.anchorModal.close();
			}

			if (response.success) {
				this.$('tr[data-id="' + id + '"] .aips-il-anchor-cell').text(anchorText);
				this.$('tr[data-id="' + id + '"] .aips-il-edit-anchor-btn').data('anchor', anchorText);
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.anchorUpdated || 'Anchor text updated.', 'success');
				}
			} else {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.anchorUpdateFailed || 'Failed to update anchor text.', 'error');
				}
			}
		});
	},

	refreshStatus() {
		const l10n = window.aipsInternalLinksL10n || {};
		const T = window.AIPS.Templates;

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_internal_links_get_status',
			nonce:  l10n.nonce
		}, (response) => {
			if (!response.success) return;

			const idx    = response.data.indexing;
			const counts = response.data.link_counts;

			if (idx) {
				if (T) {
					this.$('#aips-stat-indexed').html(T.render('aips-tmpl-il-indexed-stat', {
						indexed: idx.indexed,
						total:   idx.total_posts
					}));
				}
				this.$('#aips-index-progress-bar').css('width', idx.percent + '%');
			}

			if (counts) {
				this.$('#aips-stat-pending').text(counts.pending || 0);
				this.$('#aips-stat-accepted').text(counts.accepted || 0);
				this.$('#aips-stat-rejected').text(counts.rejected || 0);
			}
		});
	},

	getStatusLabel(status) {
		const l10n = window.aipsInternalLinksL10n || {};
		const map = {
			pending:  l10n.pending || 'Pending',
			accepted: l10n.accepted || 'Accepted',
			rejected: l10n.rejected || 'Rejected',
			inserted: l10n.inserted || 'Inserted'
		};
		return map[status] || status;
	},

	showGenerateFeedback(message, type) {
		const $el = this.$('#aips-gen-feedback');
		$el.removeClass('aips-notice-success aips-notice-error')
			.addClass('aips-notice-' + type)
			.text(message)
			.show();
	},

	openInsertModal(suggestionId) {
		const l10n = window.aipsInternalLinksL10n || {};
		const T = window.AIPS.Templates;
		const spinnerHtml = T ? T.render('aips-tmpl-il-spinner', {}) : 'Loading...';

		this._previewPostId      = 0;
		this._previewPlainText   = '';
		this._pendingInsertions  = [];
		this._suggestionDataMap  = {};

		this.$('#aips-insert-suggestions-list').html(spinnerHtml);
		this.$('#aips-insert-post-content').html(spinnerHtml);
		this.$('#aips-insert-post-title').text('');
		this.$('#aips-update-post-btn').prop('disabled', true);
		this.$('#aips-pending-count').text('');

		if (this.insertModal) {
			this.insertModal.open();
		}

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action:        'aips_internal_links_get_post_for_insertion',
			nonce:         l10n.nonce,
			suggestion_id: suggestionId
		}, (response) => {
			if (!response.success) {
				if (T) {
					this.$('#aips-insert-suggestions-list').html(
						T.render('aips-tmpl-il-notice-error', {
							message: (response.data && response.data.message) || (l10n.loadingFailed || 'Failed to load details.')
						})
					);
				}
				this.$('#aips-insert-post-content').html('');
				return;
			}

			const data = response.data;
			this._previewPostId = data.post_id || 0;
			this._previewPlainText = $('<div>').html(data.post_content || '').text();

			this.$('#aips-insert-post-title').text(data.post_title || '');

			this.renderInsertSuggestions(data.suggestions || []);
			this.renderPreviewContent();
		}).fail(() => {
			if (T) {
				this.$('#aips-insert-suggestions-list').html(
					T.render('aips-tmpl-il-notice-error', {
						message: l10n.loadingFailed || 'Failed to load details.'
					})
				);
			}
			this.$('#aips-insert-post-content').html('');
		});
	},

	renderInsertSuggestions(suggestions) {
		const $list = this.$('#aips-insert-suggestions-list');
		const l10n = window.aipsInternalLinksL10n || {};
		const T = window.AIPS.Templates;

		if (!suggestions || suggestions.length === 0) {
			if (T) {
				$list.html(T.render('aips-tmpl-il-notice-muted', {
					message: l10n.noInsertSuggestions || 'No suggestions to insert.'
				}));
			}
			return;
		}

		let items = '';
		const esc = T ? T.escape : _.escape;

		$.each(suggestions, (i, s) => {
			const suggestionId = parseInt(s.id, 10);
			const score        = Math.round(parseFloat(s.similarity_score) * 100) + '%';
			const title        = s.target_post_title || '#' + s.target_post_id;
			const anchor       = s.anchor_text || s.target_post_title || '';
			const targetUrl    = s.target_url || '';

			this._suggestionDataMap[suggestionId] = {
				anchorText: anchor,
				targetUrl:  targetUrl,
				title:      title
			};

			const targetLinkHtml = targetUrl && T
				? T.render('aips-tmpl-il-insert-target-link', { url: targetUrl })
				: '';

			if (T) {
				items += T.renderRaw('aips-tmpl-il-insert-suggestion', {
					suggestionId:            suggestionId,
					title:                   esc(title),
					anchorLabel:             esc(l10n.anchorLabel || 'Anchor'),
					anchor:                  esc(anchor),
					score:                   score,
					targetLinkHtml:          targetLinkHtml,
					insertBtn:               esc(l10n.insertBtn || 'Find Locations'),
					insertionLocationsLabel: esc(l10n.insertionLocationsLabel || 'Locations:')
				});
			}
		});

		if (T) {
			$list.html(T.renderRaw('aips-tmpl-il-suggestions-list', { items: items }));
		}
	},

	findInsertLocations(suggestionId) {
		const l10n = window.aipsInternalLinksL10n || {};
		const T = window.AIPS.Templates;
		const $item = this.$('.aips-il-suggestion-item[data-suggestion-id="' + suggestionId + '"]');
		const $panel = $item.find('.aips-il-inline-locations');
		const $list = $item.find('.aips-il-inline-locations-list');
		const $spinner = $item.find('.aips-il-inline-spinner');
		const $count = $item.find('.aips-il-inline-count');
		const $button = $item.find('.aips-il-modal-insert-btn');

		$panel.show();
		if (T) {
			$list.html(T.render('aips-tmpl-il-notice-muted', {
				message: l10n.findingLocations || 'Finding insertion locations...'
			}));
		}
		$count.text('');
		$spinner.addClass('is-active');
		$button.prop('disabled', true);

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action:        'aips_internal_links_find_insert_locations',
			nonce:         l10n.nonce,
			suggestion_id: suggestionId
		}, (response) => {
			$spinner.removeClass('is-active');
			$button.prop('disabled', false);

			if (!response.success) {
				if (T) {
					$list.html(T.render('aips-tmpl-il-notice-error', {
						message: (response.data && response.data.message) || (l10n.locationsFailed || 'Locations search failed.')
					}));
				}
				$count.text('');
				return;
			}

			const locations = response.data.locations || [];
			const requestedCount = parseInt(response.data.requested_count, 10) || locations.length;
			let aiReturnedCount = parseInt(response.data.ai_returned_count, 10);
			let validCount = parseInt(response.data.valid_count, 10);

			if (isNaN(aiReturnedCount)) {
				aiReturnedCount = locations.length;
			}

			if (isNaN(validCount)) {
				validCount = locations.length;
			}

			this.renderInsertLocations(suggestionId, locations, requestedCount, aiReturnedCount, validCount);
		}).fail(() => {
			$spinner.removeClass('is-active');
			$button.prop('disabled', false);
			if (T) {
				$list.html(T.render('aips-tmpl-il-notice-error', {
					message: l10n.locationsFailed || 'Locations search failed.'
				}));
			}
			$count.text('');
		});
	},

	renderInsertLocations(suggestionId, locations, requestedCount, aiReturnedCount, validCount) {
		const $item = this.$('.aips-il-suggestion-item[data-suggestion-id="' + suggestionId + '"]');
		const $list = $item.find('.aips-il-inline-locations-list');
		const $count = $item.find('.aips-il-inline-count');
		const l10n = window.aipsInternalLinksL10n || {};
		const T = window.AIPS.Templates;

		$count.text(this.formatCountLabel(validCount, aiReturnedCount));

		if (!locations || locations.length === 0) {
			const detailMessage = aiReturnedCount > 0
				? this.formatAiReturnedLabel(aiReturnedCount)
				: (l10n.zeroSuggestionsReturned || 'No suggestions returned.');

			if (T) {
				$list.html(T.render('aips-tmpl-il-no-locations', {
					zeroReturned: detailMessage,
					noLocations:  aiReturnedCount > 0 ? (l10n.invalidLocationsHint || 'Invalid locations.') : (l10n.noLocations || 'No locations.')
				}));
			}
			return;
		}

		let html = '';
		const esc = T ? T.escape : _.escape;

		$.each(locations, (i, loc) => {
			const num     = i + 1;
			const reason  = loc.reason || '';
			const preview = this.formatReplacementPreview(loc.replacement_snippet || '');

			const reasonHtml = reason && T
				? T.render('aips-tmpl-il-location-reason', {
					reasonLabel: l10n.reasonLabel || 'Reason:',
					reason:      reason
				})
				: '';

			if (T) {
				html += T.renderRaw('aips-tmpl-il-location-card', {
					optionLabel:          esc(l10n.optionLabel || 'Option'),
					num:                  num,
					reasonHtml:           reasonHtml,
					withLinkLabel:        esc(l10n.withLinkLabel || 'With link:'),
					preview:              preview,
					suggestionId:         parseInt(suggestionId, 10),
					matchRaw:             esc(loc.match_snippet || ''),
					replaceRaw:           esc(loc.replacement_snippet || ''),
					applyBtn:             esc(l10n.applyBtn || 'Apply')
				});
			}
		});

		$list.html(html);
	},

	formatCountLabel(validCount, aiReturnedCount) {
		const l10n = window.aipsInternalLinksL10n || {};
		const template = l10n.returnedCountLabel || 'Showing %1$d valid of %2$d AI suggestions';
		return template
			.replace('%1$d', String(validCount))
			.replace('%2$d', String(aiReturnedCount));
	},

	formatAiReturnedLabel(aiReturnedCount) {
		const l10n = window.aipsInternalLinksL10n || {};
		const template = l10n.aiSuggestionsReturned || 'AI returned %d suggestion(s)';
		return template.replace('%d', String(aiReturnedCount));
	},

	formatReplacementPreview(replacementSnippet) {
		const T = window.AIPS.Templates;
		const escaped = T ? T.escape(replacementSnippet || '') : _.escape(replacementSnippet || '');
		return srcHtmlReplace(escaped);

		function srcHtmlReplace(str) {
			return str.replace(/\[\[(.*?)\]\]/g, '<mark style="background:#fff1c6;padding:0 2px;border-radius:2px;">$1</mark>');
		}
	},

	applyInsertionPreview(suggestionId, matchSnippet, replacementSnippet, $btn) {
		const l10n = window.aipsInternalLinksL10n || {};
		for (let i = 0; i < this._pendingInsertions.length; i++) {
			if (this._pendingInsertions[i].suggestionId === suggestionId) {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(
						l10n.alreadyApplied || 'Already applied.',
						'info'
					);
				}
				return;
			}
		}

		if (this._previewPlainText.indexOf(matchSnippet) === -1) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(
					l10n.snippetNotFound || 'Text not found in content preview.',
					'error'
				);
			}
			return;
		}

		const markerMatch = replacementSnippet.match(/\[\[([\s\S]*?)\]\]/);
		let anchorText  = markerMatch ? markerMatch[1] : '';

		if (!anchorText && this._suggestionDataMap[suggestionId]) {
			anchorText = this._suggestionDataMap[suggestionId].anchorText || '';
		}

		this._pendingInsertions.push({
			suggestionId:        suggestionId,
			matchSnippet:        matchSnippet,
			replacementSnippet:  replacementSnippet,
			anchorText:          anchorText
		});

		this.renderPreviewContent();
		this.updatePendingCount();

		this.$('#aips-update-post-btn').prop('disabled', false);

		const T = window.AIPS.Templates;
		const esc = T ? T.escape : _.escape;
		$btn.prop('disabled', true).html(
			'<span class="dashicons dashicons-yes" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span> ' +
			esc(l10n.applied || 'Applied')
		);
	},

	renderPreviewContent() {
		const T = window.AIPS.Templates;
		const l10n = window.aipsInternalLinksL10n || {};
		let html = T ? T.escape(this._previewPlainText || '') : _.escape(this._previewPlainText || '');

		if (!html) {
			if (T) {
				this.$('#aips-insert-post-content').html(
					T.render('aips-tmpl-il-notice-muted', {
						message: l10n.noContent || 'No content.'
					})
				);
			}
			return;
		}

		for (let i = 0; i < this._pendingInsertions.length; i++) {
			const ins          = this._pendingInsertions[i];
			const escapedMatch = T ? T.escape(ins.matchSnippet) : _.escape(ins.matchSnippet);

			const idx = html.indexOf(escapedMatch);
			if (idx === -1) continue;

			const repSnippet  = ins.replacementSnippet || '';
			const parts       = repSnippet.match(/^([\s\S]*?)\[\[([\s\S]*?)\]\]([\s\S]*)$/);
			const before      = parts ? parts[1] : '';
			const anchor      = parts ? parts[2] : (ins.anchorText || '');
			const after       = parts ? parts[3] : '';

			if (T) {
				const insertionHtml = T.render('aips-tmpl-il-preview-insertion', {
					suggestionId: ins.suggestionId,
					matchEsc:     ins.matchSnippet,
					before:       before,
					anchor:       anchor,
					after:        after,
					editLabel:    l10n.editInsertedLink || 'Edit anchor',
					undoLabel:    l10n.removeInsertedLink || 'Remove link'
				});

				html = html.substring(0, idx) + insertionHtml + html.substring(idx + escapedMatch.length);
			}
		}

		this.$('#aips-insert-post-content').html(html);
	},

	undoInsertionPreview(suggestionId) {
		const l10n = window.aipsInternalLinksL10n || {};
		let idx = -1;

		for (let i = 0; i < this._pendingInsertions.length; i++) {
			if (this._pendingInsertions[i].suggestionId === suggestionId) {
				idx = i;
				break;
			}
		}

		if (idx === -1) return;

		this._pendingInsertions.splice(idx, 1);
		this.renderPreviewContent();
		this.updatePendingCount();

		this.$('.aips-il-apply-location-btn[data-suggestion-id="' + suggestionId + '"]')
			.prop('disabled', false)
			.text(l10n.applyBtn || 'Apply');

		if (this._pendingInsertions.length === 0) {
			this.$('#aips-update-post-btn').prop('disabled', true);
		}
	},

	editPreviewAnchor(suggestionId, newAnchor) {
		const l10n = window.aipsInternalLinksL10n || {};
		const safeAnchor = String(newAnchor).replace(/\]\]/g, '');

		for (let i = 0; i < this._pendingInsertions.length; i++) {
			if (this._pendingInsertions[i].suggestionId === suggestionId) {
				this._pendingInsertions[i].anchorText         = safeAnchor;
				this._pendingInsertions[i].replacementSnippet = this._pendingInsertions[i].replacementSnippet.replace(
					/\[\[[\s\S]*?\]\]/,
					'[[' + safeAnchor + ']]'
				);
				break;
			}
		}

		this.renderPreviewContent();
		if (this.anchorModal) {
			this.anchorModal.close();
		}
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.showToast(l10n.anchorUpdated || 'Anchor text updated.', 'success');
		}
	},

	updatePendingCount() {
		const n = this._pendingInsertions.length;
		const l10n = window.aipsInternalLinksL10n || {};

		if (n === 0) {
			this.$('#aips-pending-count').text('');
			return;
		}

		const template = n === 1
			? (l10n.pendingCountSingle || '%d pending insertion')
			: (l10n.pendingCountPlural || '%d pending insertions');

		this.$('#aips-pending-count').text(template.replace('%d', String(n)));
	},

	saveAllInsertions() {
		const l10n = window.aipsInternalLinksL10n || {};
		const T = window.AIPS.Templates;

		if (this._pendingInsertions.length === 0) return;

		const $btn            = this.$('#aips-update-post-btn');
		const originalBtnHtml = $btn.html();

		const updatingLabel = l10n.updating || 'Updating…';
		const esc = T ? T.escape : _.escape;
		$btn.prop('disabled', true).html(
			'<span class="dashicons dashicons-update" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span> ' +
			esc(updatingLabel)
		);

		const insertions = [];

		for (let i = 0; i < this._pendingInsertions.length; i++) {
			const ins = this._pendingInsertions[i];
			insertions.push({
				suggestion_id:       ins.suggestionId,
				match_snippet:       ins.matchSnippet,
				replacement_snippet: ins.replacementSnippet
			});
		}

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action:     'aips_internal_links_apply_bulk_insertions',
			nonce:      l10n.nonce,
			insertions: JSON.stringify(insertions)
		}, (response) => {
			$btn.prop('disabled', false).html(originalBtnHtml);

			if (response.success) {
				this._pendingInsertions = [];
				if (this.insertModal) {
					this.insertModal.close();
				}
				
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(
						response.data.message || (l10n.applied || 'Applied successfully.'),
						'success'
					);
				}

				if (response.data.errors && response.data.errors.length > 0 && window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(response.data.errors.join(' '), 'warning');
				}

				this.loadSuggestions();
				this.refreshStatus();
			} else {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(
						(response.data && response.data.message) || (l10n.updateFailed || 'Failed to update post.'),
						'error'
					);
				}
			}
		}).fail(() => {
			$btn.prop('disabled', false).html(originalBtnHtml);
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.updateFailed || 'Failed to update post.', 'error');
			}
		});
	}
});
