/**
 * Authors Management JavaScript
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

(function ($) {
	'use strict';

	// Authors Module
	const AuthorsModule = {
		currentAuthorId: null,
		hasImportedSuggestedAuthor: false,

		/**
		 * Initialise the Authors module by binding all event listeners.
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Register all delegated and direct jQuery event listeners for the
		 * Authors admin page, including modal triggers, topic actions, and bulk
		 * operations.
		 */
		bindEvents: function () {
			// Add Author Button
			$('.aips-add-author-btn').on('click', this.openAddModal.bind(this));

			// Edit Author Button
			$(document).on('click', '.aips-edit-author', this.editAuthor.bind(this));

			// Generate Topics Now Button
			$(document).on('click', '.aips-generate-topics-now', this.generateTopicsNow.bind(this));

			// Delete Author Button
			$(document).on('click', '.aips-delete-author', this.deleteAuthor.bind(this));

			// Close Modal
			$('.aips-modal-close').on('click', this.closeModals.bind(this));

			// Submit Author Form
			$('#aips-author-form').on('submit', this.saveAuthor.bind(this));

			// Toggle Source Groups panel when Include Sources? checkbox changes.
			$(document).on('change', '#author_include_sources', this.toggleAuthorSourceGroups.bind(this));

			// Submit Feedback Form
			$('#aips-feedback-form').on('submit', this.submitFeedback.bind(this));

			// Tab switching in topics modal
			$(document).on('aips:tabSwitch', this.onTabSwitch.bind(this));

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
			$(document).on('click', '.topic-title-cell', this.onTopicTitleCellClick.bind(this));

			// Topic search (author-topics page)
			$(document).on('keyup search', '#aips-topic-search', this.filterTopics.bind(this));
			$(document).on('click', '#aips-topic-search-clear', this.clearTopicSearch.bind(this));

			// Authors list bulk actions
			$(document).on('change', '#aips-authors-select-all', this.toggleSelectAllAuthors.bind(this));
			$(document).on('click', '#aips-authors-bulk-apply', this.executeAuthorsBulkAction.bind(this));

			// Author Suggestions
			$(document).on('click', '#aips-suggest-authors-btn', this.openSuggestModal.bind(this));
			$(document).on('submit', '#aips-suggest-authors-form', this.suggestAuthors.bind(this));
			$(document).on('click', '.aips-import-suggested-author', this.importSuggestedAuthor.bind(this));
		},

		/**
		 * Toggle all author row checkboxes from the master checkbox.
		 *
		 * @param {Event} e - Change event from `#aips-authors-select-all`.
		 */
		toggleSelectAllAuthors: function (e) {
			const isChecked = $(e.currentTarget).prop('checked');
			$('.aips-author-checkbox').prop('checked', isChecked);
		},

		/**
		 * Execute bulk actions for selected authors.
		 *
		 * Supported actions: `generate_topics`, `delete`.
		 *
		 * @param {Event} e - Click event from `#aips-authors-bulk-apply`.
		 */
		executeAuthorsBulkAction: function (e) {
			e.preventDefault();

			const action = $('#aips-authors-bulk-action-select').val();
			const authorIds = $('.aips-author-checkbox:checked').map(function () {
				return parseInt($(this).val(), 10);
			}).get().filter(function (id) { return Number.isInteger(id) && id > 0; });

			if (!action) {
				AIPS.Utilities.showToast(aipsAuthorsL10n.selectBulkAction || 'Please select a bulk action.', 'warning');
				return;
			}

			if (authorIds.length === 0) {
				AIPS.Utilities.showToast(aipsAuthorsL10n.noAuthorsSelected || 'Please select at least one author.', 'warning');
				return;
			}

			if (action === 'generate_topics') {
				this.bulkGenerateTopics(authorIds);
				return;
			}

			if (action === 'delete') {
				this.bulkDeleteAuthors(authorIds);
				return;
			}

			AIPS.Utilities.showToast(aipsAuthorsL10n.invalidAction || 'Invalid action.', 'error');
		},

		/**
		 * Bulk-generate topics for selected authors.
		 *
		 * @param {Array<number>} authorIds - Selected author IDs.
		 */
		bulkGenerateTopics: function (authorIds) {
			const message = (aipsAuthorsL10n.confirmGenerateTopicsBulk || 'Generate topics now for %d selected author(s)?').replace('%d', authorIds.length);

			AIPS.Utilities.confirm(message, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, generate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const requests = authorIds.map((authorId) => {
							return $.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'aips_generate_topics_now',
									nonce: aipsAuthorsL10n.nonce,
									author_id: authorId
								}
							});
						});

						Promise.allSettled(requests).then((results) => {
							const successCount = results.filter((r) => r.status === 'fulfilled' && r.value && r.value.success).length;
							if (successCount > 0) {
								AIPS.Utilities.showToast((aipsAuthorsL10n.topicsGeneratedBulk || '%d author(s) queued for topic generation.').replace('%d', successCount), 'success');
								setTimeout(() => location.reload(), 800);
							} else {
								AIPS.Utilities.showToast(aipsAuthorsL10n.errorGenerating || 'Error generating topics.', 'error');
							}
						});
					}
				}
			]);
		},

		/**
		 * Bulk-delete selected authors.
		 *
		 * @param {Array<number>} authorIds - Selected author IDs.
		 */
		bulkDeleteAuthors: function (authorIds) {
			const message = (aipsAuthorsL10n.confirmDeleteBulk || 'Delete %d selected author(s)?').replace('%d', authorIds.length);

			AIPS.Utilities.confirm(message, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const requests = authorIds.map((authorId) => {
							return $.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'aips_delete_author',
									nonce: aipsAuthorsL10n.nonce,
									author_id: authorId
								}
							});
						});

						Promise.allSettled(requests).then((results) => {
							const successCount = results.filter((r) => r.status === 'fulfilled' && r.value && r.value.success).length;
							if (successCount > 0) {
								AIPS.Utilities.showToast((aipsAuthorsL10n.authorDeletedBulk || '%d author(s) deleted.').replace('%d', successCount), 'success');
								setTimeout(() => location.reload(), 800);
							} else {
								AIPS.Utilities.showToast(aipsAuthorsL10n.errorDeleting || 'Error deleting authors.', 'error');
							}
						});
					}
				}
			]);
		},

		/**
		 * Reset and open the author modal in "Add New" mode.
		 *
		 * Clears the form, empties the hidden `#author_id` field, sets the
		 * modal title to the localised "Add New Author" string, and fades the
		 * modal in.
		 *
		 * @param {Event} e - Click event from an `.aips-add-author-btn` element.
		 */
		openAddModal: function (e) {
			e.preventDefault();
			$('#aips-author-modal-title').text(aipsAuthorsL10n.addNewAuthor);
			$('#aips-author-form')[0].reset();
			$('#author_id').val('');
			// Reset source group fields.
			$('#author_include_sources').prop('checked', false);
			$('.aips-author-source-group-cb').prop('checked', false);
			$('#author-source-groups-selector').hide();
			$('#aips-author-modal').fadeIn();
		},

		/**
		 * Show or hide the Author Source Groups selector.
		 *
		 * @param {Event} e - Change event from `#author_include_sources`.
		 */
		toggleAuthorSourceGroups: function (e) {
			$('#author-source-groups-selector').toggle($(e.currentTarget).is(':checked'));
		},

		/**
		 * Load and display an author's data in the modal for editing.
		 *
		 * Reads the author ID from the clicked element's `data-id` attribute,
		 * shows a loading title, then sends the `aips_get_author` AJAX action.
		 * On success, populates every form field with the returned author data.
		 * Hides the modal and shows an error toast if the request fails.
		 *
		 * @param {Event} e - Click event from an `.aips-edit-author` element.
		 */
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
						$('#voice_tone').val(author.voice_tone || '');
						$('#writing_style').val(author.writing_style || '');
						// Extended profile fields
						$('#author_target_audience').val(author.target_audience || '');
						$('#author_expertise_level').val(author.expertise_level || '');
						$('#author_content_goals').val(author.content_goals || '');
						$('#author_excluded_topics').val(author.excluded_topics || '');
						$('#author_preferred_content_length').val(author.preferred_content_length || '');
						$('#author_language').val(author.language || 'en');
						$('#author_max_posts_per_topic').val(author.max_posts_per_topic || 1);
						$('#topic_generation_quantity').val(author.topic_generation_quantity);
						$('#topic_generation_frequency').val(author.topic_generation_frequency);
						$('#post_generation_frequency').val(author.post_generation_frequency);
						$('#is_active').prop('checked', author.is_active == 1);

						// Restore source group settings.
						var includeSources = author.include_sources == 1;
						$('#author_include_sources').prop('checked', includeSources);
						$('#author-source-groups-selector').toggle(includeSources);
						$('.aips-author-source-group-cb').prop('checked', false);
						var authorSgIds = [];
						try {
							authorSgIds = JSON.parse(author.source_group_ids || '[]');
						} catch (parseErr) {
							authorSgIds = [];
						}
						authorSgIds.forEach(function(tid) {
							$('.aips-author-source-group-cb[value="' + tid + '"]').prop('checked', true);
						});
					} else {
						AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorLoading, 'error');

						$('#aips-author-modal').fadeOut();
					}
				},
				error: () => {
					AIPS.Utilities.showToast(aipsAuthorsL10n.errorLoading, 'error');

					$('#aips-author-modal').fadeOut();
				}
			});
		},

		/**
		 * Serialize and save the author form via the `aips_save_author` AJAX action.
		 *
		 * Disables the submit button while the request is in flight.
		 * Shows a success toast and reloads the page after 1 second on success,
		 * or shows an error toast on failure.
		 *
		 * @param {Event} e - Submit event from `#aips-author-form`.
		 */
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
						AIPS.Utilities.showToast(response.data.message || aipsAuthorsL10n.authorSaved, 'success');

						setTimeout(() => location.reload(), 1000);
					} else {
						AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorSaving, 'error');
					}
				},
				error: () => {
					AIPS.Utilities.showToast(aipsAuthorsL10n.errorSaving, 'error');
				},
				complete: () => {
					$submitBtn.prop('disabled', false).text(aipsAuthorsL10n.saveAuthor);
				}
			});
		},

		/**
		 * Confirm and permanently delete an author via `aips_delete_author`.
		 *
		 * Shows a confirmation dialog. On confirmation, sends the AJAX delete
		 * request. Reloads the page after 1 second on success or shows an error
		 * toast on failure.
		 *
		 * @param {Event} e - Click event from an `.aips-delete-author` element.
		 */
		deleteAuthor: function (e) {
			e.preventDefault();
			const authorId = $(e.currentTarget).data('id');

			AIPS.Utilities.confirm(aipsAuthorsL10n.confirmDelete, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
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
									AIPS.Utilities.showToast(response.data.message || aipsAuthorsL10n.authorDeleted, 'success');
									setTimeout(() => location.reload(), 1000);
								} else {
									AIPS.Utilities.showToast(
										response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorDeleting,
										'error'
									);
								}
							},
							error: () => {
								AIPS.Utilities.showToast(aipsAuthorsL10n.errorDeleting, 'error');
							}
						});
					}
				}
			]);
		},

		/**
		 * Confirm and immediately trigger topic generation for an author.
		 *
		 * Reads the author ID from the clicked element's `data-id` attribute,
		 * shows a confirmation dialog, then sends `aips_generate_topics_now`.
		 * Reloads the page after 1 second on success.
		 *
		 * @param {Event} e - Click event from an `.aips-generate-topics-now` element.
		 */
		generateTopicsNow: function (e) {
			e.preventDefault();

			const authorId = $(e.currentTarget).data('id');
			const $btn = $(e.currentTarget);

			AIPS.Utilities.confirm(aipsAuthorsL10n.confirmGenerateTopics, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, generate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
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
									AIPS.Utilities.showToast(response.data.message || aipsAuthorsL10n.topicsGenerated, 'success');
									setTimeout(() => location.reload(), 1000);
								} else {
									AIPS.Utilities.showToast(
										response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorGenerating,
										'error'
									);
								}
							},
							error: () => {
								AIPS.Utilities.showToast(aipsAuthorsL10n.errorGenerating, 'error');
							},
							complete: () => {
								$btn.prop('disabled', false).text(aipsAuthorsL10n.generateTopicsNow);
							}
						});
					}
				}
			]);
		},

		/**
		 * Fetch topics for the current author filtered by status.
		 *
		 * Sends the `aips_get_author_topics` AJAX action. On success, calls
		 * `renderTopics` with the returned topics and `updateTopicCounts` with
		 * the per-status counts. Shows an error message inline on failure.
		 *
		 * @param {string} status - The topic status tab to load
		 *                          (`'pending'`, `'approved'`, or `'rejected'`).
		 */
		loadTopics: function (status) {
			// Immediately show a loading skeleton while the AJAX request is in flight.
			this.showTopicsLoading();

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
						if (status === 'pending') {
							this.renderInlineSimilarityIndicators();
						}
						// After rendering new content, hide the loading skeleton and
						// reveal the topics table for the requested tab.
						this.hideTopicsLoading();
					} else {
						$('#aips-topics-content').html('<p>' + (response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorLoadingTopics) + '</p>');
						this.hideTopicsLoading();
					}
				},
				error: () => {
					$('#aips-topics-content').html('<p>' + aipsAuthorsL10n.errorLoadingTopics + '</p>');
					this.hideTopicsLoading();
				}
			});
		},

		/**
		 * Show the topics loading section.
		 *
		 * Hides the current topics table body to indicate that the selected
		 * tab is being loaded.
		 */
		showTopicsLoading: function () {
			var $loading = $('#aips-topics-loading');
			var $content = $('#aips-topics-content');

			$content.hide();
			$loading.show();
			$('#aips-author-topics-panel').addClass('aips-topics-loading-active');
		},

		/**
		 * Hide the topics loading section and reveal the topics content area.
		 */
		hideTopicsLoading: function () {
			$('#aips-topics-loading').hide();
			$('#aips-topics-content').show();
			$('#aips-author-topics-panel').removeClass('aips-topics-loading-active');
		},

		/**
		 * Build and inject the topics HTML table into `#aips-topics-content`.
		 *
		 * Renders a WordPress-style `widefat` table with checkboxes, topic
		 * titles (with an inline edit input), generated-at dates, and
		 * context-sensitive action buttons (quick approve/reject, edit, generate
		 * post). Also renders collapsible detail rows for reviewed topics.
		 *
		 * @param {Array<Object>} topics - Array of topic data objects from the server.
		 * @param {string}        status - Active tab status
		 *                                 (`'pending'`, `'approved'`, or `'rejected'`).
		 */
		renderTopics: function (topics, status) {
			if (!topics || topics.length === 0) {
				$('#aips-topics-content').html('<p>' + aipsAuthorsL10n.noTopicsFound + '</p>');
				return;
			}

			let rowsHtml = '';

			topics.forEach(topic => {
				let detailContentHtml = '';
				if (topic.topic_description) {
					detailContentHtml += AIPS.Templates.render('aips-tmpl-topic-detail-item', {
						label: aipsAuthorsL10n.description || 'Description',
						value: topic.topic_description
					});
				}
				if (topic.topic_rationale) {
					detailContentHtml += AIPS.Templates.render('aips-tmpl-topic-detail-item', {
						label: aipsAuthorsL10n.rationale || 'Rationale',
						value: topic.topic_rationale
					});
				}
				if (topic.reviewed_at && topic.reviewed_by) {
					detailContentHtml += AIPS.Templates.render('aips-tmpl-topic-detail-item', {
						label: aipsAuthorsL10n.reviewed || 'Reviewed',
						value: String(topic.reviewed_at) + ' by User ID ' + String(topic.reviewed_by)
					});
				}
				if (topic.last_feedback) {
					const feedbackAction = topic.last_feedback.action;
					const feedbackLabel = feedbackAction === 'rejected'
						? (aipsAuthorsL10n.reject || 'Rejected')
						: (aipsAuthorsL10n.approve || 'Approved');

					let categoryBadgeHtml = '';
					if (topic.last_feedback.reason_category && topic.last_feedback.reason_category !== 'other') {
						categoryBadgeHtml = this.renderCategoryBadge(feedbackAction, topic.last_feedback.reason_category);
					}

					let reasonHtml = topic.last_feedback.reason ? ' &mdash; ' + this.escapeHtml(topic.last_feedback.reason) : '';
					let dateHtml = topic.last_feedback.created_at ? ' <span class="aips-feedback-date">' + this.escapeHtml(String(topic.last_feedback.created_at)) + '</span>' : '';

					detailContentHtml += AIPS.Templates.renderRaw('aips-tmpl-topic-detail-feedback', {
						label: AIPS.Templates.escape(aipsAuthorsL10n.lastFeedback || 'Last Feedback'),
						action: AIPS.Templates.escape(feedbackAction),
						actionLabel: AIPS.Templates.escape(feedbackLabel),
						categoryBadge: categoryBadgeHtml,
						reason: reasonHtml,
						date: dateHtml
					});
				}
				if (topic.potential_duplicate && topic.duplicate_match) {
					detailContentHtml += AIPS.Templates.render('aips-tmpl-topic-detail-duplicate', {
						label: aipsAuthorsL10n.potentialDuplicate || 'Potential Duplicate',
						match: topic.duplicate_match
					});
				}

				let expandBtnHtml = '';
				let detailSectionHtml = '';
				if (detailContentHtml !== '') {
					const viewDetailsTitle = this.escapeHtml(aipsAuthorsL10n.viewDetails || 'View Details');
					expandBtnHtml = '<button class="aips-topic-expand-btn" data-topic-id="' + topic.id + '" title="' + viewDetailsTitle + '" aria-label="' + viewDetailsTitle + '" aria-expanded="false" aria-controls="aips-topic-details-' + topic.id + '"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>';
					detailSectionHtml = AIPS.Templates.renderRaw('aips-tmpl-topic-detail-section', {
						id: topic.id,
						content: detailContentHtml
					});
				}

				let postCountBadgeHtml = '';
				if (topic.post_count && topic.post_count > 0) {
					const viewPostsTitle = this.escapeHtml(aipsAuthorsL10n.viewPosts || 'View Posts');
					postCountBadgeHtml = ' <span class="aips-post-count-badge" data-topic-id="' + topic.id + '" title="' + viewPostsTitle + '"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span> ' + topic.post_count + '</span>';
				}

				let duplicateBadgeHtml = '';
				if (topic.potential_duplicate) {
					const dupLabel = aipsAuthorsL10n.potentialDuplicate || 'Potential Duplicate';
					const safeDupLabel = this.escapeHtml(dupLabel);
					const dupTitle = topic.duplicate_match ? safeDupLabel + ': ' + this.escapeHtml(topic.duplicate_match) : safeDupLabel;
					duplicateBadgeHtml = ' <span class="aips-duplicate-badge" title="' + dupTitle + '"><span class="dashicons dashicons-warning"></span> ' + safeDupLabel + '</span>';
				}

				let feedbackBadgeHtml = '';
				if (topic.last_feedback) {
					const fbAction = topic.last_feedback.action;
					const fbLabel = fbAction === 'rejected' ? (aipsAuthorsL10n.previouslyRejected || 'Previously Rejected') : (aipsAuthorsL10n.previouslyApproved || 'Previously Approved');
					const fbTitle = topic.last_feedback.reason ? this.escapeHtml(fbLabel) + ': ' + this.escapeHtml(topic.last_feedback.reason) : this.escapeHtml(fbLabel);
					feedbackBadgeHtml = ' <span class="aips-feedback-badge aips-feedback-badge-' + fbAction + '" title="' + fbTitle + '"><span class="dashicons dashicons-admin-comments"></span> ' + this.escapeHtml(fbLabel) + '</span>';
					if (topic.last_feedback.reason_category) {
						feedbackBadgeHtml += ' ' + this.renderCategoryBadge(fbAction, topic.last_feedback.reason_category);
					}
				}

				let actionsHtml = '';
				if (status === 'pending') {
					actionsHtml = AIPS.Templates.renderRaw('aips-tmpl-topic-actions-pending', {
						id: topic.id,
						editLabel: AIPS.Templates.escape(aipsAuthorsL10n.edit || 'Edit'),
						approveLabel: AIPS.Templates.escape(aipsAuthorsL10n.approveWithFeedback || 'Approve with Feedback'),
						rejectLabel: AIPS.Templates.escape(aipsAuthorsL10n.rejectWithFeedback || 'Reject with Feedback')
					});
				} else if (status === 'approved' || status === 'posts_generated') {
					actionsHtml = AIPS.Templates.renderRaw('aips-tmpl-topic-actions-approved', {
						id: topic.id,
						generateLabel: AIPS.Templates.escape(aipsAuthorsL10n.generatePostNow || 'Generate Post Now'),
						editLabel: AIPS.Templates.escape(aipsAuthorsL10n.edit || 'Edit')
					});
				} else {
					actionsHtml = AIPS.Templates.renderRaw('aips-tmpl-topic-actions-rejected', {
						id: topic.id,
						editLabel: AIPS.Templates.escape(aipsAuthorsL10n.edit || 'Edit')
					});
				}

				var rawGeneratedAt = topic.generated_at || '';
				var formattedGeneratedAt = this.formatTopicDate(rawGeneratedAt) || rawGeneratedAt;

				rowsHtml += AIPS.Templates.renderRaw('aips-tmpl-topic-row', {
					id: topic.id,
					topicTitle: AIPS.Templates.escape(topic.topic_title),
					expandBtn: expandBtnHtml,
					postCountBadge: postCountBadgeHtml,
					duplicateBadge: duplicateBadgeHtml,
					feedbackBadge: feedbackBadgeHtml,
					detailContent: detailSectionHtml,
					generatedAt: AIPS.Templates.escape(formattedGeneratedAt),
					actions: actionsHtml
				});
			});

			const tableHtml = AIPS.Templates.renderRaw('aips-tmpl-topics-table', {
				topicDetails: AIPS.Templates.escape(aipsAuthorsL10n.topicDetails || 'Topic Details'),
				generatedAtLabel: AIPS.Templates.escape(aipsAuthorsL10n.generatedAt),
				actionsLabel: AIPS.Templates.escape(aipsAuthorsL10n.actions),
				rows: rowsHtml
			});

			$('#aips-topics-content').html(tableHtml);

			// Update the filter bar result count
			var total = topics.length;
			var countStr = total === 1
				? total + ' ' + (aipsAuthorsL10n.topicCountSingular || 'topic')
				: total + ' ' + (aipsAuthorsL10n.topicCountPlural || 'topics');
			$('#aips-topics-result-count').text(countStr);
		},

		/**
		 * Fetch semantic similarity suggestions and render them inline in topic rows.
		 *
		 * Calls the `aips_suggest_related_topics` AJAX action, then appends a
		 * color-coded percentage badge to each matching pending topic row.
		 */
		renderInlineSimilarityIndicators: function () {
			if (!this.currentAuthorId) {
				return;
			}

			$('.aips-topic-similarity-slot').empty();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_suggest_related_topics',
					nonce: aipsAuthorsL10n.nonce,
					author_id: this.currentAuthorId,
					limit: 5
				},
				success: (response) => {
					if (!response.success || !response.data || !Array.isArray(response.data.suggestions) || response.data.suggestions.length === 0) {
						return;
					}

					response.data.suggestions.forEach((item) => {
						const topicId = parseInt(item.topic_id, 10);
						const rawScore = typeof item.similarity_score === 'number' ? item.similarity_score : parseFloat(item.similarity_score);
						const score = Number.isFinite(rawScore) ? Math.round(rawScore * 100) : 0;

						if (!topicId || score <= 0) {
							return;
						}

						const badgeClass = this.getSimilarityBadgeClass(score);
						const label = this.escapeHtml((aipsAuthorsL10n.similarityLabel || 'Similarity') + ': ' + score + '%');
						const $slot = $('.aips-topic-similarity-slot[data-topic-id="' + topicId + '"]');

						if ($slot.length) {
							$slot.html('<span class="aips-topic-similarity-badge ' + badgeClass + '" title="' + label + '">' + label + '</span>');
						}
					});
				},
				error: () => {}
			});
		},

		/**
		 * Return the CSS class for a similarity percentage.
		 *
		 * @param {number} scorePercent - Similarity score from 0 to 100.
		 * @returns {string} CSS class name.
		 */
		getSimilarityBadgeClass: function (scorePercent) {
			if (scorePercent > 75) {
				return 'aips-topic-similarity-high';
			}

			if (scorePercent > 50) {
				return 'aips-topic-similarity-medium';
			}

			return 'aips-topic-similarity-low';
		},

		/**
		 * Convert a label to Title Case while preserving separators like
		 * slashes and hyphens.
		 *
		 * @param {string} text - Input label text.
		 * @returns {string} Title-cased label.
		 */
		toTitleCase: function (text) {
			if (!text || typeof text !== 'string') {
				return text;
			}
			return text.toLowerCase().replace(/\b\w/g, function (match) {
				return match.toUpperCase();
			});
		},

		/**
		 * Return the human-readable label for a feedback reason category.
		 *
		 * Looks up the category value in `aipsAuthorsL10n.approvalCategories` or
		 * `aipsAuthorsL10n.rejectionCategories` depending on the feedback action.
		 * Falls back to the raw category slug when no match is found or the action
		 * is not one of the known values (`'approved'` / `'rejected'`).
		 *
		 * @param {string} action   - `'approved'` or `'rejected'`.
		 * @param {string} category - The `reason_category` value.
		 * @returns {string} Translated/human-readable label.
		 */
		getCategoryLabel: function (action, category) {
			var list;
			if (action === 'approved') {
				list = aipsAuthorsL10n.approvalCategories || [];
			} else if (action === 'rejected') {
				list = aipsAuthorsL10n.rejectionCategories || [];
			} else {
				// Unknown action — return the raw slug so the badge still renders meaningfully.
				return category;
			}
			const match = list.find(function (c) { return c.value === category; });
			return match ? match.label : category;
		},

		/**
		 * Build the HTML for a reason category chip/badge.
		 *
		 * Returns an empty string when category is falsy or `'other'`.
		 *
		 * @param {string} action   - `'approved'` or `'rejected'`.
		 * @param {string} category - The `reason_category` value.
		 * @returns {string} Badge HTML string.
		 */
		renderCategoryBadge: function (action, category) {
			if (!category || category === 'other') {
				return '';
			}

			const rawLabel = this.getCategoryLabel(action, category);
			const label = this.toTitleCase(rawLabel);
			const isPositive = action === 'approved';
			const groupClass = isPositive ? 'aips-reason-category-badge-positive' : 'aips-reason-category-badge-negative';
			const specificClass = 'aips-reason-category-badge-' + category.replace(/_/g, '-');
			let iconClass = '';
			switch (category) {
				case 'timely':
					iconClass = 'dashicons-clock';
					break;
				case 'relevant':
					iconClass = 'dashicons-location-alt';
					break;
				case 'well_researched':
					iconClass = 'dashicons-search';
					break;
				case 'engaging':
					iconClass = 'dashicons-megaphone';
					break;
				case 'original':
					iconClass = 'dashicons-lightbulb';
					break;
				case 'duplicate':
					iconClass = 'dashicons-admin-page';
					break;
				case 'tone':
					iconClass = 'dashicons-admin-customizer';
					break;
				case 'irrelevant':
					iconClass = 'dashicons-dismiss';
					break;
				case 'policy':
					iconClass = 'dashicons-warning';
					break;
				default:
					iconClass = '';
			}

			const iconHtml = iconClass ? '<span class="dashicons ' + iconClass + '"></span>' : '';

			return '<span class="aips-reason-category-badge ' + groupClass + ' ' + specificClass + '" title="' + this.escapeHtml(label) + '">' +
				iconHtml + this.escapeHtml(label) +
			'</span>';
		},

		/**
		 * Format a topic generated_at timestamp into a friendly string.
		 *
		 * - Today:     "Today, 2:32pm"
		 * - Yesterday: "Yesterday, 2:32pm"
		 * - Otherwise: "March 26, 2026 2:32pm"
		 *
		 * Month names, "Today", "Yesterday", and am/pm labels are pulled from
		 * the server-side aipsAuthorsL10n object so non-English sites are
		 * supported without hard-coding English strings.
		 *
		 * @param {string} raw - Datetime string (YYYY-MM-DD HH:MM:SS).
		 * @returns {string} Formatted date string.
		 */
		formatTopicDate: function (raw) {
			if (!raw || typeof raw !== 'string') {
				return raw;
			}

			// Parse "YYYY-MM-DD HH:MM:SS" into a local Date.
			var parts = raw.split(' ');
			if (parts.length < 2) {
				return raw;
			}
			var dateParts = parts[0].split('-');
			var timeParts = parts[1].split(':');
			if (dateParts.length < 3 || timeParts.length < 2) {
				return raw;
			}

			var year = parseInt(dateParts[0], 10);
			var monthIndex = parseInt(dateParts[1], 10) - 1; // 0-based
			var day = parseInt(dateParts[2], 10);
			var hour = parseInt(timeParts[0], 10);
			var minute = parseInt(timeParts[1], 10);

			if (isNaN(year) || isNaN(monthIndex) || isNaN(day) || isNaN(hour) || isNaN(minute)) {
				return raw;
			}

			var d = new Date(year, monthIndex, day, hour, minute, 0);
			var now = new Date();
			var isToday = d.getFullYear() === now.getFullYear() &&
				d.getMonth() === now.getMonth() &&
				d.getDate() === now.getDate();

			var yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
			var isYesterday = d.getFullYear() === yesterday.getFullYear() &&
				d.getMonth() === yesterday.getMonth() &&
				d.getDate() === yesterday.getDate();

			// Use localized strings from aipsAuthorsL10n when available.
			var l10n        = (typeof aipsAuthorsL10n !== 'undefined') ? aipsAuthorsL10n : {};
			var labelToday  = l10n.dateToday     || 'Today';
			var labelYday   = l10n.dateYesterday || 'Yesterday';
			var labelAM     = l10n.dateAM        || 'am';
			var labelPM     = l10n.datePM        || 'pm';
			var monthNames  = (l10n.dateMonthNames && l10n.dateMonthNames.length === 12)
				? l10n.dateMonthNames
				: [
					'January', 'February', 'March', 'April', 'May', 'June',
					'July', 'August', 'September', 'October', 'November', 'December'
				];

			var hours12    = d.getHours() % 12 || 12;
			var minutesStr = minute < 10 ? '0' + minute : String(minute);
			var ampm       = d.getHours() >= 12 ? labelPM : labelAM;
			var timeStr    = hours12 + ':' + minutesStr + ampm;

			if (isToday) {
				return labelToday + ', ' + timeStr;
			}

			if (isYesterday) {
				return labelYday + ', ' + timeStr;
			}

			var monthName = monthNames[monthIndex] || (monthIndex + 1);
			return monthName + ' ' + day + ', ' + year + ' ' + timeStr;
		},

		/**
		 * Update the per-status topic count badges in the tab bar and the Stats Cards.
		 *
		 * @param {Object} counts                    - Map of status string → count number.
		 * @param {number} [counts.pending]          - Number of pending topics.
		 * @param {number} [counts.approved]         - Number of approved topics without generated posts.
		 * @param {number} [counts.rejected]         - Number of rejected topics.
		 * @param {number} [counts.posts_generated]  - Number of approved topics that have produced a post.
		 */
		updateTopicCounts: function (counts) {
			const pending        = counts.pending         || 0;
			const approved       = counts.approved        || 0;
			const rejected       = counts.rejected        || 0;
			const postsGenerated = counts.posts_generated || 0;
			const total          = pending + approved + rejected + postsGenerated;

			// Tab count badges
			$('#pending-count').text(pending);
			$('#approved-count').text(approved);
			$('#rejected-count').text(rejected);
			$('#posts-generated-count').text(postsGenerated);

			// Stats Cards
			$('#stat-total-count').text(total);
			$('#stat-pending-count').text(pending);
			$('#stat-approved-count').text(approved);
			$('#stat-rejected-count').text(rejected);
		},

		/**
		 * Handles the custom aips:tabSwitch event fired by admin.js after a .aips-tab-link click.
		 *
		 * @param {jQuery.Event} e      - The jQuery event object.
		 * @param {string}       status - The tab ID (data-tab value) of the newly active tab.
		 */
		onTabSwitch: function (e, status) {
			// Only handle authors-page-specific behaviour
			if (!$('#aips-topics-content').length) {
				return;
			}

			// Reset topic search when switching tabs
			$('#aips-topic-search').val('');
			$('#aips-topic-search-clear').hide();
			
			// Immediately switch to the loading skeleton, then fetch the
			// appropriate content for the selected tab.
			if (status === 'feedback') {
				this.showTopicsLoading();
				this.loadFeedback();
			} else {
				this.loadTopics(status);
			}

			// Update bulk action dropdown options based on tab
			this.updateBulkActionDropdown(status);
		},

		/**
		 * Filter the rendered topics table in real time by the typed search term.
		 *
		 * Matches against the `.topic-title` span content of each row in the
		 * `.aips-topics-table`. Shows a clear button when a term is active.
		 */
		filterTopics: function() {
			var term = $('#aips-topic-search').val().toLowerCase().trim();
			var $rows = $('.aips-topics-table tbody tr');
			var $clearBtn = $('#aips-topic-search-clear');

			if (term.length > 0) {
				$clearBtn.show();
			} else {
				$clearBtn.hide();
			}

			$rows.each(function() {
				var $row = $(this);
				var title = $row.find('.topic-title').text().toLowerCase();
				if (title.indexOf(term) > -1) {
					$row.show();
				} else {
					$row.hide();
				}
			});
		},

		/**
		 * Clear the topic search input and re-run the filter to show all rows.
		 *
		 * @param {Event} e - Click event from `#aips-topic-search-clear`.
		 */
		clearTopicSearch: function(e) {
			e.preventDefault();
			$('#aips-topic-search').val('').trigger('keyup');
		},

		/**
		 * Repopulate the bulk-action dropdowns with options appropriate for the
		 * given tab status.
		 *
		 * Clears all non-default options first, then adds the relevant actions:
		 * pending → Approve / Reject / Delete; approved → Generate Now / Delete;
		 * rejected → Delete; feedback → Delete.
		 *
		 * @param {string} status - The active tab (`'pending'`, `'approved'`,
		 *                          `'rejected'`, or `'feedback'`).
		 */
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
				} else if (status === 'approved' || status === 'posts_generated') {
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

		/**
		 * Handle clicks anywhere in the Topic Details column.
		 *
		 * Expands/collapses the topic details in the same way as clicking the
		 * small arrow button, but ignores clicks on interactive controls inside
		 * the cell (buttons, links, inputs, etc.).
		 *
		 * @param {Event} e - Click event from `.topic-title-cell`.
		 */
		onTopicTitleCellClick: function (e) {
			// Do not toggle when clicking on interactive elements inside the cell.
			if (
				$(e.target).closest('button, a, input, textarea, select, label').length ||
				$(e.target).closest('.aips-topic-expand-btn').length
			) {
				return;
			}

			const $row = $(e.currentTarget).closest('tr[data-topic-id]');
			const topicId = $row.data('topic-id');

			if (!topicId) {
				return;
			}

			const $button = $('.aips-topic-expand-btn[data-topic-id="' + topicId + '"]');

			if ($button.length) {
				$button.trigger('click');
			}
		},

		/**
		 * Toggle the collapsible detail block inside a topic row.
		 *
		 * Slides the inline `.aips-topic-detail-content` up or down and updates
		 * the expand button's `aria-expanded` attribute and dashicon accordingly.
		 *
		 * @param {Event} e - Click event from an `.aips-topic-expand-btn` element.
		 */
		toggleTopicDetail: function (e) {
			e.preventDefault();
			const $button = $(e.currentTarget);
			const topicId = $button.data('topic-id');
			const $detailRow = $('#aips-topic-details-' + topicId);

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

		/**
		 * Populate the feedback category dropdown with options appropriate for the given action.
		 *
		 * Replaces all `<option>` elements in `#feedback_reason_category` with the
		 * action-specific set supplied via `aipsAuthorsL10n.approvalCategories` or
		 * `aipsAuthorsL10n.rejectionCategories`, then resets the selection to the
		 * first option ("other").  Also updates the visible label and description
		 * text next to the dropdown.
		 *
		 * @param {string} action - Either `'approve'` or `'reject'`.
		 */
		populateCategoryOptions: function (action) {
			const isApprove = action === 'approve';
			const categories = isApprove
				? (aipsAuthorsL10n.approvalCategories || [])
				: (aipsAuthorsL10n.rejectionCategories || []);

			const $select = $('#feedback_reason_category');
			$select.empty();
			$.each(categories, function (i, cat) {
				$select.append($('<option>').val(cat.value).text(cat.label));
			});

			// Update the label and helper description to match the action.
			$('#feedback_reason_category_label').text(
				isApprove
					? (aipsAuthorsL10n.approvalCategoryLabel || 'Approval Reason')
					: (aipsAuthorsL10n.rejectionCategoryLabel || 'Rejection Reason')
			);
			$('#feedback_reason_category_description').text(
				isApprove
					? (aipsAuthorsL10n.approvalCategoryDescription || 'Select a positive reason to help train future topic generation.')
					: (aipsAuthorsL10n.rejectionCategoryDescription || 'Select a structured reason to improve future topic quality.')
			);
		},

		/**
		 * Open the feedback modal pre-configured for approving a topic.
		 *
		 * Sets the hidden `#feedback_topic_id` and `#feedback_action` fields,
		 * updates the modal title, category dropdown, and input placeholder, and
		 * fades the feedback modal in.
		 *
		 * @param {Event} e - Click event from an `.aips-approve-topic` element.
		 */
		approveTopic: function (e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('id');

			// Open feedback modal
			$('#feedback_topic_id').val(topicId);
			$('#feedback_action').val('approve');
			$('#aips-feedback-modal-title').text(aipsAuthorsL10n.approveTopicTitle || 'Approve Topic');
			$('#feedback_reason').attr('placeholder', aipsAuthorsL10n.approveReasonPlaceholder || 'Why are you approving this topic?');
			$('#feedback-submit-btn').text(aipsAuthorsL10n.approve);
			this.populateCategoryOptions('approve');
			$('#aips-feedback-modal').fadeIn();
		},

		/**
		 * Open the feedback modal pre-configured for rejecting a topic.
		 *
		 * Sets the hidden `#feedback_topic_id` and `#feedback_action` fields,
		 * updates the modal title, category dropdown, and input placeholder, and
		 * fades the feedback modal in.
		 *
		 * @param {Event} e - Click event from an `.aips-reject-topic` element.
		 */
		rejectTopic: function (e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('id');

			// Open feedback modal
			$('#feedback_topic_id').val(topicId);
			$('#feedback_action').val('reject');
			$('#aips-feedback-modal-title').text(aipsAuthorsL10n.rejectTopicTitle || 'Reject Topic');
			$('#feedback_reason').attr('placeholder', aipsAuthorsL10n.rejectReasonPlaceholder || 'Why are you rejecting this topic?');
			$('#feedback-submit-btn').text(aipsAuthorsL10n.reject);
			this.populateCategoryOptions('reject');
			$('#aips-feedback-modal').fadeIn();
		},

		/**
		 * Submit the topic feedback form (approve or reject with a reason).
		 *
		 * Reads the topic ID, action, and reason from the feedback modal form.
		 * Sends either `aips_approve_topic` or `aips_reject_topic` depending on
		 * the `#feedback_action` value. Closes and resets the modal on success
		 * and reloads the pending topic list.
		 *
		 * @param {Event} e - Submit event from `#aips-feedback-form`.
		 */
		submitFeedback: function (e) {
			e.preventDefault();

			const topicId = $('#feedback_topic_id').val();
			const action = $('#feedback_action').val();
			const reason = $('#feedback_reason').val();
			const reasonCategory = $('#feedback_reason_category').val() || 'other';

			const ajaxAction = action === 'approve' ? 'aips_approve_topic' : 'aips_reject_topic';

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: ajaxAction,
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId,
					reason: reason,
					reason_category: reasonCategory,
					source: 'manual_ui'
				},
				success: (response) => {
					if (response.success) {
						$('#aips-feedback-modal').fadeOut();
						$('#aips-feedback-form')[0].reset();

						this.loadTopics('pending');
					} else {
						AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorSaving, 'error');
					}
				},
				error: () => {
					AIPS.Utilities.showToast(action === 'approve' ? aipsAuthorsL10n.errorApproving : aipsAuthorsL10n.errorRejecting, 'error');
				}
			});
		},

		/**
		 * Fetch and display all feedback entries for the current author.
		 *
		 * Shows a loading message, then sends `aips_get_author_feedback`. On
		 * success, passes the feedback array to `renderFeedback`. Shows an
		 * inline message if no author is selected or the request fails.
		 */
		loadFeedback: function () {
			if (!this.currentAuthorId) {
				$('#aips-topics-content').html('<p>No author selected.</p>');
				this.hideTopicsLoading();
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
					this.hideTopicsLoading();
				},
				error: () => {
					$('#aips-topics-content').html('<p>Error loading feedback.</p>');
					this.hideTopicsLoading();
				}
			});
		},

		/**
		 * Build and inject the feedback HTML table into `#aips-topics-content`.
		 *
		 * Renders a WordPress-style table showing topic title, approve/reject
		 * action badge, reason, user name, and date for each feedback item.
		 * Shows a "no feedback yet" message when the array is empty.
		 *
		 * @param {Array<Object>} feedback - Array of feedback objects from the server.
		 */
		renderFeedback: function (feedback) {
			if (feedback.length === 0) {
				$('#aips-topics-content').html('<p>' + (aipsAuthorsL10n.noFeedbackYet || 'No feedback yet.') + '</p>');
				return;
			}

			let rowsHtml = '';

			feedback.forEach(item => {
				rowsHtml += AIPS.Templates.renderRaw('aips-tmpl-feedback-row', {
					id: item.id,
					topicTitle: AIPS.Templates.escape(item.topic_title || 'N/A'),
					action: AIPS.Templates.escape(item.action),
					reason: AIPS.Templates.escape(item.reason || '-'),
					userName: AIPS.Templates.escape(item.user_name || 'Unknown'),
					date: AIPS.Templates.escape(item.created_at)
				});
			});

			const tableHtml = AIPS.Templates.renderRaw('aips-tmpl-feedback-table', {
				topicLabel: AIPS.Templates.escape(aipsAuthorsL10n.topic),
				actionLabel: AIPS.Templates.escape(aipsAuthorsL10n.action),
				reasonLabel: AIPS.Templates.escape(aipsAuthorsL10n.reason),
				userLabel: AIPS.Templates.escape(aipsAuthorsL10n.user),
				dateLabel: AIPS.Templates.escape(aipsAuthorsL10n.date),
				rows: rowsHtml
			});

			$('#aips-topics-content').html(tableHtml);
		},

		/**
		 * Confirm and permanently delete a single topic via `aips_delete_topic`.
		 *
		 * Shows a confirmation dialog. On confirmation, sends the AJAX request
		 * and reloads the currently active tab's topics on success, or shows an
		 * error toast on failure.
		 *
		 * @param {Event} e - Click event from an `.aips-delete-topic` element.
		 */
		deleteTopic: function (e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('id');

			AIPS.Utilities.confirm(aipsAuthorsL10n.confirmDeleteTopic, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
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
									AIPS.Utilities.showToast(
										response.data && response.data.message
											? response.data.message
											: aipsAuthorsL10n.errorDeletingTopic,
										'error'
									);
								}
							},
							error: () => {
								AIPS.Utilities.showToast(aipsAuthorsL10n.errorDeletingTopic, 'error');
							}
						});
					}
				}
			]);
		},

		/**
		 * Switch a topic row into inline-edit mode.
		 *
		 * Hides the `.topic-title` span and shows the `.topic-title-edit` text
		 * input in the same cell. Hides the original edit button and appends Save
		 * and Cancel buttons to the `.topic-actions` cell.
		 *
		 * @param {Event} e - Click event from an `.aips-edit-topic` element.
		 */
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

		/**
		 * Persist an inline topic title edit via `aips_edit_topic`.
		 *
		 * Validates that the new title is non-empty, then sends the AJAX request.
		 * On success, updates the `.topic-title` span text and restores the row
		 * to its normal (non-editing) state. Shows an error toast on failure.
		 *
		 * @param {Event} e - Click event from an `.aips-save-topic` element.
		 */
		saveTopic: function (e) {
			e.preventDefault();
			const $row = $(e.currentTarget).closest('tr');
			const topicId = $row.data('topic-id');
			const newTitle = $row.find('.topic-title-edit').val();

			if (!newTitle.trim()) {
				AIPS.Utilities.showToast(aipsAuthorsL10n.topicTitleRequired, 'warning');
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
						AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorSavingTopic);
					}
				},
				error: () => {
					AIPS.Utilities.showToast(aipsAuthorsL10n.errorSavingTopic, 'error');
				}
			});
		},

		/**
		 * Discard an in-progress inline topic title edit and restore the row.
		 *
		 * Re-shows the `.topic-title` span, hides the text input, restores the
		 * edit button, and removes the transient Save / Cancel buttons.
		 *
		 * @param {Event} e - Click event from an `.aips-cancel-edit-topic` element.
		 */
		cancelEditTopic: function (e) {
			e.preventDefault();
			const $row = $(e.currentTarget).closest('tr');
			$row.find('.topic-title').show();
			$row.find('.topic-title-edit').hide();
			$row.find('.aips-edit-topic').show();
			$row.find('.aips-save-topic, .aips-cancel-edit-topic').remove();
		},

		/**
		 * Confirm and immediately generate a post from an approved topic.
		 *
		 * Shows a confirmation dialog, then sends `aips_generate_post_from_topic`.
		 * On success, shows a success toast and reloads the currently active tab.
		 * Re-enables the button on failure.
		 *
		 * @param {Event} e - Click event from an `.aips-generate-post-now` element.
		 */
		generatePostNow: function (e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('id');
			const $btn = $(e.currentTarget);

			AIPS.Utilities.confirm(aipsAuthorsL10n.confirmGeneratePost, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, generate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
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
									AIPS.Utilities.showToast(aipsAuthorsL10n.postGenerated, 'success');
									const activeTab = $('.aips-tab-link.active').data('tab');
									this.loadTopics(activeTab);
								} else {
									AIPS.Utilities.showToast(
										response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorGeneratingPost,
										'error'
									);
									$btn.prop('disabled', false).text(aipsAuthorsL10n.generatePostNow);
								}
							},
							error: () => {
								AIPS.Utilities.showToast(aipsAuthorsL10n.errorGeneratingPost, 'error');
								$btn.prop('disabled', false).text(aipsAuthorsL10n.generatePostNow);
							}
						});
					}
				}
			]);
		},

		/**
		 * Open the topic-log modal and start loading logs for the given topic.
		 *
		 * Sets a loading message in `#aips-topic-logs-content`, fades the logs
		 * modal in, and delegates to `loadTopicLogs`.
		 *
		 * @param {Event} e - Click event from an `.aips-view-topic-log` element.
		 */
		viewTopicLog: function (e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('id');

			$('#aips-topic-logs-content').html('<p>' + (aipsAuthorsL10n.logViewerLoading || 'Loading logs...') + '</p>');
			$('#aips-topic-logs-modal').fadeIn();

			this.loadTopicLogs(topicId);
		},

		/**
		 * Fetch the action log for a topic via `aips_get_topic_logs`.
		 *
		 * On success, passes the returned logs array to `renderTopicLogs`.
		 * Shows an inline error message if the request fails.
		 *
		 * @param {number} topicId - The topic ID whose logs to load.
		 */
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

		/**
		 * Build and inject the topic-log HTML table into `#aips-topic-logs-content`.
		 *
		 * Renders a WordPress-style table with action badge, user name, date,
		 * and notes columns. Shows a "no logs found" message when the array is
		 * empty.
		 *
		 * @param {Array<Object>} logs - Array of log entry objects from the server.
		 */
		renderTopicLogs: function (logs) {
			if (!logs || logs.length === 0) {
				$('#aips-topic-logs-content').html('<p>' + aipsAuthorsL10n.noLogsFound + '</p>');
				return;
			}

			let rowsHtml = '';

			logs.forEach(log => {
				rowsHtml += AIPS.Templates.renderRaw('aips-tmpl-topic-log-row', {
					action: AIPS.Templates.escape(log.action),
					userName: AIPS.Templates.escape(log.user_name || 'System'),
					date: AIPS.Templates.escape(log.created_at),
					notes: AIPS.Templates.escape(log.notes || '-')
				});
			});

			const tableHtml = AIPS.Templates.renderRaw('aips-tmpl-topic-logs-table', {
				actionLabel: AIPS.Templates.escape(aipsAuthorsL10n.logAction),
				userLabel: AIPS.Templates.escape(aipsAuthorsL10n.logUser),
				dateLabel: AIPS.Templates.escape(aipsAuthorsL10n.logDate),
				detailsLabel: AIPS.Templates.escape(aipsAuthorsL10n.logDetails),
				rows: rowsHtml
			});

			$('#aips-topic-logs-content').html(tableHtml);
		},
		
		/**
		 * Open the topic-posts modal and start loading posts for the given topic.
		 *
		 * Reads the topic ID from the clicked element's `data-topic-id` attribute,
		 * sets a loading message, fades the posts modal in, and delegates to
		 * `loadTopicPosts`.
		 *
		 * @param {Event} e - Click event from an `.aips-post-count-badge` element.
		 */
		viewTopicPosts: function (e) {
			e.preventDefault();
			e.stopPropagation();
			
			const topicId = $(e.currentTarget).data('topic-id');
			
			$('#aips-topic-posts-content').html('<p>' + aipsAuthorsL10n.loadingPosts + '</p>');
			$('#aips-topic-posts-modal').fadeIn();
			
			this.loadTopicPosts(topicId);
		},
		
		/**
		 * Fetch posts generated from a topic via `aips_get_topic_posts`.
		 *
		 * Updates the modal title with the topic title, then delegates to
		 * `renderTopicPosts`. Shows an inline error on failure.
		 *
		 * @param {number} topicId - The topic ID whose posts to load.
		 */
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
		
		/**
		 * Build and inject the topic-posts HTML table into `#aips-topic-posts-content`.
		 *
		 * Renders a WordPress-style table with post ID, title, generated date,
		 * published date, and action links (Edit Post / View Post). Shows a
		 * "no posts found" message when the array is empty.
		 *
		 * @param {Array<Object>} posts - Array of post objects from the server.
		 */
		renderTopicPosts: function (posts) {
			if (!posts || posts.length === 0) {
				$('#aips-topic-posts-content').html('<p>' + aipsAuthorsL10n.noPostsFound + '</p>');
				return;
			}

			let rowsHtml = '';

			posts.forEach(post => {
				let actionsHtml = '';
				if (post.edit_url) {
					actionsHtml += '<a href="' + this.sanitizeUrl(post.edit_url) + '" class="button" target="_blank">' + AIPS.Templates.escape(aipsAuthorsL10n.editPost) + '</a> ';
				}
				if (post.post_url && post.post_status === 'publish') {
					actionsHtml += '<a href="' + this.sanitizeUrl(post.post_url) + '" class="button" target="_blank">' + AIPS.Templates.escape(aipsAuthorsL10n.viewPost) + '</a>';
				}

				rowsHtml += AIPS.Templates.renderRaw('aips-tmpl-topic-post-row', {
					postId: AIPS.Templates.escape(post.post_id),
					postTitle: AIPS.Templates.escape(post.post_title),
					dateGenerated: AIPS.Templates.escape(post.date_generated || ''),
					datePublished: AIPS.Templates.escape(post.date_published || aipsAuthorsL10n.notPublished),
					actions: actionsHtml
				});
			});

			const tableHtml = AIPS.Templates.renderRaw('aips-tmpl-topic-posts-table', {
				idLabel: AIPS.Templates.escape(aipsAuthorsL10n.postId),
				titleLabel: AIPS.Templates.escape(aipsAuthorsL10n.postTitle),
				generatedLabel: AIPS.Templates.escape(aipsAuthorsL10n.dateGenerated),
				publishedLabel: AIPS.Templates.escape(aipsAuthorsL10n.datePublished),
				actionsLabel: AIPS.Templates.escape(aipsAuthorsL10n.actions),
				rows: rowsHtml
			});

			$('#aips-topic-posts-content').html(tableHtml);
		},

		/**
		 * Sync all `.aips-topic-checkbox` elements with the "select all" checkbox.
		 *
		 * @param {Event} e - Change event from an `.aips-select-all-topics` element.
		 */
		toggleSelectAll: function (e) {
			const isChecked = $(e.currentTarget).prop('checked');
			$('.aips-topic-checkbox').prop('checked', isChecked);
		},

		/**
		 * Sync all `.aips-feedback-checkbox` elements with the "select all"
		 * checkbox on the Feedback tab.
		 *
		 * @param {Event} e - Change event from an `.aips-select-all-feedback` element.
		 */
		toggleSelectAllFeedback: function (e) {
			const isChecked = $(e.currentTarget).prop('checked');
			$('.aips-feedback-checkbox').prop('checked', isChecked);
		},

		/**
		 * Apply the selected bulk action to all checked topics or feedback items.
		 *
		 * Validates that an action and at least one item are selected, shows a
		 * localised confirmation dialog, then fires the appropriate AJAX action
		 * (`aips_bulk_approve_topics`, `aips_bulk_reject_topics`,
		 * `aips_bulk_delete_topics`, `aips_bulk_generate_topics`, or
		 * `aips_bulk_delete_feedback`). Reloads the active tab's content on
		 * success.
		 *
		 * @param {Event} e - Click event from an `.aips-bulk-action-execute` element.
		 */
		executeBulkAction: function (e) {
			e.preventDefault();

			// Get the dropdown closest to the clicked button
			const $button = $(e.currentTarget);
			const $dropdown = $button.siblings('.aips-bulk-action-select');
			const action = $dropdown.val();
			const activeTab = $('.aips-tab-link.active').data('tab');

			if (!action) {
				AIPS.Utilities.showToast(aipsAuthorsL10n.selectBulkAction || 'Please select a bulk action.', 'warning');
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
				AIPS.Utilities.showToast(message, 'warning');
				return;
			}

			// Confirm action
			const confirmMessage = this.getBulkConfirmMessage(action, ids.length, activeTab);
			AIPS.Utilities.confirm(confirmMessage, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, continue',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
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
								AIPS.Utilities.showToast('Invalid bulk action for feedback.', 'error');
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
									AIPS.Utilities.showToast('Invalid bulk action.', 'error');
									$button.prop('disabled', false).text(aipsAuthorsL10n.execute || 'Execute');
									return;
							}
							data = {
								action: ajaxAction,
								nonce: aipsAuthorsL10n.nonce,
								topic_ids: ids
							};
						}

						// For generate_now: fetch a time estimate first, open a progress
						// bar modal, then run the (potentially long) generation request.
						if (action === 'generate_now') {
							this._runBulkGenerateWithProgress($button, ids, data, activeTab);
							return;
						}

						// Execute all other bulk actions normally (no progress bar needed)
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: data,
							success: (response) => {
								if (response.success) {
									AIPS.Utilities.showToast(response.data.message, 'success');
									// Reload content for current tab
									if (activeTab === 'feedback') {
										this.loadFeedback();
									} else {
										this.loadTopics(activeTab);
									}
								} else {
									AIPS.Utilities.showToast(
										response.data && response.data.message
											? response.data.message
											: aipsAuthorsL10n.errorBulkAction || 'Error executing bulk action.',
										'error'
									);
								}
							},
							error: () => {
								AIPS.Utilities.showToast(aipsAuthorsL10n.errorBulkAction || 'Error executing bulk action.', 'error');
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
					}
				}
			]);
		},

		/**
		 * Build a localised confirmation message for a bulk action.
		 *
		 * Selects the message template from a map keyed by action name,
		 * substitutes the item count for the `%d` placeholder, and substitutes
		 * the action name for the `%s` placeholder when no specific template
		 * exists.
		 *
		 * @param  {string} action    - The bulk action key (`'approve'`, `'reject'`,
		 *                              `'delete'`, or `'generate_now'`).
		 * @param  {number} count     - The number of selected items.
		 * @param  {string} activeTab - The currently active tab (used to pick the
		 *                              right delete message for feedback vs. topics).
		 * @return {string} The formatted confirmation message.
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

		/**
		 * Fetch a per-post time estimate, open a progress-bar modal, and then
		 * fire the bulk-generate AJAX request.
		 *
		 * The estimate is obtained from `aips_get_bulk_generate_estimate` (which
		 * averages recent `_aips_post_generation_total_time` post-meta values).
		 * A conservative fallback of 30 s/post is used if the endpoint is
		 * unavailable or returns no historical data.  The estimate already
		 * reflects real-world wall-clock time that includes AI API latency,
		 * resilience retries, and any random back-off delays.
		 *
		 * @param {jQuery} $button     - The Execute button element.
		 * @param {Array}  ids         - Array of topic ID strings.
		 * @param {Object} ajaxData    - POST data for the generation request.
		 * @param {string} activeTab   - Currently active tab name.
		 */
		_runBulkGenerateWithProgress: function ($button, ids, ajaxData, activeTab) {
			const DEFAULT_PER_POST_SECONDS = 30;

			// Fetch the time estimate, then show progress bar + start generation.
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_bulk_generate_estimate',
					nonce: aipsAuthorsL10n.nonce
				},
				success: (estimateResponse) => {
					let perPost = DEFAULT_PER_POST_SECONDS;
					if (estimateResponse && estimateResponse.success && estimateResponse.data && estimateResponse.data.per_post_seconds > 0) {
						perPost = estimateResponse.data.per_post_seconds;
					}
					this._launchBulkGenerateProgress($button, ids, ajaxData, activeTab, perPost);
				},
				error: () => {
					// Fallback: proceed with default estimate
					this._launchBulkGenerateProgress($button, ids, ajaxData, activeTab, DEFAULT_PER_POST_SECONDS);
				}
			});
		},

		/**
		 * Open the progress-bar modal and dispatch the bulk-generation request.
		 *
		 * @param {jQuery} $button         - The Execute button element.
		 * @param {Array}  ids             - Array of topic ID strings.
		 * @param {Object} ajaxData        - POST data for the generation request.
		 * @param {string} activeTab       - Currently active tab name.
		 * @param {number} perPostSeconds  - Estimated seconds per post.
		 */
		_launchBulkGenerateProgress: function ($button, ids, ajaxData, activeTab, perPostSeconds) {
			// Enforce a minimum duration so the progress bar is visible even for a
			// single very fast generation (avoids a flash that closes immediately).
			const MIN_PROGRESS_SECONDS = 10;
			const totalSeconds = Math.max(perPostSeconds * ids.length, MIN_PROGRESS_SECONDS);

			const progressBar = AIPS.Utilities.showProgressBar({
				title:        aipsAuthorsL10n.generatingPostsTitle   || 'Generating Posts',
				message:      aipsAuthorsL10n.generatingPostsMessage || 'Please wait while your posts are being generated. This may take a few minutes.',
				totalSeconds: totalSeconds
			});

			// Reset helper called when the request finishes.
			const resetUI = () => {
				$button.prop('disabled', false).text(aipsAuthorsL10n.execute || 'Execute');
				$('.aips-bulk-action-select').val('');
				$('.aips-select-all-topics').prop('checked', false);
				$('.aips-topic-checkbox').prop('checked', false);
			};

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: ajaxData,
				success: (response) => {
					if (response.success) {
						progressBar.complete(response.data.message, 'success');
						// Reload topic list after the modal closes (~1.2 s).
						setTimeout(() => {
							this.loadTopics(activeTab);
							AIPS.Utilities.showToast(response.data.message, 'success');
						}, 1400);
					} else {
						const errMsg = (response.data && response.data.message)
							? response.data.message
							: (aipsAuthorsL10n.errorBulkAction || 'Error executing bulk action.');
						progressBar.complete(errMsg, 'error');
						setTimeout(() => {
							AIPS.Utilities.showToast(errMsg, 'error');
						}, 1400);
					}
				},
				error: () => {
					const errMsg = aipsAuthorsL10n.errorBulkAction || 'Error executing bulk action.';
					progressBar.complete(errMsg, 'error');
					setTimeout(() => {
						AIPS.Utilities.showToast(errMsg, 'error');
					}, 1400);
				},
				complete: resetUI
			});
		},

		/**
		 * Fade out all open `.aips-modal` elements.
		 *
		 * @param {Event} e - Click event from an `.aips-modal-close` element.
		 */
		closeModals: function (e) {
			e.preventDefault();
			const shouldReloadAfterClose = $('#aips-suggest-authors-modal').is(':visible') && this.hasImportedSuggestedAuthor;
			const $visibleModals = $('.aips-modal:visible');

			$visibleModals.fadeOut();

			if (shouldReloadAfterClose) {
				$visibleModals.promise().done(function () {
					window.location.reload();
				});
			}
		},

		/**
		 * Escape a value for safe insertion as HTML text content.
		 *
		 * Converts the input to a string and replaces the five characters that
		 * are significant in HTML (`&`, `<`, `>`, `"`, `'`) with their entity
		 * equivalents. Returns an empty string for `null`, `undefined`, or on
		 * any unexpected error.
		 *
		 * @param  {*}      text - Value to escape (coerced to string if needed).
		 * @return {string} HTML-safe string.
		 */
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
		},

		/**
		 * Open the Author Suggestions modal.
		 *
		 * @param {Event} e - Click event from `#aips-suggest-authors-btn`.
		 */
		openSuggestModal: function (e) {
			e.preventDefault();
			this.hasImportedSuggestedAuthor = false;
			$('#aips-suggest-authors-results').hide();
			$('#aips-suggest-authors-cards').html('');
			$('#aips-suggest-authors-modal').fadeIn();
		},

		/**
		 * Submit the Author Suggestions form and display results.
		 *
		 * Sends `aips_suggest_authors` with the form inputs and renders suggestion
		 * cards in `#aips-suggest-authors-cards` on success.
		 *
		 * @param {Event} e - Submit event from `#aips-suggest-authors-form`.
		 */
		suggestAuthors: function (e) {
			e.preventDefault();

			const siteNiche = $('#aips-suggest-site-niche').val().trim();
			if (!siteNiche) {
				AIPS.Utilities.showToast(aipsAuthorsL10n.siteNicheRequired || 'Site niche is required.', 'warning');
				return;
			}

			const $btn = $('#aips-suggest-authors-submit');
			$btn.prop('disabled', true).html(
				'<span class="dashicons dashicons-update aips-spin"></span> ' +
				(aipsAuthorsL10n.generatingSuggestions || 'Generating suggestions...')
			);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_suggest_authors',
					nonce: aipsAuthorsL10n.nonce,
					site_niche: siteNiche,
					target_audience: $('#aips-suggest-target-audience').val().trim(),
					content_goals: $('#aips-suggest-content-goals').val().trim(),
					count: $('#aips-suggest-count').val()
				},
				success: (response) => {
					if (response.success && response.data.suggestions && response.data.suggestions.length > 0) {
						this.renderSuggestedAuthors(response.data.suggestions);
						$('#aips-suggest-authors-results').show();
					} else {
						const msg = (response.data && response.data.message)
							? response.data.message
							: (aipsAuthorsL10n.errorGeneratingSuggestions || 'Error generating author suggestions.');
						AIPS.Utilities.showToast(msg, 'error');
					}
				},
				error: () => {
					AIPS.Utilities.showToast(aipsAuthorsL10n.errorGeneratingSuggestions || 'Error generating author suggestions.', 'error');
				},
				complete: () => {
					$btn.prop('disabled', false).html(
						'<span class="dashicons dashicons-lightbulb"></span> ' +
						(aipsAuthorsL10n.generateSuggestions || 'Generate Suggestions')
					);
				}
			});
		},

		/**
		 * Render the suggested author cards into `#aips-suggest-authors-cards`.
		 *
		 * Uses the `#aips-tmpl-suggestion-card` and `#aips-tmpl-suggestion-meta-row`
		 * HTML templates (defined in authors.php) so the markup lives in one place
		 * and is not duplicated in JavaScript.
		 *
		 * @param {Array<Object>} suggestions - Array of suggestion objects from the server.
		 */
		renderSuggestedAuthors: function (suggestions) {
			var html = '';

			suggestions.forEach(function (suggestion, index) {
				// Build the meta rows from optional fields using the meta-row template.
				// render() auto-escapes tokens so values are safe to insert.
				var metaRows = '';
				var metaFields = [
					{ key: 'keywords',                label: aipsAuthorsL10n.keywordsLabel        || 'Keywords' },
					{ key: 'voice_tone',               label: aipsAuthorsL10n.voiceToneLabel       || 'Voice/Tone' },
					{ key: 'writing_style',            label: aipsAuthorsL10n.writingStyleLabel    || 'Writing Style' },
					{ key: 'topic_generation_prompt',  label: aipsAuthorsL10n.topicPromptLabel     || 'Topic Generation Prompt' },
				];
				metaFields.forEach(function (field) {
					if (suggestion[field.key]) {
						metaRows += AIPS.Templates.render('aips-tmpl-suggestion-meta-row', {
							label: field.label,
							value: suggestion[field.key],
						});
					}
				});

				var importLabel = aipsAuthorsL10n.importAuthor || 'Import Author';
				var ariaLabel  = importLabel + ': ' + (suggestion.name || '');

				// render() handles escaping of all string tokens; only `meta` and `index`
				// are safe to insert as-is (meta is already rendered HTML, index is a number).
				html += AIPS.Templates.renderRaw('aips-tmpl-suggestion-card', {
					index:           index,
					name:            AIPS.Templates.escape(suggestion.name || ''),
					field_niche:     AIPS.Templates.escape(suggestion.field_niche || ''),
					description:     AIPS.Templates.escape(suggestion.description || ''),
					meta:            metaRows,
					importLabel:     AIPS.Templates.escape(importLabel),
					importAriaLabel: AIPS.Templates.escape(ariaLabel),
				});
			});

			// Store suggestions data on the container for later retrieval on import
			var $cards = $('#aips-suggest-authors-cards');
			$cards.html(html);
			$cards.data('suggestions', suggestions);
		},

		/**
		 * Import a suggested author profile by saving it via `aips_save_author`.
		 *
		 * Reads the suggestion data stored on `#aips-suggest-authors-cards` by
		 * index, sends the AJAX request, and shows a success toast on completion.
		 * Disables the import button while the request is in flight.
		 *
		 * @param {Event} e - Click event from an `.aips-import-suggested-author` element.
		 */
		importSuggestedAuthor: function (e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const index = parseInt($btn.data('index'), 10);
			const suggestions = $('#aips-suggest-authors-cards').data('suggestions');

			if (!suggestions || !suggestions[index]) {
				return;
			}

			const suggestion = suggestions[index];
			$btn.prop('disabled', true).html(
				'<span class="dashicons dashicons-update aips-spin"></span> ' +
				(aipsAuthorsL10n.importingAuthor || 'Importing...')
			);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_save_author',
					nonce: aipsAuthorsL10n.nonce,
					name: suggestion.name,
					field_niche: suggestion.field_niche,
					description: suggestion.description || '',
					details: suggestion.details || '',
					keywords: suggestion.keywords || '',
					voice_tone: suggestion.voice_tone || '',
					writing_style: suggestion.writing_style || '',
					topic_generation_prompt: suggestion.topic_generation_prompt || '',
					target_audience: suggestion.target_audience || '',
					expertise_level: suggestion.expertise_level || '',
					content_goals: suggestion.content_goals || '',
					excluded_topics: suggestion.excluded_topics || '',
					preferred_content_length: suggestion.preferred_content_length || '',
					language: suggestion.language || 'en',
					max_posts_per_topic: suggestion.max_posts_per_topic || 1,
					topic_generation_frequency: 'weekly',
					topic_generation_quantity: 5,
					post_generation_frequency: 'daily',
					post_status: 'draft',
					is_active: 1
				},
				success: (response) => {
					if (response.success) {
						this.hasImportedSuggestedAuthor = true;
						AIPS.Utilities.showToast(aipsAuthorsL10n.authorImported || 'Author imported successfully.', 'success');
						$btn.prop('disabled', true).html(
							'<span class="dashicons dashicons-yes"></span> ' +
							(aipsAuthorsL10n.importedAuthor || 'Imported Author')
						);
					} else {
						const msg = (response.data && response.data.message)
							? response.data.message
							: (aipsAuthorsL10n.errorImportingAuthor || 'Error importing author.');
						AIPS.Utilities.showToast(msg, 'error');
						$btn.prop('disabled', false).html(
							'<span class="dashicons dashicons-download"></span> ' +
							(aipsAuthorsL10n.importAuthor || 'Import Author')
						);
					}
				},
				error: () => {
					AIPS.Utilities.showToast(aipsAuthorsL10n.errorImportingAuthor || 'Error importing author.', 'error');
					$btn.prop('disabled', false).html(
						'<span class="dashicons dashicons-download"></span> ' +
						(aipsAuthorsL10n.importAuthor || 'Import Author')
					);
				}
			});
		}
	};
	
	// Generation Queue Module
	const GenerationQueueModule = {
		queueTopics: [],
		filteredQueueTopics: [],
		queueCurrentPage: 1,
		queuePerPage: 10,

		/**
		 * Initialise the Generation Queue module by binding all event listeners.
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Register delegated event listeners for the Generation Queue tab,
		 * including main-tab switching, queue-item selection, and bulk-action
		 * execution.
		 */
		bindEvents: function () {
			// React to shared tab switching events for top-level Authors tabs.
			$(document).on('aips:tabSwitch', this.handleSharedTabSwitch.bind(this));
			
			// Queue-specific actions
			$(document).on('click', '.aips-queue-bulk-action-execute', this.executeQueueBulkAction.bind(this));
			$(document).on('click', '.aips-queue-select-all', this.toggleQueueSelectAll.bind(this));
			$(document).on('click', '#aips-queue-filter-submit', this.applyQueueFilters.bind(this));
			$(document).on('change', '#aips-queue-author-filter, #aips-queue-field-filter', this.applyQueueFilters.bind(this));
			$(document).on('keyup search', '#aips-queue-search', this.onQueueSearch.bind(this));
			$(document).on('click', '#aips-queue-search-clear', this.clearQueueSearch.bind(this));
			$(document).on('click', '#aips-queue-reload-btn', this.loadQueueTopics.bind(this));
			$(document).on('click', '.aips-queue-page-link', this.goToQueuePage.bind(this));
		},

		/**
		 * Handle shared AIPS tab-switch events for the Authors page top tabs.
		 *
		 * @param {jQuery.Event} e     - Custom event object.
		 * @param {string}       tabId - The activated tab ID.
		 */
		handleSharedTabSwitch: function (e, tabId) {
			if (!$('#generation-queue-tab').length) {
				return;
			}

			if (tabId === 'generation-queue') {
				this.loadQueueTopics();
			}
		},

		/**
		 * Fetch approved topics in the generation queue via `aips_get_generation_queue`.
		 *
		 * Shows a loading message in `#aips-queue-topics-list`, then delegates
		 * to `renderQueueTopics` on success or shows an inline error message on
		 * failure.
		 */
		loadQueueTopics: function () {
			$('#aips-queue-topics-list').html('<div class="aips-panel-body"><p>' + (aipsAuthorsL10n.loadingQueue || 'Loading queue...') + '</p></div>');
			$('#aips-queue-tablenav').hide();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_generation_queue',
					nonce: aipsAuthorsL10n.nonce
				},
				success: (response) => {
					if (response.success && response.data.topics) {
						this.queueTopics = response.data.topics;
						this.populateQueueFilters();
						this.applyQueueFilters();
					} else {
						$('#aips-queue-topics-list').html(
							'<div class="aips-panel-body"><p>' + (response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorLoadingQueue || 'Error loading queue.') + '</p></div>'
						);
						$('#aips-queue-tablenav').hide();
					}
				},
				error: () => {
					$('#aips-queue-topics-list').html('<div class="aips-panel-body"><p>' + (aipsAuthorsL10n.errorLoadingQueue || 'Error loading queue.') + '</p></div>');
					$('#aips-queue-tablenav').hide();
				}
			});
		},

		/**
		 * Populate queue filter dropdowns from loaded queue data.
		 */
		populateQueueFilters: function () {
			const authorValue = $('#aips-queue-author-filter').val() || '';
			const fieldValue = $('#aips-queue-field-filter').val() || '';
			const authorSet = new Set();
			const fieldSet = new Set();

			this.queueTopics.forEach(topic => {
				if (topic.author_name) {
					authorSet.add(topic.author_name);
				}
				if (topic.field_niche) {
					fieldSet.add(topic.field_niche);
				}
			});

			const authors = Array.from(authorSet).sort((a, b) => a.localeCompare(b));
			const fields = Array.from(fieldSet).sort((a, b) => a.localeCompare(b));

			const $authorFilter = $('#aips-queue-author-filter');
			const $fieldFilter = $('#aips-queue-field-filter');

			$authorFilter.find('option:not(:first)').remove();
			$fieldFilter.find('option:not(:first)').remove();

			authors.forEach(author => {
				$authorFilter.append('<option value="' + AuthorsModule.escapeHtml(author) + '">' + AuthorsModule.escapeHtml(author) + '</option>');
			});

			fields.forEach(field => {
				$fieldFilter.append('<option value="' + AuthorsModule.escapeHtml(field) + '">' + AuthorsModule.escapeHtml(field) + '</option>');
			});

			$authorFilter.val(authorValue);
			$fieldFilter.val(fieldValue);
		},

		/**
		 * Apply queue filters and redraw queue table and footer.
		 */
		applyQueueFilters: function () {
			const selectedAuthor = $('#aips-queue-author-filter').val() || '';
			const selectedField = $('#aips-queue-field-filter').val() || '';
			const searchTerm = ($('#aips-queue-search').val() || '').toLowerCase().trim();

			this.queueCurrentPage = 1;
			this.filteredQueueTopics = this.queueTopics.filter(topic => {
				const matchesAuthor = !selectedAuthor || topic.author_name === selectedAuthor;
				const matchesField = !selectedField || topic.field_niche === selectedField;
				const haystack = ((topic.topic_title || '') + ' ' + (topic.author_name || '') + ' ' + (topic.field_niche || '')).toLowerCase();
				const matchesSearch = !searchTerm || haystack.indexOf(searchTerm) !== -1;
				return matchesAuthor && matchesField && matchesSearch;
			});

			$('#aips-queue-search-clear').toggle(searchTerm.length > 0);
			this.renderQueueTopics();
		},

		/**
		 * React to queue search input changes.
		 */
		onQueueSearch: function () {
			this.applyQueueFilters();
		},

		/**
		 * Clear queue search input and re-apply filters.
		 *
		 * @param {Event} e - Click event from the clear button.
		 */
		clearQueueSearch: function (e) {
			e.preventDefault();
			$('#aips-queue-search').val('');
			this.applyQueueFilters();
			$('#aips-queue-search').focus();
		},

		/**
		 * Build and inject the queue HTML table into `#aips-queue-topics-list`.
		 *
		 * Renders a WordPress-style table with checkboxes, topic title, author
		 * name, field/niche, and approved date columns. Shows a "no topics in
		 * queue" message when the array is empty.
		 *
		 * @param {Array<Object>} topics - Array of queue-entry objects from the server.
		 */
		renderQueueTopics: function () {
			const topics = this.filteredQueueTopics;

			if (!topics || topics.length === 0) {
				$('#aips-queue-topics-list').html(
					'<div class="aips-panel-body"><div class="aips-empty-state">'
					+ '<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>'
					+ '<h3 class="aips-empty-state-title">' + (aipsAuthorsL10n.noQueueTopicsTitle || 'No Queue Topics Found') + '</h3>'
					+ '<p class="aips-empty-state-description">' + (aipsAuthorsL10n.noQueueTopics || 'No approved topics in the queue yet.') + '</p>'
					+ '</div></div>'
				);
				$('#aips-queue-tablenav').hide();
				return;
			}

			const totalItems = topics.length;
			const totalPages = Math.max(1, Math.ceil(totalItems / this.queuePerPage));
			if (this.queueCurrentPage > totalPages) {
				this.queueCurrentPage = totalPages;
			}
			const start = (this.queueCurrentPage - 1) * this.queuePerPage;
			const pageItems = topics.slice(start, start + this.queuePerPage);

			let rowsHtml = '';

			pageItems.forEach(topic => {
				rowsHtml += AIPS.Templates.renderRaw('aips-tmpl-queue-row', {
					id: topic.id,
					title: AIPS.Templates.escape(topic.topic_title),
					author: AIPS.Templates.escape(topic.author_name),
					field: AIPS.Templates.escape(topic.field_niche),
					date: AIPS.Templates.escape(topic.reviewed_at || aipsAuthorsL10n.notAvailable || 'N/A')
				});
			});

			const tableHtml = AIPS.Templates.renderRaw('aips-tmpl-queue-table', {
				titleLabel: AIPS.Templates.escape(aipsAuthorsL10n.topicTitle || 'Topic Title'),
				authorLabel: AIPS.Templates.escape(aipsAuthorsL10n.author || 'Author'),
				fieldLabel: AIPS.Templates.escape(aipsAuthorsL10n.fieldNiche || 'Field/Niche'),
				dateLabel: AIPS.Templates.escape(aipsAuthorsL10n.approvedDate || 'Approved Date'),
				rows: rowsHtml
			});

			$('#aips-queue-topics-list').html(tableHtml);

			const topicLabel = totalItems === 1 ? (aipsAuthorsL10n.topic || 'topic') : (aipsAuthorsL10n.topics || 'topics');
			$('#aips-queue-table-footer-count').text(totalItems + ' ' + topicLabel);
			this.renderQueuePagination(totalPages);
			$('#aips-queue-tablenav').show();
		},

		/**
		 * Render queue pagination controls in the queue footer.
		 *
		 * @param {number} totalPages - Total number of pages.
		 */
		renderQueuePagination: function (totalPages) {
			const current = this.queueCurrentPage;

			if (totalPages <= 1) {
				$('#aips-queue-pagination-links').html('');
				return;
			}

			let pagesHtml = '';
			const start = Math.max(1, current - 3);
			const end = Math.min(totalPages, current + 3);

			if (start > 1) {
				pagesHtml += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-queue-page-link" data-page="1">1</button>';
				if (start > 2) {
					pagesHtml += '<span class="aips-history-page-ellipsis">…</span>';
				}
			}

			for (let p = start; p <= end; p++) {
				if (p === current) {
					pagesHtml += '<span class="aips-btn aips-btn-sm aips-btn-primary" aria-current="page">' + p + '</span>';
				} else {
					pagesHtml += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-queue-page-link" data-page="' + p + '">' + p + '</button>';
				}
			}

			if (end < totalPages) {
				if (end < totalPages - 1) {
					pagesHtml += '<span class="aips-history-page-ellipsis">…</span>';
				}
				pagesHtml += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-queue-page-link" data-page="' + totalPages + '">' + totalPages + '</button>';
			}

			const paginationHtml = AIPS.Templates.renderRaw('aips-tmpl-queue-pagination', {
				prevPage: current - 1,
				prevDisabled: current <= 1 ? 'disabled' : '',
				nextPage: current + 1,
				nextDisabled: current >= totalPages ? 'disabled' : '',
				pages: pagesHtml
			});

			$('#aips-queue-pagination-links').html(paginationHtml);
		},

		/**
		 * Navigate queue pagination.
		 *
		 * @param {Event} e - Click event from queue pagination links.
		 */
		goToQueuePage: function (e) {
			e.preventDefault();
			const page = parseInt($(e.currentTarget).data('page'), 10);
			if (!Number.isInteger(page) || page < 1) {
				return;
			}
			this.queueCurrentPage = page;
			this.renderQueueTopics();
		},

		/**
		 * Sync all `.aips-queue-topic-checkbox` elements with the "select all"
		 * checkbox in the queue table.
		 *
		 * @param {Event} e - Change event from an `.aips-queue-select-all` element.
		 */
		toggleQueueSelectAll: function (e) {
			const isChecked = $(e.currentTarget).prop('checked');
			$('.aips-queue-topic-checkbox').prop('checked', isChecked);
		},

		/**
		 * Dispatch the selected queue bulk action against all checked topics.
		 *
		 * Validates that an action and at least one topic are selected, then
		 * delegates to the appropriate handler method. Currently only
		 * `'generate_now'` is supported.
		 *
		 * @param {Event} e - Click event from an `.aips-queue-bulk-action-execute`
		 *                    element.
		 */
		executeQueueBulkAction: function (e) {
			e.preventDefault();

			const action = $('#aips-queue-bulk-action-select').val();

			if (!action) {
				AIPS.Utilities.showToast(aipsAuthorsL10n.selectBulkAction || 'Please select a bulk action.', 'warning');
				return;
			}

			// Get all checked topic IDs
			const topicIds = [];
			$('.aips-queue-topic-checkbox:checked').each(function () {
				topicIds.push($(this).val());
			});

			if (topicIds.length === 0) {
				AIPS.Utilities.showToast(aipsAuthorsL10n.noTopicsSelected || 'Please select at least one topic.', 'warning');
				return;
			}

			// Handle different actions
			switch (action) {
				case 'generate_now':
					this.generateNowFromQueue(topicIds);
					break;
				default:
					AIPS.Utilities.showToast(aipsAuthorsL10n.invalidAction || 'Invalid action.', 'error');
			}
		},

		/**
		 * Confirm and immediately generate posts for a set of queue topics.
		 *
		 * Shows a localised confirmation dialog with the selected count. On
		 * confirmation, sends `aips_bulk_generate_from_queue` and reloads the
		 * queue on success.
		 *
		 * @param {Array<string>} topicIds - Array of topic ID strings to generate.
		 */
		generateNowFromQueue: function (topicIds) {
			const confirmMessage = (aipsAuthorsL10n.confirmGenerateFromQueue || 'Generate posts now for %d selected topic(s)?').replace('%d', topicIds.length);
			const $button = $('.aips-queue-bulk-action-execute');

			AIPS.Utilities.confirm(confirmMessage, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, generate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
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
									AIPS.Utilities.showToast(
										response.data.message || aipsAuthorsL10n.postsGenerated || 'Posts generated successfully.',
										'success'
									);
									// Reload the queue
									this.loadQueueTopics();
								} else {
									AIPS.Utilities.showToast(
										response.data && response.data.message
											? response.data.message
											: aipsAuthorsL10n.errorGenerating || 'Error generating posts.',
										'error'
									);
								}
							},
							error: () => {
								AIPS.Utilities.showToast(aipsAuthorsL10n.errorGenerating || 'Error generating posts.', 'error');
							},
							complete: () => {
								$button.prop('disabled', false).text(aipsAuthorsL10n.execute || 'Execute');
							}
						});
					}
				}
			]);
		}
	};
  
	// Initialize when document is ready
	$(document).ready(function () {
		AuthorsModule.init();
		GenerationQueueModule.init();

		// On the Author Topics full-page view, auto-load topics for the current author.
		// The author ID is passed via aipsAuthorContext.authorId (set by PHP when the
		// page param is 'aips-author-topics'), so no inline script injection is needed.
		if ( typeof aipsAuthorContext !== 'undefined' && aipsAuthorContext.authorId ) {
			AuthorsModule.currentAuthorId = aipsAuthorContext.authorId;
			AuthorsModule.updateBulkActionDropdown('pending');
			AuthorsModule.loadTopics('pending');
		}

		// Deep-link: on the Authors list page, open the Edit modal directly when
		// an author_id is provided in the URL (e.g., redirected from "Edit Author").
		if ( typeof aipsAuthorContext !== 'undefined' && aipsAuthorContext.deepLinkAuthorId ) {
			const deepLinkId = parseInt( aipsAuthorContext.deepLinkAuthorId, 10 );
			const $editBtn = $( '.aips-edit-author' ).filter( function () {
				return parseInt( $( this ).data( 'id' ), 10 ) === deepLinkId;
			} );
			if ( $editBtn.length ) {
				$editBtn.first().trigger( 'click' );
			}
		}
	});
})(jQuery);
