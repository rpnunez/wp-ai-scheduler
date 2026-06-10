import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseListView } from './base-list';
import { BaseModalView } from './base-modal';

/**
 * Post Review & AI Editing View Controller
 */
export const PostReviewView = BaseListView.extend({
	el: 'body',

	events: _.extend({}, BaseListView.prototype.events, {
		// Reviews List Events
		'change #cb-select-all-1': 'onSelectAllChange',
		'change .aips-post-checkbox': 'onCheckboxChange',
		'click .aips-preview-post, .aips-preview-trigger': 'onPreviewClick',
		'click .aips-edit-post': 'onEditClick',
		'click .aips-publish-post': 'onPublishClick',
		'click .aips-delete-post': 'onDeleteClick',
		'click .aips-regenerate-post': 'onRegenerateClick',
		'click .aips-row-action-overflow-toggle': 'onRowActionOverflowToggle',
		'click .aips-row-action-menu .aips-row-action-item': 'onRowActionItemClick',
		'click #aips-bulk-action-btn': 'onBulkAction',
		'click #aips-reload-posts-btn': 'onReloadClick',
		'click #aips-post-preview-modal .aips-modal-close, #aips-post-preview-modal .aips-modal-overlay': 'closePreviewModal',

		// AI Edit Modal Events
		'click .aips-ai-edit-btn': 'openAIEditModal',
		'click #aips-ai-edit-cancel, #aips-ai-edit-close, #aips-ai-edit-modal .aips-modal-overlay': 'closeAIEditModal',
		'click .aips-regenerate-btn': 'regenerateComponent',
		'click #aips-ai-edit-regenerate-all': 'regenerateAllComponents',
		'click #aips-ai-edit-save': 'saveAIEditChanges',
		'click .aips-view-revisions-btn': 'toggleRevisionViewer',
		'click .aips-restore-revision-btn': 'restoreRevision',
		'input .aips-component-input, .aips-component-textarea': 'onAIEditComponentChange'
	}),

	initialize() {
		BaseListView.prototype.initialize.apply(this, arguments);

		this.l10n = window.aipsPostReviewL10n || {};
		this.aiEditL10n = window.aipsAIEditL10n || {};

		// AI Edit state
		this.aiEditState = {
			postId: null,
			historyId: null,
			components: {},
			changedComponents: new Set(),
			originalValues: {},
			currentSources: {},
			nonManualSnapshots: {}
		};

		// Modals
		if (this.$('#aips-post-preview-modal').length) {
			this.previewModal = new BaseModalView({ el: '#aips-post-preview-modal' });
		}
		if (this.$('#aips-ai-edit-modal').length) {
			this.aiEditModal = new BaseModalView({ el: '#aips-ai-edit-modal' });
		}

		// Bind globally on window for inline scripts
		window.AIPS = window.AIPS || {};
		window.AIPS.PostReview = this;
		window.AIPS.openAIEditModal = this.openAIEditModal.bind(this);
		window.AIPS.closeAIEditModal = this.closeAIEditModal.bind(this);
		window.AIPS.regenerateComponent = this.regenerateComponent.bind(this);
		window.AIPS.regenerateAllComponents = this.regenerateAllComponents.bind(this);
		window.AIPS.saveAIEditChanges = this.saveAIEditChanges.bind(this);
		window.AIPS.toggleRevisionViewer = this.toggleRevisionViewer.bind(this);
		window.AIPS.restoreRevision = this.restoreRevision.bind(this);
		window.AIPS.onAIEditComponentChange = this.onAIEditComponentChange.bind(this);

		// Global document clicks/keys
		$(document).on('click.aipsPostReview', this.onDocumentClick.bind(this));
		$(document).on('keydown.aipsPostReview', this.handleAIEditKeyboard.bind(this));
	},

	// -------------------------------------------------------------------------
	// Reviews List Methods
	// -------------------------------------------------------------------------

	onSelectAllChange(e) {
		this.$('.aips-post-checkbox').prop('checked', $(e.currentTarget).prop('checked'));
	},

	onCheckboxChange() {
		const total = this.$('.aips-post-checkbox').length;
		const checked = this.$('.aips-post-checkbox:checked').length;
		this.$('#cb-select-all-1').prop('checked', total > 0 && total === checked);
	},

	onPreviewClick(e) {
		e.preventDefault();
		const postId = $(e.currentTarget).attr('data-post-id') || $(e.currentTarget).data('post-id');
		this.previewPost(postId);
	},

	onEditClick(e) {
		e.preventDefault();
		const editUrl = $(e.currentTarget).attr('data-edit-url') || $(e.currentTarget).data('edit-url');
		if (editUrl) {
			window.open(editUrl, '_blank', 'noopener');
		}
	},

	onRowActionOverflowToggle(e) {
		e.preventDefault();
		e.stopPropagation();

		const $toggle = $(e.currentTarget);
		const menuId = $toggle.attr('aria-controls');
		const $menu = menuId ? this.$('#' + menuId) : $();

		if (!$menu.length) return;

		const isExpanded = $toggle.attr('aria-expanded') === 'true';
		this.closeAllRowActionMenus();

		if (!isExpanded) {
			$toggle.attr('aria-expanded', 'true');
			$menu.prop('hidden', false);
		}
	},

	onRowActionItemClick() {
		this.closeAllRowActionMenus();
	},

	onDocumentClick(e) {
		if ($(e.target).closest('.aips-row-action-group, .aips-row-action-menu').length) {
			return;
		}
		this.closeAllRowActionMenus();
	},

	closeAllRowActionMenus() {
		this.$('.aips-row-action-overflow-toggle[aria-expanded="true"]').attr('aria-expanded', 'false');
		this.$('.aips-row-action-menu').prop('hidden', true);
	},

	onPublishClick(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const postId = $btn.attr('data-post-id') || $btn.data('post-id');
		const $row = $btn.closest('tr');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(this.l10n.confirmPublish || 'Are you sure you want to publish this post?', 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, publish',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						window.AIPS.Utilities.setButtonLoading($btn, this.l10n.loading || 'Publishing...');
						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_publish_post',
								post_id: postId,
								nonce: this.l10n.nonce || ''
							},
							success: (response) => {
								if (response.success) {
									const rawMsg = response.data.message || this.l10n.publishSuccess || 'Post published successfully.';
									const safeMsg = $('<div>').text(rawMsg).html();
									if (response.data.post_id) {
										const editUrl = 'post.php?post=' + encodeURIComponent(response.data.post_id) + '&action=edit';
										const safeLink = '<a href="' + editUrl.replace(/"/g, '&quot;') + '" target="_blank">Edit Post</a>';
										window.AIPS.Utilities.showToast(safeMsg + ' ' + safeLink, 'success', { isHtml: true });
									} else {
										window.AIPS.Utilities.showToast(safeMsg, 'success');
									}
									$row.fadeOut(400, () => {
										$row.remove();
										this.updateDraftCount();
										this.checkEmptyState();
									});
								} else {
									window.AIPS.Utilities.showToast(response.data.message || this.l10n.publishError || 'Failed to publish post.', 'error');
									window.AIPS.Utilities.resetButton($btn);
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast(this.l10n.publishError || 'Failed to publish post.', 'error');
								window.AIPS.Utilities.resetButton($btn);
							}
						});
					}
				}
			]);
		}
	},

	onDeleteClick(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const postId = $btn.attr('data-post-id') || $btn.data('post-id');
		const historyId = $btn.attr('data-history-id') || $btn.data('history-id');
		const $row = $btn.closest('tr');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(this.l10n.confirmDelete || 'Are you sure you want to delete this post?', 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						window.AIPS.Utilities.setButtonLoading($btn, this.l10n.deleting || 'Deleting...');
						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_delete_draft_post',
								post_id: postId,
								history_id: historyId,
								nonce: this.l10n.nonce || ''
							},
							success: (response) => {
								if (response.success) {
									window.AIPS.Utilities.showToast(response.data.message || this.l10n.deleteSuccess || 'Post deleted successfully.', 'success');
									$row.fadeOut(400, () => {
										$row.remove();
										this.updateDraftCount();
										this.checkEmptyState();
									});
								} else {
									window.AIPS.Utilities.showToast(response.data.message || this.l10n.deleteError || 'Failed to delete post.', 'error');
									window.AIPS.Utilities.resetButton($btn);
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast(this.l10n.deleteError || 'Failed to delete post.', 'error');
								window.AIPS.Utilities.resetButton($btn);
							}
						});
					}
				}
			]);
		}
	},

	onRegenerateClick(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const historyId = $btn.attr('data-history-id') || $btn.data('history-id');
		const $row = $btn.closest('tr');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(this.l10n.confirmRegenerate || 'Are you sure you want to regenerate this post?', 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, regenerate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						window.AIPS.Utilities.setButtonLoading($btn, this.l10n.regenerating || 'Regenerating...');
						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_regenerate_post',
								history_id: historyId,
								nonce: this.l10n.nonce || ''
							},
							success: (response) => {
								if (response.success) {
									const msg = response.data.message || this.l10n.regenerateSuccess || 'Regeneration started.';
									window.AIPS.Utilities.showToast(msg + ' Check History for progress.', 'success');
									$row.fadeOut(400, () => {
										$row.remove();
										this.updateDraftCount();
										this.checkEmptyState();
									});
								} else {
									window.AIPS.Utilities.showToast(response.data.message || this.l10n.regenerateError || 'Failed to regenerate post.', 'error');
									window.AIPS.Utilities.resetButton($btn);
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast(this.l10n.regenerateError || 'Failed to regenerate post.', 'error');
								window.AIPS.Utilities.resetButton($btn);
							}
						});
					}
				}
			]);
		}
	},

	onBulkAction(e) {
		e.preventDefault();
		const action = this.$('#bulk-action-selector-top').val();
		if (!action) return;

		const checkedBoxes = this.$('.aips-post-checkbox:checked');
		if (checkedBoxes.length === 0) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(this.l10n.noPostsSelected || 'No posts selected.', 'warning');
			}
			return;
		}

		if (action === 'publish') {
			this.bulkPublish(checkedBoxes);
		} else if (action === 'delete') {
			this.bulkDelete(checkedBoxes);
		} else if (action === 'regenerate') {
			this.bulkRegenerate(checkedBoxes);
		}
	},

	onReloadClick(e) {
		e.preventDefault();
		location.reload();
	},

	closePreviewModal(e) {
		if (e) e.preventDefault();
		if (this.previewModal) {
			this.previewModal.close();
		}
		this.$('#aips-post-preview-iframe').attr('src', '');
	},

	bulkPublish(checkedBoxes) {
		const count = checkedBoxes.length;
		const confirmMsg = (this.l10n.confirmBulkPublish || 'Publish the selected %d posts?').replace('%d', count);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, publish',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const postIds = [];
						checkedBoxes.each(function() {
							postIds.push($(this).attr('data-post-id') || $(this).data('post-id'));
						});

						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_bulk_publish_posts',
								post_ids: postIds,
								nonce: this.l10n.nonce || ''
							},
							success: (response) => {
								if (response.success) {
									const msg = (this.l10n.bulkPublishSuccess || '%d posts published successfully.').replace('%d', response.data.count || count);
									window.AIPS.Utilities.showToast(msg, 'success');
									checkedBoxes.each((idx, el) => {
										$(el).closest('tr').fadeOut(400, function() {
											$(this).remove();
											window.AIPS.PostReview.updateDraftCount();
											window.AIPS.PostReview.checkEmptyState();
										});
									});
								} else {
									window.AIPS.Utilities.showToast(response.data.message || this.l10n.publishError || 'Failed to publish posts.', 'error');
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast(this.l10n.publishError || 'Failed to publish posts.', 'error');
							}
						});
					}
				}
			]);
		}
	},

	bulkDelete(checkedBoxes) {
		const count = checkedBoxes.length;
		const confirmMsg = (this.l10n.confirmBulkDelete || 'Delete the selected %d posts?').replace('%d', count);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const items = [];
						checkedBoxes.each(function() {
							items.push({
								post_id: $(this).attr('data-post-id') || $(this).data('post-id'),
								history_id: $(this).attr('data-history-id') || $(this).data('history-id')
							});
						});

						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_bulk_delete_draft_posts',
								items: items,
								nonce: this.l10n.nonce || ''
							},
							success: (response) => {
								if (response.success) {
									const msg = (this.l10n.bulkDeleteSuccess || '%d posts deleted successfully.').replace('%d', response.data.count || count);
									window.AIPS.Utilities.showToast(msg, 'success');
									checkedBoxes.each((idx, el) => {
										$(el).closest('tr').fadeOut(400, function() {
											$(this).remove();
											window.AIPS.PostReview.updateDraftCount();
											window.AIPS.PostReview.checkEmptyState();
										});
									});
								} else {
									window.AIPS.Utilities.showToast(response.data.message || this.l10n.deleteError || 'Failed to delete posts.', 'error');
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast(this.l10n.deleteError || 'Failed to delete posts.', 'error');
							}
						});
					}
				}
			]);
		}
	},

	bulkRegenerate(checkedBoxes) {
		const count = checkedBoxes.length;
		const confirmMsg = (this.l10n.confirmBulkRegenerate || 'Regenerate the selected %d posts?').replace('%d', count);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, regenerate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						const items = [];
						checkedBoxes.each(function() {
							items.push({
								post_id: $(this).attr('data-post-id') || $(this).data('post-id'),
								history_id: $(this).attr('data-history-id') || $(this).data('history-id')
							});
						});

						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_bulk_regenerate_posts',
								items: items,
								nonce: this.l10n.nonce || ''
							},
							success: (response) => {
								if (response && response.success) {
									const successCount = (response.data && typeof response.data.success_count !== 'undefined') ? response.data.success_count : count;
									const msg = (this.l10n.bulkRegenerateSuccess || '%d posts queued for regeneration.').replace('%d', successCount);
									window.AIPS.Utilities.showToast(msg + ' Check History for progress.', 'success');

									let successIds = [];
									if (response.data) {
										if (Array.isArray(response.data.success_ids)) {
											successIds = response.data.success_ids;
										} else if (Array.isArray(response.data.items)) {
											response.data.items.forEach((item) => {
												if (item && item.status === 'success' && typeof item.post_id !== 'undefined') {
													successIds.push(item.post_id);
												}
											});
										}
									}

									let $rowsToRemove = checkedBoxes;
									if (successIds.length) {
										$rowsToRemove = checkedBoxes.filter(function() {
											return $.inArray($(this).attr('data-post-id') || $(this).data('post-id'), successIds) !== -1;
										});
									}

									$rowsToRemove.each((idx, el) => {
										$(el).closest('tr').fadeOut(400, function() {
											$(this).remove();
											window.AIPS.PostReview.updateDraftCount();
											window.AIPS.PostReview.checkEmptyState();
										});
									});

									if (response.data && response.data.failed_count) {
										let failMsg = this.l10n.bulkRegeneratePartialFailure || this.l10n.regenerateError || 'Regeneration failed for %d posts.';
										failMsg = failMsg.replace('%d', response.data.failed_count);
										window.AIPS.Utilities.showToast(failMsg, 'warning');
									}
								} else {
									window.AIPS.Utilities.showToast((response && response.data && response.data.message) || this.l10n.regenerateError || 'Failed to regenerate posts.', 'error');
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast(this.l10n.regenerateError || 'Failed to regenerate posts.', 'error');
							}
						});
					}
				}
			]);
		}
	},

	previewPost(postId) {
		const modal = this.$('#aips-post-preview-modal');
		const contentContainer = this.$('#aips-preview-content-container');
		const iframe = this.$('#aips-post-preview-iframe');
		const headerTitle = modal.find('.aips-modal-header h2');
		const T = window.AIPS.Templates;

		if (T) {
			contentContainer.show().html(T.render('aips-tmpl-preview-loading', {
				message: this.l10n.loadingPreview || 'Loading preview...'
			}));
		} else {
			contentContainer.show().html('<p>Loading preview...</p>');
		}
		iframe.hide().attr('src', '');
		headerTitle.text(this.l10n.previewTitle || 'Post Preview');

		if (this.previewModal) {
			this.previewModal.open();
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_post_preview',
				post_id: postId,
				nonce: this.l10n.nonce || ''
			},
			success: (response) => {
				if (response.success) {
					const data = response.data;

					let imageHtml = '';
					if (data.featured_image && T) {
						imageHtml = T.renderRaw('aips-tmpl-preview-image', {
							src: data.featured_image
						});
					}

					let excerptHtml = '';
					if (data.excerpt && T) {
						excerptHtml = T.render('aips-tmpl-preview-excerpt', {
							excerpt: data.excerpt
						});
					}

					let editFooterHtml = '';
					if (data.edit_url && T) {
						editFooterHtml = T.renderRaw('aips-tmpl-preview-edit-footer', {
							edit_url: data.edit_url
						});
					}

					if (T) {
						const html = T.renderRaw('aips-tmpl-preview-content', {
							title: T.escape(data.title),
							featured_image: imageHtml,
							excerpt: excerptHtml,
							content: data.content,
							edit_footer: editFooterHtml
						});
						contentContainer.html(html);
					}
				} else {
					contentContainer.html('<div class="notice notice-error inline"><p>' + (response.data.message || this.l10n.previewError || 'Preview failed.') + '</p></div>');
				}
			},
			error: () => {
				contentContainer.html('<div class="notice notice-error inline"><p>' + (this.l10n.previewError || 'Failed to load preview.') + '</p></div>');
			}
		});
	},

	updateDraftCount() {
		const visibleRows = this.$('.aips-post-review-table tbody tr:visible').length;
		this.$('#aips-draft-count').text(visibleRows);
	},

	checkEmptyState() {
		const visibleRows = this.$('.aips-post-review-table tbody tr:visible').length;

		if (visibleRows === 0) {
			this.$('.aips-post-review-table').hide();
			this.$('.tablenav').hide();

			if (this.$('.aips-empty-state').length === 0) {
				const emptyStateHtml = '<div class="aips-empty-state">' +
					'<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' +
					'<h3>' + (this.l10n.noDraftPosts || 'No Draft Posts') + '</h3>' +
					'<p>' + (this.l10n.noDraftPostsDesc || 'There are no draft posts waiting for review.') + '</p>' +
					'</div>';
				this.$('#aips-post-review-form').after(emptyStateHtml);
			} else {
				this.$('.aips-empty-state').show();
			}
		}
	},

	// -------------------------------------------------------------------------
	// AI Edit Modal Methods
	// -------------------------------------------------------------------------

	openAIEditModal(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);

		this.aiEditState.postId = $btn.attr('data-post-id') || $btn.data('post-id');
		this.aiEditState.historyId = $btn.attr('data-history-id') || $btn.data('history-id');

		if (this.aiEditModal) {
			this.aiEditModal.open();
		}
		$('body').addClass('aips-modal-open');
		this.loadAIEditComponents();
	},

	loadAIEditComponents() {
		this.$('.aips-ai-edit-loading').show();
		this.$('.aips-ai-edit-content').hide();

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_post_components',
				post_id: this.aiEditState.postId,
				history_id: this.aiEditState.historyId,
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
			},
			success: this.onAIEditComponentsLoaded.bind(this),
			error: this.onAIEditLoadError.bind(this)
		});
	},

	onAIEditComponentsLoaded(response) {
		if (response.success) {
			this.aiEditState.components = response.data.components;
			this.populateAIEditModal(response.data);
		} else {
			this.showAIEditNotice(response.data.message || this.aiEditL10n.loadError || 'Load failed.', 'error');
			this.closeAIEditModal(null, { skipConfirm: true });
		}
	},

	onAIEditLoadError() {
		this.showAIEditNotice(this.aiEditL10n.loadError || 'Load failed.', 'error');
		this.closeAIEditModal(null, { skipConfirm: true });
	},

	populateAIEditModal(data) {
		this.$('#aips-context-template').text(data.context.template_name);
		this.$('#aips-context-author').text(data.context.author_name);
		this.$('#aips-context-topic').text(data.context.topic_title);

		this.$('#aips-component-title').val(data.components.title.value);
		this.aiEditState.originalValues.title = data.components.title.value;
		this.setAIEditComponentSource('title', data.components.title.value, 'persisted');

		this.$('#aips-component-excerpt').val(data.components.excerpt.value);
		this.aiEditState.originalValues.excerpt = data.components.excerpt.value;
		this.setAIEditComponentSource('excerpt', data.components.excerpt.value, 'persisted');

		this.$('#aips-component-content').val(data.components.content.value);
		this.aiEditState.originalValues.content = data.components.content.value;
		this.setAIEditComponentSource('content', data.components.content.value, 'persisted');

		this.aiEditState.originalValues.featured_image = data.components.featured_image;
		this.setAIEditComponentSource('featured_image', data.components.featured_image, 'persisted');

		if (data.components.featured_image && data.components.featured_image.url) {
			this.$('#aips-component-image').attr('src', data.components.featured_image.url).show();
			this.$('#aips-component-image-none').hide();
		} else {
			this.$('#aips-component-image').hide();
			this.$('#aips-component-image-none').show();
		}

		this.updateAIEditCharCount('title');
		this.updateAIEditCharCount('excerpt');
		this.updateAIEditCharCount('content');

		this.$('.aips-ai-edit-loading').hide();
		this.$('.aips-ai-edit-content').show();
	},

	regenerateComponent(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const component = $btn.attr('data-component') || $btn.data('component');

		const requestData = {
			action: 'aips_regenerate_component',
			post_id: this.aiEditState.postId,
			history_id: this.aiEditState.historyId,
			component: component,
			nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
		};

		if (this.shouldCaptureManualRevision(component)) {
			requestData.current_value = this.getAIEditCurrentComponentValue(component);
			requestData.current_source = 'manual_edit';
			requestData.current_reason = 'pre_regenerate_manual';
		}

		$btn.prop('disabled', true)
			.addClass('regenerating')
			.find('.button-text').text(this.aiEditL10n.regenerating || 'Regenerating...');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: requestData,
			success: (response) => {
				this.onComponentRegenerated($btn, component, response);
			},
			error: () => {
				this.onRegenerateError($btn, component);
			}
		});
	},

	getAIEditManualSnapshots(components) {
		const snapshots = {};
		components.forEach((component) => {
			if (this.shouldCaptureManualRevision(component)) {
				snapshots[component] = this.getAIEditCurrentComponentValue(component);
			}
		});
		return snapshots;
	},

	regenerateAllComponents(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const manualSnapshots = this.getAIEditManualSnapshots(['title', 'excerpt', 'content', 'featured_image']);

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($button, this.aiEditL10n.regeneratingAll || 'Regenerating All...');
		}
		this.$('.aips-regenerate-btn').prop('disabled', true);

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_regenerate_all_components',
				post_id: this.aiEditState.postId,
				history_id: this.aiEditState.historyId,
				manual_snapshots: manualSnapshots,
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
			},
			success: (response) => {
				this.onRegenerateAllSuccess($button, response);
			},
			error: () => {
				this.onRegenerateAllError($button);
			}
		});
	},

	onRegenerateAllSuccess($button, response) {
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.resetButton($button);
		}
		this.$('.aips-regenerate-btn').prop('disabled', false);

		if (!response.success) {
			this.showAIEditNotice(response.data && response.data.message ? response.data.message : (this.aiEditL10n.regenerateAllError || 'Failed to regenerate components.'), 'error');
			return;
		}

		const regenerated = response.data.regenerated || {};
		const skipped = response.data.skipped || {};
		const errors = response.data.errors || {};
		let regeneratedCount = 0;

		Object.keys(regenerated).forEach((component) => {
			this.updateAIEditComponentValue(component, regenerated[component], 'ai_generated');
			this.aiEditState.changedComponents.add(component);
			this.$('.aips-component-section[data-component="' + component + '"]').addClass('changed');
			this.showComponentStatus(component, 'success', this.aiEditL10n.regenerateSuccess || 'Regenerated.');
			this.refreshComponentRevisions(component);
			regeneratedCount++;
		});

		Object.keys(skipped).forEach((component) => {
			this.showComponentStatus(component, 'success', skipped[component]);
		});

		Object.keys(errors).forEach((component) => {
			this.showComponentStatus(component, 'error', errors[component]);
		});

		if (regeneratedCount > 0) {
			this.showAIEditNotice(response.data.message || this.aiEditL10n.regenerateAllSuccess || 'Regenerated successfully.', 'success');
		} else {
			this.showAIEditNotice(response.data.message || this.aiEditL10n.regenerateAllError || 'No components were regenerated.', 'error');
		}
	},

	onRegenerateAllError($button) {
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.resetButton($button);
		}
		this.$('.aips-regenerate-btn').prop('disabled', false);
		this.showAIEditNotice(this.aiEditL10n.regenerateAllError || 'Network error.', 'error');
	},

	onComponentRegenerated($btn, component, response) {
		$btn.prop('disabled', false)
			.removeClass('regenerating')
			.find('.button-text').text(this.aiEditL10n.regenerate || 'Regenerate');

		if (response.success) {
			this.updateAIEditComponentValue(component, response.data.new_value, 'ai_generated');
			this.aiEditState.changedComponents.add(component);
			this.$('[data-component="' + component + '"]').closest('.aips-component-section').addClass('changed');
			this.showComponentStatus(component, 'success', this.aiEditL10n.regenerateSuccess || 'Regenerated successfully.');
			this.refreshComponentRevisions(component);
		} else {
			this.showComponentStatus(component, 'error', response.data.message || this.aiEditL10n.regenerateError || 'Regeneration failed.');
		}
	},

	setAIEditComponentSource(component, value, source) {
		const normalizedValue = this.normalizeAIEditComponentValue(component, value);
		this.aiEditState.currentSources[component] = source;
		this.aiEditState.nonManualSnapshots[component] = {
			source: source,
			value: normalizedValue
		};
	},

	normalizeAIEditComponentValue(component, value) {
		if (component === 'featured_image') {
			if (!value) return '';
			return JSON.stringify({
				attachment_id: value.attachment_id || 0,
				url: value.url || ''
			});
		}
		return String(value || '');
	},

	shouldCaptureManualRevision(component) {
		return this.aiEditState.currentSources[component] === 'manual_edit' && this.aiEditState.changedComponents.has(component);
	},

	getAIEditCurrentComponentValue(component) {
		switch (component) {
			case 'title':
				return this.$('#aips-component-title').val();
			case 'excerpt':
				return this.$('#aips-component-excerpt').val();
			case 'content':
				return this.$('#aips-component-content').val();
			case 'featured_image':
				return this.aiEditState.components.featured_image || null;
			default:
				return null;
		}
	},

	refreshComponentRevisions(component) {
		const $section = this.$('.aips-component-section[data-component="' + component + '"]');
		const $revisions = $section.find('.aips-component-revisions');

		if (!$revisions.length) return;

		$revisions.data('loaded', false);

		if ($revisions.is(':visible')) {
			this.loadComponentRevisions(component, $section);
		}
	},

	onRegenerateError($btn, component) {
		$btn.prop('disabled', false)
			.removeClass('regenerating')
			.find('.button-text').text(this.aiEditL10n.regenerate || 'Regenerate');

		this.showComponentStatus(component, 'error', this.aiEditL10n.regenerateError || 'Failed.');
	},

	updateAIEditComponentValue(component, value, source) {
		switch (component) {
			case 'title':
				this.$('#aips-component-title').val(value);
				this.updateAIEditCharCount('title');
				break;
			case 'excerpt':
				this.$('#aips-component-excerpt').val(value);
				this.updateAIEditCharCount('excerpt');
				break;
			case 'content':
				this.$('#aips-component-content').val(value);
				this.updateAIEditCharCount('content');
				break;
			case 'featured_image':
				if (value && value.url) {
					this.$('#aips-component-image').attr('src', value.url).show();
					this.$('#aips-component-image-none').hide();
					this.aiEditState.components.featured_image = value;
				} else {
					this.$('#aips-component-image').attr('src', '').hide();
					this.$('#aips-component-image-none').show();
					this.aiEditState.components.featured_image = null;
				}
				break;
		}

		if (source) {
			this.setAIEditComponentSource(component, value, source);
		}
	},

	showComponentStatus(component, type, message) {
		const $section = this.$('[data-component="' + component + '"]').closest('.aips-component-section');
		const $status = $section.find('.aips-component-status');

		$status.removeClass('success error')
			.addClass(type)
			.text(message)
			.show();

		setTimeout(() => {
			$status.fadeOut();
		}, 3000);
	},

	onAIEditComponentChange(e) {
		const $input = $(e.currentTarget);
		const component = $input.closest('.aips-component-section').attr('data-component');
		const currentValue = $input.val();
		const originalValue = this.aiEditState.originalValues[component];
		const normalizedCurrent = this.normalizeAIEditComponentValue(component, currentValue);
		const normalizedOriginal = this.normalizeAIEditComponentValue(component, originalValue);
		const snapshot = this.aiEditState.nonManualSnapshots[component];

		this.updateAIEditCharCount(component);

		if (normalizedCurrent !== normalizedOriginal) {
			this.aiEditState.changedComponents.add(component);
			$input.closest('.aips-component-section').addClass('changed');
		} else {
			this.aiEditState.changedComponents.delete(component);
			$input.closest('.aips-component-section').removeClass('changed');
		}

		if (snapshot && normalizedCurrent === snapshot.value) {
			this.aiEditState.currentSources[component] = snapshot.source;
		} else if (normalizedCurrent !== normalizedOriginal) {
			this.aiEditState.currentSources[component] = 'manual_edit';
		} else {
			this.aiEditState.currentSources[component] = 'persisted';
		}
	},

	updateAIEditCharCount(component) {
		const $input = this.$('#aips-component-' + component);
		if (!$input.length) return;
		const $count = $input.siblings('.aips-component-meta').find('.aips-char-count');

		if ($count.length) {
			const charCount = $input.val().length;
			$count.text(charCount + ' characters');
		}
	},

	saveAIEditChanges(e) {
		e.preventDefault();

		if (this.aiEditState.changedComponents.size === 0) {
			this.showAIEditNotice(this.aiEditL10n.noChanges || 'No changes to save.', 'info');
			return;
		}

		const components = {};

		this.aiEditState.changedComponents.forEach((component) => {
			switch (component) {
				case 'title':
					components.title = this.$('#aips-component-title').val();
					break;
				case 'excerpt':
					components.excerpt = this.$('#aips-component-excerpt').val();
					break;
				case 'content':
					components.content = this.$('#aips-component-content').val();
					break;
				case 'featured_image':
					if (this.aiEditState.components.featured_image) {
						components.featured_image_id = this.aiEditState.components.featured_image.attachment_id;
					}
					break;
			}
		});

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading(this.$('#aips-ai-edit-save'), this.aiEditL10n.saving || 'Saving...');
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_save_post_components',
				post_id: this.aiEditState.postId,
				components: components,
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
			},
			success: this.onAIEditSaveSuccess.bind(this),
			error: this.onAIEditSaveError.bind(this)
		});
	},

	onAIEditSaveSuccess(response) {
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.resetButton(this.$('#aips-ai-edit-save'));
		}

		if (response.success) {
			this.showAIEditNotice(response.data.message || 'Saved successfully.', 'success');
			this.closeAIEditModal(null, { skipConfirm: true });
			setTimeout(() => {
				location.reload();
			}, 1000);
		} else {
			this.showAIEditNotice(response.data.message || this.aiEditL10n.saveError || 'Save failed.', 'error');
		}
	},

	onAIEditSaveError() {
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.resetButton(this.$('#aips-ai-edit-save'));
		}
		this.showAIEditNotice(this.aiEditL10n.saveError || 'Save failed.', 'error');
	},

	closeAIEditModal(e, options) {
		options = options || {};

		if (e) {
			const $target = $(e.target);
			const isOverlay = $target.hasClass('aips-modal-overlay');
			const isCloseButton = $target.is('#aips-ai-edit-cancel, #aips-ai-edit-close') ||
			                     $target.closest('#aips-ai-edit-close, #aips-ai-edit-cancel').length > 0;

			if (!isOverlay && !isCloseButton) {
				return;
			}
		}

		if (!options.skipConfirm && this.aiEditState.changedComponents.size > 0 && window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(this.aiEditL10n.confirmClose || 'Discard unsaved changes?', 'Notice', [
				{ label: 'No, keep editing', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, discard changes',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						if (this.aiEditModal) {
							this.aiEditModal.close();
						}
						$('body').removeClass('aips-modal-open');
						this.resetAIEditState();
					}
				}
			]);
			if (e) e.stopPropagation();
			return;
		}

		if (this.aiEditModal) {
			this.aiEditModal.close();
		}
		$('body').removeClass('aips-modal-open');
		this.resetAIEditState();
	},

	resetAIEditState() {
		this.aiEditState.postId = null;
		this.aiEditState.historyId = null;
		this.aiEditState.components = {};
		this.aiEditState.changedComponents = new Set();
		this.aiEditState.originalValues = {};
		this.aiEditState.currentSources = {};
		this.aiEditState.nonManualSnapshots = {};

		this.$('#aips-component-title').val('');
		this.$('#aips-component-excerpt').val('');
		this.$('#aips-component-content').val('');
		this.$('#aips-component-image').attr('src', '').hide();
		this.$('#aips-component-image-none').show();

		this.$('.aips-component-section').removeClass('changed');
		this.$('.aips-component-status').hide();

		this.$('.aips-ai-edit-loading').show();
		this.$('.aips-ai-edit-content').hide();
	},

	showAIEditNotice(message, type) {
		const formattedType = type || 'info';
		const $p = $('<p>').text(message);
		const $notice = $('<div class="notice notice-' + formattedType + ' is-dismissible">').append($p);
		this.$('.wrap').first().prepend($notice);

		setTimeout(() => {
			$notice.fadeOut(() => {
				$notice.remove();
			});
		}, 5000);
	},

	handleAIEditKeyboard(e) {
		if (!this.$('#aips-ai-edit-modal').is(':visible')) return;

		// Escape
		if (e.keyCode === 27) {
			this.closeAIEditModal();
		}

		// Ctrl/Cmd + S
		if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
			e.preventDefault();
			this.saveAIEditChanges(e);
		}
	},

	toggleRevisionViewer(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		const componentType = $button.attr('data-component') || $button.data('component');
		const $section = $button.closest('.aips-component-section');
		const $revisions = $section.find('.aips-component-revisions');

		if ($revisions.is(':visible')) {
			$revisions.slideUp(200);
			$button.removeClass('active');
		} else {
			$revisions.slideDown(200);
			$button.addClass('active');

			if (!$revisions.data('loaded')) {
				this.loadComponentRevisions(componentType, $section);
			}
		}
	},

	loadComponentRevisions(componentType, $section) {
		const $revisions = $section.find('.aips-component-revisions');
		const $loading = $revisions.find('.aips-revisions-loading');
		const $list = $revisions.find('.aips-revisions-list');
		const $empty = $revisions.find('.aips-revisions-empty');
		const $button = $section.find('.aips-view-revisions-btn');

		$loading.show();
		$list.empty().hide();
		$empty.hide();

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_component_revisions',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				post_id: this.aiEditState.postId,
				component: componentType,
				history_id: this.aiEditState.historyId
			},
			success: (response) => {
				$loading.hide();
				$revisions.data('loaded', true);

				if (response.success && response.data.revisions && response.data.revisions.length > 0) {
					const revisions = response.data.revisions;

					let $count = $button.find('.revision-count');
					if ($count.length === 0) {
						$count = $('<span class="revision-count"></span>');
						$button.find('.button-text').after($count);
					}
					$count.text('(' + revisions.length + ')');

					revisions.forEach((revision) => {
						const $item = this.renderRevisionItem(revision, componentType);
						$list.append($item);
					});

					$list.show();
				} else {
					$empty.show();
				}
			},
			error: (xhr, status, error) => {
				$loading.hide();
				console.error('Failed to load revisions:', error);
				$list.html('<div class="notice notice-error inline"><p>Failed to load revisions. Please try again.</p></div>').show();
			}
		});
	},

	renderRevisionItem(revision, componentType) {
		const $item = $('<div class="aips-revision-item"></div>');
		$item.attr('data-revision-id', revision.id);
		const timestamp = revision.timestamp || '';
		const revisionLabel = this.getAIEditRevisionLabel(revision);

		const $content = $('<div class="aips-revision-content"></div>');

		const $meta = $('<div class="aips-revision-meta"></div>');
		$meta.append('<span class="dashicons dashicons-backup"></span>');
		$meta.append('<span class="aips-revision-source">' + _.escape(revisionLabel) + '</span>');
		$meta.append('<span class="aips-revision-timestamp">' + _.escape(timestamp) + '</span>');
		$content.append($meta);

		const $value = $('<div class="aips-revision-value aips-revision-value-' + componentType + '"></div>');

		if (componentType === 'featured_image') {
			if (revision.value && revision.value.url) {
				const sanitizedUrl = String(revision.value.url).replace(/[^a-zA-Z0-9.:/_\-?=&]/g, '');
				if (sanitizedUrl) {
					$value.empty().append(
						$('<img>', {
							src: sanitizedUrl,
							alt: 'Revision',
							style: 'max-width: 100px; height: auto; display: block; margin-top: 5px; border-radius: 4px;'
						})
					);
				}
			}
		} else {
			$value.text(revision.value || '');
		}

		$content.append($value);
		$item.append($content);

		const $actions = $('<div class="aips-revision-actions"></div>');
		const $restoreBtn = $('<button type="button" class="button button-secondary button-small aips-restore-revision-btn"></button>');
		$restoreBtn.text(this.aiEditL10n.restore || 'Restore');
		$restoreBtn.attr('data-component', componentType);
		$restoreBtn.attr('data-revision-id', revision.id);
		
		$actions.append($restoreBtn);
		$item.append($actions);

		return $item;
	},

	getAIEditRevisionLabel(revision) {
		const source = revision.source || 'manual_edit';
		const reason = revision.reason || '';

		if (source === 'persisted') {
			return this.aiEditL10n.sourcePersisted || 'Original Saved';
		}
		if (source === 'ai_generated') {
			return this.aiEditL10n.sourceRegenerated || 'AI Generated';
		}
		if (source === 'manual_edit') {
			if (reason === 'pre_regenerate_manual') {
				return this.aiEditL10n.sourceBeforeRegenerate || 'Manual (Before Regeneration)';
			}
			return this.aiEditL10n.sourceManual || 'Manual Edit';
		}
		return source;
	},

	restoreRevision(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const component = $btn.attr('data-component') || $btn.data('component');
		const revisionId = $btn.attr('data-revision-id') || $btn.data('revision-id');
		const $section = $btn.closest('.aips-component-section');

		$btn.prop('disabled', true).text(this.aiEditL10n.restoring || 'Restoring...');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_restore_component_revision',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				post_id: this.aiEditState.postId,
				component: component,
				revision_id: revisionId,
				history_id: this.aiEditState.historyId
			},
			success: (response) => {
				$btn.prop('disabled', false).text(this.aiEditL10n.restore || 'Restore');

				if (response.success) {
					this.updateAIEditComponentValue(component, response.data.restored_value, response.data.restored_source || 'manual_edit');
					this.aiEditState.changedComponents.add(component);
					$section.addClass('changed');
					this.showComponentStatus(component, 'success', this.aiEditL10n.restoreSuccess || 'Revision restored.');

					const $viewerBtn = $section.find('.aips-view-revisions-btn');
					$viewerBtn.trigger('click');
				} else {
					this.showComponentStatus(component, 'error', response.data.message || this.aiEditL10n.restoreError || 'Restore failed.');
				}
			},
			error: () => {
				$btn.prop('disabled', false).text(this.aiEditL10n.restore || 'Restore');
				this.showComponentStatus(component, 'error', this.aiEditL10n.restoreError || 'Restore failed.');
			}
		});
	},

	remove() {
		$(document).off('.aipsPostReview');
		BaseListView.prototype.remove.apply(this, arguments);
	}
});

export default PostReviewView;
