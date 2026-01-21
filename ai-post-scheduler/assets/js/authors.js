/**
 * Authors Management JavaScript
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

(function($) {
	'use strict';

	// Authors Module
	const AuthorsModule = {
		currentAuthorId: null,
		
		init: function() {
			this.bindEvents();
		},
		
		bindEvents: function() {
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
		},
		
		openAddModal: function(e) {
			e.preventDefault();
			$('#aips-author-modal-title').text(aipsAuthorsL10n.addNewAuthor);
			$('#aips-author-form')[0].reset();
			$('#author_id').val('');
			$('#aips-author-modal').fadeIn();
		},
		
		editAuthor: function(e) {
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
						alert(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorLoading);
						$('#aips-author-modal').fadeOut();
					}
				},
				error: () => {
					alert(aipsAuthorsL10n.errorLoading);
					$('#aips-author-modal').fadeOut();
				}
			});
		},
		
		saveAuthor: function(e) {
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
						alert(response.data.message || aipsAuthorsL10n.authorSaved);
						location.reload();
					} else {
						alert(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorSaving);
					}
				},
				error: () => {
					alert(aipsAuthorsL10n.errorSaving);
				},
				complete: () => {
					$submitBtn.prop('disabled', false).text(aipsAuthorsL10n.saveAuthor);
				}
			});
		},
		
		deleteAuthor: function(e) {
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
						alert(response.data.message || aipsAuthorsL10n.authorDeleted);
						location.reload();
					} else {
						alert(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorDeleting);
					}
				},
				error: () => {
					alert(aipsAuthorsL10n.errorDeleting);
				}
			});
		},
		
		generateTopicsNow: function(e) {
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
						alert(response.data.message || aipsAuthorsL10n.topicsGenerated);
						location.reload();
					} else {
						alert(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorGenerating);
					}
				},
				error: () => {
					alert(aipsAuthorsL10n.errorGenerating);
				},
				complete: () => {
					$btn.prop('disabled', false).text(aipsAuthorsL10n.generateTopicsNow);
				}
			});
		},
		
		viewTopics: function(e) {
			e.preventDefault();
			const authorId = $(e.currentTarget).data('id');
			this.currentAuthorId = authorId;
			
			$('#aips-topics-content').html('<p>' + aipsAuthorsL10n.loadingTopics + '</p>');
			$('#aips-topics-modal').fadeIn();
			
			this.loadTopics('pending');
		},
		
		loadTopics: function(status) {
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
					} else {
						$('#aips-topics-content').html('<p>' + (response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorLoadingTopics) + '</p>');
					}
				},
				error: () => {
					$('#aips-topics-content').html('<p>' + aipsAuthorsL10n.errorLoadingTopics + '</p>');
				}
			});
		},
		
		renderTopics: function(topics, status) {
			if (!topics || topics.length === 0) {
				$('#aips-topics-content').html('<p>' + aipsAuthorsL10n.noTopicsFound + '</p>');
				return;
			}
			
			let html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';
			html += '<th>' + aipsAuthorsL10n.topicTitle + '</th>';
			html += '<th>' + aipsAuthorsL10n.generatedAt + '</th>';
			html += '<th>' + aipsAuthorsL10n.actions + '</th>';
			html += '</tr></thead><tbody>';
			
			topics.forEach(topic => {
				html += '<tr data-topic-id="' + topic.id + '">';
				html += '<td class="topic-title-cell"><span class="topic-title">' + this.escapeHtml(topic.topic_title) + '</span>';
				html += '<input type="text" class="topic-title-edit" style="display:none;" value="' + this.escapeHtml(topic.topic_title) + '"></td>';
				html += '<td>' + topic.generated_at + '</td>';
				html += '<td class="topic-actions">';
				
				// Actions based on status
				if (status === 'pending') {
					html += '<button class="button aips-approve-topic" data-id="' + topic.id + '">' + aipsAuthorsL10n.approve + '</button> ';
					html += '<button class="button aips-reject-topic" data-id="' + topic.id + '">' + aipsAuthorsL10n.reject + '</button> ';
				} else if (status === 'approved') {
					html += '<button class="button aips-generate-post-now" data-id="' + topic.id + '">' + aipsAuthorsL10n.generatePostNow + '</button> ';
				}
				
				html += '<button class="button aips-edit-topic" data-id="' + topic.id + '">' + aipsAuthorsL10n.edit + '</button> ';
				html += '<button class="button aips-delete-topic" data-id="' + topic.id + '">' + aipsAuthorsL10n.delete + '</button>';
				html += '</td></tr>';
			});
			
			html += '</tbody></table>';
			$('#aips-topics-content').html(html);
		},
		
		updateTopicCounts: function(counts) {
			$('#pending-count').text(counts.pending || 0);
			$('#approved-count').text(counts.approved || 0);
			$('#rejected-count').text(counts.rejected || 0);
		},
		
		switchTab: function(e) {
			e.preventDefault();
			const $tab = $(e.currentTarget);
			const status = $tab.data('tab');
			
			$('.aips-tab-link').removeClass('active');
			$tab.addClass('active');
			
			this.loadTopics(status);
		},
		
		approveTopic: function(e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('id');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_approve_topic',
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId
				},
				success: (response) => {
					if (response.success) {
						this.loadTopics('pending');
					} else {
						alert(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorApproving);
					}
				},
				error: () => {
					alert(aipsAuthorsL10n.errorApproving);
				}
			});
		},
		
		rejectTopic: function(e) {
			e.preventDefault();
			const topicId = $(e.currentTarget).data('id');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_reject_topic',
					nonce: aipsAuthorsL10n.nonce,
					topic_id: topicId
				},
				success: (response) => {
					if (response.success) {
						this.loadTopics('pending');
					} else {
						alert(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorRejecting);
					}
				},
				error: () => {
					alert(aipsAuthorsL10n.errorRejecting);
				}
			});
		},
		
		deleteTopic: function(e) {
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
						alert(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorDeletingTopic);
					}
				},
				error: () => {
					alert(aipsAuthorsL10n.errorDeletingTopic);
				}
			});
		},
		
		editTopic: function(e) {
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
		
		saveTopic: function(e) {
			e.preventDefault();
			const $row = $(e.currentTarget).closest('tr');
			const topicId = $row.data('topic-id');
			const newTitle = $row.find('.topic-title-edit').val();
			
			if (!newTitle.trim()) {
				alert(aipsAuthorsL10n.topicTitleRequired);
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
						alert(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorSavingTopic);
					}
				},
				error: () => {
					alert(aipsAuthorsL10n.errorSavingTopic);
				}
			});
		},
		
		cancelEditTopic: function(e) {
			e.preventDefault();
			const $row = $(e.currentTarget).closest('tr');
			$row.find('.topic-title').show();
			$row.find('.topic-title-edit').hide();
			$row.find('.aips-edit-topic').show();
			$row.find('.aips-save-topic, .aips-cancel-edit-topic').remove();
		},
		
		generatePostNow: function(e) {
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
						alert(aipsAuthorsL10n.postGenerated);
						const activeTab = $('.aips-tab-link.active').data('tab');
						this.loadTopics(activeTab);
					} else {
						alert(response.data && response.data.message ? response.data.message : aipsAuthorsL10n.errorGeneratingPost);
						$btn.prop('disabled', false).text(aipsAuthorsL10n.generatePostNow);
					}
				},
				error: () => {
					alert(aipsAuthorsL10n.errorGeneratingPost);
					$btn.prop('disabled', false).text(aipsAuthorsL10n.generatePostNow);
				}
			});
		},
		
		viewTopicLog: function(e) {
			e.preventDefault();
			// TODO: Implement log viewing modal
			alert('View log feature coming soon');
		},
		
		closeModals: function(e) {
			e.preventDefault();
			$('.aips-modal').fadeOut();
		},
		
		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, m => map[m]);
		}
	};
	
	// Initialize when document is ready
	$(document).ready(function() {
		AuthorsModule.init();
	});

})(jQuery);
