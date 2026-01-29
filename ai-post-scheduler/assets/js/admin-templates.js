(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    // System variables that should not be treated as AI Variables
    var SYSTEM_VARIABLES = ['date', 'year', 'month', 'day', 'time', 'site_name', 'site_description', 'random_number', 'topic', 'title'];

    Object.assign(AIPS, {
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

        cloneTemplate: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');

            if (!confirm('Are you sure you want to clone this template?')) {
                return;
            }

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
                        alert(response.data.message);
                        $btn.prop('disabled', false).text('Clone');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $btn.prop('disabled', false).text('Clone');
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

        saveDraftTemplate: function(e) {
            e.preventDefault();
            var $btn = $(this);

            // Validate at least name is provided
            if (!$('#template_name').val().trim()) {
                alert(aipsAdminL10n.templateNameRequired);
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
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-cloud-saved"></span> Save Draft');
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

        // Wizard Navigation Functions
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

        wizardBack: function(e) {
            e.preventDefault();
            var currentStep = AIPS.currentWizardStep || 1;

            if (currentStep > 1) {
                AIPS.wizardGoToStep(currentStep - 1);
            }
        },

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
                alert(errorMessage);
            }

            return isValid;
        },

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

        // AI Variables feature methods
        initAIVariablesScanner: function() {
            // Initial scan when modal opens or form loads
            AIPS.scanAllAIVariables();
        },

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
        // Template Events
        $(document).on('click', '.aips-add-template-btn', AIPS.openTemplateModal);
        $(document).on('click', '.aips-edit-template', AIPS.editTemplate);
        $(document).on('click', '.aips-clone-template', AIPS.cloneTemplate);
        $(document).on('click', '.aips-delete-template', AIPS.deleteTemplate);
        $(document).on('click', '.aips-save-template', AIPS.saveTemplate);
        $(document).on('click', '.aips-save-draft-template', AIPS.saveDraftTemplate);
        $(document).on('click', '.aips-test-template', AIPS.testTemplate);
        $(document).on('click', '.aips-run-now', AIPS.runNow);
        $(document).on('change', '#generate_featured_image', AIPS.toggleImagePrompt);
        $(document).on('change', '#featured_image_source', AIPS.toggleFeaturedImageSourceFields);
        $(document).on('click', '#featured_image_media_select', AIPS.openMediaLibrary);
        $(document).on('click', '#featured_image_media_clear', AIPS.clearMediaSelection);
        $(document).on('keyup', '#voice_search', AIPS.searchVoices);

        // Wizard navigation
        $(document).on('click', '.aips-wizard-next', AIPS.wizardNext);
        $(document).on('click', '.aips-wizard-back', AIPS.wizardBack);

        // Preview drawer
        $(document).on('click', '.aips-preview-prompts', AIPS.previewPrompts);
        $(document).on('click', '.aips-preview-drawer-handle', AIPS.togglePreviewDrawer);

        // AI Variables scanning
        $(document).on('input', '.aips-ai-var-input', function() {
            AIPS.scanAllAIVariables();
        });
        $(document).on('click', '.aips-ai-var-tag', AIPS.copyAIVariable);

        // Template Search
        $(document).on('keyup search', '#aips-template-search', AIPS.filterTemplates);
        $(document).on('click', '#aips-template-search-clear', AIPS.clearTemplateSearch);
        $(document).on('click', '.aips-clear-search-btn', AIPS.clearTemplateSearch);

        $(document).on('click', '.aips-view-template-posts', AIPS.openTemplatePostsModal);
        $(document).on('click', '.aips-modal-page', AIPS.paginateTemplatePosts);

        // Load voices on template page load
        if ($('#voice_search').length) {
            AIPS.searchVoices.call($('#voice_search'));
        }
    });

})(jQuery);
