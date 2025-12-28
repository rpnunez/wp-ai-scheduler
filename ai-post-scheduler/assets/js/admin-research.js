(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with Research functionality
    Object.assign(window.AIPS, {

        researchTopics: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submit = $('#research-submit');
            var $spinner = $form.find('.spinner');

            var niche = $('#research-niche').val();
            var count = $('#research-count').val();
            var keywordsStr = $('#research-keywords').val();
            var keywords = keywordsStr ? keywordsStr.split(',').map(function(k) { return k.trim(); }) : [];

            $submit.prop('disabled', true).addClass('is-loading');
            $spinner.addClass('is-active');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_research_topics',
                    nonce: aipsAjax.nonce,
                    niche: niche,
                    count: count,
                    keywords: keywords
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.displayResearchResults(response.data);
                        $('#load-topics').trigger('click'); // Refresh topics list
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred during research.');
                },
                complete: function() {
                    $submit.prop('disabled', false).removeClass('is-loading');
                    $spinner.removeClass('is-active');
                }
            });
        },

        displayResearchResults: function(data) {
            var $container = $('#research-results-content');
            // Using AIPS.escapeHtml (defined in admin.js) for niche and topic data to prevent XSS
            var html = '<p><strong>' + data.saved_count + ' topics saved for "' + AIPS.escapeHtml(data.niche) + '"</strong></p>';

            if (data.top_topics && data.top_topics.length > 0) {
                html += '<h4>Top 5 Topics:</h4><ol>';
                data.top_topics.forEach(function(topic) {
                    var scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
                    // Escape user-generated content
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

        loadTopics: function() {
            var niche = $('#filter-niche').val();
            var minScore = $('#filter-score').val();
            var freshOnly = $('#filter-fresh').is(':checked');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_trending_topics',
                    nonce: aipsAjax.nonce,
                    niche: niche,
                    min_score: minScore,
                    fresh_only: freshOnly,
                    limit: 50
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.displayTopicsTable(response.data.topics);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        },

        displayTopicsTable: function(topics) {
            if (!topics || topics.length === 0) {
                $('#topics-container').html('<p>No topics found matching your filters.</p>');
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
                html += '<button class="button button-small delete-topic" data-id="' + AIPS.escapeHtml(topic.id) + '">Delete</button>';
                html += '</div></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            $('#topics-container').html(html);

            // Show bulk schedule section
            $('#bulk-schedule-section').show();
        },

        deleteTopic: function() {
            if (!confirm('Delete this topic?')) {
                return;
            }

            var topicId = $(this).data('id');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_delete_trending_topic',
                    nonce: aipsAjax.nonce,
                    topic_id: topicId
                },
                success: function(response) {
                    if (response.success) {
                        $('#load-topics').trigger('click');
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        },

        selectAllTopics: function() {
            $('.topic-checkbox').prop('checked', $(this).is(':checked'));
            AIPS.updateSelectedTopics();
        },

        updateSelectedTopics: function() {
            // AIPS.selectedTopics needs to be maintained, or we can just read from DOM when needed
            // The original code used a local variable. Let's use a property on AIPS if needed,
            // or just read checked boxes at submission time to be stateless.
        },

        scheduleTrendingTopics: function(e) {
            e.preventDefault();

            var selectedTopics = $('.topic-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedTopics.length === 0) {
                alert('Please select at least one topic to schedule.');
                return;
            }

            var $form = $(this);
            var $submit = $form.find('button[type="submit"]');
            var $spinner = $form.find('.spinner');

            $submit.prop('disabled', true).addClass('is-loading');
            $spinner.addClass('is-active');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_schedule_trending_topics',
                    nonce: aipsAjax.nonce,
                    topic_ids: selectedTopics,
                    template_id: $('#schedule-template').val(),
                    start_date: $('#schedule-start-date').val(),
                    frequency: $('#schedule-frequency').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        $('.topic-checkbox').prop('checked', false);
                        $('#select-all-topics').prop('checked', false);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred during scheduling.');
                },
                complete: function() {
                    $submit.prop('disabled', false).removeClass('is-loading');
                    $spinner.removeClass('is-active');
                }
            });
        }
    });

    // Bind Research Events
    $(document).ready(function() {
        if ($('#aips-research-form').length) {
            $(document).on('submit', '#aips-research-form', AIPS.researchTopics);
            $(document).on('click', '#load-topics', AIPS.loadTopics);
            $(document).on('change', '#select-all-topics', AIPS.selectAllTopics);
            // We don't need explicit change listener for individual checkbox if we read on submit
            $(document).on('click', '.delete-topic', AIPS.deleteTopic);
            $(document).on('submit', '#bulk-schedule-form', AIPS.scheduleTrendingTopics);

            // Auto-load topics on page load
            AIPS.loadTopics();
        }
    });

})(jQuery);
