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
            var html = '<p><strong>' + this.escapeHtml(data.saved_count) + ' ' + aipsResearchL10n.topicsSaved + ' "' + this.escapeHtml(data.niche) + '"</strong></p>';

            if (data.top_topics && data.top_topics.length > 0) {
                html += '<h4>' + aipsResearchL10n.topTopics + '</h4><ol>';
                data.top_topics.forEach(function(topic) {
                    var scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                    html += '<li><strong>' + AIPS.escapeHtml(topic.topic) + '</strong> ';
                    html += '<span class="aips-score-badge aips-score-' + scoreClass + '">' + AIPS.escapeHtml(topic.score) + '</span>';
                    if (topic.reason) {
                        html += '<br><small><em>' + AIPS.escapeHtml(topic.reason) + '</em></small>';
                    }
                    html += '</li>';
                });
                html += '</ol>';
            }

            $container.html(html);
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

            this.researchSelectedTopics = [];
            this.updateSelectedTopics();

            if (!topics || topics.length === 0) {
                html = '<div class="aips-panel-body"><div class="aips-empty-state">';
                html += '<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>';

                if (isFiltered) {
                    html += '<h3 class="aips-empty-state-title">' + aipsResearchL10n.noTopicsFound + '</h3>';
                    html += '<p class="aips-empty-state-description">' + aipsResearchL10n.noTopicsFound + '</p>';
                    html += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-clear-filters">' + aipsResearchL10n.clearFilters + '</button>';
                } else {
                    html += '<h3 class="aips-empty-state-title">' + aipsResearchL10n.libraryEmpty + '</h3>';
                    html += '<p class="aips-empty-state-description">' + aipsResearchL10n.libraryEmpty + '</p>';
                    html += '<button type="button" class="aips-btn aips-btn-sm aips-btn-primary" id="aips-start-research">' + aipsResearchL10n.startResearch + '</button>';
                }

                html += '</div></div>';

                $('#topics-container').html(html);
                $('#bulk-schedule-section').hide();
                $('#topics-tablenav').hide();
                return;
            }

            html = '<table class="aips-table aips-research-table">';
            html += '<thead><tr>';
            html += '<th scope="col" style="width:30px;"><input type="checkbox" id="select-all-topics"></th>';
            html += '<th scope="col">Topic</th>';
            html += '<th scope="col">Score</th>';
            html += '<th scope="col">Niche</th>';
            html += '<th scope="col">Keywords</th>';
            html += '<th scope="col">Researched</th>';
            html += '<th scope="col">Actions</th>';
            html += '</tr></thead><tbody>';

            topics.forEach(function(topic) {
                var scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                var keywords = Array.isArray(topic.keywords) ? topic.keywords : [];

                html += '<tr>';
                html += '<td><input type="checkbox" class="topic-checkbox" value="' + AIPS.escapeHtml(topic.id) + '"></td>';
                html += '<td><strong>' + AIPS.escapeHtml(topic.topic) + '</strong>';
                if (topic.reason) {
                    html += '<br><small>' + AIPS.escapeHtml(topic.reason) + '</small>';
                }
                html += '</td>';
                html += '<td><span class="aips-score-badge aips-score-' + scoreClass + '">' + AIPS.escapeHtml(topic.score) + '</span></td>';
                html += '<td>' + AIPS.escapeHtml(topic.niche) + '</td>';
                html += '<td><div class="aips-keywords-list">';
                keywords.forEach(function(kw) {
                    html += '<span class="aips-keyword-tag">' + AIPS.escapeHtml(kw) + '</span>';
                });
                html += '</div></td>';
                html += '<td>' + new Date(topic.researched_at).toLocaleDateString() + '</td>';
                html += '<td><div class="aips-topic-actions">';
                html += '<button class="aips-btn aips-btn-sm aips-btn-danger delete-topic" data-id="' + AIPS.escapeHtml(topic.id) + '"><span class="dashicons dashicons-trash"></span> ' + aipsResearchL10n.delete + '</button>';
                html += '</div></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '<div id="topics-search-empty" class="aips-empty-state" style="display:none; padding: 40px 20px;">';
            html += '<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>';
            html += '<h3 class="aips-empty-state-title">' + aipsResearchL10n.noTopicsFoundTitle + '</h3>';
            html += '<p class="aips-empty-state-description">' + aipsResearchL10n.noTopicsFound + '</p>';
            html += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="clear-topics-search">' + aipsResearchL10n.clearSearch + '</button>';
            html += '</div>';

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
                $grid.html('<p>No gaps found.</p>');
                $container.show();
                return;
            }

            gaps.forEach(function(gap) {
                var priorityClass = (gap.priority || 'Medium').toLowerCase();
                var cardHtml = '';

                cardHtml += '<div class="aips-gap-card priority-' + AIPS.escapeHtml(priorityClass) + '">';
                cardHtml += '<span class="aips-gap-badge ' + AIPS.escapeHtml(priorityClass) + '">' + AIPS.escapeHtml(gap.priority) + ' Priority</span>';
                cardHtml += '<h4>' + AIPS.escapeHtml(gap.missing_topic) + '</h4>';
                cardHtml += '<p class="aips-gap-reason">' + AIPS.escapeHtml(gap.reason) + '</p>';
                cardHtml += '<p class="aips-gap-intent">Intent: ' + AIPS.escapeHtml(gap.search_intent) + '</p>';
                cardHtml += '<div class="aips-gap-actions">';
                cardHtml += '<button class="aips-btn aips-btn-sm aips-btn-secondary generate-gap-ideas" data-topic="' + AIPS.escapeHtml(gap.missing_topic) + '">Generate Ideas</button>';
                cardHtml += '</div></div>';

                $grid.append(cardHtml);
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

            $btn.prop('disabled', true).text('Generating...');

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
                    $btn.prop('disabled', false).text('Generate Ideas');
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
