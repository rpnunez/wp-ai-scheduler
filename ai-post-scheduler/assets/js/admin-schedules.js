/**
 * Schedule Page JavaScript
 *
 * Page-level controller for the Schedule admin page.
 * Manages create/edit/clone/save/delete, toggle, run-now, search, history view,
 * and bulk actions for both the classic schedule table and the unified schedule view.
 *
 * Extracted from admin.js to provide a single, dedicated controller path for all
 * schedule modal and CRUD flows, replacing duplicated modal-reset logic that
 * previously lived inline in each open/edit/clone handler.
 *
 * @package AI_Post_Scheduler
 * @since   1.7.3
 */

(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * AIPS.Schedules — self-contained page controller for the Schedule admin page.
	 *
	 * Follows the same init()/bindEvents() naming convention used throughout
	 * this plugin (e.g. AIPS.History) so the page can be bootstrapped with a
	 * single AIPS.initSchedules() call without polluting shared AIPS methods.
	 */
	AIPS.Schedules = {

		/* ------------------------------------------------------------------ */
		/* Shared helpers — wizard vs. legacy modal                            */
		/* ------------------------------------------------------------------ */

		/**
		 * Determine which schedule modal is active on the current page.
		 *
		 * Returns the wizard modal when it is present on the page and falls
		 * back to the legacy simple modal otherwise, so callers never need to
		 * branch themselves.
		 *
		 * @return {{ $modal: jQuery, isWizard: boolean }}
		 */
		getActiveModal: function() {
			var $wizard = $('#aips-schedule-wizard-modal');
			return {
				$modal:   $wizard.length ? $wizard : $('#aips-schedule-modal'),
				isWizard: $wizard.length > 0
			};
		},

		/**
		 * Reset and open the schedule wizard in "Add New" mode.
		 *
		 * Clears all wizard form fields, empties the hidden schedule ID, sets
		 * the modal title, navigates to step 1, and shows the modal.
		 *
		 * @param {jQuery} $wizardModal - The schedule wizard modal element.
		 * @param {string} title        - Localised title string for the modal header.
		 */
		openWizard: function($wizardModal, title) {
			$('#aips-schedule-wizard-form')[0].reset();
			$('#sw_schedule_id').val('');
			$wizardModal.find('#aips-schedule-wizard-modal-title').text(title);
			AIPS.wizardGoToStep(1, $wizardModal);
			$wizardModal.show();
		},

		/**
		 * Reset and open the legacy (non-wizard) schedule modal in "Add New" mode.
		 *
		 * Clears the legacy form, empties the hidden schedule ID, and sets the
		 * modal title before showing the modal.
		 *
		 * @param {string} title - Title string for the modal header.
		 */
		openLegacy: function(title) {
			$('#aips-schedule-form')[0].reset();
			$('#schedule_id').val('');
			$('#aips-schedule-modal-title').text(title);
			$('#aips-schedule-modal').show();
		},

		/**
		 * Populate and open the schedule wizard in "Edit" or "Clone" mode.
		 *
		 * Resets the wizard form first, then writes the supplied data object into
		 * each wizard field, formats the start-time as a local datetime-local
		 * value when a next-run timestamp is present, and shows the modal.
		 *
		 * @param {jQuery} $wizardModal - The schedule wizard modal element.
		 * @param {string} title        - Localised title string for the modal header.
		 * @param {Object} data         - Field values to populate.
		 * @param {string|number} data.scheduleId        - ID to write into #sw_schedule_id (empty for clone).
		 * @param {string}        data.scheduleTitle     - Value for #sw_schedule_title.
		 * @param {string}        data.templateId        - Value for #sw_schedule_template.
		 * @param {string}        data.frequency         - Value for #sw_schedule_frequency.
		 * @param {string}        data.topic             - Value for #sw_schedule_topic.
		 * @param {string}        data.articleStructureId - Value for #sw_article_structure_id.
		 * @param {string}        data.rotationPattern   - Value for #sw_rotation_pattern.
		 * @param {number}        data.isActive          - 1 = checked, 0 = unchecked.
		 * @param {string}        [data.nextRun]         - ISO datetime for start-time field.
		 */
		populateWizard: function($wizardModal, title, data) {
			$('#aips-schedule-wizard-form')[0].reset();
			$('#sw_schedule_id').val(data.scheduleId || '');
			$('#sw_schedule_title').val(data.scheduleTitle || '');
			$('#sw_schedule_template').val(data.templateId || '');
			$('#sw_schedule_frequency').val(data.frequency || '');
			$('#sw_schedule_topic').val(data.topic || '');
			$('#sw_article_structure_id').val(data.articleStructureId || '');
			$('#sw_rotation_pattern').val(data.rotationPattern || '');
			$('#sw_schedule_is_active').prop('checked', data.isActive == 1);

			if (data.nextRun) {
				var dt = new Date(data.nextRun);
				if (!isNaN(dt.getTime())) {
					var pad = function(n) { return n < 10 ? '0' + n : n; };
					$('#sw_schedule_start_time').val(
						dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()) +
						'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes())
					);
				}
			}

			$wizardModal.find('#aips-schedule-wizard-modal-title').text(title);
			AIPS.wizardGoToStep(1, $wizardModal);
			$wizardModal.show();
		},

		/**
		 * Populate and open the legacy schedule modal in "Edit" or "Clone" mode.
		 *
		 * @param {string} title  - Title string for the modal header.
		 * @param {Object} data   - Field values to populate (same shape as `populateWizard`).
		 */
		populateLegacy: function(title, data) {
			$('#aips-schedule-form')[0].reset();
			$('#schedule_id').val(data.scheduleId || '');
			$('#schedule_title').val(data.scheduleTitle || '');
			$('#schedule_template').val(data.templateId || '');
			$('#schedule_frequency').val(data.frequency || '');
			$('#schedule_topic').val(data.topic || '');
			$('#article_structure_id').val(data.articleStructureId || '');
			$('#rotation_pattern').val(data.rotationPattern || '');
			$('#schedule_is_active').prop('checked', data.isActive == 1);

			if (data.nextRun) {
				var dt0 = new Date(data.nextRun);
				if (!isNaN(dt0.getTime())) {
					var pad0 = function(n) { return n < 10 ? '0' + n : n; };
					$('#schedule_start_time').val(
						dt0.getFullYear() + '-' + pad0(dt0.getMonth() + 1) + '-' + pad0(dt0.getDate()) +
						'T' + pad0(dt0.getHours()) + ':' + pad0(dt0.getMinutes())
					);
				}
			}

			$('#aips-schedule-modal-title').text(title);
			$('#aips-schedule-modal').show();
		},

		/* ------------------------------------------------------------------ */
		/* Init / Events                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Initialise the Schedules module.
		 *
		 * Registers all delegated event listeners and auto-opens the modal when
		 * the page URL carries a preselect parameter.
		 */
		init: function() {
			this.bindEvents();
			this.initAutoOpen();
		},

		/**
		 * Register all delegated event listeners for the schedule admin UI.
		 *
		 * Uses event delegation on `document` with per-handler references for
		 * schedule CRUD, toggle, run-now, search, and history actions. Modal
		 * close behavior (Escape key, backdrop click, close buttons) continues
		 * to be handled globally by admin.js.
		 */
		bindEvents: function() {
			var S = AIPS.Schedules;

			// Create / edit / clone
			$(document).on('click', '.aips-add-schedule-btn',    S.openScheduleModal);
			$(document).on('click', '.aips-edit-schedule',       S.editSchedule);
			$(document).on('click', '.aips-clone-schedule',      S.cloneSchedule);

			// Save / delete / run / toggle
			$(document).on('click', '.aips-save-schedule',        S.saveSchedule);
			$(document).on('click', '.aips-save-schedule-wizard', S.saveScheduleWizard);
			$(document).on('click', '.aips-delete-schedule',      S.deleteSchedule);
			$(document).on('click', '.aips-run-now-schedule',     S.runNowSchedule);
			$(document).on('change', '.aips-toggle-schedule',     S.toggleSchedule);

			// History
			$(document).on('click', '.aips-view-schedule-history', S.viewScheduleHistory);

			// Classic schedule bulk actions
			$(document).on('change', '#cb-select-all-schedules',   S.toggleAllSchedules);
			$(document).on('change', '.aips-schedule-checkbox',    S.toggleScheduleSelection);
			$(document).on('click', '#aips-schedule-select-all',   S.selectAllSchedules);
			$(document).on('click', '#aips-schedule-unselect-all', S.unselectAllSchedules);
			$(document).on('click', '#aips-schedule-bulk-apply',   S.applyScheduleBulkAction);

			// Classic schedule search
			$(document).on('keyup search', '#aips-schedule-search',       S.filterSchedules);
			$(document).on('click', '#aips-schedule-search-clear',        S.clearScheduleSearch);
			$(document).on('click', '.aips-clear-schedule-search-btn',    S.clearScheduleSearch);

			// Unified schedule page
			$(document).on('change', '#cb-select-all-unified',          S.toggleAllUnified);
			$(document).on('change', '.aips-unified-checkbox',          S.toggleUnifiedSelection);
			$(document).on('click', '#aips-unified-select-all',         S.selectAllUnified);
			$(document).on('click', '#aips-unified-unselect-all',       S.unselectAllUnified);
			$(document).on('click', '#aips-unified-bulk-apply',         S.applyUnifiedBulkAction);
			$(document).on('change', '.aips-unified-toggle-schedule',   S.toggleUnifiedSchedule);
			$(document).on('click', '.aips-unified-run-now',            S.runNowUnified);
			$(document).on('click', '.aips-view-unified-history',       S.viewUnifiedScheduleHistory);
			$(document).on('change', '#aips-unified-type-filter',       S.filterUnifiedByType);
			$(document).on('keyup search', '#aips-unified-search',      S.filterUnifiedSchedules);
			$(document).on('click', '#aips-unified-search-clear',       S.clearUnifiedSearch);
			$(document).on('click', '.aips-clear-unified-search-btn',   S.clearUnifiedSearch);
		},

		/* ------------------------------------------------------------------ */
		/* Open / edit / clone                                                  */
		/* ------------------------------------------------------------------ */

		/**
		 * Open the schedule modal in "Add New" mode.
		 *
		 * Prefers the wizard modal when available; falls back to the legacy modal.
		 *
		 * @param {Event} e - Click event from an `.aips-add-schedule-btn` element.
		 */
		openScheduleModal: function(e) {
			e.preventDefault();
			var active = AIPS.Schedules.getActiveModal();
			if (active.isWizard) {
				AIPS.Schedules.openWizard(
					active.$modal,
					aipsAdminL10n.addNewSchedule || 'Add New Schedule'
				);
			} else {
				AIPS.Schedules.openLegacy('Add New Schedule');
			}
		},

		/**
		 * Open the schedule modal pre-filled with an existing schedule's data.
		 *
		 * Reads all schedule fields from the row's `data-*` attributes and opens
		 * the wizard (or legacy) modal in edit mode.
		 *
		 * @param {Event} e - Click event from an `.aips-edit-schedule` element.
		 */
		editSchedule: function(e) {
			e.preventDefault();

			var $row = $(this).closest('tr');
			var data = {
				scheduleId:         $row.data('schedule-id'),
				templateId:         $row.data('template-id'),
				scheduleTitle:      $row.data('title'),
				frequency:          $row.data('frequency'),
				topic:              $row.data('topic'),
				articleStructureId: $row.data('article-structure-id'),
				rotationPattern:    $row.data('rotation-pattern'),
				nextRun:            $row.data('next-run'),
				isActive:           $row.data('is-active')
			};

			var active = AIPS.Schedules.getActiveModal();
			if (active.isWizard) {
				AIPS.Schedules.populateWizard(
					active.$modal,
					aipsAdminL10n.editSchedule || 'Edit Schedule',
					data
				);
			} else {
				AIPS.Schedules.populateLegacy('Edit Schedule', data);
			}
		},

		/**
		 * Clone an existing schedule into the modal in "Add New" mode.
		 *
		 * Copies field values from the row but leaves schedule ID and start time
		 * blank so a brand-new schedule is created on save.
		 *
		 * @param {Event} e - Click event from an `.aips-clone-schedule` element.
		 */
		cloneSchedule: function(e) {
			e.preventDefault();

			var $row = $(this).closest('tr');
			var data = {
				scheduleId:         '',
				templateId:         $row.data('template-id'),
				scheduleTitle:      $row.data('title'),
				frequency:          $row.data('frequency'),
				topic:              $row.data('topic'),
				articleStructureId: $row.data('article-structure-id'),
				rotationPattern:    $row.data('rotation-pattern'),
				nextRun:            '',
				isActive:           $row.data('is-active')
			};

			var active = AIPS.Schedules.getActiveModal();
			if (active.isWizard) {
				AIPS.Schedules.populateWizard(
					active.$modal,
					aipsAdminL10n.cloneSchedule || 'Clone Schedule',
					data
				);
			} else {
				AIPS.Schedules.populateLegacy('Clone Schedule', data);
			}
		},

		/* ------------------------------------------------------------------ */
		/* Save                                                                 */
		/* ------------------------------------------------------------------ */

		/**
		 * Validate and submit the legacy schedule form via AJAX.
		 *
		 * Runs HTML5 constraint validation, then posts `aips_save_schedule` and
		 * refreshes the schedule table on success.
		 *
		 * @param {Event} e - Click event from an `.aips-save-schedule` element.
		 */
		saveSchedule: function(e) {
			e.preventDefault();

			var $btn  = $(this);
			var $form = $('#aips-schedule-form');

			if (!$form[0].checkValidity()) {
				$form[0].reportValidity();
				return;
			}

			$btn.prop('disabled', true).text(aipsAdminL10n.saving);

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action:              'aips_save_schedule',
					nonce:               aipsAjax.nonce,
					schedule_id:         $('#schedule_id').val(),
					schedule_title:      $('#schedule_title').val(),
					template_id:         $('#schedule_template').val(),
					frequency:           $('#schedule_frequency').val(),
					start_time:          $('#schedule_start_time').val(),
					topic:               $('#schedule_topic').val(),
					article_structure_id: $('#article_structure_id').val(),
					rotation_pattern:    $('#rotation_pattern').val(),
					is_active:           $('#schedule_is_active').is(':checked') ? 1 : 0
				},
				success: function(response) {
					if (response.success) {
						AIPS.Utilities.showToast(response.data.message || 'Schedule saved successfully', 'success');
						$('#aips-schedule-modal').hide();
						AIPS.Schedules.insertOrUpdateScheduleRow(response.data);
					} else {
						AIPS.Utilities.showToast(response.data.message, 'error');
					}
				},
				error: function() {
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				},
				complete: function() {
					$btn.prop('disabled', false).text(aipsAdminL10n.saveSchedule || 'Save Schedule');
				}
			});
		},

		/**
		 * Validate and submit the schedule wizard form via AJAX.
		 *
		 * Runs cross-step field validation, then posts `aips_save_schedule` and
		 * refreshes the schedule table on success.
		 *
		 * @param {Event} e - Click event from an `.aips-save-schedule-wizard` element.
		 */
		saveScheduleWizard: function(e) {
			e.preventDefault();

			var $btn         = $(this);
			var $wizardModal = $('#aips-schedule-wizard-modal');

			var invalid = AIPS.getFirstInvalidStep($wizardModal);
			if (invalid) {
				AIPS.Utilities.showToast(invalid.message, 'warning');
				AIPS.wizardGoToStep(invalid.step, $wizardModal);
				$(invalid.selector).focus();
				return;
			}

			$btn.prop('disabled', true).text(aipsAdminL10n.saving);

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action:              'aips_save_schedule',
					nonce:               aipsAjax.nonce,
					schedule_id:         $('#sw_schedule_id').val(),
					schedule_title:      $('#sw_schedule_title').val(),
					template_id:         $('#sw_schedule_template').val(),
					frequency:           $('#sw_schedule_frequency').val(),
					start_time:          $('#sw_schedule_start_time').val(),
					topic:               $('#sw_schedule_topic').val(),
					article_structure_id: $('#sw_article_structure_id').val(),
					rotation_pattern:    $('#sw_rotation_pattern').val(),
					is_active:           $('#sw_schedule_is_active').is(':checked') ? 1 : 0
				},
				success: function(response) {
					if (response.success) {
						AIPS.Utilities.showToast(
							response.data.message || aipsAdminL10n.scheduleSavedSuccess || 'Schedule saved successfully',
							'success'
						);
						$wizardModal.hide();
						AIPS.Schedules.insertOrUpdateScheduleRow(response.data);
					} else {
						AIPS.Utilities.showToast(response.data.message, 'error');
					}
				},
				error: function() {
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				},
				complete: function() {
					$btn.prop('disabled', false).text(aipsAdminL10n.saveSchedule || 'Save Schedule');
				}
			});
		},

		/**
		 * Insert a new schedule row into the unified table, or update an
		 * existing row in place, using the Template engine.
		 *
		 * Called from `saveSchedule` and `saveScheduleWizard` after a
		 * successful `aips_save_schedule` AJAX response.  Receives the
		 * `response.data` object which must include a `row` key (token map
		 * from the server) and an `is_update` boolean.
		 *
		 * Renders the `aips-tmpl-unified-schedule-row` template via
		 * `AIPS.Templates.renderRaw()` so pre-escaped HTML blobs (badges,
		 * action buttons, run-date cells) are injected without
		 * double-escaping.  Falls back to `location.reload()` when the
		 * template is not found in the DOM (e.g. legacy page without the
		 * `<script type="text/html">` block).
		 *
		 * @param {Object}  responseData          - The `response.data` payload from the server.
		 * @param {Object}  responseData.row       - Token map produced by `build_schedule_row_tokens()`.
		 * @param {boolean} responseData.is_update - `true` when editing an existing schedule.
		 */
		insertOrUpdateScheduleRow: function(responseData) {
			var rowData  = responseData && responseData.row;
			var isUpdate = !!(responseData && responseData.is_update);

			if (!rowData || !rowData.rowKey) {
				// No row data — fall back to a full page reload
				location.reload();
				return;
			}

			var rowHtml = AIPS.Templates.renderRaw('aips-tmpl-unified-schedule-row', rowData);
			if (!rowHtml) {
				// Template not found in DOM
				location.reload();
				return;
			}

			var $newRow = $(rowHtml);

			if (isUpdate) {
				// Replace the existing row in place
				var $existing = $('tr[data-row-key="' + rowData.rowKey + '"]');
				if ($existing.length) {
					$existing.replaceWith($newRow);
					AIPS.Schedules.updateUnifiedBulkActions();
					return;
				}
			}

			// Insert new row — ensure the table exists and is visible
			var $table = $('.aips-unified-schedule-table');
			var $emptyState = $('.aips-empty-state:not(#aips-unified-search-no-results)');

			if (!$table.length) {
				// Table doesn't exist yet (empty-state shown); reload to render full shell
				location.reload();
				return;
			}

			// Hide empty state if visible
			$emptyState.hide();
			$table.find('tbody').prepend($newRow);
			$table.show();

			// Update footer row count
			var rowCount = $table.find('tbody tr.aips-unified-row').length;
			$('.aips-table-footer-count').text(
				rowCount === 1
					? rowCount + ' ' + (aipsAdminL10n.scheduleSingular || 'schedule')
					: rowCount + ' ' + (aipsAdminL10n.schedulePlural  || 'schedules')
			);

			AIPS.Schedules.updateUnifiedBulkActions();
		},

		/* ------------------------------------------------------------------ */
		/* Delete / run / toggle                                                */
		/* ------------------------------------------------------------------ */

		/**
		 * Confirm and permanently delete a single schedule via AJAX.
		 *
		 * @param {Event} e - Click event from an `.aips-delete-schedule` element.
		 */
		deleteSchedule: function(e) {
			e.preventDefault();

			var $el  = $(this);
			var id   = $el.data('id');
			var $row = $el.closest('tr');

			AIPS.Utilities.confirm(aipsAdminL10n.deleteScheduleConfirm, 'Notice', [
				{ label: aipsAdminL10n.confirmCancelButton, className: 'aips-btn aips-btn-primary' },
				{ label: aipsAdminL10n.confirmDeleteButton, className: 'aips-btn aips-btn-danger-solid', action: function() {
					$.ajax({
						url:  aipsAjax.ajaxUrl,
						type: 'POST',
						data: {
							action:      'aips_delete_schedule',
							nonce:       aipsAjax.nonce,
							schedule_id: id
						},
						success: function(response) {
							if (response.success) {
								$row.fadeOut(function() { $(this).remove(); });
							} else {
								AIPS.Utilities.showToast(response.data.message, 'error');
							}
						},
						error: function() {
							AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
						}
					});
				}}
			]);
		},

		/**
		 * Trigger immediate execution of a single schedule via AJAX.
		 *
		 * @param {Event} e - Click event from an `.aips-run-now-schedule` element.
		 */
		runNowSchedule: function(e) {
			e.preventDefault();

			var $btn       = $(this);
			var scheduleId = $btn.data('id');

			if (!scheduleId) {
				return;
			}

			$btn.prop('disabled', true);
			$btn.find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update aips-spin');

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action:      'aips_run_now',
					nonce:       aipsAjax.nonce,
					schedule_id: scheduleId
				},
				success: function(response) {
					if (response.success) {
						var msg = AIPS.escapeHtml(response.data.message || 'Post generated successfully!');
						if (response.data.edit_url) {
							msg += ' <a href="' + AIPS.escapeAttribute(response.data.edit_url) + '" target="_blank">Edit Post</a>';
						}
						AIPS.Utilities.showToast(msg, 'success', { isHtml: true, duration: 8000 });
					} else {
						AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.generationFailed, 'error');
					}
				},
				error: function() {
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				},
				complete: function() {
					$btn.prop('disabled', false);
					$btn.find('.dashicons').removeClass('dashicons-update aips-spin').addClass('dashicons-controls-play');
				}
			});
		},

		/**
		 * Toggle a single schedule's active/inactive status via AJAX.
		 *
		 * Updates the status badge and icon immediately on success, and reverts
		 * the checkbox on error.
		 *
		 * Bound to the `change` event on `.aips-toggle-schedule`.
		 */
		toggleSchedule: function() {
			var $toggle  = $(this);
			var id       = $toggle.data('id');
			var isActive = $toggle.is(':checked') ? 1 : 0;
			var $wrapper = $toggle.closest('.aips-schedule-status-wrapper');
			var $badge   = $wrapper.find('.aips-badge');
			var $icon    = $badge.find('.dashicons');

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action:      'aips_toggle_schedule',
					nonce:       aipsAjax.nonce,
					schedule_id: id,
					is_active:   isActive
				},
				success: function() {
					$badge.removeClass('aips-badge-success aips-badge-neutral aips-badge-error');
					$icon.removeClass('dashicons-yes-alt dashicons-minus dashicons-warning');
					// Remove stale text nodes without touching child elements
					$badge.contents().filter(function() { return this.nodeType === 3; }).remove();

					if (isActive) {
						$badge.addClass('aips-badge-success');
						$icon.addClass('dashicons-yes-alt');
						$icon.after(' Active');
					} else {
						$badge.addClass('aips-badge-neutral');
						$icon.addClass('dashicons-minus');
						$icon.after(' Inactive');
					}

					$toggle.closest('tr').data('is-active', isActive);
				},
				error: function() {
					$toggle.prop('checked', !isActive);
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				}
			});
		},

		/* ------------------------------------------------------------------ */
		/* Schedule history modal                                               */
		/* ------------------------------------------------------------------ */

		/**
		 * Open the Schedule History modal and load history entries.
		 *
		 * Resets the modal state, shows a loading indicator, fetches entries via
		 * `aips_get_schedule_history`, and renders a timeline list on success.
		 *
		 * @param {Event} e - Click event from an `.aips-view-schedule-history` element.
		 */
		viewScheduleHistory: function(e) {
			e.preventDefault();

			var $btn          = $(this);
			var scheduleId    = $btn.data('id');
			var scheduleName  = $btn.data('name') || scheduleId;

			if (!scheduleId) {
				return;
			}

			var $modal   = $('#aips-schedule-history-modal');
			var $title   = $modal.find('#aips-schedule-history-modal-title');
			var $loading = $modal.find('#aips-schedule-history-loading');
			var $empty   = $modal.find('#aips-schedule-history-empty');
			var $list    = $modal.find('#aips-schedule-history-list');

			$title.text('Schedule History: ' + scheduleName);
			$loading.show();
			$empty.hide();
			$list.hide().empty();
			$modal.show();

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action:      'aips_get_schedule_history',
					nonce:       aipsAjax.nonce,
					schedule_id: scheduleId
				},
				success: function(response) {
					$loading.hide();

					if (!response.success) {
						AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.failedToLoadHistory, 'error');
						$modal.hide();
						return;
					}

					var entries = response.data.entries;
					if (!entries || entries.length === 0) {
						$empty.show();
						return;
					}

					AIPS.Schedules.renderTimelineEntries($list, entries);
					$list.show();
				},
				error: function() {
					$loading.hide();
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
					$modal.hide();
				}
			});
		},

		/* ------------------------------------------------------------------ */
		/* Classic schedule bulk actions                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Sync all schedule checkboxes with the "select all" header.
		 *
		 * Bound to the `change` event on `#cb-select-all-schedules`.
		 */
		toggleAllSchedules: function() {
			var isChecked = $(this).prop('checked');
			$('.aips-schedule-checkbox').prop('checked', isChecked);
			AIPS.Schedules.updateScheduleBulkActions();
		},

		/**
		 * Keep the "select all" checkbox in sync when individual rows are toggled.
		 *
		 * Bound to the `change` event on `.aips-schedule-checkbox`.
		 */
		toggleScheduleSelection: function() {
			var total   = $('.aips-schedule-checkbox').length;
			var checked = $('.aips-schedule-checkbox:checked').length;
			$('#cb-select-all-schedules').prop('checked', total > 0 && checked === total);
			AIPS.Schedules.updateScheduleBulkActions();
		},

		/**
		 * Check every schedule row checkbox and update bulk-action controls.
		 */
		selectAllSchedules: function() {
			$('.aips-schedule-checkbox').prop('checked', true);
			$('#cb-select-all-schedules').prop('checked', true);
			AIPS.Schedules.updateScheduleBulkActions();
		},

		/**
		 * Uncheck every schedule row checkbox and update bulk-action controls.
		 */
		unselectAllSchedules: function() {
			$('.aips-schedule-checkbox').prop('checked', false);
			$('#cb-select-all-schedules').prop('checked', false);
			AIPS.Schedules.updateScheduleBulkActions();
		},

		/**
		 * Update the schedule bulk-action toolbar to reflect the current selection.
		 *
		 * Enables/disables the Apply button and shows/hides the selection count label.
		 */
		updateScheduleBulkActions: function() {
			var count       = $('.aips-schedule-checkbox:checked').length;
			var $applyBtn   = $('#aips-schedule-bulk-apply');
			var $unselectBtn = $('#aips-schedule-unselect-all');
			var $countLabel = $('#aips-schedule-selected-count');

			$applyBtn.prop('disabled', count === 0);
			$unselectBtn.prop('disabled', count === 0);

			if (count > 0) {
				$countLabel.text(count + ' selected').show();
			} else {
				$countLabel.hide();
			}
		},

		/**
		 * Dispatch the selected bulk action against all checked schedule rows.
		 *
		 * Supported actions: `delete`, `pause`, `activate`, `run_now`.
		 * For `delete` and `run_now`, a confirmation dialog is shown first.
		 *
		 * @param {Event} e - Click event from `#aips-schedule-bulk-apply`.
		 */
		applyScheduleBulkAction: function(e) {
			e.preventDefault();

			var action = $('#aips-schedule-bulk-action').val();
			if (!action) {
				AIPS.Utilities.showToast('Please select a bulk action.', 'warning');
				return;
			}

			var ids = [];
			$('.aips-schedule-checkbox:checked').each(function() {
				ids.push($(this).val());
			});

			if (ids.length === 0) {
				AIPS.Utilities.showToast(aipsAdminL10n.selectAtLeastOneSchedule, 'warning');
				return;
			}

			var S = AIPS.Schedules;

			if (action === 'delete') {
				var deleteMsg = ids.length === 1
					? aipsAdminL10n.deleteOneScheduleConfirm
					: aipsAdminL10n.deleteMultipleSchedulesConfirm.replace('%d', ids.length);
				AIPS.Utilities.confirm(
					deleteMsg,
					'Delete Schedules',
					[
						{ label: aipsAdminL10n.confirmCancelButton, className: 'aips-btn aips-btn-secondary' },
						{ label: aipsAdminL10n.confirmDeleteButton, className: 'aips-btn aips-btn-danger-solid', action: function() { S.bulkDeleteSchedules(ids); } }
					]
				);
			} else if (action === 'pause') {
				S.bulkToggleSchedules(ids, 0);
			} else if (action === 'activate') {
				S.bulkToggleSchedules(ids, 1);
			} else if (action === 'run_now') {
				// Fetch estimated post count then confirm
				$.ajax({
					url:  aipsAjax.ajaxUrl,
					type: 'POST',
					data: {
						action: 'aips_get_schedules_post_count',
						nonce:  aipsAjax.nonce,
						ids:    ids
					},
					success: function(response) {
						var count   = response.success ? (response.data.count || ids.length) : ids.length;
						var runMsg  = count === 1
							? aipsAdminL10n.runPostsConfirmSingular
							: aipsAdminL10n.runPostsConfirmPlural.replace('%d', count);
						AIPS.Utilities.confirm(
							runMsg,
							aipsAdminL10n.runSchedulesNow,
							[
								{ label: aipsAdminL10n.cancel,     className: 'aips-btn aips-btn-secondary' },
								{ label: aipsAdminL10n.yesRunNow,  className: 'aips-btn aips-btn-primary', action: function() { S.bulkRunNowSchedules(ids); } }
							]
						);
					},
					error: function() {
						var runMsg = ids.length === 1
							? aipsAdminL10n.runOneScheduleConfirm
							: aipsAdminL10n.runMultipleSchedulesConfirm.replace('%d', ids.length);
						AIPS.Utilities.confirm(
							runMsg,
							aipsAdminL10n.runSchedulesNow,
							[
								{ label: aipsAdminL10n.cancel,    className: 'aips-btn aips-btn-secondary' },
								{ label: aipsAdminL10n.yesRunNow, className: 'aips-btn aips-btn-primary', action: function() { S.bulkRunNowSchedules(ids); } }
							]
						);
					}
				});
			}
		},

		/**
		 * Delete multiple schedules via `aips_bulk_delete_schedules`.
		 *
		 * @param {Array<string>} ids - Schedule ID strings to delete.
		 */
		bulkDeleteSchedules: function(ids) {
			var $applyBtn = $('#aips-schedule-bulk-apply');
			$applyBtn.prop('disabled', true).text('Deleting...');

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_bulk_delete_schedules',
					nonce:  aipsAjax.nonce,
					ids:    ids
				},
				success: function(response) {
					if (response.success) {
						AIPS.Utilities.showToast(response.data.message, 'success');
						ids.forEach(function(id) {
							$('tr[data-schedule-id="' + id + '"]').fadeOut(function() {
								$(this).remove();
							});
						});
						$('#cb-select-all-schedules').prop('checked', false);
						AIPS.Schedules.updateScheduleBulkActions();
					} else {
						AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.failedToDeleteSchedules, 'error');
					}
				},
				error: function() {
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				},
				complete: function() {
					$applyBtn.text('Apply');
					AIPS.Schedules.updateScheduleBulkActions();
				}
			});
		},

		/**
		 * Activate or pause multiple schedules via `aips_bulk_toggle_schedules`.
		 *
		 * @param {Array<string>} ids      - Schedule ID strings to update.
		 * @param {number}        isActive - 1 to activate, 0 to pause.
		 */
		bulkToggleSchedules: function(ids, isActive) {
			var $applyBtn = $('#aips-schedule-bulk-apply');
			$applyBtn.prop('disabled', true).text(isActive ? 'Activating...' : 'Pausing...');

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action:    'aips_bulk_toggle_schedules',
					nonce:     aipsAjax.nonce,
					ids:       ids,
					is_active: isActive
				},
				success: function(response) {
					if (response.success) {
						AIPS.Utilities.showToast(response.data.message, 'success');
						ids.forEach(function(id) {
							var $row     = $('tr[data-schedule-id="' + id + '"]');
							var $toggle  = $row.find('.aips-toggle-schedule');
							var $wrapper = $row.find('.aips-schedule-status-wrapper');
							var $badge   = $wrapper.find('.aips-badge');
							var $icon    = $badge.find('.dashicons');

							$toggle.prop('checked', isActive === 1);
							$badge.removeClass('aips-badge-success aips-badge-neutral aips-badge-error');
							$icon.removeClass('dashicons-yes-alt dashicons-minus dashicons-warning');
							$badge.contents().filter(function() { return this.nodeType === 3; }).remove();

							if (isActive) {
								$badge.addClass('aips-badge-success');
								$icon.addClass('dashicons-yes-alt');
								$icon.after(' Active');
							} else {
								$badge.addClass('aips-badge-neutral');
								$icon.addClass('dashicons-minus');
								$icon.after(' Inactive');
							}
							$row.data('is-active', isActive);
						});
					} else {
						AIPS.Utilities.showToast(response.data.message || 'Failed to update schedules.', 'error');
					}
				},
				error: function() {
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				},
				complete: function() {
					$applyBtn.text('Apply');
					AIPS.Schedules.updateScheduleBulkActions();
				}
			});
		},

		/**
		 * Run multiple schedules immediately via `aips_bulk_run_now_schedules`.
		 *
		 * @param {Array<string>} ids - Schedule ID strings to run.
		 */
		bulkRunNowSchedules: function(ids) {
			var $applyBtn = $('#aips-schedule-bulk-apply');
			$applyBtn.prop('disabled', true).text('Running...');

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_bulk_run_now_schedules',
					nonce:  aipsAjax.nonce,
					ids:    ids
				},
				success: function(response) {
					if (response.success) {
						AIPS.Utilities.showToast(response.data.message, 'success', { duration: 8000 });
					} else {
						AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.bulkRunFailed, 'error');
					}
				},
				error: function() {
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				},
				complete: function() {
					$applyBtn.text('Apply');
					AIPS.Schedules.updateScheduleBulkActions();
				}
			});
		},

		/* ------------------------------------------------------------------ */
		/* Classic schedule search                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * Filter the classic schedule table in real time by the search term.
		 *
		 * Matches against `.column-template`, `.column-structure`, and
		 * `.column-frequency` cells.
		 *
		 * Bound to the `keyup` and `search` events on `#aips-schedule-search`.
		 */
		filterSchedules: function() {
			var term       = $('#aips-schedule-search').val().toLowerCase().trim();
			var $rows      = $('.aips-schedule-table tbody tr');
			var $noResults = $('#aips-schedule-search-no-results');
			var $table     = $('.aips-schedule-table');
			var $clearBtn  = $('#aips-schedule-search-clear');
			var hasVisible = false;

			$clearBtn.toggle(term.length > 0);

			$rows.each(function() {
				var $row      = $(this);
				var template  = $row.find('.column-template').text().toLowerCase();
				var structure = $row.find('.column-structure').text().toLowerCase();
				var frequency = $row.find('.column-frequency').text().toLowerCase();
				var match     = template.indexOf(term) > -1 || structure.indexOf(term) > -1 || frequency.indexOf(term) > -1;

				$row.toggle(match);
				if (match) { hasVisible = true; }
			});

			if (!hasVisible && term.length > 0) {
				$table.hide();
				$noResults.show();
			} else {
				$table.show();
				$noResults.hide();
			}
		},

		/**
		 * Clear the schedule search field and restore all rows.
		 *
		 * @param {Event} e - Click event from `#aips-schedule-search-clear` or
		 *                    `.aips-clear-schedule-search-btn`.
		 */
		clearScheduleSearch: function(e) {
			e.preventDefault();
			$('#aips-schedule-search').val('').trigger('keyup');
		},

		/* ------------------------------------------------------------------ */
		/* Unified schedule page                                                */
		/* ------------------------------------------------------------------ */

		/**
		 * Navigate to the schedule page filtered by type when the dropdown changes.
		 *
		 * @param {Event} e - Change event from `#aips-unified-type-filter`.
		 */
		filterUnifiedByType: function(e) {
			var type   = $(this).val();
			var url    = window.location.href.split('?')[0];
			var params = new URLSearchParams(window.location.search);
			params.delete('schedule_type');
			if (type) {
				params.set('schedule_type', type);
			}
			var qs = params.toString();
			window.location.href = url + (qs ? '?' + qs : '');
		},

		/**
		 * Live-filter the unified schedule table rows by the search term.
		 *
		 * @param {Event} e - Keyup / search event from `#aips-unified-search`.
		 */
		filterUnifiedSchedules: function(e) {
			var term   = $(this).val().toLowerCase().trim();
			var $clear = $('#aips-unified-search-clear');
			$clear.toggle(term.length > 0);

			var $rows = $('.aips-unified-row');
			var found = 0;

			$rows.each(function() {
				var text  = $(this).text().toLowerCase();
				var match = !term || text.indexOf(term) !== -1;
				$(this).toggle(match);
				if (match) { found++; }
			});

			$('#aips-unified-search-no-results').toggle(found === 0 && $rows.length > 0);
		},

		/**
		 * Clear the unified schedule search field and restore all rows.
		 *
		 * @param {Event} e - Click event from `#aips-unified-search-clear` or
		 *                    `.aips-clear-unified-search-btn`.
		 */
		clearUnifiedSearch: function(e) {
			e.preventDefault();
			$('#aips-unified-search').val('');
			$('.aips-unified-row').show();
			$('#aips-unified-search-clear').hide();
			$('#aips-unified-search-no-results').hide();
		},

		/**
		 * Sync all unified-schedule checkboxes with the "select all" header.
		 */
		toggleAllUnified: function() {
			var isChecked = $(this).prop('checked');
			$('.aips-unified-checkbox:visible').prop('checked', isChecked);
			AIPS.Schedules.updateUnifiedBulkActions();
		},

		/**
		 * Keep the "select all" in sync when individual rows are toggled.
		 */
		toggleUnifiedSelection: function() {
			var total   = $('.aips-unified-checkbox:visible').length;
			var checked = $('.aips-unified-checkbox:visible:checked').length;
			$('#cb-select-all-unified').prop('checked', total > 0 && checked === total);
			AIPS.Schedules.updateUnifiedBulkActions();
		},

		/** Check all visible unified-schedule rows. */
		selectAllUnified: function() {
			$('.aips-unified-checkbox:visible').prop('checked', true);
			$('#cb-select-all-unified').prop('checked', true);
			AIPS.Schedules.updateUnifiedBulkActions();
		},

		/** Uncheck all unified-schedule rows. */
		unselectAllUnified: function() {
			$('.aips-unified-checkbox').prop('checked', false);
			$('#cb-select-all-unified').prop('checked', false);
			AIPS.Schedules.updateUnifiedBulkActions();
		},

		/**
		 * Enable or disable the unified bulk-action Apply button and show the
		 * selection count.
		 */
		updateUnifiedBulkActions: function() {
			var count     = $('.aips-unified-checkbox:checked').length;
			var $apply    = $('#aips-unified-bulk-apply');
			var $unselect = $('#aips-unified-unselect-all');
			var $countLbl = $('#aips-unified-selected-count');

			$apply.prop('disabled', count === 0);
			$unselect.prop('disabled', count === 0);

			if (count > 0) {
				$countLbl.text(count + ' selected').show();
			} else {
				$countLbl.hide();
			}
		},

		/**
		 * Parse selected unified-schedule checkboxes and dispatch the chosen action.
		 *
		 * Supported actions: `run_now`, `pause`, `resume`.
		 *
		 * @param {Event} e - Click event from `#aips-unified-bulk-apply`.
		 */
		applyUnifiedBulkAction: function(e) {
			e.preventDefault();

			var action = $('#aips-unified-bulk-action').val();
			if (!action) {
				AIPS.Utilities.showToast(aipsAdminL10n.selectBulkAction || 'Please select a bulk action.', 'warning');
				return;
			}

			var items = [];
			$('.aips-unified-checkbox:checked').each(function() {
				var parts = $(this).val().split(':');
				if (parts.length === 2) {
					items.push({ type: parts[0], id: parseInt(parts[1], 10) });
				}
			});

			if (items.length === 0) {
				AIPS.Utilities.showToast(aipsAdminL10n.selectAtLeastOne || 'Please select at least one schedule.', 'warning');
				return;
			}

			var S = AIPS.Schedules;

			if (action === 'run_now') {
				AIPS.Utilities.confirm(
					aipsAdminL10n.runSchedulesNow || ('Run ' + items.length + ' schedule(s) now?'),
					'Run Now',
					[
						{ label: aipsAdminL10n.cancel || 'Cancel',        className: 'aips-btn aips-btn-secondary' },
						{ label: aipsAdminL10n.yesRunNow || 'Yes, Run Now', className: 'aips-btn aips-btn-primary', action: function() {
							S.unifiedBulkRunNow(items);
						}}
					]
				);
			} else if (action === 'pause') {
				S.unifiedBulkToggle(items, 0);
			} else if (action === 'resume') {
				S.unifiedBulkToggle(items, 1);
			}
		},

		/**
		 * Bulk run-now for mixed-type schedules via `aips_unified_bulk_run_now`.
		 *
		 * @param {Array<{type: string, id: number}>} items
		 */
		unifiedBulkRunNow: function(items) {
			var $applyBtn = $('#aips-unified-bulk-apply');
			$applyBtn.prop('disabled', true).text('Running...');

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_unified_bulk_run_now',
					nonce:  aipsAjax.nonce,
					items:  items
				},
				success: function(response) {
					if (response.success) {
						AIPS.Utilities.showToast(response.data.message, 'success', { duration: 8000 });
					} else {
						AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.errorOccurred, 'error');
					}
				},
				error: function() {
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				},
				complete: function() {
					$applyBtn.prop('disabled', false).text('Apply');
					AIPS.Schedules.updateUnifiedBulkActions();
				}
			});
		},

		/**
		 * Bulk pause/resume mixed-type schedules via `aips_unified_bulk_toggle`.
		 *
		 * @param {Array<{type: string, id: number}>} items
		 * @param {number} isActive - 1 to resume, 0 to pause.
		 */
		unifiedBulkToggle: function(items, isActive) {
			var $applyBtn = $('#aips-unified-bulk-apply');
			$applyBtn.prop('disabled', true).text(isActive ? 'Resuming...' : 'Pausing...');

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action:    'aips_unified_bulk_toggle',
					nonce:     aipsAjax.nonce,
					items:     items,
					is_active: isActive
				},
				success: function(response) {
					if (response.success) {
						var data         = response.data || {};
						var updatedItems = Array.isArray(data.updated_items) ? data.updated_items : null;
						var failedItems  = Array.isArray(data.failed_items) ? data.failed_items : null;
						var errorItems   = (!updatedItems && Array.isArray(data.errors)) ? data.errors : null;

						var failedKeysMap = {};

						if (failedItems) {
							failedItems.forEach(function(item) {
								if (item && item.type && typeof item.id !== 'undefined') {
									failedKeysMap[item.type + ':' + item.id] = true;
								}
							});
						} else if (errorItems) {
							errorItems.forEach(function(item) {
								if (item && item.type && typeof item.id !== 'undefined') {
									failedKeysMap[item.type + ':' + item.id] = true;
								}
							});
							failedItems = errorItems;
						}

						var successfulItems;
						if (updatedItems) {
							successfulItems = updatedItems;
						} else if (Object.keys(failedKeysMap).length > 0) {
							successfulItems = items.filter(function(item) {
								return !failedKeysMap[item.type + ':' + item.id];
							});
						} else {
							successfulItems = items;
						}

						AIPS.Utilities.showToast(data.message, 'success');

						successfulItems.forEach(function(item) {
							if (!item || !item.type || typeof item.id === 'undefined') {
								return;
							}
							var $row = $('tr[data-row-key="' + item.type + ':' + item.id + '"]');
							if ($row.length) {
								AIPS.Schedules.updateUnifiedRowStatus($row, isActive);
								if (Object.keys(failedKeysMap).length > 0) {
									$row.find('.aips-unified-checkbox').prop('checked', false);
								}
							}
						});

						if (Object.keys(failedKeysMap).length === 0) {
							AIPS.Schedules.unselectAllUnified();
						}
					} else {
						AIPS.Utilities.showToast((response.data && response.data.message) || aipsAdminL10n.errorOccurred, 'error');
					}
				},
				error: function() {
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				},
				complete: function() {
					$applyBtn.prop('disabled', false).text('Apply');
					AIPS.Schedules.updateUnifiedBulkActions();
				}
			});
		},

		/**
		 * Toggle a single unified schedule's active status via AJAX.
		 *
		 * Bound to the `change` event on `.aips-unified-toggle-schedule`.
		 */
		toggleUnifiedSchedule: function() {
			var $toggle  = $(this);
			var id       = $toggle.data('id');
			var type     = $toggle.data('type');
			var isActive = $toggle.is(':checked') ? 1 : 0;
			var $row     = $toggle.closest('tr');

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action:    'aips_unified_toggle',
					nonce:     aipsAjax.nonce,
					id:        id,
					type:      type,
					is_active: isActive
				},
				success: function(response) {
					if (response.success) {
						AIPS.Schedules.updateUnifiedRowStatus($row, isActive);
					} else {
						$toggle.prop('checked', !isActive);
						AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.errorOccurred, 'error');
					}
				},
				error: function() {
					$toggle.prop('checked', !isActive);
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				}
			});
		},

		/**
		 * Update the status badge and toggle for a unified schedule row.
		 *
		 * @param {jQuery} $row     - The `<tr>` element to update.
		 * @param {number} isActive - 1 = active/resumed, 0 = paused.
		 */
		updateUnifiedRowStatus: function($row, isActive) {
			var $toggle  = $row.find('.aips-unified-toggle-schedule');
			var $wrapper = $row.find('.aips-schedule-status-wrapper');
			var $badge   = $wrapper.find('.aips-badge');
			var $icon    = $badge.find('.dashicons');

			$toggle.prop('checked', isActive === 1);
			$badge.removeClass('aips-badge-success aips-badge-neutral aips-badge-error');
			$icon.removeClass('dashicons-yes-alt dashicons-minus dashicons-warning');
			$badge.contents().filter(function() { return this.nodeType === 3; }).remove();

			if (isActive) {
				$badge.addClass('aips-badge-success');
				$icon.addClass('dashicons-yes-alt');
				$icon.after(' Active');
			} else {
				$badge.addClass('aips-badge-neutral');
				$icon.addClass('dashicons-minus');
				$icon.after(' Paused');
			}
			$row.data('is-active', isActive);
		},

		/**
		 * Run a single unified schedule immediately via AJAX.
		 *
		 * @param {Event} e - Click event from `.aips-unified-run-now`.
		 */
		runNowUnified: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var id   = $btn.data('id');
			var type = $btn.data('type');

			if (!id || !type) { return; }

			$btn.prop('disabled', true);
			$btn.find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update aips-spin');

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_unified_run_now',
					nonce:  aipsAjax.nonce,
					id:     id,
					type:   type
				},
				success: function(response) {
					if (response.success) {
						var msg = AIPS.escapeHtml(response.data.message || 'Executed successfully!');
						if (response.data.edit_url) {
							msg += ' <a href="' + AIPS.escapeAttribute(response.data.edit_url) + '" target="_blank">Edit Post</a>';
						}
						AIPS.Utilities.showToast(msg, 'success', { isHtml: true, duration: 8000 });
					} else {
						AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.generationFailed, 'error');
					}
				},
				error: function() {
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
				},
				complete: function() {
					$btn.prop('disabled', false);
					$btn.find('.dashicons').removeClass('dashicons-update aips-spin').addClass('dashicons-controls-play');
				}
			});
		},

		/**
		 * Open the Schedule History modal and load entries for any schedule type.
		 *
		 * @param {Event} e - Click event from `.aips-view-unified-history`.
		 */
		viewUnifiedScheduleHistory: function(e) {
			e.preventDefault();

			var $btn  = $(this);
			var id    = $btn.data('id');
			var type  = $btn.data('type');
			var name  = $btn.data('name') || id;

			if (!id || !type) { return; }

			var $modal   = $('#aips-schedule-history-modal');
			var $title   = $modal.find('#aips-schedule-history-modal-title');
			var $loading = $modal.find('#aips-schedule-history-loading');
			var $empty   = $modal.find('#aips-schedule-history-empty');
			var $list    = $modal.find('#aips-schedule-history-list');

			$title.text('Previous Runs: ' + name);
			$loading.show();
			$empty.hide();
			$list.hide().empty();
			$modal.show();

			$.ajax({
				url:  aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_unified_schedule_history',
					nonce:  aipsAjax.nonce,
					id:     id,
					type:   type
				},
				success: function(response) {
					$loading.hide();

					if (!response.success) {
						AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.errorOccurred, 'error');
						$modal.hide();
						return;
					}

					var entries = response.data.entries;
					if (!entries || entries.length === 0) {
						$empty.show();
						return;
					}

					AIPS.Schedules.renderTimelineEntries($list, entries, true);
					$list.show();
				},
				error: function() {
					$loading.hide();
					AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
					$modal.hide();
				}
			});
		},

		/* ------------------------------------------------------------------ */
		/* Shared timeline renderer                                             */
		/* ------------------------------------------------------------------ */

		/**
		 * Render a list of history-entry objects as a timeline `<li>` set.
		 *
		 * Used by both `viewScheduleHistory` and `viewUnifiedScheduleHistory` to
		 * eliminate the duplicated rendering block that previously lived in each handler.
		 *
		 * @param {jQuery}  $list    - The `<ul>` or `<ol>` element to append items to.
		 * @param {Array}   entries  - Array of entry objects from the AJAX response.
		 * @param {boolean} [isUnified=false] - Pass `true` to include author/topic icon mappings.
		 */
		renderTimelineEntries: function($list, entries, isUnified) {
			var iconMap = {
				'schedule_created':          { icon: 'dashicons-plus-alt',      cls: 'aips-timeline-created'  },
				'schedule_updated':          { icon: 'dashicons-edit',           cls: 'aips-timeline-updated'  },
				'schedule_enabled':          { icon: 'dashicons-yes-alt',        cls: 'aips-timeline-enabled'  },
				'schedule_disabled':         { icon: 'dashicons-minus',          cls: 'aips-timeline-disabled' },
				'schedule_executed':         { icon: 'dashicons-controls-play',  cls: 'aips-timeline-executed' },
				'manual_schedule_started':   { icon: 'dashicons-controls-play',  cls: 'aips-timeline-executed' },
				'manual_schedule_completed': { icon: 'dashicons-yes',            cls: 'aips-timeline-success'  },
				'manual_schedule_failed':    { icon: 'dashicons-warning',        cls: 'aips-timeline-error'    },
				'schedule_failed':           { icon: 'dashicons-warning',        cls: 'aips-timeline-error'    },
				'post_published':            { icon: 'dashicons-media-document', cls: 'aips-timeline-success'  },
				'post_draft':                { icon: 'dashicons-media-document', cls: 'aips-timeline-draft'    },
				'post_generated':            { icon: 'dashicons-media-document', cls: 'aips-timeline-draft'    }
			};

			if (isUnified) {
				iconMap['author_topic_generation'] = { icon: 'dashicons-tag',        cls: 'aips-timeline-executed' };
				iconMap['topic_post_generation']   = { icon: 'dashicons-admin-users', cls: 'aips-timeline-executed' };
			}

			var defaultIcon = { icon: 'dashicons-info', cls: '' };

			entries.forEach(function(entry) {
				var info    = iconMap[entry.event_type] || defaultIcon;
				var isError = (entry.history_type_id === 2 || entry.event_status === 'failed');
				if (isError && !info.cls) {
					info = { icon: 'dashicons-warning', cls: 'aips-timeline-error' };
				}

				var $item    = $('<li>', { 'class': 'aips-timeline-item ' + info.cls });
				var $icon    = $('<span>', { 'class': 'aips-timeline-icon', 'aria-hidden': 'true' })
				                   .append($('<span>', { 'class': 'dashicons ' + info.icon }));
				var $content = $('<div>', { 'class': 'aips-timeline-content' });
				var $msg     = $('<p>',    { 'class': 'aips-timeline-message' }).text(entry.message || entry.log_type);
				var $time    = $('<time>', { 'class': 'aips-timeline-timestamp', 'datetime': entry.timestamp })
				                   .text(entry.timestamp);

				$content.append($msg).append($time);
				$item.append($icon).append($content);
				$list.append($item);
			});
		},

		/* ------------------------------------------------------------------ */
		/* Auto-open on page load                                               */
		/* ------------------------------------------------------------------ */

		/**
		 * Auto-open the schedule modal when the page is loaded with a
		 * `?schedule_template=` or `?schedule_structure=` query parameter, or
		 * when the modal element carries matching `data-preselect-*` attributes.
		 *
		 * Cleans the URL after opening so a refresh does not reopen the modal.
		 */
		initAutoOpen: function() {
			var $wizardModal = $('#aips-schedule-wizard-modal');
			var $legacyModal = $('#aips-schedule-modal');

			var $modal = $wizardModal.length ? $wizardModal : $legacyModal;
			if (!$modal.length) { return; }

			var preselectId          = $modal.data('preselect-template');
			var preselectStructureId = $modal.data('preselect-structure');

			if (!preselectId && !preselectStructureId) {
				var urlParams = null;
				try {
					urlParams = new URL(window.location.href).searchParams;
				} catch (e) {
					try {
						urlParams = new URLSearchParams(window.location.search);
					} catch (e2) {
						urlParams = null;
					}
				}

				if (urlParams) {
					preselectId          = urlParams.get('schedule_template') || '';
					preselectStructureId = urlParams.get('schedule_structure') || '';
				}
			}

			var preselectIdNum          = parseInt(preselectId, 10);
			var preselectStructureIdNum = parseInt(preselectStructureId, 10);

			if ((!preselectIdNum || preselectIdNum <= 0) && (!preselectStructureIdNum || preselectStructureIdNum <= 0)) {
				return;
			}

			if ($wizardModal.length) {
				var $wizardForm = $('#aips-schedule-wizard-form');
				if (!$wizardForm.length) { return; }

				$wizardForm[0].reset();
				$('#sw_schedule_id').val('');

				if (preselectIdNum > 0) {
					$('#sw_schedule_template').val(preselectIdNum);
				}
				if (preselectStructureIdNum > 0) {
					$('#sw_article_structure_id').val(preselectStructureIdNum);
				}

				$wizardModal.find('#aips-schedule-wizard-modal-title').text(aipsAdminL10n.addNewSchedule || 'Add New Schedule');
				AIPS.wizardGoToStep(1, $wizardModal);
				$wizardModal.show();
			} else {
				var $legacyForm = $('#aips-schedule-form');
				if (!$legacyForm.length) { return; }

				$legacyForm[0].reset();
				$('#schedule_id').val('');

				if (preselectIdNum > 0) {
					$('#schedule_template').val(preselectIdNum);
				}
				if (preselectStructureIdNum > 0) {
					$('#article_structure_id').val(preselectStructureIdNum);
				}

				$('#aips-schedule-modal-title').text('Add New Schedule');
				$legacyModal.show();
			}

			// Clean the URL to prevent re-triggering on refresh
			if (window.history && window.history.replaceState) {
				try {
					var cleanUrlObj = new URL(window.location.href);
					cleanUrlObj.searchParams.delete('schedule_template');
					cleanUrlObj.searchParams.delete('schedule_structure');
					cleanUrlObj.hash = '';
					window.history.replaceState(null, '', cleanUrlObj.toString());
				} catch (e) {
					var cleanUrl = window.location.href
						.replace(/[?&]schedule_template=[^&]*/, '')
						.replace(/[?&]schedule_structure=[^&]*/, '')
						.replace(/\?&/, '?')
						.replace(/\?$/, '')
						.replace(/#open_schedule_modal$/, '');
					window.history.replaceState(null, '', cleanUrl);
				}
			}
		}
	};

	/**
	 * Expose a page-scoped init so the global AIPS.init (owned by admin.js) is
	 * not overwritten.
	 *
	 * @return {void}
	 */
	AIPS.initSchedules = function() {
		AIPS.Schedules.init();
	};

	$(document).ready(function() {
		AIPS.initSchedules();
	});

})(jQuery);
