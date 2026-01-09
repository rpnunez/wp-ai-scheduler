(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with Templates functionality
    Object.assign(window.AIPS, {

        /**
         * Open modal to add a new template
         */
        openTemplateModal: function(e) {
            e.preventDefault();

            $('#aips-template-form')[0].reset();
            $('#template_id').val('');
            $('#aips-modal-title').text(aipsAdminL10n.addTemplate);
            $('#featured_image_source').val('ai_prompt');
            $('#featured_image_unsplash_keywords').val('');

            AIPS.setTemplateMediaSelection([]);
            AIPS.toggleImagePrompt();

            $('#aips-template-modal').show();
        },

        /**
         * Edit an existing template
         */
        editTemplate: function(e) {
            e.preventDefault();

            var id = $(this).data('id');
            var $btn = $(this);

            $btn.prop('disabled', true);

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_get_template',
                    nonce: AIPS.resolveNonce(),
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
                        $('#generate_featured_image').prop('checked', t.generate_featured_image === 1);
                        $('#image_prompt').val(t.image_prompt || '');
                        $('#featured_image_source').val(t.featured_image_source || 'ai_prompt');
                        $('#featured_image_unsplash_keywords').val(t.featured_image_unsplash_keywords || '');

                        AIPS.setTemplateMediaSelection(t.featured_image_media_ids || '');

                        $('#post_status').val(t.post_status);
                        $('#post_category').val(t.post_category);
                        $('#post_tags').val(t.post_tags);
                        $('#post_author').val(t.post_author);
                        $('#is_active').prop('checked', t.is_active === 1);

                        AIPS.toggleImagePrompt();
                        AIPS.toggleFeaturedImageSourceFields();

                        $('#aips-modal-title').text(aipsAdminL10n.editTemplate);
                        $('#aips-template-modal').show();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert(aipsAdminL10n.errorTryAgain);
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Delete a template (with soft-confirm pattern)
         */
        deleteTemplate: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var id = $btn.data('id');
            var $row = $btn.closest('tr');

            // Soft Confirm Pattern
            if (!$btn.data('is-confirming')) {
                $btn.data('original-text', $btn.text());
                $btn.text(aipsAdminL10n.clickAgainConfirm);
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
            $btn.prop('disabled', true).text(aipsAdminL10n.deleting);

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_delete_template',
                    nonce: AIPS.resolveNonce(),
                    template_id: id
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || aipsAdminL10n.errorOccurred);
                        // Reset button state on error
                        $btn.text($btn.data('original-text'));
                        $btn.removeClass('aips-confirm-delete');
                        $btn.data('is-confirming', false);
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(aipsAdminL10n.errorTryAgain);
                    // Reset button state on error
                    $btn.text($btn.data('original-text'));
                    $btn.removeClass('aips-confirm-delete');
                    $btn.data('is-confirming', false);
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Save template (create or update)
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
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_save_template',
                    nonce: AIPS.resolveNonce(),
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
                    alert(aipsAdminL10n.errorTryAgain);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Template');
                }
            });
        },

        /**
         * Test template generation with AI
         */
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
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_test_template',
                    nonce: AIPS.resolveNonce(),
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
                    alert(aipsAdminL10n.errorTryAgain);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Test Generate');
                }
            });
        },

        /**
         * Toggle featured image prompt fields visibility
         */
        toggleImagePrompt: function() {
            var isChecked = $('#generate_featured_image').is(':checked');
            $('.aips-featured-image-settings').toggle(isChecked);
            $('#featured_image_source').prop('disabled', !isChecked);

            if (isChecked) {
                AIPS.toggleFeaturedImageSourceFields();
            }
        },

        /**
         * Toggle featured image source-specific fields
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
         * Set media library selection for template featured images
         */
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

        /**
         * Open WordPress media library for template featured image selection
         */
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

        /**
         * Clear template media library selection
         */
        clearTemplateMediaSelection: function(e) {
            if (e) {
                e.preventDefault();
            }
            AIPS.setTemplateMediaSelection([]);
        },

        /**
         * Filter templates list by search term
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
         * Clear template search input and show all templates
         */
        clearTemplateSearch: function(e) {
            e.preventDefault();
            $('#aips-template-search').val('').trigger('keyup');
        },

        /**
         * Open modal showing posts generated from a template
         */
        openTemplatePostsModal: function(e) {
            e.preventDefault();
            var id = $(this).data('id');

            $('#aips-template-posts-modal').data('template-id', id).show();
            AIPS.loadTemplatePosts(id, 1);
        },

        /**
         * Paginate through template posts
         */
        paginateTemplatePosts: function(e) {
            e.preventDefault();

            var page = $(this).data('page');
            var id = $('#aips-template-posts-modal').data('template-id');
            AIPS.loadTemplatePosts(id, page);
        },

        /**
         * Load template posts via AJAX
         */
        loadTemplatePosts: function(id, page) {
            $('#aips-template-posts-content').html('<p class="aips-loading">Loading...</p>');

            $.ajax({
                url: AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_get_template_posts',
                    nonce: AIPS.resolveNonce(),
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
        }
    });

    // Bind Template Events on DOM Ready
    $(document).ready(function() {
        // Only initialize if we're on a templates page
        if ($('.aips-templates-list').length || $('#aips-template-form').length) {
            // Template CRUD
            $(document).on('click', '.aips-add-template-btn', window.AIPS.openTemplateModal);
            $(document).on('click', '.aips-edit-template', window.AIPS.editTemplate);
            $(document).on('click', '.aips-delete-template', window.AIPS.deleteTemplate);
            $(document).on('click', '.aips-save-template', window.AIPS.saveTemplate);
            $(document).on('click', '.aips-test-template', window.AIPS.testTemplate);
            $(document).on('click', '.aips-view-template-posts', window.AIPS.openTemplatePostsModal);
            $(document).on('click', '.aips-modal-page', window.AIPS.paginateTemplatePosts);

            // Media / Image controls
            $(document).on('change', '#generate_featured_image', window.AIPS.toggleImagePrompt);
            $(document).on('change', '#featured_image_source', window.AIPS.toggleFeaturedImageSourceFields);
            $(document).on('click', '#featured_image_media_select', window.AIPS.openMediaLibraryForTemplate);
            $(document).on('click', '#featured_image_media_clear', window.AIPS.clearTemplateMediaSelection);

            // Template search
            $(document).on('keyup search', '#aips-template-search', window.AIPS.filterTemplates);
            $(document).on('click', '#aips-template-search-clear', window.AIPS.clearTemplateSearch);
            $(document).on('click', '.aips-clear-search-btn', window.AIPS.clearTemplateSearch);
        }
    });

})(jQuery);

