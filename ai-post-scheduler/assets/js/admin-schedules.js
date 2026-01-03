/**
 * Admin Schedules Component
 * 
 * Handles all schedule-related functionality including CRUD operations,
 * cloning, toggling active status, search, and filtering for the Schedules admin page.
 * 
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * Schedules Component
	 * 
	 * Manages schedule operations including create, clone, edit, delete,
	 * toggle active status, search, and filtering.
	 */
	AIPS.Schedules = {
		/**
		 * Initialize the Schedules component.
		 * 
		 * Binds all schedule-related event handlers.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind schedule-specific event handlers.
		 */
		bindEvents: function() {
			// Schedule CRUD operations
			$(document).on('click', '.aips-add-schedule-btn', this.openScheduleModal);
			$(document).on('click', '.aips-clone-schedule', this.cloneSchedule);
			$(document).on('click', '.aips-save-schedule', this.saveSchedule);
			$(document).on('click', '.aips-delete-schedule', this.deleteSchedule);
			$(document).on('change', '.aips-toggle-schedule', this.toggleSchedule);

			// Schedule search and filtering
			$(document).on('keyup search', '#aips-schedule-search', this.filterSchedules);
			$(document).on('click', '#aips-schedule-search-clear', this.clearScheduleSearch);
			$(document).on('click', '.aips-clear-schedule-search-btn', this.clearScheduleSearch);
		},

		/**
		 * Open the schedule modal for creating a new schedule.
		 * 
		 * Resets the form and displays the modal with "Add New Schedule" title.
		 * 
		 * @param {Event} e - The click event.
		 */
		openScheduleModal: function(e) {
			e.preventDefault();
			$('#aips-schedule-form')[0].reset();
			$('#schedule_id').val('');
			$('#aips-schedule-modal-title').text('Add New Schedule');
			$('#aips-schedule-modal').show();
		},

		/**
		 * Clone an existing schedule.
		 * 
		 * Copies schedule data from the table row into the form for creating
		 * a new schedule with the same settings.
		 * 
		 * @param {Event} e - The click event.
		 */
		cloneSchedule: function(e) {
			e.preventDefault();

			// Reset form first
			$('#aips-schedule-form')[0].reset();
			$('#schedule_id').val('');

			// Get data from the row
			var $row = $(this).closest('tr');
			var templateId = $row.data('template-id');
			var frequency = $row.data('frequency');
			var topic = $row.data('topic');
			var articleStructureId = $row.data('article-structure-id');
			var rotationPattern = $row.data('rotation-pattern');

			// Populate form
			$('#schedule_template').val(templateId);
			$('#schedule_frequency').val(frequency);
			$('#schedule_topic').val(topic);
			$('#article_structure_id').val(articleStructureId);
			$('#rotation_pattern').val(rotationPattern);

			// Clear start time to enforce "now" or user choice for new schedule
			$('#schedule_start_time').val('');

			// Update title and show
			$('#aips-schedule-modal-title').text('Clone Schedule');
			$('#aips-schedule-modal').show();
		},

		/**
		 * Save a schedule (create or update).
		 * 
		 * Validates the form and submits via AJAX.
		 * Reloads the page on success to reflect changes.
		 * 
		 * @param {Event} e - The click event.
		 */
		saveSchedule: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $form = $('#aips-schedule-form');

			if (!$form[0].checkValidity()) {
				$form[0].reportValidity();
				return;
			}

			$btn.prop('disabled', true).text('Saving...');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_save_schedule',
					nonce: aipsAjax.nonce,
					schedule_id: $('#schedule_id').val(),
					template_id: $('#schedule_template').val(),
					frequency: $('#schedule_frequency').val(),
					start_time: $('#schedule_start_time').val(),
					is_active: $('#schedule_is_active').is(':checked') ? 1 : 0
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Save Schedule');
				}
			});
		},

		/**
		 * Delete a schedule.
		 * 
		 * Prompts for confirmation and deletes the schedule via AJAX.
		 * 
		 * @param {Event} e - The click event.
		 */
		deleteSchedule: function(e) {
			e.preventDefault();
			if (!confirm('Are you sure you want to delete this schedule?')) {
				return;
			}

			var id = $(this).data('id');
			var $row = $(this).closest('tr');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_delete_schedule',
					nonce: aipsAjax.nonce,
					schedule_id: id
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				}
			});
		},

		/**
		 * Toggle schedule active status.
		 * 
		 * Immediately updates the schedule's active status via AJAX
		 * when the toggle switch is changed.
		 */
		toggleSchedule: function() {
			var id = $(this).data('id');
			var isActive = $(this).is(':checked') ? 1 : 0;

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_toggle_schedule',
					nonce: aipsAjax.nonce,
					schedule_id: id,
					is_active: isActive
				},
				error: function() {
					alert('An error occurred. Please try again.');
				}
			});
		},

		/**
		 * Filter schedules by search term.
		 * 
		 * Filters the schedules table based on template, structure, and frequency.
		 * Shows/hides table rows and displays a "no results" message if needed.
		 */
		filterSchedules: function() {
			var term = $('#aips-schedule-search').val().toLowerCase().trim();
			var $rows = $('.aips-schedules-container table tbody tr');
			var $noResults = $('#aips-schedule-search-no-results');
			var $table = $('.aips-schedules-container table');
			var $clearBtn = $('#aips-schedule-search-clear');
			var hasVisible = false;

			if (term.length > 0) {
				$clearBtn.show();
			} else {
				$clearBtn.hide();
			}

			$rows.each(function() {
				var $row = $(this);
				var template = $row.find('.column-template').text().toLowerCase();
				var structure = $row.find('.column-structure').text().toLowerCase();
				var frequency = $row.find('.column-frequency').text().toLowerCase();

				if (template.indexOf(term) > -1 || structure.indexOf(term) > -1 || frequency.indexOf(term) > -1) {
					$row.show();
					hasVisible = true;
				} else {
					$row.hide();
				}
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
		 * Clear the schedule search field.
		 * 
		 * Resets the search input and triggers the filter to show all schedules.
		 * 
		 * @param {Event} e - The click event.
		 */
		clearScheduleSearch: function(e) {
			e.preventDefault();
			$('#aips-schedule-search').val('').trigger('keyup');
		}
	};

	// Initialize Schedules component when DOM is ready
	$(document).ready(function() {
		AIPS.Schedules.init();
	});

})(jQuery);
