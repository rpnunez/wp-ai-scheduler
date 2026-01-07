/**
 * Activity Page JavaScript
 *
 * Handles the activity feed, filtering, and modal interactions.
 */
(function($) {
	'use strict';
	
	let currentFilter = 'all';
	let currentPostId = null;
	
	/**
	 * Initialize the activity page.
	 */
	function init() {
		loadActivity();
		bindEvents();
	}
	
	/**
	 * Bind event handlers.
	 */
	function bindEvents() {
		// Filter buttons
		$('.aips-filter-btn').on('click', function() {
			$('.aips-filter-btn').removeClass('active');
			$(this).addClass('active');
			currentFilter = $(this).data('filter');
			loadActivity();
		});
		
		// Activity item click - open modal
		$(document).on('click', '.aips-activity-item', function(e) {
			if ($(e.target).closest('.aips-activity-actions').length) {
				return; // Don't open modal if clicking action buttons
			}
			
			const postId = $(this).data('post-id');
			if (postId) {
				openActivityModal(postId);
			}
		});
		
		// Modal close
		$('.aips-modal-close').on('click', closeModal);
		$(document).on('click', '.aips-modal', function(e) {
			if ($(e.target).hasClass('aips-modal')) {
				closeModal();
			}
		});
		
		// Publish button
		$('#aips-post-publish-btn').on('click', publishPost);
		
		// Quick action buttons in feed
		$(document).on('click', '.aips-quick-publish', function(e) {
			e.stopPropagation();
			const postId = $(this).closest('.aips-activity-item').data('post-id');
			if (postId && confirm(aipsActivityL10n.confirmPublish)) {
				publishPostQuick(postId);
			}
		});
	}
	
	/**
	 * Load activity feed from server.
	 */
	function loadActivity() {
		const $loading = $('.aips-activity-loading');
		const $list = $('.aips-activity-list');
		const $empty = $('.aips-activity-empty');
		
		$loading.show();
		$list.empty().hide();
		$empty.hide();
		
		$.ajax({
			url: aipsActivityL10n.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aips_get_activity',
				nonce: aipsActivityL10n.nonce,
				filter: currentFilter,
				limit: 50
			},
			success: function(response) {
				$loading.hide();
				
				if (response.success && response.data.activities.length > 0) {
					renderActivityList(response.data.activities);
					$list.show();
				} else {
					$empty.show();
				}
			},
			error: function() {
				$loading.hide();
				$list.html('<div class="notice notice-error"><p>' + aipsActivityL10n.loadingError + '</p></div>').show();
			}
		});
	}
	
	/**
	 * Render activity list.
	 */
	function renderActivityList(activities) {
		const $list = $('.aips-activity-list');
		$list.empty();
		
		activities.forEach(function(activity) {
			const $item = $('<div class="aips-activity-item"></div>')
				.attr('data-id', activity.id)
				.attr('data-type', activity.type)
				.attr('data-status', activity.status);
			
			if (activity.post && activity.post.id) {
				$item.attr('data-post-id', activity.post.id);
			}
			
			// Status icon
			const statusClass = getStatusClass(activity.status, activity.type);
			const statusIcon = getStatusIcon(activity.status, activity.type);
			const $icon = $('<div class="aips-activity-icon ' + statusClass + '"><span class="dashicons ' + statusIcon + '"></span></div>');
			
			// Content
			const $content = $('<div class="aips-activity-content"></div>');
			const $message = $('<div class="aips-activity-message"></div>').text(activity.message || getDefaultMessage(activity));
			const $date = $('<div class="aips-activity-date"></div>').text(activity.date_formatted);
			
			$content.append($message, $date);
			
			// Actions
			const $actions = $('<div class="aips-activity-actions"></div>');
			
			if (activity.post) {
				if (activity.post.status === 'draft') {
					$actions.append('<button class="button button-small aips-quick-publish">' + 
						'<?php esc_html_e('Publish', 'ai-post-scheduler'); ?>' + '</button>');
				}
				if (activity.post.edit_url) {
					$actions.append('<a href="' + activity.post.edit_url + '" class="button button-small" target="_blank">' + 
						'<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>' + '</a>');
				}
			}
			
			$item.append($icon, $content, $actions);
			$list.append($item);
		});
	}
	
	/**
	 * Get status CSS class.
	 */
	function getStatusClass(status, type) {
		if (type === 'schedule_failed') {
			return 'status-failed';
		}
		if (status === 'draft') {
			return 'status-draft';
		}
		if (status === 'success') {
			return 'status-success';
		}
		return 'status-info';
	}
	
	/**
	 * Get status icon.
	 */
	function getStatusIcon(status, type) {
		if (type === 'schedule_failed') {
			return 'dashicons-warning';
		}
		if (status === 'draft') {
			return 'dashicons-edit';
		}
		if (status === 'success') {
			return 'dashicons-yes-alt';
		}
		return 'dashicons-info';
	}
	
	/**
	 * Get default message for activity.
	 */
	function getDefaultMessage(activity) {
		if (activity.post && activity.post.title) {
			if (activity.type === 'post_published') {
				return 'Post published: ' + activity.post.title;
			}
			if (activity.type === 'post_draft') {
				return 'Draft saved: ' + activity.post.title;
			}
		}
		if (activity.type === 'schedule_failed' && activity.schedule) {
			return 'Schedule failed: ' + activity.schedule.name;
		}
		return 'Activity event';
	}
	
	/**
	 * Open activity detail modal.
	 */
	function openActivityModal(postId) {
		currentPostId = postId;
		const $modal = $('#aips-activity-modal');
		const $loading = $('.aips-activity-detail-loading');
		const $content = $('.aips-activity-detail-content');
		
		$loading.show();
		$content.hide();
		$modal.fadeIn(200);
		
		$.ajax({
			url: aipsActivityL10n.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aips_get_activity_detail',
				nonce: aipsActivityL10n.nonce,
				post_id: postId
			},
			success: function(response) {
				$loading.hide();
				
				if (response.success) {
					renderPostDetail(response.data);
					$content.show();
				} else {
					$content.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
				}
			},
			error: function() {
				$loading.hide();
				$content.html('<div class="notice notice-error"><p>' + aipsActivityL10n.loadingError + '</p></div>').show();
			}
		});
	}
	
	/**
	 * Render post detail in modal.
	 */
	function renderPostDetail(post) {
		// Status
		$('.aips-post-status').html('<strong>Status:</strong> ' + post.status.charAt(0).toUpperCase() + post.status.slice(1));
		
		// Date and author
		$('.aips-post-date').html('<strong>Date:</strong> ' + post.date);
		$('.aips-post-author').html('<strong>Author:</strong> ' + post.author);
		
		// Featured image
		if (post.featured_image) {
			$('.aips-post-featured-image').html('<img src="' + post.featured_image + '" alt="Featured Image">').show();
		} else {
			$('.aips-post-featured-image').hide();
		}
		
		// Title
		$('.aips-post-title').html('<h2>' + post.title + '</h2>');
		
		// Excerpt
		if (post.excerpt) {
			$('.aips-post-excerpt').html('<div class="aips-post-excerpt-content">' + post.excerpt + '</div>').show();
		} else {
			$('.aips-post-excerpt').hide();
		}
		
		// Content preview (first 500 chars)
		const contentPreview = post.content.length > 500 ? post.content.substring(0, 500) + '...' : post.content;
		$('.aips-post-content').html('<div class="aips-post-content-preview">' + contentPreview + '</div>');
		
		// Categories and tags
		if (post.categories && post.categories.length > 0) {
			$('.aips-post-categories').html('<strong>Categories:</strong> ' + post.categories.join(', ')).show();
		} else {
			$('.aips-post-categories').hide();
		}
		
		if (post.tags && post.tags.length > 0) {
			$('.aips-post-tags').html('<strong>Tags:</strong> ' + post.tags.join(', ')).show();
		} else {
			$('.aips-post-tags').hide();
		}
		
		// Action buttons
		$('#aips-post-edit-link').attr('href', post.edit_url).show();
		
		if (post.status === 'draft') {
			$('#aips-post-publish-btn').show();
			$('#aips-post-view-link').hide();
		} else {
			$('#aips-post-publish-btn').hide();
			$('#aips-post-view-link').attr('href', post.view_url).show();
		}
	}
	
	/**
	 * Publish post from modal.
	 */
	function publishPost() {
		if (!currentPostId) return;
		
		if (!confirm(aipsActivityL10n.confirmPublish)) return;
		
		const $btn = $('#aips-post-publish-btn');
		$btn.prop('disabled', true).text('Publishing...');
		
		$.ajax({
			url: aipsActivityL10n.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aips_publish_draft',
				nonce: aipsActivityL10n.nonce,
				post_id: currentPostId
			},
			success: function(response) {
				if (response.success) {
					alert(aipsActivityL10n.publishSuccess);
					closeModal();
					loadActivity(); // Refresh feed
				} else {
					alert(response.data.message || aipsActivityL10n.publishError);
					$btn.prop('disabled', false).text('<?php esc_html_e('Publish Post', 'ai-post-scheduler'); ?>');
				}
			},
			error: function() {
				alert(aipsActivityL10n.publishError);
				$btn.prop('disabled', false).text('<?php esc_html_e('Publish Post', 'ai-post-scheduler'); ?>');
			}
		});
	}
	
	/**
	 * Publish post quickly from feed.
	 */
	function publishPostQuick(postId) {
		const $item = $('.aips-activity-item[data-post-id="' + postId + '"]');
		const $btn = $item.find('.aips-quick-publish');
		
		$btn.prop('disabled', true).text('Publishing...');
		
		$.ajax({
			url: aipsActivityL10n.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aips_publish_draft',
				nonce: aipsActivityL10n.nonce,
				post_id: postId
			},
			success: function(response) {
				if (response.success) {
					loadActivity(); // Refresh feed
				} else {
					alert(response.data.message || aipsActivityL10n.publishError);
					$btn.prop('disabled', false).text('<?php esc_html_e('Publish', 'ai-post-scheduler'); ?>');
				}
			},
			error: function() {
				alert(aipsActivityL10n.publishError);
				$btn.prop('disabled', false).text('<?php esc_html_e('Publish', 'ai-post-scheduler'); ?>');
			}
		});
	}
	
	/**
	 * Close modal.
	 */
	function closeModal() {
		$('#aips-activity-modal').fadeOut(200);
		currentPostId = null;
	}
	
	// Initialize on document ready
	$(document).ready(init);
	
})(jQuery);
