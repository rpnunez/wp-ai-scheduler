(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.ExistingPosts = {
		currentSuggestionId: 0,

		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$(document).on('click', '.aips-existing-review', this.onReviewClick.bind(this));
			$(document).on('click', '.aips-existing-apply-all', this.onApplyAllClick.bind(this));
			$(document).on('click', '.aips-existing-dismiss-all', this.onDismissAllClick.bind(this));
			$(document).on('click', '#aips-existing-post-review-close, #aips-existing-post-review-modal .aips-modal-overlay', this.closeModal.bind(this));
			$(document).on('click', '#aips-existing-review-apply-selected', this.onApplySelected.bind(this));
			$(document).on('click', '#aips-existing-review-accept-all', this.onAcceptAll.bind(this));
			$(document).on('click', '#aips-existing-review-dismiss-selected', this.onDismissSelected.bind(this));
		},

		onReviewClick: function(e) {
			e.preventDefault();
			var suggestionId = parseInt($(e.currentTarget).closest('tr').data('suggestion-id'), 10) || 0;
			if (!suggestionId) {
				return;
			}

			this.currentSuggestionId = suggestionId;
			this.loadDetail(suggestionId);
		},

		onApplyAllClick: function(e) {
			e.preventDefault();
			var suggestionId = parseInt($(e.currentTarget).closest('tr').data('suggestion-id'), 10) || 0;
			if (!suggestionId) {
				return;
			}

			this.applyOrDismiss('apply', suggestionId, ['all']);
		},

		onDismissAllClick: function(e) {
			e.preventDefault();
			var suggestionId = parseInt($(e.currentTarget).closest('tr').data('suggestion-id'), 10) || 0;
			if (!suggestionId) {
				return;
			}

			this.applyOrDismiss('dismiss', suggestionId, ['all']);
		},

		loadDetail: function(suggestionId) {
			var self = this;

			$('#aips-existing-post-review-modal').fadeIn(150);
			$('.aips-existing-review-loading').show();
			$('#aips-existing-review-content').empty();

			$.post(aipsExistingPostsL10n.ajaxUrl, {
				action: 'aips_existing_posts_get_suggestion_detail',
				suggestion_id: suggestionId,
				nonce_review: aipsExistingPostsL10n.nonceReview
			})
				.done(function(resp) {
					if (!resp || !resp.success || !resp.data) {
						AIPS.Utilities.showToast(aipsExistingPostsL10n.detailError, 'error');
						return;
					}

					self.renderDetail(resp.data);
				})
				.fail(function() {
					AIPS.Utilities.showToast(aipsExistingPostsL10n.detailError, 'error');
				})
				.always(function() {
					$('.aips-existing-review-loading').hide();
				});
		},

		renderDetail: function(data) {
			var items = data.items || [];
			var grouped = {};

			items.forEach(function(item) {
				if (!grouped[item.component]) {
					grouped[item.component] = [];
				}

				grouped[item.component].push(item);
			});

			var html = '<div class="aips-existing-review-header"><h3>' + AIPS.Utilities.escapeHtml((data.suggestion && data.suggestion.post_title) || '') + '</h3></div>';

			Object.keys(grouped).forEach(function(component) {
				html += '<section class="aips-existing-review-component">';
				html += '<h4>' + component + '</h4>';

				grouped[component].forEach(function(item) {
					var original = '';
					var suggested = '';

					try {
						original = JSON.parse(item.original_value || '""');
					} catch (e) {
						original = item.original_value || '';
					}

					try {
						suggested = JSON.parse(item.suggested_value || '""');
					} catch (e2) {
						suggested = item.suggested_value || '';
					}

					html += '<article class="aips-existing-review-item" data-item-id="' + parseInt(item.id, 10) + '">';
					html += '<label><input type="checkbox" class="aips-existing-item-checkbox" checked value="' + parseInt(item.id, 10) + '"> ' + (item.item_type || 'suggestion') + '</label>';
					html += '<div class="aips-existing-review-diff">';
					html += '<div class="aips-existing-review-col"><strong>' + aipsExistingPostsL10n.originalLabel + '</strong><pre>' + AIPS.Utilities.escapeHtml(typeof original === 'string' ? original : JSON.stringify(original, null, 2)) + '</pre></div>';
					html += '<div class="aips-existing-review-col"><strong>' + aipsExistingPostsL10n.suggestedLabel + '</strong><pre>' + AIPS.Utilities.escapeHtml(typeof suggested === 'string' ? suggested : JSON.stringify(suggested, null, 2)) + '</pre></div>';
					html += '</div>';
					html += '<p class="aips-existing-review-rationale">' + AIPS.Utilities.escapeHtml(item.rationale || '') + '</p>';
					html += '</article>';
				});

				html += '</section>';
			});

			$('#aips-existing-review-content').html(html);
		},

		onApplySelected: function(e) {
			e.preventDefault();
			this.applyOrDismiss('apply', this.currentSuggestionId, this.getSelectedIds());
		},

		onAcceptAll: function(e) {
			e.preventDefault();
			this.applyOrDismiss('apply', this.currentSuggestionId, ['all']);
		},

		onDismissSelected: function(e) {
			e.preventDefault();
			this.applyOrDismiss('dismiss', this.currentSuggestionId, this.getSelectedIds());
		},

		applyOrDismiss: function(mode, suggestionId, itemIds) {
			if (!suggestionId || !itemIds || itemIds.length === 0) {
				AIPS.Utilities.showToast(aipsExistingPostsL10n.selectItemsError, 'error');
				return;
			}

			var action = mode === 'apply' ? 'aips_existing_posts_apply_suggestions' : 'aips_existing_posts_dismiss_suggestions';
			var nonceField = mode === 'apply' ? { nonce_apply: aipsExistingPostsL10n.nonceApply } : { nonce_dismiss: aipsExistingPostsL10n.nonceDismiss };
			var payload = {
				action: action,
				suggestion_id: suggestionId,
				item_ids: itemIds
			};

			$.extend(payload, nonceField);

			$.post(aipsExistingPostsL10n.ajaxUrl, payload).done(function(resp) {
				if (!resp || !resp.success) {
					AIPS.Utilities.showToast((resp && resp.data && resp.data.message) || aipsExistingPostsL10n.updateError, 'error');
					return;
				}

				AIPS.Utilities.showToast((resp.data && resp.data.message) || aipsExistingPostsL10n.updateSuccess, 'success');
				window.location.reload();
			});
		},

		getSelectedIds: function() {
			return $('.aips-existing-item-checkbox:checked').map(function() {
				return parseInt($(this).val(), 10) || 0;
			}).get().filter(function(id) {
				return id > 0;
			});
		},

		closeModal: function(e) {
			if (e) {
				e.preventDefault();
			}

			$('#aips-existing-post-review-modal').fadeOut(150);
			$('#aips-existing-review-content').empty();
			this.currentSuggestionId = 0;
		}
	};

	$(document).ready(function() {
		AIPS.ExistingPosts.init();
	});
})(jQuery);
