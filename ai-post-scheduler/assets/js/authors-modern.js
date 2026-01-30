/**
 * Modern Authors UI JavaScript with Headless UI
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

(function ($) {
	'use strict';

	// Modern Authors Module
	const AuthorsModernModule = {
		currentAuthorId: null,
		currentTopicId: null,
		topics: [],
		filteredTopics: [],
		
		init: function () {
			this.bindEvents();
			this.initSlideOver();
		},

		bindEvents: function () {
			// Author Card Actions
			$(document).on('click', '.aips-author-card-view', this.viewAuthorTopics.bind(this));
			$(document).on('click', '.aips-author-card-edit', this.editAuthor.bind(this));
			$(document).on('click', '.aips-author-card-delete', this.deleteAuthor.bind(this));
			$(document).on('click', '.aips-author-card-generate', this.generateTopicsNow.bind(this));
			
			// Add New Author
			$('.aips-add-author-btn').on('click', this.openAddAuthorSlideOver.bind(this));
			
			// Slide-over controls
			$(document).on('click', '.aips-slideover-close', this.closeSlideOver.bind(this));
			$(document).on('click', '.aips-slideover-overlay', this.closeSlideOver.bind(this));
			
			// Author form
			$(document).on('submit', '#aips-author-form-modern', this.saveAuthor.bind(this));
			
			// Topic tabs
			$(document).on('click', '.aips-tab', this.switchTopicTab.bind(this));
			
			// Topic chip actions
			$(document).on('click', '.aips-topic-chip', this.selectTopicChip.bind(this));
			$(document).on('click', '.aips-topic-chip-edit', this.editTopicInSlideOver.bind(this));
			
			// Bulk actions
			$(document).on('click', '.aips-bulk-action-execute', this.executeBulkAction.bind(this));
			$(document).on('change', '.aips-select-all-topics', this.toggleSelectAllTopics.bind(this));
			
			// Topic actions from slide-over
			$(document).on('click', '.aips-topic-approve', this.approveTopic.bind(this));
			$(document).on('click', '.aips-topic-reject', this.rejectTopic.bind(this));
			$(document).on('click', '.aips-topic-delete', this.deleteTopic.bind(this));
			$(document).on('click', '.aips-topic-generate-post', this.generatePostNow.bind(this));
			
			// Topic form
			$(document).on('submit', '#aips-topic-form-modern', this.saveTopicEdit.bind(this));
		},

		initSlideOver: function () {
			// Create slide-over overlay if it doesn't exist
			if ($('.aips-slideover-overlay').length === 0) {
				$('body').append('<div class="aips-slideover-overlay"></div>');
			}
			
			// Create slide-over container for topics
			if ($('#aips-topics-slideover').length === 0) {
				$('body').append(`
					<div id="aips-topics-slideover" class="aips-slideover">
						<div class="aips-slideover-header">
							<h2 class="aips-slideover-title" id="aips-topics-slideover-title">Topics</h2>
							<button class="aips-slideover-close" aria-label="Close">&times;</button>
						</div>
						<div class="aips-slideover-body" id="aips-topics-slideover-body">
							<div class="aips-loading-spinner"></div>
						</div>
					</div>
				`);
			}
			
			// Create slide-over container for author edit
			if ($('#aips-author-slideover').length === 0) {
				$('body').append(`
					<div id="aips-author-slideover" class="aips-slideover">
						<div class="aips-slideover-header">
							<h2 class="aips-slideover-title" id="aips-author-slideover-title">Edit Author</h2>
							<button class="aips-slideover-close" aria-label="Close">&times;</button>
						</div>
						<div class="aips-slideover-body" id="aips-author-slideover-body">
							<div class="aips-loading-spinner"></div>
						</div>
					</div>
				`);
			}
		},

		openSlideOver: function (slideOverId) {
			$('.aips-slideover-overlay').addClass('active');
			$('#' + slideOverId).addClass('active');
			$('body').css('overflow', 'hidden');
		},

		closeSlideOver: function (e) {
			if (e) {
				e.preventDefault();
			}
			$('.aips-slideover-overlay').removeClass('active');
			$('.aips-slideover').removeClass('active');
			$('body').css('overflow', '');
		},

		viewAuthorTopics: function (e) {
			e.preventDefault();
			const authorId = $(e.currentTarget).data('id');
			const authorName = $(e.currentTarget).data('name');
			
			this.currentAuthorId = authorId;
			this.loadAuthorTopics(authorId, authorName);
		},

		loadAuthorTopics: function (authorId, authorName) {
			this.openSlideOver('aips-topics-slideover');
			$('#aips-topics-slideover-title').text(authorName + ' - Topics');
			$('#aips-topics-slideover-body').html('<div style="text-align: center; padding: 40px;"><div class="aips-loading-spinner" style="margin: 0 auto;"></div><p style="margin-top: 16px; color: #6b7280;">Loading topics...</p></div>');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_author_topics',
					nonce: aipsAuthorsL10n.nonce,
					author_id: authorId
				},
				success: (response) => {
					if (response.success) {
						this.topics = response.data.topics;
						this.renderTopicsView(response.data.topics, response.data.status_counts);
					} else {
						this.showError('Failed to load topics: ' + (response.data.message || 'Unknown error'));
					}
				},
				error: () => {
					this.showError('Failed to load topics. Please try again.');
				}
			});
		},

		renderTopicsView: function (topics, statusCounts) {
			const html = `
				<div class="aips-tabs">
					<button class="aips-tab active" data-status="pending">
						Pending Review
						<span class="aips-tab-count">${statusCounts.pending || 0}</span>
					</button>
					<button class="aips-tab" data-status="approved">
						Approved
						<span class="aips-tab-count">${statusCounts.approved || 0}</span>
					</button>
					<button class="aips-tab" data-status="rejected">
						Rejected
						<span class="aips-tab-count">${statusCounts.rejected || 0}</span>
					</button>
				</div>
				
				<div class="aips-bulk-actions-bar">
					<label style="display: flex; align-items: center; gap: 8px;">
						<input type="checkbox" class="aips-select-all-topics">
						<span style="font-size: 14px; color: #374151;">Select All</span>
					</label>
					<select class="aips-bulk-select" id="aips-bulk-action-select">
						<option value="">Bulk Actions</option>
						<option value="approve">Approve Selected</option>
						<option value="reject">Reject Selected</option>
						<option value="delete">Delete Selected</option>
					</select>
					<button class="aips-btn aips-btn-primary aips-btn-sm aips-bulk-action-execute">
						Execute
					</button>
				</div>
				
				<div id="aips-topics-chips-wrapper" class="aips-topics-chips-container">
					${this.renderTopicChips(topics, 'pending')}
				</div>
			`;
			
			$('#aips-topics-slideover-body').html(html);
		},

		renderTopicChips: function (topics, status) {
			const filteredTopics = topics.filter(t => t.status === status);
			
			if (filteredTopics.length === 0) {
				return `
					<div class="aips-empty-state-modern">
						<div class="aips-empty-state-icon">📝</div>
						<h3 class="aips-empty-state-title">No ${status} topics</h3>
						<p class="aips-empty-state-text">There are no ${status} topics for this author.</p>
					</div>
				`;
			}
			
			let html = '<div class="aips-topics-chips">';
			
			filteredTopics.forEach(topic => {
				const postCount = topic.post_count || 0;
				html += `
					<div class="aips-topic-chip ${topic.status}" data-topic-id="${topic.id}">
						<input type="checkbox" class="aips-topic-chip-checkbox" data-topic-id="${topic.id}">
						<span class="aips-topic-chip-title" title="${this.escapeHtml(topic.topic_title)}">
							${this.escapeHtml(topic.topic_title)}
						</span>
						${postCount > 0 ? `<span class="aips-topic-chip-count">${postCount}</span>` : ''}
					</div>
				`;
			});
			
			html += '</div>';
			return html;
		},

		switchTopicTab: function (e) {
			e.preventDefault();
			const $tab = $(e.currentTarget);
			const status = $tab.data('status');
			
			// Update active tab
			$('.aips-tab').removeClass('active');
			$tab.addClass('active');
			
			// Re-render topics
			$('#aips-topics-chips-wrapper').html(this.renderTopicChips(this.topics, status));
			
			// Reset select all checkbox
			$('.aips-select-all-topics').prop('checked', false);
		},

		selectTopicChip: function (e) {
			// If clicking on checkbox, let it handle itself
			if ($(e.target).hasClass('aips-topic-chip-checkbox')) {
				return;
			}
			
			e.preventDefault();
			const $chip = $(e.currentTarget);
			const topicId = $chip.data('topic-id');
			
			// Show topic details in a nested slide-over or expand inline
			this.showTopicDetails(topicId);
		},

		showTopicDetails: function (topicId) {
			const topic = this.topics.find(t => t.id === topicId);
			if (!topic) return;
			
			this.currentTopicId = topicId;
			
			// For now, just show actions inline
			// Could be enhanced to show a detailed view
			const confirmed = confirm('Topic: ' + topic.topic_title + '\n\nWhat would you like to do?\n\nOK = View Details\nCancel = Close');
			if (confirmed) {
				this.editTopicInSlideOver({currentTarget: {dataset: {topicId: topicId}}});
			}
		},

		editTopicInSlideOver: function (e) {
			e.preventDefault();
			const topicId = e.currentTarget.dataset.topicId || this.currentTopicId;
			const topic = this.topics.find(t => t.id == topicId);
			
			if (!topic) return;
			
			const html = `
				<form id="aips-topic-form-modern">
					<input type="hidden" name="topic_id" value="${topic.id}">
					
					<div class="aips-form-group">
						<label class="aips-form-label">Topic Title</label>
						<input type="text" name="topic_title" class="aips-form-input" value="${this.escapeHtml(topic.topic_title)}" required>
					</div>
					
					<div class="aips-form-group">
						<label class="aips-form-label">Status</label>
						<div style="font-weight: 600; color: #374151;">${topic.status}</div>
					</div>
					
					<div class="aips-form-group">
						<label class="aips-form-label">Generated</label>
						<div style="color: #6b7280;">${topic.created_at || 'N/A'}</div>
					</div>
					
					${topic.post_count > 0 ? `
					<div class="aips-form-group">
						<label class="aips-form-label">Posts Generated</label>
						<div style="color: #6b7280;">${topic.post_count} post(s)</div>
					</div>
					` : ''}
					
					<div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
						<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">Actions</h3>
						<div style="display: flex; flex-direction: column; gap: 8px;">
							${topic.status === 'pending' ? `
								<button type="button" class="aips-btn aips-btn-primary aips-topic-approve" data-topic-id="${topic.id}">
									✓ Approve Topic
								</button>
								<button type="button" class="aips-btn aips-btn-danger aips-topic-reject" data-topic-id="${topic.id}">
									✗ Reject Topic
								</button>
							` : ''}
							${topic.status === 'approved' ? `
								<button type="button" class="aips-btn aips-btn-primary aips-topic-generate-post" data-topic-id="${topic.id}">
									📝 Generate Post Now
								</button>
							` : ''}
							<button type="button" class="aips-btn aips-btn-danger aips-topic-delete" data-topic-id="${topic.id}">
								🗑 Delete Topic
							</button>
						</div>
					</div>
				</form>
			`;
			
			// Update the current slide-over
			$('#aips-topics-slideover-title').text('Topic Details');
			$('#aips-topics-slideover-body').html(html);
			
			// Add back button
			$('.aips-slideover-header').prepend(`
				<button class="aips-btn aips-btn-secondary aips-btn-sm" id="aips-back-to-topics">
					← Back to Topics
				</button>
			`);
			
			$('#aips-back-to-topics').on('click', () => {
				$('#aips-back-to-topics').remove();
				this.renderTopicsView(this.topics, this.getStatusCounts());
			});
		},

		getStatusCounts: function () {
			return {
				pending: this.topics.filter(t => t.status === 'pending').length,
				approved: this.topics.filter(t => t.status === 'approved').length,
				rejected: this.topics.filter(t => t.status === 'rejected').length
			};
		},

		editAuthor: function (e) {
			e.preventDefault();
			const authorId = $(e.currentTarget).data('id');
			this.currentAuthorId = authorId;
			
			this.openSlideOver('aips-author-slideover');
			$('#aips-author-slideover-title').text('Edit Author');
			$('#aips-author-slideover-body').html('<div style="text-align: center; padding: 40px;"><div class="aips-loading-spinner" style="margin: 0 auto;"></div></div>');
			
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
						this.renderAuthorForm(response.data.author);
					} else {
						this.showError('Failed to load author data');
					}
				},
				error: () => {
					this.showError('Failed to load author data');
				}
			});
		},

		openAddAuthorSlideOver: function (e) {
			e.preventDefault();
			this.currentAuthorId = null;
			this.openSlideOver('aips-author-slideover');
			$('#aips-author-slideover-title').text('Add New Author');
			this.renderAuthorForm(null);
		},

		renderAuthorForm: function (author) {
			const isEdit = author !== null;
			
			const html = `
				<form id="aips-author-form-modern">
					<input type="hidden" name="author_id" value="${isEdit ? author.id : ''}">
					
					<div class="aips-form-group">
						<label class="aips-form-label">Name *</label>
						<input type="text" name="name" class="aips-form-input" value="${isEdit ? this.escapeHtml(author.name) : ''}" required>
					</div>
					
					<div class="aips-form-group">
						<label class="aips-form-label">Field/Niche *</label>
						<input type="text" name="field_niche" class="aips-form-input" value="${isEdit ? this.escapeHtml(author.field_niche) : ''}" placeholder="e.g., PHP Programming" required>
						<p class="aips-form-description">The main topic or field this author covers</p>
					</div>
					
					<div class="aips-form-group">
						<label class="aips-form-label">Keywords</label>
						<input type="text" name="keywords" class="aips-form-input" value="${isEdit ? this.escapeHtml(author.keywords || '') : ''}" placeholder="e.g., Laravel, Symfony, Composer, PSR">
						<p class="aips-form-description">Comma-separated keywords to focus on when generating topics</p>
					</div>
					
					<div class="aips-form-group">
						<label class="aips-form-label">Details</label>
						<textarea name="details" class="aips-form-textarea" rows="4" placeholder="Additional context or instructions for topic generation...">${isEdit ? this.escapeHtml(author.details || '') : ''}</textarea>
						<p class="aips-form-description">Additional context that will be included when generating topics</p>
					</div>
					
					<div class="aips-form-group">
						<label class="aips-form-label">Description</label>
						<textarea name="description" class="aips-form-textarea" rows="3">${isEdit ? this.escapeHtml(author.description || '') : ''}</textarea>
					</div>
					
					<div class="aips-form-group">
						<label class="aips-form-label">Topic Generation Frequency</label>
						<select name="topic_generation_frequency" class="aips-form-select">
							<option value="daily" ${isEdit && author.topic_generation_frequency === 'daily' ? 'selected' : ''}>Daily</option>
							<option value="weekly" ${!isEdit || author.topic_generation_frequency === 'weekly' ? 'selected' : ''}>Weekly</option>
							<option value="biweekly" ${isEdit && author.topic_generation_frequency === 'biweekly' ? 'selected' : ''}>Bi-weekly</option>
							<option value="monthly" ${isEdit && author.topic_generation_frequency === 'monthly' ? 'selected' : ''}>Monthly</option>
						</select>
					</div>
					
					<div class="aips-form-group">
						<label class="aips-form-label">Number of Topics to Generate</label>
						<input type="number" name="topic_generation_quantity" class="aips-form-input" value="${isEdit ? author.topic_generation_quantity : '5'}" min="1" max="20">
					</div>
					
					<div class="aips-form-group">
						<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
							<input type="checkbox" name="is_active" ${!isEdit || author.is_active ? 'checked' : ''}>
							<span class="aips-form-label" style="margin: 0;">Active</span>
						</label>
					</div>
					
					<div class="aips-slideover-footer" style="margin: 24px -24px -24px; padding: 24px; border-top: 1px solid #e5e7eb;">
						<button type="button" class="aips-btn aips-btn-secondary aips-slideover-close">Cancel</button>
						<button type="submit" class="aips-btn aips-btn-primary">Save Author</button>
					</div>
				</form>
			`;
			
			$('#aips-author-slideover-body').html(html);
		},

		saveAuthor: function (e) {
			e.preventDefault();
			
			const $form = $(e.currentTarget);
			const formData = $form.serialize();
			
			// Disable submit button
			const $submitBtn = $form.find('button[type="submit"]');
			const originalText = $submitBtn.text();
			$submitBtn.prop('disabled', true).text('Saving...');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData + '&action=aips_save_author&nonce=' + aipsAuthorsL10n.nonce,
				success: (response) => {
					if (response.success) {
						this.closeSlideOver();
						this.showSuccess('Author saved successfully');
						// Reload the page to show updated data
						location.reload();
					} else {
						this.showError('Failed to save author: ' + (response.data.message || 'Unknown error'));
						$submitBtn.prop('disabled', false).text(originalText);
					}
				},
				error: () => {
					this.showError('Failed to save author. Please try again.');
					$submitBtn.prop('disabled', false).text(originalText);
				}
			});
		},

		deleteAuthor: function (e) {
			e.preventDefault();
			
			if (!confirm(aipsAuthorsL10n.confirmDelete || 'Are you sure you want to delete this author?')) {
				return;
			}
			
			const authorId = $(e.currentTarget).data('id');
			
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
						this.showSuccess('Author deleted successfully');
						// Remove the card from view
						$(`[data-author-id="${authorId}"]`).closest('.aips-author-card').fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						this.showError('Failed to delete author: ' + (response.data.message || 'Unknown error'));
					}
				},
				error: () => {
					this.showError('Failed to delete author. Please try again.');
				}
			});
		},

		generateTopicsNow: function (e) {
			e.preventDefault();
			
			if (!confirm('Generate topics for this author now?')) {
				return;
			}
			
			const authorId = $(e.currentTarget).data('id');
			const $btn = $(e.currentTarget);
			const originalText = $btn.text();
			
			$btn.prop('disabled', true).text('Generating...');
			
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
						this.showSuccess('Topics generated successfully');
						$btn.prop('disabled', false).text(originalText);
					} else {
						this.showError('Failed to generate topics: ' + (response.data.message || 'Unknown error'));
						$btn.prop('disabled', false).text(originalText);
					}
				},
				error: () => {
					this.showError('Failed to generate topics. Please try again.');
					$btn.prop('disabled', false).text(originalText);
				}
			});
		},

		approveTopic: function (e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('topic-id');
			this.updateTopicStatus(topicId, 'approve');
		},

		rejectTopic: function (e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('topic-id');
			this.updateTopicStatus(topicId, 'reject');
		},

		deleteTopic: function (e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to delete this topic?')) {
				return;
			}
			
			const topicId = $(e.currentTarget).data('topic-id');
			
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
						this.showSuccess('Topic deleted successfully');
						// Remove from topics array
						this.topics = this.topics.filter(t => t.id != topicId);
						// Go back to topics list
						$('#aips-back-to-topics').click();
					} else {
						this.showError('Failed to delete topic: ' + (response.data.message || 'Unknown error'));
					}
				},
				error: () => {
					this.showError('Failed to delete topic. Please try again.');
				}
			});
		},

		updateTopicStatus: function (topicId, action) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_' + action + '_topic',
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId,
					reason: '',
					reason_category: 'other'
				},
				success: (response) => {
					if (response.success) {
						this.showSuccess('Topic ' + action + 'd successfully');
						// Update topic in array
						const topic = this.topics.find(t => t.id == topicId);
						if (topic) {
							topic.status = action === 'approve' ? 'approved' : 'rejected';
						}
						// Go back to topics list
						$('#aips-back-to-topics').click();
					} else {
						this.showError('Failed to ' + action + ' topic: ' + (response.data.message || 'Unknown error'));
					}
				},
				error: () => {
					this.showError('Failed to ' + action + ' topic. Please try again.');
				}
			});
		},

		generatePostNow: function (e) {
			e.preventDefault();
			
			if (!confirm('Generate a post from this topic now?')) {
				return;
			}
			
			const topicId = $(e.currentTarget).data('topic-id');
			const $btn = $(e.currentTarget);
			const originalText = $btn.text();
			
			$btn.prop('disabled', true).text('Generating...');
			
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
						this.showSuccess('Post generated successfully');
						$btn.prop('disabled', false).text(originalText);
					} else {
						this.showError('Failed to generate post: ' + (response.data.message || 'Unknown error'));
						$btn.prop('disabled', false).text(originalText);
					}
				},
				error: () => {
					this.showError('Failed to generate post. Please try again.');
					$btn.prop('disabled', false).text(originalText);
				}
			});
		},

		toggleSelectAllTopics: function (e) {
			const checked = $(e.currentTarget).prop('checked');
			$('.aips-topic-chip-checkbox').prop('checked', checked);
		},

		executeBulkAction: function (e) {
			e.preventDefault();
			
			const action = $('#aips-bulk-action-select').val();
			if (!action) {
				alert('Please select a bulk action');
				return;
			}
			
			const selectedTopics = $('.aips-topic-chip-checkbox:checked').map(function() {
				return $(this).data('topic-id');
			}).get();
			
			if (selectedTopics.length === 0) {
				alert('Please select at least one topic');
				return;
			}
			
			if (!confirm(`Are you sure you want to ${action} ${selectedTopics.length} topic(s)?`)) {
				return;
			}
			
			const actionMap = {
				'approve': 'aips_bulk_approve_topics',
				'reject': 'aips_bulk_reject_topics',
				'delete': 'aips_bulk_delete_topics'
			};
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: actionMap[action],
					nonce: aipsAuthorsL10n.nonce,
					topic_ids: selectedTopics
				},
				success: (response) => {
					if (response.success) {
						this.showSuccess(response.data.message || 'Bulk action completed successfully');
						// Reload topics
						this.loadAuthorTopics(this.currentAuthorId, 'Author');
					} else {
						this.showError('Failed to execute bulk action: ' + (response.data.message || 'Unknown error'));
					}
				},
				error: () => {
					this.showError('Failed to execute bulk action. Please try again.');
				}
			});
		},

		showSuccess: function (message) {
			// Use WordPress admin notices
			const $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
			$('.aips-wrap').prepend($notice);
			setTimeout(() => $notice.fadeOut(), 3000);
		},

		showError: function (message) {
			// Use WordPress admin notices
			const $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
			$('.aips-wrap').prepend($notice);
		},

		escapeHtml: function (text) {
			if (!text) return '';
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, (m) => map[m]);
		}
	};

	// Initialize when document is ready
	$(document).ready(function () {
		if ($('.aips-authors-modern').length > 0) {
			AuthorsModernModule.init();
		}
	});

})(jQuery);
