(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    // System variables that should not be treated as AI Variables
    var SYSTEM_VARIABLES = ['date', 'year', 'month', 'day', 'time', 'site_name', 'site_description', 'random_number', 'topic', 'title'];

    Object.assign(AIPS, {
        /**
         * Bootstrap the AIPS admin interface.
         *
         * Registers all delegated event listeners, runs the initial AI variables
         * scan, activates any tab referenced by the URL hash, and auto-opens the
         * schedule modal when a template ID is passed via query parameter.
         */
        init: function() {
            this.bindEvents();
            this.initAIVariablesScanner();
            this.handleInitialTabFromHash();
            this.initScheduleAutoOpen();
        },

        /**
         * Activate the tab matching the current URL hash on page load.
         *
         * Reads `window.location.hash`, strips the leading `#`, and triggers
         * a click on the corresponding `.nav-tab[data-tab]` element so the
         * correct tab panel is displayed immediately after navigation.
         */
        handleInitialTabFromHash: function() {
            // Check for hash in URL and activate the corresponding tab
            var hash = window.location.hash;
            if (hash) {
                var tabId = hash.substring(1); // Remove the # prefix
                var $tabLink = $('.nav-tab[data-tab], .aips-tab-link[data-tab]').filter(function() {
                    return $(this).data('tab') === tabId;
                });
                if ($tabLink.length) {
                    $tabLink.trigger('click');
                }
            }
        },

        /**
         * Register all delegated jQuery event listeners for the admin UI.
         *
         * Uses event delegation on `document` so handlers work for elements
         * injected dynamically (e.g. rows rendered after an AJAX call).
         * Each handler is a named method on the AIPS object.
         */
        bindEvents: function() {
            $(document).on('click', '.aips-add-template-btn', this.openTemplateModal);
            $(document).on('click', '.aips-edit-template', this.editTemplate);
            $(document).on('click', '.aips-clone-template', this.cloneTemplate);
            $(document).on('click', '.aips-delete-template', this.deleteTemplate);
            $(document).on('click', '.aips-save-template', this.saveTemplate);
            $(document).on('click', '.aips-save-draft-template', this.saveDraftTemplate);
            $(document).on('click', '.aips-test-template', this.testTemplate);
            $(document).on('click', '.aips-run-now', this.runNow);
            $(document).on('change', '#generate_featured_image', this.toggleImagePrompt);
            $(document).on('change', '#featured_image_source', this.toggleFeaturedImageSourceFields);
            $(document).on('click', '#featured_image_media_select', this.openMediaLibrary);
            $(document).on('click', '#featured_image_media_clear', this.clearMediaSelection);
            $(document).on('keyup', '#voice_search', this.searchVoices);

            // Wizard navigation
            $(document).on('click', '.aips-wizard-next', this.wizardNext);
            $(document).on('click', '.aips-wizard-back', this.wizardBack);

            // Post-save next steps
            $(document).on('click', '#aips-quick-schedule-btn', this.quickSchedule);
            $(document).on('click', '#aips-quick-run-now-btn', this.quickRunNow);
            $(document).on('click', '#aips-post-save-done-btn', function() { location.reload(); });

            // Preview drawer
            $(document).on('click', '.aips-preview-prompts', this.previewPrompts);
            $(document).on('click', '.aips-preview-drawer-handle', this.togglePreviewDrawer);

            // AI Variables scanning
            $(document).on('input', '.aips-ai-var-input', function() {
                AIPS.scanAllAIVariables();
            });
            $(document).on('click', '.aips-ai-var-tag', this.copyAIVariable);

            $(document).on('click', '.aips-add-voice-btn', this.openVoiceModal);
            $(document).on('click', '.aips-edit-voice', this.editVoice);
            $(document).on('click', '.aips-delete-voice', this.deleteVoice);
            $(document).on('click', '.aips-save-voice', this.saveVoice);

            $(document).on('click', '.aips-add-schedule-btn', this.openScheduleModal);
            $(document).on('click', '.aips-edit-schedule', this.editSchedule);
            $(document).on('click', '.aips-clone-schedule', this.cloneSchedule);
            $(document).on('click', '.aips-run-now-schedule', this.runNowSchedule);
            $(document).on('click', '.aips-save-schedule', this.saveSchedule);
            $(document).on('click', '.aips-delete-schedule', this.deleteSchedule);
            $(document).on('change', '.aips-toggle-schedule', this.toggleSchedule);

            // Schedule Bulk Actions
            $(document).on('change', '#cb-select-all-schedules', this.toggleAllSchedules);
            $(document).on('change', '.aips-schedule-checkbox', this.toggleScheduleSelection);
            $(document).on('click', '#aips-schedule-select-all', this.selectAllSchedules);
            $(document).on('click', '#aips-schedule-unselect-all', this.unselectAllSchedules);
            $(document).on('click', '#aips-schedule-bulk-apply', this.applyScheduleBulkAction);

            $(document).on('click', '.aips-clear-history', this.clearHistory);
            $(document).on('click', '.aips-retry-generation', this.retryGeneration);
            $(document).on('click', '#aips-filter-btn', this.filterHistory);
            $(document).on('click', '#aips-export-history-btn', this.exportHistory);
            $(document).on('click', '#aips-history-search-btn', this.filterHistory);
            $(document).on('click', '#aips-reload-history-btn', this.reloadHistory);
            $(document).on('click', '.aips-history-page-link, .aips-history-page-prev, .aips-history-page-next', this.loadHistoryPage);
            $(document).on('keypress', '#aips-history-search-input', this.handleHistorySearchKeypress);
            $(document).on('click', '.aips-view-details', this.viewDetails);

            // History Pagination
            $(document).on('click', '#aips-history-pagination a', this.handleHistoryPaginationClick);

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

            // Section Search
            $(document).on('keyup search', '#aips-section-search', this.filterSections);
            $(document).on('click', '#aips-section-search-clear', this.clearSectionSearch);
            $(document).on('click', '.aips-clear-section-search-btn', this.clearSectionSearch);

            // Structure Search
            $(document).on('keyup search', '#aips-structure-search', this.filterStructures);
            $(document).on('click', '#aips-structure-search-clear', this.clearStructureSearch);
            $(document).on('click', '.aips-clear-structure-search-btn', this.clearStructureSearch);

            // Author Search
            $(document).on('keyup search', '#aips-author-search', this.filterAuthors);
            $(document).on('click', '#aips-author-search-clear', this.clearAuthorSearch);
            $(document).on('click', '.aips-clear-author-search-btn', this.clearAuthorSearch);

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
            $(document).on('click', '.aips-tab-link', this.switchAipsTab);
            
            // Preserve tab hash on form submissions
            $(document).on('submit', '.aips-post-review-filters, form[action*="aips-generated-posts"]', this.preserveTabOnSubmit);

            // Copy to Clipboard
            $(document).on('click', '.aips-copy-btn', this.copyToClipboard);

            // Article Structures UI handlers
            $(document).on('click', '.aips-add-structure-btn', this.openAddStructureModal);

            $(document).on('click', '.aips-save-structure', this.saveStructure);

            $(document).on('click', '.aips-edit-structure', this.editStructure);

            $(document).on('click', '.aips-delete-structure', this.deleteStructure);

            // Prompt Sections UI handlers
            $(document).on('click', '.aips-add-section-btn', this.openAddSectionModal);

            $(document).on('click', '.aips-save-section', this.saveSection);

            $(document).on('click', '.aips-edit-section', this.editSection);

            $(document).on('click', '.aips-delete-section', this.deleteSection);
        },

        /**
         * Copy the `data-clipboard-text` value of the clicked button to the
         * user's clipboard.
         *
         * On success, briefly swaps the button label to "Copied!" (or swaps the
         * dashicon to a checkmark for icon-only `.aips-copy-btn-small` buttons).
         * Falls back to `document.execCommand('copy')` when the Clipboard API
         * is unavailable.
         *
         * @param {Event} e - Click event from an `.aips-copy-btn` element.
         */
        copyToClipboard: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var text = $btn.data('clipboard-text');

            if (!text) return;

            var showSuccess = function() {
                // If button has specific small/icon class or no text content, swap icon
                if ($btn.hasClass('aips-copy-btn-small') || $btn.text().trim().length === 0) {
                    var $icon = $btn.find('.dashicons');
                    if ($icon.length) {
                        var originalClass = $icon.attr('class');
                        $icon.removeClass().addClass('dashicons dashicons-yes');
                        setTimeout(function() {
                            $icon.attr('class', originalClass);
                        }, 2000);
                        return;
                    }
                }

                // Standard text swap
                var originalText = $btn.text();
                // Store original HTML if needed, but text() is usually enough for text buttons
                // For safety, let's use html() to be robust
                var originalHtml = $btn.html();

                $btn.text('Copied!');
                setTimeout(function() {
                    $btn.html(originalHtml);
                }, 2000);
            };

            // Fallback for older browsers
            if (!navigator.clipboard) {
                var textArea = document.createElement("textarea");
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    showSuccess();
                } catch (err) {
                    console.error('Fallback: Oops, unable to copy', err);
                }
                document.body.removeChild(textArea);
                return;
            }

            navigator.clipboard.writeText(text).then(function() {
                showSuccess();
            }, function(err) {
                console.error('Async: Could not copy text: ', err);
            });
        },

        /**
         * Test the connection to the configured AI engine.
         *
         * Sends an AJAX request to the `aips_test_connection` action and
         * displays a success or error status message next to the button.
         *
         * @param {Event} e - Click event from the `#aips-test-connection` button.
         */
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

        /**
         * Switch to the tab identified by the clicked `.nav-tab`'s `data-tab`
         * attribute.
         *
         * Updates `window.location.hash`, toggles `.nav-tab-active` and ARIA
         * attributes on the tab links, and shows/hides the corresponding
         * `.aips-tab-content` panel.
         *
         * @param {Event} e - Click event from a `.nav-tab` element.
         */
        switchTab: function(e) {
            e.preventDefault();
            var tabId = $(this).data('tab');

            // Update the URL hash instead of query parameter
            window.location.hash = '#' + tabId;

            // Update nav-tab states and accessibility
            $('.nav-tab')
                .removeClass('nav-tab-active')
                .attr('aria-selected', 'false')
                .attr('tabindex', '-1');
            $(this)
                .addClass('nav-tab-active')
                .attr('aria-selected', 'true')
                .attr('tabindex', '0')
                .focus();

            // Update tab content visibility and ARIA attributes
            $('.aips-tab-content')
                .hide()
                .attr('hidden', 'hidden')
                .attr('aria-hidden', 'true');
            $('#' + tabId + '-tab')
                .show()
                .removeAttr('hidden')
                .attr('aria-hidden', 'false');
        },
        
        /**
         * Switch to an AIPS sub-tab identified by the clicked `.aips-tab-link`'s
         * `data-tab` attribute.
         *
         * Toggles the `.active` class on the tab links, shows the matching
         * `.aips-tab-content` panel, and fires the custom `aips:tabSwitch` event
         * on `document` so other modules can react.
         *
         * @param {Event} e - Click event from an `.aips-tab-link` element.
         */
        switchAipsTab: function(e) {
            e.preventDefault();
            var tabId = $(e.currentTarget).data('tab');

            // Update active state on all .aips-tab-link elements
            $('.aips-tab-link').removeClass('active');
            $(e.currentTarget).addClass('active');

            // Show the corresponding tab content
            $('.aips-tab-content').hide();
            $('#' + tabId + '-tab').show();

            // Notify other modules of the tab switch.
            // Passes tabId (string) as the first argument: $(document).on('aips:tabSwitch', function(e, tabId) { ... })
            $(document).trigger('aips:tabSwitch', [tabId]);
        },

        /**
         * Append the current URL hash to a form's `action` attribute before
         * submission so that the active tab is preserved after the page reloads.
         *
         * Bound to the `submit` event on `.aips-post-review-filters` and
         * `form[action*="aips-generated-posts"]`.
         *
         * @param {Event} e - Submit event from the form element.
         */
        preserveTabOnSubmit: function(e) {
            // Append current hash to form action to preserve active tab
            var hash = window.location.hash;
            if (hash) {
                var $form = $(this);
                var action = $form.attr('action') || window.location.pathname + window.location.search;
                
                // Remove existing hash if present
                action = action.split('#')[0];
                
                // Add the hash to the action
                $form.attr('action', action + hash);
            }
        },

        /**
         * Reset and open the template modal in "Add New" mode.
         *
         * Clears the form, resets the media selection and AI variables panel,
         * initialises the wizard to step 1, and displays the modal.
         *
         * @param {Event} e - Click event from an `.aips-add-template-btn` element.
         */
        openTemplateModal: function(e) {
            e.preventDefault();
            $('#aips-template-form')[0].reset();
            $('#template_id').val('');
            $('#aips-modal-title').text('Add New Template');
            $('#featured_image_source').val('ai_prompt');
            $('#featured_image_unsplash_keywords').val('');
            AIPS.setMediaSelection([]);
            AIPS.toggleImagePrompt();
            // Reset AI Variables panel
            AIPS.updateAIVariablesPanel([]);
            // Initialize wizard to step 1
            AIPS.wizardGoToStep(1);
            $('#aips-template-modal').show();
        },

        /**
         * Fetch a template's data via AJAX and open the modal in "Edit" mode.
         *
         * Reads the template ID from the clicked element's `data-id` attribute,
         * then populates every form field (including media selection and voice)
         * before showing the modal at wizard step 1.
         *
         * @param {Event} e - Click event from an `.aips-edit-template` element.
         */
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
                        $('#template_description').val(t.description || '');
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
                        // Scan for AI Variables after loading template data
                        AIPS.initAIVariablesScanner();
                        $('#aips-modal-title').text('Edit Template');
                        // Initialize wizard to step 1
                        AIPS.wizardGoToStep(1);
                        $('#aips-template-modal').show();
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Duplicate an existing template via AJAX after user confirmation.
         *
         * Shows a confirmation dialog, then sends the `aips_clone_template`
         * AJAX action. Reloads the page on success.
         *
         * @param {Event} e - Click event from an `.aips-clone-template` element.
         */
        cloneTemplate: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');

            AIPS.Utilities.confirm('Are you sure you want to clone this template?', 'Confirm', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, clone', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    $btn.prop('disabled', true).text('Cloning...');

                    $.ajax({
                        url: aipsAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aips_clone_template',
                            nonce: aipsAjax.nonce,
                            template_id: id
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                AIPS.Utilities.showToast(response.data.message, 'error');
                                $btn.prop('disabled', false).text('Clone');
                            }
                        },
                        error: function() {
                            AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                            $btn.prop('disabled', false).text('Clone');
                        }
                    });
                }}
            ]);
        },

        /**
         * Delete a template using a two-click soft-confirm pattern.
         *
         * The first click changes the button label to "Click again to confirm"
         * and sets a 3-second auto-reset timer. The second click (within the
         * window) sends the `aips_delete_template` AJAX action and removes the
         * table row on success.
         *
         * @param {Event} e - Click event from an `.aips-delete-template` element.
         */
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
                        AIPS.Utilities.showToast(response.data.message, 'error');
                        // Reset button state on error
                        $btn.text($btn.data('original-text'));
                        $btn.removeClass('aips-confirm-delete');
                        $btn.data('is-confirming', false);
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                    // Reset button state on error
                    $btn.text($btn.data('original-text'));
                    $btn.removeClass('aips-confirm-delete');
                    $btn.data('is-confirming', false);
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Validate and save the template form via AJAX.
         *
         * Runs HTML5 form validation before submitting. On success, stores the
         * returned template ID and switches the wizard to the post-save actions
         * panel via `showPostSaveActions()`.
         *
         * @param {Event} e - Click event from an `.aips-save-template` element.
         */
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
                    description: $('#template_description').val(),
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
                        var savedId = response.data.template_id;
                        AIPS.lastSavedTemplateId = savedId;
                        AIPS.showPostSaveActions(savedId);
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Template');
                }
            });
        },

        /**
         * Save the current template form as an inactive draft.
         *
         * Requires at least the template name to be filled in. Sends the
         * `aips_save_template` AJAX action with `is_active=0` and reloads the
         * page on success.
         *
         * @param {Event} e - Click event from an `.aips-save-draft-template` element.
         */
        saveDraftTemplate: function(e) {
            e.preventDefault();
            var $btn = $(this);
            
            // Validate at least name is provided
            if (!$('#template_name').val().trim()) {
                AIPS.Utilities.showToast(aipsAdminL10n.templateNameRequired, 'warning');
                $('#template_name').focus();
                AIPS.wizardGoToStep(1);
                return;
            }

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-cloud-saved"></span> Saving...');

            // Save with is_active set to 0 (inactive)
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_save_template',
                    nonce: aipsAjax.nonce,
                    template_id: $('#template_id').val(),
                    name: $('#template_name').val(),
                    description: $('#template_description').val(),
                    prompt_template: $('#prompt_template').val() || '',
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
                    is_active: 0 // Save as inactive draft
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-cloud-saved"></span> Save Draft');
                }
            });
        },

        /**
         * Run a one-off test generation using the current template form values.
         *
         * Requires a non-empty content prompt. Sends the `aips_test_template`
         * AJAX action with `post_quantity` forced to 1, then displays the
         * generated title, excerpt, content, and optional image prompt in the
         * test-result modal.
         *
         * @param {Event} e - Click event from an `.aips-test-template` element.
         */
        testTemplate: function(e) {
            e.preventDefault();
            
            // Validate at least prompt is there
            if (!$('#prompt_template').val().trim()) {
                AIPS.Utilities.showToast(aipsAdminL10n.contentPromptRequired || 'Please enter a content prompt first.', 'warning');
                $('#prompt_template').focus();
                return;
            }

            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> Generating...');

            // Gather all form data
            var data = {
                action: 'aips_test_template',
                nonce: aipsAjax.nonce,
                template_id: $('#template_id').val(),
                name: $('#template_name').val(),
                description: $('#template_description').val(),
                prompt_template: $('#prompt_template').val(),
                title_prompt: $('#title_prompt').val(),
                voice_id: $('#voice_id').val(),
                post_quantity: 1, // Force 1 for test
                generate_featured_image: $('#generate_featured_image').is(':checked') ? 1 : 0,
                image_prompt: $('#image_prompt').val(),
                featured_image_source: $('#featured_image_source').val(),
                featured_image_unsplash_keywords: $('#featured_image_unsplash_keywords').val(),
                featured_image_media_ids: $('#featured_image_media_ids').val(),
                post_status: $('#post_status').val(),
                post_category: $('#post_category').val(),
                post_tags: $('#post_tags').val(),
                post_author: $('#post_author').val(),
            };

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        var result = response.data.result;

                        // Populate modal
                        $('#aips-test-title').text(result.title || '-');
                        $('#aips-test-excerpt').text(result.excerpt || '-');
                        $('#aips-test-content').text(result.content || '-');

                        if (result.image_prompt) {
                            $('#aips-test-image-row').show();
                            $('#aips-test-image').text(result.image_prompt);
                        } else {
                            $('#aips-test-image-row').hide();
                        }

                        $('#aips-test-result-modal').show();
                    } else {
                        AIPS.Utilities.showToast(response.data.message || 'Generation failed.', 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        /**
         * Immediately generate a post from a template without waiting for its
         * scheduled run.
         *
         * Sends the `aips_run_now` AJAX action with the template ID taken from
         * the clicked element's `data-id` attribute. Displays the success modal
         * with an optional edit link on success.
         *
         * @param {Event} e - Click event from an `.aips-run-now` element.
         */
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
                        // Show success modal instead of alert
                        if (response.data.edit_url) {
                            $('#aips-post-link').attr('href', response.data.edit_url);
                            $('#aips-post-link-container').show();
                        } else {
                            $('#aips-post-link-container').hide();
                        }
                        $('#aips-post-success-modal').show();
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Run Now');
                }
            });
        },

        /**
         * Search for voices matching the current input value and repopulate the
         * `#voice_id` select element.
         *
         * Sends the `aips_search_voices` AJAX action. The currently selected
         * value is preserved across the repopulation.
         *
         * Bound to the `keyup` event on `#voice_search`.
         */
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

        /**
         * Reset and open the voice modal in "Add New" mode.
         *
         * Clears the voice form, empties the hidden ID field, sets the modal
         * title to "Add New Voice", and displays the modal.
         *
         * @param {Event} e - Click event from an `.aips-add-voice-btn` element.
         */
        openVoiceModal: function(e) {
            e.preventDefault();
            $('#aips-voice-form')[0].reset();
            $('#voice_id').val('');
            $('#aips-voice-modal-title').text('Add New Voice');
            $('#aips-voice-modal').show();
        },

        /**
         * Fetch a voice's data via AJAX and open the voice modal in "Edit" mode.
         *
         * Reads the voice ID from the clicked element's `data-id` attribute,
         * then populates every voice form field before showing the modal.
         *
         * @param {Event} e - Click event from an `.aips-edit-voice` element.
         */
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

        /**
         * Confirm and permanently delete a voice via AJAX.
         *
         * Shows a confirmation dialog. On confirmation, sends the
         * `aips_delete_voice` AJAX action and fades out the table row on success.
         *
         * @param {Event} e - Click event from an `.aips-delete-voice` element.
         */
        deleteVoice: function(e) {
            e.preventDefault();
            var $el = $(this);
            var id = $el.data('id');
            var $row = $el.closest('tr');
            AIPS.Utilities.confirm('Are you sure you want to delete this voice?', 'Confirm', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                                AIPS.Utilities.showToast(response.data.message, 'error');
                            }
                        }
                    });
                }}
            ]);
        },

        /**
         * Validate and save the voice form via AJAX.
         *
         * Runs HTML5 form validation before submitting. Sends the
         * `aips_save_voice` AJAX action and reloads the page on success.
         *
         * @param {Event} e - Click event from an `.aips-save-voice` element.
         */
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
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Voice');
                }
            });
        },

        /**
         * Reset and open the schedule modal in "Add New" mode.
         *
         * Clears the schedule form, empties the hidden ID field, sets the
         * modal title to "Add New Schedule", and displays the modal.
         *
         * @param {Event} e - Click event from an `.aips-add-schedule-btn` element.
         */
        openScheduleModal: function(e) {
            e.preventDefault();
            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val('');
            $('#aips-schedule-modal-title').text('Add New Schedule');
            $('#aips-schedule-modal').show();
        },

        /**
         * Opens the schedule modal pre-filled with the existing schedule's data
         * so the user can modify it in-place without deleting and recreating.
         *
         * @param {Event} e - Click event from the edit button.
         */
        editSchedule: function(e) {
            e.preventDefault();

            var $row = $(this).closest('tr');
            var scheduleId = $row.data('schedule-id');
            var templateId = $row.data('template-id');
            var frequency = $row.data('frequency');
            var topic = $row.data('topic');
            var articleStructureId = $row.data('article-structure-id');
            var rotationPattern = $row.data('rotation-pattern');
            var nextRun = $row.data('next-run');
            var isActive = $row.data('is-active');

            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val(scheduleId);
            $('#schedule_template').val(templateId);
            $('#schedule_frequency').val(frequency);
            $('#schedule_topic').val(topic || '');
            $('#article_structure_id').val(articleStructureId || '');
            $('#rotation_pattern').val(rotationPattern || '');
            $('#schedule_is_active').prop('checked', isActive == 1);

            if (nextRun) {
                var dt = new Date(nextRun);
                if (!isNaN(dt.getTime())) {
                    var pad = function(n) { return n < 10 ? '0' + n : n; };
                    var localValue = dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()) +
                        'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
                    $('#schedule_start_time').val(localValue);
                }
            }

            $('#aips-schedule-modal-title').text('Edit Schedule');
            $('#aips-schedule-modal').show();
        },

        /**
         * Copy an existing schedule's settings into the "Add New" modal.
         *
         * Reads all schedule data from the row's `data-*` attributes, populates
         * the form fields (leaving `schedule_id` and `start_time` blank so a
         * new schedule is created), and shows the modal titled "Clone Schedule".
         *
         * @param {Event} e - Click event from an `.aips-clone-schedule` element.
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
         * Validate and save the schedule form via AJAX.
         *
         * Runs HTML5 form validation before submitting. Sends the
         * `aips_save_schedule` AJAX action and reloads the page on success.
         *
         * @param {Event} e - Click event from an `.aips-save-schedule` element.
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
                    topic: $('#schedule_topic').val(),
                    article_structure_id: $('#article_structure_id').val(),
                    rotation_pattern: $('#rotation_pattern').val(),
                    is_active: $('#schedule_is_active').is(':checked') ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Schedule');
                }
            });
        },

        /**
         * Confirm and permanently delete a schedule via AJAX.
         *
         * Shows a confirmation dialog. On confirmation, sends the
         * `aips_delete_schedule` AJAX action and fades out the table row on
         * success.
         *
         * @param {Event} e - Click event from an `.aips-delete-schedule` element.
         */
        deleteSchedule: function(e) {
            e.preventDefault();

            var $el = $(this);
            var id = $el.data('id');
            var $row = $el.closest('tr');

            AIPS.Utilities.confirm('Are you sure you want to delete this schedule?', 'Notice', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                                AIPS.Utilities.showToast(response.data.message, 'error');
                            }
                        },
                        error: function() {
                            AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                        }
                    });
                }}
            ]);
        },

        /**
         * Triggers immediate execution of a specific schedule via its schedule_id,
         * showing a progress-bar modal with a "Generating X of Y posts" counter.
         *
         * First fetches the expected post count for the schedule, then opens the
         * progress-bar modal and fires the run-now AJAX request.  The description
         * line inside the modal is updated at regular intervals to simulate
         * per-post progress ("Generating 2 of 3 posts", etc.).
         *
         * @param {Event} e - Click event from the Run Now button.
         */
        runNowSchedule: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var scheduleId = $btn.data('id');

            if (!scheduleId) {
                return;
            }

            var DEFAULT_PER_POST_SECONDS  = 30;
            var MIN_PROGRESS_SECONDS      = 10;
            var PROGRESS_MODAL_CLOSE_DELAY = 1400; // ms to wait after complete() before toast

            $btn.prop('disabled', true);
            $btn.find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update aips-spin');

            // Step 1: Fetch the expected post count for this schedule so we can
            // display an accurate "Generating X of Y posts" message.
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_schedules_post_count',
                    nonce: aipsAjax.nonce,
                    ids: [scheduleId]
                },
                complete: function(countXhr) {
                    var postCount = 1;
                    try {
                        var countResp = countXhr.responseJSON;
                        if (countResp && countResp.success && countResp.data && countResp.data.count > 0) {
                            postCount = parseInt(countResp.data.count, 10) || 1;
                        }
                    } catch (ignore) {}

                    var totalSeconds = Math.max(DEFAULT_PER_POST_SECONDS * postCount, MIN_PROGRESS_SECONDS);
                    var postOfTpl    = aipsAdminL10n.generatingPostsOf || 'Generating %1$d of %2$d posts';

                    var initialMsg = postOfTpl
                        .replace('%1$d', 1)
                        .replace('%2$d', postCount);

                    // Step 2: Open the progress-bar modal.
                    var progressBar = AIPS.Utilities.showProgressBar({
                        title:        aipsAdminL10n.generatingPostsTitle || 'Generating Posts',
                        message:      initialMsg,
                        totalSeconds: totalSeconds
                    });

                    // Step 3: Simulate per-post counter updates based on elapsed time.
                    var startTime       = Date.now();
                    var shownPost       = 1;
                    var secPerPost      = totalSeconds / postCount;
                    var counterInterval = setInterval(function() {
                        if (shownPost >= postCount) {
                            clearInterval(counterInterval);
                            return;
                        }
                        var elapsed       = (Date.now() - startTime) / 1000;
                        var estimatedPost = Math.min(Math.floor(elapsed / secPerPost) + 1, postCount);
                        if (estimatedPost > shownPost) {
                            shownPost = estimatedPost;
                            progressBar.setMessage(
                                postOfTpl
                                    .replace('%1$d', shownPost)
                                    .replace('%2$d', postCount)
                            );
                        }
                    }, 500);

                    // Step 4: Fire the run-now request.
                    $.ajax({
                        url: aipsAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aips_run_now',
                            nonce: aipsAjax.nonce,
                            schedule_id: scheduleId
                        },
                        success: function(response) {
                            clearInterval(counterInterval);

                            // Snap the counter to the final value before completing.
                            progressBar.setMessage(
                                postOfTpl
                                    .replace('%1$d', postCount)
                                    .replace('%2$d', postCount)
                            );

                            if (response.success) {
                                var completionMsg = response.data.message || 'Schedule executed successfully!';
                                progressBar.complete(completionMsg, 'success');

                                // Show a toast with an optional edit link after the modal closes.
                                setTimeout(function() {
                                    var toastMsg = AIPS.escapeHtml(completionMsg);
                                    if (response.data.edit_url) {
                                        toastMsg += ' <a href="' + AIPS.escapeAttribute(response.data.edit_url) + '" target="_blank">Edit Post</a>';
                                    }
                                    AIPS.Utilities.showToast(toastMsg, 'success', { isHtml: true, duration: 8000 });
                                }, PROGRESS_MODAL_CLOSE_DELAY);
                            } else {
                                var errMsg = response.data.message || 'Generation failed.';
                                progressBar.complete(errMsg, 'error');
                                setTimeout(function() {
                                    AIPS.Utilities.showToast(errMsg, 'error');
                                }, PROGRESS_MODAL_CLOSE_DELAY);
                            }
                        },
                        error: function() {
                            clearInterval(counterInterval);
                            var errMsg = 'An error occurred. Please try again.';
                            progressBar.complete(errMsg, 'error');
                            setTimeout(function() {
                                AIPS.Utilities.showToast(errMsg, 'error');
                            }, PROGRESS_MODAL_CLOSE_DELAY);
                        },
                        complete: function() {
                            // Ensure the interval is stopped regardless of success/error outcome.
                            clearInterval(counterInterval);
                            $btn.prop('disabled', false);
                            $btn.find('.dashicons').removeClass('dashicons-update aips-spin').addClass('dashicons-controls-play');
                        }
                    });
                }
            });
        },

        /**
         * Toggle a schedule's active/inactive status via AJAX.
         *
         * Reads the schedule ID and the new checked state from the toggle
         * checkbox, then updates the adjacent status badge and icon to reflect
         * the server-confirmed state. Reverts the checkbox on AJAX error.
         *
         * Bound to the `change` event on `.aips-toggle-schedule`.
         */
        toggleSchedule: function() {
            var $toggle = $(this);
            var id = $toggle.data('id');
            var isActive = $toggle.is(':checked') ? 1 : 0;
            var $wrapper = $toggle.closest('.aips-schedule-status-wrapper');
            var $badge = $wrapper.find('.aips-badge');
            var $icon = $badge.find('.dashicons');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_toggle_schedule',
                    nonce: aipsAjax.nonce,
                    schedule_id: id,
                    is_active: isActive
                },
                success: function() {
                    $badge.removeClass('aips-badge-success aips-badge-neutral aips-badge-error');
                    $icon.removeClass('dashicons-yes-alt dashicons-minus dashicons-warning');

                    // Remove all text nodes (avoids picking wrong node when whitespace creates multiple)
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

                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                }
            });
        },

        /**
         * Sync all individual schedule checkboxes with the "select all" state.
         *
         * Reads the checked state of `#cb-select-all-schedules` and applies it
         * to every `.aips-schedule-checkbox`, then updates bulk-action controls.
         *
         * Bound to the `change` event on `#cb-select-all-schedules`.
         */
        toggleAllSchedules: function() {
            var isChecked = $(this).prop('checked');
            $('.aips-schedule-checkbox').prop('checked', isChecked);
            AIPS.updateScheduleBulkActions();
        },

        /**
         * Keep the "select all" checkbox in sync with individual row selections.
         *
         * Checks whether every `.aips-schedule-checkbox` is checked and updates
         * `#cb-select-all-schedules` accordingly, then refreshes bulk-action
         * controls.
         *
         * Bound to the `change` event on `.aips-schedule-checkbox`.
         */
        toggleScheduleSelection: function() {
            var total = $('.aips-schedule-checkbox').length;
            var checked = $('.aips-schedule-checkbox:checked').length;
            $('#cb-select-all-schedules').prop('checked', total > 0 && checked === total);
            AIPS.updateScheduleBulkActions();
        },

        /**
         * Check every schedule row checkbox and update bulk-action controls.
         *
         * Sets all `.aips-schedule-checkbox` and `#cb-select-all-schedules` to
         * checked, then calls `updateScheduleBulkActions`.
         */
        selectAllSchedules: function() {
            $('.aips-schedule-checkbox').prop('checked', true);
            $('#cb-select-all-schedules').prop('checked', true);
            AIPS.updateScheduleBulkActions();
        },

        /**
         * Uncheck every schedule row checkbox and update bulk-action controls.
         *
         * Sets all `.aips-schedule-checkbox` and `#cb-select-all-schedules` to
         * unchecked, then calls `updateScheduleBulkActions`.
         */
        unselectAllSchedules: function() {
            $('.aips-schedule-checkbox').prop('checked', false);
            $('#cb-select-all-schedules').prop('checked', false);
            AIPS.updateScheduleBulkActions();
        },

        /**
         * Update the schedule bulk-action toolbar to reflect the current
         * selection count.
         *
         * Enables or disables the Apply and Unselect-All buttons, and shows or
         * hides the "N selected" label based on the number of checked rows.
         */
        updateScheduleBulkActions: function() {
            var count = $('.aips-schedule-checkbox:checked').length;
            var $applyBtn = $('#aips-schedule-bulk-apply');
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
         * For `run_now`, the estimated post count is fetched via AJAX before
         * the confirm to give the user an accurate preview.
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
                AIPS.Utilities.showToast('Please select at least one schedule.', 'warning');
                return;
            }

            if (action === 'delete') {
                var deleteMsg = ids.length === 1
                    ? 'Are you sure you want to delete 1 schedule?'
                    : 'Are you sure you want to delete ' + ids.length + ' schedules?';
                AIPS.Utilities.confirm(
                    deleteMsg,
                    'Delete Schedules',
                    [
                        { label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
                        { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() { AIPS.bulkDeleteSchedules(ids); } }
                    ]
                );
            } else if (action === 'pause') {
                AIPS.bulkToggleSchedules(ids, 0);
            } else if (action === 'activate') {
                AIPS.bulkToggleSchedules(ids, 1);
            } else if (action === 'run_now') {
                // Fetch estimated post count then confirm
                $.ajax({
                    url: aipsAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aips_get_schedules_post_count',
                        nonce: aipsAjax.nonce,
                        ids: ids
                    },
                    success: function(response) {
                        var count = response.success ? (response.data.count || ids.length) : ids.length;
                        var runMsg = 'This will generate an estimated ' + count + ' post' + (count !== 1 ? 's' : '') + '. Are you sure?';
                        AIPS.Utilities.confirm(
                            runMsg,
                            'Run Schedules Now',
                            [
                                { label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
                                { label: 'Yes, run now', className: 'aips-btn aips-btn-primary', action: function() { AIPS.bulkRunNowSchedules(ids); } }
                            ]
                        );
                    },
                    error: function() {
                        var runMsg = 'This will run ' + ids.length + ' schedule' + (ids.length !== 1 ? 's' : '') + '. Are you sure?';
                        AIPS.Utilities.confirm(
                            runMsg,
                            'Run Schedules Now',
                            [
                                { label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
                                { label: 'Yes, run now', className: 'aips-btn aips-btn-primary', action: function() { AIPS.bulkRunNowSchedules(ids); } }
                            ]
                        );
                    }
                });
            }
        },

        /**
         * Delete multiple schedules at once via the `aips_bulk_delete_schedules`
         * AJAX action.
         *
         * On success, fades out each affected table row, unchecks the "select
         * all" checkbox, and refreshes the bulk-action toolbar.
         *
         * @param {Array<string>} ids - Array of schedule ID strings to delete.
         */
        bulkDeleteSchedules: function(ids) {
            var $applyBtn = $('#aips-schedule-bulk-apply');
            $applyBtn.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_delete_schedules',
                    nonce: aipsAjax.nonce,
                    ids: ids
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
                        AIPS.updateScheduleBulkActions();
                    } else {
                        AIPS.Utilities.showToast(response.data.message || 'Failed to delete schedules.', 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $applyBtn.text('Apply');
                    AIPS.updateScheduleBulkActions();
                }
            });
        },

        /**
         * Activate or pause multiple schedules at once via the
         * `aips_bulk_toggle_schedules` AJAX action.
         *
         * On success, updates the toggle checkbox and status badge for each
         * affected row to reflect the new state.
         *
         * @param {Array<string>} ids      - Array of schedule ID strings to update.
         * @param {number}        isActive - `1` to activate, `0` to pause.
         */
        bulkToggleSchedules: function(ids, isActive) {
            var $applyBtn = $('#aips-schedule-bulk-apply');
            $applyBtn.prop('disabled', true).text(isActive ? 'Activating...' : 'Pausing...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_toggle_schedules',
                    nonce: aipsAjax.nonce,
                    ids: ids,
                    is_active: isActive
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success');
                        ids.forEach(function(id) {
                            var $row = $('tr[data-schedule-id="' + id + '"]');
                            var $toggle = $row.find('.aips-toggle-schedule');
                            var $wrapper = $row.find('.aips-schedule-status-wrapper');
                            var $badge = $wrapper.find('.aips-badge');
                            var $icon = $badge.find('.dashicons');

                            $toggle.prop('checked', isActive === 1);
                            $badge.removeClass('aips-badge-success aips-badge-neutral aips-badge-error');
                            $icon.removeClass('dashicons-yes-alt dashicons-minus dashicons-warning');
                            // nodeType === 3 = TEXT_NODE; removes leftover status text without touching child elements
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
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $applyBtn.text('Apply');
                    AIPS.updateScheduleBulkActions();
                }
            });
        },

        /**
         * Immediately run multiple schedules at once via the
         * `aips_bulk_run_now_schedules` AJAX action.
         *
         * Shows a persistent success toast with a longer duration on success to
         * give the user time to read the result.
         *
         * @param {Array<string>} ids - Array of schedule ID strings to run.
         */
        bulkRunNowSchedules: function(ids) {
            var $applyBtn = $('#aips-schedule-bulk-apply');
            $applyBtn.prop('disabled', true).text('Running...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_run_now_schedules',
                    nonce: aipsAjax.nonce,
                    ids: ids
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success', { duration: 8000 });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || 'Bulk run failed.', 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $applyBtn.text('Apply');
                    AIPS.updateScheduleBulkActions();
                }
            });
        },

        /**
         * Confirm and clear history entries, optionally filtered by status.
         *
         * Reads the optional `data-status` attribute from the clicked button to
         * scope the clear to a specific status (e.g. "failed"). Shows a
         * confirmation dialog, then sends the `aips_clear_history` AJAX action
         * and reloads the page on success.
         *
         * @param {Event} e - Click event from an `.aips-clear-history` element.
         */
        clearHistory: function(e) {
            e.preventDefault();

            var status = $(this).data('status');
            var message = status ? 'Are you sure you want to clear all ' + status + ' history?' : 'Are you sure you want to clear all history?';

            AIPS.Utilities.confirm(message, 'Notice', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, clear', className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                                AIPS.Utilities.showToast(response.data.message, 'error');
                            }
                        },
                        error: function() {
                            AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                        }
                    });
                }}
            ]);
        },

        /**
         * Retry a failed history entry via the `aips_retry_generation` AJAX
         * action.
         *
         * Reads the history entry ID from the clicked element's `data-id`
         * attribute. Shows a success toast and reloads the page on success.
         *
         * @param {Event} e - Click event from an `.aips-retry-generation` element.
         */
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
                        AIPS.Utilities.showToast(response.data.message, 'success');

                        location.reload();
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Retry');
                }
            });
        },

        /**
         * Apply the current status filter and search term to the history list.
         *
         * Reads `#aips-filter-status` and `#aips-history-search-input`, updates
         * the browser URL via `history.pushState` (without reloading), then
         * calls `reloadHistory` to fetch the filtered results via AJAX.
         *
         * Bound to the `click` event on `#aips-filter-btn` and
         * `#aips-history-search-btn`.
         *
         * @param {Event} e - Click event from the filter or search button.
         */
        filterHistory: function(e) {
            e.preventDefault();

            var status = $('#aips-filter-status').val();
            var search = $('#aips-history-search-input').val();

            // Update URL without reloading
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
            // If we are in the main history page, don't set tab param unless needed.
            // But if we are in a tab, we might need it.
            // The existing logic enforced tab=history.
            // However, simply reloading via AJAX is better.
            
            window.history.pushState({path: url.toString()}, '', url.toString());

            AIPS.reloadHistory(e, 1);
        },

        /**
         * Export the current history view as a downloadable file.
         *
         * Builds a hidden `<form>` with the current status filter and search
         * term, submits it as a POST to the `aips_export_history` AJAX action,
         * and immediately removes the form. The server responds with file
         * download headers.
         *
         * @param {Event} e - Click event from `#aips-export-history-btn`.
         */
        exportHistory: function(e) {
            e.preventDefault();

            var status = $('#aips-filter-status').val();
            var search = $('#aips-history-search-input').val();
            
            // Create a form and submit it with POST
            var form = $('<form>', {
                'method': 'POST',
                'action': aipsAjax.ajaxUrl,
                'target': '_self'
            });
            
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'action',
                'value': 'aips_export_history'
            }));
            
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'nonce',
                'value': aipsAjax.nonce
            }));
            
            if (status) {
                form.append($('<input>', {
                    'type': 'hidden',
                    'name': 'status',
                    'value': status
                }));
            }
            
            if (search) {
                form.append($('<input>', {
                    'type': 'hidden',
                    'name': 'search',
                    'value': search
                }));
            }
            
            // Append form to body, submit, and remove
            form.appendTo('body').submit().remove();
        },

        /**
         * Load a specific page of the history list.
         *
         * Reads the target page number from the clicked element's `data-page`
         * attribute. Ignores disabled buttons and missing page values, then
         * delegates to `reloadHistory`.
         *
         * Bound to the `click` event on `.aips-history-page-link`,
         * `.aips-history-page-prev`, and `.aips-history-page-next`.
         *
         * @param {Event} e - Click event from a history page-navigation element.
         */
        loadHistoryPage: function(e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);

            if ($btn.prop('disabled')) {
                return;
            }

            var page = $btn.data('page');

            if (!page) {
                return;
            }

            AIPS.reloadHistory(e, parseInt(page, 10));
        },

        /**
         * Fetch and render a page of history entries via AJAX.
         *
         * Sends the current status filter, search term, and page number to the
         * `aips_reload_history` action. On success, updates the table body,
         * pagination, and stats widgets without a full page reload. Shows a
         * spinner on the Reload button when triggered from that button.
         *
         * @param {Event|null} e     - The triggering event, or `null` when called
         *                             programmatically.
         * @param {number}     paged - 1-based page number to load. Defaults to 1.
         */
        reloadHistory: function(e, paged) {
            if (e) {
                e.preventDefault();
            }

            var status = $('#aips-filter-status').val();
            var search = $('#aips-history-search-input').val();
            var $btn = $('#aips-reload-history-btn');            
            var isReloadBtn = $btn.length && e && $(e.currentTarget).is('#aips-reload-history-btn');
            var originalHtml;

            paged = (paged === undefined || paged === null) ? 1 : Math.max(1, parseInt(paged, 10));

            if (isReloadBtn) {
                originalHtml = $btn.html();

                $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> Reloading...');
            } else {
                $('.aips-history-table').css('opacity', '0.5');
            }

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'aips_reload_history',
                    nonce: aipsAjax.nonce,
                    status: status,
                    search: search,
                    paged: paged
                },
                success: function(response) {
                    if (!response.success) {
                        AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : 'Failed to reload history.', 'error');

                        return;
                    }

                    // Update table body
                    var $tbody = $('.aips-history-table tbody');

                    if ($tbody.length) {
                        $tbody.html(response.data.items_html || '');
                    } else if ($('.aips-empty-state').length && response.data.items_html) {
                        // If we were in empty state but now have items (e.g. after clear filter), we might need to reconstruct the table structure
                        // But simplification: reload page if structure missing is easier.
                        // For now assume table exists or items_html is empty.
                        // Ideally we should replace the whole content panel body if switching between empty and list.
                         location.reload();

                         return;
                    }

                    // Update pagination in tfoot
                    if (response.data.pagination_html) {
                        var $cell = $('.aips-history-pagination-cell');

                        if ($cell.length) {
                            $cell.html(response.data.pagination_html);
                        }
                    }

                    // Update stats
                    if (response.data.stats) {
                        $('#aips-stat-total').text(response.data.stats.total);
                        $('#aips-stat-completed').text(response.data.stats.completed);
                        $('#aips-stat-failed').text(response.data.stats.failed);
                        $('#aips-stat-success-rate').text(response.data.stats.success_rate + '%');
                    }

                    // Update URL without reload
                    var url = new URL(window.location.href);

                    if (paged > 1) {
                        url.searchParams.set('paged', paged);
                    } else {
                        url.searchParams.delete('paged');
                    }

                    window.history.replaceState({}, '', url.toString());

                    // Reset bulk selection state
                    $('#cb-select-all-1').prop('checked', false);

                    AIPS.updateDeleteButton();
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred while reloading history.', 'error');
                },
                complete: function() {
                    if (isReloadBtn && $btn.length) {
                        $btn.prop('disabled', false).html(originalHtml || '<span class="dashicons dashicons-update"></span> Reload');
                    }

                    $('.aips-history-table').css('opacity', '1');
                }
            });
        },

        /**
         * Submit the history filter when the Enter key is pressed inside the
         * search input.
         *
         * Delegates to `filterHistory` when `e.which === 13`.
         *
         * Bound to the `keypress` event on `#aips-history-search-input`.
         *
         * @param {Event} e - Keypress event.
         */
        handleHistorySearchKeypress: function(e) {
            if (e.which == 13) {
                AIPS.filterHistory(e);
            }
        },

        /**
         * Handle a click on a legacy `#aips-history-pagination` anchor tag.
         *
         * Extracts the `paged` query parameter from the anchor's `href` and
         * delegates to `reloadHistory` with that page number.
         *
         * Bound to the `click` event on `#aips-history-pagination a`.
         *
         * @param {Event} e - Click event from a pagination anchor.
         */
        handleHistoryPaginationClick: function(e) {
            e.preventDefault();
            var href = $(this).attr('href');
            var match = href.match(/paged=(\d+)/);
            var page = match ? parseInt(match[1]) : 1;
            AIPS.reloadHistory(e, page);
        },

        /**
         * Show or hide the featured-image settings block based on the state of
         * the `#generate_featured_image` checkbox.
         *
         * When the checkbox is checked, also calls
         * `toggleFeaturedImageSourceFields` to ensure the correct sub-field
         * panel is visible.
         *
         * Bound to the `change` event on `#generate_featured_image`.
         *
         * @param {Event} [e] - Change event (optional when called programmatically).
         */
        toggleImagePrompt: function(e) {
            var isChecked = $('#generate_featured_image').is(':checked');

            $('.aips-featured-image-settings').toggle(isChecked);
            $('#featured_image_source').prop('disabled', !isChecked);

            if (isChecked) {
                AIPS.toggleFeaturedImageSourceFields();
            }
        },

        /**
         * Show the correct featured-image source sub-panel based on the current
         * value of `#featured_image_source`.
         *
         * Hides all `.aips-image-source` panels, then shows only the one that
         * matches the selected source (`ai_prompt`, `unsplash`, or
         * `media_library`).
         *
         * Bound to the `change` event on `#featured_image_source`.
         */
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

        /**
         * Store the given media attachment IDs in the hidden field and update
         * the preview text.
         *
         * Accepts either an array of IDs or a comma-separated string. Filters
         * out empty values before storing. Updates `#featured_image_media_ids`
         * and `#featured_image_media_preview`.
         *
         * @param {Array<number|string>|string} ids - Attachment IDs to select.
         */
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

        /**
         * Open the WordPress media library frame and update the featured-image
         * selection when the user confirms their choice.
         *
         * Creates the `wp.media` frame lazily on the first call and reuses it
         * on subsequent calls. On selection, passes the chosen attachment IDs
         * to `setMediaSelection`.
         *
         * @param {Event} e - Click event from `#featured_image_media_select`.
         */
        openMediaLibrary: function(e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) {
                AIPS.Utilities.showToast('Media library is not available.', 'warning');
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

        /**
         * Clear the current featured-image media selection.
         *
         * Delegates to `setMediaSelection([])` to empty the hidden field and
         * reset the preview text.
         *
         * @param {Event} [e] - Click event (optional when called programmatically).
         */
        clearMediaSelection: function(e) {
            if (e) {
                e.preventDefault();
            }
            AIPS.setMediaSelection([]);
        },

        /**
         * Filter the templates table in real time by the typed search term.
         *
         * Matches against the `.column-name` and `.column-category` cells of
         * each row. Shows a "no results" notice and hides the table when no rows
         * match a non-empty term.
         *
         * Bound to the `keyup` and `search` events on `#aips-template-search`.
         */
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

        /**
         * Clear the template search input and re-run the filter to show all rows.
         *
         * @param {Event} e - Click event from `#aips-template-search-clear` or
         *                    `.aips-clear-search-btn`.
         */
        clearTemplateSearch: function(e) {
            e.preventDefault();
            $('#aips-template-search').val('').trigger('keyup');
        },

        /**
         * Filter the schedules table in real time by the typed search term.
         *
         * Matches against the `.column-template`, `.column-structure`, and
         * `.column-frequency` cells of each row.
         *
         * Bound to the `keyup` and `search` events on `#aips-schedule-search`.
         */
        filterSchedules: function() {
            var term = $('#aips-schedule-search').val().toLowerCase().trim();
            var $rows = $('.aips-schedule-table tbody tr');
            var $noResults = $('#aips-schedule-search-no-results');
            var $table = $('.aips-schedule-table');
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
         * Clear the schedule search input and re-run the filter to show all rows.
         *
         * @param {Event} e - Click event from `#aips-schedule-search-clear` or
         *                    `.aips-clear-schedule-search-btn`.
         */
        clearScheduleSearch: function(e) {
            e.preventDefault();
            $('#aips-schedule-search').val('').trigger('keyup');
        },

        /**
         * Filter the voices table in real time by the typed search term.
         *
         * Matches against the `.column-name` cell of each row.
         *
         * Bound to the `keyup` and `search` events on `#aips-voice-search`.
         */
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

        /**
         * Clear the voice search input and re-run the filter to show all rows.
         *
         * @param {Event} e - Click event from `#aips-voice-search-clear` or
         *                    `.aips-clear-voice-search-btn`.
         */
        clearVoiceSearch: function(e) {
            e.preventDefault();
            $('#aips-voice-search').val('').trigger('keyup');
        },

        /**
         * Filter the prompt sections table in real time by the typed search term.
         *
         * Matches against the `.column-name`, `.column-key code`, and
         * `.column-description` cells of each row.
         *
         * Bound to the `keyup` and `search` events on `#aips-section-search`.
         */
        filterSections: function() {
            var term = $('#aips-section-search').val().toLowerCase().trim();
            var $rows = $('.aips-sections-list tbody tr');
            var $noResults = $('#aips-section-search-no-results');
            var $table = $('.aips-sections-list');
            var $clearBtn = $('#aips-section-search-clear');
            var hasVisible = false;

            if (term.length > 0) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }

            $rows.each(function() {
                var $row = $(this);
                var name = $row.find('.column-name').text().toLowerCase();
                var key = $row.find('.column-key code').text().toLowerCase();
                var description = $row.find('.column-description').text().toLowerCase();

                if (name.indexOf(term) > -1 || key.indexOf(term) > -1 || description.indexOf(term) > -1) {
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
         * Clear the section search input and re-run the filter to show all rows.
         *
         * @param {Event} e - Click event from `#aips-section-search-clear` or
         *                    `.aips-clear-section-search-btn`.
         */
        clearSectionSearch: function(e) {
            e.preventDefault();
            $('#aips-section-search').val('').trigger('keyup');
        },

        /**
         * Filter the article structures table in real time by the typed search
         * term.
         *
         * Matches against the `.column-name` and `.column-description` cells of
         * each row.
         *
         * Bound to the `keyup` and `search` events on `#aips-structure-search`.
         */
        filterStructures: function() {
            var term = $('#aips-structure-search').val().toLowerCase().trim();
            var $rows = $('.aips-structures-list tbody tr');
            var $noResults = $('#aips-structure-search-no-results');
            var $table = $('.aips-structures-list');
            var $clearBtn = $('#aips-structure-search-clear');
            var hasVisible = false;

            if (term.length > 0) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }

            $rows.each(function() {
                var $row = $(this);
                var name = $row.find('.column-name').text().toLowerCase();
                var description = $row.find('.column-description').text().toLowerCase();

                if (name.indexOf(term) > -1 || description.indexOf(term) > -1) {
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
         * Clear the structure search input and re-run the filter to show all rows.
         *
         * @param {Event} e - Click event from `#aips-structure-search-clear` or
         *                    `.aips-clear-structure-search-btn`.
         */
        clearStructureSearch: function(e) {
            e.preventDefault();
            $('#aips-structure-search').val('').trigger('keyup');
        },

        /**
         * Filter the authors table in real time by the typed search term.
         *
         * Matches against the `.column-name` and `.column-field` cells of each
         * row.
         *
         * Bound to the `keyup` and `search` events on `#aips-author-search`.
         */
        filterAuthors: function() {
            var term = $('#aips-author-search').val().toLowerCase().trim();
            var $rows = $('.aips-authors-table tbody tr');
            var $noResults = $('#aips-author-search-no-results');
            var $table = $('.aips-authors-table');
            var $clearBtn = $('#aips-author-search-clear');
            var hasVisible = false;

            if (term.length > 0) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }

            $rows.each(function() {
                var $row = $(this);
                var name = $row.find('.column-name').text().toLowerCase();
                var field = $row.find('.column-field').text().toLowerCase();

                if (name.indexOf(term) > -1 || field.indexOf(term) > -1) {
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
         * Clear the author search input and re-run the filter to show all rows.
         *
         * @param {Event} e - Click event from `#aips-author-search-clear` or
         *                    `.aips-clear-author-search-btn`.
         */
        clearAuthorSearch: function(e) {
            e.preventDefault();
            $('#aips-author-search').val('').trigger('keyup');
        },

        /**
         * Open the generation-details modal and fetch full detail data for a
         * history entry.
         *
         * Reads the history entry ID from the clicked element's `data-id`
         * attribute. Shows a loading indicator while the `aips_get_history_details`
         * AJAX request is in flight, then hands the response to `renderDetails`.
         *
         * @param {Event} e - Click event from an `.aips-view-details` element.
         */
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
                        AIPS.Utilities.showToast(response.data.message, 'error');
                        $('#aips-details-modal').hide();
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                    $('#aips-details-modal').hide();
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $('#aips-details-loading').hide();
                }
            });
        },

        /**
         * Populate the generation-details modal with the fetched history data.
         *
         * Builds and injects HTML for the summary table (status, title, timing,
         * error), generated prompt and content, template snapshot, voice
         * snapshot, individual AI API calls (request/response pairs), and any
         * logged errors.
         *
         * @param {Object} data - The `response.data` payload from the
         *                        `aips_get_history_details` AJAX action.
         */
        renderDetails: function(data) {
            var log = data.generation_log || {};
            
            var summaryHtml = '<table class="aips-details-table">';
            summaryHtml += '<tr><th>Status:</th><td><span class="aips-status aips-status-' + data.status + '">' + data.status.charAt(0).toUpperCase() + data.status.slice(1) + '</span></td></tr>';
            summaryHtml += '<tr><th>Title:</th><td>' + AIPS.escapeHtml(data.generated_title || '-') + '</td></tr>';
            if (data.post_id) {
                summaryHtml += '<tr><th>Post ID:</th><td>' + data.post_id + '</td></tr>';
            }
            summaryHtml += '<tr><th>Started:</th><td>' + (log.started_at || data.created_at) + '</td></tr>';
            summaryHtml += '<tr><th>Completed:</th><td>' + (log.completed_at || data.completed_at || '-') + '</td></tr>';
            if (data.error_message) {
                summaryHtml += '<tr><th>Error:</th><td class="aips-error-text">' + AIPS.escapeHtml(data.error_message) + '</td></tr>';
            }
            summaryHtml += '</table>';

            if (data.prompt) {
                summaryHtml += '<div class="aips-details-subsection"><h4>Generated Prompt</h4>';
                summaryHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.escapeAttribute(data.prompt) + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                summaryHtml += '<pre class="aips-prompt-text">' + AIPS.escapeHtml(data.prompt) + '</pre></div>';
            }

            if (data.generated_content) {
                summaryHtml += '<div class="aips-details-subsection"><h4>Generated Content</h4>';
                summaryHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.escapeAttribute(data.generated_content) + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                summaryHtml += '<pre class="aips-prompt-text" style="max-height: 300px; overflow-y: auto;">' + AIPS.escapeHtml(data.generated_content) + '</pre></div>';
            }

            $('#aips-details-summary').html(summaryHtml);
            
            if (log.template) {
                var templateHtml = '<table class="aips-details-table">';
                templateHtml += '<tr><th>Name:</th><td>' + AIPS.escapeHtml(log.template.name || '-') + '</td></tr>';
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
                voiceHtml += '<tr><th>Name:</th><td>' + AIPS.escapeHtml(log.voice.name || '-') + '</td></tr>';
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

        // Article Structures handlers

        /**
         * Reset and open the article structure modal in "Add New" mode.
         *
         * Clears the structure form, empties the hidden ID field, sets the
         * modal title to "Add New Article Structure", and displays the modal.
         *
         * @param {Event} e - Click event from an `.aips-add-structure-btn` element.
         */
        openAddStructureModal: function(e) {
            e.preventDefault();
            $('#aips-structure-form')[0].reset();
            $('#structure_id').val('');
            $('#aips-structure-modal-title').text('Add New Article Structure');
            $('#aips-structure-modal').show();
        },

        /**
         * Save the article structure form via AJAX.
         *
         * Sends the `aips_save_structure` AJAX action with all structure form
         * fields. Reloads the page on success or shows a toast on failure.
         *
         * Bound to the `click` event on `.aips-save-structure`.
         */
        saveStructure: function() {
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
                    AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.saveStructureFailed, 'error');
                }
            }).fail(function(){
                $btn.prop('disabled', false).text('Save Structure');
                AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
            });
        },

        /**
         * Fetch an article structure's data via AJAX and open the modal in
         * "Edit" mode.
         *
         * Reads the structure ID from the clicked element's `data-id` attribute.
         * Parses the `structure_data` JSON field to populate the prompt template
         * and sections fields.
         *
         * Bound to the `click` event on `.aips-edit-structure`.
         */
        editStructure: function() {
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
                    AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.loadStructureFailed, 'error');
                }
            }).fail(function(){
                AIPS.Utilities.showToast(aipsAdminL10n.errorOccurred, 'error');
            });
        },

        /**
         * Confirm and permanently delete an article structure via AJAX.
         *
         * Shows a confirmation dialog. On confirmation, sends the
         * `aips_delete_structure` AJAX action and fades out the table row on
         * success.
         *
         * Bound to the `click` event on `.aips-delete-structure`.
         */
        deleteStructure: function() {
            var $el = $(this);
            var id = $el.data('id');
            var $row = $el.closest('tr');
            AIPS.Utilities.confirm(aipsAdminL10n.deleteStructureConfirm, 'Confirm', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    $.post(aipsAjax.ajaxUrl, {action: 'aips_delete_structure', nonce: aipsAjax.nonce, structure_id: id}, function(response){
                        if (response.success) {
                            $row.fadeOut(function(){ $(this).remove(); });
                        } else {
                            AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.deleteStructureFailed, 'error');
                        }
                    }).fail(function(){ AIPS.Utilities.showToast(aipsAdminL10n.errorOccurred, 'error'); });
                }}
            ]);
        },

        // Prompt Sections handlers

        /**
         * Reset and open the prompt section modal in "Add New" mode.
         *
         * Clears the section form, empties the hidden ID field, sets the modal
         * title to "Add New Prompt Section", and displays the modal.
         *
         * @param {Event} e - Click event from an `.aips-add-section-btn` element.
         */
        openAddSectionModal: function(e) {
            e.preventDefault();
            $('#aips-section-form')[0].reset();
            $('#section_id').val('');
            $('#aips-section-modal-title').text('Add New Prompt Section');
            $('#aips-section-modal').show();
        },

        /**
         * Save the prompt section form via AJAX.
         *
         * Sends the `aips_save_prompt_section` AJAX action with all section form
         * fields. Reloads the page on success or shows a toast on failure.
         *
         * Bound to the `click` event on `.aips-save-section`.
         */
        saveSection: function() {
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
                    AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.saveSectionFailed, 'error');
                }
            }).fail(function(){
                $btn.prop('disabled', false).text('Save Section');
                AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
            });
        },

        /**
         * Fetch a prompt section's data via AJAX and open the modal in "Edit"
         * mode.
         *
         * Reads the section ID from the clicked element's `data-id` attribute
         * and populates all section form fields.
         *
         * Bound to the `click` event on `.aips-edit-section`.
         */
        editSection: function() {
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
                    AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.loadSectionFailed, 'error');
                }
            }).fail(function(){
                AIPS.Utilities.showToast(aipsAdminL10n.errorOccurred, 'error');
            });
        },

        /**
         * Confirm and permanently delete a prompt section via AJAX.
         *
         * Shows a confirmation dialog. On confirmation, sends the
         * `aips_delete_prompt_section` AJAX action and fades out the table row
         * on success.
         *
         * Bound to the `click` event on `.aips-delete-section`.
         */
        deleteSection: function() {
            var $el = $(this);
            var id = $el.data('id');
            var $row = $el.closest('tr');
            AIPS.Utilities.confirm(aipsAdminL10n.deleteSectionConfirm, 'Confirm', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    $.post(aipsAjax.ajaxUrl, {action: 'aips_delete_prompt_section', nonce: aipsAjax.nonce, section_id: id}, function(response){
                        if (response.success) {
                            $row.fadeOut(function(){ $(this).remove(); });
                        } else {
                            AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.deleteSectionFailed, 'error');
                        }
                    }).fail(function(){ AIPS.Utilities.showToast(aipsAdminL10n.errorOccurred, 'error'); });
                }}
            ]);
        },

        /**
         * Escape a plain-text string for safe insertion as HTML content.
         *
         * Uses a temporary `<div>` element and the browser's own `textContent`
         * setter to perform the escaping, which handles all HTML special
         * characters correctly without a manual entity map.
         *
         * @param  {string} text - Raw text to escape.
         * @return {string} HTML-safe string, or an empty string if `text` is falsy.
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Escape text for safe use in HTML attributes.
         * 
         * This function expects raw text input and will escape special characters
         * to prevent XSS attacks. It uses a two-pass approach: first replacing 
         * ampersands, then other characters, to avoid double-encoding. 
         * Do not use this function on text that already contains HTML entities, 
         * as they will be double-encoded.
         * 
         * @param {string} text - Raw text to escape
         * @return {string} Escaped text safe for HTML attributes
         */
        escapeAttribute: function(text) {
            if (!text) return '';
            // First pass: replace ampersands to avoid double-encoding
            text = text.replace(/&/g, '&amp;');
            // Second pass: replace other special characters
            var entityMap = {
                '"': '&quot;',
                "'": '&#39;',
                '<': '&lt;',
                '>': '&gt;',
                '\r': '&#13;',
                '\n': '&#10;',
                '\t': '&#9;'
            };
            return text.replace(/["'<>\r\n\t]/g, function(match) {
                return entityMap[match];
            });
        },

        /**
         * Close the nearest ancestor `.aips-modal` of the clicked element, or
         * hide all open modals if the click did not originate from inside one.
         *
         * Bound to the `click` event on `.aips-modal-close` and called directly
         * by the modal-backdrop and Escape-key handlers.
         */
        closeModal: function() {
            var $target = $(this).closest('.aips-modal');
            if ($target.length) {
                $target.hide();
            } else {
                $('.aips-modal').hide();
            }
        },

        /**
         * Displays the post-save "Next Steps" panel inside the template wizard.
         *
         * Replaces the hard page reload after a successful template save,
         * keeping the user in-context with actionable next steps.
         *
         * @param {number} templateId - The ID of the just-saved template.
         */
        showPostSaveActions: function(templateId) {
            $('.aips-wizard-step-content').hide();
            $('.aips-post-save-step').show();

            $('.aips-wizard-progress').hide();
            $('.aips-wizard-footer').hide();

            var scheduleUrl = (typeof aipsAjax !== 'undefined' && aipsAjax.schedulePageUrl)
                ? aipsAjax.schedulePageUrl + '&schedule_template=' + templateId
                : 'admin.php?page=aips-schedule&schedule_template=' + templateId;
            $('#aips-quick-schedule-btn').attr('href', scheduleUrl).data('template-id', templateId);
            $('#aips-quick-run-now-btn').data('template-id', templateId);
        },

        /**
         * Triggers Quick Schedule for the just-saved template from the post-save panel.
         *
         * @param {Event} e - Click event.
         */
        quickSchedule: function(e) {
            // Allow modified clicks (Ctrl/Cmd-click, middle-click) to open in a new tab as usual
            if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                return;
            }
            e.preventDefault();
            var $btn = $(this);
            var templateId = $btn.data('template-id');

            if (!templateId) return;

            // Use the aipsAjax.schedulePageUrl if available or fallback
            var scheduleUrlBase = (typeof aipsAjax !== 'undefined' && aipsAjax.schedulePageUrl)
                ? aipsAjax.schedulePageUrl
                : 'admin.php?page=aips-schedule';

            // Build the URL safely, handling whether scheduleUrlBase already contains a query string
            var url = new URL(scheduleUrlBase, window.location.href);
            url.searchParams.set('schedule_template', templateId);
            url.hash = 'open_schedule_modal';
            window.location.href = url.toString();
        },

        /**
         * Triggers "Run Now" for the just-saved template from the post-save panel.
         *
         * @param {Event} e - Click event.
         */
        quickRunNow: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var templateId = $btn.data('template-id');

            if (!templateId) return;

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update aips-spin"></span> Generating...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_run_now',
                    nonce: aipsAjax.nonce,
                    template_id: templateId
                },
                success: function(response) {
                    if (response.success) {
                        $('#aips-template-modal').hide();
                        if (response.data.edit_url) {
                            $('#aips-post-link').attr('href', response.data.edit_url);
                            $('#aips-post-link-container').show();
                        } else {
                            $('#aips-post-link-container').hide();
                        }
                        $('#aips-post-success-modal').show();
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Run Now');
                }
            });
        },

        /**
         * Auto-opens the schedule modal with a pre-selected template when
         * the schedule page is loaded with a ?schedule_template= query parameter.
         */
        initScheduleAutoOpen: function() {
            var $modal = $('#aips-schedule-modal');
            if (!$modal.length) return;

            // Prefer preselect from data attribute, then fall back to URL query param.
            var preselectId = $modal.data('preselect-template');

            if (!preselectId) {
                var urlParams = null;

                try {
                    // Use URL API when available (already used elsewhere in this file)
                    // and fall back to URLSearchParams if needed
                    urlParams = new URL(window.location.href).searchParams;
                } catch (e) {
                    try {
                        urlParams = new URLSearchParams(window.location.search);
                    } catch (e2) {
                        urlParams = null;
                    }
                }

                if (urlParams) {
                    preselectId = urlParams.get('schedule_template');
                }
            }

            // Only proceed with a valid positive integer template ID
            var preselectIdNum = parseInt(preselectId, 10);
            if (!preselectIdNum || preselectIdNum <= 0) return;

            var $form = $('#aips-schedule-form');
            if (!$form.length) return;

            $form[0].reset();
            $('#schedule_id').val('');
            $('#schedule_template').val(preselectIdNum);
            $('#aips-schedule-modal-title').text('Add New Schedule');
            $modal.show();

            // Clean the URL to prevent re-triggering on refresh
            if (window.history && window.history.replaceState) {
                try {
                    var cleanUrlObj = new URL(window.location.href);
                    cleanUrlObj.searchParams.delete('schedule_template');
                    cleanUrlObj.hash = '';
                    window.history.replaceState(null, '', cleanUrlObj.toString());
                } catch (e) {
                    // Fallback to regex cleanup if URL API unavailable
                    var cleanUrl = window.location.href.replace(/[?&]schedule_template=[^&]*/, '');
                    cleanUrl = cleanUrl.replace(/\?&/, '?');  // Fix orphaned ?& when param was first
                    cleanUrl = cleanUrl.replace(/\?$/, '');
                    cleanUrl = cleanUrl.replace(/#open_schedule_modal$/, '');
                    window.history.replaceState(null, '', cleanUrl);
                }
            }
        },

        // Wizard Navigation Functions

        /**
         * Navigate the template-creation wizard to a specific step.
         *
         * Hides all `.aips-wizard-step-content` panels and shows the one
         * matching `step`. Updates the progress indicator (marking earlier steps
         * as completed), toggles the Back/Next/Save buttons, and stores the
         * current step in `AIPS.currentWizardStep`. Calls `updateWizardSummary`
         * when advancing to the final step.
         *
         * @param {number} step - 1-based step index to navigate to (1–5).
         */
        wizardGoToStep: function(step) {
            var totalSteps = 5;
            
            // Hide all steps
            $('.aips-wizard-step-content').hide();
            
            // Show current step
            $('.aips-wizard-step-content[data-step="' + step + '"]').show();
            
            // Update progress indicator
            $('.aips-wizard-step').removeClass('active completed');
            $('.aips-wizard-step').each(function() {
                var stepNum = parseInt($(this).data('step'));
                if (stepNum < step) {
                    $(this).addClass('completed');
                } else if (stepNum === step) {
                    $(this).addClass('active');
                }
            });
            
            // Update button visibility
            if (step === 1) {
                $('.aips-wizard-back').hide();
            } else {
                $('.aips-wizard-back').show();
            }
            
            if (step === totalSteps) {
                $('.aips-wizard-next').hide();
                $('.aips-save-template').show();
                // Update summary
                AIPS.updateWizardSummary();
            } else {
                $('.aips-wizard-next').show();
                $('.aips-save-template').hide();
            }
            
            // Store current step
            AIPS.currentWizardStep = step;
        },

        /**
         * Advance the wizard to the next step after validating the current one.
         *
         * Calls `validateWizardStep` for the current step and only proceeds if
         * validation passes. Does nothing when already on the last step.
         *
         * @param {Event} e - Click event from an `.aips-wizard-next` element.
         */
        wizardNext: function(e) {
            e.preventDefault();
            var currentStep = AIPS.currentWizardStep || 1;
            
            // Validate current step before proceeding
            if (!AIPS.validateWizardStep(currentStep)) {
                return;
            }
            
            if (currentStep < 5) {
                AIPS.wizardGoToStep(currentStep + 1);
            }
        },

        /**
         * Go back to the previous wizard step.
         *
         * Does nothing when already on step 1.
         *
         * @param {Event} e - Click event from an `.aips-wizard-back` element.
         */
        wizardBack: function(e) {
            e.preventDefault();
            var currentStep = AIPS.currentWizardStep || 1;
            
            if (currentStep > 1) {
                AIPS.wizardGoToStep(currentStep - 1);
            }
        },

        /**
         * Validate the required fields for a given wizard step.
         *
         * Step 1 requires a template name; step 3 requires a content prompt.
         * Steps 2, 4, and 5 have no required fields. Shows an error toast and
         * focuses the first invalid field when validation fails.
         *
         * @param  {number}  step - The 1-based wizard step number to validate.
         * @return {boolean} `true` if validation passes, `false` otherwise.
         */
        validateWizardStep: function(step) {
            var isValid = true;
            var errorMessage = '';
            
            switch(step) {
                case 1:
                    // Validate name (required)
                    if (!$('#template_name').val().trim()) {
                        errorMessage = aipsAdminL10n.templateNameRequired;
                        isValid = false;
                        $('#template_name').focus();
                    }
                    break;
                case 2:
                    // Title prompt is optional, so no validation needed
                    break;
                case 3:
                    // Validate content prompt (required)
                    if (!$('#prompt_template').val().trim()) {
                        errorMessage = aipsAdminL10n.contentPromptRequired;
                        isValid = false;
                        $('#prompt_template').focus();
                    }
                    break;
                case 4:
                    // Featured image settings are optional
                    break;
                case 5:
                    // Final step, just display summary
                    break;
            }
            
            if (!isValid && errorMessage) {
                AIPS.Utilities.showToast(errorMessage, 'error');
            }
            
            return isValid;
        },

        /**
         * Populate the wizard's final summary step with the current form values.
         *
         * Reads template name, description, title prompt, content prompt, voice,
         * post quantity, and featured-image settings, then updates the
         * corresponding `#summary_*` elements.
         */
        updateWizardSummary: function() {
            // Update summary display with current form values
            $('#summary_name').text($('#template_name').val() || '-');
            $('#summary_description').text($('#template_description').val() || '-');
            
            var titlePrompt = $('#title_prompt').val();
            $('#summary_title_prompt').text(titlePrompt || 'Auto-generate from content');
            
            var contentPrompt = $('#prompt_template').val();
            if (contentPrompt.length > 100) {
                contentPrompt = contentPrompt.substring(0, 100) + '...';
            }
            $('#summary_content_prompt').text(contentPrompt || '-');
            
            var voiceText = $('#voice_id option:selected').text();
            $('#summary_voice').text(voiceText || 'None');
            
            $('#summary_quantity').text($('#post_quantity').val() || '1');
            
            var featuredImage = $('#generate_featured_image').is(':checked');
            if (featuredImage) {
                var source = $('#featured_image_source option:selected').text();
                $('#summary_featured_image').text('Yes (' + source + ')');
            } else {
                $('#summary_featured_image').text('No');
            }
        },

        /**
         * Sync all individual history checkboxes with the "select all" state.
         *
         * Bound to the `change` event on `#cb-select-all-1`.
         */
        toggleAllHistory: function() {
            var isChecked = $(this).prop('checked');
            $('.aips-history-table input[name="history[]"]').prop('checked', isChecked);
            AIPS.updateDeleteButton();
        },

        /**
         * Keep the history "select all" checkbox in sync with individual row
         * selections and update the delete button state.
         *
         * Bound to the `change` event on `.aips-history-table input[name="history[]"]`.
         */
        toggleHistorySelection: function() {
            var allChecked = $('.aips-history-table input[name="history[]"]').length === $('.aips-history-table input[name="history[]"]:checked').length;
            $('#cb-select-all-1').prop('checked', allChecked);
            AIPS.updateDeleteButton();
        },

        /**
         * Enable or disable the "Delete Selected" button based on the current
         * history row selection count.
         */
        updateDeleteButton: function() {
            var count = $('.aips-history-table input[name="history[]"]:checked').length;
            $('#aips-delete-selected-btn').prop('disabled', count === 0);
        },

        /**
         * Confirm and bulk-delete the selected history entries via AJAX.
         *
         * Collects IDs from all checked `input[name="history[]"]` checkboxes,
         * shows a confirmation dialog, then sends the `aips_bulk_delete_history`
         * AJAX action and reloads the page on success.
         *
         * @param {Event} e - Click event from `#aips-delete-selected-btn`.
         */
        deleteSelectedHistory: function(e) {
            e.preventDefault();
            var ids = [];
            $('.aips-history-table input[name="history[]"]:checked').each(function() {
                ids.push($(this).val());
            });

            if (ids.length === 0) return;

            var $btn = $(this);
            AIPS.Utilities.confirm('Are you sure you want to delete ' + ids.length + ' item(s)?', 'Notice', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                        AIPS.Utilities.showToast(response.data.message, 'error');
                        $btn.prop('disabled', false).text('Delete Selected');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                    $btn.prop('disabled', false).text('Delete Selected');
                }
            });
                }}
            ]);
        },

        // AI Variables feature methods

        /**
         * Perform an initial scan of all `.aips-ai-var-input` fields and
         * populate the AI variables panel.
         *
         * Called on modal open and whenever the template form is loaded with
         * existing data so the panel reflects the current state immediately.
         */
        initAIVariablesScanner: function() {
            // Initial scan when modal opens or form loads
            AIPS.scanAllAIVariables();
        },

        /**
         * Scan all `.aips-ai-var-input` fields, collect unique AI variable names,
         * and update the AI variables panel.
         *
         * Deduplicates variable names across all inputs before passing the list
         * to `updateAIVariablesPanel`.
         */
        scanAllAIVariables: function() {
            var allVariables = [];
            $('.aips-ai-var-input').each(function() {
                var text = $(this).val() || '';
                var vars = AIPS.extractAIVariables(text);
                vars.forEach(function(v) {
                    if (allVariables.indexOf(v) === -1) {
                        allVariables.push(v);
                    }
                });
            });
            AIPS.updateAIVariablesPanel(allVariables);
        },

        /**
         * Extract custom `{{variable}}` tokens from a text string.
         *
         * Matches all `{{...}}` patterns, trims whitespace from each name, and
         * skips names that appear in the `SYSTEM_VARIABLES` list or have already
         * been collected. Returns a deduplicated array of unique variable names.
         *
         * @param  {string}          text - The text to scan for variable tokens.
         * @return {Array<string>}        Array of unique custom variable names.
         */
        extractAIVariables: function(text) {
            var variables = [];
            var regex = /\{\{([^}]+)\}\}/g;
            var match;

            while ((match = regex.exec(text)) !== null) {
                var varName = match[1].trim();
                // Exclude system variables
                if (SYSTEM_VARIABLES.indexOf(varName) === -1 && variables.indexOf(varName) === -1) {
                    variables.push(varName);
                }
            }

            return variables;
        },

        /**
         * Render clickable `{{variable}}` tag chips in the AI variables panel.
         *
         * Hides the panel when `variables` is empty; otherwise builds an HTML
         * string of `.aips-ai-var-tag` spans with `data-variable` attributes and
         * injects it into `#aips-ai-variables-list`, then shows the panel.
         *
         * @param {Array<string>} variables - List of variable names to display.
         */
        updateAIVariablesPanel: function(variables) {
            var $panel = $('.aips-ai-variables-panel');
            var $list = $('#aips-ai-variables-list');

            if (variables.length === 0) {
                $panel.hide();
                return;
            }

            // Build the variable tags
            var html = '';
            variables.forEach(function(varName) {
                html += '<span class="aips-ai-var-tag" data-variable="{{' + AIPS.escapeHtml(varName) + '}}" title="Click to copy">';
                html += '<span class="dashicons dashicons-tag"></span>';
                html += '{{' + AIPS.escapeHtml(varName) + '}}';
                html += '</span>';
            });

            $list.html(html);
            $panel.show();
        },

        /**
         * Fetch a fully rendered preview of the current template's prompts and
         * display them in the collapsible preview drawer.
         *
         * Expands the drawer if it is currently collapsed, shows a loading
         * indicator, then sends the `aips_preview_template_prompts` AJAX action
         * with the current form values. On success, populates the content, title,
         * excerpt, and (optionally) image prompt sections along with voice and
         * structure metadata.
         *
         * @param {Event} e - Click event from an `.aips-preview-prompts` element.
         */
        previewPrompts: function(e) {
            e.preventDefault();
            
            var $drawer = $('#aips-preview-drawer');
            var $content = $drawer.find('.aips-preview-drawer-content');
            var $loading = $drawer.find('.aips-preview-loading');
            var $error = $drawer.find('.aips-preview-error');
            var $sections = $drawer.find('.aips-preview-sections');
            
            // Expand drawer if collapsed
            if (!$drawer.hasClass('expanded')) {
                AIPS.togglePreviewDrawer.call($drawer.find('.aips-preview-drawer-handle'));
            }
            
            // Show loading state
            $content.show();
            $loading.show();
            $error.hide();
            $sections.hide();
            
            // Gather form data
            var formData = {
                action: 'aips_preview_template_prompts',
                nonce: aipsAjax.nonce,
                prompt_template: $('#prompt_template').val(),
                title_prompt: $('#title_prompt').val(),
                voice_id: parseInt($('#voice_id').val()) || 0,
                article_structure_id: parseInt($('#article_structure_id').val()) || 0,
                image_prompt: $('#image_prompt').val(),
                generate_featured_image: $('#generate_featured_image').is(':checked') ? 1 : 0,
                featured_image_source: $('#featured_image_source').val()
            };
            
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    $loading.hide();
                    
                    if (response.success) {
                        var prompts = response.data.prompts;
                        var metadata = response.data.metadata;
                        
                        // Update metadata section
                        if (metadata.voice) {
                            $('#aips-preview-voice').show().find('.aips-preview-voice-name').text(metadata.voice);
                        } else {
                            $('#aips-preview-voice').hide();
                        }
                        
                        if (metadata.article_structure) {
                            $('#aips-preview-structure').show().find('.aips-preview-structure-name').text(metadata.article_structure);
                        } else {
                            $('#aips-preview-structure').hide();
                        }
                        
                        $('.aips-preview-sample-topic').text(metadata.sample_topic || 'Example Topic');
                        
                        // Update prompt sections
                        $('#aips-preview-content-prompt').text(prompts.content || '-');
                        $('#aips-preview-title-prompt').text(prompts.title || '-');
                        $('#aips-preview-excerpt-prompt').text(prompts.excerpt || '-');
                        
                        if (prompts.image) {
                            $('#aips-preview-image-section').show();
                            $('#aips-preview-image-prompt').text(prompts.image);
                        } else {
                            $('#aips-preview-image-section').hide();
                        }
                        
                        $sections.show();
                    } else {
                        var errorMsg = response.data.message || 'Failed to generate preview. Please check that all required fields are filled.';
                        $error.text(errorMsg).show();
                    }
                },
                error: function() {
                    $loading.hide();
                    $error.text('An error occurred while generating the preview. Please check your network connection and try again.').show();
                }
            });
        },

        /**
         * Toggle the prompt preview drawer open or closed.
         *
         * Adds/removes the `.expanded` class on `#aips-preview-drawer` and
         * slides the `.aips-preview-drawer-content` panel accordingly.
         *
         * @param {Event} [e] - Click event from `.aips-preview-drawer-handle`
         *                      (optional when called programmatically).
         */
        togglePreviewDrawer: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            var $drawer = $('#aips-preview-drawer');
            var $content = $drawer.find('.aips-preview-drawer-content');
            
            if ($drawer.hasClass('expanded')) {
                $drawer.removeClass('expanded');
                $content.slideUp(300);
            } else {
                $drawer.addClass('expanded');
                $content.slideDown(300);
            }
        },

        /**
         * Copy the `{{variable}}` token stored in a clicked `.aips-ai-var-tag`'s
         * `data-variable` attribute to the clipboard.
         *
         * Briefly applies the `.aips-ai-var-copied` CSS class to provide visual
         * feedback. Falls back to `document.execCommand('copy')` when the
         * Clipboard API is unavailable.
         *
         * @param {Event} e - Click event from an `.aips-ai-var-tag` element.
         */
        copyAIVariable: function(e) {
            e.preventDefault();
            var $tag = $(this);
            var variable = $tag.data('variable');

            if (!variable) return;

            // Use the existing copy functionality
            var showSuccess = function() {
                $tag.addClass('aips-ai-var-copied');
                setTimeout(function() {
                    $tag.removeClass('aips-ai-var-copied');
                }, 1500);
            };

            // Fallback for older browsers
            if (!navigator.clipboard) {
                var textArea = document.createElement('textarea');
                textArea.value = variable;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    showSuccess();
                } catch (err) {
                    console.error('Fallback: Unable to copy', err);
                }
                document.body.removeChild(textArea);
                return;
            }

            navigator.clipboard.writeText(variable).then(function() {
                showSuccess();
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
    });

    $(document).ready(function() {
        AIPS.init();
        // Load voices on template page load
        if ($('#voice_search').length) {
            AIPS.searchVoices.call($('#voice_search'));
        }
        console.log('AIPS: hello from admin.js')
    });

})(jQuery);
