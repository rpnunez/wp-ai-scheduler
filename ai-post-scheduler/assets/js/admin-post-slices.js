/**
 * Admin Post Slices page JS.
 *
 * Handles add, edit, delete, toggle-active, and search interactions for the
 * Post Slices management UI.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.PostSlices = {
		currentSliceId: 0,

		/**
		 * Bootstrap the page module.
		 *
		 * @return {void}
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Register delegated event handlers.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			$(document).on('click', '#aips-add-post-slice-btn, #aips-add-post-slice-empty-btn', this.openAddModal.bind(this));
			$(document).on('click', '.aips-edit-post-slice', this.openEditModal.bind(this));
			$(document).on('click', '#aips-save-post-slice-btn', this.saveSlice.bind(this));
			$(document).on('click', '.aips-delete-post-slice', this.deleteSlice.bind(this));
			$(document).on('click', '.aips-toggle-post-slice', this.toggleSlice.bind(this));
			$(document).on('click', '#aips-post-slice-modal .aips-modal-close', this.closeModal.bind(this));
			$(document).on('click', '#aips-post-slice-modal', this.onOverlayClick.bind(this));
			$(document).on('input', '#aips-post-slice-search', this.filterSlices.bind(this));
			$(document).on('click', '#aips-post-slice-search-clear, #aips-post-slice-search-clear-2', this.clearSearch.bind(this));
		},

		/**
		 * Open the modal for a new post slice.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openAddModal: function (e) {
			e.preventDefault();
			this.currentSliceId = 0;
			this.resetForm();
			$('#aips-post-slice-modal-title').text(aipsPostSlicesL10n.addNewSlice);
			$('#aips-post-slice-modal').show();
			$('#aips-post-slice-name').trigger('focus');
		},

		/**
		 * Open the modal for an existing post slice.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openEditModal: function (e) {
			e.preventDefault();

			var $btn = $(e.currentTarget);
			var $row = $btn.closest('tr');
			var id = parseInt($btn.data('id'), 10);

			this.currentSliceId = id;

			$('#aips-post-slice-id').val(id);
			$('#aips-post-slice-name').val($row.data('name') || '');
			$('#aips-post-slice-description').val($row.data('description') || '');
			$('#aips-post-slice-sort-order').val($row.data('sort-order') || 0);
			$('#aips-post-slice-is-active').prop('checked', parseInt($row.data('active'), 10) === 1);

			$('#aips-post-slice-modal-title').text(aipsPostSlicesL10n.editSlice);
			$('#aips-post-slice-modal').show();
			$('#aips-post-slice-name').trigger('focus');
		},

		/**
		 * Close the modal.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		closeModal: function (e) {
			e.preventDefault();
			$('#aips-post-slice-modal').hide();
		},

		/**
		 * Close the modal when clicking the backdrop.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onOverlayClick: function (e) {
			if ($(e.target).is('#aips-post-slice-modal')) {
				$('#aips-post-slice-modal').hide();
			}
		},

		/**
		 * Reset modal fields.
		 *
		 * @return {void}
		 */
		resetForm: function () {
			$('#aips-post-slice-id').val(0);
			$('#aips-post-slice-name').val('');
			$('#aips-post-slice-description').val('');
			$('#aips-post-slice-sort-order').val(0);
			$('#aips-post-slice-is-active').prop('checked', true);
		},

		/**
		 * Create or update a post slice.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		saveSlice: function (e) {
			e.preventDefault();

			var name = $('#aips-post-slice-name').val().trim();

			if (!name) {
				AIPS.Utilities.showToast(aipsPostSlicesL10n.nameRequired, 'error');
				$('#aips-post-slice-name').trigger('focus');
				return;
			}

			var $btn = $('#aips-save-post-slice-btn');
			$btn.prop('disabled', true).text(aipsPostSlicesL10n.saving);

			var self = this;
			$.post(aipsAjax.ajaxUrl, {
				action:      'aips_save_post_slice',
				nonce:       aipsAjax.nonce,
				slice_id:    this.currentSliceId,
				name:        name,
				description: $('#aips-post-slice-description').val().trim(),
				sort_order:  parseInt($('#aips-post-slice-sort-order').val(), 10) || 0,
				is_active:   $('#aips-post-slice-is-active').is(':checked') ? 1 : 0,
			}, function (response) {
				$btn.prop('disabled', false).text(aipsPostSlicesL10n.saveSlice);

				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message || aipsPostSlicesL10n.saveFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message, 'success');
				$('#aips-post-slice-modal').hide();
				self.refreshPage();
			}).fail(function () {
				$btn.prop('disabled', false).text(aipsPostSlicesL10n.saveSlice);
				AIPS.Utilities.showToast(aipsPostSlicesL10n.saveFailed, 'error');
			});
		},

		/**
		 * Delete a post slice.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		deleteSlice: function (e) {
			e.preventDefault();

			var id = parseInt($(e.currentTarget).data('id'), 10);

			if (!confirm(aipsPostSlicesL10n.deleteConfirm)) {
				return;
			}

			var self = this;
			$.post(aipsAjax.ajaxUrl, {
				action:   'aips_delete_post_slice',
				nonce:    aipsAjax.nonce,
				slice_id: id,
			}, function (response) {
				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message || aipsPostSlicesL10n.deleteFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message, 'success');
				self.refreshPage();
			}).fail(function () {
				AIPS.Utilities.showToast(aipsPostSlicesL10n.deleteFailed, 'error');
			});
		},

		/**
		 * Toggle active status.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		toggleSlice: function (e) {
			e.preventDefault();

			var $btn = $(e.currentTarget);
			var id = parseInt($btn.data('id'), 10);
			var isActive = parseInt($btn.data('active'), 10);
			var newStatus = isActive === 1 ? 0 : 1;

			var self = this;
			$.post(aipsAjax.ajaxUrl, {
				action:    'aips_toggle_post_slice_active',
				nonce:     aipsAjax.nonce,
				slice_id:  id,
				is_active: newStatus,
			}, function (response) {
				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message || aipsPostSlicesL10n.toggleFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message, 'success');
				self.refreshPage();
			}).fail(function () {
				AIPS.Utilities.showToast(aipsPostSlicesL10n.toggleFailed, 'error');
			});
		},

		/**
		 * Filter the table by search text.
		 *
		 * @param {Event} e Input event.
		 * @return {void}
		 */
		filterSlices: function (e) {
			var term = $(e.currentTarget).val().toLowerCase().trim();
			var $rows = $('#aips-post-slices-table tbody tr');
			var visible = 0;

			$('#aips-post-slice-search-clear').toggle(term.length > 0);

			$rows.each(function () {
				var text = $(this).text().toLowerCase();
				var show = !term || text.indexOf(term) !== -1;
				$(this).toggle(show);

				if (show) {
					visible++;
				}
			});

			$('#aips-post-slice-search-no-results').toggle(visible === 0 && term.length > 0);
		},

		/**
		 * Clear the search input.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		clearSearch: function (e) {
			e.preventDefault();
			$('#aips-post-slice-search').val('').trigger('input');
		},

		/**
		 * Reload the page after a mutation.
		 *
		 * @return {void}
		 */
		refreshPage: function () {
			window.location.reload();
		},
	};

	AIPS.initPostSlices = function () {
		AIPS.PostSlices.init();
	};

	$(document).ready(function () {
		AIPS.initPostSlices();
	});

})(jQuery);
