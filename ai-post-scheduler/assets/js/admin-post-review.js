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

		/**
		 * Bootstrap the Post Review page.
		 *
		 * @return {void}
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind all UI event listeners.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			// Select-all checkbox.
			$(document).on('change', '#cb-select-all-1', this.onSelectAllChange.bind(this));

			// Individual row checkbox.
			$(document).on('change', '.aips-post-checkbox', this.onCheckboxChange.bind(this));

			// Open post-preview modal.
			$(document).on('click', '.aips-preview-post, .aips-preview-trigger', this.onPreviewClick.bind(this));

			// Single-row actions.
			$(document).on('click', '.aips-publish-post',    this.onPublishClick.bind(this));
			$(document).on('click', '.aips-delete-post',     this.onDeleteClick.bind(this));
			$(document).on('click', '.aips-regenerate-post', this.onRegenerateClick.bind(this));

			// Bulk actions.
			$(document).on('click', '#aips-bulk-action-btn', this.onBulkAction.bind(this));

			// Reload button.
			$(document).on('click', '#aips-reload-posts-btn', this.onReloadClick.bind(this));

			// Close preview modal.
			$(document).on('click', '#aips-post-preview-modal .aips-modal-close, #aips-post-preview-modal .aips-modal-overlay', this.closePreviewModal.bind(this));
		},

		// -----------------------------------------------------------------
		// Event handlers – Select / checkbox
		// -----------------------------------------------------------------

		/**
		 * Sync all row checkboxes when the select-all header checkbox changes.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onSelectAllChange: function (e) {
			$('.aips-post-checkbox').prop('checked', $(e.currentTarget).prop('checked'));
		},

		/**
		 * Update the select-all checkbox state after an individual row checkbox changes.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onCheckboxChange: function (e) {
			var allChecked = $('.aips-post-checkbox').length === $('.aips-post-checkbox:checked').length;
			$('#cb-select-all-1').prop('checked', allChecked);
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
		 * Confirm then publish a single draft post.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onPublishClick: function (e) {
			e.preventDefault();
			var $btn   = $(e.currentTarget);
			var postId = $btn.data('post-id');
			var $row   = $btn.closest('tr');

			AIPS.Utilities.confirm(aipsPostReviewL10n.confirmPublish, 'Notice', [
				{ label: 'No, cancel',   className: 'aips-btn aips-btn-primary' },
				{ label: 'Yes, publish', className: 'aips-btn aips-btn-danger-solid', action: function () {
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
								$row.fadeOut(400, function () {
									$(this).remove();
									AIPS.PostReview.updateDraftCount();
									AIPS.PostReview.checkEmptyState();
								});
							} else {
								AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.publishError, 'error');
								AIPS.Utilities.resetButton($btn);
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsPostReviewL10n.publishError, 'error');
							AIPS.Utilities.resetButton($btn);
						},
					});
				} },
			]);
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
			var $row      = $btn.closest('tr');

			AIPS.Utilities.confirm(aipsPostReviewL10n.confirmDelete, 'Notice', [
				{ label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
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
			var $row      = $btn.closest('tr');

			AIPS.Utilities.confirm(aipsPostReviewL10n.confirmRegenerate, 'Notice', [
				{ label: 'No, cancel',      className: 'aips-btn aips-btn-primary' },
				{ label: 'Yes, regenerate', className: 'aips-btn aips-btn-danger-solid', action: function () {
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
				return;
			}

			var checkedBoxes = $('.aips-post-checkbox:checked');
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
		 * Reload the page when the reload button is clicked.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onReloadClick: function (e) {
			e.preventDefault();
			location.reload();
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
				{ label: 'No, cancel',   className: 'aips-btn aips-btn-primary' },
				{ label: 'Yes, publish', className: 'aips-btn aips-btn-danger-solid', action: function () {
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
								var msg = aipsPostReviewL10n.bulkPublishSuccess.replace('%d', response.data.count || count);
								AIPS.Utilities.showToast(msg, 'success');
								checkedBoxes.each(function () {
									$(this).closest('tr').fadeOut(400, function () {
										$(this).remove();
										AIPS.PostReview.updateDraftCount();
										AIPS.PostReview.checkEmptyState();
									});
								});
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
				{ label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
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
								checkedBoxes.each(function () {
									$(this).closest('tr').fadeOut(400, function () {
										$(this).remove();
										AIPS.PostReview.updateDraftCount();
										AIPS.PostReview.checkEmptyState();
									});
								});
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
				{ label: 'No, cancel',      className: 'aips-btn aips-btn-primary' },
				{ label: 'Yes, regenerate', className: 'aips-btn aips-btn-danger-solid', action: function () {
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

								$rowsToRemove.each(function () {
									$(this).closest('tr').fadeOut(400, function () {
										$(this).remove();
										AIPS.PostReview.updateDraftCount();
										AIPS.PostReview.checkEmptyState();
									});
								});

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
		 * Refresh the `#aips-draft-count` badge with the number of currently
		 * visible table rows.
		 *
		 * Called after any row is removed (published, deleted, or regenerated)
		 * so the count stays accurate without a full page reload.
		 *
		 * @return {void}
		 */
		updateDraftCount: function () {
			var visibleRows = $('.aips-post-review-table tbody tr:visible').length;
			$('#aips-draft-count').text(visibleRows);
		},

		/**
		 * Show or hide the empty-state placeholder based on whether any table
		 * rows remain visible.
		 *
		 * When all rows have been removed, hides the table and pagination
		 * controls and injects (or reveals) an `.aips-empty-state` element with
		 * a friendly "no draft posts" message.
		 *
		 * @return {void}
		 */
		checkEmptyState: function () {
			var visibleRows = $('.aips-post-review-table tbody tr:visible').length;

			if (visibleRows === 0) {
				$('.aips-post-review-table').hide();
				$('.tablenav').hide();

				if ($('.aips-empty-state').length === 0) {
					var emptyStateHtml = '<div class="aips-empty-state">' +
						'<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' +
						'<h3>' + (aipsPostReviewL10n.noDraftPosts || 'No Draft Posts') + '</h3>' +
						'<p>' + (aipsPostReviewL10n.noDraftPostsDesc || 'There are no draft posts waiting for review.') + '</p>' +
						'</div>';
					$('#aips-post-review-form').after(emptyStateHtml);
				} else {
					$('.aips-empty-state').show();
				}
			}
		},
	};

	$(document).ready(function () {
		AIPS.PostReview.init();
	});

})(jQuery);
