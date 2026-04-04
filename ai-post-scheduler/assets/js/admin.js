(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    // System variables that should not be treated as AI Variables
    var SYSTEM_VARIABLES = ['date', 'year', 'month', 'day', 'time', 'site_name', 'site_description', 'random_number', 'topic', 'title'];

    // Required-field rules for the template wizard, shared by validateWizardStep and getFirstInvalidStep.
    // Each entry maps a 1-based step number to its required field selector and l10n message key.
    var WIZARD_REQUIRED_FIELDS = [
        { step: 1, selector: '#template_name',   messageKey: 'templateNameRequired' },
        { step: 3, selector: '#prompt_template', messageKey: 'contentPromptRequired' }
    ];

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

            // Toggle source groups panel when Include Sources? checkbox changes.
            $(document).on('change', '#include_sources', this.toggleSourceGroupsSelector);

            // Wizard navigation
            $(document).on('click', '.aips-wizard-next', this.wizardNext);
            $(document).on('click', '.aips-wizard-back', this.wizardBack);
            $(document).on('click', '.aips-wizard-step', this.wizardStepClick);

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
            $(document).on('click', '.aips-view-schedule-history', this.viewScheduleHistory);

            // Unified Schedule Page handlers
            $(document).on('change', '#cb-select-all-unified', this.toggleAllUnified);
            $(document).on('change', '.aips-unified-checkbox', this.toggleUnifiedSelection);
            $(document).on('click', '#aips-unified-select-all', this.selectAllUnified);
            $(document).on('click', '#aips-unified-unselect-all', this.unselectAllUnified);
            $(document).on('click', '#aips-unified-bulk-apply', this.applyUnifiedBulkAction);
            $(document).on('change', '.aips-unified-toggle-schedule', this.toggleUnifiedSchedule);
            $(document).on('click', '.aips-unified-run-now', this.runNowUnified);
            $(document).on('click', '.aips-view-unified-history', this.viewUnifiedScheduleHistory);
            $(document).on('change', '#aips-unified-type-filter', this.filterUnifiedByType);
            $(document).on('keyup search', '#aips-unified-search', this.filterUnifiedSchedules);
            $(document).on('click', '#aips-unified-search-clear', this.clearUnifiedSearch);
            $(document).on('click', '.aips-clear-unified-search-btn', this.clearUnifiedSearch);



            // Template Search
            $(document).on('keyup search', '#aips-template-search', this.filterTemplates);
            $(document).on('click', '#aips-template-search-clear', this.clearTemplateSearch);
            $(document).on('click', '.aips-clear-search-btn', this.clearTemplateSearch);

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
                    $result.addClass('aips-status-error').text(aipsAdminL10n.errorTryAgain);
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
            var $tabLink = $(e.currentTarget);
            var tabId = $tabLink.data('tab');
            var $tabNav = $tabLink.closest('.aips-tab-nav, .aips-topics-tabs, .aips-page-tabs');

            if (!$tabNav.length) {
                $tabNav = $tabLink.parent();
            }

            var $scope = $tabNav.closest('.aips-page-container, .aips-modal-content, .aips-modal-body');

            if (!$scope.length) {
                $scope = $(document);
            }

            // Update active state only for the local tab nav
            $tabNav.find('.aips-tab-link').removeClass('active');
            $tabLink.addClass('active');

            // Show corresponding tab content only within local scope
            $scope.find('.aips-tab-content').hide();
            var $targetTab = $scope.find('#' + tabId + '-tab').first();
            if ($targetTab.length) {
                $targetTab.show();
            }

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
         * Show or hide the Source Groups selector based on the Include Sources checkbox.
         *
         * @return {void}
         */
        toggleSourceGroupsSelector: function() {
            var checked = $('#include_sources').is(':checked');
            $('#template-source-groups-selector').toggle(checked);
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
            // Reset source groups
            $('.aips-template-source-group-cb').prop('checked', false);
            $('#template-source-groups-selector').hide();
            // Initialize wizard to step 1
            AIPS.wizardGoToStep(1, $('#aips-template-modal'));
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

                        // Restore source group settings.
                        var includeSources = t.include_sources == 1;
                        $('#include_sources').prop('checked', includeSources);
                        $('#template-source-groups-selector').toggle(includeSources);
                        $('.aips-template-source-group-cb').prop('checked', false);
                        var sgIds = [];
                        try {
                            sgIds = JSON.parse(t.source_group_ids || '[]');
                        } catch (parseErr) {
                            sgIds = [];
                        }
                        sgIds.forEach(function(tid) {
                            $('.aips-template-source-group-cb[value="' + tid + '"]').prop('checked', true);
                        });

                        // Scan for AI Variables after loading template data
                        AIPS.initAIVariablesScanner();
                        $('#aips-modal-title').text('Edit Template');
                        // Initialize wizard to step 1
                        AIPS.wizardGoToStep(1, $('#aips-template-modal'));
                        $('#aips-template-modal').show();
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
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
                { label: aipsAdminL10n.confirmCancelButton, className: 'aips-btn aips-btn-primary' },
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
                            AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
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
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
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

            // Cross-step validation: navigate to the first step with an unfilled required field.
            var invalid = AIPS.getFirstInvalidStep($('#aips-template-modal'));
            if (invalid) {
                AIPS.Utilities.showToast(invalid.message, 'warning');
                AIPS.wizardGoToStep(invalid.step, $('#aips-template-modal'));
                $(invalid.selector).focus();
                return;
            }

            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            $btn.prop('disabled', true).text(aipsAdminL10n.saving);

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
                    include_sources: $('#include_sources').is(':checked') ? 1 : 0,
                    source_group_ids: (function() {
                        var ids = [];
                        $('.aips-template-source-group-cb:checked').each(function() { ids.push($(this).val()); });
                        return ids;
                    }()),
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
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
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
         * `aips_save_template` AJAX action with `is_active=0` and updates the
         * template_id on success without reloading the page.
         *
         * @param {Event} e - Click event from an `.aips-save-draft-template` element.
         */
        saveDraftTemplate: function(e) {
            e.preventDefault();
            var $btn = $(this);
            
            // Validate at least name is provided
            var nameRule = WIZARD_REQUIRED_FIELDS.filter(function(r) { return r.step === 1; })[0];
            if (nameRule && !$(nameRule.selector).val().trim()) {
                AIPS.Utilities.showToast(aipsAdminL10n[nameRule.messageKey], 'warning');
                $(nameRule.selector).focus();
                AIPS.wizardGoToStep(1, $('#aips-template-modal'));
                return;
            }

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-cloud-saved"></span> ' + aipsAdminL10n.saving);

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
                    include_sources: $('#include_sources').is(':checked') ? 1 : 0,
                    source_group_ids: (function() {
                        var ids = [];
                        $('.aips-template-source-group-cb:checked').each(function() { ids.push($(this).val()); });
                        return ids;
                    }()),
                    is_active: 0 // Save as inactive draft
                },
                success: function(response) {
                    if (response.success) {
                        // Update the template_id so subsequent saves update the same draft
                        if (response.data && response.data.template_id) {
                            $('#template_id').val(response.data.template_id);
                            AIPS.lastSavedTemplateId = response.data.template_id;
                        }

                        AIPS.Utilities.showToast(aipsAdminL10n.draftSaved, 'success');
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-cloud-saved"></span> ' + aipsAdminL10n.saveDraft);
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
            var promptRule = WIZARD_REQUIRED_FIELDS.filter(function(r) { return r.step === 3; })[0];
            if (promptRule && !$(promptRule.selector).val().trim()) {
                AIPS.Utilities.showToast(aipsAdminL10n[promptRule.messageKey], 'warning');
                $(promptRule.selector).focus();
                return;
            }

            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> ' + aipsAdminL10n.generating);

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
                        AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.generationFailed, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
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

            $btn.prop('disabled', true).text(aipsAdminL10n.generating);

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
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(aipsAdminL10n.runNow);
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
                        $select.html('<option value="0">' + aipsAdminL10n.noVoiceDefault + '</option>');
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
            $('#aips-voice-modal-title').text(aipsAdminL10n.addNewVoice);
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
                        $('#aips-voice-modal-title').text(aipsAdminL10n.editVoice);
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
            AIPS.Utilities.confirm(aipsAdminL10n.deleteVoiceConfirm, 'Confirm', [
                { label: aipsAdminL10n.confirmCancelButton,  className: 'aips-btn aips-btn-primary' },
                { label: aipsAdminL10n.confirmDeleteButton, className: 'aips-btn aips-btn-danger-solid', action: function() {
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
            $btn.prop('disabled', true).text(aipsAdminL10n.saving);
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
                        AIPS.Utilities.showToast(response.data.message, 'success');
                        $('#aips-voice-modal').hide();

                        // Dynamically update the voices table
                        $.get(location.href, function(html) {
                            var $newDoc = $(html);
                            var $newContent = $newDoc.find('.aips-voices-list').closest('.aips-content-panel');
                            var $existingPanel = $('.aips-voices-list').closest('.aips-content-panel');

                            if ($newContent.length) {
                                if ($existingPanel.length) {
                                    $existingPanel.replaceWith($newContent);
                                } else {
                                    // If table didn't exist (we were on the empty state), replace the empty state panel
                                    // It's the one containing .aips-empty-state within .aips-voices-container.
                                    var $emptyStatePanel = $('.aips-voices-container').closest('.aips-content-panel');
                                    if ($emptyStatePanel.length) {
                                        $emptyStatePanel.replaceWith($newContent);
                                    } else {
                                        location.reload();
                                    }
                                }
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(aipsAdminL10n.saveVoice);
                }
            });
        },

        /**
         * Open the schedule modal in "Add New" mode.
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
         * Opens the schedule wizard pre-filled with the existing schedule's data
         * so the user can modify it in-place without deleting and recreating.
         *
         * @param {Event} e - Click event from the edit button.
         */
        editSchedule: function(e) {
            e.preventDefault();

            var $row = $(this).closest('tr');
            var scheduleId = $row.data('schedule-id');
            var templateId = $row.data('template-id');
            var scheduleTitle = $row.data('title');
            var frequency = $row.data('frequency');
            var topic = $row.data('topic');
            var articleStructureId = $row.data('article-structure-id');
            var rotationPattern = $row.data('rotation-pattern');
            var nextRun = $row.data('next-run');
            var isActive = $row.data('is-active');

            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val(scheduleId);
            $('#schedule_title').val(scheduleTitle || '');
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
                    $('#schedule_start_time').val(dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()) +
                        'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes()));
                }
            }
            $('#aips-schedule-modal-title').text('Edit Schedule');
            $('#aips-schedule-modal').show();
        },

        /**
         * Copy an existing schedule's settings into the wizard in "Add New" mode.
         *
         * Reads all schedule data from the row's `data-*` attributes, populates
         * the wizard form fields (leaving `schedule_id` and `start_time` blank
         * so a new schedule is created), and shows the wizard titled "Clone Schedule".
         *
         * @param {Event} e - Click event from an `.aips-clone-schedule` element.
         */
        cloneSchedule: function(e) {
            e.preventDefault();

            // Get data from the row
            var $row = $(this).closest('tr');
            var templateId = $row.data('template-id');
            var scheduleTitle = $row.data('title');
            var frequency = $row.data('frequency');
            var topic = $row.data('topic');
            var articleStructureId = $row.data('article-structure-id');
            var rotationPattern = $row.data('rotation-pattern');

            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val('');
            $('#schedule_title').val(scheduleTitle || '');
            $('#schedule_template').val(templateId);
            $('#schedule_frequency').val(frequency);
            $('#schedule_topic').val(topic);
            $('#article_structure_id').val(articleStructureId);
            $('#rotation_pattern').val(rotationPattern);
            $('#schedule_start_time').val('');
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

            $btn.prop('disabled', true).text(aipsAdminL10n.saving);

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_save_schedule',
                    nonce: aipsAjax.nonce,
                    schedule_id: $('#schedule_id').val(),
                    schedule_title: $('#schedule_title').val(),
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
                        AIPS.Utilities.showToast(response.data.message || 'Schedule saved successfully', 'success');
                        $('#aips-schedule-modal').hide();

                        // Dynamically update the schedules table
                        $.get(location.href, function(html) {
                            var $newDoc = $(html);
                            var $newContent = $newDoc.find('.aips-schedule-table').closest('.aips-content-panel');
                            var $existingPanel = $('.aips-schedule-table').closest('.aips-content-panel');

                            if ($newContent.length) {
                                if ($existingPanel.length) {
                                    $existingPanel.replaceWith($newContent);
                                } else {
                                    // If table didn't exist (we were on the empty state), replace the empty state panel
                                    // We need to find the correct panel to replace.
                                    // It's the one containing .aips-empty-state that is related to schedules.
                                    var $emptyStatePanel = $('.aips-content-panel').has('.aips-empty-state').last();
                                    if ($emptyStatePanel.length) {
                                        $emptyStatePanel.replaceWith($newContent);
                                    } else {
                                        location.reload();
                                    }
                                }

                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
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

            AIPS.Utilities.confirm(aipsAdminL10n.deleteScheduleConfirm, 'Notice', [
                { label: aipsAdminL10n.confirmCancelButton,  className: 'aips-btn aips-btn-primary' },
                { label: aipsAdminL10n.confirmDeleteButton, className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                            AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                        }
                    });
                }}
            ]);
        },

        /**
         * Triggers immediate execution of a specific schedule via its schedule_id.
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

            $btn.prop('disabled', true);
            $btn.find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update aips-spin');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_run_now',
                    nonce: aipsAjax.nonce,
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

                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                }
            });
        },

        /**
         * Open the Schedule History modal and load history entries for the given schedule.
         *
         * Fetches all activity/error log entries from the schedule's persistent
         * lifecycle history container via AJAX and renders a timeline list.
         *
         * @param {Event} e - Click event from an `.aips-view-schedule-history` element.
         */
        viewScheduleHistory: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var scheduleId = $btn.data('id');
            var scheduleName = $btn.data('name') || scheduleId;

            if (!scheduleId) {
                return;
            }

            var $modal = $('#aips-schedule-history-modal');
            var $title = $modal.find('#aips-schedule-history-modal-title');
            var $loading = $modal.find('#aips-schedule-history-loading');
            var $empty = $modal.find('#aips-schedule-history-empty');
            var $list = $modal.find('#aips-schedule-history-list');

            // Reset state
            $title.text('Schedule History: ' + scheduleName);
            $loading.show();
            $empty.hide();
            $list.hide().empty();
            $modal.show();

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_schedule_history',
                    nonce: aipsAjax.nonce,
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

                    var iconMap = {
                        'schedule_created':  { icon: 'dashicons-plus-alt',        cls: 'aips-timeline-created'  },
                        'schedule_updated':  { icon: 'dashicons-edit',             cls: 'aips-timeline-updated'  },
                        'schedule_enabled':  { icon: 'dashicons-yes-alt',          cls: 'aips-timeline-enabled'  },
                        'schedule_disabled': { icon: 'dashicons-minus',            cls: 'aips-timeline-disabled' },
                        'schedule_executed': { icon: 'dashicons-controls-play',    cls: 'aips-timeline-executed' },
                        'manual_schedule_started':   { icon: 'dashicons-controls-play', cls: 'aips-timeline-executed' },
                        'manual_schedule_completed': { icon: 'dashicons-yes',           cls: 'aips-timeline-success'  },
                        'manual_schedule_failed':    { icon: 'dashicons-warning',        cls: 'aips-timeline-error'    },
                        'schedule_failed':   { icon: 'dashicons-warning',          cls: 'aips-timeline-error'    },
                        'post_published':    { icon: 'dashicons-media-document',   cls: 'aips-timeline-success'  },
                        'post_draft':        { icon: 'dashicons-media-document',   cls: 'aips-timeline-draft'    },
                        'post_generated':    { icon: 'dashicons-media-document',   cls: 'aips-timeline-draft'    },
                    };
                    var defaultIcon = { icon: 'dashicons-info', cls: '' };

                    entries.forEach(function(entry) {
                        var info = iconMap[entry.event_type] || defaultIcon;
                        var isError = (entry.history_type_id === 2 || entry.event_status === 'failed');
                        if (isError && !info.cls) {
                            info = { icon: 'dashicons-warning', cls: 'aips-timeline-error' };
                        }

                        var $item = $('<li>', { 'class': 'aips-timeline-item ' + info.cls });
                        var $icon = $('<span>', { 'class': 'aips-timeline-icon', 'aria-hidden': 'true' })
                            .append($('<span>', { 'class': 'dashicons ' + info.icon }));
                        var $content = $('<div>', { 'class': 'aips-timeline-content' });
                        var $msg = $('<p>', { 'class': 'aips-timeline-message' }).text(entry.message || entry.log_type);
                        var $time = $('<time>', { 'class': 'aips-timeline-timestamp', 'datetime': entry.timestamp })
                            .text(entry.timestamp);

                        $content.append($msg).append($time);
                        $item.append($icon).append($content);
                        $list.append($item);
                    });

                    $list.show();
                },
                error: function() {
                    $loading.hide();
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                    $modal.hide();
                }
            });
        },
        // =====================================================================
        // Unified Schedule Page handlers
        // =====================================================================

        /**
         * Navigate to the schedules page filtered by type when the type
         * dropdown changes.
         *
         * @param {Event} e - Change event from `#aips-unified-type-filter`.
         */
        filterUnifiedByType: function(e) {
            var type = $(this).val();
            var url  = window.location.href.split('?')[0];
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
            var term = $(this).val().toLowerCase().trim();
            var $clear = $('#aips-unified-search-clear');
            $clear.toggle(term.length > 0);

            var $rows = $('.aips-unified-row');
            var found = 0;

            $rows.each(function() {
                var text = $(this).text().toLowerCase();
                var match = !term || text.indexOf(term) !== -1;
                $(this).toggle(match);
                if (match) { found++; }
            });

            $('#aips-unified-search-no-results').toggle(found === 0 && $rows.length > 0);
        },

        /**
         * Clear the unified schedule search field and restore all rows.
         *
         * Bound to `#aips-unified-search-clear` and `.aips-clear-unified-search-btn`.
         *
         * @param {Event} e - Click event.
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
            AIPS.updateUnifiedBulkActions();
        },

        /**
         * Keep the "select all" in sync when individual rows are toggled.
         */
        toggleUnifiedSelection: function() {
            var total   = $('.aips-unified-checkbox:visible').length;
            var checked = $('.aips-unified-checkbox:visible:checked').length;
            $('#cb-select-all-unified').prop('checked', total > 0 && checked === total);
            AIPS.updateUnifiedBulkActions();
        },

        /** Check all visible rows. */
        selectAllUnified: function() {
            $('.aips-unified-checkbox:visible').prop('checked', true);
            $('#cb-select-all-unified').prop('checked', true);
            AIPS.updateUnifiedBulkActions();
        },

        /** Uncheck all rows. */
        unselectAllUnified: function() {
            $('.aips-unified-checkbox').prop('checked', false);
            $('#cb-select-all-unified').prop('checked', false);
            AIPS.updateUnifiedBulkActions();
        },

        /**
         * Enable or disable the unified bulk-action Apply button and show the
         * selection count.
         */
        updateUnifiedBulkActions: function() {
            var count      = $('.aips-unified-checkbox:checked').length;
            var $apply     = $('#aips-unified-bulk-apply');
            var $unselect  = $('#aips-unified-unselect-all');
            var $countLbl  = $('#aips-unified-selected-count');

            $apply.prop('disabled', count === 0);
            $unselect.prop('disabled', count === 0);

            if (count > 0) {
                $countLbl.text(count + ' selected').show();
            } else {
                $countLbl.hide();
            }
        },

        /**
         * Parse selected unified-schedule checkboxes and dispatch the chosen
         * bulk action.
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

            if (action === 'run_now') {
                AIPS.Utilities.confirm(
                    aipsAdminL10n.runSchedulesNow
                        ? aipsAdminL10n.runSchedulesNow
                        : 'Run ' + items.length + ' schedule(s) now?',
                    'Run Now',
                    [
                        { label: aipsAdminL10n.cancel || 'Cancel', className: 'aips-btn aips-btn-secondary' },
                        { label: aipsAdminL10n.yesRunNow || 'Yes, Run Now', className: 'aips-btn aips-btn-primary', action: function() {
                            AIPS.unifiedBulkRunNow(items);
                        }}
                    ]
                );
            } else if (action === 'pause') {
                AIPS.unifiedBulkToggle(items, 0);
            } else if (action === 'resume') {
                AIPS.unifiedBulkToggle(items, 1);
            }
        },

        /**
         * Bulk run-now for mixed-type schedules via `aips_unified_bulk_run_now`.
         *
         * @param {Array<{type: string, id: number}>} items
         */
        unifiedBulkRunNow: function(items) {
            var $applyBtn = $('#aips-unified-bulk-apply');
            $applyBtn.prop('disabled', true).text('Running…');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_unified_bulk_run_now',
                    nonce: aipsAjax.nonce,
                    items: items
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
                    AIPS.updateUnifiedBulkActions();
                }
            });
        },

        /**
         * Bulk pause/resume mixed-type schedules via `aips_unified_bulk_toggle`.
         *
         * @param {Array<{type: string, id: number}>} items
         * @param {number} isActive 1 to resume, 0 to pause.
         */
        unifiedBulkToggle: function(items, isActive) {
            var $applyBtn = $('#aips-unified-bulk-apply');
            $applyBtn.prop('disabled', true).text(isActive ? 'Resuming…' : 'Pausing…');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_unified_bulk_toggle',
                    nonce: aipsAjax.nonce,
                    items: items,
                    is_active: isActive
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data || {};
                        var updatedItems = Array.isArray(data.updated_items) ? data.updated_items : null;
                        var failedItems  = Array.isArray(data.failed_items) ? data.failed_items : null;
                        var errorItems   = (!updatedItems && Array.isArray(data.errors)) ? data.errors : null;

                        var failedKeysMap = {};
                        var successfulItems;

                        // Normalize failed items from either failed_items or errors.
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

                        if (updatedItems) {
                            // Backend explicitly told us which items were updated.
                            successfulItems = updatedItems;
                        } else if (Object.keys(failedKeysMap).length > 0) {
                            // Infer successes as "requested items minus failed".
                            successfulItems = items.filter(function(item) {
                                var key = item.type + ':' + item.id;
                                return !failedKeysMap[key];
                            });
                        } else {
                            // No per-item info available; fall back to previous behavior.
                            successfulItems = items;
                        }

                        AIPS.Utilities.showToast(data.message, 'success');

                        // Update each successful row's badge and toggle to reflect new state.
                        successfulItems.forEach(function(item) {
                            if (!item || !item.type || typeof item.id === 'undefined') {
                                return;
                            }
                            var key  = item.type + ':' + item.id;
                            var $row = $('tr[data-row-key="' + key + '"]');
                            if ($row.length) {
                                AIPS.updateUnifiedRowStatus($row, isActive);
                                // In partial success, unselect only successful rows to keep failures visible.
                                if (Object.keys(failedKeysMap).length > 0) {
                                    $row.find('.aips-unified-select').prop('checked', false);
                                }
                            }
                        });

                        // If there were no known failures, keep existing behavior (unselect all).
                        if (Object.keys(failedKeysMap).length === 0) {
                            AIPS.unselectAllUnified();
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
                    AIPS.updateUnifiedBulkActions();
                }
            });
        },

        /**
         * Toggle a single unified schedule's active status.
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
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_unified_toggle',
                    nonce: aipsAjax.nonce,
                    id: id,
                    type: type,
                    is_active: isActive
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.updateUnifiedRowStatus($row, isActive);
                    } else {
                        // Revert the toggle
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
         * @param {jQuery} $row     The `<tr>` element to update.
         * @param {number} isActive 1 = active/resumed, 0 = paused.
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
         * Run a single unified schedule immediately.
         *
         * Bound to click on `.aips-unified-run-now`.
         *
         * @param {Event} e - Click event.
         */
        runNowUnified: function(e) {
            e.preventDefault();

            var $btn  = $(this);
            var id    = $btn.data('id');
            var type  = $btn.data('type');

            if (!id || !type) { return; }

            $btn.prop('disabled', true);
            $btn.find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update aips-spin');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_unified_run_now',
                    nonce: aipsAjax.nonce,
                    id: id,
                    type: type
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
            var limit = $btn.data('limit') || 0;

            if (!id || !type) { return; }

            var $modal   = $('#aips-schedule-history-modal');
            var $title   = $modal.find('#aips-schedule-history-modal-title');
            var $loading = $modal.find('#aips-schedule-history-loading');
            var $empty   = $modal.find('#aips-schedule-history-empty');
            var $list    = $modal.find('#aips-schedule-history-list');

            $title.text('Recent History: ' + name);
            $loading.show();
            $empty.hide();
            $list.hide().empty();
            $modal.show();

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_unified_schedule_history',
                    nonce: aipsAjax.nonce,
                    id: id,
                    type: type,
                    limit: limit
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
                        'post_generated':            { icon: 'dashicons-media-document', cls: 'aips-timeline-draft'    },
                        'author_topic_generation':   { icon: 'dashicons-tag',            cls: 'aips-timeline-executed' },
                        'topic_post_generation':     { icon: 'dashicons-admin-users',    cls: 'aips-timeline-executed' },
                    };
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
                        var $msg     = $('<p>', { 'class': 'aips-timeline-message' }).text(entry.message || entry.log_type);
                        var $time    = $('<time>', { 'class': 'aips-timeline-timestamp', 'datetime': entry.timestamp })
                                           .text(entry.timestamp);

                        $content.append($msg).append($time);
                        $item.append($icon).append($content);
                        $list.append($item);
                    });

                    $list.show();
                },
                error: function() {
                    $loading.hide();
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                    $modal.hide();
                }
            });
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
                var field = ($row.data('field-niche') || '').toString().toLowerCase();

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
         * fields. On success, renders the updated row using `AIPS.Templates` and
         * replaces or inserts it in the structures table without a page reload.
         *
         * Bound to the `click` event on `.aips-save-structure`.
         */
        saveStructure: function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text(aipsAdminL10n.saving);

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
                    AIPS.Utilities.showToast(response.data.message || 'Structure saved successfully', 'success');
                    $('#aips-structure-modal').hide();

                    var structure = response.data.structure;
                    if (structure) {
                        var T = AIPS.Templates;
                        var activeBadge = structure.is_active == 1
                            ? '<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span> ' + T.escape(aipsAdminL10n.activeLabel) + '</span>'
                            : '<span class="aips-badge aips-badge-neutral"><span class="dashicons dashicons-minus"></span> ' + T.escape(aipsAdminL10n.inactiveLabel) + '</span>';
                        var defaultBadge = structure.is_default == 1
                            ? '<span class="aips-badge aips-badge-info">' + T.escape(aipsAdminL10n.defaultLabel) + '</span>'
                            : '<span class="cell-meta">&mdash;</span>';
                        var scheduleUrl = (aipsAjax.schedulePageUrl || '') + '&schedule_structure=' + T.escape(String(structure.id));

                        var rowHtml = T.renderRaw('aips-tmpl-structure-row', {
                            id: T.escape(String(structure.id)),
                            name: T.escape(structure.name || ''),
                            description: T.escape(structure.description || ''),
                            activeBadge: activeBadge,
                            defaultBadge: defaultBadge,
                            scheduleUrl: scheduleUrl,
                        });

                        var $existingRow = $('tr[data-structure-id="' + parseInt(structure.id, 10) + '"]');
                        if ($existingRow.length) {
                            $existingRow.replaceWith(rowHtml);
                        } else {
                            var $tbody = $('.aips-structures-list tbody');
                            if ($tbody.length) {
                                $tbody.append(rowHtml);
                            }
                        }
                    }
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
                { label: aipsAdminL10n.confirmCancelButton,  className: 'aips-btn aips-btn-primary' },
                { label: aipsAdminL10n.confirmDeleteButton, className: 'aips-btn aips-btn-danger-solid', action: function() {
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
         * fields. On success, renders the updated row using `AIPS.Templates` and
         * replaces or inserts it in the sections table, then refreshes the
         * `#structure_sections` select options — all without a page reload.
         *
         * Bound to the `click` event on `.aips-save-section`.
         */
        saveSection: function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text(aipsAdminL10n.saving);

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
                    AIPS.Utilities.showToast(response.data.message || 'Section saved successfully', 'success');
                    $('#aips-section-modal').hide();

                    var section = response.data.section;
                    if (section) {
                        var T = AIPS.Templates;
                        var activeBadge = section.is_active == 1
                            ? '<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span> ' + T.escape(aipsAdminL10n.activeLabel) + '</span>'
                            : '<span class="aips-badge aips-badge-neutral"><span class="dashicons dashicons-minus"></span> ' + T.escape(aipsAdminL10n.inactiveLabel) + '</span>';

                        var rowHtml = T.renderRaw('aips-tmpl-section-row', {
                            id: T.escape(String(section.id)),
                            name: T.escape(section.name || ''),
                            section_key: T.escape(section.section_key || ''),
                            description: T.escape(section.description || ''),
                            activeBadge: activeBadge,
                        });

                        var $existingRow = $('tr[data-section-id="' + parseInt(section.id, 10) + '"]');
                        if ($existingRow.length) {
                            $existingRow.replaceWith(rowHtml);
                        } else {
                            var $tbody = $('.aips-sections-list tbody');
                            if ($tbody.length) {
                                $tbody.append(rowHtml);
                            }
                        }

                        // Refresh the section option in the structure modal's multi-select.
                        var sectionKey = section.section_key || '';
                        var optionHtml = T.renderRaw('aips-tmpl-section-option', {
                            section_key: T.escape(sectionKey),
                            name: T.escape(section.name || ''),
                        });
                        var $select = $('#structure_sections');
                        var $existingOption = $select.find('option[value="' + T.escape(sectionKey) + '"]');
                        if ($existingOption.length) {
                            $existingOption.replaceWith(optionHtml);
                        } else {
                            $select.append(optionHtml);
                        }
                    }
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
                { label: aipsAdminL10n.confirmCancelButton,  className: 'aips-btn aips-btn-primary' },
                { label: aipsAdminL10n.confirmDeleteButton, className: 'aips-btn aips-btn-danger-solid', action: function() {
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
            var $modal = $('#aips-template-modal');
            $modal.find('.aips-wizard-step-content').hide();
            $modal.find('.aips-post-save-step').show();

            $modal.find('.aips-wizard-progress').hide();
            $modal.find('.aips-wizard-footer').hide();

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

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update aips-spin"></span> ' + aipsAdminL10n.generating);

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
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> ' + aipsAdminL10n.runNow);
                }
            });
        },

        /**
         * Auto-open the schedule modal with pre-selected template/structure IDs.
         */
        initScheduleAutoOpen: function() {
            var $legacyModal = $('#aips-schedule-modal');
            if (!$legacyModal.length) return;

            // Prefer preselect from data attribute, then fall back to URL query param.
            var preselectId = $legacyModal.data('preselect-template');
            var preselectStructureId = $legacyModal.data('preselect-structure');

            if (!preselectId && !preselectStructureId) {
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
                    if (urlParams.get('schedule_template')) {
                        preselectId = urlParams.get('schedule_template');
                    } else if (urlParams.get('schedule_structure')) {
                        preselectStructureId = urlParams.get('schedule_structure');
                    }
                }
            }

            // Only proceed with a valid positive integer template ID or structure ID
            var preselectIdNum = parseInt(preselectId, 10);
            var preselectStructureIdNum = parseInt(preselectStructureId, 10);

            if ((!preselectIdNum || preselectIdNum <= 0) && (!preselectStructureIdNum || preselectStructureIdNum <= 0)) {
                return;
            }

            var $legacyForm = $('#aips-schedule-form');
            if (!$legacyForm.length) return;

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

            // Clean the URL to prevent re-triggering on refresh
            if (window.history && window.history.replaceState) {
                try {
                    var cleanUrlObj = new URL(window.location.href);
                    cleanUrlObj.searchParams.delete('schedule_template');
                    cleanUrlObj.searchParams.delete('schedule_structure');
                    cleanUrlObj.hash = '';
                    window.history.replaceState(null, '', cleanUrlObj.toString());
                } catch (e) {
                    // Fallback to regex cleanup if URL API unavailable
                    var cleanUrl = window.location.href.replace(/[?&]schedule_template=[^&]*/, '');
                    cleanUrl = cleanUrl.replace(/[?&]schedule_structure=[^&]*/, '');
                    cleanUrl = cleanUrl.replace(/\?&/, '?');  // Fix orphaned ?& when param was first
                    cleanUrl = cleanUrl.replace(/\?$/, '');
                    cleanUrl = cleanUrl.replace(/#open_schedule_modal$/, '');
                    window.history.replaceState(null, '', cleanUrl);
                }
            }
        },

        // Wizard Navigation Functions

        /**
         * Navigate a wizard modal to a specific step.
         *
         * Scopes all DOM queries to `$modal` so multiple wizard modals on the
         * same page (e.g. Template Wizard and Schedule Wizard) do not interfere
         * with each other. Reads the total number of steps from the modal's
         * `data-wizard-steps` attribute, or counts `.aips-wizard-step-content`
         * elements (excluding the post-save panel). Updates progress indicators,
         * toggles Back/Next buttons, and calls `updateWizardSummary` when
         * advancing to the last step.
         *
         * @param {number}  step   - 1-based step index to navigate to.
         * @param {jQuery}  $modal - The wizard modal element to operate on.
         */
        wizardGoToStep: function(step, $modal) {
            $modal = $modal || AIPS.currentWizardModal;
            if (!$modal || !$modal.length) return;

            var totalSteps = parseInt($modal.data('wizard-steps'), 10) ||
                $modal.find('.aips-wizard-step-content').not('.aips-post-save-step').length;

            // Hide all steps, then show the target step
            $modal.find('.aips-wizard-step-content').hide();
            $modal.find('.aips-wizard-step-content[data-step="' + step + '"]').show();

            // Update progress indicator
            $modal.find('.aips-wizard-step').removeClass('active completed').each(function() {
                var stepNum = parseInt($(this).data('step'), 10);
                if (stepNum < step) {
                    $(this).addClass('completed');
                } else if (stepNum === step) {
                    $(this).addClass('active');
                }
            });

            // Toggle Back / Next button visibility
            if (step === 1) {
                $modal.find('.aips-wizard-back').hide();
            } else {
                $modal.find('.aips-wizard-back').show();
            }

            if (step === totalSteps) {
                $modal.find('.aips-wizard-next').hide();
                $modal.find('.aips-wizard-save-btn').removeClass('button-secondary').addClass('button-primary');
                // Populate the summary step
                AIPS.updateWizardSummary($modal);
            } else {
                $modal.find('.aips-wizard-next').show();
                $modal.find('.aips-wizard-save-btn').removeClass('button-primary').addClass('button-secondary');
            }

            // Store the current step on the modal element so each wizard tracks
            // its own state independently.
            $modal.data('current-step', step);
            AIPS.currentWizardModal = $modal;
            AIPS.currentWizardStep = step; // backward-compat alias
        },

        /**
         * Advance the wizard to the next step after validating the current one.
         *
         * Derives the modal context from the clicked element. Calls
         * `validateWizardStep` for the current step and only proceeds if
         * validation passes.
         *
         * @param {Event} e - Click event from an `.aips-wizard-next` element.
         */
        wizardNext: function(e) {
            e.preventDefault();
            var $modal = $(this).closest('.aips-wizard-modal');
            if (!$modal.length) return;
            var currentStep = parseInt($modal.data('current-step'), 10) || 1;
            var totalSteps = parseInt($modal.data('wizard-steps'), 10) ||
                $modal.find('.aips-wizard-step-content').not('.aips-post-save-step').length;

            // Validate current step before proceeding
            if (!AIPS.validateWizardStep(currentStep, $modal)) {
                return;
            }

            if (currentStep < totalSteps) {
                AIPS.wizardGoToStep(currentStep + 1, $modal);
            }
        },

        /**
         * Go back to the previous wizard step.
         *
         * Derives the modal context from the clicked element.
         *
         * @param {Event} e - Click event from an `.aips-wizard-back` element.
         */
        wizardBack: function(e) {
            e.preventDefault();
            var $modal = $(this).closest('.aips-wizard-modal');
            if (!$modal.length) return;
            var currentStep = parseInt($modal.data('current-step'), 10) || 1;

            if (currentStep > 1) {
                AIPS.wizardGoToStep(currentStep - 1, $modal);
            }
        },

        /**
         * Handle clicking directly on a progress indicator step.
         *
         * Allows navigating directly to previous steps, or advancing to future
         * steps provided all intermediate steps pass validation.
         *
         * @param {Event} e - Click event from an `.aips-wizard-step` element.
         */
        wizardStepClick: function(e) {
            e.preventDefault();
            var $modal = $(this).closest('.aips-wizard-modal');
            if (!$modal.length) return;
            var currentStep = parseInt($modal.data('current-step'), 10) || 1;
            var targetStep = parseInt($(this).data('step'), 10);

            if (!targetStep || targetStep === currentStep) {
                return;
            }

            // If going backwards, just go there directly
            if (targetStep < currentStep) {
                AIPS.wizardGoToStep(targetStep, $modal);
                return;
            }

            // If going forwards, validate all intermediate steps
            for (var i = currentStep; i < targetStep; i++) {
                if (!AIPS.validateWizardStep(i, $modal)) {
                    // Validation failed on step 'i', so we can't proceed past it.
                    // If we are not already on the step that failed, go to it.
                    if (currentStep !== i) {
                        AIPS.wizardGoToStep(i, $modal);
                    }
                    return;
                }
            }

            // If all validation passed, go to the target step
            AIPS.wizardGoToStep(targetStep, $modal);
        },

        /**
         * Return the first wizard step that contains an unfilled required field,
         * or `null` if all required fields are valid.
         *
         * Selects the appropriate rule set based on the modal's `id`. Used by
         * both `validateWizardStep` (per-step Next-click validation) and the
         * save functions (full pre-save validation across all steps).
         *
         * @param  {jQuery} $modal - The wizard modal element.
         * @return {{ step: number, selector: string, message: string }|null}
         */
        getFirstInvalidStep: function($modal) {
            $modal = $modal || AIPS.currentWizardModal;
            var rules = WIZARD_REQUIRED_FIELDS;

            for (var i = 0; i < rules.length; i++) {
                var rule = rules[i];
                if (!$(rule.selector).val().trim()) {
                    return { step: rule.step, selector: rule.selector, message: aipsAdminL10n[rule.messageKey] };
                }
            }
            return null;
        },

        /**
         * Validate the required fields for a given wizard step.
         *
         * Selects the appropriate rule set based on the modal's `id`. Steps
         * with no required fields always pass. Shows an error toast and focuses
         * the invalid field when validation fails.
         *
         * @param  {number}  step   - The 1-based wizard step number to validate.
         * @param  {jQuery}  $modal - The wizard modal element.
         * @return {boolean} `true` if validation passes, `false` otherwise.
         */
        validateWizardStep: function(step, $modal) {
            $modal = $modal || AIPS.currentWizardModal;
            var rules = WIZARD_REQUIRED_FIELDS;

            for (var i = 0; i < rules.length; i++) {
                var rule = rules[i];
                if (rule.step === step && !$(rule.selector).val().trim()) {
                    AIPS.Utilities.showToast(aipsAdminL10n[rule.messageKey], 'error');
                    $(rule.selector).focus();
                    return false;
                }
            }
            return true;
        },

        /**
         * Populate the final summary step of the active wizard.
         *
         * Dispatches to the appropriate summary renderer based on the modal's
         * `id`; the template wizard and schedule wizard have different fields.
         *
         * @param {jQuery} $modal - The wizard modal element.
         */
        updateWizardSummary: function($modal) {
            $modal = $modal || AIPS.currentWizardModal;
            if (!$modal || !$modal.length) return;
            AIPS.updateTemplateWizardSummary($modal);
        },

        /**
         * Populate the template wizard's final summary step with form values.
         *
         * Reads template name, description, title prompt, content prompt, voice,
         * post quantity, and featured-image settings, then updates the
         * corresponding `#summary_*` elements.
         *
         * @param {jQuery} $modal - The template wizard modal element.
         */
        updateTemplateWizardSummary: function($modal) {
            $modal = $modal || AIPS.currentWizardModal;

            $modal.find('#summary_name').text($('#template_name').val() || '-');
            $modal.find('#summary_description').text($('#template_description').val() || '-');

            var titlePrompt = $('#title_prompt').val();
            $modal.find('#summary_title_prompt').text(titlePrompt || aipsAdminL10n.autoGenerateFromContent);

            var contentPrompt = $('#prompt_template').val();
            if (contentPrompt.length > 100) {
                contentPrompt = contentPrompt.substring(0, 100) + '...';
            }
            $modal.find('#summary_content_prompt').text(contentPrompt || '-');

            var voiceText = $('#voice_id option:selected').text();
            $modal.find('#summary_voice').text(voiceText || aipsAdminL10n.noneOption);

            $modal.find('#summary_quantity').text($('#post_quantity').val() || '1');

            var featuredImage = $('#generate_featured_image').is(':checked');
            if (featuredImage) {
                var source = $('#featured_image_source option:selected').text();
                $modal.find('#summary_featured_image').text(aipsAdminL10n.featuredImageYes.replace('%s', source));
            } else {
                $modal.find('#summary_featured_image').text(aipsAdminL10n.featuredImageNo);
            }
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
                html += '<span class="aips-ai-var-tag" data-variable="{{' + AIPS.escapeHtml(varName) + '}}" title="' + aipsAdminL10n.clickToCopy + '">';
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
                featured_image_source: $('#featured_image_source').val(),
                include_sources: $('#include_sources').is(':checked') ? 1 : 0,
                source_group_ids: (function() {
                    var ids = [];
                    $('.aips-template-source-group-cb:checked').each(function() { ids.push($(this).val()); });
                    return ids;
                }())
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
                        
                        $('.aips-preview-sample-topic').text(metadata.sample_topic || aipsAdminL10n.exampleTopic);
                        
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
                        var errorMsg = response.data.message || aipsAdminL10n.failedToGeneratePreview;
                        $error.text(errorMsg).show();
                    }
                },
                error: function() {
                    $loading.hide();
                    $error.text(aipsAdminL10n.previewNetworkError).show();
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
    });

})(jQuery);
