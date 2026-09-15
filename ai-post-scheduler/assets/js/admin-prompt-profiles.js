/**
 * Prompt Profiles Admin JavaScript
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

/* global jQuery, aipsAjax, aipsPromptProfilesL10n, AIPS */

(function($) {
    'use strict';

    var PromptProfiles = {
        currentProfileId: null,
        activeStage: 'post_title',
        coreFallbacks: {},
        isDirty: false,
        previewTimeout: null,

        init: function() {
            this.bindEvents();
            this.loadCoreFallbacks();
        },

        bindEvents: function() {
            var self = this;

            // Add Profile Button
            $(document).on('click', '.aips-add-profile-btn, #aips-add-profile-btn', function(e) {
                e.preventDefault();
                self.openCreateModal();
            });

            // Edit Profile Button
            $(document).on('click', '.aips-edit-profile-btn', function(e) {
                e.preventDefault();
                var profileId = $(this).data('id');
                self.openEditModal(profileId);
            });

            // Clone Profile Button
            $(document).on('click', '.aips-clone-profile-btn', function(e) {
                e.preventDefault();
                var profileId = $(this).data('id');
                self.cloneProfile(profileId);
            });

            // Set Default Button
            $(document).on('click', '.aips-set-default-btn', function(e) {
                e.preventDefault();
                var profileId = $(this).data('id');
                self.setDefaultProfile(profileId);
            });

            // Delete Profile Button
            $(document).on('click', '.aips-delete-profile-btn', function(e) {
                e.preventDefault();
                var profileId = $(this).data('id');
                self.deleteProfile(profileId);
            });

            // Modal Close Buttons
            $(document).on('click', '#aips-prompt-profile-modal .aips-modal-close', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Stage Tab Navigation
            $(document).on('click', '.aips-stage-tabs-list li', function() {
                var stage = $(this).data('stage');
                self.switchStage(stage);
            });

            // Placeholder Chip Click -> Insert tag into active textarea
            $(document).on('click', '.aips-placeholder-chip', function(e) {
                e.preventDefault();
                var tag = $(this).data('tag');
                self.insertTagAtCursor(tag);
            });

            // Reset Stage to Core Default
            $(document).on('click', '.aips-reset-stage-btn', function(e) {
                e.preventDefault();
                var stage = $(this).data('stage');
                self.resetStagePrompt(stage);
            });

            // Form Submit / Save Profile
            $('#aips-prompt-profile-form').on('submit', function(e) {
                e.preventDefault();
                self.saveProfile();
            });

            // Track changes on textareas & inputs
            $('#aips-prompt-profile-form').on('input change', 'input, textarea', function() {
                self.setDirty(true);
                self.updateCustomizedIndicators();
                if ($('#aips-sandbox-body').is(':visible')) {
                    self.debouncePreview();
                }
            });

            // Sandbox Accordion Toggle
            $('#aips-toggle-sandbox').on('click', function() {
                var $body = $('#aips-sandbox-body');
                var $container = $('.aips-prompt-preview-sandbox');
                if ($body.is(':visible')) {
                    $body.slideUp(200);
                    $container.removeClass('is-expanded');
                } else {
                    $body.slideDown(200, function() {
                        $container.addClass('is-expanded');
                        self.fetchLivePreview();
                    });
                }
            });

            // Refresh Preview button
            $('#aips-refresh-preview-btn').on('click', function(e) {
                e.preventDefault();
                self.fetchLivePreview();
            });

            // Filter Bar Buttons
            $('.aips-filter-btn').on('click', function() {
                $('.aips-filter-btn').removeClass('is-active');
                $(this).addClass('is-active');
                self.applyFilters();
            });

            // Search Bar Input
            $('#aips-profile-search').on('input', function() {
                var query = $(this).val().trim();
                $('#aips-profile-search-clear').toggle(query.length > 0);
                self.applyFilters();
            });

            // Search Clear Button
            $('#aips-profile-search-clear, #aips-clear-search-btn').on('click', function() {
                $('#aips-profile-search').val('');
                $('#aips-profile-search-clear').hide();
                self.applyFilters();
            });
        },

        loadCoreFallbacks: function() {
            var self = this;
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_prompt_profiles',
                    nonce: aipsPromptProfilesL10n.nonce
                },
                success: function(response) {
                    if (response.success && response.data.core_fallbacks) {
                        self.coreFallbacks = response.data.core_fallbacks;
                    }
                }
            });
        },

        openCreateModal: function() {
            var $form = $('#aips-prompt-profile-form');
            $form[0].reset();
            $('#aips_profile_id').val('');
            $('#aips_profile_is_default').val('0');
            $('#aips-prompt-profile-modal-title').text(aipsPromptProfilesL10n.createProfile);
            $('#aips-modal-profile-badge').hide();
            this.currentProfileId = null;
            this.switchStage('post_title');
            this.setDirty(false);
            this.updateCustomizedIndicators();
            $('#aips-preview-output-box').text('Click refresh or type above to assemble preview...');
            $('#aips-prompt-profile-modal').fadeIn(200);
        },

        openEditModal: function(profileId) {
            var self = this;
            self.currentProfileId = profileId;

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_prompt_profile',
                    id: profileId,
                    nonce: aipsPromptProfilesL10n.nonce
                },
                beforeSend: function() {
                    $('#aips-prompt-profile-modal-title').text(aipsPromptProfilesL10n.editProfile + ' (Loading...)');
                    $('#aips-prompt-profile-modal').fadeIn(200);
                },
                success: function(response) {
                    if (response.success && response.data.profile) {
                        var p = response.data.profile;
                        $('#aips_profile_id').val(p.id);
                        $('#aips_profile_name').val(p.name);
                        $('#aips_profile_slug').val(p.slug);
                        $('#aips_profile_description').val(p.description || '');
                        $('#aips_profile_is_default').val(p.is_default ? '1' : '0');

                        // Populate stages
                        var stages = p.stages || {};
                        $('#stage_post_title').val(stages.post_title || '');
                        $('#stage_post_title_followup').val(stages.post_title_followup || '');
                        $('#stage_post_content').val(stages.post_content || '');
                        $('#stage_post_excerpt').val(stages.post_excerpt || '');
                        $('#stage_post_excerpt_followup').val(stages.post_excerpt_followup || '');
                        $('#stage_featured_image').val(stages.featured_image || '');
                        $('#stage_topic_idea').val(stages.topic_idea || '');
                        $('#stage_seo_metadata').val(stages.seo_metadata || '');
                        $('#stage_taxonomy').val(stages.taxonomy || '');

                        $('#aips-prompt-profile-modal-title').text(aipsPromptProfilesL10n.editProfile + ': ' + p.name);

                        var $badge = $('#aips-modal-profile-badge');
                        if (p.is_builtin) {
                            $badge.text(aipsPromptProfilesL10n.builtinBadge).removeClass('aips-badge-neutral').addClass('aips-badge-info').show();
                        } else if (p.is_default) {
                            $badge.text(aipsPromptProfilesL10n.defaultBadge).removeClass('aips-badge-neutral').addClass('aips-badge-success').show();
                        } else {
                            $badge.text(aipsPromptProfilesL10n.customBadge).removeClass('aips-badge-info aips-badge-success').addClass('aips-badge-neutral').show();
                        }

                        self.switchStage(self.activeStage || 'post_title');
                        self.setDirty(false);
                        self.updateCustomizedIndicators();
                    } else {
                        self.notify('error', (response.data && response.data.message) || aipsPromptProfilesL10n.errorLoading);
                        self.closeModal();
                    }
                },
                error: function() {
                    self.notify('error', aipsPromptProfilesL10n.errorLoading);
                    self.closeModal();
                }
            });
        },

        closeModal: function() {
            $('#aips-prompt-profile-modal').fadeOut(200);
            this.setDirty(false);
        },

        switchStage: function(stage) {
            this.activeStage = stage;
            $('.aips-stage-tabs-list li').removeClass('is-active').filter('[data-stage="' + stage + '"]').addClass('is-active');
            $('.aips-stage-panel').removeClass('is-active').filter('[data-stage-panel="' + stage + '"]').addClass('is-active');

            if ($('#aips-sandbox-body').is(':visible')) {
                this.fetchLivePreview();
            }
        },

        insertTagAtCursor: function(tag) {
            var activePanel = $('.aips-stage-panel.is-active');
            var textarea = activePanel.find('.aips-stage-textarea')[0];
            if (!textarea) {
                return;
            }

            var startPos = textarea.selectionStart;
            var endPos = textarea.selectionEnd;
            var text = textarea.value;

            textarea.value = text.substring(0, startPos) + tag + text.substring(endPos, text.length);
            textarea.selectionStart = startPos + tag.length;
            textarea.selectionEnd = startPos + tag.length;
            textarea.focus();

            this.setDirty(true);
            this.updateCustomizedIndicators();
            this.debouncePreview();
            this.notify('info', aipsPromptProfilesL10n.copiedChip);
        },

        resetStagePrompt: function(stage) {
            if (!confirm(aipsPromptProfilesL10n.confirmResetStage)) {
                return;
            }

            var fallback = this.coreFallbacks[stage] || '';
            var $textarea = $('#stage_' + stage);
            $textarea.val(fallback);
            this.setDirty(true);
            this.updateCustomizedIndicators();
            this.debouncePreview();
        },

        updateCustomizedIndicators: function() {
            var stages = ['post_title', 'post_title_followup', 'post_content', 'post_excerpt', 'post_excerpt_followup', 'featured_image', 'topic_idea', 'seo_metadata', 'taxonomy'];
            stages.forEach(function(stg) {
                var val = $('#stage_' + stg).val();
                var hasCustom = val && val.trim().length > 0;
                $('.aips-stage-indicator[data-stage="' + stg + '"]').toggleClass('is-customized', !!hasCustom);
            });
        },

        saveProfile: function() {
            var self = this;
            var name = $('#aips_profile_name').val().trim();
            if (!name) {
                self.notify('error', aipsPromptProfilesL10n.nameRequired);
                $('#aips_profile_name').focus();
                return;
            }

            var formData = {
                action: 'aips_save_prompt_profile',
                nonce: aipsPromptProfilesL10n.nonce,
                id: $('#aips_profile_id').val(),
                name: name,
                slug: $('#aips_profile_slug').val().trim(),
                description: $('#aips_profile_description').val().trim(),
                is_default: $('#aips_profile_is_default').val(),
                stages: {
                    post_title: $('#stage_post_title').val(),
                    post_title_followup: $('#stage_post_title_followup').val(),
                    post_content: $('#stage_post_content').val(),
                    post_excerpt: $('#stage_post_excerpt').val(),
                    post_excerpt_followup: $('#stage_post_excerpt_followup').val(),
                    featured_image: $('#stage_featured_image').val(),
                    topic_idea: $('#stage_topic_idea').val(),
                    seo_metadata: $('#stage_seo_metadata').val(),
                    taxonomy: $('#stage_taxonomy').val()
                }
            };

            var $btn = $('#aips-save-profile-btn');
            $btn.prop('disabled', true).text(aipsPromptProfilesL10n.saving);

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> ' + aipsPromptProfilesL10n.saveProfile);
                    if (response.success) {
                        self.notify('success', aipsPromptProfilesL10n.profileSaved);
                        self.closeModal();
                        setTimeout(function() {
                            window.location.reload();
                        }, 600);
                    } else {
                        self.notify('error', (response.data && response.data.message) || aipsPromptProfilesL10n.errorSaving);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> ' + aipsPromptProfilesL10n.saveProfile);
                    self.notify('error', aipsPromptProfilesL10n.errorSaving);
                }
            });
        },

        cloneProfile: function(profileId) {
            var self = this;
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_clone_prompt_profile',
                    id: profileId,
                    nonce: aipsPromptProfilesL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.notify('success', aipsPromptProfilesL10n.profileCloned);
                        setTimeout(function() {
                            window.location.reload();
                        }, 600);
                    } else {
                        self.notify('error', (response.data && response.data.message) || aipsPromptProfilesL10n.errorCloning);
                    }
                },
                error: function() {
                    self.notify('error', aipsPromptProfilesL10n.errorCloning);
                }
            });
        },

        setDefaultProfile: function(profileId) {
            var self = this;
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_set_default_prompt_profile',
                    id: profileId,
                    nonce: aipsPromptProfilesL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.notify('success', aipsPromptProfilesL10n.defaultSet);
                        setTimeout(function() {
                            window.location.reload();
                        }, 600);
                    } else {
                        self.notify('error', (response.data && response.data.message) || aipsPromptProfilesL10n.errorSaving);
                    }
                },
                error: function() {
                    self.notify('error', aipsPromptProfilesL10n.errorSaving);
                }
            });
        },

        deleteProfile: function(profileId) {
            var self = this;
            if (!confirm(aipsPromptProfilesL10n.confirmDelete)) {
                return;
            }

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_delete_prompt_profile',
                    id: profileId,
                    nonce: aipsPromptProfilesL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.notify('success', aipsPromptProfilesL10n.profileDeleted);
                        $('tr[data-profile-id="' + profileId + '"]').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        self.notify('error', (response.data && response.data.message) || aipsPromptProfilesL10n.errorDeleting);
                    }
                },
                error: function() {
                    self.notify('error', aipsPromptProfilesL10n.errorDeleting);
                }
            });
        },

        debouncePreview: function() {
            var self = this;
            clearTimeout(self.previewTimeout);
            self.previewTimeout = setTimeout(function() {
                self.fetchLivePreview();
            }, 400);
        },

        fetchLivePreview: function() {
            var self = this;
            var stage = self.activeStage;
            var promptText = $('#stage_' + stage).val();
            var sampleTopic = $('#aips_preview_sample_topic').val() || '10 Proven Strategies for Sustainable Organic Gardening';
            var sampleVoice = $('#aips_preview_sample_voice').val() || 'Conversational and authoritative.';

            var sampleContext = {
                topic: sampleTopic,
                voice: sampleVoice,
                article_data: 'Title: ' + sampleTopic + '\nOutline: 1. Soil Health 2. Watering 3. Pest Control',
                word_count_min: 1200,
                word_count_max: 2000,
                diversity_blocks: 'Include specific gardening examples and varied sentence cadence.'
            };

            $('#aips-preview-output-box').text(aipsPromptProfilesL10n.previewGenerating);

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_preview_prompt_profile',
                    nonce: aipsPromptProfilesL10n.nonce,
                    stage: stage,
                    prompt_template: promptText,
                    sample_context: sampleContext
                },
                success: function(response) {
                    if (response.success && response.data.preview_prompt) {
                        $('#aips-preview-output-box').text(response.data.preview_prompt);
                    } else {
                        $('#aips-preview-output-box').text(aipsPromptProfilesL10n.errorPreview);
                    }
                },
                error: function() {
                    $('#aips-preview-output-box').text(aipsPromptProfilesL10n.errorPreview);
                }
            });
        },

        applyFilters: function() {
            var activeFilter = $('.aips-filter-btn.is-active').data('filter') || 'all';
            var query = ($('#aips-profile-search').val() || '').toLowerCase();
            var visibleCount = 0;

            $('#aips-prompt-profiles-table tbody tr').each(function() {
                var $row = $(this);
                var isBuiltin = $row.data('is-builtin') === 1 || $row.data('is-builtin') === '1';
                var text = $row.text().toLowerCase();

                var matchesFilter = true;
                if (activeFilter === 'builtin' && !isBuiltin) {
                    matchesFilter = false;
                } else if (activeFilter === 'custom' && isBuiltin) {
                    matchesFilter = false;
                }

                var matchesSearch = !query || text.indexOf(query) !== -1;

                if (matchesFilter && matchesSearch) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });

            $('#aips-profile-search-no-results').toggle(visibleCount === 0);
            $('#aips-prompt-profiles-table').toggle(visibleCount > 0);
        },

        setDirty: function(dirty) {
            this.isDirty = dirty;
            $('#aips-unsaved-changes-indicator').toggle(dirty);
        },

        notify: function(type, message) {
            if (typeof AIPS !== 'undefined' && AIPS.Utilities && typeof AIPS.Utilities.showNotification === 'function') {
                AIPS.Utilities.showNotification(message, type);
            } else {
                alert(message);
            }
        }
    };

    $(document).ready(function() {
        PromptProfiles.init();
    });

})(jQuery);
