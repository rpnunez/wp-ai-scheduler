(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    Object.assign(AIPS, {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Voices Logic (Legacy, to be extracted)
            $(document).on('keyup', '#voice_search', this.searchVoices);
            $(document).on('click', '.aips-add-voice-btn', this.openVoiceModal);
            $(document).on('click', '.aips-edit-voice', this.editVoice);
            $(document).on('click', '.aips-delete-voice', this.deleteVoice);
            $(document).on('click', '.aips-save-voice', this.saveVoice);

            // History Logic (Legacy, to be extracted)
            $(document).on('click', '.aips-clear-history', this.clearHistory);
            $(document).on('click', '.aips-retry-generation', this.retryGeneration);
            $(document).on('click', '#aips-filter-btn', this.filterHistory);
            $(document).on('click', '#aips-history-search-btn', this.filterHistory);
            $(document).on('keypress', '#aips-history-search-input', function(e) {
                if(e.which == 13) {
                    AIPS.filterHistory(e);
                }
            });
            $(document).on('click', '.aips-view-details', this.viewDetails);

            // History Bulk Actions
            $(document).on('change', '#cb-select-all-1', this.toggleAllHistory);
            $(document).on('change', '.aips-history-table input[name="history[]"]', this.toggleHistorySelection);
            $(document).on('click', '#aips-delete-selected-btn', this.deleteSelectedHistory);

            // Voice Search
            $(document).on('keyup search', '#aips-voice-search', this.filterVoices);
            $(document).on('click', '#aips-voice-search-clear', this.clearVoiceSearch);
            $(document).on('click', '.aips-clear-voice-search-btn', this.clearVoiceSearch);

            // Common Modal Logic
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

            // Settings
            $(document).on('click', '#aips-test-connection', this.testConnection);

            // Tabs
            $(document).on('click', '.nav-tab', this.switchTab);

            // Copy to Clipboard
            $(document).on('click', '.aips-copy-btn', this.copyToClipboard);

            // Article Structures UI handlers

            // @TODO: Refactor to use AIPS.addStructure
            $(document).on('click', '.aips-add-structure-btn', function(e){
                e.preventDefault();
                $('#aips-structure-form')[0].reset();
                $('#structure_id').val('');
                $('#aips-structure-modal-title').text('Add New Article Structure');
                $('#aips-structure-modal').show();
            });

            // @TODO: Refactor to AIPS.closeModal -- or use existing function
            $(document).on('click', '.aips-modal-close', function(){
                $(this).closest('.aips-modal').hide();
            });

            // @TODO: Refactor to AIPS.saveStructure
            $(document).on('click', '.aips-save-structure', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text('Saving...');

                var data = {
                    action: 'aips_save_structure',
                    nonce: aipsAjax.nonce,
                    structure_id: $('#structure_id').val(),
                    name: $('#structure_name').val(),
                    description: $('#structure_description').val(),
                    prompt_template: $('#prompt_template').val(),
                    sections: $('#structure_sections').val() || [],
                    is_active: $('#structure_is_active').is(':checked') ? 1 : 0,
                    is_default: $('#structure_is_default').is(':checked') ? 1 : 0,
                };

                $.post(aipsAjax.ajaxUrl, data, function(response){
                    $btn.prop('disabled', false).text('Save Structure');
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || aipsAdminL10n.saveStructureFailed);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text('Save Structure');
                    alert(aipsAdminL10n.errorTryAgain);
                });
            });

            // @TODO: Refactor to AIPS.saveStructure
            $(document).on('click', '.aips-edit-structure', function(){
                var id = $(this).data('id');
                $.post(aipsAjax.ajaxUrl, {action: 'aips_get_structure', nonce: aipsAjax.nonce, structure_id: id}, function(response){
                    if (response.success) {
                        var s = response.data.structure;
                        var structureData = {};

                        if (s.structure_data) {
                            try {
                                structureData = JSON.parse(s.structure_data) || {};
                            } catch (e) {
                                console.error('Invalid structure_data JSON for structure ID ' + s.id, e);
                                structureData = {};
                            }
                        }

                        $('#structure_id').val(s.id);
                        $('#structure_name').val(s.name);
                        $('#structure_description').val(s.description);
                        $('#prompt_template').val(structureData.prompt_template || '');
                        var sections = structureData.sections || [];
                        $('#structure_sections').val(sections);
                        $('#structure_is_active').prop('checked', s.is_active == 1);
                        $('#structure_is_default').prop('checked', s.is_default == 1);
                        $('#aips-structure-modal-title').text('Edit Article Structure');
                        $('#aips-structure-modal').show();
                    } else {
                        alert(response.data.message || aipsAdminL10n.loadStructureFailed);
                    }
                }).fail(function(){
                    alert(aipsAdminL10n.errorOccurred);
                });
            });

            // @TODO: Refactor to AIPS.deleteStructure
            $(document).on('click', '.aips-delete-structure', function(){
                if (!confirm(aipsAdminL10n.deleteStructureConfirm)) return;
                var id = $(this).data('id');
                var $row = $(this).closest('tr');
                $.post(aipsAjax.ajaxUrl, {action: 'aips_delete_structure', nonce: aipsAjax.nonce, structure_id: id}, function(response){
                    if (response.success) {
                        $row.fadeOut(function(){ $(this).remove(); });
                    } else {
                        alert(response.data.message || aipsAdminL10n.deleteStructureFailed);
                    }
                }).fail(function(){ alert(aipsAdminL10n.errorOccurred); });
            });

            // Prompt Sections UI handlers
            $(document).on('click', '.aips-add-section-btn', function(e){
                e.preventDefault();
                $('#aips-section-form')[0].reset();
                $('#section_id').val('');
                $('#aips-section-modal-title').text('Add New Prompt Section');
                $('#aips-section-modal').show();
            });

            $(document).on('click', '.aips-save-section', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text('Saving...');

                var data = {
                    action: 'aips_save_prompt_section',
                    nonce: aipsAjax.nonce,
                    section_id: $('#section_id').val(),
                    name: $('#section_name').val(),
                    section_key: $('#section_key').val(),
                    description: $('#section_description').val(),
                    content: $('#section_content').val(),
                    is_active: $('#section_is_active').is(':checked') ? 1 : 0
                };

                $.post(aipsAjax.ajaxUrl, data, function(response){
                    $btn.prop('disabled', false).text('Save Section');
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || aipsAdminL10n.saveSectionFailed);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text('Save Section');
                    alert(aipsAdminL10n.errorTryAgain);
                });
            });

            $(document).on('click', '.aips-edit-section', function(){
                var id = $(this).data('id');
                $.post(aipsAjax.ajaxUrl, {action: 'aips_get_prompt_section', nonce: aipsAjax.nonce, section_id: id}, function(response){
                    if (response.success) {
                        var s = response.data.section;
                        $('#section_id').val(s.id);
                        $('#section_name').val(s.name);
                        $('#section_key').val(s.section_key);
                        $('#section_description').val(s.description);
                        $('#section_content').val(s.content);
                        $('#section_is_active').prop('checked', s.is_active == 1);
                        $('#aips-section-modal-title').text('Edit Prompt Section');
                        $('#aips-section-modal').show();
                    } else {
                        alert(response.data.message || aipsAdminL10n.loadSectionFailed);
                    }
                }).fail(function(){
                    alert(aipsAdminL10n.errorOccurred);
                });
            });

            $(document).on('click', '.aips-delete-section', function(){
                if (!confirm(aipsAdminL10n.deleteSectionConfirm)) return;
                var id = $(this).data('id');
                var $row = $(this).closest('tr');
                $.post(aipsAjax.ajaxUrl, {action: 'aips_delete_prompt_section', nonce: aipsAjax.nonce, section_id: id}, function(response){
                    if (response.success) {
                        $row.fadeOut(function(){ $(this).remove(); });
                    } else {
                        alert(response.data.message || aipsAdminL10n.deleteSectionFailed);
                    }
                }).fail(function(){ alert(aipsAdminL10n.errorOccurred); });
            });
        },

        copyToClipboard: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var text = $btn.data('clipboard-text');
            var originalIcon = $btn.data('original-icon') || 'dashicons-admin-page';
            var originalText = $btn.text();

            if (!text) return;

            // Fallback for older browsers
            if (!navigator.clipboard) {
                var textArea = document.createElement("textarea");
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    $btn.text('Copied!');
                    setTimeout(function() {
                        $btn.text(originalText);
                    }, 2000);
                } catch (err) {
                    console.error('Fallback: Oops, unable to copy', err);
                }
                document.body.removeChild(textArea);
                return;
            }

            navigator.clipboard.writeText(text).then(function() {
                $btn.text('Copied!');
                setTimeout(function() {
                    $btn.text(originalText);
                }, 2000);
            }, function(err) {
                console.error('Async: Could not copy text: ', err);
            });
        },

        testConnection: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $spinner = $btn.next('.spinner');
            var $result = $spinner.next('#aips-connection-result');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.removeClass('aips-status-ok aips-status-error').text('');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_test_connection',
                    nonce: aipsAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('aips-status-ok').html('<span class="dashicons dashicons-yes"></span> ' + response.data.message);
                    } else {
                        $result.addClass('aips-status-error').html('<span class="dashicons dashicons-warning"></span> ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('aips-status-error').text('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        switchTab: function(e) {
            e.preventDefault();
            var tabId = $(this).data('tab');

            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.aips-tab-content').hide();
            $('#' + tabId + '-tab').show();
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
            var search = $('#aips-history-search-input').val();
            var url = new URL(window.location.href);
            
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }

            if (search) {
                url.searchParams.set('s', search);
            } else {
                url.searchParams.delete('s');
            }

            url.searchParams.delete('paged');
            
            window.location.href = url.toString();
        },

        filterVoices: function() {
            var term = $('#aips-voice-search').val().toLowerCase().trim();
            var $rows = $('.aips-voices-list tbody tr');
            var $noResults = $('#aips-voice-search-no-results');
            var $table = $('.aips-voices-list');
            var $clearBtn = $('#aips-voice-search-clear');
            var hasVisible = false;

            if (term.length > 0) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }

            $rows.each(function() {
                var $row = $(this);
                var name = $row.find('.column-name').text().toLowerCase();

                if (name.indexOf(term) > -1) {
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

        clearVoiceSearch: function(e) {
            e.preventDefault();
            $('#aips-voice-search').val('').trigger('keyup');
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
                templateHtml += '<tr><th>Prompt Template:</th><td>';
                templateHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.escapeAttribute(log.template.prompt_template || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                templateHtml += '<pre class="aips-prompt-text">' + AIPS.escapeHtml(log.template.prompt_template || '') + '</pre></td></tr>';
                if (log.template.title_prompt) {
                    templateHtml += '<tr><th>Title Prompt:</th><td>';
                    templateHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.escapeAttribute(log.template.title_prompt) + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                    templateHtml += '<pre class="aips-prompt-text">' + AIPS.escapeHtml(log.template.title_prompt) + '</pre></td></tr>';
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
                voiceHtml += '<tr><th>Title Prompt:</th><td>';
                voiceHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.escapeAttribute(log.voice.title_prompt || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                voiceHtml += '<pre class="aips-prompt-text">' + AIPS.escapeHtml(log.voice.title_prompt || '') + '</pre></td></tr>';
                voiceHtml += '<tr><th>Content Instructions:</th><td>';
                voiceHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.escapeAttribute(log.voice.content_instructions || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                voiceHtml += '<pre class="aips-prompt-text">' + AIPS.escapeHtml(log.voice.content_instructions || '') + '</pre></td></tr>';
                if (log.voice.excerpt_instructions) {
                    voiceHtml += '<tr><th>Excerpt Instructions:</th><td>';
                    voiceHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.escapeAttribute(log.voice.excerpt_instructions) + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                    voiceHtml += '<pre class="aips-prompt-text">' + AIPS.escapeHtml(log.voice.excerpt_instructions) + '</pre></td></tr>';
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
                    callsHtml += '<div class="aips-call-section-header">';
                    callsHtml += '<h4>Request</h4>';
                    callsHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.escapeAttribute(call.request.prompt || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                    callsHtml += '</div>';
                    callsHtml += '<pre class="aips-prompt-text">' + AIPS.escapeHtml(call.request.prompt || '') + '</pre>';
                    if (call.request.options && Object.keys(call.request.options).length > 0) {
                        callsHtml += '<p><small>Options: ' + JSON.stringify(call.request.options) + '</small></p>';
                    }
                    callsHtml += '</div>';
                    callsHtml += '<div class="aips-call-section">';
                    callsHtml += '<div class="aips-call-section-header">';
                    callsHtml += '<h4>Response</h4>';
                    if (call.response.success) {
                        callsHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.escapeAttribute(call.response.content || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                    }
                    callsHtml += '</div>';
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

        escapeAttribute: function(text) {
            if (!text) return '';
            return text.replace(/"/g, '&quot;');
        },

        closeModal: function() {
            var $target = $(this).closest('.aips-modal');
            if ($target.length) {
                $target.hide();
            } else {
                $('.aips-modal').hide();
            }
        },

        toggleAllHistory: function() {
            var isChecked = $(this).prop('checked');
            $('.aips-history-table input[name="history[]"]').prop('checked', isChecked);
            AIPS.updateDeleteButton();
        },

        toggleHistorySelection: function() {
            var allChecked = $('.aips-history-table input[name="history[]"]').length === $('.aips-history-table input[name="history[]"]:checked').length;
            $('#cb-select-all-1').prop('checked', allChecked);
            AIPS.updateDeleteButton();
        },

        updateDeleteButton: function() {
            var count = $('.aips-history-table input[name="history[]"]:checked').length;
            $('#aips-delete-selected-btn').prop('disabled', count === 0);
        },

        deleteSelectedHistory: function(e) {
            e.preventDefault();
            var ids = [];
            $('.aips-history-table input[name="history[]"]:checked').each(function() {
                ids.push($(this).val());
            });

            if (ids.length === 0) return;

            if (!confirm('Are you sure you want to delete ' + ids.length + ' item(s)?')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_delete_history',
                    nonce: aipsAjax.nonce,
                    ids: ids
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                        $btn.prop('disabled', false).text('Delete Selected');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $btn.prop('disabled', false).text('Delete Selected');
                }
            });
        }
    });

    $(document).ready(function() {
        AIPS.init();
        // Load voices on template page load
        if ($('#voice_search').length) {
            AIPS.searchVoices.call($('#voice_search'));
        }
    });

})(jQuery);
