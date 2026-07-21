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

	AIPS.Taxonomy = AIPS.Taxonomy || {};

	Object.assign(AIPS.Taxonomy, {
		selectedPostIds: [],
		currentTab: 'categories',
		searchTimeout: null,

		/**
		 * Initialize the Taxonomy module.
		 */
		init: function() {
			this.bindEvents();
			this.loadTaxonomyItems('categories');
		},

		/**
		 * Bind delegated event listeners.
		 */
		bindEvents: function() {
			$(document).on('click', '#aips-open-generate-modal', this.openGenerateModal.bind(this));
			// $(document).on('click', '.aips-modal-close', this.closeModal.bind(this)); // Handled globally by admin.js
			$(document).on('submit', '#aips-generate-taxonomy-form', this.generateTaxonomy.bind(this));
			$(document).on('keyup', '#base_posts', this.searchPosts.bind(this));
			$(document).on('click', '.aips-remove-post', this.removeSelectedPost.bind(this));
			$(document).on('click', '.aips-search-result', this.selectSearchResult.bind(this));
			$(document).on('click', '.aips-tab-link', this.switchTab.bind(this));
			$(document).on('click', '.aips-select-all-taxonomy', this.toggleSelectAll.bind(this));
			$(document).on('change', '.aips-taxonomy-checkbox', this.syncSelectAllState.bind(this));
			$(document).on('click', '.aips-bulk-action-execute', this.executeBulkAction.bind(this));
			$(document).on('click', '.aips-approve-taxonomy', this.approveTaxonomy.bind(this));
			$(document).on('click', '.aips-reject-taxonomy', this.rejectTaxonomy.bind(this));
			$(document).on('click', '.aips-delete-taxonomy', this.deleteTaxonomy.bind(this));
			$(document).on('click', '.aips-create-term', this.createTerm.bind(this));
			$(document).on('keyup search', '#aips-taxonomy-search', this.filterItems.bind(this));
			$(document).on('click', '#aips-taxonomy-search-clear', this.clearSearch.bind(this));
		},

		/**
		 * Open the generate taxonomy modal.
		 *
		 * @param {Event} e Click event.
		 */
		openGenerateModal: function(e) {
			e.preventDefault();
			this.selectedPostIds = [];
			$('#aips-generate-taxonomy-form')[0].reset();
			$('#base-post-search-results').empty();
			$('#selected-posts-container').empty();
			AIPS.Core.Modal.open('#aips-generate-taxonomy-modal');
		},

		/**
		 * Close the active modal.
		 *
		 * @param {Event} [e] Click event.
		 */
		closeModal: function(e) {
			if (e) {
				e.preventDefault();
			}
			AIPS.Core.Modal.close('.aips-modal');
		},

		/**
		 * Handle post search input with debounce.
		 *
		 * @param {Event} e Keyup event.
		 */
		searchPosts: function(e) {
			var searchTerm = $(e.currentTarget).val();

			if (searchTerm.length < 3) {
				$('#base-post-search-results').empty();
				return;
			}

			var self = this;
			clearTimeout(this.searchTimeout);
			this.searchTimeout = setTimeout(function() {
				AIPS.Core.Http.ajaxRequest({
					action: 'aips_search_posts',
					nonce: aipsTaxonomyL10n.nonce,
					data: { search_term: searchTerm },
					toastOnError: false,
					onSuccess: function(data) {
						if (data.posts) {
							self.displayPostSearchResults(data.posts);
						}
					}
				});
			}, 300);
		},

		/**
		 * Handle clicking on a search result to select it.
		 *
		 * @param {Event} e Click event.
		 */
		selectSearchResult: function(e) {
			e.preventDefault();

			var $result = $(e.currentTarget);
			var postId = Number($result.data('post-id'));
			var postTitle = $result.find('span').text();

			this.addSelectedPost(postId, postTitle);
			$result.remove();
		},

		/**
		 * Render post search results into the search results container.
		 *
		 * @param {Array} posts Array of post objects with id and title properties.
		 */
		displayPostSearchResults: function(posts) {
			var container = $('#base-post-search-results');
			var html = '';
			var esc = AIPS.Templates ? AIPS.Templates.escape : function(str) { return String(str || ''); };

			posts.forEach(function(post) {
				if (this.selectedPostIds.indexOf(post.id) === -1) {
					html += '<div class="aips-search-result" data-post-id="' + post.id + '" style="cursor: pointer; padding: 5px; border-bottom: 1px solid #ddd;">';
					html += '<span>' + esc(post.title) + '</span>';
					html += '</div>';
				}
			}.bind(this));

			if (html) {
				container.html(html);
			} else {
				container.empty();
			}
		},

		/**
		 * Add a post to the selected posts list.
		 *
		 * @param {number} postId    Post ID.
		 * @param {string} postTitle Post title.
		 */
		addSelectedPost: function(postId, postTitle) {
			if (this.selectedPostIds.indexOf(postId) !== -1) {
				return;
			}

			this.selectedPostIds.push(postId);

			var html = AIPS.Templates.renderRaw('aips-tmpl-selected-post', {
				id: postId,
				title: AIPS.Templates.escape(postTitle)
			});

			$('#selected-posts-container').append(html);
			$('#base-post-search-results').empty();
			$('#base_posts').val('');
		},

		/**
		 * Remove a post from the selected posts list.
		 *
		 * @param {Event} e Click event.
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
		 * Handle the generate taxonomy form submission.
		 *
		 * @param {Event} e Submit event.
		 */
		generateTaxonomy: function(e) {
			e.preventDefault();

			var taxonomyType = $('#taxonomy_type').val();
			var generationPrompt = $('#generation_prompt').val();

			if (!taxonomyType) {
				AIPS.Utilities.showToast(aipsTaxonomyL10n.selectTaxonomyType, 'warning');
				return;
			}

			if (this.selectedPostIds.length === 0) {
				AIPS.Utilities.showToast(aipsTaxonomyL10n.selectPost, 'warning');
				return;
			}

			var submitBtn = $('#generate-taxonomy-submit-btn');
			var self = this;

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_generate_taxonomy',
				nonce: aipsTaxonomyL10n.nonce,
				data: {
					taxonomy_type: taxonomyType,
					generation_prompt: generationPrompt,
					base_post_ids: this.selectedPostIds
				},
				$button: submitBtn,
				loadingLabel: aipsTaxonomyL10n.generating,
				errorFallback: aipsTaxonomyL10n.generationFailed,
				onSuccess: function(data) {
					AIPS.Utilities.showToast(data.message, 'success');
					self.updateStats(data.stats || null);
					self.closeModal();
					self.loadTaxonomyItems(self.currentTab);
				}
			});
		},

		/**
		 * Switch between category/tag tabs.
		 *
		 * @param {Event} e Click event.
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
		 * Load taxonomy items for the given tab via AJAX.
		 *
		 * @param {string} tab Tab name ('categories' or 'tags').
		 */
		loadTaxonomyItems: function(tab) {
			var taxonomyType = tab === 'categories' ? 'category' : 'post_tag';
			var activeSearchTerm = $('#aips-taxonomy-search').val();
			var self = this;

			$('#aips-taxonomy-loading').show();
			$('#aips-taxonomy-content').hide();

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_get_taxonomy_items',
				nonce: aipsTaxonomyL10n.nonce,
				data: { taxonomy_type: taxonomyType },
				toastOnError: false,
				onSuccess: function(data) {
					self.updateStats(data.stats || null);
					self.renderTaxonomyItems(data.items);
					if (activeSearchTerm) {
						$('#aips-taxonomy-search').trigger('search');
					}
				}
			}).always(function() {
				$('#aips-taxonomy-loading').hide();
				$('#aips-taxonomy-content').show();
			});
		},

		/**
		 * Render taxonomy items into the content area.
		 *
		 * @param {Array} items Array of taxonomy item objects.
		 */
		renderTaxonomyItems: function(items) {
			var rowsHtml = '';
			var esc = AIPS.Templates ? AIPS.Templates.escape : function(str) { return String(str || ''); };

			items.forEach(function(item) {
				var actions = this.renderItemActions(item);

				rowsHtml += AIPS.Templates.renderRaw('aips-tmpl-taxonomy-row', {
					id: item.id,
					name: esc(item.name),
					taxonomy_type: item.taxonomy_type,
					status: item.status,
					status_label: esc(AIPS.Utilities.toTitleCase(item.status)),
					generated_at: esc(item.created_at),
					actions: actions
				});
			}.bind(this));

			if (!rowsHtml) {
				rowsHtml = '<tr><td colspan="5" style="text-align: center;">No items found.</td></tr>';
			}

			var tableHtml = AIPS.Templates.renderRaw('aips-tmpl-taxonomy-table', {
				selectAllLabel: 'Select all taxonomy items',
				nameLabel: 'Name',
				statusLabel: 'Status',
				generatedAtLabel: 'Generated',
				actionsLabel: 'Actions',
				rows: rowsHtml
			});

			$('#aips-taxonomy-content').html(tableHtml);
			this.updateVisibleResultCount();
		},

		/**
		 * Render action buttons for a taxonomy item.
		 *
		 * @param {Object} item Taxonomy item.
		 * @return {string} HTML string for actions.
		 */
		renderItemActions: function(item) {
			var templateId = '';
			var createControl = '';

			if (item.status === 'pending') {
				templateId = 'aips-tmpl-taxonomy-actions-pending';
			} else if (item.status === 'approved') {
				templateId = 'aips-tmpl-taxonomy-actions-approved';
				if (item.term_id && Number(item.term_id) > 0) {
					createControl = '<span class="aips-taxonomy-term-created">Term Created</span>';
				} else {
					createControl = '<button class="aips-btn aips-btn-sm aips-btn-secondary aips-create-term" data-id="' + item.id + '">Create Term</button>';
				}
			} else if (item.status === 'rejected') {
				templateId = 'aips-tmpl-taxonomy-actions-rejected';
			} else if (item.status === 'created') {
				return '<span class="aips-taxonomy-term-created">Term Created</span>';
			}

			if (!templateId) {
				return '';
			}

			return AIPS.Templates.renderRaw(templateId, {
				id: item.id,
				approveLabel: 'Approve',
				rejectLabel: 'Reject',
				deleteLabel: 'Delete',
				createControl: createControl
			});
		},

		/**
		 * Toggle all taxonomy checkboxes.
		 *
		 * @param {Event} e Change event.
		 */
		toggleSelectAll: function(e) {
			AIPS.Core.Table.toggleAllRows({
				checked: $(e.currentTarget).prop('checked'),
				rowCheckboxSelector: '.aips-taxonomy-checkbox'
			});
		},

		/**
		 * Sync the select-all checkbox state based on individual checkboxes.
		 */
		syncSelectAllState: function() {
			AIPS.Core.Table.syncSelectAll({
				$selectAll: $('.aips-select-all-taxonomy'),
				rowCheckboxSelector: '.aips-taxonomy-checkbox'
			});
		},

		/**
		 * Execute the selected bulk action.
		 *
		 * @param {Event} e Click event.
		 */
		executeBulkAction: function(e) {
			e.preventDefault();

			var action = $('.aips-bulk-action-select').val();
			if (!action) {
				AIPS.Utilities.showToast(aipsTaxonomyL10n.selectAction, 'warning');
				return;
			}

			var itemIds = AIPS.Core.Table.getSelectedIds('.aips-taxonomy-checkbox');

			if (itemIds.length === 0) {
				AIPS.Utilities.showToast(aipsTaxonomyL10n.selectItem, 'warning');
				return;
			}

			var ajaxAction = action === 'generate_terms' ? 'aips_bulk_create_taxonomy_terms' : 'aips_bulk_' + action + '_taxonomy';
			var actionLabel = action === 'generate_terms' ? 'generate terms for' : action;
			var confirmMsg = aipsTaxonomyL10n.confirmBulkAction.replace('%s', actionLabel).replace('%d', itemIds.length);
			var self = this;

			AIPS.Core.Bulk.dispatch({
				action: ajaxAction,
				ids: itemIds,
				idsField: 'item_ids',
				nonce: aipsTaxonomyL10n.nonce,
				confirmMessage: confirmMsg,
				confirmHeading: 'Notice',
				confirmLabel: 'Yes, confirm',
				cancelLabel: 'Cancel',
				errorFallback: aipsTaxonomyL10n.actionFailed,
				onSuccess: function(data) {
					AIPS.Utilities.showToast(data.message, 'success');
					self.updateStats(data.stats || null);
					self.loadTaxonomyItems(self.currentTab);
				}
			});
		},

		/**
		 * Approve a single taxonomy item.
		 *
		 * @param {Event} e Click event.
		 */
		approveTaxonomy: function(e) {
			e.preventDefault();
			var itemId = $(e.currentTarget).data('id');
			this.updateItemStatus(itemId, 'aips_approve_taxonomy');
		},

		/**
		 * Reject a single taxonomy item.
		 *
		 * @param {Event} e Click event.
		 */
		rejectTaxonomy: function(e) {
			e.preventDefault();
			var itemId = $(e.currentTarget).data('id');
			this.updateItemStatus(itemId, 'aips_reject_taxonomy');
		},

		/**
		 * Delete a single taxonomy item.
		 *
		 * @param {Event} e Click event.
		 */
		deleteTaxonomy: function(e) {
			e.preventDefault();

			var itemId = $(e.currentTarget).data('id');
			var self = this;

			AIPS.Utilities.confirm(aipsTaxonomyL10n.confirmDelete, 'Notice', [
				{ label: 'Cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						AIPS.Core.Http.ajaxRequest({
							action: 'aips_delete_taxonomy',
							nonce: aipsTaxonomyL10n.nonce,
							data: { item_id: itemId },
							errorFallback: aipsTaxonomyL10n.deleteFailed,
							onSuccess: function(data) {
								self.updateStats(data.stats || null);
								self.loadTaxonomyItems(self.currentTab);
							}
						});
					}
				}
			]);
		},

		/**
		 * Create a WordPress term from an approved taxonomy item.
		 *
		 * @param {Event} e Click event.
		 */
		createTerm: function(e) {
			e.preventDefault();

			var itemId = $(e.currentTarget).data('id');
			var self = this;

			AIPS.Utilities.confirm(aipsTaxonomyL10n.confirmCreateTerm, 'Notice', [
				{ label: 'Cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, create',
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						AIPS.Core.Http.ajaxRequest({
							action: 'aips_create_taxonomy_term',
							nonce: aipsTaxonomyL10n.nonce,
							data: { item_id: itemId },
							errorFallback: aipsTaxonomyL10n.termCreationFailed,
							onSuccess: function(data) {
								AIPS.Utilities.showToast(data.message, 'success');
								self.updateStats(data.stats || null);
								self.loadTaxonomyItems(self.currentTab);
							}
						});
					}
				}
			]);
		},

		/**
		 * Update a taxonomy item's status via AJAX.
		 *
		 * @param {number} itemId Taxonomy item ID.
		 * @param {string} action AJAX action name.
		 */
		updateItemStatus: function(itemId, action) {
			var self = this;

			AIPS.Core.Http.ajaxRequest({
				action: action,
				nonce: aipsTaxonomyL10n.nonce,
				data: { item_id: itemId },
				errorFallback: aipsTaxonomyL10n.updateFailed,
				onSuccess: function(data) {
					self.updateStats(data.stats || null);
					self.loadTaxonomyItems(self.currentTab);
				}
			});
		},

		/**
		 * Filter visible table rows by search term.
		 *
		 * @param {Event} e Keyup or search event.
		 */
		filterItems: function(e) {
			AIPS.Core.Table.filterRows({
				term: $(e.currentTarget).val(),
				$rows: $('.aips-taxonomy-table tbody tr'),
				$clearButton: $('#aips-taxonomy-search-clear')
			});

			this.updateVisibleResultCount();
		},

		/**
		 * Clear the search field and show all rows.
		 *
		 * @param {Event} e Click event.
		 */
		clearSearch: function(e) {
			e.preventDefault();
			$('#aips-taxonomy-search').val('').trigger('search');
		},

		/**
		 * Update all stat counters from a stats object.
		 *
		 * @param {Object|null} stats Stats payload from the server.
		 */
		updateStats: function(stats) {
			if (!stats) {
				return;
			}

			$('#stat-pending-count').text(stats.pending_total || 0);
			$('#stat-approved-count').text(stats.approved_total || 0);
			$('#stat-rejected-count').text(stats.rejected_total || 0);
			$('#stat-total-count').text(stats.total_items || 0);
			$('#categories-count').text(stats.categories_total || 0);
			$('#tags-count').text(stats.tags_total || 0);
		},

		/**
		 * Refresh the visible result count label.
		 */
		updateVisibleResultCount: function() {
			var visibleCount = $('.aips-taxonomy-table tbody tr[data-taxonomy-id]:visible').length;
			this.updateResultCountLabel(visibleCount);
		},

		/**
		 * Update the result count label text.
		 *
		 * @param {number} count Number of visible rows.
		 */
		updateResultCountLabel: function(count) {
			var normalizedCount = Number(count || 0);
			var label = normalizedCount === 1 ? aipsTaxonomyL10n.item : aipsTaxonomyL10n.items;

			$('#aips-taxonomy-result-count').text(normalizedCount + ' ' + label);
		}
	});

	$(document).ready(function() {
		AIPS.Taxonomy.init();
	});

})(jQuery);
