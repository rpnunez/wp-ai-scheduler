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
        function updateSelectedTopics() {
            selectedTopics = $('.topic-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
        }

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
    });

})(jQuery);
