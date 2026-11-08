/**
 * Admin Post Review – Pending Review posts management JS.
 *
 * Handles publish / delete / regenerate / bulk actions for draft posts
 * awaiting review, as well as the post-preview modal.
 *
 * @package AI_Post_Scheduler
 * @since   1.7.3
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	// -----------------------------------------------------------------
	// PostReview module
	// -----------------------------------------------------------------
	AIPS.PostReview = {

		/** @type {number} Current page for cards pagination */
		currentPage: 1,

		/** @type {number} Total pages available */
		totalPages: 1,

		/** @type {boolean} Guard against concurrent AJAX requests */
		isLoading: false,

		/** @type {number} Track auto-scroll count for hybrid infinite scroll */
		autoScrollPagesLoaded: 0,

		/** @type {number|null} Debounce timer ID for live search */
		searchTimer: null,

		/**
		 * Bootstrap the Post Review page.
		 *
		 * @return {void}
		 */
		init: function () {
			var $grid = $('#aips-pending-posts-grid');
			if ($grid.length) {
				this.currentPage = parseInt($grid.data('current-page'), 10) || 1;
				this.totalPages  = parseInt($grid.data('total-pages'), 10) || 1;
			}
			this.bindEvents();
			this.initInfiniteScroll();

			var savedView = 'cards';
			try {
				savedView = localStorage.getItem('aips_pending_review_view') || 'cards';
			} catch (e) {}
			this.setView(savedView);
			this.updateSelectionState();
		},

		/**
		 * Row checkboxes of the Pending Review cards or table.
		 *
		 * @return {jQuery}
		 */
		rowCheckboxes: function () {
			return $('.aips-pending-posts-grid .aips-post-checkbox, #aips-post-review-form tbody .aips-post-checkbox');
		},

		/**
		 * Bind all UI event listeners.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			// Select-all checkbox (cards toolbar or WP_List_Table header)
			$(document).on('change', '#aips-select-all-cards, input[id^="cb-select-all-"]', this.onSelectAllChange.bind(this));

			// Individual card / row checkbox
			$(document).on('change', '.aips-post-checkbox', this.onCheckboxChange.bind(this));

			// Open post-preview modal
			$(document).on('click', '.aips-preview-post, .aips-preview-trigger', this.onPreviewClick.bind(this));
			$(document).on('click', '.aips-edit-post', this.onEditClick.bind(this));

			// Single-item actions
			$(document).on('click', '.aips-publish-post',    this.onPublishClick.bind(this));
			$(document).on('click', '.aips-delete-post',     this.onDeleteClick.bind(this));
			$(document).on('click', '.aips-regenerate-post', this.onRegenerateClick.bind(this));
			$(document).on('keydown', this.onDocumentKeyDown.bind(this));

			// Bulk actions
			$(document).on('click', '#aips-bulk-action-btn', this.onBulkAction.bind(this));
			$(document).on('change', '#aips-floating-select-all', this.onFloatingSelectAllChange.bind(this));
			$(document).on('click', '#aips-floating-bulk-apply', this.onFloatingBulkApply.bind(this));
			$(document).on('click', '#aips-floating-clear-selection', this.onClearSelection.bind(this));

			// Reload button
			$(document).on('click', '#aips-reload-posts-btn', this.onReloadClick.bind(this));

			// Load More button
			$(document).on('click', '#aips-pending-load-more-btn', this.onLoadMoreClick.bind(this));

			// Live Filter & Search
			$(document).on('input', '#aips-post-search-input', this.onSearchInput.bind(this));
			$(document).on('change', '#aips-filter-template, #aips-filter-post-type-review', this.onFilterChange.bind(this));
			$(document).on('submit', '#aips-pending-filter-form', function (e) {
				e.preventDefault();
				AIPS.PostReview.applyFilters();
			});
			$(document).on('click', '#aips-pending-clear-filters', this.onClearFilters.bind(this));

			// View toggle (cards vs list)
			$(document).on('click', '.aips-view-toggle-btn', this.onViewToggleClick.bind(this));

			// Close preview modal
			$(document).on('click', '#aips-post-preview-modal .aips-modal-close, #aips-post-preview-modal .aips-modal-overlay', this.closePreviewModal.bind(this));

			// Pagination page-jump (legacy toolbar support)
			$(document).on('keydown', '.aips-review-pagination .current-page', this.onPageJump.bind(this));
		},

		/**
		 * Attach window scroll event for hybrid infinite scrolling.
		 * Auto-loads the next page on scroll for up to 2 extra pages, then prompts the user with Load More.
		 *
		 * @return {void}
		 */
		initInfiniteScroll: function () {
			var self = this;
			$(window).on('scroll.aipsPendingReview', function () {
				if (self.isLoading || self.currentPage >= self.totalPages) {
					return;
				}
				// Hybrid mode: allow 2 automatic scrolls (pages 2 and 3)
				if (self.autoScrollPagesLoaded >= 2) {
					return;
				}

				var $sentinel = $('#aips-pending-load-more-wrap');
				if (!$sentinel.length || !$sentinel.is(':visible')) {
					return;
				}

				var windowBottom = $(window).scrollTop() + $(window).height();
				var sentinelTop  = $sentinel.offset().top;

				if (windowBottom >= sentinelTop - 300) {
					self.autoScrollPagesLoaded++;
					self.fetchPosts(self.currentPage + 1, false);
				}
			});
		},

		/**
		 * Handle click on layout view toggle (cards vs list).
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onViewToggleClick: function (e) {
			e.preventDefault();
			var view = $(e.currentTarget).data('view');
			if (view) {
				this.setView(view);
			}
		},

		/**
		 * Switch between cards and list views.
		 *
		 * @param {string} view 'cards' or 'list'.
		 * @return {void}
		 */
		setView: function (view) {
			$('.aips-view-toggle-btn').removeClass('is-active').attr('aria-pressed', 'false');
			$('.aips-view-toggle-btn[data-view="' + view + '"]').addClass('is-active').attr('aria-pressed', 'true');

			if (view === 'list') {
				$('#aips-pending-posts-wrap').hide();
				$('#aips-pending-list-wrap').show();
				$('#aips-pending-cards-count').hide();
				$('#aips-pending-list-pagination').show();
				$('#aips-select-all-cards-wrap').hide();
			} else {
				$('#aips-pending-list-wrap').hide();
				$('#aips-pending-posts-wrap').show();
				$('#aips-pending-list-pagination').hide();
				$('#aips-pending-cards-count').show();
				$('#aips-select-all-cards-wrap').show();
			}

			try {
				localStorage.setItem('aips_pending_review_view', view);
			} catch (e) {}
		},

		// -----------------------------------------------------------------
		// Event handlers – Select / checkbox
		// -----------------------------------------------------------------

		/**
		 * Navigate to the page typed into the pagination input on Enter.
		 *
		 * @param {Event} e Keydown event.
		 * @return {void}
		 */
		onPageJump: function (e) {
			if (e.key !== 'Enter') {
				return;
			}
			e.preventDefault();

			var totalPages = parseInt($('.aips-review-pagination .total-pages').first().text(), 10) || 1;
			var page       = Math.min(Math.max(parseInt($(e.currentTarget).val(), 10) || 1, 1), totalPages);
			var url        = new URL(window.location.href);

			url.searchParams.set('paged', page);
			window.location.href = url.toString();
		},

		/**
		 * Synchronize selection state across top toolbar, cards, and floating bulk bar.
		 *
		 * @return {void}
		 */
		updateSelectionState: function () {
			var $rows           = this.rowCheckboxes();
			var checkedCount    = $rows.filter(':checked').length;
			var totalCount      = parseInt($('#aips-pending-total-count').text(), 10) || $rows.length;
			var allChecked      = $rows.length > 0 && $rows.length === checkedCount;
			var isIndeterminate = checkedCount > 0 && !allChecked;

			$('#aips-select-all-cards, input[id^="cb-select-all-"]').prop('checked', allChecked).prop('indeterminate', isIndeterminate);
			$('#aips-floating-select-all').prop('checked', allChecked).prop('indeterminate', isIndeterminate);

			if (checkedCount > 0) {
				$('#aips-floating-selected-count').text(checkedCount);
				$('#aips-floating-total-count').text(totalCount);
				$('#aips-floating-bulk-bar').addClass('is-active').attr('aria-hidden', 'false');
			} else {
				$('#aips-floating-bulk-bar').removeClass('is-active').attr('aria-hidden', 'true');
			}
		},

		/**
		 * Sync all card / row checkboxes when the select-all header checkbox changes.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onSelectAllChange: function (e) {
			var isChecked = $(e.currentTarget).prop('checked');
			this.rowCheckboxes().prop('checked', isChecked);
			$('.aips-post-card').toggleClass('is-selected', isChecked);
			this.updateSelectionState();
		},

		/**
		 * Sync all card / row checkboxes when the floating select-all checkbox changes.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onFloatingSelectAllChange: function (e) {
			var isChecked = $(e.currentTarget).prop('checked');
			this.rowCheckboxes().prop('checked', isChecked);
			$('.aips-post-card').toggleClass('is-selected', isChecked);
			this.updateSelectionState();
		},

		/**
		 * Update the selection state after an individual row/card checkbox changes.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onCheckboxChange: function (e) {
			var $cb = $(e.currentTarget);
			$cb.closest('.aips-post-card').toggleClass('is-selected', $cb.prop('checked'));
			this.updateSelectionState();
		},

		/**
		 * Clear all card selections and dismiss the floating bulk bar.
		 *
		 * @param {Event} [e] Optional click or keyboard event.
		 * @return {void}
		 */
		onClearSelection: function (e) {
			if (e && e.preventDefault) {
				e.preventDefault();
			}
			this.rowCheckboxes().prop('checked', false);
			$('.aips-post-card').removeClass('is-selected');
			this.updateSelectionState();
		},

		// -----------------------------------------------------------------
		// Event handlers – Single-row actions
		// -----------------------------------------------------------------

		/**
		 * Open the preview modal for the clicked post row.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onPreviewClick: function (e) {
			e.preventDefault();
			this.previewPost($(e.currentTarget).data('post-id'));
		},

		/**
		 * Open the post editor in a new tab.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onEditClick: function (e) {
			e.preventDefault();
			var editUrl = $(e.currentTarget).data('edit-url');
			if (editUrl) {
				window.open(editUrl, '_blank', 'noopener');
			}
		},

		/**
		 * Close overflow menus when pressing Escape.
		 *
		 * @param {KeyboardEvent} e Keyboard event.
		 * @return {void}
		 */
		onDocumentKeyDown: function (e) {
			if (e.key === 'Escape') {
				AIPS.closeAllRowActionMenus();
				if ($('#aips-floating-bulk-bar').hasClass('is-active')) {
					this.onClearSelection(e);
				}
			}
		},

		/**
		 * Publish a single draft post immediately (1-click, without confirmation modal).
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onPublishClick: function (e) {
			e.preventDefault();
			var $btn   = $(e.currentTarget);
			var postId = $btn.data('post-id');
			var $card  = $btn.closest('.aips-post-card, tr');

			if ($card.hasClass('is-processing')) {
				return;
			}
			$card.addClass('is-processing');
			AIPS.Utilities.setButtonLoading($btn, aipsPostReviewL10n.loading || 'Publishing...');

			$.ajax({
				url:  aipsPostReviewL10n.ajaxUrl,
				type: 'POST',
				data: {
					action:  'aips_publish_post',
					post_id: postId,
					nonce:   aipsPostReviewL10n.nonce,
				},
				success: function (response) {
					if (response.success) {
						var rawMsg  = response.data.message || aipsPostReviewL10n.publishSuccess;
						var safeMsg = $('<div>').text(rawMsg).html();
						if (response.data.post_id) {
							var editUrl  = 'post.php?post=' + encodeURIComponent(response.data.post_id) + '&action=edit';
							var safeLink = '<a href="' + editUrl.replace(/"/g, '&quot;') + '" target="_blank">Edit Post</a>';
							AIPS.Utilities.showToast(safeMsg + ' ' + safeLink, 'success', { isHtml: true });
						} else {
							AIPS.Utilities.showToast(safeMsg, 'success');
						}
						$card.fadeOut(400, function () {
							$(this).remove();
							AIPS.PostReview.updateDraftCount();
							AIPS.PostReview.checkEmptyState();
						});
					} else {
						$card.removeClass('is-processing');
						AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.publishError, 'error');
						AIPS.Utilities.resetButton($btn);
					}
				},
				error: function () {
					$card.removeClass('is-processing');
					AIPS.Utilities.showToast(aipsPostReviewL10n.publishError, 'error');
					AIPS.Utilities.resetButton($btn);
				},
			});
		},

		/**
		 * Confirm then delete a single draft post.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onDeleteClick: function (e) {
			e.preventDefault();
			var $btn      = $(e.currentTarget);
			var postId    = $btn.data('post-id');
			var historyId = $btn.data('history-id');
			var $row      = $btn.closest('.aips-post-card, tr');

			AIPS.Utilities.confirm(aipsPostReviewL10n.confirmDelete, 'Notice', [
				{ label: 'No, cancel',  className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function () {
					AIPS.Utilities.setButtonLoading($btn, aipsPostReviewL10n.deleting || 'Deleting...');

					$.ajax({
						url:  aipsPostReviewL10n.ajaxUrl,
						type: 'POST',
						data: {
							action:     'aips_delete_draft_post',
							post_id:    postId,
							history_id: historyId,
							nonce:      aipsPostReviewL10n.nonce,
						},
						success: function (response) {
							if (response.success) {
								AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.deleteSuccess, 'success');
								$row.fadeOut(400, function () {
									$(this).remove();
									AIPS.PostReview.updateDraftCount();
									AIPS.PostReview.checkEmptyState();
								});
							} else {
								AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.deleteError, 'error');
								AIPS.Utilities.resetButton($btn);
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsPostReviewL10n.deleteError, 'error');
							AIPS.Utilities.resetButton($btn);
						},
					});
				} },
			]);
		},

		/**
		 * Confirm then regenerate a single draft post.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onRegenerateClick: function (e) {
			e.preventDefault();
			var $btn      = $(e.currentTarget);
			var historyId = $btn.data('history-id');
			var $row      = $btn.closest('.aips-post-card, tr');

			AIPS.Utilities.confirm(aipsPostReviewL10n.confirmRegenerate, 'Notice', [
				{ label: 'No, cancel',      className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, regenerate', className: 'aips-btn aips-btn-primary', action: function () {
					AIPS.Utilities.setButtonLoading($btn, aipsPostReviewL10n.regenerating || 'Regenerating...');

					$.ajax({
						url:  aipsPostReviewL10n.ajaxUrl,
						type: 'POST',
						data: {
							action:     'aips_regenerate_post',
							history_id: historyId,
							nonce:      aipsPostReviewL10n.nonce,
						},
						success: function (response) {
							if (response.success) {
								var msg = response.data.message || aipsPostReviewL10n.regenerateSuccess;
								AIPS.Utilities.showToast(msg + ' Check History for progress.', 'success');
								$row.fadeOut(400, function () {
									$(this).remove();
									AIPS.PostReview.updateDraftCount();
									AIPS.PostReview.checkEmptyState();
								});
							} else {
								AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.regenerateError, 'error');
								AIPS.Utilities.resetButton($btn);
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsPostReviewL10n.regenerateError, 'error');
							AIPS.Utilities.resetButton($btn);
						},
					});
				} },
			]);
		},

		// -----------------------------------------------------------------
		// Event handlers – Bulk
		// -----------------------------------------------------------------

		/**
		 * Dispatch the selected bulk action to the appropriate handler.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onBulkAction: function (e) {
			e.preventDefault();

			var action = $('#bulk-action-selector-top').val();
			if (!action) {
				AIPS.Utilities.showToast(aipsPostReviewL10n.noActionSelected || 'Please select a bulk action.', 'warning');
				return;
			}

			var checkedBoxes = this.rowCheckboxes().filter(':checked');
			if (checkedBoxes.length === 0) {
				AIPS.Utilities.showToast(aipsPostReviewL10n.noPostsSelected, 'warning');
				return;
			}

			if (action === 'publish') {
				this.bulkPublish(checkedBoxes);
			} else if (action === 'delete') {
				this.bulkDelete(checkedBoxes);
			} else if (action === 'regenerate') {
				this.bulkRegenerate(checkedBoxes);
			}
		},

		/**
		 * Dispatch the selected bulk action from the floating toolbar.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onFloatingBulkApply: function (e) {
			e.preventDefault();

			var action = $('#aips-floating-bulk-action').val();
			if (!action) {
				AIPS.Utilities.showToast(aipsPostReviewL10n.noActionSelected || 'Please select a bulk action.', 'warning');
				return;
			}

			var checkedBoxes = this.rowCheckboxes().filter(':checked');
			if (checkedBoxes.length === 0) {
				AIPS.Utilities.showToast(aipsPostReviewL10n.noPostsSelected, 'warning');
				return;
			}

			if (action === 'publish') {
				this.bulkPublish(checkedBoxes);
			} else if (action === 'delete') {
				this.bulkDelete(checkedBoxes);
			} else if (action === 'regenerate') {
				this.bulkRegenerate(checkedBoxes);
			}
		},

		// -----------------------------------------------------------------
		// Event handlers – Misc
		// -----------------------------------------------------------------

		/**
		 * Refresh the Pending Review list in place when the reload button is clicked.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onReloadClick: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			AIPS.Utilities.setButtonLoading($btn, aipsPostReviewL10n.reloading || 'Reloading...');
			this.refreshList(function () {
				AIPS.Utilities.resetButton($btn);
			});
		},

		/**
		 * Handler for search input (debounced by 300ms).
		 *
		 * @return {void}
		 */
		onSearchInput: function () {
			var self = this;
			if (this.searchTimer) {
				clearTimeout(this.searchTimer);
			}
			this.searchTimer = setTimeout(function () {
				self.applyFilters();
			}, 300);
		},

		/**
		 * Handler for filter dropdown change.
		 *
		 * @return {void}
		 */
		onFilterChange: function () {
			this.applyFilters();
		},

		/**
		 * Clear active filters and search.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onClearFilters: function (e) {
			e.preventDefault();
			$('#aips-filter-template').val('');
			$('#aips-filter-post-type-review').val('');
			$('#aips-post-search-input').val('');
			$('#aips-pending-clear-filters').addClass('aips-hidden');
			this.applyFilters();
		},

		/**
		 * Apply filters and reload cards from page 1.
		 *
		 * @return {void}
		 */
		applyFilters: function () {
			this.autoScrollPagesLoaded = 0;
			var hasFilters = !!$('#aips-filter-template').val() || !!$('#aips-filter-post-type-review').val() || !!$('#aips-post-search-input').val();
			$('#aips-pending-clear-filters').toggleClass('aips-hidden', !hasFilters);
			this.fetchPosts(1, true);
		},

		/**
		 * Handler for Load More button click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onLoadMoreClick: function (e) {
			e.preventDefault();
			if (this.isLoading || this.currentPage >= this.totalPages) {
				return;
			}
			this.fetchPosts(this.currentPage + 1, false);
		},

		/**
		 * Fetch draft posts from server via AJAX.
		 *
		 * @param {number} page Page number to fetch.
		 * @param {boolean} reset Whether to clear existing cards or append.
		 * @param {Function} [callback] Optional completion callback.
		 * @return {void}
		 */
		fetchPosts: function (page, reset, callback) {
			var self = this;
			if (self.isLoading) {
				return;
			}
			self.isLoading = true;

			var $grid         = $('#aips-pending-posts-grid');
			var $loadMoreWrap = $('#aips-pending-load-more-wrap');
			var $loadMoreBtn  = $('#aips-pending-load-more-btn');
			var $emptyWrap    = $('#aips-pending-empty-state-wrap');
			var $postsWrap    = $('#aips-pending-posts-wrap');
			var $toolbar      = $('.aips-pending-toolbar');

			if (reset) {
				$grid.css('opacity', '0.5');
			} else {
				$loadMoreBtn.addClass('is-loading');
			}

			var templateId = $('#aips-filter-template').val() || 0;
			var postType   = $('#aips-filter-post-type-review').val() || '';
			var search     = $('#aips-post-search-input').val() || '';

			$.ajax({
				url:  aipsPostReviewL10n.ajaxUrl,
				type: 'POST',
				data: {
					action:      'aips_get_draft_posts',
					page:        page,
					per_page:    12,
					template_id: templateId,
					post_type:   postType,
					search:      search,
					nonce:       aipsPostReviewL10n.nonce,
				},
				success: function (response) {
					$grid.css('opacity', '1');
					$loadMoreBtn.removeClass('is-loading');
					self.isLoading = false;

					if (response && response.success && response.data) {
						var data  = response.data;
						var items = data.items || [];
						self.currentPage = parseInt(data.current_page, 10) || page;
						self.totalPages  = parseInt(data.pages, 10) || 1;
						var totalItems   = parseInt(data.total, 10) || 0;

						var cardsHtml = '';
						var rowsHtml  = '';
						for (var i = 0; i < items.length; i++) {
							cardsHtml += self.renderCardHtml(items[i]);
							rowsHtml  += self.renderTableRowHtml(items[i]);
						}

						if (reset) {
							$grid.html(cardsHtml);
							$('#aips-post-review-form tbody').html(rowsHtml);
							$('input[id^="cb-select-all-"], #aips-select-all-cards').prop('checked', false);
						} else {
							$grid.append(cardsHtml);
							$('#aips-post-review-form tbody').append(rowsHtml);
						}

						var currentLoaded = $grid.find('.aips-post-card').length;
						$('#aips-pending-loaded-count').text(currentLoaded);
						$('#aips-pending-total-count').text(totalItems);
						$('.aips-page-header .aips-page-summary-strip .aips-summary-chip-value').first().text(totalItems);
						$('#aips-pending-review-tab .aips-filter-bar').data('total', totalItems);

						if (totalItems > 0 && currentLoaded > 0) {
							var currentView = 'cards';
							try {
								currentView = localStorage.getItem('aips_pending_review_view') || 'cards';
							} catch (e) {}

							if (currentView === 'list') {
								$('#aips-pending-list-wrap').show();
								$postsWrap.hide();
								$('#aips-select-all-cards-wrap').hide();
							} else {
								$postsWrap.show();
								$('#aips-pending-list-wrap').hide();
								$('#aips-select-all-cards-wrap').show();
							}
							$toolbar.show();
							$emptyWrap.hide();
							if (self.currentPage < self.totalPages) {
								$loadMoreWrap.show();
							} else {
								$loadMoreWrap.hide();
							}
						} else {
							$postsWrap.hide();
							$('#aips-pending-list-wrap').hide();
							$toolbar.hide();
							$emptyWrap.show();
							$loadMoreWrap.hide();

							// Update empty state text
							var hasFilters = !!templateId || !!postType || !!search;
							if (hasFilters) {
								$('#aips-empty-title').text(aipsPostReviewL10n.noPostsFound || 'No Posts Found');
								$('#aips-empty-desc').text(aipsPostReviewL10n.noPostsFoundDesc || 'No draft posts match your search criteria. Try a different search term.');
								$('#aips-empty-actions').html('<button type="button" class="aips-btn aips-btn-primary" id="aips-empty-clear-filters"><span class="dashicons dashicons-dismiss"></span> Clear Filters</button>');
								$(document).one('click', '#aips-empty-clear-filters', function () {
									self.onClearFilters(new $.Event('click'));
								});
							} else {
								$('#aips-empty-title').text(aipsPostReviewL10n.noDraftPosts || 'No Draft Posts');
								$('#aips-empty-desc').text(aipsPostReviewL10n.noDraftPostsDesc || 'There are no draft posts waiting for review. All generated posts have been published or deleted.');
								$('#aips-empty-actions').html('<a href="admin.php?page=aips-schedules" class="aips-btn aips-btn-secondary"><span class="dashicons dashicons-calendar-alt"></span> Manage Schedules</a>');
							}
						}
						self.updateSelectionState();
					} else {
						AIPS.Utilities.showToast((response && response.data && response.data.message) || aipsPostReviewL10n.fetchError || 'Failed to load posts.', 'error');
					}

					if (typeof callback === 'function') {
						callback();
					}
				},
				error: function () {
					$grid.css('opacity', '1');
					$loadMoreBtn.removeClass('is-loading');
					self.isLoading = false;
					AIPS.Utilities.showToast(aipsPostReviewL10n.fetchError || 'Failed to load posts.', 'error');
					if (typeof callback === 'function') {
						callback();
					}
				},
			});
		},

		/**
		 * Render a single card's HTML from item object.
		 *
		 * @param {Object} item Draft post item.
		 * @return {string} Card HTML string.
		 */
		renderCardHtml: function (item) {
			var esc = (AIPS.Templates && AIPS.Templates.escape) ? AIPS.Templates.escape : function (s) { return $('<div>').text(s || '').html(); };

			var postId = parseInt(item.post_id, 10);
			var historyId = parseInt(item.id, 10);
			var title = item.post_title || item.generated_title || 'Untitled';
			var snippet = item.snippet || '';
			var postTypeLabel = item.post_type_label || item.wp_post_type || 'Post';
			var source = item.source || (item.template_name ? ('Template: ' + item.template_name) : '');
			var createdFormatted = item.created_at_formatted || '';
			var editUrl = item.edit_url || ('post.php?post=' + postId + '&action=edit');
			var menuId = 'aips-review-card-actions-' + postId;
			var historyUrl = 'admin.php?page=aips-generated-posts&tab=history&post_id=' + postId;

			var imageHtml = '';
			if (item.featured_image) {
				imageHtml = '<div class="aips-card-image aips-preview-post" data-post-id="' + postId + '" role="button" tabindex="0" title="Preview post">' +
					'<img src="' + esc(item.featured_image) + '" alt="' + esc(title) + '" loading="lazy" />' +
				'</div>';
			} else {
				imageHtml = '<div class="aips-card-image aips-card-image-placeholder aips-preview-post" data-post-id="' + postId + '" role="button" tabindex="0" title="Preview post">' +
					'<span class="dashicons dashicons-format-aside" aria-hidden="true"></span>' +
				'</div>';
			}

			var metaHtml = '';
			if (source) {
				metaHtml += '<span class="aips-card-source" title="' + esc(source) + '">' + esc(source) + '</span>' +
					'<span class="aips-card-meta-sep">•</span>';
			}
			metaHtml += '<span class="aips-card-date">' + esc(createdFormatted) + '</span>';

			return '<div class="aips-post-card" data-post-id="' + postId + '" data-history-id="' + historyId + '">' +
				'<div class="aips-card-banner">' +
					imageHtml +
					'<div class="aips-card-checkbox-wrap">' +
						'<input type="checkbox" class="aips-post-checkbox" value="' + postId + '" data-post-id="' + postId + '" data-history-id="' + historyId + '" aria-label="Select post" />' +
					'</div>' +
					'<div class="aips-card-type-badge">' +
						'<span class="aips-badge aips-badge-neutral">' + esc(postTypeLabel) + '</span>' +
					'</div>' +
				'</div>' +
				'<div class="aips-card-body">' +
					'<div class="aips-card-meta">' + metaHtml + '</div>' +
					'<h3 class="aips-card-title">' +
						'<a href="#" class="aips-preview-post" data-post-id="' + postId + '" title="Preview post">' + esc(title) + '</a>' +
					'</h3>' +
					'<div class="aips-card-snippet">' + esc(snippet) + '</div>' +
				'</div>' +
				'<div class="aips-card-footer">' +
					'<div class="aips-card-footer-left">' +
						'<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-publish-post" data-post-id="' + postId + '" title="Publish this post immediately">' +
							'<span class="dashicons dashicons-upload"></span>' +
							'Publish' +
						'</button>' +
						'<a href="' + esc(editUrl) + '" class="aips-card-edit-link" target="_blank" rel="noopener">' +
							'<span class="dashicons dashicons-edit"></span>' +
							'Edit Post' +
						'</a>' +
					'</div>' +
					'<div class="aips-card-footer-right">' +
						'<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="' + menuId + '" title="More actions">' +
							'<span class="dashicons dashicons-ellipsis"></span>' +
							'<span class="screen-reader-text">More actions</span>' +
						'</button>' +
						'<div id="' + menuId + '" class="aips-row-action-menu" hidden>' +
							'<button type="button" class="aips-row-action-item aips-preview-post" data-post-id="' + postId + '" title="Preview this post">' +
								'<span class="dashicons dashicons-visibility"></span>' +
								'<span>Preview</span>' +
							'</button>' +
							'<button type="button" class="aips-row-action-item aips-ai-edit-btn" data-post-id="' + postId + '" data-history-id="' + historyId + '" title="AI Edit - Regenerate components">' +
								'<span class="dashicons dashicons-admin-customizer"></span>' +
								'<span>AI Edit</span>' +
							'</button>' +
							'<a class="aips-row-action-item aips-open-history-modal" href="' + esc(historyUrl) + '" data-history-id="' + historyId + '" data-post-id="' + postId + '" title="View history for this post">' +
								'<span class="dashicons dashicons-backup"></span>' +
								'<span>View History</span>' +
							'</a>' +
							'<button type="button" class="aips-row-action-item aips-regenerate-post" data-history-id="' + historyId + '" data-post-id="' + postId + '" title="Regenerate this post">' +
								'<span class="dashicons dashicons-update"></span>' +
								'<span>Re-generate</span>' +
							'</button>' +
							'<button type="button" class="aips-row-action-item aips-delete-post aips-danger-action" data-post-id="' + postId + '" data-history-id="' + historyId + '" title="Delete this draft post">' +
								'<span class="dashicons dashicons-trash"></span>' +
								'<span>Delete</span>' +
							'</button>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';
		},

		/**
		 * Render a single table row HTML from item object.
		 *
		 * @param {Object} item Draft post item.
		 * @return {string} Table row HTML string.
		 */
		renderTableRowHtml: function (item) {
			var esc = (AIPS.Templates && AIPS.Templates.escape) ? AIPS.Templates.escape : function (s) { return $('<div>').text(s || '').html(); };

			var postId           = parseInt(item.post_id, 10);
			var historyId        = parseInt(item.id, 10);
			var title            = item.post_title || item.generated_title || 'Untitled';
			var source           = item.source || (item.template_name ? ('Template: ' + item.template_name) : '');
			var postTypeLabel    = item.post_type_label || item.wp_post_type || 'Post';
			var createdFormatted = item.created_at_formatted || '';
			var editUrl          = item.edit_url || ('post.php?post=' + postId + '&action=edit');
			var menuId           = 'aips-review-row-actions-' + postId;
			var historyUrl       = 'admin.php?page=aips-generated-posts&tab=history&post_id=' + postId;

			return '<tr id="post-' + postId + '">' +
				'<th scope="row" class="check-column">' +
					'<input type="checkbox" name="post[]" value="' + postId + '" class="aips-post-checkbox">' +
				'</th>' +
				'<td class="title column-title has-row-actions column-primary">' +
					'<strong><a href="#" class="aips-preview-post" data-post-id="' + postId + '" title="Preview post">' + esc(title) + '</a></strong>' +
					(source ? '<span class="aips-cell-source">' + esc(source) + '</span>' : '') +
				'</td>' +
				'<td class="context column-context">' +
					(source ? '<span class="aips-cell-source">' + esc(source) + '</span>' : '') +
				'</td>' +
				'<td class="type column-type">' +
					'<span class="aips-badge aips-badge-neutral">' + esc(postTypeLabel) + '</span>' +
				'</td>' +
				'<td class="created column-created">' +
					'<div class="cell-meta">' + esc(createdFormatted) + '</div>' +
				'</td>' +
				'<td class="actions column-actions">' +
					'<div class="cell-actions">' +
						'<div class="aips-row-action-group">' +
							'<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-publish-post" data-post-id="' + postId + '" title="Publish this post">' +
								'<span class="dashicons dashicons-upload"></span> ' + (aipsPostReviewL10n.publish || 'Publish') +
							'</button>' +
							'<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="' + menuId + '" title="More actions">' +
								'<span class="screen-reader-text">More actions</span>' +
							'</button>' +
						'</div>' +
						'<div id="' + menuId + '" class="aips-row-action-menu" hidden>' +
							'<button type="button" class="aips-row-action-item aips-edit-post" data-edit-url="' + esc(editUrl) + '" title="Edit this post">' +
								'<span class="dashicons dashicons-edit"></span> <span>Edit</span>' +
							'</button>' +
							'<button type="button" class="aips-row-action-item aips-preview-post" data-post-id="' + postId + '" title="Preview this post">' +
								'<span class="dashicons dashicons-visibility"></span> <span>Preview</span>' +
							'</button>' +
							'<button type="button" class="aips-row-action-item aips-ai-edit-btn" data-post-id="' + postId + '" data-history-id="' + historyId + '" title="AI Edit - Regenerate components">' +
								'<span class="dashicons dashicons-admin-customizer"></span> <span>AI Edit</span>' +
							'</button>' +
							'<a class="aips-row-action-item aips-open-history-modal" href="' + esc(historyUrl) + '" data-history-id="' + historyId + '" data-post-id="' + postId + '" title="View history for this post">' +
								'<span class="dashicons dashicons-backup"></span> <span>View History</span>' +
							'</a>' +
							'<button type="button" class="aips-row-action-item aips-regenerate-post" data-history-id="' + historyId + '" data-post-id="' + postId + '" title="Regenerate this post">' +
								'<span class="dashicons dashicons-update"></span> <span>Re-generate</span>' +
							'</button>' +
							'<button type="button" class="aips-row-action-item aips-delete-post aips-danger-action" data-post-id="' + postId + '" data-history-id="' + historyId + '" title="Delete this draft post">' +
								'<span class="dashicons dashicons-trash"></span> <span>Delete</span>' +
							'</button>' +
						'</div>' +
					'</div>' +
				'</td>' +
			'</tr>';
		},

		/**
		 * Re-fetch the Pending Review cards from the server.
		 *
		 * @param {Function} [callback] Optional completion callback.
		 * @return {void}
		 */
		refreshList: function (callback) {
			this.fetchPosts(1, true, callback);
		},

		/**
		 * Update the page-header "Pending Approval" count from the filter bar's total.
		 *
		 * @return {void}
		 */
		syncHeaderCount: function () {
			var total = parseInt($('#aips-pending-total-count').text(), 10);
			if (isNaN(total)) {
				total = parseInt($('#aips-pending-review-tab .aips-filter-bar').data('total'), 10);
			}
			if (isNaN(total)) {
				return;
			}
			$('.aips-page-header .aips-page-summary-strip .aips-summary-chip-value').first().text(total);
		},

		/**
		 * Close the post-preview modal and clear the iframe src.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		closePreviewModal: function (e) {
			$('#aips-post-preview-modal').hide();
			$('#aips-post-preview-iframe').attr('src', '');
		},

		// -----------------------------------------------------------------
		// Bulk action implementations
		// -----------------------------------------------------------------

		/**
		 * Bulk-publish the selected draft posts via `aips_bulk_publish_posts`.
		 *
		 * Shows a confirmation dialog with the post count. On confirmation,
		 * collects the post IDs from the checked boxes and sends them to the
		 * server. Fades out each published row and refreshes the draft count and
		 * empty-state check on success.
		 *
		 * @param {jQuery} checkedBoxes The set of checked `.aips-post-checkbox` elements.
		 * @return {void}
		 */
		bulkPublish: function (checkedBoxes) {
			var count      = checkedBoxes.length;
			var confirmMsg = aipsPostReviewL10n.confirmBulkPublish.replace('%d', count);

			AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: 'No, cancel',   className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, publish', className: 'aips-btn aips-btn-primary', action: function () {
					var postIds = [];
					checkedBoxes.each(function () {
						postIds.push($(this).data('post-id'));
					});

					$.ajax({
						url:  aipsPostReviewL10n.ajaxUrl,
						type: 'POST',
						data: {
							action:   'aips_bulk_publish_posts',
							post_ids: postIds,
							nonce:    aipsPostReviewL10n.nonce,
						},
						success: function (response) {
							if (response.success) {
								var msg;
								if (response.data && response.data.queued) {
									// Large selection: the server queued a background publish job.
									msg = response.data.message || (aipsPostReviewL10n.bulkPublishQueued || '').replace('%d', count);
								} else {
									msg = aipsPostReviewL10n.bulkPublishSuccess.replace('%d', response.data.count || count);
								}
								AIPS.Utilities.showToast(msg, 'success');
								AIPS.PostReview.removeRowsThenRefresh(checkedBoxes);
							} else {
								AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.publishError, 'error');
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsPostReviewL10n.publishError, 'error');
						},
					});
				} },
			]);
		},

		/**
		 * Bulk-delete the selected draft posts via `aips_bulk_delete_draft_posts`.
		 *
		 * Shows a confirmation dialog with the post count. On confirmation,
		 * builds an array of `{post_id, history_id}` objects and sends them to
		 * the server. Fades out each deleted row and refreshes the draft count and
		 * empty-state check on success.
		 *
		 * @param {jQuery} checkedBoxes The set of checked `.aips-post-checkbox` elements.
		 * @return {void}
		 */
		bulkDelete: function (checkedBoxes) {
			var count      = checkedBoxes.length;
			var confirmMsg = aipsPostReviewL10n.confirmBulkDelete.replace('%d', count);

			AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: 'No, cancel',  className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function () {
					var items = [];
					checkedBoxes.each(function () {
						items.push({
							post_id:    $(this).data('post-id'),
							history_id: $(this).data('history-id'),
						});
					});

					$.ajax({
						url:  aipsPostReviewL10n.ajaxUrl,
						type: 'POST',
						data: {
							action: 'aips_bulk_delete_draft_posts',
							items:  items,
							nonce:  aipsPostReviewL10n.nonce,
						},
						success: function (response) {
							if (response.success) {
								var msg = aipsPostReviewL10n.bulkDeleteSuccess.replace('%d', response.data.count || count);
								AIPS.Utilities.showToast(msg, 'success');
								AIPS.PostReview.removeRowsThenRefresh(checkedBoxes);
							} else {
								AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.deleteError, 'error');
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsPostReviewL10n.deleteError, 'error');
						},
					});
				} },
			]);
		},

		/**
		 * Bulk-regenerate the selected draft posts via `aips_bulk_regenerate_posts`.
		 *
		 * Shows a confirmation dialog with the post count. On confirmation,
		 * builds an array of `{post_id, history_id}` objects and sends them to
		 * the server. Fades out each regenerated row and refreshes the draft count and
		 * empty-state check on success.
		 *
		 * @param {jQuery} checkedBoxes The set of checked `.aips-post-checkbox` elements.
		 * @return {void}
		 */
		bulkRegenerate: function (checkedBoxes) {
			var count      = checkedBoxes.length;
			var confirmMsg = aipsPostReviewL10n.confirmBulkRegenerate.replace('%d', count);

			AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: 'No, cancel',      className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, regenerate', className: 'aips-btn aips-btn-primary', action: function () {
					var items = [];
					checkedBoxes.each(function () {
						items.push({
							post_id:    $(this).data('post-id'),
							history_id: $(this).data('history-id'),
						});
					});

					$.ajax({
						url:  aipsPostReviewL10n.ajaxUrl,
						type: 'POST',
						data: {
							action: 'aips_bulk_regenerate_posts',
							items:  items,
							nonce:  aipsPostReviewL10n.nonce,
						},
						success: function (response) {
							if (response && response.success) {
								var successCount = (response.data && typeof response.data.success_count !== 'undefined') ? response.data.success_count : count;
								var msg          = aipsPostReviewL10n.bulkRegenerateSuccess.replace('%d', successCount);
								AIPS.Utilities.showToast(msg + ' Check History for progress.', 'success');

								var successIds = [];
								if (response.data) {
									if ($.isArray(response.data.success_ids)) {
										successIds = response.data.success_ids;
									} else if ($.isArray(response.data.items)) {
										$.each(response.data.items, function (index, item) {
											if (item && item.status === 'success' && typeof item.post_id !== 'undefined') {
												successIds.push(item.post_id);
											}
										});
									}
								}

								var $rowsToRemove = checkedBoxes;
								if (successIds.length) {
									$rowsToRemove = checkedBoxes.filter(function () {
										return $.inArray($(this).data('post-id'), successIds) !== -1;
									});
								}

								AIPS.PostReview.removeRowsThenRefresh($rowsToRemove);

								if (response.data && response.data.failed_count) {
									var failMsg = aipsPostReviewL10n.bulkRegeneratePartialFailure || aipsPostReviewL10n.regenerateError;
									failMsg = failMsg.replace('%d', response.data.failed_count);
									AIPS.Utilities.showToast(failMsg, 'warning');
								}
							} else {
								AIPS.Utilities.showToast((response && response.data && response.data.message) || aipsPostReviewL10n.regenerateError, 'error');
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsPostReviewL10n.regenerateError, 'error');
						},
					});
				} },
			]);
		},

		// -----------------------------------------------------------------
		// Post preview modal
		// -----------------------------------------------------------------

		/**
		 * Open the post-preview modal and load the rendered post content.
		 *
		 * Resets the modal to a loading state, then sends the
		 * `aips_get_post_preview` AJAX action. On success, renders an HTML
		 * preview (title, featured image, excerpt, body content, and an
		 * optional edit link) into `#aips-preview-content-container` using
		 * `AIPS.Templates.render` / `renderRaw`.
		 *
		 * @param {number} postId The WordPress post ID to preview.
		 * @return {void}
		 */
		previewPost: function (postId) {
			var modal            = $('#aips-post-preview-modal');
			var contentContainer = $('#aips-preview-content-container');
			var iframe           = $('#aips-post-preview-iframe');
			var headerTitle      = modal.find('.aips-modal-header h2');

			contentContainer.show().html(AIPS.Templates.render('aips-tmpl-preview-loading', {
				message: aipsPostReviewL10n.loadingPreview || 'Loading preview...',
			}));
			iframe.hide().attr('src', '');
			headerTitle.text(aipsPostReviewL10n.previewTitle || 'Post Preview');
			modal.show();

			$.ajax({
				url:  aipsPostReviewL10n.ajaxUrl,
				type: 'POST',
				data: {
					action:  'aips_get_post_preview',
					post_id: postId,
					nonce:   aipsPostReviewL10n.nonce,
				},
				success: function (response) {
					if (response.success) {
						var data = response.data;

						var imageHtml = '';
						if (data.featured_image) {
							imageHtml = AIPS.Templates.renderRaw('aips-tmpl-preview-image', {
								src: data.featured_image,
							});
						}

						var excerptHtml = '';
						if (data.excerpt) {
							excerptHtml = AIPS.Templates.render('aips-tmpl-preview-excerpt', {
								excerpt: data.excerpt,
							});
						}

						var editFooterHtml = '';
						if (data.edit_url) {
							editFooterHtml = AIPS.Templates.renderRaw('aips-tmpl-preview-edit-footer', {
								edit_url: data.edit_url,
							});
						}

						var html = AIPS.Templates.renderRaw('aips-tmpl-preview-content', {
							title:          AIPS.Templates.escape(data.title),
							featured_image: imageHtml,
							excerpt:        excerptHtml,
							content:        data.content,
							edit_footer:    editFooterHtml,
						});

						contentContainer.html(html);
					} else {
						contentContainer.html('<div class="notice notice-error inline"><p>' + (response.data.message || aipsPostReviewL10n.previewError) + '</p></div>');
					}
				},
				error: function () {
					contentContainer.html('<div class="notice notice-error inline"><p>' + (aipsPostReviewL10n.previewError || 'Failed to load preview.') + '</p></div>');
				},
			});
		},

		// -----------------------------------------------------------------
		// Helpers
		// -----------------------------------------------------------------

		/**
		 * Refresh loaded count and total count badges.
		 *
		 * Called after any item is removed (published, deleted, or regenerated)
		 * so the count stays accurate without a full page reload.
		 *
		 * @return {void}
		 */
		updateDraftCount: function () {
			var visibleCards = $('#aips-pending-posts-grid .aips-post-card:visible').length;
			var visibleRows  = $('#aips-pending-review-tab .aips-post-review-table tbody tr:visible').length;
			var count = Math.max(visibleCards, visibleRows);

			$('#aips-pending-loaded-count').text(count);
			$('#aips-draft-count').text(count);

			var $totalElem = $('#aips-pending-total-count');
			var remaining  = parseInt($totalElem.text(), 10);
			if (!isNaN(remaining) && remaining > 0) {
				var newTotal = Math.max(0, remaining - 1);
				$totalElem.text(newTotal);
				$('#aips-pending-review-tab .aips-filter-bar').data('total', newTotal);

				var $chip = $('.aips-page-header .aips-page-summary-strip .aips-summary-chip-value').first();
				$chip.text(newTotal);
			}
			this.updateSelectionState();
		},

		/**
		 * Fade out the cards/rows belonging to the given checkboxes, then refresh the
		 * list from the server so remaining drafts load.
		 *
		 * @param {jQuery} $boxes Checked `.aips-post-checkbox` elements.
		 * @return {void}
		 */
		removeRowsThenRefresh: function ($boxes) {
			var $items = $boxes.closest('.aips-post-card, tr');
			if (!$items.length) {
				AIPS.PostReview.refreshList();
				return;
			}
			var pending = $items.length;
			$items.fadeOut(300, function () {
				$(this).remove();
				if (--pending === 0) {
					AIPS.PostReview.refreshList();
				}
			});
		},

		/**
		 * Show or hide the empty-state placeholder based on whether any cards
		 * or rows remain visible.
		 *
		 * @return {void}
		 */
		checkEmptyState: function () {
			var visibleCards = $('#aips-pending-posts-grid .aips-post-card:visible').length;
			var visibleRows  = $('#aips-pending-review-tab .aips-post-review-table tbody tr:visible').length;
			var remaining    = Math.max(visibleCards, visibleRows);

			if (remaining === 0) {
				var total = parseInt($('#aips-pending-total-count').text(), 10);
				if (!isNaN(total) && total > 0) {
					this.refreshList();
					return;
				}

				$('#aips-pending-posts-wrap').hide();
				$('#aips-pending-list-wrap').hide();
				$('.aips-pending-toolbar').hide();
				$('#aips-pending-empty-state-wrap').show();
			}
		},
	};

	$(document).ready(function () {
		AIPS.PostReview.init();
	});

})(jQuery);
