(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	var postReviewModule = {
		l10n: {},
		workflowStatusLabels: {},

		/**
		 * Bootstrap the Post Review module.
		 */
		init: function() {
			this.l10n = typeof aipsPostReviewL10n !== 'undefined' ? aipsPostReviewL10n : {};
			this.workflowStatusLabels = this.l10n.workflowStatusLabels || {};
			this.bindEvents();
			window.AIPS.updateWorkflowStatus = this.updateWorkflowStatus.bind(this);
		},

		/**
		 * Register DOM event listeners used across the post review table.
		 */
		bindEvents: function() {
			var self = this;

			$(document).on('change', '#cb-select-all-1', function(e) {
				self.handleSelectAllChange(e);
			});

			$(document).on('change', '.aips-post-checkbox', function(e) {
				self.handleCheckboxChange(e);
			});

			$(document).on('click', '.aips-preview-post, .aips-preview-trigger', function(e) {
				self.handlePreviewTrigger(e);
			});

			$(document).on('click', '.aips-publish-post', function(e) {
				self.handlePublishClick(e);
			});

			$(document).on('click', '.aips-delete-post', function(e) {
				self.handleDeleteClick(e);
			});

			$(document).on('click', '.aips-regenerate-post', function(e) {
				self.handleRegenerateClick(e);
			});

			$(document).on('click', '#aips-bulk-action-btn', function(e) {
				self.handleBulkAction(e);
			});

			$(document).on('click', '#aips-reload-posts-btn', function(e) {
				self.handleReload(e);
			});

			$(document).on('click', '#aips-post-preview-modal .aips-modal-close, #aips-post-preview-modal .aips-modal-overlay', function() {
				self.closePreviewModal();
			});
		},

		/**
		 * Apply a workflow status label to a table row.
		 *
		 * @param {jQuery} $row
		 * @param {string} status
		 */
		applyWorkflowStatusToRow: function($row, status) {
			if (!$row || !$row.length || !status) {
				return;
			}

			var label = this.workflowStatusLabels[status] || status;
			var $badge = $row.find('.column-workflow .aips-badge');

			if ($badge.length && label) {
				$badge.text(label);
			}

			$row.attr('data-workflow-status', status);
		},

		/**
		 * Update the workflow status via AJAX and reconcile UI state after the response.
		 *
		 * @param {number} historyId
		 * @param {string} status
		 * @param {number} workflowId
		 * @param {Object} [options]
		 * @param {jQuery} [options.row]
		 * @param {string} [options.previousStatus]
		 * @returns {*}
		 */
		updateWorkflowStatus: function(historyId, status, workflowId, options) {
			var self = this;
			if (!historyId || !status || !this.l10n.ajaxUrl) {
				return $.Deferred().reject();
			}

			options = options || {};

			return $.ajax({
				url: this.l10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_update_workflow_status',
					history_id: historyId,
					workflow_status: status,
					workflow_id: workflowId || '',
					nonce: this.l10n.nonce
				}
			}).done(function(response) {
				if (!response.success) {
					self.handleWorkflowStatusFailure(options.row, options.previousStatus, response.data && response.data.message);
				}
			}).fail(function() {
				self.handleWorkflowStatusFailure(options.row, options.previousStatus, self.l10n.workflowUpdateError);
			});
		},

		/**
		 * Restore the previous status badge and show a toast when the AJAX update fails.
		 *
		 * @param {jQuery|null} $row
		 * @param {string|null} previousStatus
		 * @param {string=} message
		 */
		handleWorkflowStatusFailure: function($row, previousStatus, message) {
			if ($row && previousStatus) {
				this.applyWorkflowStatusToRow($row, previousStatus);
			}

			var toast = message || this.l10n.workflowUpdateError;
			if (toast && window.AIPS && AIPS.Utilities) {
				AIPS.Utilities.showToast(toast, 'error');
			}
		},



		/**
		 * Handle the select-all checkbox toggle.
		 *
		 * @param {Event} e
		 */
		handleSelectAllChange: function(e) {
			var checked = $(e.currentTarget).prop('checked');
			$('.aips-post-checkbox').prop('checked', checked);
		},

		/**
		 * Sync the select-all checkbox when individual checkboxes change.
		 *
		 * @param {Event} e
		 */
		handleCheckboxChange: function(e) {
			var allChecked = $('.aips-post-checkbox').length === $('.aips-post-checkbox:checked').length;
			$('#cb-select-all-1').prop('checked', allChecked);
		},

		/**
		 * Trigger the preview UI for a post.
		 *
		 * @param {Event} e
		 */
		handlePreviewTrigger: function(e) {
			e.preventDefault();
			var postId = $(e.currentTarget).data('post-id');
			this.previewPost(postId);
		},

		/**
		 * Handle publishing a single post.
		 *
		 * @param {Event} e
		 */
		handlePublishClick: function(e) {
			var self = this;
			e.preventDefault();
			var $button = $(e.currentTarget);
			var postId = $button.data('post-id');
			var historyId = $button.data('history-id');
			var row = $button.closest('tr');
			var previousStatus = row.attr('data-workflow-status');
			var readyStatus = this.l10n.workflowStatusReadyToPublish;

			AIPS.Utilities.confirm(this.l10n.confirmPublish, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, publish',
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						$button.prop('disabled', true).text(self.l10n.loading || 'Publishing...');

						$.ajax({
							url: self.l10n.ajaxUrl,
							type: 'POST',
							data: {
								action: 'aips_publish_post',
								post_id: postId,
								nonce: self.l10n.nonce
							},
							success: function(response) {
								if (response.success) {
									var rawMsg = response.data.message || self.l10n.publishSuccess;
									var safeMsg = $('<div>').text(rawMsg).html();
									if (response.data.post_id) {
										var editUrl = 'post.php?post=' + encodeURIComponent(response.data.post_id) + '&action=edit';
										var safeLink = '<a href="' + editUrl.replace(/\"/g, '&quot;') + '" target="_blank">Edit Post</a>';
										AIPS.Utilities.showToast(safeMsg + ' ' + safeLink, 'success', { isHtml: true });
									} else {
										AIPS.Utilities.showToast(safeMsg, 'success');
									}

									if (historyId && readyStatus) {
										self.updateWorkflowStatus(historyId, readyStatus, null, {
											row: row,
											previousStatus: previousStatus
										});
										self.applyWorkflowStatusToRow(row, readyStatus);
									}

									row.fadeOut(400, function() {
										$(this).remove();
										self.updateDraftCount();
										self.checkEmptyState();
									});
								} else {
									AIPS.Utilities.showToast(response.data.message || self.l10n.publishError, 'error');
									$button.prop('disabled', false).text(self.l10n.publish || 'Publish');
								}
							},
							error: function() {
								AIPS.Utilities.showToast(self.l10n.publishError, 'error');
								$button.prop('disabled', false).text(self.l10n.publish || 'Publish');
							}
						});
					}
				}
			]);
		},

		/**
		 * Handle deleting a single post.
		 *
		 * @param {Event} e
		 */
		handleDeleteClick: function(e) {
			var self = this;
			e.preventDefault();
			var $button = $(e.currentTarget);
			var postId = $button.data('post-id');
			var historyId = $button.data('history-id');
			var row = $button.closest('tr');

			AIPS.Utilities.confirm(this.l10n.confirmDelete, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						$button.prop('disabled', true).text(self.l10n.deleting || 'Deleting...');

						$.ajax({
							url: self.l10n.ajaxUrl,
							type: 'POST',
							data: {
								action: 'aips_delete_draft_post',
								post_id: postId,
								history_id: historyId,
								nonce: self.l10n.nonce
							},
							success: function(response) {
								if (response.success) {
									AIPS.Utilities.showToast(response.data.message || self.l10n.deleteSuccess, 'success');
									row.fadeOut(400, function() {
										$(this).remove();
										self.updateDraftCount();
										self.checkEmptyState();
									});
								} else {
									AIPS.Utilities.showToast(response.data.message || self.l10n.deleteError, 'error');
									$button.prop('disabled', false).text(self.l10n.delete || 'Delete');
								}
							},
							error: function() {
								AIPS.Utilities.showToast(self.l10n.deleteError, 'error');
								$button.prop('disabled', false).text(self.l10n.delete || 'Delete');
							}
						});
					}
				}
			]);
		},

		/**
		 * Handle regenerating a single post.
		 *
		 * @param {Event} e
		 */
		handleRegenerateClick: function(e) {
			var self = this;
			e.preventDefault();
			var $button = $(e.currentTarget);
			var historyId = $button.data('history-id');
			var row = $button.closest('tr');

			AIPS.Utilities.confirm(this.l10n.confirmRegenerate, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, regenerate',
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						$button.prop('disabled', true).text(self.l10n.regenerating || 'Regenerating...');

						$.ajax({
							url: self.l10n.ajaxUrl,
							type: 'POST',
							data: {
								action: 'aips_regenerate_post',
								history_id: historyId,
								nonce: self.l10n.nonce
							},
							success: function(response) {
								if (response.success) {
									var msg = response.data.message || self.l10n.regenerateSuccess;
									AIPS.Utilities.showToast(msg + ' Check History for progress.', 'success');
									row.fadeOut(400, function() {
										$(this).remove();
										self.updateDraftCount();
										self.checkEmptyState();
									});
								} else {
									AIPS.Utilities.showToast(response.data.message || self.l10n.regenerateError, 'error');
									$button.prop('disabled', false).text(self.l10n.regenerate || 'Re-generate');
								}
							},
							error: function() {
								AIPS.Utilities.showToast(self.l10n.regenerateError, 'error');
								$button.prop('disabled', false).text(self.l10n.regenerate || 'Re-generate');
							}
						});
					}
				}
			]);
		},

		/**
		 * Handle bulk action button clicks.
		 *
		 * @param {Event} e
		 */
		handleBulkAction: function(e) {
			e.preventDefault();

			var action = $('#bulk-action-selector-top').val();
			if (!action) {
				return;
			}

			var checkedBoxes = $('.aips-post-checkbox:checked');
			if (checkedBoxes.length === 0) {
				AIPS.Utilities.showToast(this.l10n.noPostsSelected, 'warning');
				return;
			}

			if (action === 'publish') {
				this.bulkPublish(checkedBoxes);
			} else if (action === 'delete') {
				this.bulkDelete(checkedBoxes);
			}
		},

		/**
		 * Bulk publish handler.
		 *
		 * @param {jQuery} checkedBoxes
		 */
		bulkPublish: function(checkedBoxes) {
			var self = this;
			var count = checkedBoxes.length;
			var confirmMsg = this.l10n.confirmBulkPublish.replace('%d', count);

			AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, publish',
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						var postIds = [];
						checkedBoxes.each(function() {
							postIds.push($(this).data('post-id'));
						});

						$.ajax({
							url: self.l10n.ajaxUrl,
							type: 'POST',
							data: {
								action: 'aips_bulk_publish_posts',
								post_ids: postIds,
								nonce: self.l10n.nonce
							},
							success: function(response) {
								if (response.success) {
									var msg = self.l10n.bulkPublishSuccess.replace('%d', response.data.count || count);
									AIPS.Utilities.showToast(msg, 'success');

									var readyStatus = self.l10n.workflowStatusReadyToPublish;
									checkedBoxes.each(function() {
										var $row = $(this).closest('tr');
										var historyId = $(this).data('history-id') || $row.data('history-id');
										if (historyId && readyStatus) {
											self.updateWorkflowStatus(historyId, readyStatus, null, {
												row: $row,
												previousStatus: $row.attr('data-workflow-status')
											});
											self.applyWorkflowStatusToRow($row, readyStatus);
										}
										$row.fadeOut(400, function() {
											$(this).remove();
											self.updateDraftCount();
											self.checkEmptyState();
										});
									});
								} else {
									AIPS.Utilities.showToast(response.data.message || self.l10n.publishError, 'error');
								}
							},
							error: function() {
								AIPS.Utilities.showToast(self.l10n.publishError, 'error');
							}
						});
					}
				}
			]);
		},

		/**
		 * Bulk delete handler.
		 *
		 * @param {jQuery} checkedBoxes
		 */
		bulkDelete: function(checkedBoxes) {
			var self = this;
			var count = checkedBoxes.length;
			var confirmMsg = this.l10n.confirmBulkDelete.replace('%d', count);

			AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						var items = [];
						checkedBoxes.each(function() {
							items.push({
								post_id: $(this).data('post-id'),
								history_id: $(this).data('history-id')
							});
						});

						$.ajax({
							url: self.l10n.ajaxUrl,
							type: 'POST',
							data: {
								action: 'aips_bulk_delete_draft_posts',
								items: items,
								nonce: self.l10n.nonce
							},
							success: function(response) {
								if (response.success) {
									var msg = self.l10n.bulkDeleteSuccess.replace('%d', response.data.count || count);
									AIPS.Utilities.showToast(msg, 'success');

									checkedBoxes.each(function() {
										$(this).closest('tr').fadeOut(400, function() {
											$(this).remove();
											self.updateDraftCount();
											self.checkEmptyState();
										});
									});
								} else {
									AIPS.Utilities.showToast(response.data.message || self.l10n.deleteError, 'error');
								}
							},
							error: function() {
								AIPS.Utilities.showToast(self.l10n.deleteError, 'error');
							}
						});
					}
				}
			]);
		},

		/**
		 * Preview a generated post via AJAX.
		 *
		 * @param {number} postId
		 */
		previewPost: function(postId) {
			var modal = $('#aips-post-preview-modal');
			var contentContainer = $('#aips-preview-content-container');
			var iframe = $('#aips-post-preview-iframe');
			var headerTitle = modal.find('.aips-modal-header h2');

			contentContainer.show().html('<div class="aips-loading-spinner"><span class="spinner is-active" style="float:none; margin: 0 auto; display:block;"></span> <p style="text-align:center;">' + (this.l10n.loadingPreview || 'Loading preview...') + '</p></div>');
			iframe.hide().attr('src', '');
			headerTitle.text(this.l10n.previewTitle || 'Post Preview');
			modal.show();

			$.ajax({
				url: this.l10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_draft_post_preview',
					post_id: postId,
					nonce: this.l10n.nonce
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						var html = '';

						html += '<h1 style="margin-bottom: 20px;">' + data.title + '</h1>';
						if (data.featured_image) {
							html += '<div class="aips-preview-image" style="margin-bottom: 20px;">';
							html += '<img src="' + data.featured_image + '" style="max-width: 100%; height: auto; border-radius: 4px;">';
							html += '</div>';
						}

						if (data.excerpt) {
							html += '<div class="aips-preview-excerpt" style="background: #f0f0f1; padding: 15px; margin-bottom: 20px; border-left: 4px solid #72aee6;">';
							html += '<strong>Excerpt:</strong> ' + data.excerpt;
							html += '</div>';
						}

						html += '<div class="aips-preview-body">' + data.content + '</div>';

						if (data.edit_url) {
							html += '<div style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px;">';
							html += '<a href="' + data.edit_url + '" target="_blank" class="button button-primary">Edit Post in WordPress</a>';
							html += '</div>';
						}

						contentContainer.html(html);
					} else {
						contentContainer.html('<div class="notice notice-error inline"><p>' + (response.data.message || this.l10n.previewError) + '</p></div>');
					}
				}.bind(this),
				error: function() {
					contentContainer.html('<div class="notice notice-error inline"><p>' + (this.l10n.previewError || 'Failed to load preview.') + '</p></div>');
				}.bind(this)
			});
		},

		/**
		 * Hide the preview modal.
		 */
		closePreviewModal: function() {
			$('#aips-post-preview-modal').hide();
			$('#aips-post-preview-iframe').attr('src', '');
		},

		/**
		 * Handle the reload posts button.
		 *
		 * @param {Event} e
		 */
		handleReload: function(e) {
			e.preventDefault();
			location.reload();
		},

		/**
		 * Update the draft count badge.
		 */
		updateDraftCount: function() {
			var visibleRows = $('.aips-post-review-table tbody tr:visible').length;
			$('#aips-draft-count').text(visibleRows);
		},

		/**
		 * Show or hide the empty state when no drafts remain.
		 */
		checkEmptyState: function() {
			var visibleRows = $('.aips-post-review-table tbody tr:visible').length;

			if (visibleRows === 0) {
				$('.aips-post-review-table').hide();
				$('.tablenav').hide();

				if ($('.aips-empty-state').length === 0) {
					var emptyStateHtml = '<div class="aips-empty-state">' +
						'<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' +
						'<h3>' + (this.l10n.noDraftPosts || 'No Draft Posts') + '</h3>' +
						'<p>' + (this.l10n.noDraftPostsDesc || 'There are no draft posts waiting for review.') + '</p>' +
						'</div>';
					$('#aips-post-review-form').after(emptyStateHtml);
				} else {
					$('.aips-empty-state').show();
				}
			}
		}
	};

	Object.assign(AIPS, {
		PostReview: postReviewModule
	});

	$(document).ready(function() {
		AIPS.PostReview.init();
	});
})(jQuery);
