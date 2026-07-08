(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * First-wave cross-module consumers for the shared wp.hooks event layer.
	 * These listeners keep UI reactions and History refresh behavior decoupled
	 * from the producers that trigger them.
	 */
	AIPS.EventConsumers = {
		/**
		 * Register the initial shared listeners.
		 */
		init: function () {
			if (!AIPS.Events) {
				return;
			}

			AIPS.Events.addAction(
				'aips.ui.toast.shown',
				'aips/event-consumers',
				this.decorateHistoryToast.bind(this)
			);
			AIPS.Events.addAction(
				'aips.ui.notice.shown',
				'aips/event-consumers',
				this.enhanceNoticeRegion.bind(this)
			);

			[
				'aips.postReview.regenerationQueued',
				'aips.postReview.bulkRegenerationQueued',
				'aips.aiEdit.regenerateAllCompleted',
				'aips.aiEdit.componentRegenerated',
				'aips.aiEdit.saved'
			].forEach(function (hookName) {
				AIPS.Events.addAction(
					hookName,
					'aips/event-consumers',
					AIPS.EventConsumers.requestHistoryRefresh.bind(AIPS.EventConsumers)
				);
			});
		},

		/**
		 * Resolve the History page URL from event options or localized config.
		 *
		 * @param {Object} options Optional event options payload.
		 *
		 * @return {string} History page URL or an empty string.
		 */
		getHistoryPageUrl: function (options) {
			if (options && options.historyPageUrl) {
				return String(options.historyPageUrl);
			}

			if (window.aipsAjax && window.aipsAjax.historyPageUrl) {
				return String(window.aipsAjax.historyPageUrl);
			}

			return '';
		},

		/**
		 * Add contextual History actions to the newest toast after it renders.
		 *
		 * @param {Object} payload Toast event payload.
		 */
		decorateHistoryToast: function (payload) {
			var options = payload && payload.options ? payload.options : {};
			var historyId = parseInt(options.historyId || 0, 10);
			var historyPageUrl = this.getHistoryPageUrl(options);
			var $toast = $('#aips-toast-container .aips-toast').last();
			var $message;
			var $action;

			if (!$toast.length || (!historyId && !historyPageUrl) || $toast.data('aipsHistoryActionBound')) {
				return;
			}

			$message = $toast.find('.aips-toast-message').first();
			if (!$message.length) {
				return;
			}

			if (historyId) {
				// Reuse the shared event contract so toast actions do not depend on
				// History modal internals.
				$action = $('<button type="button" class="button-link">')
					.text(options.historyButtonLabel || 'View History')
					.on('click', function (e) {
						e.preventDefault();
						AIPS.Events.emitAction('aips.history.modal.openRequested', {
							historyId: historyId,
							source: options.eventSource || 'toast-action'
						});
					});
			} else {
				$action = $('<a class="button-link">')
					.attr('href', historyPageUrl)
					.text(options.historyPageLabel || 'Open History');
			}

			$message.append(' ').append($action);
			$toast.data('aipsHistoryActionBound', true);
		},

		/**
		 * Improve notice-region accessibility once shared notice rendering finishes.
		 *
		 * @param {Object} payload Notice event payload.
		 */
		enhanceNoticeRegion: function (payload) {
			var region = payload && payload.region ? String(payload.region) : '';
			var $region;

			if (!region) {
				return;
			}

			$region = $(region);
			if (!$region.length) {
				return;
			}

			$region.attr({
				role: payload && payload.type === 'error' ? 'alert' : 'status',
				'aria-live': payload && payload.type === 'error' ? 'assertive' : 'polite',
				'aria-atomic': 'true'
			});
		},

		/**
		 * Convert downstream workflow events into a single History reload request.
		 *
		 * @param {Object} payload Producer event payload.
		 */
		requestHistoryRefresh: function (payload) {
			AIPS.Events.emitAction('aips.history.reloadRequested', {
				source: payload && payload.source ? payload.source : 'event-consumer',
				reason: 'downstream-update',
				historyId: payload && payload.historyId ? payload.historyId : null
			});
		}
	};

	$(document).ready(function () {
		AIPS.EventConsumers.init();
	});
}(jQuery));
