/**
 * Blueprint Presets admin management.
 *
 * Handles CRUD operations for Blueprint Presets within the unified Blueprints page.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.0
 */
/* global jQuery, aipsAjax, aipsBlueprintPresetsL10n, aipsAdminL10n */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.BlueprintPresets = {
		containerSelector: '.aips-blueprint-presets-container',

		/**
		 * Bootstrap the Blueprint Presets module.
		 *
		 * @return {void}
		 */
		init: function() {
			this.cacheElements();
			this.bindEvents();
		},

		/**
		 * Cache frequently used DOM elements.
		 *
		 * @return {void}
		 */
		cacheElements: function() {
			this.$modal = $('#aips-blueprint-preset-modal');
			this.$form = $('#aips-blueprint-preset-form');
			this.$modalTitle = $('#aips-blueprint-preset-modal-title');
			this.$saveButton = $('#aips-save-blueprint-preset-btn');
			this.$nameField = $('#aips-blueprint-preset-name');
		},

		/**
		 * Register delegated UI event handlers.
		 *
		 * @return {void}
		 */
		bindEvents: function() {
			$(document).on('click', '#aips-add-blueprint-preset-btn, #aips-add-blueprint-preset-empty-btn', this.openAddModal.bind(this));
			$(document).on('click', '.aips-edit-blueprint-preset', this.openEditModal.bind(this));
			$(document).on('click', '.aips-delete-blueprint-preset', this.confirmDeletePreset.bind(this));
			$(document).on('click', '#aips-save-blueprint-preset-btn', this.savePreset.bind(this));
			$(document).on('click', '#aips-blueprint-preset-modal .aips-modal-close', this.closeModal.bind(this));
			$(document).on('click', '#aips-blueprint-preset-modal', this.onOverlayClick.bind(this));
			$(document).on('input', '#aips-preset-search', this.filterPresets.bind(this));
			$(document).on('click', '#aips-preset-search-clear', this.clearSearch.bind(this));
		},

		/**
		 * Open the modal for a new preset.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openAddModal: function(e) {
			e.preventDefault();

			this.resetForm();
			this.$modalTitle.text(this.getL10nValue('addTitle', 'Add Blueprint Preset'));
			this.openModal();
		},

		/**
		 * Open the modal for an existing preset.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		openEditModal: function(e) {
			e.preventDefault();

			var presetId = parseInt($(e.currentTarget).data('id'), 10) || 0;

			if (!presetId) {
				return;
			}

			$.post(
				aipsAjax.ajaxUrl,
				{
					action: 'aips_get_blueprint_preset',
					nonce: this.getL10nValue('nonce', ''),
					preset_id: presetId,
				},
				this.handleEditResponse.bind(this)
			).fail(this.handleEditFailure.bind(this));
		},

		/**
		 * Populate the form with the preset returned by the server.
		 *
		 * @param {Object} response AJAX response payload.
		 * @return {void}
		 */
		handleEditResponse: function(response) {
			if (!response || !response.success || !response.data) {
				this.showToast('error', this.getResponseMessage(response, this.getL10nValue('loadFailed', 'Failed to load preset.')));
				return;
			}

			this.populateForm(response.data);
			this.$modalTitle.text(this.getL10nValue('editTitle', 'Edit Blueprint Preset'));
			this.openModal();
		},

		/**
		 * Show an error when preset loading fails.
		 *
		 * @return {void}
		 */
		handleEditFailure: function() {
			this.showToast('error', this.getL10nValue('loadFailed', 'Failed to load preset.'));
		},

		/**
		 * Fill form fields from a preset object.
		 *
		 * @param {Object} preset Preset record returned from AJAX.
		 * @return {void}
		 */
		populateForm: function(preset) {
			$('#aips-blueprint-preset-id').val(preset.id || 0);
			this.$nameField.val(preset.name || '');
			$('#aips-blueprint-preset-description').val(preset.description || '');
			$('#aips-blueprint-preset-structure').val(preset.structure_id || '');
			$('#aips-blueprint-preset-voice').val(preset.voice_id || '');
			$('#aips-blueprint-preset-slices').val(this.parseSliceIds(preset.slice_ids));
			$('#aips-blueprint-preset-is-active').prop('checked', parseInt(preset.is_active, 10) === 1);
			$('#aips-blueprint-preset-is-default').prop('checked', parseInt(preset.is_default, 10) === 1);
		},

		/**
		 * Reset the preset form to its default state.
		 *
		 * @return {void}
		 */
		resetForm: function() {
			if (this.$form.length && this.$form[0]) {
				this.$form[0].reset();
			}

			$('#aips-blueprint-preset-id').val(0);
			$('#aips-blueprint-preset-slices').val([]);
		},

		/**
		 * Persist the current preset form via AJAX.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		savePreset: function(e) {
			e.preventDefault();

			var name = $.trim(this.$nameField.val() || '');

			if (!name) {
				this.showToast('error', this.getL10nValue('nameRequired', 'Preset name is required.'));
				this.$nameField.trigger('focus');
				return;
			}

			this.setSaveButtonDisabled(true);

			$.post(
				aipsAjax.ajaxUrl,
				this.buildSaveRequest(name),
				this.handleSaveResponse.bind(this)
			).fail(this.handleSaveFailure.bind(this));
		},

		/**
		 * Build the preset save request payload.
		 *
		 * @param {string} name Validated preset name.
		 * @return {Object}
		 */
		buildSaveRequest: function(name) {
			return {
				action: 'aips_save_blueprint_preset',
				nonce: this.getL10nValue('nonce', ''),
				preset_id: parseInt($('#aips-blueprint-preset-id').val(), 10) || 0,
				name: name,
				description: $.trim($('#aips-blueprint-preset-description').val() || ''),
				structure_id: parseInt($('#aips-blueprint-preset-structure').val(), 10) || 0,
				voice_id: parseInt($('#aips-blueprint-preset-voice').val(), 10) || 0,
				slice_ids: JSON.stringify(this.getSelectedSliceIds()),
				is_active: $('#aips-blueprint-preset-is-active').is(':checked') ? 1 : 0,
				is_default: $('#aips-blueprint-preset-is-default').is(':checked') ? 1 : 0,
			};
		},

		/**
		 * Handle a successful save request response.
		 *
		 * @param {Object} response AJAX response payload.
		 * @return {void}
		 */
		handleSaveResponse: function(response) {
			this.setSaveButtonDisabled(false);

			if (!response || !response.success) {
				this.showToast('error', this.getResponseMessage(response, this.getL10nValue('saveFailed', 'Failed to save preset.')));
				return;
			}

			this.showToast('success', this.getResponseMessage(response, this.getL10nValue('saveSuccess', 'Preset saved.')));
			this.closeModal();
			this.refreshList();
		},

		/**
		 * Handle a failed save request.
		 *
		 * @return {void}
		 */
		handleSaveFailure: function() {
			this.setSaveButtonDisabled(false);
			this.showToast('error', this.getL10nValue('saveFailed', 'Failed to save preset.'));
		},

		/**
		 * Confirm preset deletion before calling the delete endpoint.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		confirmDeletePreset: function(e) {
			e.preventDefault();

			var presetId = parseInt($(e.currentTarget).data('id'), 10) || 0;
			var self = this;

			if (!presetId) {
				return;
			}

			AIPS.Utilities.confirm(
				this.getL10nValue('confirmDelete', 'Are you sure you want to delete this preset?'),
				this.getL10nValue('confirmTitle', 'Confirm'),
				[
					{
						label: this.getSharedAdminL10nValue('confirmCancelButton', 'No, cancel'),
						className: 'aips-btn aips-btn-primary'
					},
					{
						label: this.getSharedAdminL10nValue('confirmDeleteButton', 'Yes, delete'),
						className: 'aips-btn aips-btn-danger-solid',
						action: function() {
							self.deletePreset(presetId);
						}
					}
				]
			);
		},

		/**
		 * Send the preset delete request.
		 *
		 * @param {number} presetId Preset identifier.
		 * @return {void}
		 */
		deletePreset: function(presetId) {
			$.post(
				aipsAjax.ajaxUrl,
				{
					action: 'aips_delete_blueprint_preset',
					nonce: this.getL10nValue('nonce', ''),
					preset_id: presetId,
				},
				this.handleDeleteResponse.bind(this)
			).fail(this.handleDeleteFailure.bind(this));
		},

		/**
		 * Handle a successful delete request response.
		 *
		 * @param {Object} response AJAX response payload.
		 * @return {void}
		 */
		handleDeleteResponse: function(response) {
			if (!response || !response.success) {
				this.showToast('error', this.getResponseMessage(response, this.getL10nValue('deleteFailed', 'Failed to delete preset.')));
				return;
			}

			this.showToast('success', this.getResponseMessage(response, this.getL10nValue('deleteSuccess', 'Preset deleted.')));
			this.refreshList();
		},

		/**
		 * Handle a failed delete request.
		 *
		 * @return {void}
		 */
		handleDeleteFailure: function() {
			this.showToast('error', this.getL10nValue('deleteFailed', 'Failed to delete preset.'));
		},

		/**
		 * Filter the preset table by search term.
		 *
		 * @param {Event} e Input event.
		 * @return {void}
		 */
		filterPresets: function(e) {
			var term = $.trim($(e.currentTarget).val() || '').toLowerCase();
			var $presetRows = $('#aips-blueprint-presets-table tbody tr');

			$('#aips-preset-search-clear').toggle(term.length > 0);

			$presetRows.each(function() {
				var text = $(this).text().toLowerCase();
				$(this).toggle(!term || text.indexOf(term) !== -1);
			});
		},

		/**
		 * Clear the preset search input and restore all rows.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		clearSearch: function(e) {
			e.preventDefault();

			$('#aips-preset-search').val('').trigger('input').trigger('focus');
		},

		/**
		 * Close the modal.
		 *
		 * @param {Event} [e] Click event.
		 * @return {void}
		 */
		closeModal: function(e) {
			if (e) {
				e.preventDefault();
			}

			this.$modal.hide();
		},

		/**
		 * Close the modal when the backdrop is clicked.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onOverlayClick: function(e) {
			if ($(e.target).is('#aips-blueprint-preset-modal')) {
				this.closeModal();
			}
		},

		/**
		 * Show the preset modal and focus the name field.
		 *
		 * @return {void}
		 */
		openModal: function() {
			this.$modal.show();
			this.$nameField.trigger('focus');
		},

		/**
		 * Refresh the presets panel after a create/update/delete action.
		 *
		 * @return {void}
		 */
		refreshList: function() {
			AIPS.refreshContentPanel(this.containerSelector, this.containerSelector, this.cacheElements.bind(this));
		},

		/**
		 * Enable or disable the save button.
		 *
		 * @param {boolean} disabled Whether the save button should be disabled.
		 * @return {void}
		 */
		setSaveButtonDisabled: function(disabled) {
			this.$saveButton.prop('disabled', disabled);
		},

		/**
		 * Return the currently selected slice IDs as integers.
		 *
		 * @return {Array<number>}
		 */
		getSelectedSliceIds: function() {
			return this.parseSliceIds($('#aips-blueprint-preset-slices').val());
		},

		/**
		 * Normalize preset slice IDs from strings or serialized JSON.
		 *
		 * @param {(Array|string|null)} sliceIds Slice IDs from the DOM or AJAX.
		 * @return {Array<number>}
		 */
		parseSliceIds: function(sliceIds) {
			var normalized = sliceIds;

			if ('string' === typeof normalized) {
				try {
					normalized = JSON.parse(normalized);
				} catch (error) {
					normalized = [];
				}
			}

			if (!Array.isArray(normalized)) {
				return [];
			}

			return normalized.map(function(value) {
				return parseInt(value, 10) || 0;
			}).filter(function(value) {
				return value > 0;
			});
		},

		/**
		 * Read a localized Blueprint Presets string with a fallback.
		 *
		 * @param {string} key Localized object key.
		 * @param {string} fallback Fallback string.
		 * @return {string}
		 */
		getL10nValue: function(key, fallback) {
			return (window.aipsBlueprintPresetsL10n && window.aipsBlueprintPresetsL10n[key]) || fallback;
		},

		/**
		 * Read a shared admin localized string with a fallback.
		 *
		 * @param {string} key Localized object key.
		 * @param {string} fallback Fallback string.
		 * @return {string}
		 */
		getSharedAdminL10nValue: function(key, fallback) {
			return (window.aipsAdminL10n && window.aipsAdminL10n[key]) || fallback;
		},

		/**
		 * Extract a user-facing message from an AJAX response.
		 *
		 * @param {Object} response AJAX response payload.
		 * @param {string} fallback Fallback message.
		 * @return {string}
		 */
		getResponseMessage: function(response, fallback) {
			if (response && 'string' === typeof response.data) {
				return response.data;
			}

			if (response && response.data && response.data.message) {
				return response.data.message;
			}

			return fallback;
		},

		/**
		 * Show user feedback through the shared toast helper.
		 *
		 * @param {string} type Toast type.
		 * @param {string} message Toast message.
		 * @return {void}
		 */
		showToast: function(type, message) {
			AIPS.Utilities.showToast(message, type);
		},
	};

	$(document).ready(function() {
		AIPS.BlueprintPresets.init();
	});

})(jQuery);
