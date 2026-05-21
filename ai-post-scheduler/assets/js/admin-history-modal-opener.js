/**
 * History Modal Opener
 *
 * Opens the History modal directly via AJAX without navigating to the History page.
 * Works globally on all admin pages where the `aips-open-history-modal` button class is used.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.HistoryModalOpener = {
		escapeHtml: function (value) {
			return $('<div>').text(value == null ? '' : String(value)).html();
		},

		updateModalHeader: function ($modal, container) {
			var title = container && container.header_title
				? container.header_title
				: (aipsHistoryModalOpenerL10n.historyDetails || 'History Details');
			var actions = container && Array.isArray(container.header_actions)
				? container.header_actions
				: [];
			var $title = $modal.find('#aips-history-modal-title');
			var $actions = $modal.find('#aips-history-modal-actions');
			var $status = $modal.find('#aips-history-modal-status');
			var statusHtml = '';
			var actionsHtml = '';
			var self = this;

			$title.text(title);

			actions.forEach(function (action) {
				if (!action || !action.url || !action.label) {
					return;
				}

				actionsHtml += '<a href="' + self.escapeHtml(action.url) + '" target="_blank" rel="noopener noreferrer">'
					+ self.escapeHtml(action.label)
					+ '</a>';
			});

			if (container && container.status && container.status_class) {
				statusHtml = '<span class="aips-badge '
					+ self.escapeHtml(container.status_class)
					+ '">'
					+ self.escapeHtml(container.status)
					+ '</span>';
			}

			$actions.html(actionsHtml);
			$status.html(statusHtml);
		},

		init: function () {
			var self = this;
			$(document).on('click', '.aips-open-history-modal', function (e) {
				e.preventDefault();
				e.stopPropagation();
				self.openHistoryModal($(this));
			});
		},

		getAjaxConfig: function () {
			if (window.aipsAjax && window.aipsAjax.ajaxUrl && window.aipsAjax.nonce) {
				return window.aipsAjax;
			}

			if (window.aipsHistoryModalAjax && window.aipsHistoryModalAjax.ajaxUrl && window.aipsHistoryModalAjax.nonce) {
				return window.aipsHistoryModalAjax;
			}

			return null;
		},

		openHistoryModal: function ($button) {
			var historyId = parseInt($button.data('history-id') || 0, 10);
			var ajaxConfig = this.getAjaxConfig();

			if (!historyId) {
				AIPS.Utilities.showToast(aipsHistoryModalOpenerL10n.invalidHistoryId || 'Invalid history ID.', 'error');
				return;
			}

			if (!ajaxConfig) {
				AIPS.Utilities.showToast(aipsHistoryModalOpenerL10n.loadingError || 'Error loading history modal.', 'error');
				return;
			}

			var self = this;
			var $modal = $('#aips-history-modal');

			if (!$modal.length) {
				self.createModalElement();
				$modal = $('#aips-history-modal');
			}

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
							: (aipsHistoryModalOpenerL10n.loadingFailed || 'Failed to load history modal.');
						AIPS.Utilities.showToast(message, 'error');
						$modal.fadeOut(200);
						return;
					}

					var container = response.data.container || {};
					var modalHtml = response.data.modal_html || '';

					self.updateModalHeader($modal, container);
					$modal.find('#aips-history-modal-content').html(modalHtml);
					self.bindModalEvents($modal);
					$modal.fadeIn(200);
				},
				error: function () {
					AIPS.Utilities.showToast(aipsHistoryModalOpenerL10n.loadingError || 'Error loading history modal.', 'error');
					$modal.fadeOut(200);
				}
			});
		},

		createModalElement: function () {
			var modalHtml = '';
			modalHtml += '<div id="aips-history-modal" class="aips-modal" style="display: none;">';
			modalHtml += '<div class="aips-modal-content aips-modal-large">';
			modalHtml += '<div class="aips-modal-header">';
			modalHtml += '<div class="aips-history-modal-header-main">';
			modalHtml += '<h3 id="aips-history-modal-title">' + (aipsHistoryModalOpenerL10n.historyDetails || 'History Details') + '</h3>';
			modalHtml += '<div id="aips-history-modal-actions" class="aips-history-modal-header-links"></div>';
			modalHtml += '</div>';
			modalHtml += '<div class="aips-history-modal-header-side">';
			modalHtml += '<div id="aips-history-modal-status"></div>';
			modalHtml += '<button type="button" class="aips-modal-close" aria-label="' + (aipsHistoryModalOpenerL10n.closeModal || 'Close modal') + '">&times;</button>';
			modalHtml += '</div>';
			modalHtml += '</div>';
			modalHtml += '<div class="aips-modal-body" id="aips-history-modal-content"></div>';
			modalHtml += '</div>';
			modalHtml += '</div>';

			$('body').append(modalHtml);
		},

		showLoading: function ($modal) {
			var loadingHtml = '<div style="text-align: center; padding: 20px;"><span class="dashicons dashicons-update aips-spin" aria-hidden="true"></span> ' + (aipsHistoryModalOpenerL10n.loading || 'Loading…') + '</div>';
			$modal.find('#aips-history-modal-title').text(aipsHistoryModalOpenerL10n.historyDetails || 'History Details');
			$modal.find('#aips-history-modal-actions').empty();
			$modal.find('#aips-history-modal-status').empty();
			$modal.find('#aips-history-modal-content').html(loadingHtml);
			$modal.fadeIn(200);
		},

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

			$modal.find('.aips-json-viewer-toggle').off('change').on('change', function () {
				var $toggle = $(this);
				var $renderer = $toggle.closest('.aips-history-log-renderer');
				$renderer.toggleClass('aips-json-viewer-enabled', $toggle.is(':checked'));
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
				var rowTypes = String($(this).attr('data-type-ids') || $(this).data('type-id') || '')
					.split(',')
					.map(function (value) {
						return $.trim(String(value));
					})
					.filter(Boolean);
				$(this).toggle(rowTypes.indexOf(String(typeId)) !== -1);
			});
		},

		toggleLogDetail: function ($modal, $button) {
			var targetSelector = $button.data('target');
			var $target = $modal.find(targetSelector);

			if (!$target.length) {
				return;
			}

			var showLabel = aipsHistoryModalOpenerL10n.showDetails || 'Show details';
			var hideLabel = aipsHistoryModalOpenerL10n.hideDetails || 'Hide details';

			$target.slideToggle(150, function () {
				$button.text($target.is(':visible') ? hideLabel : showLabel);
			});
		},

		copyLogDetail: function ($modal, $button) {
			var targetSelector = $button.data('copy-target');
			var $target = $modal.find(targetSelector);
			var text = $target.find('pre code').text();

			if (!text) {
				return;
			}

			var self = this;
			var copyLabel = aipsHistoryModalOpenerL10n.copy || 'Copy';
			var copiedLabel = aipsHistoryModalOpenerL10n.copied || 'Copied!';

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
