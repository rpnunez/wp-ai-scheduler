import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseListView } from './base-list';
import { BaseModalView } from './base-modal';
import { PostSliceModel } from '../models/post-slice';

/**
 * Post Slices View Controller
 */
export const PostSlicesView = BaseListView.extend({
	el: 'body',

	listSelector: '#aips-post-slices-table',
	rowSelector: '#aips-post-slices-table tbody tr',
	searchSelector: '#aips-post-slice-search',
	selectAllSelector: '',
	checkboxSelector: '',
	bulkApplySelector: '',

	currentSliceId: 0,

	events: _.extend({}, BaseListView.prototype.events, {
		'click #aips-add-post-slice-btn, #aips-add-post-slice-empty-btn': 'openAddModal',
		'click .aips-edit-post-slice': 'openEditModal',
		'click #aips-save-post-slice-btn': 'saveSlice',
		'click .aips-delete-post-slice': 'deleteSlice',
		'click .aips-toggle-post-slice': 'toggleSlice',
		'input #aips-post-slice-search': 'onSearchInput',
		'click #aips-post-slice-search-clear, #aips-post-slice-search-clear-2': 'clearSearch'
	}),

	initialize() {
		BaseListView.prototype.initialize.apply(this, arguments);

		this.l10n = window.aipsPostSlicesL10n || {};

		if (this.$('#aips-post-slice-modal').length) {
			this.modal = new BaseModalView({ el: '#aips-post-slice-modal' });
		}
	},

	/**
	 * Reset form fields in the modal
	 */
	resetForm() {
		this.$('#aips-post-slice-id').val(0);
		this.$('#aips-post-slice-name').val('');
		this.$('#aips-post-slice-description').val('');
		this.$('#aips-post-slice-sort-order').val(0);
		this.$('#aips-post-slice-is-active').prop('checked', true);
	},

	/**
	 * Open Add modal
	 */
	openAddModal(e) {
		if (e) e.preventDefault();

		this.currentSliceId = 0;
		this.resetForm();

		this.$('#aips-post-slice-modal-title').text(this.l10n.addNewSlice || 'Add New Slice');
		
		if (this.modal) {
			this.modal.open();
		}
		this.$('#aips-post-slice-name').trigger('focus');
	},

	/**
	 * Open Edit modal
	 */
	openEditModal(e) {
		if (e) e.preventDefault();

		const $btn = $(e.currentTarget);
		const $row = $btn.closest('tr');
		const id = parseInt($btn.attr('data-id') || $btn.data('id'), 10);

		this.currentSliceId = id;

		this.$('#aips-post-slice-id').val(id);
		this.$('#aips-post-slice-name').val($row.attr('data-name') || $row.data('name') || '');
		this.$('#aips-post-slice-description').val($row.attr('data-description') || $row.data('description') || '');
		this.$('#aips-post-slice-sort-order').val($row.attr('data-sort-order') || $row.data('sort-order') || 0);
		this.$('#aips-post-slice-is-active').prop('checked', parseInt($row.attr('data-active') || $row.data('active'), 10) === 1);

		this.$('#aips-post-slice-modal-title').text(this.l10n.editSlice || 'Edit Slice');
		
		if (this.modal) {
			this.modal.open();
		}
		this.$('#aips-post-slice-name').trigger('focus');
	},

	/**
	 * Create or update post slice
	 */
	saveSlice(e) {
		if (e) e.preventDefault();

		const name = this.$('#aips-post-slice-name').val().trim();
		if (!name) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(this.l10n.nameRequired || 'A slice name is required.', 'error');
			}
			this.$('#aips-post-slice-name').trigger('focus');
			return;
		}

		const $btn = this.$('#aips-save-post-slice-btn');
		const defaultLabel = $btn.text();
		$btn.prop('disabled', true).text(this.l10n.saving || 'Saving...');

		const model = new PostSliceModel({
			id: this.currentSliceId,
			name: name,
			description: this.$('#aips-post-slice-description').val().trim(),
			sort_order: parseInt(this.$('#aips-post-slice-sort-order').val(), 10) || 0,
			is_active: this.$('#aips-post-slice-is-active').is(':checked') ? 1 : 0
		});

		model.save(null, {
			success: (model, response) => {
				$btn.prop('disabled', false).text(defaultLabel);
				
				const msg = (response && response.message) || this.l10n.saveSuccess || 'Slice saved successfully.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(msg, 'success');
				}
				
				if (this.modal) {
					this.modal.close();
				}
				setTimeout(() => window.location.reload(), 1000);
			},
			error: (model, err) => {
				$btn.prop('disabled', false).text(defaultLabel);
				
				const errMsg = (err && err.message) || this.l10n.saveFailed || 'Failed to save slice.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
			}
		});
	},

	/**
	 * Delete a post slice
	 */
	deleteSlice(e) {
		if (e) e.preventDefault();

		const $btn = $(e.currentTarget);
		const id = parseInt($btn.attr('data-id') || $btn.data('id'), 10);
		if (!id) return;

		const confirmMsg = this.l10n.deleteConfirm || 'Are you sure you want to delete this slice?';
		if (!confirm(confirmMsg)) {
			return;
		}

		const model = new PostSliceModel({ id: id });
		model.destroy({
			success: (model, response) => {
				const msg = (response && response.message) || this.l10n.deleteSuccess || 'Slice deleted successfully.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(msg, 'success');
				}
				setTimeout(() => window.location.reload(), 1000);
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || this.l10n.deleteFailed || 'Failed to delete slice.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
			}
		});
	},

	/**
	 * Toggle post slice active status
	 */
	toggleSlice(e) {
		if (e) e.preventDefault();

		const $btn = $(e.currentTarget);
		const id = parseInt($btn.attr('data-id') || $btn.data('id'), 10);
		const isActive = parseInt($btn.attr('data-active') || $btn.data('active'), 10);
		const newStatus = isActive === 1 ? 0 : 1;

		$btn.prop('disabled', true);

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_toggle_post_slice_active',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				slice_id: id,
				is_active: newStatus
			},
			success: (response) => {
				if (response.success) {
					const msg = (response.data && response.data.message) || 'Status updated successfully.';
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(msg, 'success');
					}
					setTimeout(() => window.location.reload(), 1000);
				} else {
					$btn.prop('disabled', false);
					const errMsg = (response.data && response.data.message) || this.l10n.toggleFailed || 'Failed to update slice status.';
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(errMsg, 'error');
					}
				}
			},
			error: () => {
				$btn.prop('disabled', false);
				const errMsg = this.l10n.toggleFailed || 'Failed to update slice status.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
			}
		});
	},

	/**
	 * Handle key/input events in search field
	 */
	onSearchInput(e) {
		const term = $(e.currentTarget).val().toLowerCase().trim();
		this.$('#aips-post-slice-search-clear').toggle(term.length > 0);
		this.filterList(term);
	},

	/**
	 * Filter rows in table by search query
	 */
	filterList(query) {
		const $rows = this.$(this.rowSelector);
		let visible = 0;

		$rows.each(function() {
			const text = $(this).text().toLowerCase();
			const show = !query || text.indexOf(query) !== -1;
			$(this).toggle(show);
			if (show) {
				visible++;
			}
		});

		this.$('#aips-post-slice-search-no-results').toggle(visible === 0 && query.length > 0);
	},

	/**
	 * Clear the search input
	 */
	clearSearch(e) {
		if (e) e.preventDefault();
		this.$('#aips-post-slice-search').val('');
		this.$('#aips-post-slice-search-clear').hide();
		this.filterList('');
	}
});

export default PostSlicesView;
