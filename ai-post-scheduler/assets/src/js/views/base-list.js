import Backbone from 'backbone';
import $ from 'jquery';

/**
 * Reusable Base View for list-based pages with search, filters, checkbox selection, and bulk actions.
 */
export const BaseListView = Backbone.View.extend({
	listSelector: '',
	rowSelector: '',
	searchSelector: '',
	selectAllSelector: '',
	checkboxSelector: '',
	bulkActionSelector: '',
	bulkApplySelector: '',
	perPageSelector: '',
	l10n: {},

	events: {
		'keyup [data-list-search]': 'onSearchKeyup',
		'search [data-list-search]': 'onSearchClear',
		'click [data-list-search-clear]': 'clearSearch',
		'change [data-list-select-all]': 'toggleSelectAll',
		'change [data-list-checkbox]': 'onSelectionChange',
		'click [data-list-bulk-apply]': 'onBulkApply',
		'change [data-list-per-page]': 'onPerPageChange'
	},

	initialize() {
		// Child views can extend this
	},

	onSearchKeyup(e) {
		const query = $(e.currentTarget).val().toLowerCase();
		this.filterList(query);
	},

	onSearchClear(e) {
		const query = $(e.currentTarget).val().toLowerCase();
		if (!query) {
			this.filterList('');
		}
	},

	clearSearch(e) {
		if (e) e.preventDefault();
		const $search = this.$(this.searchSelector || '[data-list-search]');
		if ($search.length) {
			$search.val('');
			this.filterList('');
		}
	},

	filterList(query) {
		const rows = this.$(this.rowSelector || 'tbody tr');
		let hasVisible = false;

		rows.each(function() {
			const $row = $(this);
			const text = $row.text().toLowerCase();
			if (text.indexOf(query) > -1) {
				$row.show();
				hasVisible = true;
			} else {
				$row.hide();
			}
		});

		const $noResults = this.$('[data-list-no-results]');
		const $table = this.$(this.listSelector || 'table');
		
		if (!hasVisible && query.length > 0) {
			if ($table.length) $table.hide();
			if ($noResults.length) $noResults.show();
		} else {
			if ($table.length) $table.show();
			if ($noResults.length) $noResults.hide();
		}
	},

	toggleSelectAll(e) {
		const checked = $(e.currentTarget).is(':checked');
		this.$(this.checkboxSelector || '[data-list-checkbox]').prop('checked', checked);
		this.onSelectionChange();
	},

	onSelectionChange() {
		const total = this.$(this.checkboxSelector || '[data-list-checkbox]').length;
		const checked = this.$(this.checkboxSelector || '[data-list-checkbox]:checked').length;
		
		const $selectAll = this.$(this.selectAllSelector || '[data-list-select-all]');
		if ($selectAll.length) {
			$selectAll.prop('checked', total > 0 && total === checked);
		}

		const $bulkApply = this.$(this.bulkApplySelector || '[data-list-bulk-apply]');
		if ($bulkApply.length) {
			$bulkApply.prop('disabled', checked === 0);
		}
	},

	getSelectedIds() {
		const ids = [];
		this.$(this.checkboxSelector || '[data-list-checkbox]:checked').each(function() {
			const id = $(this).val();
			if (id) {
				ids.push(id);
			}
		});
		return ids;
	},

	onBulkApply(e) {
		if (e) e.preventDefault();
		const action = this.$(this.bulkActionSelector || '[data-list-bulk-action]').val();
		if (!action || action === '-1') {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(this.l10n.selectBulkAction || 'Please select a bulk action.', 'warning');
			}
			return;
		}

		const ids = this.getSelectedIds();
		if (!ids.length) return;

		this.executeBulkAction(action, ids);
	},

	executeBulkAction(action, ids) {
		// Override in child class
	},

	onPerPageChange(e) {
		// Override in child class
	}
});
