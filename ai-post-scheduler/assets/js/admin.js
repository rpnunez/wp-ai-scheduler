(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    Object.assign(AIPS, {
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
            $(document).on('change', '#featured_image_source', this.toggleFeaturedImageSourceFields);
            $(document).on('click', '#featured_image_media_select', this.openMediaLibrary);
            $(document).on('click', '#featured_image_media_clear', this.clearMediaSelection);
            $(document).on('keyup', '#voice_search', this.searchVoices);

            $(document).on('click', '.aips-add-voice-btn', this.openVoiceModal);
            $(document).on('click', '.aips-edit-voice', this.editVoice);
            $(document).on('click', '.aips-delete-voice', this.deleteVoice);
            $(document).on('click', '.aips-save-voice', this.saveVoice);

            $(document).on('click', '.aips-add-schedule-btn', this.openScheduleModal);
            $(document).on('click', '.aips-clone-schedule', this.cloneSchedule);
            $(document).on('click', '.aips-save-schedule', this.saveSchedule);
            $(document).on('click', '.aips-delete-schedule', this.deleteSchedule);
            $(document).on('change', '.aips-toggle-schedule', this.toggleSchedule);

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

            // Template Search
            $(document).on('keyup search', '#aips-template-search', this.filterTemplates);
            $(document).on('click', '#aips-template-search-clear', this.clearTemplateSearch);
            $(document).on('click', '.aips-clear-search-btn', this.clearTemplateSearch);

            // Schedule Search
            $(document).on('keyup search', '#aips-schedule-search', this.filterSchedules);
            $(document).on('click', '#aips-schedule-search-clear', this.clearScheduleSearch);
            $(document).on('click', '.aips-clear-schedule-search-btn', this.clearScheduleSearch);

            // Voice Search
            $(document).on('keyup search', '#aips-voice-search', this.filterVoices);
            $(document).on('click', '#aips-voice-search-clear', this.clearVoiceSearch);
            $(document).on('click', '.aips-clear-voice-search-btn', this.clearVoiceSearch);

            $(document).on('click', '.aips-view-template-posts', this.openTemplatePostsModal);
            $(document).on('click', '.aips-modal-page', this.paginateTemplatePosts);

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

        openTemplateModal: function(e) {
            e.preventDefault();
            $('#aips-template-form')[0].reset();
            $('#template_id').val('');
            $('#aips-modal-title').text('Add New Template');
            $('#featured_image_source').val('ai_prompt');
            $('#featured_image_unsplash_keywords').val('');
            AIPS.setMediaSelection([]);
            AIPS.toggleImagePrompt();
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
                        $('#image_prompt').val(t.image_prompt || '');
                        $('#featured_image_source').val(t.featured_image_source || 'ai_prompt');
                        $('#featured_image_unsplash_keywords').val(t.featured_image_unsplash_keywords || '');
                        AIPS.setMediaSelection(t.featured_image_media_ids || '');
                        $('#post_status').val(t.post_status);
                        $('#post_category').val(t.post_category);
                        $('#post_tags').val(t.post_tags);
                        $('#post_author').val(t.post_author);
                        $('#is_active').prop('checked', t.is_active == 1);
                        AIPS.toggleImagePrompt();
                        AIPS.toggleFeaturedImageSourceFields();
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
            var $btn = $(this);
            var id = $btn.data('id');
            var $row = $btn.closest('tr');

            // Soft Confirm Pattern
            if (!$btn.data('is-confirming')) {
                $btn.data('original-text', $btn.text());
                $btn.text('Click again to confirm');
                $btn.addClass('aips-confirm-delete');
                $btn.data('is-confirming', true);

                // Reset after 3 seconds
                setTimeout(function() {
                    $btn.text($btn.data('original-text'));
                    $btn.removeClass('aips-confirm-delete');
                    $btn.data('is-confirming', false);
                }, 3000);
                return;
            }

            // Confirmed, proceed with deletion
            $btn.prop('disabled', true).text('Deleting...');

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
                        // Reset button state on error
                        $btn.text($btn.data('original-text'));
                        $btn.removeClass('aips-confirm-delete');
                        $btn.data('is-confirming', false);
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    // Reset button state on error
                    $btn.text($btn.data('original-text'));
                    $btn.removeClass('aips-confirm-delete');
                    $btn.data('is-confirming', false);
                    $btn.prop('disabled', false);
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
                    featured_image_source: $('#featured_image_source').val(),
                    featured_image_unsplash_keywords: $('#featured_image_unsplash_keywords').val(),
                    featured_image_media_ids: $('#featured_image_media_ids').val(),
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
            var type = $(this).data('type') || 'template';
            var $btn = $(this);

            $btn.prop('disabled', true).text('Generating...');

            var data = {
                action: 'aips_run_now',
                nonce: aipsAjax.nonce
            };

            if (type === 'schedule') {
                data.schedule_id = id;
            } else {
                data.template_id = id;
            }

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: data,
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
            $('#aips-schedule-modal-title').text('Add New Schedule');
            $('#aips-schedule-modal').show();
        },

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

        toggleImagePrompt: function(e) {
            var isChecked = $('#generate_featured_image').is(':checked');
            $('.aips-featured-image-settings').toggle(isChecked);
            $('#featured_image_source').prop('disabled', !isChecked);

            if (isChecked) {
                AIPS.toggleFeaturedImageSourceFields();
            }
        },

        toggleFeaturedImageSourceFields: function() {
            var source = $('#featured_image_source').val();
            $('.aips-image-source').hide();

            if (source === 'unsplash') {
                $('.aips-image-source-unsplash').show();
            } else if (source === 'media_library') {
                $('.aips-image-source-media').show();
            } else {
                $('.aips-image-source-ai').show();
            }
        },

        setMediaSelection: function(ids) {
            var parsedIds = [];

            if (Array.isArray(ids)) {
                parsedIds = ids;
            } else if (typeof ids === 'string') {
                parsedIds = ids.split(',').filter(function(id) {
                    return id.trim().length > 0;
                });
            }

            $('#featured_image_media_ids').val(parsedIds.join(','));
            $('#featured_image_media_preview').text(parsedIds.length ? parsedIds.join(', ') : 'No images selected.');
        },

        openMediaLibrary: function(e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) {
                alert('Media library is not available.');
                return;
            }

            if (!AIPS.mediaFrame) {
                AIPS.mediaFrame = wp.media({
                    title: 'Select Images',
                    multiple: true,
                    library: { type: 'image' },
                    button: { text: 'Use these images' }
                });

                AIPS.mediaFrame.on('select', function() {
                    var selection = AIPS.mediaFrame.state().get('selection');
                    var ids = [];

                    selection.each(function(attachment) {
                        ids.push(attachment.id);
                    });

                    AIPS.setMediaSelection(ids);
                });
            }

            AIPS.mediaFrame.open();
        },

        clearMediaSelection: function(e) {
            if (e) {
                e.preventDefault();
            }
            AIPS.setMediaSelection([]);
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

        clearScheduleSearch: function(e) {
            e.preventDefault();
            $('#aips-schedule-search').val('').trigger('keyup');
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

        openTemplatePostsModal: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            $('#aips-template-posts-modal').data('template-id', id).show();
            AIPS.loadTemplatePosts(id, 1);
        },

        paginateTemplatePosts: function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            var id = $('#aips-template-posts-modal').data('template-id');
            AIPS.loadTemplatePosts(id, page);
        },

        loadTemplatePosts: function(id, page) {
            $('#aips-template-posts-content').html('<p class="aips-loading">Loading...</p>');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_template_posts',
                    nonce: aipsAjax.nonce,
                    template_id: id,
                    page: page
                },
                success: function(response) {
                    if (response.success) {
                        $('#aips-template-posts-content').html(response.data.html);
                    } else {
                        $('#aips-template-posts-content').html('<p class="aips-error-text">' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $('#aips-template-posts-content').html('<p class="aips-error-text">An error occurred.</p>');
                }
            });
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
