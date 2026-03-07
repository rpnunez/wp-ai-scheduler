(function($) {
    'use strict';

    $(document).ready(function() {
        let selectedTopics = [];

        // Local helper for HTML escaping to prevent XSS
        /**
         * Escape a value for safe insertion as HTML text content.
         *
         * Converts the value to a string and replaces `&`, `<`, `>`, `"`, and `'`
         * with their corresponding HTML entities. Returns an empty string for
         * `null` or `undefined` inputs.
         *
         * @param  {*}      text - Value to escape (coerced to string if needed).
         * @return {string} HTML-safe string.
         */
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Research form submission
        $('#aips-research-form').on('submit', function(e) {
            e.preventDefault();

            const $form = $(this);
            const $submit = $('#research-submit');
            const $spinner = $form.find('.spinner');

            const niche = $('#research-niche').val();
            const count = $('#research-count').val();
            const keywordsStr = $('#research-keywords').val();
            const keywords = keywordsStr ? keywordsStr.split(',').map(k => k.trim()) : [];

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
                        displayResearchResults(response.data);
                        $('#load-topics').trigger('click'); // Refresh topics list
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
        });

        // Display research results
        /**
         * Render the research-result summary into `#research-results-content`.
         *
         * Builds an HTML snippet showing the number of saved topics and the niche,
         * followed by an ordered list of the top-ranked topics with scores and
         * optional reasoning. Slides the `#research-results` panel into view.
         *
         * @param {Object}        data            - The `response.data` from `aips_research_topics`.
         * @param {number}        data.saved_count - Total topics saved by this research run.
         * @param {string}        data.niche       - The niche that was researched.
         * @param {Array<Object>} data.top_topics  - Array of top-ranked topic objects.
         */
        function displayResearchResults(data) {
            const $container = $('#research-results-content');
            // Security: Escape HTML using local escapeHtml helper
            let html = '<p><strong>' + escapeHtml(data.saved_count) + ' ' + aipsResearchL10n.topicsSaved + ' "' + escapeHtml(data.niche) + '"</strong></p>';

            if (data.top_topics && data.top_topics.length > 0) {
                html += '<h4>' + aipsResearchL10n.topTopics + '</h4><ol>';
                data.top_topics.forEach(function(topic) {
                    const scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                    html += '<li><strong>' + escapeHtml(topic.topic) + '</strong> ';
                    html += '<span class="aips-score-badge aips-score-' + scoreClass + '">' + escapeHtml(topic.score) + '</span>';
                    if (topic.reason) {
                        html += '<br><small><em>' + escapeHtml(topic.reason) + '</em></small>';
                    }
                    html += '</li>';
                });
                html += '</ol>';
            }

            $container.html(html);
            $('#research-results').slideDown();
        }

        // Load topics
        $('#load-topics').on('click', function() {
            const niche = $('#filter-niche').val();
            const minScore = $('#filter-score').val();
            const freshOnly = $('#filter-fresh').is(':checked');

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
                        displayTopicsTable(response.data.topics);
                    } else {
                        AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
                    }
                }
            });
        });

        // Display topics table
        /**
         * Render the full topics table (or an empty-state panel) in
         * `#topics-container`.
         *
         * Builds a `<table>` with checkboxes, score badges, keywords, and action
         * buttons for each topic. Also appends an initially hidden "no results"
         * empty state for the in-page search filter. Shows or hides the
         * `#bulk-schedule-section` depending on whether any topics are present.
         * Re-applies the current search term if `#filter-search` is non-empty.
         *
         * @param {Array<Object>} topics - Array of topic objects from the server.
         */
        function displayTopicsTable(topics) {
            if (!topics || topics.length === 0) {
                // Check if filters are active
                const niche = $('#filter-niche').val();
                const minScore = $('#filter-score').val();
                const freshOnly = $('#filter-fresh').is(':checked');

                const isFiltered = niche || minScore !== '0' || freshOnly;

                let emptyStateHtml = '<div class="aips-empty-state">';
                emptyStateHtml += '<span class="dashicons dashicons-search" aria-hidden="true"></span>';

                if (isFiltered) {
                    emptyStateHtml += '<h3>' + aipsResearchL10n.noTopicsFound + '</h3>';
                    emptyStateHtml += '<p>' + aipsResearchL10n.noTopicsFound + '</p>';
                    emptyStateHtml += '<button type="button" class="button button-primary" id="aips-clear-filters">' + aipsResearchL10n.clearFilters + '</button>';
                } else {
                    emptyStateHtml += '<h3>' + aipsResearchL10n.libraryEmpty + '</h3>';
                    emptyStateHtml += '<p>' + aipsResearchL10n.libraryEmpty + '</p>';
                    emptyStateHtml += '<button type="button" class="button button-primary" id="aips-start-research">' + aipsResearchL10n.startResearch + '</button>';
                }

                emptyStateHtml += '</div>';

                $('#topics-container').html(emptyStateHtml);
                $('#bulk-schedule-section').hide();
                return;
            }

            let html = '<table class="aips-topics-table">';
            html += '<thead><tr>';
            html += '<th><input type="checkbox" id="select-all-topics"></th>';
            html += '<th>Topic</th>';
            html += '<th>Score</th>';
            html += '<th>Niche</th>';
            html += '<th>Keywords</th>';
            html += '<th>Researched</th>';
            html += '<th>Actions</th>';
            html += '</tr></thead><tbody>';

            topics.forEach(function(topic) {
                const scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                const keywords = Array.isArray(topic.keywords) ? topic.keywords : [];

                html += '<tr>';
                html += '<td><input type="checkbox" class="topic-checkbox" value="' + escapeHtml(topic.id) + '"></td>';
                html += '<td><strong>' + escapeHtml(topic.topic) + '</strong>';
                if (topic.reason) {
                    html += '<br><small>' + escapeHtml(topic.reason) + '</small>';
                }
                html += '</td>';
                html += '<td><span class="aips-score-badge aips-score-' + scoreClass + '">' + escapeHtml(topic.score) + '</span></td>';
                html += '<td>' + escapeHtml(topic.niche) + '</td>';
                html += '<td><div class="aips-keywords-list">';
                keywords.forEach(function(kw) {
                    html += '<span class="aips-keyword-tag">' + escapeHtml(kw) + '</span>';
                });
                html += '</div></td>';
                html += '<td>' + new Date(topic.researched_at).toLocaleDateString() + '</td>';
                html += '<td><div class="aips-topic-actions">';
                html += '<button class="button button-small delete-topic" data-id="' + escapeHtml(topic.id) + '">' + aipsResearchL10n.delete + '</button>';
                html += '</div></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';

            // Empty state for search
            html += '<div id="topics-search-empty" class="aips-empty-state" style="display:none;">';
            html += '<span class="dashicons dashicons-search" aria-hidden="true"></span>';
            html += '<h3>' + aipsResearchL10n.noTopicsFoundTitle + '</h3>';
            html += '<p>' + aipsResearchL10n.noTopicsFound + '</p>';
            html += '<button type="button" class="button button-primary" id="clear-topics-search">' + aipsResearchL10n.clearSearch + '</button>';
            html += '</div>';

            $('#topics-container').html(html);

            // Show bulk schedule section
            $('#bulk-schedule-section').show();

            // Re-apply filter if search box has value
            if ($('#filter-search').val()) {
                filterTopics();
            }
        }

        // Search Filter Logic
        /**
         * Filter the visible topics table rows against the current search query.
         *
         * Shows or hides the `#filter-search-clear` button, toggles rows based
         * on whether the topic text cell contains the query, and shows the
         * `#topics-search-empty` empty-state element when no rows match.
         *
         * Bound to the `keyup` and `search` events on `#filter-search`.
         */
        function filterTopics() {
            const query = $('#filter-search').val().toLowerCase();
            const $rows = $('.aips-topics-table tbody tr');
            let visibleCount = 0;

            if (query.length > 0) {
                $('#filter-search-clear').show();
            } else {
                $('#filter-search-clear').hide();
            }

            $rows.each(function() {
                const topicText = $(this).find('td:nth-child(2)').text().toLowerCase();
                if (topicText.indexOf(query) > -1) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });

            // Show/hide empty state
            if (visibleCount === 0 && $rows.length > 0) {
                $('.aips-topics-table').hide();
                $('#topics-search-empty').show();
            } else {
                $('.aips-topics-table').show();
                $('#topics-search-empty').hide();
            }
        }

        // Search Listeners
        $(document).on('keyup search', '#filter-search', function() {
            filterTopics();
        });

        // Helper function to clear search
        /**
         * Clear the `#filter-search` input and re-trigger the search event to
         * restore all hidden topic rows, then return focus to the field.
         */
        function clearSearch() {
            $('#filter-search').val('').trigger('search');
            $('#filter-search').focus();
        }

        $(document).on('click', '#filter-search-clear, #clear-topics-search', clearSearch);

        // Select all topics
        $(document).on('change', '#select-all-topics', function() {
            // Only select visible checkboxes
            $('.topic-checkbox:visible').prop('checked', $(this).is(':checked'));
            updateSelectedTopics();
        });

        // Individual checkbox change
        $(document).on('change', '.topic-checkbox', function() {
            updateSelectedTopics();
        });

        // Update selected topics
        /**
         * Rebuild the `selectedTopics` array from currently checked
         * `.topic-checkbox` elements.
         *
         * Called on every individual or "select all" checkbox change so that
         * the bulk-schedule form always has an up-to-date list of IDs.
         */
        function updateSelectedTopics() {
            selectedTopics = $('.topic-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
        }

        // Delete topic
        $(document).on('click', '.delete-topic', function() {
            var $el = $(this);
            var topicId = $el.data('id');
            AIPS.Utilities.confirm(aipsResearchL10n.deleteTopicConfirm, 'Notice', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                }}
            ]);
        });

        // Bulk schedule
        $('#bulk-schedule-form').on('submit', function(e) {
            e.preventDefault();

            if (selectedTopics.length === 0) {
                AIPS.Utilities.showToast(aipsResearchL10n.selectTopicSchedule, 'warning');
                return;
            }

            const $form = $(this);
            const $submit = $form.find('button[type="submit"]');
            const $spinner = $form.find('.spinner');

            $submit.prop('disabled', true).addClass('is-loading');
            $spinner.addClass('is-active');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aips_schedule_trending_topics',
                    nonce: $('#aips_nonce').val(),
                    topic_ids: selectedTopics,
                    template_id: $('#schedule-template').val(),
                    start_date: $('#schedule-start-date').val(),
                    frequency: $('#schedule-frequency').val()
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success');
                        selectedTopics = [];
                        $('.topic-checkbox').prop('checked', false);
                        $('#select-all-topics').prop('checked', false);
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
        });

        // Clear filters handler
        $(document).on('click', '#aips-clear-filters', function() {
            $('#filter-niche').val('');
            $('#filter-score').val('0');
            $('#filter-fresh').prop('checked', false);
            $('#load-topics').trigger('click');
        });

        // Start research handler
        $(document).on('click', '#aips-start-research', function() {
            var $form = $('#aips-research-form');

            if ($form.length > 0) {
                $('html, body').animate({
                    scrollTop: $form.offset().top - 50
                }, 500);

                var $nicheField = $('#research-niche');
                if ($nicheField.length > 0) {
                    $nicheField.focus();
                }
            }
        });

        // Auto-load topics on page load if elements exist
        if ($('#load-topics').length > 0) {
            $('#load-topics').trigger('click');
        }

        // --- Gap Analysis Logic ---

        // Analyze Gaps Button
        $('#analyze-gaps-btn').on('click', function(e) {
            e.preventDefault();
            const niche = $('#gap-niche').val();
            const $btn = $(this);
            const $spinner = $btn.next('.spinner');

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
                        renderGapResults(response.data.gaps);
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
        });

        // Render Gap Cards
        /**
         * Render the gap-analysis results as a grid of priority-coded cards.
         *
         * Clears the `#gap-results-container .aips-gap-grid` and rebuilds it
         * from the `gaps` array. Each card shows the priority badge, missing
         * topic title, reason, search intent, and a "Generate Ideas" button
         * that triggers an AJAX-backed idea-generation flow. Slides the
         * container into view on completion.
         *
         * @param {Array<Object>} gaps - Array of gap objects returned by the server.
         */
        function renderGapResults(gaps) {
            const $container = $('#gap-results-container');
            const $grid = $container.find('.aips-gap-grid');
            $grid.empty();

            if (!gaps || gaps.length === 0) {
                $grid.html('<p>No gaps found.</p>');
                $container.show();
                return;
            }

            gaps.forEach(function(gap) {
                const priorityClass = (gap.priority || 'Medium').toLowerCase();
                let cardHtml = `
                    <div class="aips-gap-card priority-${escapeHtml(priorityClass)}">
                        <span class="aips-gap-badge ${escapeHtml(priorityClass)}">${escapeHtml(gap.priority)} Priority</span>
                        <h4>${escapeHtml(gap.missing_topic)}</h4>
                        <p class="aips-gap-reason">${escapeHtml(gap.reason)}</p>
                        <p class="aips-gap-intent">Intent: ${escapeHtml(gap.search_intent)}</p>
                        <div class="aips-gap-actions">
                            <button class="button button-secondary generate-gap-ideas" data-topic="${escapeHtml(gap.missing_topic)}">
                                Generate Ideas
                            </button>
                        </div>
                    </div>
                `;
                $grid.append(cardHtml);
            });

            $container.slideDown();
        }

        // Generate Ideas from Gap
        $(document).on('click', '.generate-gap-ideas', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const topic = $btn.data('topic');
            const niche = $('#gap-niche').val();

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
                        // Switch to Trending tab and reload
                        $('.aips-tab-link[data-tab="trending"]').trigger('click');
                        // Wait for tab switch then reload
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
        });

    });

})(jQuery);
