(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};

    // Global helpers used by multiple admin modules
    Object.assign(window.AIPS, {
        /**
         * Resolve AJAX URL to use for admin requests. Falls back to `ajaxurl`.
         * @return {string}
         */
        resolveAjaxUrl: function() {
            if (typeof aipsAjax !== 'undefined' && aipsAjax.ajaxUrl) return aipsAjax.ajaxUrl;
            if (typeof ajaxurl !== 'undefined') return ajaxurl;
            return '';
        },

        /**
         * Resolve the plugin nonce for AJAX security.
         * Uses `aipsAjax.nonce` when available or reads a DOM element `#aips_nonce` as a fallback.
         * @return {string}
         */
        resolveNonce: function() {
            if (typeof aipsAjax !== 'undefined' && aipsAjax.nonce) return aipsAjax.nonce;
            var $aipsNonce = $('#aips_nonce');
            if ($aipsNonce.length) return $aipsNonce.val();
            return '';
        },

        /**
         * Escape HTML for safe insertion into the DOM. Shared across admin scripts.
         * @param {string} text
         * @return {string}
         */
        escapeHtml: function(text) {
            if (text === null || text === undefined) return '';
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        /**
         * Copy text to clipboard. Works with modern and legacy browsers.
         * @param {Event} e - Click event from copy button
         */
        copyToClipboard: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var text = $btn.data('clipboard-text');
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

                    $btn.text(AIPS.t ? AIPS.t('copied','', 'Copied!') : 'Copied!');

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
                $btn.text(AIPS.t ? AIPS.t('copied','', 'Copied!') : 'Copied!');

                setTimeout(function() {
                    $btn.text(originalText);

                }, 2000);
            }, function(err) {
                console.error('Async: Could not copy text: ', err);
            });
        },

        /**
         * Switch between admin tabs
         * @param {Event} e - Click event from tab
         */
        switchTab: function(e) {
            e.preventDefault();

            var tabId = $(this).data('tab');

            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.aips-tab-content').hide();
            $('#' + tabId + '-tab').show();
        },

        closeModal: function() {
            var $target = $(this).closest('.aips-modal');

            if ($target.length) {
                $target.hide();
            } else {
                $('.aips-modal').hide();
            }
        }
    });

    var AIPS = window.AIPS;

    Object.assign(AIPS, {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // ===== Global / Helpers =====
            // Tabs
            $(document).on('click', '.nav-tab', this.switchTab);

            // Modal backdrop click and close button
            $(document).on('click', '.aips-modal', function(e) {
                if ($(e.target).hasClass('aips-modal')) {
                    AIPS.closeModal();
                }
            });

            $(document).on('click', '.aips-modal-close', this.closeModal);

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    AIPS.closeModal();
                }
            });

            // Copy to Clipboard
            $(document).on('click', '.aips-copy-btn', this.copyToClipboard);

            // Settings / connection test
            $(document).on('click', '#aips-test-connection', this.testConnection);

            // ===== Templates =====
            // NOTE: Template handlers moved to admin-templates.js

            // ===== Voices =====
            $(document).on('keyup', '#voice_search', this.searchVoices);
            $(document).on('click', '.aips-add-voice-btn', this.openVoiceModal);
            $(document).on('click', '.aips-edit-voice', this.editVoice);
            $(document).on('click', '.aips-delete-voice', this.deleteVoice);
            $(document).on('click', '.aips-save-voice', this.saveVoice);

            // ===== Schedules =====
            $(document).on('click', '.aips-run-now', this.runScheduleNow);
            $(document).on('click', '.aips-add-schedule-btn', this.openScheduleModal);
            $(document).on('click', '.aips-clone-schedule', this.cloneSchedule);
            $(document).on('click', '.aips-save-schedule', this.saveSchedule);
            $(document).on('click', '.aips-delete-schedule', this.deleteSchedule);
            $(document).on('change', '.aips-toggle-schedule', this.toggleSchedule);

            // ===== History (single-item actions) =====
            $(document).on('click', '.aips-clear-history', this.clearHistory);
            $(document).on('click', '.aips-retry-generation', this.retryHistoryGeneration);
            $(document).on('click', '#aips-filter-btn', this.filterHistory);
            $(document).on('click', '#aips-history-search-btn', this.filterHistory);
            $(document).on('click', '.aips-view-details', this.viewHistoryDetails);
            $(document).on('keypress', '#aips-history-search-input', function(e) {
                if (e.which === 13) {
                    AIPS.filterHistory(e);
                }
            });

            // ===== History Bulk Actions =====
            $(document).on('change', '#cb-select-all-1', this.toggleAllHistory);
            $(document).on('change', '.aips-history-table input[name="history[]"]', this.toggleHistorySelection);
            $(document).on('click', '#aips-delete-selected-btn', this.deleteSelectedHistory);

            // ===== Search Helpers =====

            // Schedule search
            $(document).on('keyup search', '#aips-schedule-search', this.filterSchedules);
            $(document).on('click', '#aips-schedule-search-clear', this.clearScheduleSearch);
            $(document).on('click', '.aips-clear-schedule-search-btn', this.clearScheduleSearch);

            // Voice search
            $(document).on('keyup search', '#aips-voice-search', this.filterVoices);
            $(document).on('click', '#aips-voice-search-clear', this.clearVoiceSearch);
            $(document).on('click', '.aips-clear-voice-search-btn', this.clearVoiceSearch);

            // Article Structures
            $(document).on('click', '.aips-add-structure-btn', this.openStructureModal);
            $(document).on('click', '.aips-save-structure', this.saveStructure);
            $(document).on('click', '.aips-edit-structure', this.editStructure);
            $(document).on('click', '.aips-delete-structure', this.deleteStructure);
        },

        // Open 'Add Structure' modal and reset form
        openStructureModal: function(e) {
            e.preventDefault();
            $('#aips-structure-form')[0].reset();
            $('#structure_id').val('');
            $('#aips-structure-modal-title').text(aipsAdminL10n.addStructure);
            $('#aips-structure-modal').show();
        },

        // Save structure (create or update)
        saveStructure: function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).text(aipsAdminL10n.saving);

            var data = {
                action: 'aips_save_structure',
                nonce: AIPS.resolveNonce(),
                structure_id: $('#structure_id').val(),
                name: $('#structure_name').val(),
                description: $('#structure_description').val(),
                prompt_template: $('#prompt_template').val(),
                sections: $('#structure_sections').val() || [],
                is_active: $('#structure_is_active').is(':checked') ? 1 : 0,
                is_default: $('#structure_is_default').is(':checked') ? 1 : 0,
            };

            $.post(AIPS.resolveAjaxUrl(), data, function(response){
                $btn.prop('disabled', false).text(aipsAdminL10n.saveStructure);

                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || aipsAdminL10n.saveStructureFailed);
                }
            }).fail(function(){
                $btn.prop('disabled', false).text(aipsAdminL10n.saveStructure);

                alert(aipsAdminL10n.errorTryAgain);
            });
        },

        // Edit structure — load data and open modal
        editStructure: function(e) {
            e.preventDefault();
            var id = $(this).data('id');

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_get_structure',
                    nonce: AIPS.resolveNonce(),
                    structure_id: id
                },
                success: function(response) {
                    if (response.success) {
                        var s = response.data.structure;
                        var structureData = {};

                        if (s.structure_data) {
                            try {
                                structureData = JSON.parse(s.structure_data) || {};
                            } catch (err) {
                                console.error('Invalid structure_data JSON for structure ID ' + s.id, err);
                                structureData = {};
                            }
                        }

                        $('#structure_id').val(s.id);
                        $('#structure_name').val(s.name);
                        $('#structure_description').val(s.description);
                        $('#prompt_template').val(structureData.prompt_template || '');

                        var sections = structureData.sections || [];
                        $('#structure_sections').val(sections);
                        $('#structure_is_active').prop('checked', s.is_active === 1);
                        $('#structure_is_default').prop('checked', s.is_default === 1);
                        $('#aips-structure-modal-title').text(aipsAdminL10n.editStructure);
                        $('#aips-structure-modal').show();
                    } else {
                        alert(response.data.message || aipsAdminL10n.loadStructureFailed);
                    }
                },
                error: function() {
                    alert(aipsAdminL10n.errorOccurred);
                }
            });
        },

        // Delete structure
        deleteStructure: function(e) {
            e.preventDefault();
            if (!confirm(aipsAdminL10n.deleteStructureConfirm)) return;
            var id = $(this).data('id');
            var $row = $(this).closest('tr');

            $.post(AIPS.resolveAjaxUrl(), { action: 'aips_delete_structure', nonce: AIPS.resolveNonce(), structure_id: id }, function(response){
                if (response.success) {
                    $row.fadeOut(function(){
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message || aipsAdminL10n.deleteStructureFailed);
                }
            }).fail(function(){
                alert(aipsAdminL10n.errorOccurred);
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
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_test_connection',
                    nonce: AIPS.resolveNonce()
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('aips-status-ok').html('<span class="dashicons dashicons-yes"></span> ' + response.data.message);
                    } else {
                        $result.addClass('aips-status-error').html('<span class="dashicons dashicons-warning"></span> ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('aips-status-error').text(aipsAdminL10n.errorTryAgain);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        runScheduleNow: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $btn = $(this);

            $btn.prop('disabled', true).text('Generating...');

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_run_now',
                    nonce: AIPS.resolveNonce(),
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
                    alert(aipsAdminL10n.errorTryAgain);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Run Now');
                }
            });
        },

        searchVoices: function() {
            var search = $(this).val();
            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_search_voices',
                    nonce: AIPS.resolveNonce(),
                    search: search
                },
                success: function(response) {
                    if (response.success) {
                        var $select = $('#voice_id');
                        var currentVal = $select.val();
                        $select.html('<option value="0">' + aipsAdminL10n.noVoiceUseDefault + '</option>');
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
            $('#aips-voice-modal-title').text(aipsAdminL10n.addVoice);
            $('#aips-voice-modal').show();
        },

        editVoice: function(e) {
            e.preventDefault();

            var id = $(this).data('id');

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_get_voice',
                    nonce: AIPS.resolveNonce(),
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
                        $('#voice_is_active').prop('checked', v.is_active === 1);
                        $('#aips-voice-modal-title').text(aipsAdminL10n.editVoice);
                        $('#aips-voice-modal').show();
                    }
                }
            });
        },

        deleteVoice: function(e) {
            e.preventDefault();

            var delVoiceMsg = aipsAdminL10n.deleteVoiceConfirm;

            if (!confirm(delVoiceMsg)) {
                 return;
             }

            var id = $(this).data('id');
            var $row = $(this).closest('tr');

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_delete_voice',
                    nonce: AIPS.resolveNonce(),
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
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_save_voice',
                    nonce: AIPS.resolveNonce(),
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
                    alert(aipsAdminL10n.errorTryAgain);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(aipsAdminL10n.saveVoice);
                }
            });
        },

        openScheduleModal: function(e) {
            e.preventDefault();

            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val('');
            $('#aips-schedule-modal-title').text(aipsAdminL10n.addSchedule);
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
            $('#aips-schedule-modal-title').text(aipsAdminL10n.cloneSchedule);
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
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_save_schedule',
                    nonce: AIPS.resolveNonce(),
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
                    alert(aipsAdminL10n.errorTryAgain);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Schedule');
                }
            });
        },

        deleteSchedule: function(e) {
            e.preventDefault();

            var delSchedMsg = aipsAdminL10n.deleteScheduleConfirm;
            if (!confirm(delSchedMsg)) {
                 return;
             }

            var id = $(this).data('id');
            var $row = $(this).closest('tr');

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_delete_schedule',
                    nonce: AIPS.resolveNonce(),
                    schedule_id: id
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || aipsAdminL10n.errorOccurred);
                    }
                },
                error: function() {
                    alert(aipsAdminL10n.errorTryAgain);
                }
            });
        },

        toggleSchedule: function() {
            var id = $(this).data('id');
            var isActive = $(this).is(':checked') ? 1 : 0;

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_toggle_schedule',
                    nonce: AIPS.resolveNonce(),
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
            var message;

            if (status) {
                message = aipsAdminL10n.clearHistoryStatus.replace('{status}', status);
            } else {
                message = aipsAdminL10n.clearHistory;
            }

            if (!confirm(message)) {
                 return;
             }

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_clear_history',
                    nonce: AIPS.resolveNonce(),
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || aipsAdminL10n.errorOccurred);
                    }
                },
                error: function() {
                    alert(aipsAdminL10n.errorTryAgain);
                }
            });
        },

        retryHistoryGeneration: function(e) {
            e.preventDefault();

            var id = $(this).data('id');
            var $btn = $(this);

            $btn.prop('disabled', true).text('Retrying...');

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_retry_generation',
                    nonce: AIPS.resolveNonce(),
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

        toggleImagePrompt: function() {
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

        setTemplateMediaSelection: function(ids) {
            var parsedIds = [];

            if (Array.isArray(ids)) {
                parsedIds = ids;
            } else if (typeof ids === 'string') {
                parsedIds = ids.split(',').filter(function(id) {
                    return id.trim().length > 0;
                });
            }

            $('#featured_image_media_ids').val(parsedIds.join(','));
            $('#featured_image_media_preview').text(parsedIds.length ? parsedIds.join(', ') : aipsAdminL10n.noImagesSelected);
        },

        openMediaLibraryForTemplate: function(e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) {
                alert(aipsAdminL10n.mediaLibraryUnavailable);

                return;
            }

            if (!AIPS.mediaFrame) {
                AIPS.mediaFrame = wp.media({
                    title: aipsAdminL10n.selectImages,
                    multiple: true,
                    library: {
                        type: 'image'
                    },
                    button: {
                        text: aipsAdminL10n.useTheseImages
                    }
                });

                AIPS.mediaFrame.on('select', function() {
                    var selection = AIPS.mediaFrame.state().get('selection');
                    var ids = [];

                    selection.each(function(attachment) {
                        ids.push(attachment.id);
                    });

                    AIPS.setTemplateMediaSelection(ids);
                });
            }

            AIPS.mediaFrame.open();
        },

        clearTemplateMediaSelection: function(e) {
            if (e) {
                e.preventDefault();
            }

            AIPS.setTemplateMediaSelection([]);
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

        viewHistoryDetails: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $btn = $(this);

            $btn.prop('disabled', true);

            $('#aips-details-loading').show();
            $('#aips-details-content').hide();
            $('#aips-details-modal').show();

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_get_history_details',
                    nonce: AIPS.resolveNonce(),
                    history_id: id
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.renderHistoryDetails(response.data);
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

        renderHistoryDetails: function(data) {
            var log = data.generation_log || {};
            var statusLabel = data.status.charAt(0).toUpperCase() + data.status.slice(1);

            // Summary section with template literal
            $('#aips-details-summary').html(`
                <table class="aips-details-table">
                    <tr><th>Status:</th><td><span class="aips-status aips-status-${data.status}">${statusLabel}</span></td></tr>
                    <tr><th>Title:</th><td>${data.generated_title || '-'}</td></tr>
                    ${data.post_id ? `<tr><th>Post ID:</th><td>${data.post_id}</td></tr>` : ''}
                    <tr><th>Started:</th><td>${log.started_at || data.created_at}</td></tr>
                    <tr><th>Completed:</th><td>${log.completed_at || data.completed_at || '-'}</td></tr>
                    ${data.error_message ? `<tr><th>Error:</th><td class="aips-error-text">${data.error_message}</td></tr>` : ''}
                </table>
            `);

            // Template section
            if (log.template) {
                $('#aips-details-template').html(`
                    <table class="aips-details-table">
                        <tr><th>Name:</th><td>${log.template.name || '-'}</td></tr>
                        <tr><th>Prompt Template:</th><td>
                            <button class="button button-small aips-copy-btn" data-clipboard-text="${AIPS.escapeHtml(log.template.prompt_template || '')}">
                                <span class="dashicons dashicons-admin-page"></span> Copy
                            </button>
                            <pre class="aips-prompt-text">${AIPS.escapeHtml(log.template.prompt_template || '')}</pre>
                        </td></tr>
                        ${log.template.title_prompt ? `
                            <tr><th>Title Prompt:</th><td>
                                <button class="button button-small aips-copy-btn" data-clipboard-text="${AIPS.escapeHtml(log.template.title_prompt)}">
                                    <span class="dashicons dashicons-admin-page"></span> Copy
                                </button>
                                <pre class="aips-prompt-text">${AIPS.escapeHtml(log.template.title_prompt)}</pre>
                            </td></tr>
                        ` : ''}
                        <tr><th>Post Status:</th><td>${log.template.post_status || 'draft'}</td></tr>
                        <tr><th>Post Quantity:</th><td>${log.template.post_quantity || 1}</td></tr>
                        ${log.template.generate_featured_image ? `
                            <tr><th>Image Prompt:</th><td>
                                <pre class="aips-prompt-text">${AIPS.escapeHtml(log.template.image_prompt || '')}</pre>
                            </td></tr>
                        ` : ''}
                    </table>
                `);
            } else {
                $('#aips-details-template').html('<p>No template data available.</p>');
            }

            // Voice section
            if (log.voice) {
                $('#aips-details-voice').html(`
                    <table class="aips-details-table">
                        <tr><th>Name:</th><td>${log.voice.name || '-'}</td></tr>
                        <tr><th>Title Prompt:</th><td>
                            <button class="button button-small aips-copy-btn" data-clipboard-text="${AIPS.escapeHtml(log.voice.title_prompt || '')}">
                                <span class="dashicons dashicons-admin-page"></span> Copy
                            </button>
                            <pre class="aips-prompt-text">${AIPS.escapeHtml(log.voice.title_prompt || '')}</pre>
                        </td></tr>
                        <tr><th>Content Instructions:</th><td>
                            <button class="button button-small aips-copy-btn" data-clipboard-text="${AIPS.escapeHtml(log.voice.content_instructions || '')}">
                                <span class="dashicons dashicons-admin-page"></span> Copy
                            </button>
                            <pre class="aips-prompt-text">${AIPS.escapeHtml(log.voice.content_instructions || '')}</pre>
                        </td></tr>
                        ${log.voice.excerpt_instructions ? `
                            <tr><th>Excerpt Instructions:</th><td>
                                <button class="button button-small aips-copy-btn" data-clipboard-text="${AIPS.escapeHtml(log.voice.excerpt_instructions)}">
                                    <span class="dashicons dashicons-admin-page"></span> Copy
                                </button>
                                <pre class="aips-prompt-text">${AIPS.escapeHtml(log.voice.excerpt_instructions)}</pre>
                            </td></tr>
                        ` : ''}
                    </table>
                `);
                $('#aips-details-voice-section').show();
            } else {
                $('#aips-details-voice-section').hide();
            }

            // AI Calls section
            if (log.ai_calls && log.ai_calls.length > 0) {
                var callsHtml = log.ai_calls.map(function(call, index) {
                    var statusClass = call.response.success ? 'aips-call-success' : 'aips-call-error';
                    var callType = call.type.charAt(0).toUpperCase() + call.type.slice(1);
                    var optionsHtml = (call.request.options && Object.keys(call.request.options).length > 0)
                        ? `<p><small>Options: ${JSON.stringify(call.request.options)}</small></p>`
                        : '';
                    var responseHtml = call.response.success
                        ? `<pre class="aips-response-text">${AIPS.escapeHtml(call.response.content || '')}</pre>`
                        : `<p class="aips-error-text">Error: ${AIPS.escapeHtml(call.response.error || 'Unknown error')}</p>`;

                    return `
                        <div class="aips-ai-call ${statusClass}">
                            <div class="aips-call-header">
                                <strong>Call #${index + 1} - ${callType}</strong>
                                <span class="aips-call-time">${call.timestamp}</span>
                            </div>
                            <div class="aips-call-section">
                                <h4>Request</h4>
                                <pre class="aips-prompt-text">${AIPS.escapeHtml(call.request.prompt || '')}</pre>
                                ${optionsHtml}
                            </div>
                            <div class="aips-call-section">
                                <h4>Response</h4>
                                ${responseHtml}
                            </div>
                        </div>
                    `;
                }).join('');

                $('#aips-details-ai-calls').html(callsHtml);
            } else {
                $('#aips-details-ai-calls').html('<p>No AI call data available for this entry.</p>');
            }

            // Errors section
            if (log.errors && log.errors.length > 0) {
                var errorItems = log.errors.map(function(error) {
                    return `
                        <li>
                            <strong>${error.type}</strong> at ${error.timestamp}<br>
                            <span class="aips-error-text">${AIPS.escapeHtml(error.message)}</span>
                        </li>
                    `;
                }).join('');

                $('#aips-details-errors').html(`<ul class="aips-errors-list">${errorItems}</ul>`);
                $('#aips-details-errors-section').show();
            } else {
                $('#aips-details-errors-section').hide();
            }

            $('#aips-details-content').show();
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

            if (ids.length === 0) {
                return;
            }

            var deleteConfirmMsg = aipsAdminL10n.deleteConfirmMultiple.replace('{count}', ids.length);

            if (!confirm(deleteConfirmMsg)) {
                 return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text(aipsAdminL10n.deleting);

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_bulk_delete_history',
                    nonce: AIPS.resolveNonce(),
                    ids: ids
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || aipsAdminL10n.bulkDeleteFailed);

                        $btn.prop('disabled', false).text(aipsAdminL10n.deleteSelected);
                    }
                },
                error: function() {
                    alert(aipsAdminL10n.errorTryAgain);

                    $btn.prop('disabled', false).text(aipsAdminL10n.deleteSelected);
                }
            });
         }
     });

    $(document).ready(function() {
        AIPS.init();

        // Load voices on template page load
        var $voiceSearch = $('#voice_search');

        if ($voiceSearch.length) {
            // Call searchVoices with a DOM element as context so $(this).val() works correctly
            AIPS.searchVoices.call($voiceSearch[0]);
        }
    });

})(jQuery);
