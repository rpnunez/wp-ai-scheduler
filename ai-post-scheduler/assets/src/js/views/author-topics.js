import Backbone from 'backbone';
import $ from 'jquery';
import { TopicCollection, TopicModel } from '../models/topic';

/**
 * Author Topics View Controller
 */
export const AuthorTopicsView = Backbone.View.extend({
	el: 'body',

	events: {
		// Tabs
		'aips:tabSwitch': 'onTabSwitch',

		// Topic approvals & feedback
		'click .aips-approve-topic': 'approveTopic',
		'click .aips-reject-topic': 'rejectTopic',
		'click .aips-delete-topic': 'deleteTopic',
		'click .aips-edit-topic': 'editTopic',
		'click .aips-save-topic': 'saveTopic',
		'click .aips-cancel-edit-topic': 'cancelEditTopic',
		'click .aips-generate-post-now': 'generatePostNow',
		'click .aips-view-topic-log': 'viewTopicLog',

		// Row actions overflow menu
		'click .aips-row-action-overflow-toggle': 'onRowActionOverflowToggle',
		'click .aips-row-action-menu .aips-row-action-item': 'onRowActionItemClick',

		// Topic details toggle
		'click .aips-topic-expand-btn': 'toggleTopicDetail',
		'click .topic-title-cell': 'onTopicTitleCellClick',

		// Topic Search
		'keyup #aips-topic-search': 'filterTopics',
		'search #aips-topic-search': 'filterTopics',
		'click #aips-topic-search-clear': 'clearTopicSearch',

		// Bulk Actions
		'click .aips-select-all-topics': 'toggleSelectAll',
		'click .aips-select-all-feedback': 'toggleSelectAllFeedback',
		'click .aips-bulk-action-execute': 'executeBulkAction',

		// Post Count & Publishing
		'click .aips-post-count-badge[data-context="author-topic"]': 'viewTopicPosts',
		'click .aips-publish-topic-post': 'publishTopicPost',

		// Feedback submit
		'submit #aips-feedback-form': 'submitFeedback',

		// Window event shims
		'click': 'onDocumentClick',
		'keydown': 'onDocumentKeyDown'
	},

	initialize() {
		this.collection = new TopicCollection();
		this.currentAuthorId = null;
		this.currentTopicPostsTopicId = null;

		// Bind counts updates to custom event on collection
		this.listenTo(this.collection, 'sync:counts', this.updateTopicCounts);

		// Auto-initialize if author ID context is loaded
		if (typeof window.aipsAuthorContext !== 'undefined' && window.aipsAuthorContext.authorId) {
			this.currentAuthorId = window.aipsAuthorContext.authorId;
			this.updateBulkActionDropdown('pending');
			this.loadTopics('pending');
		}
	},

	loadTopics(status) {
		this.showTopicsLoading();
		this.collection.fetch({
			author_id: this.currentAuthorId,
			status: status,
			success: (collection, response) => {
				this.renderTopics(response, status);
				if (status === 'pending') {
					this.renderInlineSimilarityIndicators();
				}
				this.hideTopicsLoading();
			},
			error: () => {
				const errorMsg = (window.aipsAuthorsL10n && window.aipsAuthorsL10n.errorLoadingTopics) || 'Error loading topics.';
				$('#aips-topics-content').html('<p>' + errorMsg + '</p>');
				this.hideTopicsLoading();
			}
		});
	},

	showTopicsLoading() {
		$('#aips-topics-content').hide();
		$('#aips-topics-loading').show();
		$('#aips-author-topics-panel').addClass('aips-topics-loading-active');
	},

	hideTopicsLoading() {
		$('#aips-topics-loading').hide();
		$('#aips-topics-content').show();
		$('#aips-author-topics-panel').removeClass('aips-topics-loading-active');
	},

	renderTopics(topics, status) {
		const l10n = window.aipsAuthorsL10n || {};
		if (!topics || topics.length === 0) {
			$('#aips-topics-content').html('<p>' + (l10n.noTopicsFound || 'No topics found.') + '</p>');
			return;
		}

		let secondaryDateHeaderHtml = '';
		if (status === 'approved') {
			secondaryDateHeaderHtml = '<th class="column-date">' + (l10n.dateApproved || 'Date Approved') + '</th>';
		} else if (status === 'rejected') {
			secondaryDateHeaderHtml = '<th class="column-date">' + (l10n.dateRejected || 'Date Rejected') + '</th>';
		} else if (status === 'posts_generated') {
			secondaryDateHeaderHtml = '<th class="column-date">' + (l10n.datePostGenerated || 'Date Post Generated') + '</th>';
		}

		const dtL10n = {
			today: l10n.dateToday || 'Today',
			yesterday: l10n.dateYesterday || 'Yesterday'
		};

		let rowsHtml = '';
		topics.forEach(topic => {
			const rawReviewedAt = topic.reviewed_at || '';
			const formattedReviewedAt = rawReviewedAt && window.AIPS.DateTime ? window.AIPS.DateTime.formatDateLabel(rawReviewedAt, dtL10n) : rawReviewedAt;

			let detailContentHtml = '';
			if (topic.topic_description) {
				detailContentHtml += window.AIPS.Templates.render('aips-tmpl-topic-detail-item', {
					label: l10n.description || 'Description',
					value: topic.topic_description
				});
			}
			if (topic.topic_rationale) {
				detailContentHtml += window.AIPS.Templates.render('aips-tmpl-topic-detail-item', {
					label: l10n.rationale || 'Rationale',
					value: topic.topic_rationale
				});
			}
			if (topic.reviewed_at && topic.reviewed_by) {
				detailContentHtml += window.AIPS.Templates.render('aips-tmpl-topic-detail-item', {
					label: l10n.reviewed || 'Reviewed',
					value: formattedReviewedAt + ' by User ID ' + topic.reviewed_by
				});
			}
			if (topic.last_feedback) {
				const feedbackAction = topic.last_feedback.action;
				const feedbackLabel = feedbackAction === 'rejected' ? (l10n.reject || 'Rejected') : (l10n.approve || 'Approved');
				const reasonLabel = this.formatFeedbackReasonCategory(feedbackAction, topic.last_feedback.reason_category);

				detailContentHtml += window.AIPS.Templates.render('aips-tmpl-topic-detail-feedback', {
					actionLabel: feedbackLabel,
					actionClass: feedbackAction === 'rejected' ? 'rejected' : 'approved',
					categoryLabel: reasonLabel,
					reasonText: topic.last_feedback.reason || '-'
				});
			}

			// Render inline similarity badge slot for pending status
			let similaritySlotHtml = '';
			if (status === 'pending') {
				similaritySlotHtml = '<div class="aips-topic-similarity-slot" data-topic-id="' + topic.id + '"></div>';
			}

			// Render action controls
			let actionControlsHtml = '';
			if (status === 'pending') {
				actionControlsHtml = window.AIPS.Templates.render('aips-tmpl-topic-actions-pending', {
					id: topic.id,
					approveLabel: l10n.approve || 'Approve',
					rejectLabel: l10n.reject || 'Reject'
				});
			} else if (status === 'approved') {
				actionControlsHtml = window.AIPS.Templates.render('aips-tmpl-topic-actions-approved', {
					id: topic.id,
					generatePostLabel: l10n.generatePost || 'Generate Post',
					rejectLabel: l10n.reject || 'Reject'
				});
			} else if (status === 'rejected') {
				actionControlsHtml = window.AIPS.Templates.render('aips-tmpl-topic-actions-rejected', {
					id: topic.id,
					approveLabel: l10n.approve || 'Approve'
				});
			}

			// Add detail rows for non-pending with descriptions
			let rowDetailHtml = '';
			if (detailContentHtml) {
				rowDetailHtml = window.AIPS.Templates.render('aips-tmpl-topic-detail-section', {
					colSpan: (status === 'pending') ? 4 : 5,
					content: detailContentHtml
				});
			}

			// Format post count badge
			let postBadgeHtml = '';
			if (topic.posts_count > 0) {
				postBadgeHtml = '<span class="aips-post-count-badge" data-topic-id="' + topic.id + '" data-context="author-topic" title="' + (l10n.viewPosts || 'View posts generated from this topic') + '">' +
					topic.posts_count + '</span>';
			}

			rowsHtml += window.AIPS.Templates.render('aips-tmpl-topic-row', {
				id: topic.id,
				title: topic.topic_title,
				similaritySlot: similaritySlotHtml,
				postBadge: postBadgeHtml,
				secondaryDate: (status === 'pending') ? '' : '<td>' + formattedReviewedAt + '</td>',
				actions: actionControlsHtml,
				detailSection: rowDetailHtml,
				editPlaceholder: l10n.topicTitleRequired || 'Title required.'
			});
		});

		const tableHtml = window.AIPS.Templates.render('aips-tmpl-topics-table', {
			titleLabel: l10n.topicTitle || 'Topic Title',
			secondaryDateHeader: secondaryDateHeaderHtml,
			actionsLabel: l10n.actions || 'Actions',
			rows: rowsHtml
		});

		$('#aips-topics-content').html(tableHtml);
	},

	updateTopicCounts(counts) {
		const pending = counts.pending || 0;
		const approved = counts.approved || 0;
		const rejected = counts.rejected || 0;
		const postsGenerated = counts.posts_generated || 0;
		const total = pending + approved + rejected + postsGenerated;

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
		$('#stat-generated-count').text(postsGenerated);
	},

	updateBulkActionDropdown(status) {
		const $select = $('#aips-bulk-action-select');
		if (!$select.length) return;

		$select.find('option').not('[value=""]').remove();
		const l10n = window.aipsAuthorsL10n || {};

		if (status === 'pending') {
			$select.append(
				'<option value="approve">' + (l10n.approveSelected || 'Approve Selected') + '</option>' +
				'<option value="reject">' + (l10n.rejectSelected || 'Reject Selected') + '</option>' +
				'<option value="delete">' + (l10n.deleteSelected || 'Delete Selected') + '</option>'
			);
		} else if (status === 'approved') {
			$select.append(
				'<option value="generate_posts">' + (l10n.generatePostsSelected || 'Generate Posts') + '</option>' +
				'<option value="reject">' + (l10n.rejectSelected || 'Reject Selected') + '</option>' +
				'<option value="delete">' + (l10n.deleteSelected || 'Delete Selected') + '</option>'
			);
		} else if (status === 'rejected') {
			$select.append(
				'<option value="approve">' + (l10n.approveSelected || 'Approve Selected') + '</option>' +
				'<option value="delete">' + (l10n.deleteSelected || 'Delete Selected') + '</option>'
			);
		} else {
			$select.append('<option value="delete">' + (l10n.deleteSelected || 'Delete Selected') + '</option>');
		}
	},

	onTabSwitch(e, status) {
		if (!$('#aips-topics-content').length) return;

		// Reset search
		$('#aips-topic-search').val('');
		$('#aips-topic-search-clear').hide();

		if (status === 'feedback') {
			this.showTopicsLoading();
			this.loadFeedback();
		} else {
			this.loadTopics(status);
		}

		this.updateBulkActionDropdown(status);
	},

	loadFeedback() {
		if (!this.currentAuthorId) {
			$('#aips-topics-content').html('<p>No author selected.</p>');
			this.hideTopicsLoading();
			return;
		}

		$('#aips-topics-content').html('<p>' + ((window.aipsAuthorsL10n && window.aipsAuthorsL10n.loading) || 'Loading...') + '</p>');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_author_feedback',
				nonce: (window.aipsAuthorsL10n && window.aipsAuthorsL10n.nonce) || '',
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

	renderFeedback(feedback) {
		const l10n = window.aipsAuthorsL10n || {};
		if (feedback.length === 0) {
			$('#aips-topics-content').html('<p>' + (l10n.noFeedbackYet || 'No feedback yet.') + '</p>');
			return;
		}

		let rowsHtml = '';
		feedback.forEach(item => {
			rowsHtml += window.AIPS.Templates.render('aips-tmpl-feedback-row', {
				id: item.id,
				topicTitle: item.topic_title || 'N/A',
				action: item.action,
				reason: item.reason || '-',
				userName: item.user_name || 'Unknown',
				date: item.created_at
			});
		});

		const tableHtml = window.AIPS.Templates.render('aips-tmpl-feedback-table', {
			topicLabel: l10n.topic || 'Topic',
			actionLabel: l10n.action || 'Action',
			reasonLabel: l10n.reason || 'Reason',
			userLabel: l10n.user || 'User',
			dateLabel: l10n.date || 'Date',
			rows: rowsHtml
		});

		$('#aips-topics-content').html(tableHtml);
	},

	approveTopic(e) {
		e.preventDefault();
		const topicId = $(e.currentTarget).data('id');
		const l10n = window.aipsAuthorsL10n || {};

		$('#feedback_topic_id').val(topicId);
		$('#feedback_action').val('approve');
		$('#aips-feedback-modal-title').text(l10n.approveTopicTitle || 'Approve Topic');
		$('#feedback_reason').attr('placeholder', l10n.approveReasonPlaceholder || 'Why are you approving this topic?');
		$('#feedback-submit-btn').text(l10n.approve || 'Approve');
		this.populateCategoryOptions('approve');
		$('#aips-feedback-modal').fadeIn();
	},

	rejectTopic(e) {
		e.preventDefault();
		const topicId = $(e.currentTarget).data('id');
		const l10n = window.aipsAuthorsL10n || {};

		$('#feedback_topic_id').val(topicId);
		$('#feedback_action').val('reject');
		$('#aips-feedback-modal-title').text(l10n.rejectTopicTitle || 'Reject Topic');
		$('#feedback_reason').attr('placeholder', l10n.rejectReasonPlaceholder || 'Why are you rejecting this topic?');
		$('#feedback-submit-btn').text(l10n.reject || 'Reject');
		this.populateCategoryOptions('reject');
		$('#aips-feedback-modal').fadeIn();
	},

	populateCategoryOptions(action) {
		const $select = $('#feedback_reason_category');
		if (!$select.length) return;

		$select.empty();
		const l10n = window.aipsAuthorsL10n || {};
		const categories = action === 'approve' ? (l10n.approvalCategories || {}) : (l10n.rejectionCategories || {});

		Object.keys(categories).forEach(key => {
			$select.append('<option value="' + key + '">' + categories[key] + '</option>');
		});
	},

	submitFeedback(e) {
		e.preventDefault();
		const topicId = $('#feedback_topic_id').val();
		const action = $('#feedback_action').val();
		const reason = $('#feedback_reason').val();
		const reasonCategory = $('#feedback_reason_category').val() || 'other';

		const ajaxAction = action === 'approve' ? 'aips_approve_topic' : 'aips_reject_topic';
		const l10n = window.aipsAuthorsL10n || {};

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: ajaxAction,
				nonce: l10n.nonce || '',
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
					const msg = response.data && response.data.message ? response.data.message : (l10n.errorSaving || 'Error saving feedback.');
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(msg, 'error');
					}
				}
			},
			error: () => {
				const msg = action === 'approve' ? (l10n.errorApproving || 'Error approving topic.') : (l10n.errorRejecting || 'Error rejecting topic.');
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(msg, 'error');
				}
			}
		});
	},

	deleteTopic(e) {
		e.preventDefault();
		const topicId = $(e.currentTarget).data('id');
		const l10n = window.aipsAuthorsL10n || {};

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(l10n.confirmDeleteTopic || 'Are you sure you want to delete this topic?', 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const topic = new TopicModel({ id: topicId });
						topic.destroy({
							success: () => {
								const activeTab = $('.aips-tab-link.active').data('tab') || 'pending';
								this.loadTopics(activeTab);
							},
							error: (model, err) => {
								const msg = err && err.message ? err.message : (l10n.errorDeletingTopic || 'Error deleting topic.');
								window.AIPS.Utilities.showToast(msg, 'error');
							}
						});
					}
				}
			]);
		}
	},

	editTopic(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const $row = $btn.closest('tr');
		const $titleSpan = $row.find('.topic-title');
		const $titleInput = $row.find('.topic-title-edit');
		const $rowActions = $row.find('.cell-actions');
		const l10n = window.aipsAuthorsL10n || {};

		$titleSpan.hide();
		$titleInput.show().focus();

		this.closeAllRowActionMenus();
		$btn.hide();
		$rowActions.hide();
		$row.find('.topic-actions').append(
			'<button type="button" class="button aips-save-topic">' + (l10n.save || 'Save') + '</button> ' +
			'<button type="button" class="button aips-cancel-edit-topic">' + (l10n.cancel || 'Cancel') + '</button>'
		);
	},

	saveTopic(e) {
		e.preventDefault();
		const $row = $(e.currentTarget).closest('tr');
		const topicId = $row.data('topic-id');
		const newTitle = $row.find('.topic-title-edit').val();
		const l10n = window.aipsAuthorsL10n || {};

		if (!newTitle.trim()) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.topicTitleRequired || 'Title required.', 'warning');
			}
			return;
		}

		const topic = new TopicModel({ id: topicId, topic_title: newTitle });
		topic.save(null, {
			success: () => {
				$row.find('.topic-title').text(newTitle).show();
				$row.find('.topic-title-edit').hide();
				$row.find('.aips-save-topic, .aips-cancel-edit-topic').remove();
				$row.find('.cell-actions, .aips-edit-topic').show();
			},
			error: (model, err) => {
				const msg = err && err.message ? err.message : (l10n.errorSaving || 'Error saving topic.');
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(msg, 'error');
				}
			}
		});
	},

	cancelEditTopic(e) {
		e.preventDefault();
		const $row = $(e.currentTarget).closest('tr');
		$row.find('.topic-title').show();
		$row.find('.topic-title-edit').hide();
		$row.find('.aips-save-topic, .aips-cancel-edit-topic').remove();
		$row.find('.cell-actions, .aips-edit-topic').show();
	},

	generatePostNow(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const topicId = $button.data('id');
		const l10n = window.aipsAuthorsL10n || {};

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(l10n.confirmGeneratePost || 'Generate post now from this topic?', 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, generate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						window.AIPS.Utilities.setButtonLoading($button, l10n.generating || 'Generating...');
						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_generate_post_from_topic',
								nonce: l10n.nonce || '',
								topic_id: topicId
							},
							success: (response) => {
								if (response.success) {
									window.AIPS.Utilities.showToast(response.data.message || 'Post generated successfully.', 'success');
									this.loadTopics('approved');
								} else {
									window.AIPS.Utilities.showToast(response.data.message || 'Error generating post.', 'error');
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast(l10n.errorGenerating || 'Error generating post.', 'error');
							},
							complete: () => {
								window.AIPS.Utilities.resetButton($button);
							}
						});
					}
				}
			]);
		}
	},

	viewTopicLog(e) {
		e.preventDefault();
		const topicId = $(e.currentTarget).data('id');
		const l10n = window.aipsAuthorsL10n || {};

		$('#aips-topic-logs-content').html('<p>' + (l10n.logViewerLoading || 'Loading logs...') + '</p>');
		$('#aips-topic-logs-modal').fadeIn();

		this.loadTopicLogs(topicId);
	},

	loadTopicLogs(topicId) {
		const l10n = window.aipsAuthorsL10n || {};
		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_topic_logs',
				nonce: l10n.nonce || '',
				topic_id: topicId
			},
			success: (response) => {
				if (response.success) {
					this.renderTopicLogs(response.data.logs);
				} else {
					$('#aips-topic-logs-content').html('<p>' + (response.data && response.data.message ? response.data.message : (l10n.logViewerError || 'Error loading logs.')) + '</p>');
				}
			},
			error: () => {
				$('#aips-topic-logs-content').html('<p>' + (l10n.logViewerError || 'Error loading logs.') + '</p>');
			}
		});
	},

	renderTopicLogs(logs) {
		const l10n = window.aipsAuthorsL10n || {};
		if (!logs || logs.length === 0) {
			$('#aips-topic-logs-content').html('<p>' + (l10n.noLogsFound || 'No logs found.') + '</p>');
			return;
		}

		let rowsHtml = '';
		logs.forEach(log => {
			rowsHtml += window.AIPS.Templates.render('aips-tmpl-topic-log-row', {
				action: log.action,
				userName: log.user_name || 'System',
				date: log.created_at,
				notes: log.notes || '-'
			});
		});

		const tableHtml = window.AIPS.Templates.render('aips-tmpl-topic-logs-table', {
			actionLabel: l10n.logAction || 'Action',
			userLabel: l10n.logUser || 'User',
			dateLabel: l10n.logDate || 'Date',
			detailsLabel: l10n.logDetails || 'Details',
			rows: rowsHtml
		});

		$('#aips-topic-logs-content').html(tableHtml);
	},

	viewTopicPosts(e) {
		e.preventDefault();
		e.stopPropagation();

		const topicId = $(e.currentTarget).data('topic-id');
		this.currentTopicPostsTopicId = topicId;
		const l10n = window.aipsAuthorsL10n || {};

		$('#aips-topic-posts-content').html('<p>' + (l10n.loadingPosts || 'Loading posts...') + '</p>');
		$('#aips-topic-posts-modal').fadeIn();

		this.loadTopicPosts(topicId);
	},

	loadTopicPosts(topicId) {
		const l10n = window.aipsAuthorsL10n || {};
		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_topic_posts',
				nonce: l10n.nonce || '',
				topic_id: topicId
			},
			success: (response) => {
				if (response.success && response.data.posts) {
					this.renderTopicPosts(response.data.posts);
				} else {
					$('#aips-topic-posts-content').html('<p>' + (l10n.noPostsFound || 'No posts found.') + '</p>');
				}
			},
			error: () => {
				$('#aips-topic-posts-content').html('<p>' + (l10n.errorLoadingPosts || 'Error loading posts.') + '</p>');
			}
		});
	},

	renderTopicPosts(posts) {
		const l10n = window.aipsAuthorsL10n || {};
		if (!posts || posts.length === 0) {
			$('#aips-topic-posts-content').html('<p>' + (l10n.noPostsFound || 'No posts found.') + '</p>');
			return;
		}

		let rowsHtml = '';
		posts.forEach(post => {
			let imageHtml = window.AIPS.Templates.render('aips-tmpl-topic-post-image-placeholder', {});
			if (post.featured_image_url) {
				imageHtml = window.AIPS.Templates.render('aips-tmpl-topic-post-image', {
					src: post.featured_image_url
				});
			}

			let actionLinkHtml = window.AIPS.Templates.render('aips-tmpl-topic-post-action-link', {
				url: post.edit_url,
				label: l10n.edit || 'Edit'
			});

			if (post.status === 'draft') {
				actionLinkHtml += ' | ' + window.AIPS.Templates.render('aips-tmpl-topic-post-action-publish', {
					id: post.id,
					label: l10n.publish || 'Publish'
				});
			}

			rowsHtml += window.AIPS.Templates.render('aips-tmpl-topic-post-item', {
				image: imageHtml,
				title: post.title,
				status: post.status,
				statusLabel: post.status.toUpperCase(),
				date: post.date,
				actions: actionLinkHtml
			});
		});

		const listHtml = window.AIPS.Templates.render('aips-tmpl-topic-posts-list', {
			rows: rowsHtml
		});

		$('#aips-topic-posts-content').html(listHtml);
	},

	publishTopicPost(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const postId = $button.data('id');
		const l10n = window.aipsAuthorsL10n || {};

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(l10n.confirmPublishPost || 'Are you sure you want to publish this post?', 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, publish',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						window.AIPS.Utilities.setButtonLoading($button, l10n.publishing || 'Publishing...');
						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_publish_post',
								nonce: l10n.nonce || '',
								post_id: postId
							},
							success: (response) => {
								if (response.success) {
									window.AIPS.Utilities.showToast(response.data.message || 'Post published successfully.', 'success');
									if (this.currentTopicPostsTopicId) {
										this.loadTopicPosts(this.currentTopicPostsTopicId);
									}
									const activeTab = $('.aips-tab-link.active').data('tab') || 'pending';
									this.loadTopics(activeTab);
								} else {
									window.AIPS.Utilities.showToast(response.data.message || 'Error publishing post.', 'error');
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast(l10n.errorPublishing || 'Error publishing post.', 'error');
							},
							complete: () => {
								window.AIPS.Utilities.resetButton($button);
							}
						});
					}
				}
			]);
		}
	},

	toggleSelectAll(e) {
		const isChecked = $(e.currentTarget).prop('checked');
		$('.aips-topic-checkbox').prop('checked', isChecked);
	},

	toggleSelectAllFeedback(e) {
		const isChecked = $(e.currentTarget).prop('checked');
		$('.aips-feedback-checkbox').prop('checked', isChecked);
	},

	executeBulkAction(e) {
		e.preventDefault();
		const action = $('#aips-bulk-action-select').val();
		const topicIds = $('.aips-topic-checkbox:checked').map(function() {
			return parseInt($(this).val(), 10);
		}).get().filter(id => Number.isInteger(id) && id > 0);

		const l10n = window.aipsAuthorsL10n || {};

		if (!action) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.selectBulkAction || 'Please select a bulk action.', 'warning');
			}
			return;
		}

		if (topicIds.length === 0) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.noTopicsSelected || 'Please select at least one topic.', 'warning');
			}
			return;
		}

		if (action === 'approve') {
			this.bulkApproveTopics(topicIds);
		} else if (action === 'reject') {
			this.bulkRejectTopics(topicIds);
		} else if (action === 'delete') {
			this.bulkDeleteTopics(topicIds);
		} else if (action === 'generate_posts') {
			this.bulkGeneratePosts(topicIds);
		}
	},

	bulkApproveTopics(topicIds) {
		const l10n = window.aipsAuthorsL10n || {};
		const msg = (l10n.confirmApproveBulk || 'Approve %d selected topic(s)?').replace('%d', topicIds.length);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(msg, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, approve',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const requests = topicIds.map(id => $.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_approve_topic',
								nonce: l10n.nonce || '',
								topic_id: id,
								reason: 'Bulk approved',
								reason_category: 'approved',
								source: 'manual_ui'
							}
						}));

						Promise.allSettled(requests).then(results => {
							const successCount = results.filter(r => r.status === 'fulfilled' && r.value && r.value.success).length;
							if (successCount > 0) {
								window.AIPS.Utilities.showToast((l10n.topicsApprovedBulk || '%d topic(s) approved.').replace('%d', successCount), 'success');
								this.loadTopics('pending');
							} else {
								window.AIPS.Utilities.showToast(l10n.errorApproving || 'Error approving topics.', 'error');
							}
						});
					}
				}
			]);
		}
	},

	bulkRejectTopics(topicIds) {
		const l10n = window.aipsAuthorsL10n || {};
		const msg = (l10n.confirmRejectBulk || 'Reject %d selected topic(s)?').replace('%d', topicIds.length);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(msg, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, reject',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const requests = topicIds.map(id => $.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_reject_topic',
								nonce: l10n.nonce || '',
								topic_id: id,
								reason: 'Bulk rejected',
								reason_category: 'rejected',
								source: 'manual_ui'
							}
						}));

						Promise.allSettled(requests).then(results => {
							const successCount = results.filter(r => r.status === 'fulfilled' && r.value && r.value.success).length;
							if (successCount > 0) {
								window.AIPS.Utilities.showToast((l10n.topicsRejectedBulk || '%d topic(s) rejected.').replace('%d', successCount), 'success');
								this.loadTopics('pending');
							} else {
								window.AIPS.Utilities.showToast(l10n.errorRejecting || 'Error rejecting topics.', 'error');
							}
						});
					}
				}
			]);
		}
	},

	bulkDeleteTopics(topicIds) {
		const l10n = window.aipsAuthorsL10n || {};
		const msg = (l10n.confirmDeleteBulk || 'Delete %d selected topic(s)?').replace('%d', topicIds.length);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(msg, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const requests = topicIds.map(id => $.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_delete_topic',
								nonce: l10n.nonce || '',
								topic_id: id
							}
						}));

						Promise.allSettled(requests).then(results => {
							const successCount = results.filter(r => r.status === 'fulfilled' && r.value && r.value.success).length;
							if (successCount > 0) {
								window.AIPS.Utilities.showToast((l10n.topicDeletedBulk || '%d topic(s) deleted.').replace('%d', successCount), 'success');
								const activeTab = $('.aips-tab-link.active').data('tab') || 'pending';
								this.loadTopics(activeTab);
							} else {
								window.AIPS.Utilities.showToast(l10n.errorDeleting || 'Error deleting topics.', 'error');
							}
						});
					}
				}
			]);
		}
	},

	bulkGeneratePosts(topicIds) {
		const l10n = window.aipsAuthorsL10n || {};
		const msg = (l10n.confirmGeneratePostsBulk || 'Generate posts for %d selected topic(s)?').replace('%d', topicIds.length);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(msg, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, generate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const requests = topicIds.map(id => $.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_generate_post_from_topic',
								nonce: l10n.nonce || '',
								topic_id: id
							}
						}));

						Promise.allSettled(requests).then(results => {
							const successCount = results.filter(r => r.status === 'fulfilled' && r.value && r.value.success).length;
							if (successCount > 0) {
								window.AIPS.Utilities.showToast((l10n.postsGeneratedBulk || '%d post(s) queued for generation.').replace('%d', successCount), 'success');
								this.loadTopics('approved');
							} else {
								window.AIPS.Utilities.showToast(l10n.errorGenerating || 'Error generating posts.', 'error');
							}
						});
					}
				}
			]);
		}
	},

	filterTopics() {
		const query = $('#aips-topic-search').val().toLowerCase();
		const $clear = $('#aips-topic-search-clear');

		if (query) {
			$clear.show();
		} else {
			$clear.hide();
		}

		$('.aips-table-row').each(function() {
			const $row = $(this);
			const titleText = $row.find('.topic-title').text().toLowerCase();
			if (titleText.includes(query)) {
				$row.show();
			} else {
				$row.hide();
				// Also hide detail row if open
				const topicId = $row.data('topic-id');
				$('.aips-detail-row[data-topic-id="' + topicId + '"]').hide();
			}
		});
	},

	clearTopicSearch(e) {
		e.preventDefault();
		$('#aips-topic-search').val('');
		this.filterTopics();
	},

	toggleTopicDetail(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const $row = $btn.closest('tr');
		const topicId = $row.data('topic-id');
		const $detailRow = $('.aips-detail-row[data-topic-id="' + topicId + '"]');

		if ($detailRow.length) {
			const isVisible = $detailRow.is(':visible');
			$detailRow.toggle(!isVisible);
			$btn.find('.dashicons').toggleClass('dashicons-arrow-right-alt2', isVisible).toggleClass('dashicons-arrow-down-alt2', !isVisible);
		}
	},

	onTopicTitleCellClick(e) {
		// Only trigger toggle if we didn't click inside an input or button
		if ($(e.target).closest('input, button, a, span.aips-post-count-badge').length) {
			return;
		}
		const $row = $(e.currentTarget).closest('tr');
		$row.find('.aips-topic-expand-btn').trigger('click');
	},

	renderInlineSimilarityIndicators() {
		if (!this.currentAuthorId) return;

		$('.aips-topic-similarity-slot').empty();

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_suggest_related_topics',
				nonce: (window.aipsAuthorsL10n && window.aipsAuthorsL10n.nonce) || '',
				author_id: this.currentAuthorId,
				limit: 5
			},
			success: (response) => {
				if (!response.success || !response.data || !Array.isArray(response.data.suggestions)) {
					return;
				}

				response.data.suggestions.forEach((item) => {
					const topicId = parseInt(item.topic_id, 10);
					const rawScore = typeof item.similarity_score === 'number' ? item.similarity_score : parseFloat(item.similarity_score);
					const score = Number.isFinite(rawScore) ? Math.round(rawScore * 100) : 0;

					if (!topicId || score <= 0) return;

					const badgeClass = this.getSimilarityBadgeClass(score);
					const label = (window.aipsAuthorsL10n && window.aipsAuthorsL10n.similarityLabel || 'Similarity') + ': ' + score + '%';
					const $slot = $('.aips-topic-similarity-slot[data-topic-id="' + topicId + '"]');

					if ($slot.length) {
						$slot.html('<span class="aips-topic-similarity-badge ' + badgeClass + '" title="' + label + '"><span class="dashicons dashicons-info" aria-hidden="true"></span> ' + label + '</span>');
					}
				});
			}
		});
	},

	getSimilarityBadgeClass(scorePercent) {
		if (scorePercent > 75) return 'aips-topic-similarity-high';
		if (scorePercent > 50) return 'aips-topic-similarity-medium';
		return 'aips-topic-similarity-low';
	},

	formatFeedbackReasonCategory(action, category) {
		const l10n = window.aipsAuthorsL10n || {};
		const categories = action === 'rejected'
			? (l10n.rejectionCategories || {})
			: (l10n.approvalCategories || {});
		return categories[category] || category || '';
	},

	onRowActionOverflowToggle(e) {
		e.preventDefault();
		e.stopPropagation();

		const $toggle = $(e.currentTarget);
		const menuId = $toggle.attr('aria-controls');
		const $menu = menuId ? $('#' + menuId) : $();

		if (!$menu.length) return;

		const isExpanded = $toggle.attr('aria-expanded') === 'true';
		this.closeAllRowActionMenus();

		if (!isExpanded) {
			$toggle.attr('aria-expanded', 'true');
			$menu.prop('hidden', false);
		}
	},

	onRowActionItemClick(e) {
		e.preventDefault();
		this.closeAllRowActionMenus();
	},

	onDocumentClick(e) {
		if ($(e.target).closest('.aips-row-action-group, .aips-row-action-menu').length) {
			return;
		}
		this.closeAllRowActionMenus();
	},

	onDocumentKeyDown(e) {
		if (e.key === 'Escape') {
			this.closeAllRowActionMenus();
			$('.aips-modal').fadeOut();
		}
	},

	closeAllRowActionMenus() {
		$('.aips-row-action-overflow-toggle[aria-expanded="true"]').attr('aria-expanded', 'false');
		$('.aips-row-action-menu').prop('hidden', true);
	}
});
