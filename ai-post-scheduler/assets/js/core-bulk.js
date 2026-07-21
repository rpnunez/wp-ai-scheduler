/**
 * AIPS Core Bulk — shared helpers for bulk-action toolbars and dispatch.
 *
 * Most bulk actions in this plugin already send ONE request with an array of
 * IDs to a matching PHP `ajax_bulk_*` handler that does a single DB pass —
 * `dispatch()` is for those (pure UI-glue deduplication, no PHP changes
 * needed). A small minority of actions have no bulk PHP endpoint and instead
 * fire one request per selected item — `runForEach()` is for those, and
 * composes `AIPS.Core.Http.ajaxRequest()` per item with `Promise.allSettled()`.
 *
 * Both helpers compose `AIPS.Core.Http` (AJAX), `AIPS.Core.Table.getSelectedIds`
 * (reading which rows are selected), and `AIPS.Core.Modal.confirmDelete`
 * (optional confirmation) rather than reimplementing any of them.
 *
 * Usage:
 *   AIPS.Core.Bulk.updateToolbarState({
 *       rowCheckboxSelector: '.aips-thing-checkbox',
 *       $toolbarActions: $('#aips-bulk-apply, #aips-bulk-unselect'),
 *       $countLabel: $('#aips-bulk-count'),
 *   });
 *
 *   AIPS.Core.Bulk.dispatch({
 *       action: 'aips_bulk_delete_things',
 *       ids: AIPS.Core.Table.getSelectedIds('.aips-thing-checkbox'),
 *       confirmMessage: aipsThingL10n.bulkDeleteConfirm,
 *       onSuccess: function () { location.reload(); },
 *   });
 *
 *   AIPS.Core.Bulk.runForEach({
 *       ids: authorIds,
 *       action: 'aips_generate_topics_now',
 *       buildData: function (id) { return { author_id: id }; },
 *       toastSummary: false,
 *       onComplete: function (successCount) { ... },
 *   });
 *
 * @since 2.7.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.Core = AIPS.Core || {};

	/**
	 * Default per-item success check for `runForEach()`'s
	 * `Promise.allSettled()` results.
	 *
	 * @param {Object} result A `Promise.allSettled` result entry.
	 * @return {boolean}
	 */
	function defaultSuccessPredicate(result) {
		return result.status === 'fulfilled' && result.value && result.value.success;
	}

	/**
	 * @namespace AIPS.Core.Bulk
	 */
	AIPS.Core.Bulk = {

		/**
		 * Fire one AJAX request per id (for actions with no bulk PHP
		 * endpoint), aggregate the results, and optionally show one summary
		 * toast.
		 *
		 * @param {Object}   options
		 * @param {Array}    options.ids                Item ids to process.
		 * @param {string}   options.action              `wp_ajax_*` action fired once per id.
		 * @param {Function} [options.buildData]         `function(id)` → POST data object.
		 *                                               Default `function(id){ return {id: id}; }`.
		 * @param {Function} [options.successPredicate]  `function(settledResult)` → boolean.
		 *                                               Default checks `result.value.success`.
		 * @param {string}   [options.nonce]             Nonce override forwarded to
		 *                                               `AIPS.Core.Http.ajaxRequest()`, for
		 *                                               pages that localize their own nonce
		 *                                               rather than using `aipsAjax.nonce`.
		 * @param {boolean}  [options.toastSummary]      Auto-show a "%d of %d succeeded"
		 *                                               toast. Default `true`. Set `false`
		 *                                               when the caller wants bespoke
		 *                                               success/failure messaging via
		 *                                               `onComplete`.
		 * @param {Function} [options.onComplete]        `function(successCount, totalCount, results)`.
		 * @return {Promise} The `Promise.allSettled()` promise.
		 */
		runForEach: function (options) {
			options = options || {};

			var ids = options.ids || [];
			var buildData = typeof options.buildData === 'function'
				? options.buildData
				: function (id) { return { id: id }; };
			var successPredicate = typeof options.successPredicate === 'function'
				? options.successPredicate
				: defaultSuccessPredicate;
			var toastSummary = options.toastSummary !== false;

			var requests = ids.map(function (id) {
				return AIPS.Core.Http.ajaxRequest({
					action: options.action,
					data: buildData(id),
					nonce: options.nonce,
					toastOnError: false
				});
			});

			return Promise.allSettled(requests).then(function (results) {
				var successCount = results.filter(successPredicate).length;
				var totalCount = results.length;

				if (toastSummary) {
					AIPS.Utilities.showToast(
						successCount + ' of ' + totalCount + ' succeeded.',
						successCount === totalCount ? 'success' : (successCount > 0 ? 'warning' : 'error')
					);
				}

				if (typeof options.onComplete === 'function') {
					options.onComplete(successCount, totalCount, results);
				}

				return results;
			});
		},

		/**
		 * Enable/disable bulk-action toolbar controls and update a
		 * "N selected" label based on the current selection count.
		 *
		 * @param {Object} options
		 * @param {string} [options.rowCheckboxSelector] Selector for row checkboxes;
		 *                                                used to compute `count` when
		 *                                                `options.count` isn't given.
		 * @param {number} [options.count]                Explicit selection count,
		 *                                                overrides `rowCheckboxSelector`.
		 * @param {jQuery} [options.$toolbarActions]       Elements disabled when count is 0.
		 * @param {jQuery} [options.$countLabel]           Element whose text is set from
		 *                                                `countTemplate`.
		 * @param {string} [options.countTemplate]         Default `'%d selected'`; `%d`
		 *                                                is replaced with the count.
		 * @return {number} The resolved selection count.
		 */
		updateToolbarState: function (options) {
			options = options || {};

			var count = typeof options.count === 'number'
				? options.count
				: $(options.rowCheckboxSelector + ':checked').length;

			if (options.$toolbarActions) {
				options.$toolbarActions.prop('disabled', count === 0);
			}

			if (options.$countLabel) {
				var template = options.countTemplate || '%d selected';
				options.$countLabel.text(template.replace('%d', count));
			}

			return count;
		},

		/**
		 * Dispatch a single-request bulk action (an array of ids to an
		 * existing `ajax_bulk_*` PHP handler), optionally gated behind a
		 * confirmation dialog.
		 *
		 * @param {Object}   options
		 * @param {string}   options.action           `wp_ajax_*` bulk action name.
		 * @param {Array}    options.ids               Selected item ids.
		 * @param {string}   [options.idsField]        POST field name for `options.ids`.
		 *                                             Default `'ids'` (the majority
		 *                                             convention); some handlers expect
		 *                                             a different name (e.g. `topic_ids`).
		 * @param {Object}   [options.data]            Extra POST fields merged in
		 *                                             alongside the ids field.
		 * @param {string}   [options.nonce]           Nonce override forwarded to
		 *                                             `AIPS.Core.Http.ajaxRequest()`.
		 * @param {string}   [options.confirmMessage]  When given, gates the request
		 *                                             behind `AIPS.Core.Modal.confirmDelete()`.
		 *                                             Omit for non-destructive actions
		 *                                             (e.g. bulk-activate).
		 * @param {string}   [options.confirmHeading]
		 * @param {string}   [options.confirmLabel]
		 * @param {string}   [options.cancelLabel]
		 * @param {jQuery}   [options.$button]
		 * @param {string}   [options.loadingLabel]
		 * @param {boolean}  [options.loadingLabelIsHtml]
		 * @param {boolean}  [options.toastOnError]  Default `true`. Set `false` when the
		 *                                           caller shows its own error feedback
		 *                                           via `onError`.
		 * @param {string}   [options.errorFallback]
		 * @param {Function} [options.onSuccess]
		 * @param {Function} [options.onError]
		 * @return {void}
		 */
		dispatch: function (options) {
			options = options || {};

			function fire() {
				var idsField = options.idsField || 'ids';
				var idsData = {};
				idsData[idsField] = options.ids;

				AIPS.Core.Http.ajaxRequest({
					action: options.action,
					data: $.extend(idsData, options.data || {}),
					nonce: options.nonce,
					$button: options.$button,
					loadingLabel: options.loadingLabel,
					loadingLabelIsHtml: options.loadingLabelIsHtml,
					toastOnError: options.toastOnError,
					errorFallback: options.errorFallback,
					onSuccess: options.onSuccess,
					onError: options.onError
				});
			}

			if (options.confirmMessage) {
				AIPS.Core.Modal.confirmDelete({
					message: options.confirmMessage,
					heading: options.confirmHeading,
					confirmLabel: options.confirmLabel,
					cancelLabel: options.cancelLabel,
					onConfirm: fire
				});
			} else {
				fire();
			}
		}
	};

})(jQuery);
