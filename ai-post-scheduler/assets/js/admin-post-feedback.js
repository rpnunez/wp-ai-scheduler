(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};

	AIPS.PostFeedback = {
		config: window.aipsPostFeedback || {},
		namespace: '.aipsPostFeedback',
		selectors: {
			root: '.aips-post-feedback-controls',
			reaction: '.aips-post-feedback-reaction',
			dialog: '.aips-post-feedback-dialog',
			reason: '.aips-post-feedback-reason',
			comment: '.aips-post-feedback-comment',
			save: '.aips-post-feedback-save',
			clear: '.aips-post-feedback-clear',
			cancel: '.aips-post-feedback-cancel'
		},

		/** Initialize delegated feedback behavior. Safe to call repeatedly. */
		init: function () {
			this.bindEvents();
		},

		/** Register namespaced delegated handlers for current and future controls. */
		bindEvents: function () {
			var module = this;
			var $document = $(document);

			$document.off(this.namespace);
			$document.on('click' + this.namespace, this.selectors.reaction, function () {
				module.openDialog($(this));
			});
			$document.on('click' + this.namespace, this.selectors.save, function () {
				module.save($(this).closest(module.selectors.root));
			});
			$document.on('click' + this.namespace, this.selectors.clear, function () {
				module.clear($(this).closest(module.selectors.root));
			});
			$document.on('click' + this.namespace, this.selectors.cancel, function () {
				module.closeDialog($(this).closest(module.selectors.root));
			});
			$document.on('keydown' + this.namespace, this.selectors.dialog, function (event) {
				if (event.key === 'Escape') {
					module.closeDialog($(this).closest(module.selectors.root));
				}
			});
			$document.on('click' + this.namespace, '.aips-feedback-overrides-toggle', function () {
				var $panel = $(this).siblings('.aips-feedback-overrides');
				var open = $panel.prop('hidden');

				$panel.prop('hidden', !open);
				$(this).attr('aria-expanded', open ? 'true' : 'false');
			});
			$document.on('click' + this.namespace, '#aips-post-feedback-bulk-apply', function () {
				module.bulkApply($(this));
			});
			$document.on('change' + this.namespace, '#aips-post-feedback-bulk-action', function () {
				module.updateBulkDetails($(this).val());
			});
			$document.on('change' + this.namespace, '#aips-generated-posts-select-all', function () {
				$('.aips-generated-post-checkbox').prop('checked', this.checked);
			});
		},

		/** Build reason options without interpreting localized labels as markup. */
		populateReasons: function ($select, reaction) {
			var config = this.config;
			var labels = (config.reasons && config.reasons[reaction]) || {};

			$select.empty().append($('<option>').val('').text(config.noReason || ''));
			Object.keys(labels).forEach(function (key) {
				$select.append($('<option>').val(key).text(labels[key]));
			});
		},

		openDialog: function ($button) {
			var $root = $button.closest(this.selectors.root);
			var reaction = $button.data('reaction');
			var active = $root.attr('data-reaction') === reaction;

			$root.data('feedback-trigger', $button).attr('data-pending-reaction', reaction);
			this.populateReasons($root.find(this.selectors.reason), reaction);
			$root.find(this.selectors.reason).val(active ? ($root.attr('data-reason') || '') : '');
			$root.find(this.selectors.comment).val(active ? ($root.attr('data-comment') || '') : '');
			$root.find(this.selectors.dialog).prop('hidden', false).find('select').trigger('focus');
		},

		closeDialog: function ($root) {
			var $trigger = $root.data('feedback-trigger');

			$root.find(this.selectors.dialog).prop('hidden', true);
			if ($trigger && $trigger.length) {
				$trigger.trigger('focus');
			}
		},

		setState: function ($root, reaction, reason, comment) {
			$root.attr('data-reaction', reaction || '')
				.attr('data-reason', reason || '')
				.attr('data-comment', comment || '');
			$root.find(this.selectors.reaction).each(function () {
				$(this).attr('aria-pressed', $(this).data('reaction') === reaction ? 'true' : 'false');
			});
			$root.find(this.selectors.clear).prop('hidden', !reaction);
			$root.find(this.selectors.reason).val(reason || '');
			$root.find(this.selectors.comment).val(comment || '');
			this.closeDialog($root);
		},

		setLoading: function ($scope, loading) {
			$scope.toggleClass('is-loading', loading)
				.find('button, select, textarea, input').prop('disabled', loading);
		},

		request: function (data) {
			return $.post(this.config.ajaxUrl, $.extend({ nonce: this.config.nonce }, data));
		},

		messageFrom: function (response) {
			if (response && response.data && typeof response.data.message === 'string') {
				return response.data.message;
			}
			return this.config.error || '';
		},

		notice: function (message, type) {
			if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
				AIPS.Utilities.showToast(message, type || 'success');
			}
		},

		save: function ($root) {
			var module = this;
			var reaction = $root.attr('data-pending-reaction') || '';
			var reason = $root.find(this.selectors.reason).val() || '';
			var comment = $root.find(this.selectors.comment).val() || '';

			this.setLoading($root, true);
			this.request({ action: 'aips_post_feedback_set', post_id: $root.data('post-id'), reaction: reaction, reason_category: reason, comment: comment })
				.done(function (response) {
					if (!response || response.success !== true || !response.data) {
						module.notice(module.messageFrom(response), 'error');
						return;
					}
					module.setState($root, reaction, reason, comment);
					module.notice(module.config.saved || '', 'success');
				})
				.fail(function () {
					module.notice(module.config.error || '', 'error');
				})
				.always(function () {
					module.setLoading($root, false);
				});
		},

		clear: function ($root) {
			var module = this;

			this.setLoading($root, true);
			this.request({ action: 'aips_post_feedback_clear', post_id: $root.data('post-id') })
				.done(function (response) {
					if (!response || response.success !== true || !response.data) {
						module.notice(module.messageFrom(response), 'error');
						return;
					}
					module.setState($root, '', '', '');
					module.notice(module.config.cleared || '', 'success');
				})
				.fail(function () {
					module.notice(module.config.error || '', 'error');
				})
				.always(function () {
					module.setLoading($root, false);
				});
		},

		bulkApply: function ($button) {
			var module = this;
			var $scope = $button.closest('.aips-post-feedback-bulk');
			var ids = $('.aips-generated-post-checkbox:checked').map(function () { return $(this).val(); }).get();
			var reaction = $('#aips-post-feedback-bulk-action').val();
			var reason = $('#aips-post-feedback-bulk-reason').val() || '';
			var comment = $('#aips-post-feedback-bulk-comment').val() || '';

			if (!ids.length || !reaction) {
				this.notice(this.config.selectPosts || '', 'warning');
				return;
			}
			// Guard against a second click landing while the first request is
			// still in flight; setLoading only disables controls when it finds
			// the wrapper element, so a class rename would silently re-open a
			// concurrency window that duplicates every feedback row.
			if (this.bulkInFlight) {
				return;
			}
			this.bulkInFlight = true;
			this.setLoading($scope, true);
			this.request({ action: 'aips_post_feedback_bulk', post_ids: ids, reaction: reaction, reason_category: reason, comment: comment })
				.done(function (response) {
					if (!response || response.success !== true || !response.data) {
						module.notice(module.messageFrom(response), 'error');
						return;
					}
					(response.data.succeeded || []).forEach(function (id) {
						var cleared = reaction === 'cleared';
						module.setState($(module.selectors.root + '[data-post-id="' + id + '"]'), cleared ? '' : reaction, cleared ? '' : reason, cleared ? '' : comment);
					});
					var failed = Object.keys(response.data.failed || {}).length;
					module.notice(failed ? (module.config.partial || '') : (module.config.saved || ''), failed ? 'warning' : 'success');
				})
				.fail(function () {
					module.notice(module.config.error || '', 'error');
				})
				.always(function () {
					module.bulkInFlight = false;
					module.setLoading($scope, false);
				});
		},

		updateBulkDetails: function (reaction) {
			var hasDetails = reaction === 'liked' || reaction === 'disliked';

			this.populateReasons($('#aips-post-feedback-bulk-reason'), hasDetails ? reaction : '');
			$('.aips-post-feedback-bulk-details').prop('hidden', !hasDetails);
			if (!hasDetails) {
				$('#aips-post-feedback-bulk-comment').val('');
			}
		}
	};

	$(function () {
		AIPS.PostFeedback.init();
	});
})(jQuery);
