/**
 * AIPS Core — shared low-level primitives used across admin JS modules.
 *
 * `AIPS.Core.Http` centralizes the AJAX request/response boilerplate that was
 * previously duplicated at every `$.ajax`/`$.post` call site: nonce/URL
 * resolution, `{success, data}` response-shape handling (matching the PHP
 * `AIPS_Ajax_Response` contract), error-message normalization, optional
 * button loading-state integration, and error toasts.
 *
 * Usage:
 *   AIPS.Core.Http.ajaxRequest({
 *       action: 'aips_save_thing',
 *       data: { id: 5, name: 'Example' },
 *       $button: $saveBtn,
 *       loadingLabel: aipsAdminL10n.saving,
 *       errorFallback: aipsThingL10n.saveFailed,
 *       onSuccess: function (data) { ... },
 *   });
 *
 * The call returns the underlying jqXHR so callers that need `.done()`/
 * `.fail()`/`.always()` chaining, or that fire several requests in parallel
 * via `Promise.allSettled()`, can still do so.
 *
 * @since 2.7.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.Core = AIPS.Core || {};

	/**
	 * @namespace AIPS.Core.Http
	 */
	AIPS.Core.Http = {

		/**
		 * Fire an AJAX request against `admin-ajax.php` and normalize its
		 * `{success, data}` response.
		 *
		 * @param {Object}   options                 Request options.
		 * @param {string}   options.action           Required. The `wp_ajax_*` action name.
		 * @param {Object}   [options.data]           Extra fields merged into the POST body.
		 * @param {string}   [options.nonce]          Nonce override. Defaults to `aipsAjax.nonce`.
		 *                                             Pages with multiple distinct nonces (e.g. one
		 *                                             per operation) must pass this explicitly.
		 * @param {string}   [options.url]            URL override. Defaults to `aipsAjax.ajaxUrl`,
		 *                                             falling back to the WP core `ajaxurl` global.
		 * @param {string}   [options.method]         HTTP method. Default `'POST'`.
		 * @param {jQuery}   [options.$button]         Button to auto-manage via
		 *                                             `AIPS.Utilities.setButtonLoading()` /
		 *                                             `resetButton()` for the lifetime of the request.
		 * @param {string}   [options.loadingLabel]    Label passed to `setButtonLoading()`.
		 *                                             Only used when `options.$button` is given.
		 * @param {boolean}  [options.toastOnError]    Auto-show an error toast via
		 *                                             `AIPS.Utilities.showToast()` on failure.
		 *                                             Default `true`. Set `false` when the caller
		 *                                             renders its own error UI (e.g. an inline log).
		 * @param {string}   [options.errorFallback]   Message used when the server/response gives
		 *                                             none (see `getErrorMessage()`).
		 * @param {Function} [options.onSuccess]       `function(data, response)` — called when
		 *                                             `response.success` is true. `data` is
		 *                                             `response.data` (or `{}` if absent).
		 * @param {Function} [options.onError]         `function(message, response)` — called on
		 *                                             both application-level failure
		 *                                             (`response.success === false`) and
		 *                                             network/transport failure. `response` is
		 *                                             `null` for network-level failures, since
		 *                                             there is no response body to parse.
		 *
		 * @return {jqXHR} The underlying jQuery XHR/promise object.
		 */
		ajaxRequest: function (options) {
			options = options || {};

			var url = options.url || (window.aipsAjax && aipsAjax.ajaxUrl) || window.ajaxurl;
			var nonce = options.nonce !== undefined
				? options.nonce
				: ((window.aipsAjax && aipsAjax.nonce) || '');
			var data = $.extend({ action: options.action, nonce: nonce }, options.data || {});
			var $button = options.$button;
			var toastOnError = options.toastOnError !== false;

			if ($button) {
				AIPS.Utilities.setButtonLoading($button, options.loadingLabel || '');
			}

			var xhr = $.ajax({
				url: url,
				type: options.method || 'POST',
				dataType: 'json',
				data: data
			});

			xhr.done(function (response) {
				if (response && response.success) {
					if (typeof options.onSuccess === 'function') {
						options.onSuccess(response.data || {}, response);
					}
					return;
				}

				var message = AIPS.Core.Http.getErrorMessage(response, options.errorFallback);

				if (toastOnError) {
					AIPS.Utilities.showToast(message, 'error');
				}
				if (typeof options.onError === 'function') {
					options.onError(message, response);
				}
			});

			xhr.fail(function () {
				var message = options.errorFallback
					|| (window.aipsAdminL10n && aipsAdminL10n.errorTryAgain)
					|| 'An error occurred. Please try again.';

				if (toastOnError) {
					AIPS.Utilities.showToast(message, 'error');
				}
				if (typeof options.onError === 'function') {
					options.onError(message, null);
				}
			});

			if ($button) {
				xhr.always(function () {
					AIPS.Utilities.resetButton($button);
				});
			}

			return xhr;
		},

		/**
		 * Normalize an `AIPS_Ajax_Response`-shaped error response into a single
		 * display-ready message string.
		 *
		 * Resolution order: `response.data.message` (object form) →
		 * `response.data` (legacy plain-string form) → the caller-supplied
		 * `fallback` → `aipsAdminL10n.errorOccurred` → a hardcoded default.
		 *
		 * @param {Object} response          The parsed JSON response (may be undefined/null).
		 * @param {string} [fallback]        Caller-supplied fallback message.
		 * @return {string} A non-empty, display-ready error message.
		 */
		getErrorMessage: function (response, fallback) {
			if (response && response.data) {
				if (typeof response.data === 'string' && response.data) {
					return response.data;
				}
				if (typeof response.data.message === 'string' && response.data.message) {
					return response.data.message;
				}
			}

			if (fallback) {
				return fallback;
			}

			if (window.aipsAdminL10n && aipsAdminL10n.errorOccurred) {
				return aipsAdminL10n.errorOccurred;
			}

			return 'An error occurred.';
		}
	};

})(jQuery);
