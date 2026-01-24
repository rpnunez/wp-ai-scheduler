/**
 * Activity Page JavaScript
 *
 * Handles the activity feed, filtering, and modal interactions.
 */
(function($) {
	'use strict';
	
	// Ensure AIPS object exists
	window.AIPS = window.AIPS || {};
	
	// Extend AIPS with Activity functionality
	Object.assign(window.AIPS, {
		
		currentActivityFilter: 'all',
		currentActivityPostId: null,
		currentActivitySearch: '',
		
		/**
		 * Load activity feed from server.
		 */
		loadActivity: function() {
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
					filter: window.AIPS.currentActivityFilter,
					search: window.AIPS.currentActivitySearch,
					limit: 50
				},
				success: function(response) {
					$loading.hide();
					
					if (response.success && response.data.activities.length > 0) {
						window.AIPS.renderActivityList(response.data.activities);
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
		},
		
		/**
		 * Render activity list.
		 */
		renderActivityList: function(activities) {
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
				const statusClass = window.AIPS.getActivityStatusClass(activity.status, activity.type);
				const statusIcon = window.AIPS.getActivityStatusIcon(activity.status, activity.type);
				const $icon = $('<div class="aips-activity-icon ' + statusClass + '"><span class="dashicons ' + statusIcon + '"></span></div>');
				
				// Content
				const $content = $('<div class="aips-activity-content"></div>');
				const $message = $('<div class="aips-activity-message"></div>').text(activity.message || window.AIPS.getDefaultActivityMessage(activity));
				const $date = $('<div class="aips-activity-date"></div>').text(activity.date_formatted);
				
				$content.append($message, $date);
				
				// Actions
				const $actions = $('<div class="aips-activity-actions"></div>');
				
				if (activity.post) {
					if (activity.post.status === 'draft') {
						$actions.append('<button class="button button-small aips-quick-publish">Publish</button>');
					}
					if (activity.post.edit_url) {
						$actions.append('<a href="' + activity.post.edit_url + '" class="button button-small" target="_blank">Edit</a>');
					}
				}
				
				$item.append($icon, $content, $actions);
				$list.append($item);
			});
		},
		
		/**
		 * Get status CSS class.
		 */
		getActivityStatusClass: function(status, type) {
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
		},
		
		/**
		 * Get status icon.
		 */
		getActivityStatusIcon: function(status, type) {
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
		},
		
		/**
		 * Get default message for activity.
		 */
		getDefaultActivityMessage: function(activity) {
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
		},
		
		/**
		 * Handle filter button click.
		 */
		handleFilterClick: function() {
			$('.aips-filter-btn').removeClass('active');
			$(this).addClass('active');
			window.AIPS.currentActivityFilter = $(this).data('filter');
			window.AIPS.loadActivity();
		},

		/**
		 * Handle search.
		 */
		handleSearch: function() {
			const searchQuery = $('#aips-activity-search').val().trim();
			window.AIPS.currentActivitySearch = searchQuery;

			if (searchQuery) {
				$('#aips-activity-search-clear').show();
			} else {
				$('#aips-activity-search-clear').hide();
			}

			window.AIPS.loadActivity();
		},

		/**
		 * Handle clear search.
		 */
		handleClearSearch: function() {
			$('#aips-activity-search').val('');
			window.AIPS.currentActivitySearch = '';
			$('#aips-activity-search-clear').hide();
			window.AIPS.loadActivity();
		},
		
		/**
		 * Handle activity item click.
		 */
		handleActivityItemClick: function(e) {
			if ($(e.target).closest('.aips-activity-actions').length) {
				return; // Don't open modal if clicking action buttons
			}
			
			const postId = $(this).data('post-id');
			if (postId) {
				window.AIPS.openActivityModal(postId);
			}
		},
		
		/**
		 * Open activity detail modal.
		 */
		openActivityModal: function(postId) {
			window.AIPS.currentActivityPostId = postId;
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
						window.AIPS.renderPostDetail(response.data);
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
		},
		
		/**
		 * Render post detail in modal.
		 */
		renderPostDetail: function(post) {
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
		},
		
		/**
		 * Publish post from modal.
		 */
		publishPost: function() {
			if (!window.AIPS.currentActivityPostId) return;
			
			if (!confirm(aipsActivityL10n.confirmPublish)) return;
			
			const $btn = $('#aips-post-publish-btn');
			$btn.prop('disabled', true).text('Publishing...');
			
			$.ajax({
				url: aipsActivityL10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_publish_draft',
					nonce: aipsActivityL10n.nonce,
					post_id: window.AIPS.currentActivityPostId
				},
				success: function(response) {
					if (response.success) {
						alert(aipsActivityL10n.publishSuccess);
						window.AIPS.closeActivityModal();
						window.AIPS.loadActivity(); // Refresh feed
					} else {
						alert(response.data.message || aipsActivityL10n.publishError);
						$btn.prop('disabled', false).text('Publish Post');
					}
				},
				error: function() {
					alert(aipsActivityL10n.publishError);
					$btn.prop('disabled', false).text('Publish Post');
				}
			});
		},
		
		/**
		 * Publish post quickly from feed.
		 */
		publishPostQuick: function(e) {
			e.stopPropagation();
			
			const postId = $(this).closest('.aips-activity-item').data('post-id');
			if (!postId || !confirm(aipsActivityL10n.confirmPublish)) {
				return;
			}
			
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
						window.AIPS.loadActivity(); // Refresh feed
					} else {
						alert(response.data.message || aipsActivityL10n.publishError);
						$btn.prop('disabled', false).text('Publish');
					}
				},
				error: function() {
					alert(aipsActivityL10n.publishError);
					$btn.prop('disabled', false).text('Publish');
				}
			});
		},
		
		/**
		 * Close modal.
		 */
		closeActivityModal: function() {
			$('#aips-activity-modal').fadeOut(200);
			window.AIPS.currentActivityPostId = null;
		},
		
		/**
		 * Handle modal background click.
		 */
		handleModalClick: function(e) {
			if ($(e.target).hasClass('aips-modal')) {
				window.AIPS.closeActivityModal();
			}
		}
	});
	
	// Bind Activity Events
	$(document).ready(function() {
		// Initialize on page load
		if ($('.aips-activity-container').length > 0) {
			window.AIPS.loadActivity();
		}
		
		// Filter buttons
		$(document).on('click', '.aips-filter-btn', window.AIPS.handleFilterClick);
		
		// Search handlers
		$(document).on('click', '#aips-activity-search-btn', window.AIPS.handleSearch);
		$(document).on('keypress', '#aips-activity-search', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				window.AIPS.handleSearch();
			}
		});
		$(document).on('click', '#aips-activity-search-clear', window.AIPS.handleClearSearch);

		// Activity item click
		$(document).on('click', '.aips-activity-item', window.AIPS.handleActivityItemClick);
		
		// Modal close buttons
		$(document).on('click', '.aips-modal-close', window.AIPS.closeActivityModal);
		$(document).on('click', '.aips-modal', window.AIPS.handleModalClick);
		
		// Publish button in modal
		$(document).on('click', '#aips-post-publish-btn', window.AIPS.publishPost);
		
		// Quick publish from feed
		$(document).on('click', '.aips-quick-publish', window.AIPS.publishPostQuick);
	});
	
})(jQuery);
