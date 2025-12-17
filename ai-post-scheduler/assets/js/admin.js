(function($) {
    'use strict';

    var AIPS = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.aips-add-template-btn', this.openTemplateModal);
            $(document).on('click', '.aips-edit-template', this.editTemplate);
            $(document).on('click', '.aips-delete-template', this.deleteTemplate);
            $(document).on('click', '.aips-save-template', this.saveTemplate);
            $(document).on('click', '.aips-test-template', this.testTemplate);
            $(document).on('click', '.aips-run-now', this.runNow);

            $(document).on('click', '.aips-add-schedule-btn', this.openScheduleModal);
            $(document).on('click', '.aips-save-schedule', this.saveSchedule);
            $(document).on('click', '.aips-delete-schedule', this.deleteSchedule);
            $(document).on('change', '.aips-toggle-schedule', this.toggleSchedule);

            $(document).on('click', '.aips-clear-history', this.clearHistory);
            $(document).on('click', '.aips-retry-generation', this.retryGeneration);
            $(document).on('click', '#aips-filter-btn', this.filterHistory);

            $(document).on('click', '.aips-modal-close', this.closeModal);
            $(document).on('click', '.aips-modal', function(e) {
                if ($(e.target).hasClass('aips-modal')) {
                    AIPS.closeModal();
                }
            });

            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    AIPS.closeModal();
                }
            });
        },

        openTemplateModal: function(e) {
            e.preventDefault();
            $('#aips-template-form')[0].reset();
            $('#template_id').val('');
            $('#aips-modal-title').text('Add New Template');
            $('#aips-template-modal').show();
        },

        editTemplate: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $btn = $(this);
            
            $btn.prop('disabled', true);

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_template',
                    nonce: aipsAjax.nonce,
                    template_id: id
                },
                success: function(response) {
                    if (response.success) {
                        var t = response.data.template;
                        $('#template_id').val(t.id);
                        $('#template_name').val(t.name);
                        $('#prompt_template').val(t.prompt_template);
                        $('#title_prompt').val(t.title_prompt);
                        $('#post_status').val(t.post_status);
                        $('#post_category').val(t.post_category);
                        $('#post_tags').val(t.post_tags);
                        $('#post_author').val(t.post_author);
                        $('#is_active').prop('checked', t.is_active == 1);
                        $('#aips-modal-title').text('Edit Template');
                        $('#aips-template-modal').show();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        deleteTemplate: function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this template?')) {
                return;
            }

            var id = $(this).data('id');
            var $row = $(this).closest('tr');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_delete_template',
                    nonce: aipsAjax.nonce,
                    template_id: id
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

        saveTemplate: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $form = $('#aips-template-form');

            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_save_template',
                    nonce: aipsAjax.nonce,
                    template_id: $('#template_id').val(),
                    name: $('#template_name').val(),
                    prompt_template: $('#prompt_template').val(),
                    title_prompt: $('#title_prompt').val(),
                    post_status: $('#post_status').val(),
                    post_category: $('#post_category').val(),
                    post_tags: $('#post_tags').val(),
                    post_author: $('#post_author').val(),
                    is_active: $('#is_active').is(':checked') ? 1 : 0
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
                    $btn.prop('disabled', false).text('Save Template');
                }
            });
        },

        testTemplate: function(e) {
            e.preventDefault();
            var prompt = $('#prompt_template').val();
            
            if (!prompt) {
                alert('Please enter a prompt template first.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Generating...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_test_template',
                    nonce: aipsAjax.nonce,
                    prompt_template: prompt
                },
                success: function(response) {
                    if (response.success) {
                        $('#aips-test-content').text(response.data.content);
                        $('#aips-test-result-modal').show();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Test Generate');
                }
            });
        },

        runNow: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $btn = $(this);

            $btn.prop('disabled', true).text('Generating...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_run_now',
                    nonce: aipsAjax.nonce,
                    template_id: id
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        if (response.data.edit_url) {
                            window.open(response.data.edit_url, '_blank');
                        }
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Run Now');
                }
            });
        },

        openScheduleModal: function(e) {
            e.preventDefault();
            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val('');
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

        clearHistory: function(e) {
            e.preventDefault();
            var status = $(this).data('status');
            var message = status ? 'Are you sure you want to clear all ' + status + ' history?' : 'Are you sure you want to clear all history?';
            
            if (!confirm(message)) {
                return;
            }

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_clear_history',
                    nonce: aipsAjax.nonce,
                    status: status
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
                }
            });
        },

        retryGeneration: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $btn = $(this);

            $btn.prop('disabled', true).text('Retrying...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_retry_generation',
                    nonce: aipsAjax.nonce,
                    history_id: id
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Retry');
                }
            });
        },

        filterHistory: function(e) {
            e.preventDefault();
            var status = $('#aips-filter-status').val();
            var url = new URL(window.location.href);
            
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            url.searchParams.delete('paged');
            
            window.location.href = url.toString();
        },

        closeModal: function() {
            $('.aips-modal').hide();
        }
    };

    $(document).ready(function() {
        AIPS.init();
    });

})(jQuery);
