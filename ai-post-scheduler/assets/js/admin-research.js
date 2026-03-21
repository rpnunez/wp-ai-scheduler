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
            this.bindResearchEvents();

            if ($('#load-topics').length) {
                $('#load-topics').trigger('click');
            }
        },

        /**
         * Register delegated event handlers for the research admin UI.
         */
        bindResearchEvents: function() {
            $(document).on('submit', '#aips-research-form', this.submitResearchForm);
            $(document).on('click', '#load-topics', this.loadTopics);
            $(document).on('keyup search', '#filter-search', this.filterTopics);
            $(document).on('click', '#filter-search-clear, #clear-topics-search', this.clearTopicsSearch);
            $(document).on('change', '#select-all-topics', this.toggleAllTopics);
            $(document).on('change', '.topic-checkbox', this.toggleTopicSelection);
            $(document).on('click', '.delete-topic', this.deleteTopic);
            $(document).on('submit', '#bulk-schedule-form', this.submitBulkSchedule);
            $(document).on('click', '#aips-clear-filters', this.clearTopicFilters);
            $(document).on('click', '#aips-start-research', this.focusResearchForm);
            $(document).on('click', '#analyze-gaps-btn', this.analyzeGaps);
            $(document).on('click', '.generate-gap-ideas', this.generateGapIdeas);
            $(document).on('click', '#aips-delete-selected-topics', this.bulkDeleteSelectedTopics);
            $(document).on('click', '#aips-generate-selected-topics', this.generateSelectedTopics);
            $(document).on('click', '#aips-reload-topics-btn', this.reloadTopics);
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

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aips_get_trending_topics',
                    nonce: $('#aips_nonce').val(),
                    niche: niche,
                    min_score: minScore,
                    fresh_only: freshOnly,
                    limit: 50
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.renderTopicsTable(response.data.topics);
                    } else {
                        AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
                    }
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
            var isFiltered = niche || minScore !== '0' || freshOnly;
            var esc = AIPS.Templates ? AIPS.Templates.escape : function(str) { return String(str || ''); };

            this.researchSelectedTopics = [];
            this.updateSelectedTopics();

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

                    return AIPS.Templates.renderRaw('aips-tmpl-research-topics-row', {
                        id: esc(topic.id),
                        topic: esc(topic.topic),
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
                this.filterTopics();
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
            this.updateSelectedTopics();
        },

        /**
         * Refresh selected-topic state and related action-button availability.
         */
        updateSelectedTopics: function() {
            this.researchSelectedTopics = $('.topic-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            var hasSelected = this.researchSelectedTopics.length > 0;
            $('#aips-delete-selected-topics').prop('disabled', !hasSelected);
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
        generateSelectedTopics: function(e) {
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
