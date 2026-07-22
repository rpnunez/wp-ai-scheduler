/**
 * AIPS Core Table — shared helpers for the plugin's client-filtered lists,
 * checkbox-select tables, and empty-state toggling.
 *
 * Does not cover server-paginated/AJAX-rendered tables — those currently use
 * three incompatible row-rendering strategies across different files and are
 * a separate, later effort (some require coordinated PHP response-shape
 * changes, unlike the pure client-side helpers here).
 *
 * Usage:
 *   AIPS.Core.Table.filterRows({
 *       term: $(e.currentTarget).val(),
 *       $rows: $('#aips-thing-table tbody tr'),
 *       $clearButton: $('#aips-thing-search-clear'),
 *       $noResults: $('#aips-thing-search-no-results'),
 *   });
 *
 *   AIPS.Core.Table.toggleAllRows({ checked: $(e.currentTarget).prop('checked'), rowCheckboxSelector: '.aips-thing-checkbox' });
 *   AIPS.Core.Table.syncSelectAll({ $selectAll: $('#cb-select-all-1'), rowCheckboxSelector: '.aips-thing-checkbox' });
 *   var ids = AIPS.Core.Table.getSelectedIds('.aips-thing-checkbox');
 *
 * @since 2.7.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.Core = AIPS.Core || {};

	/**
	 * @namespace AIPS.Core.Table
	 */
	AIPS.Core.Table = {

		/**
		 * Show/hide rows of a table based on a case-insensitive substring
		 * match against each row's text content, and optionally toggle a
		 * "clear search" button and a "no results" element.
		 *
		 * @param {Object}  options
		 * @param {string}  options.term          Raw search input value.
		 * @param {jQuery}  options.$rows          Candidate rows (re-query on
		 *                                         each call so it stays correct
		 *                                         across re-renders).
		 * @param {jQuery}  [options.$clearButton] Shown while `term` is non-empty.
		 * @param {jQuery}  [options.$noResults]   Shown when 0 rows match a
		 *                                         non-empty `term`.
		 * @return {{visible: number, total: number}}
		 */
		filterRows: function (options) {
			options = options || {};

			var term = (options.term || '').toLowerCase().trim();
			var $rows = options.$rows || $();
			var visible = 0;

			if (options.$clearButton) {
				options.$clearButton.toggle(term.length > 0);
			}

			$rows.each(function () {
				var text = $(this).text().toLowerCase();
				var show = !term || text.indexOf(term) !== -1;
				$(this).toggle(show);

				if (show) {
					visible++;
				}
			});

			if (options.$noResults) {
				options.$noResults.toggle(visible === 0 && term.length > 0);
			}

			return { visible: visible, total: $rows.length };
		},

		/**
		 * Check/uncheck every row checkbox to match a "select all" checkbox.
		 *
		 * @param {Object}  options
		 * @param {boolean} options.checked              New checked state.
		 * @param {string}  options.rowCheckboxSelector  Selector for row checkboxes.
		 * @return {void}
		 */
		toggleAllRows: function (options) {
			options = options || {};
			$(options.rowCheckboxSelector).prop('checked', !!options.checked);
		},

		/**
		 * Sync a "select all" checkbox's checked state to whether every row
		 * checkbox is currently checked. Call this after an individual row
		 * checkbox changes.
		 *
		 * @param {Object} options
		 * @param {jQuery} options.$selectAll            The "select all" checkbox.
		 * @param {string} options.rowCheckboxSelector    Selector for row checkboxes.
		 * @return {void}
		 */
		syncSelectAll: function (options) {
			options = options || {};

			var $rowCheckboxes = $(options.rowCheckboxSelector);
			var allChecked = $rowCheckboxes.length === $rowCheckboxes.filter(':checked').length;

			if (options.$selectAll) {
				options.$selectAll.prop('checked', allChecked);
			}
		},

		/**
		 * Read the `value` of every checked row checkbox.
		 *
		 * @param {string} rowCheckboxSelector Selector for row checkboxes.
		 * @return {Array<string>} Values of the checked checkboxes.
		 */
		getSelectedIds: function (rowCheckboxSelector) {
			return $(rowCheckboxSelector + ':checked').map(function () {
				return $(this).val();
			}).get();
		},

		/**
		 * Toggle a table's empty-state element.
		 *
		 * @param {Object}  options
		 * @param {jQuery}  options.$emptyState Element to show/hide.
		 * @param {boolean} options.isEmpty     Whether to show it.
		 * @return {void}
		 */
		toggleEmptyState: function (options) {
			options = options || {};

			if (options.$emptyState) {
				options.$emptyState.toggle(!!options.isEmpty);
			}
		}
	};

})(jQuery);
