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
		 *                                             Reset is guaranteed to run before `onSuccess`/
		 *                                             `onError`, even if that callback throws.
		 * @param {string}   [options.loadingLabel]    Label passed to `setButtonLoading()`. When
		 *                                             omitted, `options.$button` is only disabled —
		 *                                             its content is left untouched rather than being
		 *                                             blanked to an empty label.
		 * @param {boolean}  [options.loadingLabelIsHtml] When `true`, `loadingLabel` is inserted as
		 *                                             raw HTML (e.g. a dashicon spinner) rather than
		 *                                             escaped text — forwarded to `setButtonLoading()`.
		 * @param {boolean}  [options.toastOnError]    Auto-show an error toast via
		 *                                             `AIPS.Utilities.showToast()` on failure.
		 *                                             Default `true`. Set `false` when the caller
		 *                                             renders its own error UI (e.g. an inline log).
		 * @param {string}   [options.errorFallback]   Message used when the server/response gives
		 *                                             none (see `getErrorMessage()`).
		 * @param {Function} [options.onSuccess]       `function(data, response)` — called when
		 *                                             `response.success` is true. `data` is
		 *                                             `response.data` (or `{}` if absent).
		 * @param {Function} [options.onError]         `function(message, response, isTransportError)`
		 *                                             — called on failure. `response` is the parsed
		 *                                             JSON body when the server sent one — including
		 *                                             non-2xx responses such as
		 *                                             `AIPS_Ajax_Response::permission_denied()`/
		 *                                             `not_found()`, which jQuery still routes through
		 *                                             its failure path — or `undefined` when no body
		 *                                             could be parsed at all (a true network/transport
		 *                                             failure). `isTransportError` is `true` when
		 *                                             jQuery treated the request as failed (any
		 *                                             non-2xx status or transport-level failure),
		 *                                             `false` when the server responded 2xx with
		 *                                             `response.success === false`.
		 *
		 * @return {jqXHR} The underlying jQuery XHR/promise object.
		 */
		ajaxRequest: function (options) {
			options = options || {};

			var url = options.url || (window.aipsAjax && aipsAjax.ajaxUrl) || window.ajaxurl;
			var nonce = options.nonce !== undefined
				? options.nonce
				: ((window.aipsAjax && aipsAjax.nonce) || '');
			var data = $.extend({}, options.data || {}, { action: options.action, nonce: nonce });
			var $button = options.$button;
			var toastOnError = options.toastOnError !== false;

			if ($button) {
				if (options.loadingLabel) {
					AIPS.Utilities.setButtonLoading($button, options.loadingLabel, { isHtml: !!options.loadingLabelIsHtml });
				} else {
					$button.prop('disabled', true);
				}
			}

			var xhr = $.ajax({
				url: url,
				type: options.method || 'POST',
				dataType: 'json',
				data: data
			});

			// Registered before .done()/.fail() below so the button is always
			// restored first, even if a caller's onSuccess/onError callback throws
			// (jQuery's Callbacks list has no exception isolation between callbacks).
			if ($button) {
				xhr.always(function () {
					AIPS.Utilities.resetButton($button);
				});
			}

			function handleFailure(response, isTransportError) {
				var message = AIPS.Core.Http.getErrorMessage(response, options.errorFallback);

				if (toastOnError) {
					AIPS.Utilities.showToast(message, 'error');
				}
				if (typeof options.onError === 'function') {
					options.onError(message, response, isTransportError);
				}
			}

			xhr.done(function (response) {
				if (response && response.success) {
					if (typeof options.onSuccess === 'function') {
						options.onSuccess(response.data || {}, response);
					}
					return;
				}

				handleFailure(response, false);
			});

			xhr.fail(function (jqXHR) {
				// Non-2xx HTTP responses (e.g. AIPS_Ajax_Response::permission_denied()/
				// not_found()) still carry a parsed JSON body on jqXHR.responseJSON —
				// read it so getErrorMessage() can surface the server's real message
				// instead of always falling back to a generic one.
				handleFailure(jqXHR ? jqXHR.responseJSON : undefined, true);
			});

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
