/**
 * Authors Management JavaScript
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

(function ($) {
	'use strict';

	// Shared utility for showing toast notifications
	const showToast = function (message, type = 'info', duration = 5000) {
		const iconMap = {
			success: '✓',
			error: '✕',
			warning: '⚠',
			info: 'ℹ'
		};

		// Ensure toast container exists
		let $container = $('#aips-toast-container');
		if (!$container.length) {
			$container = $('<div id="aips-toast-container"></div>');
			$('body').append($container);
		}

		const closeLabel = ( window.aipsAuthorsL10n && aipsAuthorsL10n.toastCloseLabel ) ? aipsAuthorsL10n.toastCloseLabel : 'Close';

		const $toast = $('<div class="aips-toast ' + type + '">')
			.append('<span class="aips-toast-icon">' + iconMap[type] + '</span>')
			.append('<div class="aips-toast-message">' + $('<div>').text(message).html() + '</div>')
			.append('<button class="aips-toast-close" aria-label="' + String(closeLabel).replace(/"/g, '&quot;') + '">&times;</button>');

		$container.append($toast);

		// Close on click
		$toast.find('.aips-toast-close').on('click', function() {
			$toast.addClass('closing');
			setTimeout(() => $toast.remove(), 300);
		});

		// Auto close
		if (duration > 0) {
			setTimeout(() => {
				if ($toast.length) {
					$toast.addClass('closing');
					setTimeout(() => $toast.remove(), 300);
				}
			}, duration);
		}
	};

	// Authors Module
	const AuthorsModule = {
		currentAuthorId: null,

		init: function () {
			this.bindEvents();
			this.setupKeyboardNavigation();
		},

		bindEvents: function () {
			// Add Author Button
			$('.aips-add-author-btn').on('click', this.openAddModal.bind(this));

			// Edit Author Button
			$(document).on('click', '.aips-edit-author', this.editAuthor.bind(this));

			// View Topics Button
			$(document).on('click', '.aips-view-author', this.viewTopics.bind(this));

			// Generate Topics Now Button
			$(document).on('click', '.aips-generate-topics-now', this.generateTopicsNow.bind(this));

			// Delete Author Button
			$(document).on('click', '.aips-delete-author', this.deleteAuthor.bind(this));

			// Close Modal
			$('.aips-modal-close').on('click', this.closeModals.bind(this));

			// Submit Author Form
			$('#aips-author-form').on('submit', this.saveAuthor.bind(this));

			// Submit Feedback Form
			$('#aips-feedback-form').on('submit', this.submitFeedback.bind(this));

			// Tab switching in topics modal
			$(document).on('click', '.aips-tab-link', this.switchTab.bind(this));

			// Topic actions
			$(document).on('click', '.aips-approve-topic', this.approveTopic.bind(this));
			$(document).on('click', '.aips-reject-topic', this.rejectTopic.bind(this));
			$(document).on('click', '.aips-delete-topic', this.deleteTopic.bind(this));
			$(document).on('click', '.aips-edit-topic', this.editTopic.bind(this));
			$(document).on('click', '.aips-save-topic', this.saveTopic.bind(this));
			$(document).on('click', '.aips-cancel-edit-topic', this.cancelEditTopic.bind(this));
			$(document).on('click', '.aips-generate-post-now', this.generatePostNow.bind(this));
			$(document).on('click', '.aips-view-topic-log', this.viewTopicLog.bind(this));

			// Bulk actions
			$(document).on('click', '.aips-select-all-topics', this.toggleSelectAll.bind(this));
			$(document).on('click', '.aips-select-all-feedback', this.toggleSelectAllFeedback.bind(this));
			$(document).on('click', '.aips-bulk-action-execute', this.executeBulkAction.bind(this));

			// View topic posts
			$(document).on('click', '.aips-post-count-badge', this.viewTopicPosts.bind(this));

			// Topic detail expand/collapse
			$(document).on('click', '.aips-topic-expand-btn', this.toggleTopicDetail.bind(this));
		},

		/**
		 * Sets up keyboard navigation for the topics list.
		 * Supports j/k for navigation and a/r for actions.
		 */
		setupKeyboardNavigation: function() {
			$(document).on('keydown', (e) => {
				if (!$('#aips-topics-modal').is(':visible')) return;

				// If focus is in an input, ignore navigation keys
				if ($(e.target).is('input, textarea')) return;

				const $rows = $('.aips-topic-row:visible');
				if ($rows.length === 0) return;

				const $selected = $('.aips-topic-row.selected-topic:visible');
				let index = $rows.index($selected);

				switch(e.key) {
					case 'j':
					case 'ArrowDown':
						e.preventDefault();
						if (index < $rows.length - 1) {
							this.selectRow($rows.eq(index + 1));
						} else if (index === -1) {
							this.selectRow($rows.eq(0));
						}
						break;
					case 'k':
					case 'ArrowUp':
						e.preventDefault();
						if (index > 0) {
							this.selectRow($rows.eq(index - 1));
						} else if (index === -1 && $rows.length > 0) {
                            this.selectRow($rows.eq($rows.length - 1));
                        }
						break;
					case 'a':
						if ($selected.length) {
							const $btn = $selected.find('.aips-approve-topic');
							if ($btn.length) $btn.trigger('click');
						}
						break;
					case 'r':
						if ($selected.length) {
							const $btn = $selected.find('.aips-reject-topic');
							if ($btn.length) $btn.trigger('click');
						}
						break;
					case 'd':
						if ($selected.length) {
							const $btn = $selected.find('.aips-delete-topic');
							if ($btn.length) $btn.trigger('click');
						}
						break;
				}
			});

			// Click to select
			$(document).on('click', '.aips-topic-row', (e) => {
				this.selectRow($(e.currentTarget));
			});
		},

		/**
		 * Selects a topic row and scrolls it into view.
		 *
		 * @param {jQuery} $row The row element to select.
		 */
		selectRow: function($row) {
			$('.aips-topic-row').removeClass('selected-topic');
			$row.addClass('selected-topic');

			// Ensure visible
			if ($row.length && $row[0].scrollIntoView) {
				$row[0].scrollIntoView({block: 'nearest', behavior: 'smooth'});
			}
		},

		openAddModal: function (e) {
			e.preventDefault();
			$('#aips-author-modal-title').text(aipsAuthorsL10n.addNewAuthor);
			$('#aips-author-form')[0].reset();
			$('#author_id').val('');
			$('#aips-author-modal').fadeIn();
		},

		editAuthor: function (e) {
			e.preventDefault();
			const authorId = $(e.currentTarget).data('id');
			this.currentAuthorId = authorId;

			// Show loading
			$('#aips-author-modal-title').text(aipsAuthorsL10n.loading);
			$('#aips-author-modal').fadeIn();

			// Load author data
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_author',
					nonce: aipsAuthorsL10n.nonce,
					author_id: authorId
				},
				success: (response) => {
					if (response.success && response.data.author) {
						const author = response.data.author;

						$('#aips-author-modal-title').text(aipsAuthorsL10n.editAuthor);
						$('#author_id').val(author.id);
						$('#author_name').val(author.name);
						$('#author_field_niche').val(author.field_niche);
						$('#author_description').val(author.description);
						$('#author_keywords').val(author.keywords || '');
						$('#author_details').val(author.details || '');
						$('#article_structure_id').val(author.article_structure_id || '');
						$('#topic_generation_quantity').val(author.topic_generation_quantity);
						$('#topic_generation_frequency').val(author.topic_generation_frequency);
						$('#post_generation_frequency').val(author.post_generation_frequency);
						$('#is_active').prop('checked', author.is_active == 1);
					} else {
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorLoading, 'error');

						$('#aips-author-modal').fadeOut();
					}
				},
				error: () => {
					showToast(aipsAuthorsL10n.errorLoading, 'error');

					$('#aips-author-modal').fadeOut();
				}
			});
		},

		saveAuthor: function (e) {
			e.preventDefault();

			const $form = $('#aips-author-form');
			const $submitBtn = $form.find('[type="submit"]');
			const formData = $form.serialize();

			// Disable submit button
			$submitBtn.prop('disabled', true).text(aipsAuthorsL10n.saving);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData + '&action=aips_save_author&nonce=' + aipsAuthorsL10n.nonce,
				success: (response) => {
					if (response.success) {
						showToast(response.data.message || aipsAuthorsL10n.authorSaved, 'success');

						setTimeout(() => location.reload(), 1000);
					} else {
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorSaving, 'error');
					}
				},
				error: () => {
					showToast(aipsAuthorsL10n.errorSaving, 'error');
				},
				complete: () => {
					$submitBtn.prop('disabled', false).text(aipsAuthorsL10n.saveAuthor);
				}
			});
		},

		deleteAuthor: function (e) {
			e.preventDefault();
			const authorId = $(e.currentTarget).data('id');

			if (!confirm(aipsAuthorsL10n.confirmDelete)) {
				return;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_delete_author',
					nonce: aipsAuthorsL10n.nonce,
					author_id: authorId
				},
				success: (response) => {
					if (response.success) {
						showToast(response.data.message || aipsAuthorsL10n.authorDeleted, 'success');

						setTimeout(() => location.reload(), 1000);
					} else {
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorDeleting, 'error');
					}
				},
				error: () => {
					showToast(aipsAuthorsL10n.errorDeleting, 'error');
				}
			});
		},

		generateTopicsNow: function (e) {
			e.preventDefault();

			const authorId = $(e.currentTarget).data('id');

			if (!confirm(aipsAuthorsL10n.confirmGenerateTopics)) {
				return;
			}

			const $btn = $(e.currentTarget);

			$btn.prop('disabled', true).text(aipsAuthorsL10n.generating);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_generate_topics_now',
					nonce: aipsAuthorsL10n.nonce,
					author_id: authorId
				},
				success: (response) => {
					if (response.success) {
						showToast(response.data.message || aipsAuthorsL10n.topicsGenerated, 'success');

						setTimeout(() => location.reload(), 1000);
					} else {
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorGenerating, 'error');
					}
				},
				error: () => {
					showToast(aipsAuthorsL10n.errorGenerating, 'error');
				},
				complete: () => {
					$btn.prop('disabled', false).text(aipsAuthorsL10n.generateTopicsNow);
				}
			});
		},

		viewTopics: function (e) {
			e.preventDefault();

			const authorId = $(e.currentTarget).data('id');
			this.currentAuthorId = authorId;

			$('#aips-topics-content').html('<p>' + aipsAuthorsL10n.loadingTopics + '</p>');

			// Reset tabs to pending
			$('.aips-tab-link').removeClass('active');
			$('.aips-tab-link[data-tab="pending"]').addClass('active');

			// Update bulk action dropdown for pending tab
			this.updateBulkActionDropdown('pending');

			$('#aips-topics-modal').fadeIn();

			this.loadTopics('pending');
		},

		loadTopics: function (status) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_author_topics',
					nonce: aipsAuthorsL10n.nonce,
					author_id: this.currentAuthorId,
					status: status
				},
				success: (response) => {
					if (response.success) {
						this.renderTopics(response.data.topics, status);
						this.updateTopicCounts(response.data.status_counts);
						// Select first row by default for keyboard nav
						if (response.data.topics && response.data.topics.length > 0) {
							setTimeout(() => {
								this.selectRow($('.aips-topic-row').first());
							}, 50);
						}
					} else {
						$('#aips-topics-content').html('<p>' + (response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorLoadingTopics) + '</p>');
					}
				},
				error: () => {
					$('#aips-topics-content').html('<p>' + aipsAuthorsL10n.errorLoadingTopics + '</p>');
				}
			});
		},

		renderTopics: function (topics, status) {
			if (!topics || topics.length === 0) {
				$('#aips-topics-content').html('<p>' + aipsAuthorsL10n.noTopicsFound + '</p>');
				return;
			}

			let html = '<table class="wp-list-table widefat fixed striped aips-topics-table"><thead><tr>';
			html += '<th class="check-column"><input type="checkbox" class="aips-select-all-topics"></th>';
			html += '<th>' + aipsAuthorsL10n.topicTitle + '</th>';
			html += '<th>' + aipsAuthorsL10n.generatedAt + '</th>';
			html += '<th>' + aipsAuthorsL10n.actions + '</th>';
			html += '</tr></thead><tbody>';

			topics.forEach(topic => {
				html += '<tr data-topic-id="' + topic.id + '" class="aips-topic-row">';
				html += '<th class="check-column"><input type="checkbox" class="aips-topic-checkbox" value="' + topic.id + '"></th>';
				html += '<td class="topic-title-cell">';
				html += '<button class="aips-topic-expand-btn" data-topic-id="' + topic.id + '" title="' + (aipsAuthorsL10n.viewDetails || 'View Details') + '" aria-expanded="false" aria-controls="aips-topic-details-' + topic.id + '">';
				html += '<span class="dashicons dashicons-arrow-right-alt2"></span>';
				html += '</button> ';
				html += '<span class="topic-title">' + this.escapeHtml(topic.topic_title) + '</span>';

				// Add post count badge if there are any posts
				if (topic.post_count && topic.post_count > 0) {
					html += ' <span class="aips-post-count-badge" data-topic-id="' + topic.id + '" title="' + aipsAuthorsL10n.viewPosts + '">';
					html += '<span class="dashicons dashicons-admin-post"></span> ' + topic.post_count;
					html += '</span>';
				}

				html += '<input type="text" class="topic-title-edit" style="display:none;" value="' + this.escapeHtml(topic.topic_title) + '">';
				html += '</td>';
				html += '<td>' + topic.generated_at + '</td>';
				html += '<td class="topic-actions">';

				// Actions based on status
				if (status === 'pending') {
					// Use title attribute to show shortcut hint
					html += '<button class="button aips-approve-topic" data-id="' + topic.id + '" title="' + (aipsAuthorsL10n.approve + ' (Shift+Click for details)') + '">' + aipsAuthorsL10n.approve + '</button> ';
					html += '<button class="button aips-reject-topic" data-id="' + topic.id + '" title="' + (aipsAuthorsL10n.reject + ' (Shift+Click for details)') + '">' + aipsAuthorsL10n.reject + '</button> ';
				} else if (status === 'approved') {
					html += '<button class="button aips-generate-post-now" data-id="' + topic.id + '">' + aipsAuthorsL10n.generatePostNow + '</button> ';
				}

				html += '<button class="button aips-edit-topic" data-id="' + topic.id + '">' + aipsAuthorsL10n.edit + '</button>';
				html += '</td></tr>';

				// Add collapsible detail row
				html += '<tr class="aips-topic-detail-row" data-topic-id="' + topic.id + '" style="display:none;">';
				html += '<td colspan="4" class="aips-topic-detail-cell">';
				html += '<div class="aips-topic-detail-content">';
				if (topic.topic_description) {
					html += '<div class="aips-detail-section"><strong>' + (aipsAuthorsL10n.description || 'Description') + ':</strong> ' + this.escapeHtml(topic.topic_description) + '</div>';
				}
				if (topic.topic_rationale) {
					html += '<div class="aips-detail-section"><strong>' + (aipsAuthorsL10n.rationale || 'Rationale') + ':</strong> ' + this.escapeHtml(topic.topic_rationale) + '</div>';
				}
				if (topic.reviewed_at && topic.reviewed_by) {
					html += '<div class="aips-detail-section"><strong>' + (aipsAuthorsL10n.reviewed || 'Reviewed') + ':</strong> ' + this.escapeHtml(String(topic.reviewed_at)) + ' by User ID ' + this.escapeHtml(String(topic.reviewed_by)) + '</div>';
				}
				html += '</div></td></tr>';
			});

			html += '</tbody></table>';
			$('#aips-topics-content').html(html);
		},

		updateTopicCounts: function (counts) {
			$('#pending-count').text(counts.pending || 0);
			$('#approved-count').text(counts.approved || 0);
			$('#rejected-count').text(counts.rejected || 0);
		},

		switchTab: function (e) {
			e.preventDefault();
			const $tab = $(e.currentTarget);
			const status = $tab.data('tab');

			$('.aips-tab-link').removeClass('active');
			$tab.addClass('active');

			// Add fade transition
			$('#aips-topics-content').fadeOut(200, () => {
				if (status === 'feedback') {
					this.loadFeedback();
				} else {
					this.loadTopics(status);
				}
				$('#aips-topics-content').fadeIn(200);
			});

			// Update bulk action dropdown options based on tab
			this.updateBulkActionDropdown(status);
		},

		updateBulkActionDropdown: function (status) {
			const $dropdowns = $('.aips-bulk-action-select');

			// Clear existing options except the default one
			$dropdowns.each(function() {
				const $dropdown = $(this);
				$dropdown.find('option:not(:first)').remove();

				// Add options based on the active tab
				if (status === 'pending') {
					// Pending Review tab: Approve, Reject, Delete
					$dropdown.append('<option value="approve">' + (aipsAuthorsL10n.approve || 'Approve') + '</option>');
					$dropdown.append('<option value="reject">' + (aipsAuthorsL10n.reject || 'Reject') + '</option>');
					$dropdown.append('<option value="delete">' + (aipsAuthorsL10n.delete || 'Delete') + '</option>');
				} else if (status === 'approved') {
					// Approved tab: Generate Now, Delete (no Approve)
					$dropdown.append('<option value="generate_now">' + (aipsAuthorsL10n.generateNow || 'Generate Now') + '</option>');
					$dropdown.append('<option value="delete">' + (aipsAuthorsL10n.delete || 'Delete') + '</option>');
				} else if (status === 'rejected') {
					// Rejected tab: Delete only (no Reject, no Generate Now)
					$dropdown.append('<option value="delete">' + (aipsAuthorsL10n.delete || 'Delete') + '</option>');
				} else if (status === 'feedback') {
					// Feedback tab: Delete only (no Approve, Generate Now, or Reject)
					$dropdown.append('<option value="delete">' + (aipsAuthorsL10n.delete || 'Delete') + '</option>');
				}
			});
		},

		toggleTopicDetail: function (e) {
			e.preventDefault();
			const $button = $(e.currentTarget);
			const $row = $button.closest('tr');
			const $detailRow = $row.next('.aips-topic-detail-row');

			// If no corresponding detail row is found, do nothing.
			if (!$detailRow.length) {
				return;
			}

			const $icon = $button.find('.dashicons');

			if ($detailRow.is(':visible')) {
				$detailRow.slideUp(200);
				$icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
				$button.attr('aria-expanded', 'false');
			} else {
				$detailRow.slideDown(200);
				$icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
				$button.attr('aria-expanded', 'true');
			}
		},

		approveTopic: function (e) {
			e.preventDefault();
			e.stopPropagation();
			const topicId = $(e.currentTarget).data('id');

			// Check for Shift key (Power User mode)
			if (e.shiftKey) {
				// Open feedback modal
				$('#feedback_topic_id').val(topicId);
				$('#feedback_action').val('approve');
				$('#aips-feedback-modal-title').text(aipsAuthorsL10n.approveTopicTitle || 'Approve Topic');
				$('#feedback_reason').attr('placeholder', aipsAuthorsL10n.approveReasonPlaceholder || 'Why are you approving this topic?');
				$('#feedback-submit-btn').text(aipsAuthorsL10n.approve);
				$('#aips-feedback-modal').fadeIn();
			} else {
				// Quick action
				this.quickAction(topicId, 'approve');
			}
		},

		rejectTopic: function (e) {
			e.preventDefault();
			e.stopPropagation();
			const topicId = $(e.currentTarget).data('id');

			// Check for Shift key (Power User mode)
			if (e.shiftKey) {
				// Open feedback modal
				$('#feedback_topic_id').val(topicId);
				$('#feedback_action').val('reject');
				$('#aips-feedback-modal-title').text(aipsAuthorsL10n.rejectTopicTitle || 'Reject Topic');
				$('#feedback_reason').attr('placeholder', aipsAuthorsL10n.rejectReasonPlaceholder || 'Why are you rejecting this topic?');
				$('#feedback-submit-btn').text(aipsAuthorsL10n.reject);
				$('#aips-feedback-modal').fadeIn();
			} else {
				// Quick action
				this.quickAction(topicId, 'reject');
			}
		},

		/**
		 * Performs a quick action on a topic.
		 *
		 * @param {number} topicId The ID of the topic to act on.
		 * @param {string} action The action to perform ('approve' or 'reject').
		 */
		quickAction: function(topicId, action) {
			const ajaxAction = action === 'approve' ? 'aips_approve_topic' : 'aips_reject_topic';
			const $row = $('.aips-topic-row[data-topic-id="' + topicId + '"]');
			const $buttons = $row.find('button');

			// Optimistic UI state
			$row.addClass('processing');
			$buttons.prop('disabled', true);
			$row.find('.topic-actions').append('<span class="spinner is-active" style="float:none; margin:0 0 0 5px;"></span>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: ajaxAction,
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId
				},
				success: (response) => {
					if (response.success) {
						// Success! Remove row and update UI
						$row.next('.aips-topic-detail-row').remove(); // Remove detail row
						$row.slideUp(200, () => {
							$row.remove();

							// If no rows left, show empty state
							if ($('.aips-topic-row').length === 0) {
								$('#aips-topics-content').html('<p>' + aipsAuthorsL10n.noTopicsFound + '</p>');
							} else {
								// Select next row for keyboard flow
								const $next = $('.aips-topic-row').first();
								if ($next.length) {
									this.selectRow($next);
								}
							}
						});

						this.updateLocalCounts(action);
						showToast(response.data.message || 'Success', 'success');
					} else {
						// Revert UI on error
						$row.removeClass('processing');
						$buttons.prop('disabled', false);
						$row.find('.spinner').remove();
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorSaving, 'error');
					}
				},
				error: () => {
					// Revert UI on error
					$row.removeClass('processing');
					$buttons.prop('disabled', false);
					$row.find('.spinner').remove();
					showToast(aipsAuthorsL10n.errorOccurred || 'Error occurred', 'error');
				}
			});
		},

		/**
		 * Updates the topic counts in the DOM.
		 *
		 * @param {string} action The action performed ('approve', 'reject', or 'delete').
		 */
		updateLocalCounts: function(action) {
			// Parse current counts
			let pending = parseInt($('#pending-count').text()) || 0;
			let approved = parseInt($('#approved-count').text()) || 0;
			let rejected = parseInt($('#rejected-count').text()) || 0;

			// Logic assumes we are in "Pending" tab because quick actions (approve/reject) are only there
			// If we implement quick actions elsewhere, we need to check current tab
			const currentTab = $('.aips-tab-link.active').data('tab');

			if (currentTab === 'pending') {
				pending = Math.max(0, pending - 1);
				if (action === 'approve') {
					approved++;
				} else if (action === 'reject') {
					rejected++;
				}
			} else if (currentTab === 'approved' && action === 'delete') {
				approved = Math.max(0, approved - 1);
			} else if (currentTab === 'rejected' && action === 'delete') {
				rejected = Math.max(0, rejected - 1);
			}

			// Update DOM
			$('#pending-count').text(pending);
			$('#approved-count').text(approved);
			$('#rejected-count').text(rejected);
		},

		submitFeedback: function (e) {
			e.preventDefault();

			const topicId = $('#feedback_topic_id').val();
			const action = $('#feedback_action').val();
			const reason = $('#feedback_reason').val();

			const ajaxAction = action === 'approve' ? 'aips_approve_topic' : 'aips_reject_topic';

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: ajaxAction,
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId,
					reason: reason
				},
				success: (response) => {
					if (response.success) {
						$('#aips-feedback-modal').fadeOut();
						$('#aips-feedback-form')[0].reset();

						this.loadTopics('pending');
					} else {
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorSaving, 'error');
					}
				},
				error: () => {
					showToast(action === 'approve' ? aipsAuthorsL10n.errorApproving : aipsAuthorsL10n.errorRejecting, 'error');
				}
			});
		},

		loadFeedback: function () {
			if (!this.currentAuthorId) {
				$('#aips-topics-content').html('<p>No author selected.</p>');

				return;
			}

			$('#aips-topics-content').html('<p>' + aipsAuthorsL10n.loading + '</p>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_author_feedback',
					nonce: aipsAuthorsL10n.nonce,
					author_id: this.currentAuthorId
				},
				success: (response) => {
					if (response.success && response.data.feedback) {
						this.renderFeedback(response.data.feedback);
					} else {
						$('#aips-topics-content').html('<p>No feedback found.</p>');
					}
				},
				error: () => {
					$('#aips-topics-content').html('<p>Error loading feedback.</p>');
				}
			});
		},

		renderFeedback: function (feedback) {
			if (feedback.length === 0) {
				$('#aips-topics-content').html('<p>No feedback yet.</p>');
				return;
			}

			let html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';
			html += '<th class="check-column"><input type="checkbox" class="aips-select-all-feedback"></th>';
			html += '<th>' + aipsAuthorsL10n.topic + '</th>';
			html += '<th>' + aipsAuthorsL10n.action + '</th>';
			html += '<th>' + aipsAuthorsL10n.reason + '</th>';
			html += '<th>' + aipsAuthorsL10n.user + '</th>';
			html += '<th>' + aipsAuthorsL10n.date + '</th>';
			html += '</tr></thead><tbody>';

			feedback.forEach(item => {
				html += '<tr>';
				html += '<th class="check-column"><input type="checkbox" class="aips-feedback-checkbox" value="' + item.id + '"></th>';
				html += '<td>' + this.escapeHtml(item.topic_title || 'N/A') + '</td>';
				html += '<td><span class="aips-status aips-status-' + item.action + '">' + item.action + '</span></td>';
				html += '<td>' + this.escapeHtml(item.reason || '-') + '</td>';
				html += '<td>' + this.escapeHtml(item.user_name || 'Unknown') + '</td>';
				html += '<td>' + item.created_at + '</td>';
				html += '</tr>';
			});

			html += '</tbody></table>';
			$('#aips-topics-content').html(html);
		},

		deleteTopic: function (e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('id');

			if (!confirm(aipsAuthorsL10n.confirmDeleteTopic)) {
				return;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_delete_topic',
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId
				},
				success: (response) => {
					if (response.success) {
						const activeTab = $('.aips-tab-link.active').data('tab');
						this.loadTopics(activeTab);
					} else {
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorDeletingTopic, 'error');
					}
				},
				error: () => {
					showToast(aipsAuthorsL10n.errorDeletingTopic, 'error');
				}
			});
		},

		editTopic: function (e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);
			const $row = $btn.closest('tr');
			const $titleSpan = $row.find('.topic-title');
			const $titleInput = $row.find('.topic-title-edit');

			$titleSpan.hide();
			$titleInput.show().focus();

			$btn.hide();
			$row.find('.topic-actions').append(
				'<button class="button aips-save-topic">' + aipsAuthorsL10n.save + '</button> ' +
				'<button class="button aips-cancel-edit-topic">' + aipsAuthorsL10n.cancel + '</button>'
			);
		},

		saveTopic: function (e) {
			e.preventDefault();
			const $row = $(e.currentTarget).closest('tr');
			const topicId = $row.data('topic-id');
			const newTitle = $row.find('.topic-title-edit').val();

			if (!newTitle.trim()) {
				showToast(aipsAuthorsL10n.topicTitleRequired, 'warning');
				return;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_edit_topic',
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId,
					topic_title: newTitle
				},
				success: (response) => {
					if (response.success) {
						$row.find('.topic-title').text(newTitle).show();
						$row.find('.topic-title-edit').hide();
						$row.find('.aips-edit-topic').show();
						$row.find('.aips-save-topic, .aips-cancel-edit-topic').remove();
					} else {
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorSavingTopic);
					}
				},
				error: () => {
					showToast(aipsAuthorsL10n.errorSavingTopic, 'error');
				}
			});
		},

		cancelEditTopic: function (e) {
			e.preventDefault();
			const $row = $(e.currentTarget).closest('tr');
			$row.find('.topic-title').show();
			$row.find('.topic-title-edit').hide();
			$row.find('.aips-edit-topic').show();
			$row.find('.aips-save-topic, .aips-cancel-edit-topic').remove();
		},

		generatePostNow: function (e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('id');

			if (!confirm(aipsAuthorsL10n.confirmGeneratePost)) {
				return;
			}

			const $btn = $(e.currentTarget);
			$btn.prop('disabled', true).text(aipsAuthorsL10n.generating);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_generate_post_from_topic',
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId
				},
				success: (response) => {
					if (response.success) {
						showToast(aipsAuthorsL10n.postGenerated, 'success');
						const activeTab = $('.aips-tab-link.active').data('tab');
						this.loadTopics(activeTab);
					} else {
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorGeneratingPost, 'error');
						$btn.prop('disabled', false).text(aipsAuthorsL10n.generatePostNow);
					}
				},
				error: () => {
					showToast(aipsAuthorsL10n.errorGeneratingPost, 'error');
					$btn.prop('disabled', false).text(aipsAuthorsL10n.generatePostNow);
				}
			});
		},

		viewTopicLog: function (e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('id');

			$('#aips-topic-logs-content').html('<p>' + (aipsAuthorsL10n.logViewerLoading || 'Loading logs...') + '</p>');
			$('#aips-topic-logs-modal').fadeIn();

			this.loadTopicLogs(topicId);
		},

		loadTopicLogs: function (topicId) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_topic_logs',
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId
				},
				success: (response) => {
					if (response.success) {
						this.renderTopicLogs(response.data.logs);
					} else {
						 $('#aips-topic-logs-content').html(
							'<p>' + (response.data && response.data.message ? response.data.message : aipsAuthorsL10n.logViewerError) + '</p>'
						);
					}
				},
				error: () => {
					 $('#aips-topic-logs-content').html('<p>' + aipsAuthorsL10n.logViewerError + '</p>');
				}
			});
		},

		renderTopicLogs: function (logs) {
			if (!logs || logs.length === 0) {
				$('#aips-topic-logs-content').html('<p>' + aipsAuthorsL10n.noLogsFound + '</p>');
				return;
			}

			let html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';
			html += '<th>' + aipsAuthorsL10n.logAction + '</th>';
			html += '<th>' + aipsAuthorsL10n.logUser + '</th>';
			html += '<th>' + aipsAuthorsL10n.logDate + '</th>';
			html += '<th>' + aipsAuthorsL10n.logDetails + '</th>';
			html += '</tr></thead><tbody>';

			logs.forEach(log => {
				html += '<tr>';
				html += '<td><span class="aips-status aips-status-' + log.action + '">' + log.action + '</span></td>';
				html += '<td>' + this.escapeHtml(log.user_name || 'System') + '</td>';
				html += '<td>' + log.created_at + '</td>';
				html += '<td>' + this.escapeHtml(log.notes || '-') + '</td>';
				html += '</tr>';
			});

			html += '</tbody></table>';
			$('#aips-topic-logs-content').html(html);
		},

		viewTopicPosts: function (e) {
			e.preventDefault();
			e.stopPropagation();

			const topicId = $(e.currentTarget).data('topic-id');

			$('#aips-topic-posts-content').html('<p>' + aipsAuthorsL10n.loadingPosts + '</p>');
			$('#aips-topic-posts-modal').fadeIn();

			this.loadTopicPosts(topicId);
		},

		loadTopicPosts: function (topicId) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_topic_posts',
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId
				},
				success: (response) => {
					if (response.success) {
						const topic = response.data.topic;
						const posts = response.data.posts;

						$('#aips-topic-posts-modal-title').text(
							aipsAuthorsL10n.postsGeneratedFrom + ': ' + this.escapeHtml(topic.topic_title)
						);

						this.renderTopicPosts(posts);
					} else {
						$('#aips-topic-posts-content').html(
							'<p>' + (response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorLoadingPosts) + '</p>'
						);
					}
				},
				error: () => {
					$('#aips-topic-posts-content').html('<p>' + aipsAuthorsL10n.errorLoadingPosts + '</p>');
				}
			});
		},

		renderTopicPosts: function (posts) {
			if (!posts || posts.length === 0) {
				$('#aips-topic-posts-content').html('<p>' + aipsAuthorsL10n.noPostsFound + '</p>');
				return;
			}

			let html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';
			html += '<th>' + aipsAuthorsL10n.postId + '</th>';
			html += '<th>' + aipsAuthorsL10n.postTitle + '</th>';
			html += '<th>' + aipsAuthorsL10n.dateGenerated + '</th>';
			html += '<th>' + aipsAuthorsL10n.datePublished + '</th>';
			html += '<th>' + aipsAuthorsL10n.actions + '</th>';
			html += '</tr></thead><tbody>';

			posts.forEach(post => {
				html += '<tr>';
				html += '<td>' + this.escapeHtml(post.post_id) + '</td>';
				html += '<td>' + this.escapeHtml(post.post_title) + '</td>';
				html += '<td>' + this.escapeHtml(post.date_generated || '') + '</td>';
				html += '<td>' + this.escapeHtml(post.date_published || aipsAuthorsL10n.notPublished) + '</td>';
				html += '<td>';
				if (post.edit_url) {
					html += '<a href="' + this.sanitizeUrl(post.edit_url) + '" class="button" target="_blank">' + aipsAuthorsL10n.editPost + '</a> ';
				}
				if (post.post_url && post.post_status === 'publish') {
					html += '<a href="' + this.sanitizeUrl(post.post_url) + '" class="button" target="_blank">' + aipsAuthorsL10n.viewPost + '</a>';
				}
				html += '</td>';
				html += '</tr>';
			});

			html += '</tbody></table>';
			$('#aips-topic-posts-content').html(html);
		},

		toggleSelectAll: function (e) {
			const isChecked = $(e.currentTarget).prop('checked');
			$('.aips-topic-checkbox').prop('checked', isChecked);
		},

		toggleSelectAllFeedback: function (e) {
			const isChecked = $(e.currentTarget).prop('checked');
			$('.aips-feedback-checkbox').prop('checked', isChecked);
		},

		executeBulkAction: function (e) {
			e.preventDefault();

			// Get the dropdown closest to the clicked button
			const $button = $(e.currentTarget);
			const $dropdown = $button.siblings('.aips-bulk-action-select');
			const action = $dropdown.val();
			const activeTab = $('.aips-tab-link.active').data('tab');

			if (!action) {
				showToast(aipsAuthorsL10n.selectBulkAction || 'Please select a bulk action.', 'warning');
				return;
			}

			// Get checked IDs based on current tab
			const ids = [];
			if (activeTab === 'feedback') {
				$('.aips-feedback-checkbox:checked').each(function () {
					ids.push($(this).val());
				});
			} else {
				$('.aips-topic-checkbox:checked').each(function () {
					ids.push($(this).val());
				});
			}

			if (ids.length === 0) {
				const message = activeTab === 'feedback'
					? (aipsAuthorsL10n.noFeedbackSelected || 'Please select at least one feedback item.')
					: (aipsAuthorsL10n.noTopicsSelected || 'Please select at least one topic.');
				showToast(message, 'warning');
				return;
			}

			// Confirm action
			const confirmMessage = this.getBulkConfirmMessage(action, ids.length, activeTab);
			if (!confirm(confirmMessage)) {
				return;
			}

			// Disable button while processing
			$button.prop('disabled', true).text(aipsAuthorsL10n.processing || 'Processing...');

			// Determine the AJAX action and data
			let ajaxAction, data;
			if (activeTab === 'feedback') {
				if (action === 'delete') {
					ajaxAction = 'aips_bulk_delete_feedback';
					data = {
						action: ajaxAction,
						nonce: aipsAuthorsL10n.nonce,
						feedback_ids: ids
					};
				} else {
					showToast('Invalid bulk action for feedback.', 'error');
					$button.prop('disabled', false).text(aipsAuthorsL10n.execute || 'Execute');
					return;
				}
			} else {
				switch (action) {
					case 'approve':
						ajaxAction = 'aips_bulk_approve_topics';
						break;
					case 'reject':
						ajaxAction = 'aips_bulk_reject_topics';
						break;
					case 'delete':
						ajaxAction = 'aips_bulk_delete_topics';
						break;
					case 'generate_now':
						ajaxAction = 'aips_bulk_generate_topics';
						break;
					default:
						showToast('Invalid bulk action.', 'error');
						$button.prop('disabled', false).text(aipsAuthorsL10n.execute || 'Execute');
						return;
				}
				data = {
					action: ajaxAction,
					nonce: aipsAuthorsL10n.nonce,
					topic_ids: ids
				};
			}

			// Execute bulk action
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: data,
				success: (response) => {
					if (response.success) {
						showToast(response.data.message, 'success');
						// Reload content for current tab
						if (activeTab === 'feedback') {
							this.loadFeedback();
						} else {
							this.loadTopics(activeTab);
						}
					} else {
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorBulkAction || 'Error executing bulk action.', 'error');
					}
				},
				error: () => {
					showToast(aipsAuthorsL10n.errorBulkAction || 'Error executing bulk action.', 'error');
				},
				complete: () => {
					$button.prop('disabled', false).text(aipsAuthorsL10n.execute || 'Execute');
					// Reset dropdowns
					$('.aips-bulk-action-select').val('');
					// Uncheck all checkboxes
					$('.aips-select-all-topics').prop('checked', false);
					$('.aips-topic-checkbox').prop('checked', false);
					$('.aips-select-all-feedback').prop('checked', false);
					$('.aips-feedback-checkbox').prop('checked', false);
				}
			});
		},

		/**
		 * Generates a localized confirmation message for bulk actions.
		 *
		 * @param {string} action The action being performed.
		 * @param {number} count The number of items selected.
		 * @param {string} activeTab The currently active tab.
		 * @returns {string} The formatted confirmation message.
		 */
		getBulkConfirmMessage: function (action, count, activeTab) {
			const messages = {
				approve: aipsAuthorsL10n.confirmBulkApprove || 'Are you sure you want to approve %d topics?',
				reject: aipsAuthorsL10n.confirmBulkReject || 'Are you sure you want to reject %d topics?',
				delete: activeTab === 'feedback'
					? (aipsAuthorsL10n.confirmBulkDeleteFeedback || 'Are you sure you want to delete %d feedback items? This action cannot be undone.')
					: (aipsAuthorsL10n.confirmBulkDelete || 'Are you sure you want to delete %d topics? This action cannot be undone.'),
				generate_now: aipsAuthorsL10n.confirmBulkGenerate || 'Are you sure you want to generate posts for %d topics?'
			};
			const template = messages[action] || 'Are you sure you want to %s %d items?';
			return template.replace('%d', count).replace('%s', action);
		},

		closeModals: function (e) {
			e.preventDefault();
			$('.aips-modal').fadeOut();
		},

		escapeHtml: function (text) {
			try {
				// Handle null, undefined, or non-string values
				if (text === null || text === undefined) {
					return '';
				}

				// Convert to string if not already
				const str = String(text);

				const map = {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				};
				return str.replace(/[&<>"']/g, m => map[m]);
			} catch (error) {
				console.error('Error in escapeHtml:', error);
				// Return empty string as a safe fallback
				return '';
			}
		},

		/**
		 * Sanitize and validate URLs for use in href attributes
		 * Validates URL protocol and format without HTML-escaping (browsers handle href encoding)
		 * @param {string} url - The URL to sanitize
		 * @returns {string} - Validated URL or empty string if invalid
		 */
		sanitizeUrl: function (url) {
			try {
				// Handle null, undefined, or empty values
				if (!url) {
					return '';
				}

				// Convert to string and trim whitespace
				const urlStr = String(url).trim();

				if (!urlStr) {
					return '';
				}

				// Check for dangerous protocols (case-insensitive)
				const dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'file:'];
				const lowerUrl = urlStr.toLowerCase();

				for (const protocol of dangerousProtocols) {
					if (lowerUrl.startsWith(protocol)) {
						console.warn('Dangerous URL protocol detected:', protocol);
						return '';
					}
				}

				// For absolute URLs, validate with URL constructor
				if (urlStr.startsWith('http://') || urlStr.startsWith('https://')) {
					try {
						const urlObj = new URL(urlStr);
						// Return the normalized URL (URL constructor already handles encoding)
						return urlObj.href;
					} catch (e) {
						console.warn('Invalid URL format:', urlStr);
						return '';
					}
				}

				// For relative URLs (WordPress admin paths), return as-is after validation
				if (urlStr.startsWith('/')) {
					return urlStr;
				}

				// Reject anything else
				console.warn('URL does not match allowed patterns:', urlStr);
				return '';
			} catch (error) {
				console.error('Error in sanitizeUrl:', error);
				return '';
			}
		}
	};

	// Generation Queue Module
	const GenerationQueueModule = {
		init: function () {
			this.bindEvents();
		},

		bindEvents: function () {
			// Main page tab switching
			$(document).on('click', '.aips-authors-tab-link', this.switchMainTab.bind(this));

			// Queue-specific actions
			$(document).on('click', '.aips-queue-bulk-action-execute', this.executeQueueBulkAction.bind(this));
			$(document).on('click', '.aips-queue-select-all', this.toggleQueueSelectAll.bind(this));
		},

		switchMainTab: function (e) {
			e.preventDefault();
			const $tab = $(e.currentTarget);
			const tabId = $tab.data('tab');

			// Validate tabId to prevent XSS
			const allowedTabs = ['authors-list', 'generation-queue'];
			if (!allowedTabs.includes(tabId)) {
				return;
			}

			// Update active tab button
			$('.aips-authors-tab-link').removeClass('active');
			$tab.addClass('active');

			// Show/hide tab content
			$('.aips-authors-tab-content').hide();
			$('#' + tabId + '-tab').show();

			// Load queue data if switching to generation queue
			if (tabId === 'generation-queue') {
				this.loadQueueTopics();
			}
		},

		loadQueueTopics: function () {
			$('#aips-queue-topics-list').html('<p>' + (aipsAuthorsL10n.loadingQueue || 'Loading queue...') + '</p>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_generation_queue',
					nonce: aipsAuthorsL10n.nonce
				},
				success: (response) => {
					if (response.success && response.data.topics) {
						this.renderQueueTopics(response.data.topics);
					} else {
						$('#aips-queue-topics-list').html(
							'<p>' + (response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorLoadingQueue || 'Error loading queue.') + '</p>'
						);
					}
				},
				error: () => {
					$('#aips-queue-topics-list').html('<p>' + (aipsAuthorsL10n.errorLoadingQueue || 'Error loading queue.') + '</p>');
				}
			});
		},

		renderQueueTopics: function (topics) {
			if (!topics || topics.length === 0) {
				$('#aips-queue-topics-list').html('<p>' + (aipsAuthorsL10n.noQueueTopics || 'No approved topics in the queue yet.') + '</p>');
				return;
			}

			let html = '<table class="wp-list-table widefat fixed striped">';
			html += '<thead><tr>';
			html += '<th class="check-column"><input type="checkbox" class="aips-queue-select-all"></th>';
			html += '<th>' + (aipsAuthorsL10n.topicTitle || 'Topic Title') + '</th>';
			html += '<th>' + (aipsAuthorsL10n.author || 'Author') + '</th>';
			html += '<th>' + (aipsAuthorsL10n.fieldNiche || 'Field/Niche') + '</th>';
			html += '<th>' + (aipsAuthorsL10n.approvedDate || 'Approved Date') + '</th>';
			html += '</tr></thead><tbody>';

			topics.forEach(topic => {
				html += '<tr>';
				html += '<th class="check-column"><input type="checkbox" class="aips-queue-topic-checkbox" value="' + topic.id + '"></th>';
				html += '<td>' + AuthorsModule.escapeHtml(topic.topic_title) + '</td>';
				html += '<td>' + AuthorsModule.escapeHtml(topic.author_name) + '</td>';
				html += '<td>' + AuthorsModule.escapeHtml(topic.field_niche) + '</td>';
				html += '<td>' + (topic.reviewed_at || aipsAuthorsL10n.notAvailable || 'N/A') + '</td>';
				html += '</tr>';
			});

			html += '</tbody></table>';
			$('#aips-queue-topics-list').html(html);
		},

		toggleQueueSelectAll: function (e) {
			const isChecked = $(e.currentTarget).prop('checked');
			$('.aips-queue-topic-checkbox').prop('checked', isChecked);
		},

		executeQueueBulkAction: function (e) {
			e.preventDefault();

			const action = $('#aips-queue-bulk-action-select').val();

			if (!action) {
				showToast(aipsAuthorsL10n.selectBulkAction || 'Please select a bulk action.', 'warning');
				return;
			}

			// Get all checked topic IDs
			const topicIds = [];
			$('.aips-queue-topic-checkbox:checked').each(function () {
				topicIds.push($(this).val());
			});

			if (topicIds.length === 0) {
				showToast(aipsAuthorsL10n.noTopicsSelected || 'Please select at least one topic.', 'warning');
				return;
			}

			// Handle different actions
			switch (action) {
				case 'generate_now':
					this.generateNowFromQueue(topicIds);
					break;
				default:
					showToast(aipsAuthorsL10n.invalidAction || 'Invalid action.', 'error');
			}
		},

		generateNowFromQueue: function (topicIds) {
			const confirmMessage = (aipsAuthorsL10n.confirmGenerateFromQueue || 'Generate posts now for %d selected topic(s)?').replace('%d', topicIds.length);

			if (!confirm(confirmMessage)) {
				return;
			}

			const $button = $('.aips-queue-bulk-action-execute');
			$button.prop('disabled', true).text(aipsAuthorsL10n.generating || 'Generating...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_bulk_generate_from_queue',
					nonce: aipsAuthorsL10n.nonce,
					topic_ids: topicIds
				},
				success: (response) => {
					if (response.success) {
						showToast(response.data.message || aipsAuthorsL10n.postsGenerated || 'Posts generated successfully.', 'success');

						// Reload the queue
						this.loadQueueTopics();
					} else {
						showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorGenerating || 'Error generating posts.', 'error');
					}
				},
				error: () => {
					showToast(aipsAuthorsL10n.errorGenerating || 'Error generating posts.', 'error');
				},
				complete: () => {
					$button.prop('disabled', false).text(aipsAuthorsL10n.execute || 'Execute');
				}
			});
		}
	};

	// Initialize when document is ready
	$(document).ready(function () {
		AuthorsModule.init();
		GenerationQueueModule.init();
	});

})(jQuery);