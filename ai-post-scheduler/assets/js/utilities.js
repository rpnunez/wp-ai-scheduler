/**
 * AIPS Shared Utilities
 *
 * Provides globally accessible helpers (toast notifications, confirm dialogs)
 * shared across all AIPS admin pages.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

window.AIPS = window.AIPS || {};

(function ($) {
	'use strict';

	/**
	 * AIPS.Utilities — shared helpers for all AIPS admin pages.
	 */
	AIPS.Utilities = {

		/**
		 * Display a toast notification.
		 *
		 * @param {string}  message          - Text to display (auto-escaped unless opts.isHtml is true).
		 * @param {string}  [type='info']    - One of 'success', 'error', 'warning', 'info'.
		 * @param {Object}  [opts]           - Optional settings.
		 * @param {boolean} [opts.isHtml]    - If true, insert message as raw HTML.
		 * @param {number}  [opts.duration]  - Auto-dismiss delay in ms (0 = no auto-dismiss). Default 5000.
		 */
		showToast: function (message, type, opts) {
			type = type || 'info';
			opts = opts || {};
			var duration = opts.duration !== undefined ? opts.duration : 5000;
			var isHtml   = opts.isHtml || false;

			var iconMap = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };

			var $container = $('#aips-toast-container');
			if (!$container.length) {
				$container = $('<div id="aips-toast-container"></div>');
				$('body').append($container);
			}

			var closeLabel = (window.aipsUtilitiesL10n && aipsUtilitiesL10n.closeLabel) ? aipsUtilitiesL10n.closeLabel : 'Close notification';
			var safeMessage = isHtml ? message : $('<div>').text(message).html();

			var $toast = $('<div class="aips-toast ' + type + '">')
				.append('<span class="aips-toast-icon">' + iconMap[type] + '</span>')
				.append('<div class="aips-toast-message">' + safeMessage + '</div>')
				.append($('<button class="aips-toast-close">&times;</button>').attr('aria-label', closeLabel));

			$container.append($toast);

			$toast.find('.aips-toast-close').on('click', function () {
				$toast.addClass('closing');
				setTimeout(function () { $toast.remove(); }, 300);
			});

			if (duration > 0) {
				setTimeout(function () {
					if ($toast.parent().length) {
						$toast.addClass('closing');
						setTimeout(function () { $toast.remove(); }, 300);
					}
				}, duration);
			}
		},

		/**
		 * Display a confirmation dialog.
		 *
		 * Wraps the native window.confirm so that individual pages are
		 * isolated from the underlying implementation and a custom modal
		 * can be swapped in later without touching each call-site.
		 *
		 * @param  {string}  message - Question to present to the user.
		 * @return {boolean}         - true if the user confirmed, false otherwise.
		 */
		confirm: function (message) {
			return window.confirm(message);
		}
	};

})(jQuery);
