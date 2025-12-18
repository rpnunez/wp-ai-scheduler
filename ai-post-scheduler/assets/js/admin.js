(function($) {
    'use strict';

    var AIPS = {
        // Debounce utility function for performance optimization
        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        },

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
            $(document).on('change', '#generate_featured_image', this.toggleImagePrompt);
            
            // Use debounced search for better performance (300ms delay)
            $(document).on('keyup', '#voice_search', this.debounce(this.searchVoices, 300));

            $(document).on('click', '.aips-add-voice-btn', this.openVoiceModal);
            $(document).on('click', '.aips-edit-voice', this.editVoice);
            $(document).on('click', '.aips-delete-voice', this.deleteVoice);
            $(document).on('click', '.aips-save-voice', this.saveVoice);

            $(document).on('click', '.aips-add-schedule-btn', this.openScheduleModal);
            $(document).on('click', '.aips-save-schedule', this.saveSchedule);
            $(document).on('click', '.aips-delete-schedule', this.deleteSchedule);
            $(document).on('change', '.aips-toggle-schedule', this.toggleSchedule);

            $(document).on('click', '.aips-clear-history', this.clearHistory);
            $(document).on('click', '.aips-retry-generation', this.retryGeneration);
            $(document).on('click', '#aips-filter-btn', this.filterHistory);
            $(document).on('click', '.aips-view-details', this.viewDetails);

            // Template Search - use debounced search for better performance (300ms delay)
            $(document).on('keyup search', '#aips-template-search', this.debounce(this.filterTemplates, 300));
            $(document).on('click', '#aips-template-search-clear', this.clearTemplateSearch);
            $(document).on('click', '.aips-clear-search-btn', this.clearTemplateSearch);

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

            // Planner events
            $(document).on('click', '.nav-tab', this.switchTab);
            $(document).on('click', '#btn-generate-topics', this.generateTopics);
            $(document).on('click', '#btn-parse-manual', this.parseManualTopics);
            $(document).on('click', '#btn-bulk-schedule', this.bulkSchedule);
            $(document).on('change', '#check-all-topics', this.toggleAllTopics);
            $(document).on('change', '.topic-checkbox', this.updateSelectionCount);
        },

        switchTab: function(e) {
            e.preventDefault();
            var tabId = $(this).data('tab');

            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.aips-tab-content').hide();
            $('#' + tabId + '-tab').show();
        },

        generateTopics: function(e) {
            e.preventDefault();
            var niche = $('#planner-niche').val();
            var count = $('#planner-count').val();

            if (!niche) {
                alert('Please enter a niche or topic.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);
            $btn.next('.spinner').addClass('is-active');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_generate_topics',
                    nonce: aipsAjax.nonce,
                    niche: niche,
                    count: count
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.renderTopics(response.data.topics);
                        $('#planner-results').slideDown();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.next('.spinner').removeClass('is-active');
                }
            });
        },

        parseManualTopics: function(e) {
            e.preventDefault();
            var text = $('#planner-manual-topics').val();
            if (!text) return;

            var topics = text.split('\n').map(function(t) { return t.trim(); }).filter(function(t) { return t.length > 0; });

            if (topics.length > 0) {
                AIPS.renderTopics(topics, true); // true = append
                $('#planner-results').slideDown();
                $('#planner-manual-topics').val('');
            }
        },

        renderTopics: function(topics, append) {
            var html = '';
            topics.forEach(function(topic) {
                // Escape HTML
                var div = document.createElement('div');
                div.textContent = topic;
                var safeTopic = div.innerHTML;

                html += '<div class="topic-item">';
                html += '<label>';
                html += '<input type="checkbox" class="topic-checkbox" value="' + safeTopic + '" checked>';
                html += '<span>' + safeTopic + '</span>';
                html += '</label>';
                html += '</div>';
            });

            if (append) {
                $('#topics-list').append(html);
            } else {
                $('#topics-list').html(html);
            }

            AIPS.updateSelectionCount();
        },

        toggleAllTopics: function() {
            var isChecked = $(this).is(':checked');
            $('.topic-checkbox').prop('checked', isChecked);
            AIPS.updateSelectionCount();
        },

        updateSelectionCount: function() {
            var count = $('.topic-checkbox:checked').length;
            $('.selection-count').text(count + ' selected');
        },

        bulkSchedule: function(e) {
            e.preventDefault();
            var topics = [];
            $('.topic-checkbox:checked').each(function() {
                topics.push($(this).val());
            });

            if (topics.length === 0) {
                alert('Please select at least one topic.');
                return;
            }

            var templateId = $('#bulk-template').val();
            var startDate = $('#bulk-start-date').val();

            if (!templateId) {
                alert('Please select a template.');
                return;
            }
            if (!startDate) {
                alert('Please select a start date.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);
            $btn.next('.spinner').addClass('is-active');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_schedule',
                    nonce: aipsAjax.nonce,
                    topics: topics,
                    template_id: templateId,
                    start_date: startDate,
                    frequency: $('#bulk-frequency').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        // Clear selection or redirect?
                        // For now just uncheck scheduled ones or clear list
                         $('#topics-list').html('');
                         $('#planner-results').slideUp();
                         $('#planner-niche').val('');
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.next('.spinner').removeClass('is-active');
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
                        $('#post_quantity').val(t.post_quantity || 1);
                        $('#generate_featured_image').prop('checked', t.generate_featured_image == 1);
                        $('#image_prompt').val(t.image_prompt || '').prop('disabled', t.generate_featured_image != 1);
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
                    voice_id: $('#voice_id').val(),
                    post_quantity: $('#post_quantity').val(),
                    generate_featured_image: $('#generate_featured_image').is(':checked') ? 1 : 0,
                    image_prompt: $('#image_prompt').val(),
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
                        location.reload();
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

        searchVoices: function() {
            var search = $(this).val();
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_search_voices',
                    nonce: aipsAjax.nonce,
                    search: search
                },
                success: function(response) {
                    if (response.success) {
                        var $select = $('#voice_id');
                        var currentVal = $select.val();
                        $select.html('<option value="0">' + 'No Voice (Use Default)' + '</option>');
                        $.each(response.data.voices, function(i, voice) {
                            $select.append('<option value="' + voice.id + '">' + voice.name + '</option>');
                        });
                        $select.val(currentVal);
                    }
                }
            });
        },

        openVoiceModal: function(e) {
            e.preventDefault();
            $('#aips-voice-form')[0].reset();
            $('#voice_id').val('');
            $('#aips-voice-modal-title').text('Add New Voice');
            $('#aips-voice-modal').show();
        },

        editVoice: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_voice',
                    nonce: aipsAjax.nonce,
                    voice_id: id
                },
                success: function(response) {
                    if (response.success) {
                        var v = response.data.voice;
                        $('#voice_id').val(v.id);
                        $('#voice_name').val(v.name);
                        $('#voice_title_prompt').val(v.title_prompt);
                        $('#voice_content_instructions').val(v.content_instructions);
                        $('#voice_excerpt_instructions').val(v.excerpt_instructions || '');
                        $('#voice_is_active').prop('checked', v.is_active == 1);
                        $('#aips-voice-modal-title').text('Edit Voice');
                        $('#aips-voice-modal').show();
                    }
                }
            });
        },

        deleteVoice: function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this voice?')) {
                return;
            }
            var id = $(this).data('id');
            var $row = $(this).closest('tr');
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_delete_voice',
                    nonce: aipsAjax.nonce,
                    voice_id: id
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() { $(this).remove(); });
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        },

        saveVoice: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $form = $('#aips-voice-form');
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }
            $btn.prop('disabled', true).text('Saving...');
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_save_voice',
                    nonce: aipsAjax.nonce,
                    voice_id: $('#voice_id').val(),
                    name: $('#voice_name').val(),
                    title_prompt: $('#voice_title_prompt').val(),
                    content_instructions: $('#voice_content_instructions').val(),
                    excerpt_instructions: $('#voice_excerpt_instructions').val(),
                    is_active: $('#voice_is_active').is(':checked') ? 1 : 0
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
                    $btn.prop('disabled', false).text('Save Voice');
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

        toggleImagePrompt: function(e) {
            var isChecked = $(this).is(':checked');
            $('#image_prompt').prop('disabled', !isChecked);
        },

        filterTemplates: function() {
            var term = $('#aips-template-search').val().toLowerCase().trim();
            var $rows = $('.aips-templates-list tbody tr');
            var $noResults = $('#aips-template-search-no-results');
            var $table = $('.aips-templates-list table');
            var $clearBtn = $('#aips-template-search-clear');
            var hasVisible = false;

            if (term.length > 0) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }

            $rows.each(function() {
                var $row = $(this);
                var name = $row.find('.column-name').text().toLowerCase();
                var category = $row.find('.column-category').text().toLowerCase();

                if (name.indexOf(term) > -1 || category.indexOf(term) > -1) {
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

        clearTemplateSearch: function(e) {
            e.preventDefault();
            $('#aips-template-search').val('').trigger('keyup');
        },

        viewDetails: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $btn = $(this);
            
            $btn.prop('disabled', true);
            $('#aips-details-loading').show();
            $('#aips-details-content').hide();
            $('#aips-details-modal').show();

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_history_details',
                    nonce: aipsAjax.nonce,
                    history_id: id
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.renderDetails(response.data);
                    } else {
                        alert(response.data.message);
                        $('#aips-details-modal').hide();
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $('#aips-details-modal').hide();
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $('#aips-details-loading').hide();
                }
            });
        },

        renderDetails: function(data) {
            var log = data.generation_log || {};
            
            var summaryHtml = '<table class="aips-details-table">';
            summaryHtml += '<tr><th>Status:</th><td><span class="aips-status aips-status-' + data.status + '">' + data.status.charAt(0).toUpperCase() + data.status.slice(1) + '</span></td></tr>';
            summaryHtml += '<tr><th>Title:</th><td>' + (data.generated_title || '-') + '</td></tr>';
            if (data.post_id) {
                summaryHtml += '<tr><th>Post ID:</th><td>' + data.post_id + '</td></tr>';
            }
            summaryHtml += '<tr><th>Started:</th><td>' + (log.started_at || data.created_at) + '</td></tr>';
            summaryHtml += '<tr><th>Completed:</th><td>' + (log.completed_at || data.completed_at || '-') + '</td></tr>';
            if (data.error_message) {
                summaryHtml += '<tr><th>Error:</th><td class="aips-error-text">' + data.error_message + '</td></tr>';
            }
            summaryHtml += '</table>';
            $('#aips-details-summary').html(summaryHtml);
            
            if (log.template) {
                var templateHtml = '<table class="aips-details-table">';
                templateHtml += '<tr><th>Name:</th><td>' + (log.template.name || '-') + '</td></tr>';
                templateHtml += '<tr><th>Prompt Template:</th><td><pre class="aips-prompt-text">' + AIPS.escapeHtml(log.template.prompt_template || '') + '</pre></td></tr>';
                if (log.template.title_prompt) {
                    templateHtml += '<tr><th>Title Prompt:</th><td><pre class="aips-prompt-text">' + AIPS.escapeHtml(log.template.title_prompt) + '</pre></td></tr>';
                }
                templateHtml += '<tr><th>Post Status:</th><td>' + (log.template.post_status || 'draft') + '</td></tr>';
                templateHtml += '<tr><th>Post Quantity:</th><td>' + (log.template.post_quantity || 1) + '</td></tr>';
                if (log.template.generate_featured_image) {
                    templateHtml += '<tr><th>Image Prompt:</th><td><pre class="aips-prompt-text">' + AIPS.escapeHtml(log.template.image_prompt || '') + '</pre></td></tr>';
                }
                templateHtml += '</table>';
                $('#aips-details-template').html(templateHtml);
            } else {
                $('#aips-details-template').html('<p>No template data available.</p>');
            }
            
            if (log.voice) {
                var voiceHtml = '<table class="aips-details-table">';
                voiceHtml += '<tr><th>Name:</th><td>' + (log.voice.name || '-') + '</td></tr>';
                voiceHtml += '<tr><th>Title Prompt:</th><td><pre class="aips-prompt-text">' + AIPS.escapeHtml(log.voice.title_prompt || '') + '</pre></td></tr>';
                voiceHtml += '<tr><th>Content Instructions:</th><td><pre class="aips-prompt-text">' + AIPS.escapeHtml(log.voice.content_instructions || '') + '</pre></td></tr>';
                if (log.voice.excerpt_instructions) {
                    voiceHtml += '<tr><th>Excerpt Instructions:</th><td><pre class="aips-prompt-text">' + AIPS.escapeHtml(log.voice.excerpt_instructions) + '</pre></td></tr>';
                }
                voiceHtml += '</table>';
                $('#aips-details-voice').html(voiceHtml);
                $('#aips-details-voice-section').show();
            } else {
                $('#aips-details-voice-section').hide();
            }
            
            if (log.ai_calls && log.ai_calls.length > 0) {
                var callsHtml = '';
                log.ai_calls.forEach(function(call, index) {
                    var statusClass = call.response.success ? 'aips-call-success' : 'aips-call-error';
                    callsHtml += '<div class="aips-ai-call ' + statusClass + '">';
                    callsHtml += '<div class="aips-call-header">';
                    callsHtml += '<strong>Call #' + (index + 1) + ' - ' + call.type.charAt(0).toUpperCase() + call.type.slice(1) + '</strong>';
                    callsHtml += '<span class="aips-call-time">' + call.timestamp + '</span>';
                    callsHtml += '</div>';
                    callsHtml += '<div class="aips-call-section">';
                    callsHtml += '<h4>Request</h4>';
                    callsHtml += '<pre class="aips-prompt-text">' + AIPS.escapeHtml(call.request.prompt || '') + '</pre>';
                    if (call.request.options && Object.keys(call.request.options).length > 0) {
                        callsHtml += '<p><small>Options: ' + JSON.stringify(call.request.options) + '</small></p>';
                    }
                    callsHtml += '</div>';
                    callsHtml += '<div class="aips-call-section">';
                    callsHtml += '<h4>Response</h4>';
                    if (call.response.success) {
                        callsHtml += '<pre class="aips-response-text">' + AIPS.escapeHtml(call.response.content || '') + '</pre>';
                    } else {
                        callsHtml += '<p class="aips-error-text">Error: ' + AIPS.escapeHtml(call.response.error || 'Unknown error') + '</p>';
                    }
                    callsHtml += '</div>';
                    callsHtml += '</div>';
                });
                $('#aips-details-ai-calls').html(callsHtml);
            } else {
                $('#aips-details-ai-calls').html('<p>No AI call data available for this entry.</p>');
            }
            
            if (log.errors && log.errors.length > 0) {
                var errorsHtml = '<ul class="aips-errors-list">';
                log.errors.forEach(function(error) {
                    errorsHtml += '<li>';
                    errorsHtml += '<strong>' + error.type + '</strong> at ' + error.timestamp + '<br>';
                    errorsHtml += '<span class="aips-error-text">' + AIPS.escapeHtml(error.message) + '</span>';
                    errorsHtml += '</li>';
                });
                errorsHtml += '</ul>';
                $('#aips-details-errors').html(errorsHtml);
                $('#aips-details-errors-section').show();
            } else {
                $('#aips-details-errors-section').hide();
            }
            
            $('#aips-details-content').show();
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        closeModal: function() {
            var $target = $(this).closest('.aips-modal');
            if ($target.length) {
                $target.hide();
            } else {
                $('.aips-modal').hide();
            }
        }
    };

    $(document).ready(function() {
        AIPS.init();
        // Load voices on template page load
        if ($('#voice_search').length) {
            AIPS.searchVoices.call($('#voice_search'));
        }
    });

})(jQuery);
