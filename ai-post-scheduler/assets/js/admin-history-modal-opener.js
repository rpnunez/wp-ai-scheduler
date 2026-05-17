/**
 * History Modal Opener
 *
 * Opens the History modal directly via AJAX without navigating to the History page.
 * Works globally on all admin pages where the `aips-open-history-modal` button class is used.
 * The modal scaffold (#aips-history-modal) is rendered server-side via admin_footer.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.HistoryModalOpener = {

		/* ------------------------------------------------------------------ */
		/* Init / events                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Initialise the HistoryModalOpener module.
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Register all event listeners for the History modal opener.
		 */
		bindEvents: function () {
			$(document).on('click', '.aips-open-history-modal', this.handleHistoryModalClick.bind(this));
		},

		/* ------------------------------------------------------------------ */
		/* Handlers                                                             */
		/* ------------------------------------------------------------------ */

		/**
		 * Handle a click on an `.aips-open-history-modal` element.
		 *
		 * @param {Event} e jQuery click event.
		 */
		handleHistoryModalClick: function (e) {
			e.preventDefault();
			e.stopPropagation();
			this.openHistoryModal($(e.currentTarget));
		},

		/* ------------------------------------------------------------------ */
		/* Core                                                                 */
		/* ------------------------------------------------------------------ */

		/**
		 * Retrieve the AJAX configuration object (URL + nonce).
		 *
		 * Prefers the global `aipsAjax` object used on plugin admin pages; falls
		 * back to `aipsHistoryModalAjax` injected on native WP post screens.
		 *
		 * @return {Object|null} Config object or null when unavailable.
		 */
		getAjaxConfig: function () {
			if (window.aipsAjax && window.aipsAjax.ajaxUrl && window.aipsAjax.nonce) {
				return window.aipsAjax;
			}

			if (window.aipsHistoryModalAjax && window.aipsHistoryModalAjax.ajaxUrl && window.aipsHistoryModalAjax.nonce) {
				return window.aipsHistoryModalAjax;
			}

			return null;
		},

		/**
		 * Open the History modal for the history entry referenced by a button.
		 *
		 * @param {jQuery} $button The clicked `.aips-open-history-modal` element.
		 */
		openHistoryModal: function ($button) {
			var historyId  = parseInt($button.data('history-id') || 0, 10);
			var ajaxConfig = this.getAjaxConfig();

			if (!historyId) {
				AIPS.Utilities.showToast(aipsHistoryModalOpenerL10n.invalidHistoryId, 'error');
				return;
			}

			if (!ajaxConfig) {
				AIPS.Utilities.showToast(aipsHistoryModalOpenerL10n.loadingError, 'error');
				return;
			}

			var self   = this;
			var $modal = $('#aips-history-modal');

			self.showLoading($modal);

			$.ajax({
				url: ajaxConfig.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_history_modal_html',
					nonce: ajaxConfig.nonce,
					history_id: historyId
				},
				success: function (response) {
					if (!response || !response.success || !response.data) {
						var message = response && response.data && response.data.message
							? response.data.message
							: aipsHistoryModalOpenerL10n.loadingFailed;
						AIPS.Utilities.showToast(message, 'error');
						$modal.fadeOut(200);
						return;
					}

					var container = response.data.container || {};
					var modalHtml = response.data.modal_html || '';

					var title = container.generated_title
						? container.generated_title
						: aipsHistoryModalOpenerL10n.historyDetails + (container.id ? ' #' + container.id : '');

					$modal.find('#aips-history-modal-title').text(title);
					$modal.find('#aips-history-modal-content').html(modalHtml);
					self.bindModalEvents($modal);
					$modal.fadeIn(200);
				},
				error: function () {
					AIPS.Utilities.showToast(aipsHistoryModalOpenerL10n.loadingError, 'error');
					$modal.fadeOut(200);
				}
			});
		},

		/**
		 * Show a loading indicator inside the modal and make it visible.
		 *
		 * @param {jQuery} $modal The #aips-history-modal element.
		 */
		showLoading: function ($modal) {
			var loadingHtml = '<div style="text-align: center; padding: 20px;"><span class="dashicons dashicons-update aips-spin" aria-hidden="true"></span> ' + aipsHistoryModalOpenerL10n.loading + '</div>';
			$modal.find('#aips-history-modal-content').html(loadingHtml);
			$modal.fadeIn(200);
		},

		/**
		 * Attach interactive event handlers to the loaded modal content.
		 *
		 * @param {jQuery} $modal The #aips-history-modal element.
		 */
		bindModalEvents: function ($modal) {
			var self = this;

			$modal.find('.aips-modal-close').off('click').on('click', function (e) {
				e.preventDefault();
				$modal.fadeOut(200);
			});

			$modal.off('click.historyModal').on('click.historyModal', function (e) {
				if ($(e.target).is('#aips-history-modal')) {
					$modal.fadeOut(200);
				}
			});

			$modal.find('.aips-log-type-filter-btn').off('click').on('click', function (e) {
				e.preventDefault();
				self.filterLogsByType($modal, $(this));
			});

			$modal.find('.aips-log-toggle').off('click').on('click', function (e) {
				e.preventDefault();
				self.toggleLogDetail($modal, $(this));
			});

			$modal.find('[data-copy-target]').off('click').on('click', function (e) {
				e.preventDefault();
				self.copyLogDetail($modal, $(this));
			});

			$(document).off('keydown.historyModal').on('keydown.historyModal', function (e) {
				if (e.keyCode === 27 && $modal.is(':visible')) {
					$modal.fadeOut(200);
				}
			});
		},

		/* ------------------------------------------------------------------ */
		/* Log interaction helpers                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * Filter the log rows inside the modal to show only a specific type.
		 *
		 * @param {jQuery} $modal  The #aips-history-modal element.
		 * @param {jQuery} $button The clicked filter button.
		 */
		filterLogsByType: function ($modal, $button) {
			var typeId = $button.data('type-id');

			$modal.find('.aips-log-type-filter-btn')
				.removeClass('aips-btn-primary')
				.addClass('aips-btn-ghost');
			$button.removeClass('aips-btn-ghost').addClass('aips-btn-primary');

			var $rows = $modal.find('.aips-history-logs-table tbody tr');
			if (!typeId || typeId === 'all') {
				$rows.show();
				return;
			}

			$rows.each(function () {
				var rowType = $(this).data('type-id');
				$(this).toggle(String(rowType) === String(typeId));
			});
		},

		/**
		 * Toggle the expanded detail section for a log row.
		 *
		 * @param {jQuery} $modal  The #aips-history-modal element.
		 * @param {jQuery} $button The clicked toggle button.
		 */
		toggleLogDetail: function ($modal, $button) {
			var targetSelector = $button.data('target');
			var $target = $modal.find(targetSelector);

			if (!$target.length) {
				return;
			}

			var showLabel = aipsHistoryModalOpenerL10n.showDetails;
			var hideLabel = aipsHistoryModalOpenerL10n.hideDetails;

			$target.slideToggle(150, function () {
				$button.text($target.is(':visible') ? hideLabel : showLabel);
			});
		},

		/**
		 * Copy the log detail content to the clipboard.
		 *
		 * @param {jQuery} $modal  The #aips-history-modal element.
		 * @param {jQuery} $button The clicked copy button.
		 */
		copyLogDetail: function ($modal, $button) {
			var targetSelector = $button.data('copy-target');
			var $target = $modal.find(targetSelector);
			var text = $target.find('pre code').text();

			if (!text) {
				return;
			}

			var self        = this;
			var copyLabel   = aipsHistoryModalOpenerL10n.copy;
			var copiedLabel = aipsHistoryModalOpenerL10n.copied;

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text)
					.then(function () {
						self.showCopySuccess($button, copyLabel, copiedLabel);
					})
					.catch(function () {
						if (self.copyDetailFallback($target)) {
							self.showCopySuccess($button, copyLabel, copiedLabel);
						}
					});
				return;
			}

			if (self.copyDetailFallback($target)) {
				self.showCopySuccess($button, copyLabel, copiedLabel);
			}
		},

		/**
		 * Fallback clipboard copy using execCommand for older browsers.
		 *
		 * @param {jQuery} $target Element whose `<pre>` content should be copied.
		 * @return {boolean} Whether the copy succeeded.
		 */
		copyDetailFallback: function ($target) {
			try {
				var sel = window.getSelection();
				var range = document.createRange();
				range.selectNodeContents($target.find('pre')[0]);
				sel.removeAllRanges();
				sel.addRange(range);
				document.execCommand('copy');
				return true;
			} catch (error) {
				return false;
			}
		},

		/**
		 * Briefly change a copy button's label to confirm success then restore it.
		 *
		 * @param {jQuery} $button     The copy button.
		 * @param {string} copyLabel   Label to restore after 1.5 s.
		 * @param {string} copiedLabel Temporary success label.
		 */
		showCopySuccess: function ($button, copyLabel, copiedLabel) {
			$button.text(copiedLabel).prop('disabled', true);
			setTimeout(function () {
				$button.text(copyLabel).prop('disabled', false);
			}, 1500);
		}
	};

	$(document).ready(function () {
		AIPS.HistoryModalOpener.init();
	});

})(jQuery);
