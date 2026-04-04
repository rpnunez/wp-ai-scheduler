/**
 * Taxonomy Management JavaScript
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.Taxonomy = {
		/**
		 * Selected post IDs for taxonomy generation.
		 */
		selectedPostIds: [],

		/**
		 * Current active tab.
		 */
		currentTab: 'categories',

		/**
		 * Initialize the Taxonomy module.
		 */
		init: function() {
			this.bindEvents();
			this.loadTaxonomyItems('categories');
		},

		/**
		 * Bind all event listeners for the Taxonomy page.
		 */
		bindEvents: function() {
			// Open generate modal
			$(document).on('click', '#aips-open-generate-modal', this.openGenerateModal.bind(this));

			// Close modals
			$(document).on('click', '.aips-modal-close', this.closeModal.bind(this));

			// Submit generate form
			$(document).on('submit', '#aips-generate-taxonomy-form', this.generateTaxonomy.bind(this));

			// Post search
			$(document).on('keyup', '#base_posts', this.searchPosts.bind(this));

			// Remove selected post
			$(document).on('click', '.aips-remove-post', this.removeSelectedPost.bind(this));

			// Tab switching
			$(document).on('click', '.aips-tab-link', this.switchTab.bind(this));

			// Bulk actions
			$(document).on('click', '.aips-select-all-taxonomy', this.toggleSelectAll.bind(this));
			$(document).on('click', '.aips-bulk-action-execute', this.executeBulkAction.bind(this));

			// Individual item actions
			$(document).on('click', '.aips-approve-taxonomy', this.approveTaxonomy.bind(this));
			$(document).on('click', '.aips-reject-taxonomy', this.rejectTaxonomy.bind(this));
			$(document).on('click', '.aips-delete-taxonomy', this.deleteTaxonomy.bind(this));
			$(document).on('click', '.aips-create-term', this.createTerm.bind(this));

			// Search
			$(document).on('keyup search', '#aips-taxonomy-search', this.filterItems.bind(this));
			$(document).on('click', '#aips-taxonomy-search-clear', this.clearSearch.bind(this));
		},

		/**
		 * Open the generate taxonomy modal.
		 *
		 * @param {Event} e - Click event.
		 */
		openGenerateModal: function(e) {
			e.preventDefault();
			this.selectedPostIds = [];
			$('#aips-generate-taxonomy-form')[0].reset();
			$('#selected-posts-container').empty();
			$('#aips-generate-taxonomy-modal').fadeIn();
		},

		/**
		 * Close all modals.
		 *
		 * @param {Event} e - Click event.
		 */
		closeModal: function(e) {
			e.preventDefault();
			$('.aips-modal').fadeOut();
		},

		/**
		 * Search for posts via AJAX.
		 *
		 * @param {Event} e - Keyup event.
		 */
		searchPosts: function(e) {
			var searchTerm = $(e.currentTarget).val();

			if (searchTerm.length < 3) {
				return;
			}

			var self = this;
			clearTimeout(this.searchTimeout);
			this.searchTimeout = setTimeout(function() {
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'aips_search_posts',
						nonce: aipsL10n.nonce,
						search_term: searchTerm
					},
					success: function(response) {
						if (response.success && response.data.posts) {
							self.displayPostSearchResults(response.data.posts);
						}
					}
				});
			}, 300);
		},

		/**
		 * Display post search results as selectable items.
		 *
		 * @param {Array} posts - Array of post objects.
		 */
		displayPostSearchResults: function(posts) {
			var container = $('#selected-posts-container');
			var html = '';

			posts.forEach(function(post) {
				if (this.selectedPostIds.indexOf(post.id) === -1) {
					html += '<div class="aips-search-result" data-post-id="' + post.id + '" style="cursor: pointer; padding: 5px; border-bottom: 1px solid #ddd;">';
					html += '<span>' + post.title + '</span>';
					html += '</div>';
				}
			}.bind(this));

			if (html) {
				container.html(html);

				// Add click handler for search results
				$(document).off('click', '.aips-search-result').on('click', '.aips-search-result', function(e) {
					var postId = $(e.currentTarget).data('post-id');
					var postTitle = $(e.currentTarget).find('span').text();
					this.addSelectedPost(postId, postTitle);
					$(e.currentTarget).remove();
				}.bind(this));
			}
		},

		/**
		 * Add a post to the selected posts list.
		 *
		 * @param {number} postId - Post ID.
		 * @param {string} postTitle - Post title.
		 */
		addSelectedPost: function(postId, postTitle) {
			if (this.selectedPostIds.indexOf(postId) !== -1) {
				return;
			}

			this.selectedPostIds.push(postId);

			var html = AIPS.Templates.renderRaw('aips-tmpl-selected-post', {
				id: postId,
				title: postTitle
			});

			$('#selected-posts-container').append(html);
			$('#base_posts').val('');
		},

		/**
		 * Remove a post from the selected posts list.
		 *
		 * @param {Event} e - Click event.
		 */
		removeSelectedPost: function(e) {
			e.preventDefault();
			var postId = $(e.currentTarget).data('post-id');
			var index = this.selectedPostIds.indexOf(postId);

			if (index > -1) {
				this.selectedPostIds.splice(index, 1);
			}

			$(e.currentTarget).closest('.aips-selected-post').remove();
		},

		/**
		 * Submit the taxonomy generation form.
		 *
		 * @param {Event} e - Submit event.
		 */
		generateTaxonomy: function(e) {
			e.preventDefault();

			var taxonomyType = $('#taxonomy_type').val();
			var generationPrompt = $('#generation_prompt').val();

			if (!taxonomyType) {
				alert('Please select a taxonomy type.');
				return;
			}

			if (this.selectedPostIds.length === 0) {
				alert('Please select at least one post.');
				return;
			}

			var submitBtn = $('#generate-taxonomy-submit-btn');
			submitBtn.prop('disabled', true).text('Generating...');

			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'aips_generate_taxonomy',
					nonce: aipsL10n.nonce,
					taxonomy_type: taxonomyType,
					generation_prompt: generationPrompt,
					base_post_ids: this.selectedPostIds
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						this.closeModal({ preventDefault: function() {} });
						this.loadTaxonomyItems(this.currentTab);
					} else {
						alert(response.data.message || 'Generation failed.');
					}
				}.bind(this),
				complete: function() {
					submitBtn.prop('disabled', false).text('Generate');
				}
			});
		},

		/**
		 * Switch between Categories and Tags tabs.
		 *
		 * @param {Event} e - Click event.
		 */
		switchTab: function(e) {
			e.preventDefault();
			var tab = $(e.currentTarget).data('tab');

			$('.aips-tab-link').removeClass('active');
			$(e.currentTarget).addClass('active');

			this.currentTab = tab;
			this.loadTaxonomyItems(tab);
		},

		/**
		 * Load taxonomy items for the current tab.
		 *
		 * @param {string} tab - Tab name ('categories' or 'tags').
		 */
		loadTaxonomyItems: function(tab) {
			var taxonomyType = tab === 'categories' ? 'category' : 'post_tag';

			$('#aips-taxonomy-loading').show();
			$('#aips-taxonomy-content').hide();

			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'aips_get_taxonomy_items',
					nonce: aipsL10n.nonce,
					taxonomy_type: taxonomyType
				},
				success: function(response) {
					if (response.success) {
						this.renderTaxonomyItems(response.data.items);
					}
				}.bind(this),
				complete: function() {
					$('#aips-taxonomy-loading').hide();
					$('#aips-taxonomy-content').show();
				}
			});
		},

		/**
		 * Render taxonomy items in a table.
		 *
		 * @param {Array} items - Array of taxonomy item objects.
		 */
		renderTaxonomyItems: function(items) {
			var rowsHtml = '';

			items.forEach(function(item) {
				var actions = this.renderItemActions(item);
				var typeLabel = item.taxonomy_type === 'category' ? 'Category' : 'Tag';

				rowsHtml += AIPS.Templates.renderRaw('aips-tmpl-taxonomy-row', {
					id: item.id,
					name: item.name,
					taxonomy_type: item.taxonomy_type,
					taxonomy_type_label: typeLabel,
					status: item.status,
					generated_at: item.created_at,
					actions: actions
				});
			}.bind(this));

			if (!rowsHtml) {
				rowsHtml = '<tr><td colspan="6" style="text-align: center;">' + 'No items found.' + '</td></tr>';
			}

			var tableHtml = AIPS.Templates.renderRaw('aips-tmpl-taxonomy-table', {
				nameLabel: 'Name',
				typeLabel: 'Type',
				statusLabel: 'Status',
				generatedAtLabel: 'Generated',
				actionsLabel: 'Actions',
				rows: rowsHtml
			});

			$('#aips-taxonomy-content').html(tableHtml);
		},

		/**
		 * Render action buttons for a taxonomy item based on its status.
		 *
		 * @param {Object} item - Taxonomy item object.
		 * @return {string} HTML string of action buttons.
		 */
		renderItemActions: function(item) {
			var templateId = '';

			if (item.status === 'pending') {
				templateId = 'aips-tmpl-taxonomy-actions-pending';
			} else if (item.status === 'approved') {
				templateId = 'aips-tmpl-taxonomy-actions-approved';
			} else if (item.status === 'rejected') {
				templateId = 'aips-tmpl-taxonomy-actions-rejected';
			}

			if (!templateId) {
				return '';
			}

			return AIPS.Templates.renderRaw(templateId, {
				id: item.id,
				approveLabel: 'Approve',
				rejectLabel: 'Reject',
				deleteLabel: 'Delete',
				createLabel: 'Create Term'
			});
		},

		/**
		 * Toggle select all checkboxes.
		 *
		 * @param {Event} e - Change event.
		 */
		toggleSelectAll: function(e) {
			var isChecked = $(e.currentTarget).prop('checked');
			$('.aips-taxonomy-checkbox').prop('checked', isChecked);
		},

		/**
		 * Execute bulk action.
		 *
		 * @param {Event} e - Click event.
		 */
		executeBulkAction: function(e) {
			e.preventDefault();

			var action = $('.aips-bulk-action-select').val();
			if (!action) {
				alert('Please select an action.');
				return;
			}

			var itemIds = [];
			$('.aips-taxonomy-checkbox:checked').each(function() {
				itemIds.push($(this).val());
			});

			if (itemIds.length === 0) {
				alert('Please select at least one item.');
				return;
			}

			var ajaxAction = 'aips_bulk_' + action + '_taxonomy';
			var confirmMsg = 'Are you sure you want to ' + action + ' ' + itemIds.length + ' items?';

			if (!confirm(confirmMsg)) {
				return;
			}

			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: ajaxAction,
					nonce: aipsL10n.nonce,
					item_ids: itemIds
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						this.loadTaxonomyItems(this.currentTab);
					} else {
						alert(response.data.message || 'Action failed.');
					}
				}.bind(this)
			});
		},

		/**
		 * Approve a taxonomy item.
		 *
		 * @param {Event} e - Click event.
		 */
		approveTaxonomy: function(e) {
			e.preventDefault();
			var itemId = $(e.currentTarget).data('id');
			this.updateItemStatus(itemId, 'aips_approve_taxonomy', 'approved');
		},

		/**
		 * Reject a taxonomy item.
		 *
		 * @param {Event} e - Click event.
		 */
		rejectTaxonomy: function(e) {
			e.preventDefault();
			var itemId = $(e.currentTarget).data('id');
			this.updateItemStatus(itemId, 'aips_reject_taxonomy', 'rejected');
		},

		/**
		 * Delete a taxonomy item.
		 *
		 * @param {Event} e - Click event.
		 */
		deleteTaxonomy: function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to delete this item?')) {
				return;
			}

			var itemId = $(e.currentTarget).data('id');

			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'aips_delete_taxonomy',
					nonce: aipsL10n.nonce,
					item_id: itemId
				},
				success: function(response) {
					if (response.success) {
						this.loadTaxonomyItems(this.currentTab);
					} else {
						alert(response.data.message || 'Delete failed.');
					}
				}.bind(this)
			});
		},

		/**
		 * Create a WordPress term from an approved item.
		 *
		 * @param {Event} e - Click event.
		 */
		createTerm: function(e) {
			e.preventDefault();

			if (!confirm('Create this term in WordPress?')) {
				return;
			}

			var itemId = $(e.currentTarget).data('id');

			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'aips_create_taxonomy_term',
					nonce: aipsL10n.nonce,
					item_id: itemId
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						this.loadTaxonomyItems(this.currentTab);
					} else {
						alert(response.data.message || 'Term creation failed.');
					}
				}.bind(this)
			});
		},

		/**
		 * Update the status of a taxonomy item.
		 *
		 * @param {number} itemId - Item ID.
		 * @param {string} action - AJAX action name.
		 * @param {string} status - New status.
		 */
		updateItemStatus: function(itemId, action, status) {
			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: action,
					nonce: aipsL10n.nonce,
					item_id: itemId
				},
				success: function(response) {
					if (response.success) {
						this.loadTaxonomyItems(this.currentTab);
					} else {
						alert(response.data.message || 'Update failed.');
					}
				}.bind(this)
			});
		},

		/**
		 * Filter items by search term.
		 *
		 * @param {Event} e - Keyup/search event.
		 */
		filterItems: function(e) {
			var searchTerm = $(e.currentTarget).val().toLowerCase();
			var clearBtn = $('#aips-taxonomy-search-clear');

			if (searchTerm) {
				clearBtn.show();
			} else {
				clearBtn.hide();
			}

			$('.aips-taxonomy-table tbody tr').each(function() {
				var name = $(this).find('.column-name').text().toLowerCase();
				if (name.indexOf(searchTerm) !== -1) {
					$(this).show();
				} else {
					$(this).hide();
				}
			});
		},

		/**
		 * Clear the search filter.
		 *
		 * @param {Event} e - Click event.
		 */
		clearSearch: function(e) {
			e.preventDefault();
			$('#aips-taxonomy-search').val('').trigger('search');
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		AIPS.Taxonomy.init();
	});

})(jQuery);
