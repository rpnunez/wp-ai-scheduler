import Backbone from 'backbone';
import $ from 'jquery';
import { PostSliceModel } from '../models/post-slice';

/**
 * Post Slices View
 * Manages add, edit, delete, toggle-active, and search for post slices
 */
export const PostSlicesView = Backbone.View.extend({
	el: 'body',

	events: {
		'click #aips-add-post-slice-btn, #aips-add-post-slice-empty-btn': 'openAddModal',
		'click .aips-edit-post-slice': 'openEditModal',
		'click #aips-save-post-slice-btn': 'saveSlice',
		'click .aips-delete-post-slice': 'deleteSlice',
		'click .aips-toggle-post-slice': 'toggleSlice',
		'click #aips-post-slice-modal .aips-modal-close': 'closeModal',
		'click #aips-post-slice-modal': 'onOverlayClick',
		'input #aips-post-slice-search': 'filterSlices',
		'click #aips-post-slice-search-clear, #aips-post-slice-search-clear-2': 'clearSearch'
	},

	currentSliceId: 0,
	l10n: {},

	initialize() {
		this.model = new PostSliceModel();
		this.l10n = window.aipsPostSlicesL10n || {};
	},

	openAddModal(e) {
		e.preventDefault();
		this.currentSliceId = 0;
		this.resetForm();
		this.$('#aips-post-slice-modal-title').text(this.l10n.addNewSlice || 'Add New Slice');
		this.$('#aips-post-slice-modal').show();
		this.$('#aips-post-slice-name').trigger('focus');
	},

	openEditModal(e) {
		e.preventDefault();

		const $btn = $(e.currentTarget);
		const $row = $btn.closest('tr');
		const id = parseInt($btn.data('id'), 10);

		this.currentSliceId = id;

		this.$('#aips-post-slice-id').val(id);
		this.$('#aips-post-slice-name').val($row.data('name') || '');
		this.$('#aips-post-slice-description').val($row.data('description') || '');
		this.$('#aips-post-slice-sort-order').val($row.data('sort-order') || 0);
		this.$('#aips-post-slice-is-active').prop('checked', parseInt($row.data('active'), 10) === 1);

		this.$('#aips-post-slice-modal-title').text(this.l10n.editSlice || 'Edit Slice');
		this.$('#aips-post-slice-modal').show();
		this.$('#aips-post-slice-name').trigger('focus');
	},

	closeModal(e) {
		if (e) e.preventDefault();
		this.$('#aips-post-slice-modal').hide();
	},

	onOverlayClick(e) {
		if ($(e.target).is('#aips-post-slice-modal')) {
			this.$('#aips-post-slice-modal').hide();
		}
	},

	resetForm() {
		this.$('#aips-post-slice-id').val(0);
		this.$('#aips-post-slice-name').val('');
		this.$('#aips-post-slice-description').val('');
		this.$('#aips-post-slice-sort-order').val(0);
		this.$('#aips-post-slice-is-active').prop('checked', true);
	},

	saveSlice(e) {
		e.preventDefault();

		const name = this.$('#aips-post-slice-name').val().trim();

		if (!name) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(this.l10n.nameRequired || 'Name is required.', 'error');
			}
			this.$('#aips-post-slice-name').trigger('focus');
			return;
		}

		const $btn = this.$('#aips-save-post-slice-btn');
		const defaultLabel = $btn.text();

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, this.l10n.saving || 'Saving...');
		}

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_save_post_slice',
			nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
			slice_id: this.currentSliceId,
			name,
			description: this.$('#aips-post-slice-description').val().trim(),
			sort_order: parseInt(this.$('#aips-post-slice-sort-order').val(), 10) || 0,
			is_active: this.$('#aips-post-slice-is-active').is(':checked') ? 1 : 0
		})
			.done((response) => {
				const message = (response && response.data && response.data.message)
					|| this.l10n.saveSuccess
					|| 'Slice saved successfully.';

				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(message, 'success');
				}
				this.closeModal();
				this.refreshPage();
			})
			.fail(() => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(this.l10n.saveFailed || 'Failed to save slice.', 'error');
				}
			})
			.always(() => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($btn, defaultLabel);
				}
			});
	},

	deleteSlice(e) {
		e.preventDefault();

		const id = parseInt($(e.currentTarget).data('id'), 10);

		if (!confirm(this.l10n.deleteConfirm || 'Are you sure you want to delete this slice?')) {
			return;
		}

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_delete_post_slice',
			nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
			slice_id: id
		})
			.done((response) => {
				const message = (response && response.data && response.data.message)
					|| this.l10n.deleteSuccess
					|| 'Slice deleted successfully.';

				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(message, 'success');
				}
				this.refreshPage();
			})
			.fail(() => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(this.l10n.deleteFailed || 'Failed to delete slice.', 'error');
				}
			});
	},

	toggleSlice(e) {
		e.preventDefault();

		const $btn = $(e.currentTarget);
		const id = parseInt($btn.data('id'), 10);
		const isActive = parseInt($btn.data('active'), 10);
		const newStatus = isActive === 1 ? 0 : 1;

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_toggle_post_slice_active',
			nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
			slice_id: id,
			is_active: newStatus
		})
			.done((response) => {
				const message = (response && response.data && response.data.message)
					|| this.l10n.toggleSuccess
					|| 'Toggle successful.';

				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(message, 'success');
				}
				this.refreshPage();
			})
			.fail(() => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(this.l10n.toggleFailed || 'Failed to toggle slice.', 'error');
				}
			});
	},

	filterSlices(e) {
		const term = $(e.currentTarget).val().toLowerCase().trim();
		const $rows = this.$('#aips-post-slices-table tbody tr');
		let visible = 0;

		this.$('#aips-post-slice-search-clear').toggle(term.length > 0);

		$rows.each(function() {
			const text = $(this).text().toLowerCase();
			const show = !term || text.indexOf(term) !== -1;
			$(this).toggle(show);

			if (show) {
				visible += 1;
			}
		});

		this.$('#aips-post-slice-search-no-results').toggle(visible === 0 && term.length > 0);
	},

	clearSearch(e) {
		e.preventDefault();
		this.$('#aips-post-slice-search').val('').trigger('input');
	},

	refreshPage() {
		window.location.reload();
	}
});
