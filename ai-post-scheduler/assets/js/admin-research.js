(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with Research functionality
    Object.assign(window.AIPS, {
        // Selected topics state shared across methods
        selectedTopics: [],

        // Handle research form submission
        handleResearchSubmit: function (e) {
            e.preventDefault();

            var $form = $('#aips-research-form');
            var $submit = $('#research-submit');
            var $spinner = $form.find('.spinner');

            var niche = $('#research-niche').val();
            var count = $('#research-count').val();
            var keywordsStr = $('#research-keywords').val();
            var keywords = keywordsStr ? keywordsStr.split(',').map(function (k) {
                return k.trim();
            }) : [];

            $submit.prop('disabled', true).addClass('is-loading');
            $spinner.addClass('is-active');

            $.ajax({
                url: (window.AIPS && window.AIPS.resolveAjaxUrl) ? window.AIPS.resolveAjaxUrl() : '',
                type: 'POST',
                data: {
                    action: 'aips_research_topics',
                    nonce: (window.AIPS && window.AIPS.resolveNonce) ? window.AIPS.resolveNonce() : '',
                    niche: niche,
                    count: count,
                    keywords: keywords
                },
                success: function (response) {
                    if (response.success) {
                        window.AIPS.displayResearchResults(response.data);
                        $('#load-topics').trigger('click'); // Refresh topics list
                    } else {
                        alert((response.data && response.data.message) ? response.data.message : (typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.unknownError : 'Unknown error'));
                    }
                },
                error: function () {
                    alert(typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.researchError : 'An error occurred during research.');
                },
                complete: function () {
                    $submit.prop('disabled', false).removeClass('is-loading');
                    $spinner.removeClass('is-active');
                }
            });
        },

        // Display research results in UI
        displayResearchResults: function (data) {
            var $container = $('#research-results-content');
            var html = '<p><strong>' + (window.AIPS && window.AIPS.escapeHtml ? window.AIPS.escapeHtml(data.saved_count) : String(data.saved_count)) + ' ' + (typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.topicsSaved : 'topics saved for') + ' "' + (window.AIPS && window.AIPS.escapeHtml ? window.AIPS.escapeHtml(data.niche) : String(data.niche)) + '"</strong></p>';

            if (data.top_topics && data.top_topics.length > 0) {
                html += '<h4>' + (typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.topTopics : 'Top Topics') + '</h4><ol>';
                data.top_topics.forEach(function (topic) {
                    var scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                    html += '<li><strong>' + (window.AIPS && window.AIPS.escapeHtml ? window.AIPS.escapeHtml(topic.topic) : String(topic.topic)) + '</strong> ';
                    html += '<span class="aips-score-badge aips-score-' + scoreClass + '">' + (window.AIPS && window.AIPS.escapeHtml ? window.AIPS.escapeHtml(topic.score) : String(topic.score)) + '</span>';
                    if (topic.reason) {
                        html += '<br><small><em>' + (window.AIPS && window.AIPS.escapeHtml ? window.AIPS.escapeHtml(topic.reason) : String(topic.reason)) + '</em></small>';
                    }
                    html += '</li>';
                });
                html += '</ol>';
            }

            $container.html(html);
            $('#research-results').slideDown();
        },

        // Load topics list from server
        loadTopics: function (e) {
            if (e && e.preventDefault) e.preventDefault();

            // Cache selectors for slightly better performance
            var $niche = $('#filter-niche');
            var $minScore = $('#filter-score');
            var $fresh = $('#filter-fresh');

            var niche = $niche.val();
            var minScore = $minScore.val();
            var freshOnly = $fresh.is(':checked');

            $.ajax({
                url: (window.AIPS && window.AIPS.resolveAjaxUrl) ? window.AIPS.resolveAjaxUrl() : '',
                type: 'POST',
                data: {
                    action: 'aips_get_trending_topics',
                    nonce: (window.AIPS && window.AIPS.resolveNonce) ? window.AIPS.resolveNonce() : '',
                    niche: niche,
                    min_score: minScore,
                    fresh_only: freshOnly,
                    limit: 50
                },
                success: function (response) {
                    if (response.success) {
                        window.AIPS.displayTopicsTable(response.data.topics);
                    } else {
                        alert((response.data && response.data.message) ? response.data.message : (typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.unknownError : 'Unknown error'));
                    }
                }
            });
        },

        // Render topics into the DOM
        displayTopicsTable: function (topics) {
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

            topics.forEach(function (topic) {
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
                keywords.forEach(function (kw) {
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

            // Show bulk schedule section
            $('#bulk-schedule-section').show();
        },

        // Update selected topics state
        updateSelectedTopics: function () {
            // Cache selection lookup
            window.AIPS.selectedTopics = $('.topic-checkbox:checked').map(function () { return $(this).val(); }).get();
        },

        // Delete a single trending topic
        deleteTopic: function (e) {
            e.preventDefault();
            var topicId = $(this).data('id');

            if (!confirm((typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.deleteTopicConfirm : 'Delete this topic?'))) {
                return;
            }

            $.ajax({
                url: (window.AIPS && window.AIPS.resolveAjaxUrl) ? window.AIPS.resolveAjaxUrl() : '',
                type: 'POST',
                data: {
                    action: 'aips_delete_trending_topic',
                    nonce: (window.AIPS && window.AIPS.resolveNonce) ? window.AIPS.resolveNonce() : '',
                    topic_id: topicId
                },
                success: function (response) {
                    if (response.success) {
                        $('#load-topics').trigger('click');
                    } else {
                        alert((response.data && response.data.message) ? response.data.message : (typeof aipsResearchL10n !== 'undefined' ? aipsResearchL10n.unknownError : 'Unknown error'));
                    }
                }
            });
        },

        // Bulk schedule selected topics
        handleBulkSchedule: function (e) {
            e.preventDefault();

            if (!window.AIPS.selectedTopics || window.AIPS.selectedTopics.length === 0) {
                alert(aipsResearchL10n.selectTopicSchedule);

                return;
            }

            var $form = $('#bulk-schedule-form');
            var $submit = $form.find('button[type="submit"]');
            var $spinner = $form.find('.spinner');

            $submit.prop('disabled', true).addClass('is-loading');
            $spinner.addClass('is-active');

            $.ajax({
                url: (window.AIPS && window.AIPS.resolveAjaxUrl) ? window.AIPS.resolveAjaxUrl() : '',
                type: 'POST',
                data: {
                    action: 'aips_schedule_trending_topics',
                    nonce: (window.AIPS && window.AIPS.resolveNonce) ? window.AIPS.resolveNonce() : '',
                    topic_ids: window.AIPS.selectedTopics,
                    template_id: $('#schedule-template').val(),
                    start_date: $('#schedule-start-date').val(),
                    frequency: $('#schedule-frequency').val()
                },
                success: function (response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.AIPS.selectedTopics = [];
                        $('.topic-checkbox').prop('checked', false);
                        $('#select-all-topics').prop('checked', false);
                    } else {
                        alert((response.data && response.data.message) ? response.data.message : 'Unknown error');
                    }
                },
                error: function () {
                    alert(aipsResearchL10n.schedulingError);
                },
                complete: function () {
                    $submit.prop('disabled', false).removeClass('is-loading');
                    $spinner.removeClass('is-active');
                }
            });
        }
    });

    // Bind Template Events on DOM Ready
    $(document).ready(function() {

        $(document).on('submit', '#aips-research-form', window.AIPS.handleResearchSubmit);
        $(document).on('click','#load-topics', window.AIPS.loadTopics);
        $(document).on('submit', '#bulk-schedule-form', window.AIPS.handleBulkSchedule);
        $(document).on('click', '.delete-topic', window.AIPS.deleteTopic);

        // Select all topics
        $(document).on('change', '#select-all-topics', function() {
            $('.topic-checkbox').prop('checked', $(this).is(':checked'));

            window.AIPS.updateSelectedTopics();
        });

        // Individual checkbox change
        $(document).on('change', '.topic-checkbox', function() {
            window.AIPS.updateSelectedTopics();
        });

        // Auto-load topics on page load if elements exist
        if ($('#load-topics').length > 0) {
            $('#load-topics').trigger('click');
        }
    });

 })(jQuery);
