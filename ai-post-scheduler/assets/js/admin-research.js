(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    /**
     * Resolve AJAX URL to use for admin requests.
     * Falls back to `ajaxurl` or empty string if `aipsAjax` isn't available.
     * @return {string}
     */
    function resolveAjaxUrl() {
        return (typeof aipsAjax !== 'undefined' && aipsAjax.ajaxUrl) ? aipsAjax.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    }

    /**
     * Resolve the plugin nonce for AJAX security.
     * Uses `aipsAjax.nonce` when available or reads a DOM element `#aips_nonce` as a fallback.
     * @return {string}
     */
    function resolveNonce() {
        return (typeof aipsAjax !== 'undefined' && aipsAjax.nonce) ? aipsAjax.nonce : ($('#aips_nonce').length ? $('#aips_nonce').val() : '');
    }

    Object.assign(window.AIPS, {
        /**
         * Array of topic IDs selected by the admin in the research UI.
         * Maintained as a simple in-memory state so other methods can act on the
         * currently selected items without re-querying the DOM repeatedly.
         * @type {Array<string|number>}
         */
        selectedTopics: [],

        /**
         * Escape a string for safe insertion into HTML/text nodes.
         * Minimal helper to reduce XSS surface when injecting dynamic values.
         * @param {string} text
         * @return {string}
         */
        escapeHtml: function(text) {
            if (text === null || text === undefined) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/\"/g, "&quot;")
                .replace(/'/g, "&#039;");
        },

        /**
         * Submit the research form to generate trending topics.
         * - Reads form fields (niche, count, keywords)
         * - Disables the submit button and shows a spinner while request runs
         * - On success, displays results and refreshes the topics list
         * @param {Event} e
         */
        handleResearchSubmit: function(e) {
            e.preventDefault();

            var $form = $('#aips-research-form');
            var $submit = $('#research-submit');
            var $spinner = $form.find('.spinner');

            var niche = $('#research-niche').val();
            var count = $('#research-count').val();
            var keywordsStr = $('#research-keywords').val();
            var keywords = keywordsStr ? keywordsStr.split(',').map(function(k){ return k.trim(); }) : [];

            // Disable UI and show loading state
            $submit.prop('disabled', true).addClass('is-loading');
            $spinner.addClass('is-active');

            $.ajax({
                url: resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_research_topics',
                    nonce: resolveNonce(),
                    niche: niche,
                    count: count,
                    keywords: keywords
                },
                success: function(response) {
                    if (response.success) {
                        // Render results and refresh the topic table
                        window.AIPS.displayResearchResults(response.data);
                        $('#load-topics').trigger('click'); // Refresh topics list
                    } else {
                        alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                },
                error: function() {
                    // Use localized fallback string if available
                    alert(typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.researchError : 'An error occurred during research.');
                },
                complete: function() {
                    // Restore UI
                    $submit.prop('disabled', false).removeClass('is-loading');
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Render the research results returned by the server.
         * Expects an object with saved_count, niche, and top_topics[] where each
         * topic has topic, score, and optional reason.
         * @param {Object} data
         */
        displayResearchResults: function(data) {
            var $container = $('#research-results-content');

            // Build a small summary header and optional list of top topics
            var html = '<p><strong>' + window.AIPS.escapeHtml(data.saved_count) + ' ' + (typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.topicsSaved : 'topics saved for') + ' "' + window.AIPS.escapeHtml(data.niche) + '"</strong></p>';

            if (data.top_topics && data.top_topics.length > 0) {
                html += '<h4>' + (typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.topTopics : 'Top Topics') + '</h4><ol>';
                data.top_topics.forEach(function(topic) {
                    var scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                    html += '<li><strong>' + window.AIPS.escapeHtml(topic.topic) + '</strong> ';
                    html += '<span class="aips-score-badge aips-score-' + scoreClass + '">' + window.AIPS.escapeHtml(topic.score) + '</span>';
                    if (topic.reason) {
                        html += '<br><small><em>' + window.AIPS.escapeHtml(topic.reason) + '</em></small>';
                    }
                    html += '</li>';
                });
                html += '</ol>';
            }

            $container.html(html);
            $('#research-results').slideDown();
        },

        /**
         * Fetch the list of trending topics from the server using current filters.
         * Populates the topics table on success by calling `displayTopicsTable`.
         * @param {Event} [e]
         */
        loadTopics: function(e) {
            if (e && e.preventDefault) e.preventDefault();

            var niche = $('#filter-niche').val();
            var minScore = $('#filter-score').val();
            var freshOnly = $('#filter-fresh').is(':checked');

            $.ajax({
                url: resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_get_trending_topics',
                    nonce: resolveNonce(),
                    niche: niche,
                    min_score: minScore,
                    fresh_only: freshOnly,
                    limit: 50
                },
                success: function(response) {
                    if (response.success) {
                        window.AIPS.displayTopicsTable(response.data.topics);
                    } else {
                        alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                }
            });
        },

        /**
         * Render a topics table into the DOM. The table includes checkboxes for
         * bulk actions and a delete button for each topic.
         * @param {Array<Object>} topics
         */
        displayTopicsTable: function(topics) {
            if (!topics || topics.length === 0) {
                $('#topics-container').html('<p>' + (typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.noTopicsFound : 'No topics found matching your filters.') + '</p>');
                return;
            }

            var html = '<table class="aips-topics-table">';
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
                var scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                var keywords = Array.isArray(topic.keywords) ? topic.keywords : [];

                html += '<tr>';
                html += '<td><input type="checkbox" class="topic-checkbox" value="' + window.AIPS.escapeHtml(topic.id) + '"></td>';
                html += '<td><strong>' + window.AIPS.escapeHtml(topic.topic) + '</strong>';
                if (topic.reason) {
                    html += '<br><small>' + window.AIPS.escapeHtml(topic.reason) + '</small>';
                }
                html += '</td>';
                html += '<td><span class="aips-score-badge aips-score-' + scoreClass + '">' + window.AIPS.escapeHtml(topic.score) + '</span></td>';
                html += '<td>' + window.AIPS.escapeHtml(topic.niche) + '</td>';
                html += '<td><div class="aips-keywords-list">';
                keywords.forEach(function(kw) {
                    html += '<span class="aips-keyword-tag">' + window.AIPS.escapeHtml(kw) + '</span>';
                });
                html += '</div></td>';
                html += '<td>' + (topic.researched_at ? new Date(topic.researched_at).toLocaleDateString() : '') + '</td>';
                html += '<td><div class="aips-topic-actions">';
                html += '<button class="button button-small delete-topic" data-id="' + window.AIPS.escapeHtml(topic.id) + '">' + (typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.delete : 'Delete') + '</button>';
                html += '</div></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            $('#topics-container').html(html);

            // Show bulk schedule section (reveals scheduling form)
            $('#bulk-schedule-section').show();
        },

        /**
         * Rebuild the `selectedTopics` array from currently checked checkboxes.
         * This keeps a small JS-side cache of selected topic IDs for bulk actions.
         */
        updateSelectedTopics: function() {
            window.AIPS.selectedTopics = $('.topic-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
        },

        /**
         * Delete a trending topic by ID. Prompts for confirmation before making the request.
         * @param {Event} e
         */
        deleteTopic: function(e) {
            e.preventDefault();
            var topicId = $(this).data('id');

            if (!confirm((typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.deleteTopicConfirm : 'Delete this topic?'))) {
                return;
            }

            $.ajax({
                url: resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_delete_trending_topic',
                    nonce: resolveNonce(),
                    topic_id: topicId
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the topics list after deletion
                        $('#load-topics').trigger('click');
                    } else {
                        alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                }
            });
        },

        /**
         * Schedule the currently selected topics in bulk using the provided form values.
         * - Validates selection and form fields
         * - Disables the submit button and shows a spinner while scheduling
         * @param {Event} e
         */
        handleBulkSchedule: function(e) {
            e.preventDefault();

            if (!window.AIPS.selectedTopics || window.AIPS.selectedTopics.length === 0) {
                alert((typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.selectTopicSchedule : 'Please select at least one topic to schedule.'));
                return;
            }

            var $form = $('#bulk-schedule-form');
            var $submit = $form.find('button[type="submit"]');
            var $spinner = $form.find('.spinner');

            $submit.prop('disabled', true).addClass('is-loading');
            $spinner.addClass('is-active');

            $.ajax({
                url: resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_schedule_trending_topics',
                    nonce: resolveNonce(),
                    topic_ids: window.AIPS.selectedTopics,
                    template_id: $('#schedule-template').val(),
                    start_date: $('#schedule-start-date').val(),
                    frequency: $('#schedule-frequency').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        // Clear selection UI on success
                        window.AIPS.selectedTopics = [];
                        $('.topic-checkbox').prop('checked', false);
                        $('#select-all-topics').prop('checked', false);
                    } else {
                        alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                },
                error: function() {
                    alert((typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.schedulingError : 'An error occurred during scheduling.'));
                },
                complete: function() {
                    $submit.prop('disabled', false).removeClass('is-loading');
                    $spinner.removeClass('is-active');
                }
            });
        }
    });

    // Bind events on document ready
    $(document).ready(function() {
        // Research form submission
        $(document).on('submit', '#aips-research-form', window.AIPS.handleResearchSubmit);

        // Load topics button
        $(document).on('click', '#load-topics', window.AIPS.loadTopics);

        // Select all / individual topic checkbox handlers
        $(document).on('change', '#select-all-topics', function() {
            $('.topic-checkbox').prop('checked', $(this).is(':checked'));
            window.AIPS.updateSelectedTopics();
        });
        $(document).on('change', '.topic-checkbox', window.AIPS.updateSelectedTopics);

        // Delete topic
        $(document).on('click', '.delete-topic', window.AIPS.deleteTopic);

        // Bulk schedule form
        $(document).on('submit', '#bulk-schedule-form', window.AIPS.handleBulkSchedule);

        // Auto-load topics on page load if elements exist
        if ($('#load-topics').length > 0) {
            $('#load-topics').trigger('click');
        }
    });

})(jQuery);
