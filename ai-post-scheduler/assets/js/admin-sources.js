/**
 * Admin Sources – Trusted Sources management page JS.
 *
 * Handles add / edit / delete / toggle-active interactions for the Sources UI,
 * as well as Source Group management (create / delete taxonomy terms).
 *
 * NOTE: This file must NOT define AIPS.init or AIPS.bindEvents because admin.js
 * already owns those names. Source-page bootstrap is exposed as AIPS.initSources.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	// -----------------------------------------------------------------
	// Sources-page module (namespaced to avoid clobbering AIPS.init)
	// -----------------------------------------------------------------
	AIPS.Sources = {

		/** @type {number} ID of the source currently being edited (0 = new). */
		currentSourceId: 0,

		/**
		 * Bootstrap the Sources page.
		 *
		 * @return {void}
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind all UI event listeners.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			// Open modal for a new source.
			$(document).on('click', '#aips-add-source-btn, #aips-add-source-empty-btn', this.openAddModal.bind(this));

			// Open modal for an existing source.
			$(document).on('click', '.aips-edit-source', this.openEditModal.bind(this));

			// Save (create or update) a source.
			$(document).on('click', '#aips-save-source-btn', this.saveSource.bind(this));

			// Delete a source.
			$(document).on('click', '.aips-delete-source', this.deleteSource.bind(this));

			// Toggle active status.
			$(document).on('click', '.aips-toggle-source', this.toggleSource.bind(this));

			// Fetch content now.
			$(document).on('click', '.aips-fetch-source-now', this.fetchSourceNow.bind(this));

			// Modal close (button + backdrop click + Escape) is handled globally by admin.js.

			// Live search / filter.
			$(document).on('input', '#aips-source-search', this.filterSources.bind(this));
			$(document).on('click', '#aips-source-search-clear, #aips-source-search-clear-2', this.clearSearch.bind(this));

			// Source Groups modal.
			$(document).on('click', '#aips-manage-source-groups-btn', this.openGroupsModal.bind(this));
			$(document).on('click', '#aips-add-group-btn', this.addSourceGroup.bind(this));
			$(document).on('click', '.aips-delete-source-group', this.deleteSourceGroup.bind(this));
		},

		// -----------------------------------------------------------------
		// Modal helpers – Source
		// -----------------------------------------------------------------

		/**
		 * Open the Add New Source modal with empty fields.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openAddModal: function (e) {
			e.preventDefault();
			this.currentSourceId = 0;
			this.resetForm();
			AIPS.Core.Modal.open('#aips-source-modal', { title: aipsSourcesL10n.addNewSource });
		},

		/**
		 * Open the Edit Source modal pre-filled with the row's data attributes.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openEditModal: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var id   = parseInt($btn.data('id'), 10);
			var $row = $btn.closest('tr');

			this.currentSourceId = id;

			AIPS.Core.Modal.populateFields('#aips-source-modal', {
				'#aips-source-id': id,
				'#aips-source-url': $row.data('url'),
				'#aips-source-label': $row.data('label'),
				'#aips-source-description': $row.data('description'),
				'#aips-source-is-active': parseInt($row.data('active'), 10) === 1,
				'#aips-source-fetch-interval': $row.data('fetch-interval') || ''
			});

			// Restore group checkboxes (not a simple selector -> value map, handled separately).
			var termIds = [];
			try {
				termIds = JSON.parse($row.attr('data-term-ids') || '[]');
			} catch (err) {
				termIds = [];
			}
			$('.aips-source-group-checkbox').prop('checked', false);
			termIds.forEach(function (tid) {
				$('.aips-source-group-checkbox[value="' + tid + '"]').prop('checked', true);
			});

			AIPS.Core.Modal.open('#aips-source-modal', { title: aipsSourcesL10n.editSource });
		},

		/**
		 * Clear all form inputs to their defaults.
		 *
		 * @return {void}
		 */
		resetForm: function () {
			AIPS.Core.Modal.resetFields('#aips-source-modal', {
				'#aips-source-id': 0,
				'#aips-source-url': '',
				'#aips-source-label': '',
				'#aips-source-description': '',
				'#aips-source-is-active': true,
				'#aips-source-fetch-interval': ''
			});
			$('.aips-source-group-checkbox').prop('checked', false);
		},

		// -----------------------------------------------------------------
		// AJAX – Save
		// -----------------------------------------------------------------

		/**
		 * Send a save (create or update) request to the server.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		saveSource: function (e) {
			e.preventDefault();

			var url = $('#aips-source-url').val().trim();
			if (!url) {
				AIPS.Utilities.showToast(aipsSourcesL10n.urlRequired, 'error');
				return;
			}

			// Collect checked source group term IDs.
			var termIds = [];
			$('.aips-source-group-checkbox:checked').each(function () {
				termIds.push(parseInt($(this).val(), 10));
			});

			var data = {
				action:         'aips_save_source',
				nonce:          aipsAjax.nonce,
				source_id:      this.currentSourceId,
				url:            url,
				label:          $('#aips-source-label').val().trim(),
				description:    $('#aips-source-description').val().trim(),
				term_ids:       termIds,
				fetch_interval: $('#aips-source-fetch-interval').val(),
			};

			if ($('#aips-source-is-active').is(':checked')) {
				data.is_active = 1;
			}

			$('#aips-save-source-btn').prop('disabled', true).text(aipsSourcesL10n.saving);

			var self = this;
			$.post(aipsAjax.ajaxUrl, data, function (response) {
				$('#aips-save-source-btn').prop('disabled', false).text(aipsSourcesL10n.saveSource);

				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message || aipsSourcesL10n.saveFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message, 'success');
				$('#aips-source-modal').hide();
				self.refreshPage();
			}).fail(function () {
				$('#aips-save-source-btn').prop('disabled', false).text(aipsSourcesL10n.saveSource);
				AIPS.Utilities.showToast(aipsSourcesL10n.saveFailed, 'error');
			});
		},

		// -----------------------------------------------------------------
		// AJAX – Delete
		// -----------------------------------------------------------------

		/**
		 * Confirm then send a delete request for a source row.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		deleteSource: function (e) {
			e.preventDefault();
			var id = parseInt($(e.currentTarget).data('id'), 10);
			var self = this;

			AIPS.Core.Modal.confirmDelete({
				message: aipsSourcesL10n.deleteConfirm,
				onConfirm: function () {
					$.post(aipsAjax.ajaxUrl, {
						action:    'aips_delete_source',
						nonce:     aipsAjax.nonce,
						source_id: id,
					}, function (response) {
						if (!response.success) {
							AIPS.Utilities.showToast(response.data.message || aipsSourcesL10n.deleteFailed, 'error');
							return;
						}

						AIPS.Utilities.showToast(response.data.message, 'success');
						self.refreshPage();
					}).fail(function () {
						AIPS.Utilities.showToast(aipsSourcesL10n.deleteFailed, 'error');
					});
				}
			});
		},

		// -----------------------------------------------------------------
		// AJAX – Toggle active
		// -----------------------------------------------------------------

		/**
		 * Toggle the is_active flag for a source row.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		toggleSource: function (e) {
			e.preventDefault();
			var $btn      = $(e.currentTarget);
			var id        = parseInt($btn.data('id'), 10);
			var isActive  = parseInt($btn.data('active'), 10);
			var newStatus = isActive === 1 ? 0 : 1;

			var self = this;
			$.post(aipsAjax.ajaxUrl, {
				action:    'aips_toggle_source_active',
				nonce:     aipsAjax.nonce,
				source_id: id,
				is_active: newStatus,
			}, function (response) {
				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message || aipsSourcesL10n.toggleFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message, 'success');
				self.refreshPage();
			}).fail(function () {
				AIPS.Utilities.showToast(aipsSourcesL10n.toggleFailed, 'error');
			});
		},

		// -----------------------------------------------------------------
		// Source Groups modal
		// -----------------------------------------------------------------

		/**
		 * Open the Manage Source Groups modal.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openGroupsModal: function (e) {
			e.preventDefault();
			$('#aips-new-group-name').val('');
			AIPS.Core.Modal.open('#aips-groups-modal');
		},

		/**
		 * Send a request to create a new source group taxonomy term.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		addSourceGroup: function (e) {
			e.preventDefault();
			var name = $('#aips-new-group-name').val().trim();
			if (!name) {
				AIPS.Utilities.showToast(aipsSourcesL10n.groupNameRequired || 'Please enter a group name.', 'error');
				return;
			}

			var $btn = $('#aips-add-group-btn');
			$btn.prop('disabled', true);

			var self = this;
			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_save_source_group',
				nonce:  aipsAjax.nonce,
				name:   name,
			}, function (response) {
				$btn.prop('disabled', false);
				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message || 'Failed to create group.', 'error');
					return;
				}
				AIPS.Utilities.showToast(response.data.message, 'success');
				self.refreshPage();
			}).fail(function () {
				$btn.prop('disabled', false);
				AIPS.Utilities.showToast('Failed to create group.', 'error');
			});
		},

		/**
		 * Confirm then delete a source group taxonomy term.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		deleteSourceGroup: function (e) {
			e.preventDefault();
			var termId = parseInt($(e.currentTarget).data('term-id'), 10);
			var self = this;

			AIPS.Core.Modal.confirmDelete({
				message: aipsSourcesL10n.deleteGroupConfirm || 'Delete this Source Group? Sources in this group will not be deleted.',
				onConfirm: function () {
					$.post(aipsAjax.ajaxUrl, {
						action:  'aips_delete_source_group',
						nonce:   aipsAjax.nonce,
						term_id: termId,
					}, function (response) {
						if (!response.success) {
							AIPS.Utilities.showToast(response.data.message || 'Failed to delete group.', 'error');
							return;
						}
						AIPS.Utilities.showToast(response.data.message, 'success');
						self.refreshPage();
					}).fail(function () {
						AIPS.Utilities.showToast('Failed to delete group.', 'error');
					});
				}
			});
		},

		// -----------------------------------------------------------------
		// AJAX – Fetch Now
		// -----------------------------------------------------------------

		/**
		 * Trigger an immediate content fetch for a single source.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		fetchSourceNow: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var id   = parseInt($btn.data('id'), 10);

			$btn.prop('disabled', true);
			var $icon = $btn.find('.dashicons');
			$icon.removeClass('dashicons-download').addClass('dashicons-update aips-spin');

			var self = this;
			$.post(aipsAjax.ajaxUrl, {
				action:    'aips_fetch_source_now',
				nonce:     aipsAjax.nonce,
				source_id: id,
			}, function (response) {
				$btn.prop('disabled', false);
				$icon.removeClass('dashicons-update aips-spin').addClass('dashicons-download');

				if (!response.success) {
					AIPS.Utilities.showToast(response.data.message || 'Fetch failed.', 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message, 'success');
				self.refreshPage();
			}).fail(function () {
				$btn.prop('disabled', false);
				$icon.removeClass('dashicons-update aips-spin').addClass('dashicons-download');
				AIPS.Utilities.showToast('Fetch failed.', 'error');
			});
		},

		// -----------------------------------------------------------------
		// Search / Filter
		// -----------------------------------------------------------------

		/**
		 * Filter the sources table rows based on the search input value.
		 *
		 * @param {Event} e Input event.
		 * @return {void}
		 */
		filterSources: function (e) {
			AIPS.Core.Table.filterRows({
				term: $(e.currentTarget).val(),
				$rows: $('#aips-sources-table tbody tr'),
				$clearButton: $('#aips-source-search-clear'),
				$noResults: $('#aips-source-search-no-results')
			});
		},

		/**
		 * Clear the search input and restore all rows.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		clearSearch: function (e) {
			e.preventDefault();
			$('#aips-source-search').val('').trigger('input');
		},

		// -----------------------------------------------------------------
		// Utilities
		// -----------------------------------------------------------------

		/**
		 * Reload the current page to reflect database changes.
		 *
		 * @return {void}
		 */
		refreshPage: function () {
			if (typeof AIPS !== 'undefined' && typeof AIPS.refreshContentPanel === 'function') {
				AIPS.refreshContentPanel('.aips-content-panel', '.aips-content-panel');
			} else {
				window.location.reload();
			}
		},
	};

	/**
	 * Expose a page-scoped init so the global AIPS.init (owned by admin.js) is not overwritten.
	 *
	 * @return {void}
	 */
	AIPS.initSources = function () {
		AIPS.Sources.init();
	};

	$(document).ready(function () {
		AIPS.initSources();
	});

})(jQuery);
