(function($) {
    'use strict';

    $(document).ready(function() {
        let selectedTopics = [];

        // Local helper for HTML escaping to prevent XSS
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
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert(aipsResearchL10n.researchError);
                },
                complete: function() {
                    $submit.prop('disabled', false).removeClass('is-loading');
                    $spinner.removeClass('is-active');
                }
            });
        });

        // Display research results
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
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        });

        // Display topics table
        function displayTopicsTable(topics) {
            if (!topics || topics.length === 0) {
                $('#topics-container').html('<p>' + aipsResearchL10n.noTopicsFound + '</p>');
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
            $('#topics-container').html(html);

            // Show bulk schedule section and bulk actions
            $('#bulk-schedule-section').show();
            $('#aips-research-bulk-actions').show();
        }

        // Select all topics
        $(document).on('change', '#select-all-topics', function() {
            $('.topic-checkbox').prop('checked', $(this).is(':checked'));
            updateSelectedTopics();
        });

        // Individual checkbox change
        $(document).on('change', '.topic-checkbox', function() {
            updateSelectedTopics();
        });

        // Update selected topics
        function updateSelectedTopics() {
            selectedTopics = $('.topic-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            // Update copy button state
            $('#copy-selected-topics').prop('disabled', selectedTopics.length === 0);
        }

        // Copy selected topics
        $(document).on('click', '#copy-selected-topics', function() {
            const topics = [];
            $('.topic-checkbox:checked').each(function() {
                // Navigate from checkbox to the topic text cell (2nd column)
                // Structure: tr > td > checkbox
                // Target: tr > td:nth-child(2) > strong
                const topicText = $(this).closest('tr').find('td:nth-child(2) strong').text();
                if (topicText) {
                    topics.push(topicText.trim());
                }
            });

            if (topics.length === 0) {
                return;
            }

            const textToCopy = topics.join('\n');
            const $btn = $(this);
            const originalHtml = $btn.html();

            // Fallback copy mechanism (reused from planner)
            const fallbackCopy = function() {
                const $temp = $('<textarea>');
                $temp.css({ position: 'fixed', top: '-9999px', left: '-9999px' });
                $('body').append($temp);
                $temp.val(textToCopy).trigger('focus').trigger('select');

                let success = false;
                try {
                    success = document.execCommand('copy');
                } catch (err) {
                    success = false;
                }

                $temp.remove();
                return success;
            };

            const showFeedback = function() {
                $btn.html('<span class="dashicons dashicons-saved" style="line-height: 1.3;"></span> Copied!');
                setTimeout(function() { $btn.html(originalHtml); }, 2000);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(showFeedback).catch(function() {
                    if (fallbackCopy()) showFeedback();
                    else alert('Unable to copy text. Please select topics and copy manually.');
                });
            } else {
                if (fallbackCopy()) showFeedback();
                else alert('Unable to copy text. Please select topics and copy manually.');
            }
        });

        // Delete topic
        $(document).on('click', '.delete-topic', function() {
            if (!confirm(aipsResearchL10n.deleteTopicConfirm)) {
                return;
            }

            const topicId = $(this).data('id');

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
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        });

        // Bulk schedule
        $('#bulk-schedule-form').on('submit', function(e) {
            e.preventDefault();

            if (selectedTopics.length === 0) {
                alert(aipsResearchL10n.selectTopicSchedule);
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
                        alert(response.data.message);
                        selectedTopics = [];
                        $('.topic-checkbox').prop('checked', false);
                        $('#select-all-topics').prop('checked', false);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert(aipsResearchL10n.schedulingError);
                },
                complete: function() {
                    $submit.prop('disabled', false).removeClass('is-loading');
                    $spinner.removeClass('is-active');
                }
            });
        });

        // Auto-load topics on page load if elements exist
        if ($('#load-topics').length > 0) {
            $('#load-topics').trigger('click');
        }
    });

})(jQuery);
