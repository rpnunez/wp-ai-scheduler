/**
 * AIPS Schedules
 *
 * Schedule CRUD operations and search/filtering.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};

    Object.assign(AIPS, {

        openScheduleModal: function(e) {
            e.preventDefault();
            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val('');
            $('#aips-schedule-modal-title').text('Add New Schedule');
            $('#aips-schedule-modal').show();
        },

        cloneSchedule: function(e) {
            e.preventDefault();

            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val('');

            var $row = $(this).closest('tr');
            var templateId = $row.data('template-id');
            var frequency = $row.data('frequency');
            var topic = $row.data('topic');
            var articleStructureId = $row.data('article-structure-id');
            var rotationPattern = $row.data('rotation-pattern');

            $('#schedule_template').val(templateId);
            $('#schedule_frequency').val(frequency);
            $('#schedule_topic').val(topic);
            $('#article_structure_id').val(articleStructureId);
            $('#rotation_pattern').val(rotationPattern);
            $('#schedule_start_time').val('');

            $('#aips-schedule-modal-title').text('Clone Schedule');
            $('#aips-schedule-modal').show();
        },

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
                        $row.fadeOut(function() { $(this).remove(); });
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        },

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

        filterSchedules: function() {
            var term = $('#aips-schedule-search').val().toLowerCase().trim();
            var $rows = $('.aips-schedules-container table tbody tr');
            var $noResults = $('#aips-schedule-search-no-results');
            var $table = $('.aips-schedules-container table');
            var $clearBtn = $('#aips-schedule-search-clear');
            var hasVisible = false;

            $clearBtn.toggle(term.length > 0);

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

        clearScheduleSearch: function(e) {
            e.preventDefault();
            $('#aips-schedule-search').val('').trigger('keyup');
        }
    });

    // Bind schedule events
    $(document).ready(function() {
        $(document).on('click', '.aips-add-schedule-btn', AIPS.openScheduleModal);
        $(document).on('click', '.aips-clone-schedule', AIPS.cloneSchedule);
        $(document).on('click', '.aips-save-schedule', AIPS.saveSchedule);
        $(document).on('click', '.aips-delete-schedule', AIPS.deleteSchedule);
        $(document).on('change', '.aips-toggle-schedule', AIPS.toggleSchedule);

        $(document).on('keyup search', '#aips-schedule-search', AIPS.filterSchedules);
        $(document).on('click', '#aips-schedule-search-clear', AIPS.clearScheduleSearch);
        $(document).on('click', '.aips-clear-schedule-search-btn', AIPS.clearScheduleSearch);
    });

})(jQuery);
