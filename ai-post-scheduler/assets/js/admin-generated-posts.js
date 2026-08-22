/**
 * Generated Posts Admin JavaScript
 *
 * Implements interactive features for the Generated Posts admin page:
 * - View mode switcher (Grouped / Table / Cards)
 * - Grouped accordion toggles and bulk Expand/Collapse with keyboard accessibility
 * - Quick inline AJAX post status switcher with styled confirmation modal
 * - Bulk selection and multi-action operations with modal confirmations
 * - Post preview modal and action overflow menus
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * Generated Posts Module
	 */
	AIPS.GeneratedPosts = {
		/**
		 * Configuration object passed from WordPress localization
		 * @type {Object}
		 */
		config: {},

		/**
		 * Admin AJAX URL
		 * @type {string}
		 */
		ajaxUrl: '',

		/**
		 * AJAX security nonce
		 * @type {string}
		 */
		nonce: '',

		/**
		 * Main bootstrap entry point.
		 */
		init: function() {
			this.config = window.aipsGeneratedPostsConfig || {};
			this.ajaxUrl = this.config.ajaxUrl || window.ajaxurl || '';
			this.nonce = this.config.nonce || '';

			this.bindEvents();
		},

		/**
		 * Register UI event listeners using delegated event handlers.
		 */
		bindEvents: function() {
			$(document).on('click', '.aips-view-btn', this.handleViewSwitch.bind(this));
			$(document).on('change', '#aips-group-by-select, #aips-per-page-select, #aips-filter-status, #aips-filter-author, #aips-filter-template, #aips-filter-campaign', this.handleFilterChange.bind(this));
			$(document).on('click', '.aips-group-header', this.handleGroupHeaderClick.bind(this));
			$(document).on('keydown', '.aips-group-header', this.handleGroupHeaderKeydown.bind(this));
			$(document).on('click', '#aips-toggle-all-groups', this.handleToggleAllGroups.bind(this));
			$(document).on('click', '.aips-row-action-overflow-toggle', this.handleOverflowToggle.bind(this));
			$(document).on('click', this.handleDocumentClick.bind(this));
			$(document).on('change', '.aips-quick-status-select', this.handleQuickStatusChange.bind(this));
			$(document).on('change', '#aips-select-all-posts', this.handleSelectAllChange.bind(this));
			$(document).on('change', '.aips-post-checkbox', this.handlePostCheckboxChange.bind(this));
			$(document).on('click', '#aips-apply-bulk-action', this.handleApplyBulkAction.bind(this));
			$(document).on('click', '.aips-preview-post, .aips-preview-trigger', this.handlePreviewPost.bind(this));
			$(document).on('click', '#aips-post-preview-modal .aips-modal-close', this.handleClosePreviewModal.bind(this));
			$(document).on('keydown', this.handleKeyDown.bind(this));
			$(document).on('click', '.aips-edit-post', this.handleEditPost.bind(this));
		},

		/**
		 * Handle switching view mode (Grouped / Table / Cards).
		 *
		 * @param {Event} e Click event.
		 */
		handleViewSwitch: function(e) {
			e.preventDefault();
			var targetView = $(e.currentTarget).data('view');
			if (!targetView) {
				return;
			}

			$('#aips-view-mode-input').val(targetView);
			if (window.localStorage) {
				window.localStorage.setItem('aips_generated_posts_view_mode', targetView);
			}
			$('#aips-posts-filter-form').submit();
		},

		/**
		 * Handle filter and grouping select changes.
		 *
		 * @param {Event} e Change event.
		 */
		handleFilterChange: function(e) {
			$('#aips-posts-filter-form').submit();
		},

		/**
		 * Handle clicking on a group accordion header to collapse or expand.
		 *
		 * @param {Event} e Click event.
		 */
		handleGroupHeaderClick: function(e) {
			if ($(e.target).closest('input, button, a, select').length) {
				return;
			}
			var $header = $(e.currentTarget);
			var $section = $header.closest('.aips-group-section');
			var isCollapsed = $section.hasClass('collapsed');

			$section.toggleClass('collapsed');
			$header.attr('aria-expanded', isCollapsed ? 'true' : 'false');
		},

		/**
		 * Handle keydown on a group header for keyboard accessibility (Enter/Space).
		 *
		 * @param {Event} e Keydown event.
		 */
		handleGroupHeaderKeydown: function(e) {
			if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
				e.preventDefault();
				this.handleGroupHeaderClick(e);
			}
		},

		/**
		 * Handle toggle all groups button (Expand All / Collapse All).
		 *
		 * @param {Event} e Click event.
		 */
		handleToggleAllGroups: function(e) {
			e.preventDefault();
			var $sections = $('.aips-group-section');
			var anyOpen = $sections.filter(':not(.collapsed)').length > 0;

			if (anyOpen) {
				$sections.addClass('collapsed');
				$sections.find('.aips-group-header').attr('aria-expanded', 'false');
				$('.aips-toggle-all-text').text(this.config.expandAll || 'Expand All');
			} else {
				$sections.removeClass('collapsed');
				$sections.find('.aips-group-header').attr('aria-expanded', 'true');
				$('.aips-toggle-all-text').text(this.config.collapseAll || 'Collapse All');
			}
		},

		/**
		 * Handle toggling individual row action overflow menu.
		 *
		 * @param {Event} e Click event.
		 */
		handleOverflowToggle: function(e) {
			e.preventDefault();
			e.stopPropagation();

			var $btn = $(e.currentTarget);
			var menuId = $btn.attr('aria-controls');
			var $menu = $('#' + menuId);
			var isExpanded = $btn.attr('aria-expanded') === 'true';

			// Close all open action menus
			$('.aips-row-action-menu').attr('hidden', true);
			$('.aips-row-action-overflow-toggle').attr('aria-expanded', 'false');

			if (!isExpanded && $menu.length) {
				$menu.removeAttr('hidden');
				$btn.attr('aria-expanded', 'true');
			}
		},

		/**
		 * Handle document click to close open overflow menus.
		 *
		 * @param {Event} e Click event.
		 */
		handleDocumentClick: function(e) {
			if (!$(e.target).closest('.cell-actions, .aips-card-actions').length) {
				$('.aips-row-action-menu').attr('hidden', true);
				$('.aips-row-action-overflow-toggle').attr('aria-expanded', 'false');
			}
		},

		/**
		 * Handle quick inline post status change via AJAX.
		 *
		 * @param {Event} e Change event.
		 */
		handleQuickStatusChange: function(e) {
			var self = this;
			var $select = $(e.currentTarget);
			var postId = $select.data('post-id');
			var newStatus = $select.val();
			var $row = $select.closest('.aips-post-row-item, tr, .aips-post-card');
			var $pill = $row.find('.aips-status-pill');

			if (!postId || !newStatus) {
				return;
			}

			if (newStatus === 'trash') {
				var confirmMsg = this.config.confirmTrash || 'Are you sure you want to move this post to trash?';
				if (AIPS.Utilities && typeof AIPS.Utilities.confirm === 'function') {
					AIPS.Utilities.confirm(
						confirmMsg,
						'Move to Trash',
						[
							{
								label: 'Cancel',
								className: 'aips-btn aips-btn-secondary',
								action: function() {
									$select.val($select.data('previous-status') || 'publish');
								}
							},
							{
								label: 'Move to Trash',
								className: 'aips-btn aips-btn-danger-solid',
								action: function() {
									self.executeStatusUpdate($select, postId, newStatus, $pill);
								}
							}
						]
					);
					return;
				}
			}

			this.executeStatusUpdate($select, postId, newStatus, $pill);
		},

		/**
		 * Perform AJAX status update request.
		 *
		 * @param {jQuery} $select   Dropdown element.
		 * @param {number} postId    Post ID.
		 * @param {string} newStatus New post status.
		 * @param {jQuery} $pill     Status pill badge element.
		 */
		executeStatusUpdate: function($select, postId, newStatus, $pill) {
			var self = this;
			$select.prop('disabled', true);

			$.ajax({
				url: self.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_update_post_status',
					post_id: postId,
					new_status: newStatus,
					nonce: self.nonce
				},
				success: function(response) {
					$select.prop('disabled', false);
					if (response && response.success) {
						var data = response.data || {};
						$select.data('previous-status', data.new_status || newStatus);

						$pill.removeClass('status-publish status-future status-draft status-trash')
							.addClass('status-' + (data.new_status || newStatus))
							.text(data.status_label || newStatus);

						var successMsg = data.message || self.config.statusUpdated || 'Post status updated.';
						if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
							AIPS.Utilities.showToast(successMsg, 'success');
						}
					} else {
						var errorMsg = (response && response.data && response.data.message) ? response.data.message : (self.config.statusError || 'Failed to update post status.');
						if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
							AIPS.Utilities.showToast(errorMsg, 'error');
						}
					}
				},
				error: function() {
					$select.prop('disabled', false);
					var errorMsg = self.config.statusError || 'Failed to update post status.';
					if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
						AIPS.Utilities.showToast(errorMsg, 'error');
					}
				}
			});
		},

		/**
		 * Handle master checkbox selection toggle.
		 *
		 * @param {Event} e Change event.
		 */
		handleSelectAllChange: function(e) {
			var isChecked = $(e.currentTarget).is(':checked');
			$('.aips-post-checkbox').prop('checked', isChecked);
			this.updateSelectedCount();
		},

		/**
		 * Handle individual post checkbox change.
		 *
		 * @param {Event} e Change event.
		 */
		handlePostCheckboxChange: function(e) {
			this.updateSelectedCount();
		},

		/**
		 * Recalculate selected posts counter and master checkbox state.
		 */
		updateSelectedCount: function() {
			var checkedCount = $('.aips-post-checkbox:checked').length;
			var $counter = $('#aips-selected-count');
			if (checkedCount > 0) {
				$counter.text(checkedCount + ' selected').show();
			} else {
				$counter.hide();
			}

			var totalCheckboxes = $('.aips-post-checkbox').length;
			$('#aips-select-all-posts').prop('checked', totalCheckboxes > 0 && checkedCount === totalCheckboxes);
		},

		/**
		 * Handle submitting bulk actions via AJAX.
		 *
		 * @param {Event} e Click event.
		 */
		handleApplyBulkAction: function(e) {
			e.preventDefault();
			var self = this;
			var bulkAction = $('#aips-bulk-action-select').val();
			var selectedIds = [];

			$('.aips-post-checkbox:checked').each(function() {
				var val = parseInt($(this).val(), 10);
				if (val) {
					selectedIds.push(val);
				}
			});

			if (!bulkAction) {
				if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
					AIPS.Utilities.showToast('Please select a bulk action.', 'warning');
				}
				return;
			}

			if (selectedIds.length === 0) {
				var noPostsMsg = this.config.noPostsSelected || 'Please select at least one post.';
				if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
					AIPS.Utilities.showToast(noPostsMsg, 'warning');
				}
				return;
			}

			var confirmText = '';
			var confirmHeading = 'Confirm Bulk Action';
			var confirmActionClass = 'aips-btn aips-btn-primary';
			var confirmActionLabel = 'Confirm';

			if (bulkAction === 'trash') {
				confirmText = (this.config.confirmBulkTrash || 'Move %d post(s) to trash?').replace('%d', selectedIds.length);
				confirmHeading = 'Move to Trash';
				confirmActionClass = 'aips-btn aips-btn-danger-solid';
				confirmActionLabel = 'Move to Trash';
			} else if (bulkAction === 'publish') {
				confirmText = (this.config.confirmBulkPublish || 'Publish %d post(s)?').replace('%d', selectedIds.length);
				confirmHeading = 'Publish Posts';
				confirmActionLabel = 'Publish';
			} else if (bulkAction === 'draft') {
				confirmText = (this.config.confirmBulkDraft || 'Set %d post(s) to draft?').replace('%d', selectedIds.length);
				confirmHeading = 'Set to Draft';
				confirmActionLabel = 'Set to Draft';
			}

			var $btn = $(e.currentTarget);

			if (confirmText && AIPS.Utilities && typeof AIPS.Utilities.confirm === 'function') {
				AIPS.Utilities.confirm(
					confirmText,
					confirmHeading,
					[
						{
							label: 'Cancel',
							className: 'aips-btn aips-btn-secondary'
						},
						{
							label: confirmActionLabel,
							className: confirmActionClass,
							action: function() {
								self.executeBulkAction($btn, bulkAction, selectedIds);
							}
						}
					]
				);
				return;
			}

			this.executeBulkAction($btn, bulkAction, selectedIds);
		},

		/**
		 * Execute bulk action AJAX call.
		 *
		 * @param {jQuery} $btn        Bulk action submit button.
		 * @param {string} bulkAction  Selected action name.
		 * @param {Array}  selectedIds Array of post IDs.
		 */
		executeBulkAction: function($btn, bulkAction, selectedIds) {
			var self = this;
			$btn.prop('disabled', true);

			$.ajax({
				url: self.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_bulk_generated_posts_action',
					bulk_action: bulkAction,
					post_ids: selectedIds,
					nonce: self.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false);
					if (response && response.success) {
						if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
							AIPS.Utilities.showToast(self.config.bulkSuccess || 'Bulk action completed successfully.', 'success');
						}
						setTimeout(function() {
							window.location.reload();
						}, 500);
					} else {
						var msg = (response && response.data && response.data.message) ? response.data.message : (self.config.bulkError || 'Failed to execute bulk action.');
						if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
							AIPS.Utilities.showToast(msg, 'error');
						}
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					var errorMsg = self.config.bulkError || 'Failed to execute bulk action.';
					if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
						AIPS.Utilities.showToast(errorMsg, 'error');
					}
				}
			});
		},

		/**
		 * Handle opening post preview modal iframe.
		 *
		 * @param {Event} e Click event.
		 */
		handlePreviewPost: function(e) {
			e.preventDefault();
			var postId = $(e.currentTarget).data('post-id');
			var siteUrl = this.config.siteUrl || '';
			var previewUrl = siteUrl + '/?p=' + postId + '&preview=true';

			$('#aips-post-preview-iframe').attr('src', previewUrl);
			$('#aips-post-preview-modal').fadeIn(200);
		},

		/**
		 * Handle closing post preview modal.
		 *
		 * @param {Event} e Click event.
		 */
		handleClosePreviewModal: function(e) {
			e.preventDefault();
			this.closePreviewModal();
		},

		/**
		 * Close preview modal and clear iframe src.
		 */
		closePreviewModal: function() {
			var $modal = $('#aips-post-preview-modal');
			var $iframe = $('#aips-post-preview-iframe');
			$modal.fadeOut(200, function() {
				$iframe.attr('src', '');
			});
		},

		/**
		 * Handle keyboard navigation (e.g. Escape to close preview modal).
		 *
		 * @param {Event} e Keydown event.
		 */
		handleKeyDown: function(e) {
			if (e.key === 'Escape' && $('#aips-post-preview-modal').is(':visible')) {
				this.closePreviewModal();
			}
		},

		/**
		 * Handle clicking Edit button to navigate to post edit URL.
		 *
		 * @param {Event} e Click event.
		 */
		handleEditPost: function(e) {
			var editUrl = $(e.currentTarget).data('edit-url');
			if (editUrl) {
				window.location.href = editUrl;
			}
		}
	};

	$(document).ready(function() {
		AIPS.GeneratedPosts.init();
	});
})(jQuery);