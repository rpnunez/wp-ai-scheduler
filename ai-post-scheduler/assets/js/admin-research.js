(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    Object.assign(AIPS, {
        /**
         * Topic IDs selected in the Trending Topics table.
         *
         * @type {string[]}
         */
        researchSelectedTopics: [],

        /**
         * Initialize the research admin module.
         *
         * Binds all delegated listeners and auto-loads topics when the page is
         * available.
         */
        initResearch: function() {
            AIPS.bindResearchEvents();

            if ($('#load-topics').length) {
                $('#load-topics').trigger('click');
            }
        },

        /**
         * Register delegated event handlers for the research admin UI.
         */
        bindResearchEvents: function() {
            $(document).on('submit', '#aips-research-form', AIPS.submitResearchForm);
            $(document).on('click', '#load-topics', AIPS.loadTopics);
            $(document).on('keyup search', '#filter-search', AIPS.filterTopics);
            $(document).on('click', '#filter-search-clear, #clear-topics-search', AIPS.clearTopicsSearch);
            $(document).on('change', '#select-all-topics', AIPS.toggleAllTopics);
            $(document).on('change', '.topic-checkbox', AIPS.toggleTopicSelection);
            $(document).on('click', '.delete-topic', AIPS.deleteTopic);
            $(document).on('submit', '#bulk-schedule-form', AIPS.submitBulkSchedule);
            $(document).on('click', '#aips-clear-filters', AIPS.clearTopicFilters);
            $(document).on('click', '#aips-start-research', AIPS.focusResearchForm);
            $(document).on('click', '#analyze-gaps-btn', AIPS.analyzeGaps);
            $(document).on('click', '.generate-gap-ideas', AIPS.generateGapIdeas);
            $(document).on('click', '#aips-delete-selected-topics', AIPS.bulkDeleteSelectedTopics);
            $(document).on('click', '#aips-schedule-selected-topics', AIPS.scheduleSelectedTopics);
            $(document).on('click', '#aips-generate-selected-topics', AIPS.bulkGenerateSelectedTopics);
            $(document).on('click', '.aips-post-count-badge', AIPS.viewTrendingTopicPosts);
            $(document).on('click', '#aips-reload-topics-btn', AIPS.reloadTopics);
        },

        /**
         * Escape a value for safe insertion into HTML content.
         *
         * @param {*} text Value to escape.
         * @returns {string} Escaped HTML-safe text.
         */
        escapeHtml: function(text) {
            if (text === null || text === undefined) {
                return '';
            }

            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        /**
         * Handle submission of the "New Research" form.
         *
         * @param {Event} e Submit event.
         */
        submitResearchForm: function(e) {
            e.preventDefault();

            var $form = $(e.currentTarget);
            var $submit = $('#research-submit');
            var $spinner = $form.find('.spinner');

            var niche = $('#research-niche').val();
            var count = $('#research-count').val();
            var keywordsStr = $('#research-keywords').val();
            var keywords = keywordsStr ? keywordsStr.split(',').map(function(k) { return k.trim(); }) : [];

            $submit.prop('disabled', true).addClass('is-loading');
            $spinner.addClass('is-active');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aips_research_topics',
                    nonce: $('#aips_nonce').val(),
                    niche: niche,
                    count: count,
                    keywords: keywords
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.renderResearchResults(response.data);
                        $('#load-topics').trigger('click');
                    } else {
                        AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsResearchL10n.researchError, 'error');
                },
                complete: function() {
                    $submit.prop('disabled', false).removeClass('is-loading');
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Render the research results summary panel.
         *
         * @param {Object} data Response payload from research AJAX action.
         */
        renderResearchResults: function(data) {
            var $container = $('#research-results-content');
            var topTopicsBlockHtml = '';
            var esc = AIPS.Templates ? AIPS.Templates.escape : function(str) { return String(str || ''); };

            if (AIPS.Templates && data.top_topics && data.top_topics.length > 0) {
                var itemsHtml = data.top_topics.map(function(topic) {
                    var scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                    var reasonHtml = topic.reason
                        ? AIPS.Templates.render('aips-tmpl-research-top-topic-reason', { reason: topic.reason })
                        : '';

                    return AIPS.Templates.renderRaw('aips-tmpl-research-top-topic-item', {
                        topic: esc(topic.topic),
                        score_class: esc(scoreClass),
                        score: esc(topic.score),
                        reason_html: reasonHtml
                    });
                }).join('');

                topTopicsBlockHtml = AIPS.Templates.renderRaw('aips-tmpl-research-top-topics-block', {
                    top_topics_label: esc(aipsResearchL10n.topTopics),
                    items_html: itemsHtml
                });
            }

            var html = AIPS.Templates
                ? AIPS.Templates.renderRaw('aips-tmpl-research-results-summary', {
                    saved_count: esc(data.saved_count),
                    topics_saved: esc(aipsResearchL10n.topicsSaved),
                    niche: esc(data.niche),
                    top_topics_block_html: topTopicsBlockHtml
                })
                : '';

            $container.html(html || '');
            $('#research-results').slideDown();
        },

        /**
         * Load filtered trending topics from the server.
         *
         * @param {Event} e Click event.
         */
        loadTopics: function(e) {
            if (e) {
                e.preventDefault();
            }

            var niche = $('#filter-niche').val();
            var minScore = $('#filter-score').val();
            var freshOnly = $('#filter-fresh').is(':checked');
            var includeUsed = $('#filter-include-used').is(':checked');
            var status = includeUsed ? 'all' : 'new';

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aips_get_trending_topics',
                    nonce: $('#aips_nonce').val(),
                    niche: niche,
                    min_score: minScore,
                    fresh_only: freshOnly ? 'true' : 'false',
                    status: status,
                    limit: 50
                },
                success: function(response) {
                    if (response.success && response.data && response.data.topics) {
                        AIPS.renderTopicsTable(response.data.topics);
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                        AIPS.Utilities.showToast('Error: ' + errorMsg, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    AIPS.Utilities.showToast('Error loading topics: ' + error, 'error');
                }
            });
        },

        /**
         * Render the topics table or empty state.
         *
         * @param {Array<Object>} topics Topics returned from AJAX.
         */
        renderTopicsTable: function(topics) {
            var html = '';
            var niche = $('#filter-niche').val();
            var minScore = $('#filter-score').val();
            var freshOnly = $('#filter-fresh').is(':checked');
            var includeUsed = $('#filter-include-used').is(':checked');
            var isFiltered = niche || minScore !== '0' || freshOnly || includeUsed;
            var esc = AIPS.Templates ? AIPS.Templates.escape : function(str) { return String(str || ''); };

            AIPS.researchSelectedTopics = [];
            AIPS.updateSelectedTopics();

            if (!topics || topics.length === 0) {
                if (AIPS.Templates) {
                    html = AIPS.Templates.render('aips-tmpl-research-empty-state', {
                        title: isFiltered ? aipsResearchL10n.noTopicsFound : (aipsResearchL10n.libraryEmpty || aipsResearchL10n.noTopicsFound),
                        description: isFiltered ? aipsResearchL10n.noTopicsFound : (aipsResearchL10n.libraryEmpty || aipsResearchL10n.noTopicsFound),
                        button_id: isFiltered ? 'aips-clear-filters' : 'aips-start-research',
                        button_class: isFiltered ? 'aips-btn-secondary' : 'aips-btn-primary',
                        button_label: isFiltered
                            ? (aipsResearchL10n.clearFilters || aipsResearchL10n.clearSearch || 'Clear Filters')
                            : (aipsResearchL10n.startResearch || 'Start Research')
                    });
                }

                $('#topics-container').html(html);
                $('#bulk-schedule-section').hide();
                $('#topics-tablenav').hide();
                return;
            }

            if (AIPS.Templates) {
                var rowsHtml = topics.map(function(topic) {
                    var scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                    var keywords = Array.isArray(topic.keywords) ? topic.keywords : [];
                    var keywordsHtml = keywords.map(function(kw) {
                        return AIPS.Templates.render('aips-tmpl-research-keyword-tag', { keyword: kw });
                    }).join('');

                    var reasonHtml = topic.reason
                        ? AIPS.Templates.render('aips-tmpl-research-topic-reason', { reason: topic.reason })
                        : '';
                    
                    var generatedPostCount = parseInt(topic.generated_post_count || 0, 10);
                    var postCountBadgeHtml = generatedPostCount > 0
                        ? AIPS.Templates.render('aips-tmpl-research-topic-post-count-badge', {
                            topic_id: esc(topic.id),
                            count: esc(generatedPostCount)
                        })
                        : '';

                    var statusLabel = (topic.status || 'new').toLowerCase();
                    var statusLabelText = {
                        'new': aipsResearchL10n.statusNew || 'New',
                        'scheduled': aipsResearchL10n.statusScheduled || 'Scheduled',
                        'generated': aipsResearchL10n.statusGenerated || 'Generated'
                    }[statusLabel] || statusLabel;
                    var statusChipHtml = AIPS.Templates.render('aips-tmpl-research-topic-status-chip', {
                        status: esc(statusLabel),
                        status_label: esc(statusLabelText)
                    });

                    return AIPS.Templates.renderRaw('aips-tmpl-research-topics-row', {
                        id: esc(topic.id),
                        topic: esc(topic.topic),
                        status_chip_html: statusChipHtml,
                        post_count_badge_html: postCountBadgeHtml,
                        reason_html: reasonHtml,
                        score_class: esc(scoreClass),
                        score: esc(topic.score),
                        niche: esc(topic.niche),
                        keywords_html: keywordsHtml,
                        researched_at: esc(new Date(topic.researched_at).toLocaleDateString()),
                        delete_label: esc(aipsResearchL10n.delete)
                    });
                }).join('');

                var searchEmptyHtml = AIPS.Templates.render('aips-tmpl-research-topics-search-empty', {
                    title: aipsResearchL10n.noTopicsFoundTitle,
                    description: aipsResearchL10n.noTopicsFound,
                    clear_label: aipsResearchL10n.clearSearch
                });

                html = AIPS.Templates.renderRaw('aips-tmpl-research-topics-table', {
                    rows_html: rowsHtml,
                    search_empty_html: searchEmptyHtml
                });
            }

            $('#topics-container').html(html);

            $('#topics-count').text(topics.length + ' ' + (topics.length === 1 ? 'topic' : 'topics'));
            $('#topics-tablenav').show();
            $('#bulk-schedule-section').hide();

            if ($('#filter-search').val()) {
                AIPS.filterTopics();
            }
        },

        /**
         * Apply the client-side search filter to the rendered topics table.
         *
         * @param {Event} e Key/search event.
         */
        filterTopics: function(e) {
            if (e) {
                e.preventDefault();
            }

            var query = $('#filter-search').val().toLowerCase();
            var $rows = $('.aips-research-table tbody tr');
            var visibleCount = 0;

            if (query.length > 0) {
                $('#filter-search-clear').show();
            } else {
                $('#filter-search-clear').hide();
            }

            $rows.each(function() {
                var topicText = $(this).find('td:nth-child(2)').text().toLowerCase();
                if (topicText.indexOf(query) > -1) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });

            if (visibleCount === 0 && $rows.length > 0) {
                $('.aips-research-table').hide();
                $('#topics-search-empty').show();
            } else {
                $('.aips-research-table').show();
                $('#topics-search-empty').hide();
            }
        },

        /**
         * Clear the topics search input and restore all rows.
         *
         * @param {Event} e Click event.
         */
        clearTopicsSearch: function(e) {
            e.preventDefault();
            $('#filter-search').val('').trigger('search').focus();
        },

        /**
         * Toggle all visible topic checkboxes from the header checkbox.
         *
         * @param {Event} e Change event.
         */
        toggleAllTopics: function(e) {
            var isChecked = $(e.currentTarget).is(':checked');
            $('.topic-checkbox:visible').prop('checked', isChecked);
            AIPS.updateSelectedTopics();
        },

        /**
         * Recompute selected topics when an individual checkbox changes.
         *
         * @param {Event} e Change event.
         */
        toggleTopicSelection: function(e) {
            e.preventDefault();
            AIPS.updateSelectedTopics();
        },

        /**
         * Refresh selected-topic state and related action-button availability.
         */
        updateSelectedTopics: function() {
            AIPS.researchSelectedTopics = $('.topic-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            var hasSelected = AIPS.researchSelectedTopics.length > 0;
            $('#aips-delete-selected-topics').prop('disabled', !hasSelected);
            $('#aips-schedule-selected-topics').prop('disabled', !hasSelected);
            $('#aips-generate-selected-topics').prop('disabled', !hasSelected);
        },

        /**
         * Delete a single topic from the table.
         *
         * @param {Event} e Click event.
         */
        deleteTopic: function(e) {
            e.preventDefault();

            var $el = $(e.currentTarget);
            var topicId = $el.data('id');

            AIPS.Utilities.confirm(aipsResearchL10n.deleteTopicConfirm, 'Notice', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                {
                    label: 'Yes, delete',
                    className: 'aips-btn aips-btn-danger-solid',
                    action: function() {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'aips_delete_trending_topic',
                                nonce: $('#aips_nonce').val(),
                                topic_id: topicId
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('#load-topics').trigger('click');
                                } else {
                                    AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
                                }
                            }
                        });
                    }
                }
            ]);
        },

        /**
         * Submit bulk schedule action for selected topics.
         *
         * @param {Event} e Submit event.
         */
        submitBulkSchedule: function(e) {
            e.preventDefault();

            if (AIPS.researchSelectedTopics.length === 0) {
                AIPS.Utilities.showToast(aipsResearchL10n.selectTopicSchedule, 'warning');
                return;
            }

            var $form = $(e.currentTarget);
            var $submit = $form.find('button[type="submit"]');
            var $spinner = $form.find('.spinner');

            $submit.prop('disabled', true).addClass('is-loading');
            $spinner.addClass('is-active');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aips_schedule_trending_topics',
                    nonce: $('#aips_nonce').val(),
                    topic_ids: AIPS.researchSelectedTopics,
                    template_id: $('#schedule-template').val(),
                    start_date: $('#schedule-start-date').val(),
                    frequency: $('#schedule-frequency').val()
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success');
                        AIPS.researchSelectedTopics = [];
                        $('.topic-checkbox').prop('checked', false);
                        $('#select-all-topics').prop('checked', false);
                        AIPS.updateSelectedTopics();
                        $('#bulk-schedule-section').hide();
                        $('#load-topics').trigger('click');
                    } else {
                        AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsResearchL10n.schedulingError, 'error');
                },
                complete: function() {
                    $submit.prop('disabled', false).removeClass('is-loading');
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Reset topic filters to defaults and reload the list.
         *
         * @param {Event} e Click event.
         */
        clearTopicFilters: function(e) {
            e.preventDefault();
            $('#filter-niche').val('');
            $('#filter-score').val('0');
            $('#filter-fresh').prop('checked', false);
            $('#filter-include-used').prop('checked', false);
            $('#load-topics').trigger('click');
        },

        /**
         * Scroll to the research form and focus the niche field.
         *
         * @param {Event} e Click event.
         */
        focusResearchForm: function(e) {
            e.preventDefault();

            var $form = $('#aips-research-form');
            if (!$form.length) {
                return;
            }

            $('html, body').animate({
                scrollTop: $form.offset().top - 50
            }, 500);

            var $nicheField = $('#research-niche');
            if ($nicheField.length) {
                $nicheField.focus();
            }
        },

        /**
         * Analyze site content gaps for the requested niche.
         *
         * @param {Event} e Click event.
         */
        analyzeGaps: function(e) {
            e.preventDefault();

            var niche = $('#gap-niche').val();
            var $btn = $(e.currentTarget);
            var $spinner = $btn.next('.spinner');

            if (!niche) {
                AIPS.Utilities.showToast('Please enter a target niche.', 'warning');
                return;
            }

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aips_perform_gap_analysis',
                    nonce: $('#aips_nonce').val(),
                    niche: niche
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.renderGapResults(response.data.gaps);
                    } else {
                        AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred during gap analysis.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Render cards for gap-analysis results.
         *
         * @param {Array<Object>} gaps Gap records from the server.
         */
        renderGapResults: function(gaps) {
            var $container = $('#gap-results-container');
            var $grid = $container.find('.aips-gap-grid');
            $grid.empty();

            if (!gaps || gaps.length === 0) {
                if (AIPS.Templates) {
                    $grid.html(AIPS.Templates.renderRaw('aips-tmpl-research-gap-empty', {}));
                } else {
                    $grid.html('<p>No gaps found.</p>');
                }
                $container.show();
                return;
            }

            gaps.forEach(function(gap) {
                var priorityClass = (gap.priority || 'Medium').toLowerCase();

                if (AIPS.Templates) {
                    $grid.append(AIPS.Templates.render('aips-tmpl-research-gap-card', {
                        priority_class: priorityClass,
                        priority: gap.priority || 'Medium',
                        missing_topic: gap.missing_topic,
                        reason: gap.reason,
                        search_intent: gap.search_intent,
                        generate_ideas_label: (aipsResearchL10n.generateIdeas || 'Generate Ideas')
                    }));
                } else {
                    var cardHtml = '';
                    cardHtml += '<div class="aips-gap-card priority-' + AIPS.escapeHtml(priorityClass) + '">';
                    cardHtml += '<span class="aips-gap-badge ' + AIPS.escapeHtml(priorityClass) + '">' + AIPS.escapeHtml(gap.priority) + ' Priority</span>';
                    cardHtml += '<h4>' + AIPS.escapeHtml(gap.missing_topic) + '</h4>';
                    cardHtml += '<p class="aips-gap-reason">' + AIPS.escapeHtml(gap.reason) + '</p>';
                    cardHtml += '<p class="aips-gap-intent">Intent: ' + AIPS.escapeHtml(gap.search_intent) + '</p>';
                    cardHtml += '<div class="aips-gap-actions">';
                    cardHtml += '<button class="aips-btn aips-btn-sm aips-btn-secondary generate-gap-ideas" data-topic="' + AIPS.escapeHtml(gap.missing_topic) + '">' + (aipsResearchL10n.generateIdeas || 'Generate Ideas') + '</button>';
                    cardHtml += '</div></div>';
                    $grid.append(cardHtml);
                }
            });

            $container.slideDown();
        },

        /**
         * Generate topic ideas from a selected gap card.
         *
         * @param {Event} e Click event.
         */
        generateGapIdeas: function(e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var topic = $btn.data('topic');
            var niche = $('#gap-niche').val();

            $btn.prop('disabled', true).text(aipsResearchL10n.generatingIdeas || 'Generating...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aips_generate_topics_from_gap',
                    nonce: $('#aips_nonce').val(),
                    gap_topic: topic,
                    niche: niche
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success');
                        $('.aips-tab-link[data-tab="trending"]').trigger('click');
                        setTimeout(function() {
                            $('#load-topics').trigger('click');
                        }, 500);
                    } else {
                        AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred while generating topics.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(aipsResearchL10n.generateIdeas || 'Generate Ideas');
                }
            });
        },

        /**
         * Open modal to view posts generated from a trending topic.
         *
         * @param {Event} e Click event.
         */
        viewTrendingTopicPosts: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var topicId = $(e.currentTarget).data('topic-id');

            if (!topicId) {
                return;
            }

            $('#aips-trending-topic-posts-content').html('<p>' + (aipsResearchL10n.loadingPosts || 'Loading posts...') + '</p>');
            $('#aips-trending-topic-posts-modal').fadeIn();

            AIPS.loadTrendingTopicPosts(topicId);
        },

        /**
         * Fetch generated posts for a trending topic.
         *
         * @param {number} topicId Trending topic ID.
         */
        loadTrendingTopicPosts: function(topicId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aips_get_trending_topic_posts',
                    nonce: $('#aips_nonce').val(),
                    topic_id: topicId
                },
                success: function(response) {
                    if (response.success) {
                        var topicTitle = response.data.topic && response.data.topic.topic
                            ? response.data.topic.topic
                            : '';

                        $('#aips-trending-topic-posts-modal-title').text(
                            (aipsResearchL10n.postsGeneratedFrom || 'Posts Generated from Topic') + ': ' + topicTitle
                        );

                        AIPS.renderTrendingTopicPosts(response.data.posts || []);
                    } else {
                        $('#aips-trending-topic-posts-content').html(
                            '<p>' + (response.data && response.data.message ? response.data.message : (aipsResearchL10n.errorLoadingPosts || 'Error loading posts.')) + '</p>'
                        );
                    }
                },
                error: function() {
                    $('#aips-trending-topic-posts-content').html('<p>' + (aipsResearchL10n.errorLoadingPosts || 'Error loading posts.') + '</p>');
                }
            });
        },

        /**
         * Render generated posts table in the trending-topic posts modal.
         *
         * @param {Array<Object>} posts Generated post records.
         */
        renderTrendingTopicPosts: function(posts) {
            if (!posts || posts.length === 0) {
                $('#aips-trending-topic-posts-content').html('<p>' + (aipsResearchL10n.noPostsFound || 'No posts found.') + '</p>');
                return;
            }

            var esc = AIPS.Templates ? AIPS.Templates.escape : AIPS.escapeHtml;

            var rowsHtml = posts.map(function(post) {
                var actionsHtml = '';
                if (post.edit_url) {
                    actionsHtml += '<a href="' + esc(post.edit_url) + '" class="button" target="_blank" rel="noopener noreferrer">' + esc(aipsResearchL10n.editPost || 'Edit Post') + '</a> ';
                }
                if (post.post_url && post.post_status === 'publish') {
                    actionsHtml += '<a href="' + esc(post.post_url) + '" class="button" target="_blank" rel="noopener noreferrer">' + esc(aipsResearchL10n.viewPost || 'View Post') + '</a>';
                }

                return AIPS.Templates.renderRaw('aips-tmpl-research-topic-post-row', {
                    post_id: esc(post.post_id || ''),
                    post_title: esc(post.post_title || ''),
                    date_generated: esc(post.date_generated || ''),
                    date_published: esc(post.date_published || (aipsResearchL10n.notPublished || 'Not published')),
                    actions: actionsHtml
                });
            }).join('');

            var tableHtml = AIPS.Templates.renderRaw('aips-tmpl-research-topic-posts-table', {
                id_label: esc(aipsResearchL10n.postId || 'Post ID'),
                title_label: esc(aipsResearchL10n.postTitle || 'Post Title'),
                generated_label: esc(aipsResearchL10n.dateGenerated || 'Date Generated'),
                published_label: esc(aipsResearchL10n.datePublished || 'Date Published'),
                actions_label: esc(aipsResearchL10n.actions || 'Actions'),
                rows: rowsHtml
            });

            $('#aips-trending-topic-posts-content').html(tableHtml);
        },

        /**
         * Bulk-delete selected topics.
         *
         * @param {Event} e Click event.
         */
        bulkDeleteSelectedTopics: function(e) {
            e.preventDefault();

            if (AIPS.researchSelectedTopics.length === 0) {
                return;
            }

            AIPS.Utilities.confirm(
                (aipsResearchL10n.deleteTopicsConfirm || 'Delete ' + AIPS.researchSelectedTopics.length + ' selected topic(s)?'),
                'Notice',
                [
                    { label: aipsResearchL10n.cancel || 'No, cancel', className: 'aips-btn aips-btn-primary' },
                    {
                        label: aipsResearchL10n.confirmDelete || 'Yes, delete',
                        className: 'aips-btn aips-btn-danger-solid',
                        action: function() {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'aips_delete_trending_topic_bulk',
                                    nonce: $('#aips_nonce').val(),
                                    topic_ids: AIPS.researchSelectedTopics
                                },
                                success: function(response) {
                                    if (response.success) {
                                        AIPS.Utilities.showToast(response.data.message, 'success');
                                        AIPS.researchSelectedTopics = [];
                                        $('#load-topics').trigger('click');
                                    } else {
                                        AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
                                    }
                                }
                            });
                        }
                    }
                ]
            );
        },

        /**
         * Open the schedule panel for selected topics and prefill start time.
         *
         * @param {Event} e Click event.
         */
        scheduleSelectedTopics: function(e) {
            e.preventDefault();

            if (AIPS.researchSelectedTopics.length === 0) {
                return;
            }

            var now = new Date();
            var pad = function(n) {
                return String(n).padStart(2, '0');
            };
            var localDT = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());

            $('#schedule-start-date').val(localDT);
            $('#bulk-schedule-section').show();

            if ($('#bulk-schedule-section').length) {
                $('html, body').animate({
                    scrollTop: $('#bulk-schedule-section').offset().top - 50
                }, 400);
            }
        },

        /**
         * Bulk generate posts from selected topics immediately (on-demand).
         *
         * @param {Event} e Click event.
         */
        bulkGenerateSelectedTopics: function(e) {
            e.preventDefault();

            if (AIPS.researchSelectedTopics.length === 0) {
                return;
            }

            AIPS.Utilities.confirm(
                'Generate ' + AIPS.researchSelectedTopics.length + ' post(s) immediately from selected topics?',
                'Confirm Generation',
                [
                    { label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
                    {
                        label: 'Generate Now',
                        className: 'aips-btn aips-btn-primary',
                        action: function() {
                            var $btn = $('#aips-generate-selected-topics');
                            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update aips-spin"></span> Generating...');

                            AIPS.runResearchBulkGenerateWithProgress($btn, {
                                action: 'aips_generate_trending_topics_bulk',
                                nonce: $('#aips_nonce').val(),
                                topic_ids: AIPS.researchSelectedTopics
                            });
                        }
                    }
                ]
            );
        },

        /**
         * Fetch a per-post generation-time estimate and then launch bulk
         * generation with a progress bar.
         *
         * Uses the same estimate endpoint as Author Topics (`aips_get_bulk_generate_estimate`)
         * so the progress duration reflects recent real-world generation timing.
         *
         * @param {jQuery} $button   Generate button element.
         * @param {Object} ajaxData  POST payload for bulk generate request.
         */
        runResearchBulkGenerateWithProgress: function($button, ajaxData) {
            var DEFAULT_PER_POST_SECONDS = 30;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aips_get_bulk_generate_estimate',
                    nonce: $('#aips_nonce').val()
                },
                success: function(estimateResponse) {
                    var perPost = DEFAULT_PER_POST_SECONDS;

                    if (
                        estimateResponse &&
                        estimateResponse.success &&
                        estimateResponse.data &&
                        estimateResponse.data.per_post_seconds > 0
                    ) {
                        perPost = estimateResponse.data.per_post_seconds;
                    }

                    AIPS.launchResearchBulkGenerateProgress($button, ajaxData, perPost);
                },
                error: function() {
                    AIPS.launchResearchBulkGenerateProgress($button, ajaxData, DEFAULT_PER_POST_SECONDS);
                }
            });
        },

        /**
         * Open a progress bar modal and execute bulk topic generation.
         *
         * @param {jQuery} $button          Generate button element.
         * @param {Object} ajaxData         POST payload for bulk generate request.
         * @param {number} perPostSeconds   Estimated seconds per generated post.
         */
        launchResearchBulkGenerateProgress: function($button, ajaxData, perPostSeconds) {
            var topicCount = AIPS.researchSelectedTopics.length;
            var MIN_PROGRESS_SECONDS = 10;
            var totalSeconds = Math.max(perPostSeconds * topicCount, MIN_PROGRESS_SECONDS);
            var buttonDefaultText = aipsResearchL10n.generateSelected;
            var progressTitle = aipsResearchL10n.generatingPostsTitle;
            var progressMessage = aipsResearchL10n.generatingPostsMessage;

            var progressBar = AIPS.Utilities.showProgressBar({
                title: progressTitle,
                message: progressMessage,
                totalSeconds: totalSeconds
            });

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        progressBar.complete(response.data.message, 'success');
                        AIPS.researchSelectedTopics = [];
                        setTimeout(function() {
                            $('#load-topics').trigger('click');
                        }, 1400);
                    } else {
                        var errorMessage = response.data && response.data.message
                            ? response.data.message
                            : aipsResearchL10n.generateError;

                        progressBar.complete(errorMessage, 'error');
                        setTimeout(function() {
                            AIPS.Utilities.showToast('Error: ' + errorMessage, 'error');
                        }, 1400);
                    }
                },
                error: function(xhr, status, error) {
                    var fallbackError = error ? error : aipsResearchL10n.generateError;
                    progressBar.complete(fallbackError, 'error');
                    setTimeout(function() {
                        AIPS.Utilities.showToast('Error: ' + fallbackError, 'error');
                    }, 1400);
                },
                complete: function() {
                    $button.prop('disabled', false).html(buttonDefaultText);
                }
            });
        },

        /**
         * Reload the trending topics list.
         *
         * @param {Event} e Click event.
         */
        reloadTopics: function(e) {
            e.preventDefault();
            $('#load-topics').trigger('click');
        }
    });

    $(document).ready(function() {
        AIPS.initResearch();
    });
})(jQuery);
