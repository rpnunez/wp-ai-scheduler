(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};

    Object.assign(window.AIPS, {
        plannerMixTimer: null,
        plannerMixRequest: null,

        generateTopics: function(e) {
            e.preventDefault();
            var niche = $('#planner-niche').val();
            var count = $('#planner-count').val();

            if (!niche) {
                AIPS.Utilities.showToast('Please enter a niche or topic.', 'warning');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);
            $btn.next('.spinner').addClass('is-active');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_generate_topics',
                    nonce: aipsAjax.nonce,
                    niche: niche,
                    count: count
                },
                success: function(response) {
                    if (response.success) {
                        window.AIPS.renderTopics(response.data.topics);
                        $('#planner-results').slideDown();
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.next('.spinner').removeClass('is-active');
                }
            });
        },

        parseManualTopics: function(e) {
            e.preventDefault();
            var text = $('#planner-manual-topics').val();
            if (!text) {
                return;
            }

            var topics = text.split('\n').map(function(topic) {
                return topic.trim();
            }).filter(function(topic) {
                return topic.length > 0;
            });

            if (topics.length > 0) {
                window.AIPS.renderTopics(topics, true);
                $('#planner-results').slideDown();
                $('#planner-manual-topics').val('');
            }
        },

        renderTopics: function(topics, append) {
            var html = '';
            topics.forEach(function(topic) {
                var div = document.createElement('div');
                div.textContent = topic;
                var safeTopic = div.innerHTML.replace(/"/g, '&quot;');

                html += '<div class="topic-item">';
                html += '<input type="checkbox" class="topic-checkbox" checked>';
                html += '<input type="text" class="topic-text-input" value="' + safeTopic + '" aria-label="Edit topic title">';
                html += '<button type="button" class="aips-remove-topic-btn" aria-label="Remove Topic" title="Remove Topic"><span class="dashicons dashicons-dismiss"></span></button>';
                html += '</div>';
            });

            if (append) {
                $('#topics-list').append(html);
            } else {
                $('#topics-list').html(html);
            }

            window.AIPS.updateSelectionCount();
            window.AIPS.scheduleMixEvaluation();
        },

        removeTopic: function(e) {
            e.preventDefault();
            var $item = $(this).closest('.topic-item');

            $item.fadeOut(200, function() {
                $(this).remove();
                window.AIPS.updateSelectionCount();
                if ($('#topics-list .topic-item').length === 0) {
                    $('#planner-results').slideUp();
                    $('#planner-niche').val('');
                    $('#planner-topic-search').val('');
                }
                window.AIPS.scheduleMixEvaluation();
            });
        },

        filterTopics: function() {
            var term = $('#planner-topic-search').val().toLowerCase();
            var $clearBtn = $('#planner-topic-search-clear');

            if (term) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }

            $('.topic-item').each(function() {
                var text = $(this).find('.topic-text-input').val().toLowerCase();
                $(this).toggle(text.indexOf(term) > -1);
            });

            var $topicsList = $('#topics-list');
            var visibleCount = $topicsList.find('.topic-item:visible').length;
            var $emptyState = $topicsList.find('.topics-empty-state');
            if (term && visibleCount === 0) {
                if ($emptyState.length === 0) {
                    $topicsList.append('<div class="topics-empty-state" style="padding: 20px; text-align: center; color: #666;">No topics match your search.</div>');
                }
            } else if ($emptyState.length) {
                $emptyState.remove();
            }
        },

        toggleAllTopics: function() {
            var isChecked = $(this).is(':checked');
            $('.topic-checkbox:visible').prop('checked', isChecked);
            window.AIPS.updateSelectionCount();
            window.AIPS.scheduleMixEvaluation();
        },

        updateSelectionCount: function() {
            var count = $('.topic-checkbox:checked').length;
            $('.selection-count').text(count + ' selected');
        },

        clearTopics: function() {
            var $btn = $(this);
            var originalText = $btn.data('original-text') || $btn.text();

            if (!$btn.data('original-text')) {
                $btn.data('original-text', originalText);
            }

            if ($btn.data('is-confirming')) {
                $('#topics-list').empty();
                $('#planner-results').slideUp();
                $('#planner-niche').val('');
                $('#planner-manual-topics').val('');
                $('#planner-topic-search').val('');
                window.AIPS.updateSelectionCount();
                window.AIPS.resetMixInsights();

                $btn.text(originalText);
                $btn.removeData('is-confirming');
                clearTimeout($btn.data('timeout'));
            } else {
                $btn.text('Click again to confirm');
                $btn.data('is-confirming', true);

                var timeout = setTimeout(function() {
                    $btn.text(originalText);
                    $btn.removeData('is-confirming');
                }, 3000);

                $btn.data('timeout', timeout);
            }
        },

        clearTopicSearch: function() {
            $('#planner-topic-search').val('').trigger('keyup');
        },

        copySelectedTopics: function() {
            var topics = window.AIPS.getSelectedTopics();
            if (topics.length === 0) {
                AIPS.Utilities.showToast('Please select at least one topic.', 'warning');
                return;
            }

            var textToCopy = topics.join('\n');
            var $btn = $('#btn-copy-topics');
            var originalText = $btn.text();

            var fallbackCopy = function() {
                var $temp = $('<textarea>');
                $temp.css({ position: 'fixed', top: '-9999px', left: '-9999px' });
                $('body').append($temp);
                $temp.val(textToCopy).trigger('focus').trigger('select');

                var success = false;
                try {
                    if (typeof document.queryCommandSupported !== 'function' || document.queryCommandSupported('copy')) {
                        success = document.execCommand('copy');
                    }
                } catch (err) {
                    success = false;
                }

                $temp.remove();

                if (success) {
                    $btn.text('Copied!');
                    setTimeout(function() { $btn.text(originalText); }, 2000);
                } else {
                    AIPS.Utilities.showToast('Unable to copy text automatically. Please select the topics and copy them manually (Ctrl+C or Cmd+C on Mac).', 'warning');
                }
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    $btn.text('Copied!');
                    setTimeout(function() { $btn.text(originalText); }, 2000);
                }).catch(function() {
                    fallbackCopy();
                });
            } else {
                fallbackCopy();
            }
        },

        bulkSchedule: function(e) {
            e.preventDefault();
            var topics = window.AIPS.getSelectedTopics();

            if (topics.length === 0) {
                AIPS.Utilities.showToast('Please select at least one topic.', 'warning');
                return;
            }

            var templateId = $('#bulk-template').val();
            var startDate = $('#bulk-start-date').val();

            if (!templateId) {
                AIPS.Utilities.showToast('Please select a template.', 'warning');
                return;
            }
            if (!startDate) {
                AIPS.Utilities.showToast('Please select a start date.', 'warning');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);
            $btn.next('.spinner').addClass('is-active');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_schedule',
                    nonce: aipsAjax.nonce,
                    topics: topics,
                    template_id: templateId,
                    start_date: startDate,
                    frequency: $('#bulk-frequency').val()
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success');
                        $('#topics-list').html('');
                        $('#planner-results').slideUp();
                        $('#planner-niche').val('');
                        window.AIPS.resetMixInsights();
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.next('.spinner').removeClass('is-active');
                }
            });
        },

        getSelectedTopics: function() {
            var topics = [];
            $('.topic-checkbox:checked').each(function() {
                var value = $(this).siblings('.topic-text-input').val();
                if (value && value.trim().length > 0) {
                    topics.push(value.trim());
                }
            });
            return topics;
        },

        scheduleMixEvaluation: function() {
            clearTimeout(window.AIPS.plannerMixTimer);
            window.AIPS.plannerMixTimer = setTimeout(function() {
                window.AIPS.refreshMixInsights();
            }, 250);
        },

        refreshMixInsights: function() {
            var topics = window.AIPS.getSelectedTopics();
            if (window.AIPS.plannerMixRequest && typeof window.AIPS.plannerMixRequest.abort === 'function') {
                window.AIPS.plannerMixRequest.abort();
            }

            window.AIPS.plannerMixRequest = $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_score_planner_mix',
                    nonce: aipsAjax.nonce,
                    topics: topics,
                    template_id: $('#bulk-template').val(),
                    start_date: $('#bulk-start-date').val(),
                    frequency: $('#bulk-frequency').val()
                },
                success: function(response) {
                    if (response.success) {
                        window.AIPS.renderMixInsights(response.data);
                    }
                }
            });
        },

        resetMixInsights: function() {
            $('#planner-candidate-insights').html('<p style="margin:0;color:#64748b;">Select topics to preview whether each proposed item improves or worsens the weekly editorial mix.</p>');
            $('#planner-projected-overview').html('<p style="margin:0;color:#64748b;">The planner will forecast beat concentration, evergreen quota, and format balance once you choose a template, start date, and cadence.</p>');
        },

        renderMixInsights: function(data) {
            var candidateScores = data.candidate_scores || { items: [], summary: {} };
            var projectedReport = data.projected_report || {};
            var suggestions = data.suggestions || [];
            var currentReport = data.current_report || {};

            $('#planner-candidate-insights').html(window.AIPS.renderCandidateScorecard(candidateScores));
            $('#planner-projected-overview').html(window.AIPS.renderProjectedOverview(projectedReport));
            $('#planner-rebalance-suggestions').html(window.AIPS.renderSuggestions(suggestions));
            $('#planner-balance-overview').html(window.AIPS.renderCurrentWarnings(currentReport));
        },

        renderCandidateScorecard: function(candidateScores) {
            var items = candidateScores.items || [];
            var summary = candidateScores.summary || {};
            if (!items.length) {
                return '<p style="margin:0;color:#64748b;">Select one or more topics to score them against the weekly mix rules.</p>';
            }

            var html = '';
            html += '<div class="aips-toolbar" style="gap:8px; flex-wrap:wrap; margin-bottom:12px;">';
            html += '<span class="aips-badge aips-badge-success">Improved: ' + (summary.improved || 0) + '</span>';
            html += '<span class="aips-badge aips-badge-warning">Worsened: ' + (summary.worsened || 0) + '</span>';
            html += '<span class="aips-badge aips-badge-neutral">Neutral: ' + (summary.neutral || 0) + '</span>';
            html += '</div>';
            html += '<div style="display:grid; gap:10px;">';
            items.forEach(function(item) {
                var impactClass = item.impact === 'improved' ? 'notice-success' : (item.impact === 'worsened' ? 'notice-warning' : 'notice-info');
                html += '<div class="notice inline ' + impactClass + '" style="margin:0;">';
                html += '<p style="margin-bottom:6px;"><strong>' + window.AIPS.escapeHtml(item.title) + '</strong> — score ' + item.score + ' • ' + window.AIPS.escapeHtml(item.impact) + '</p>';
                html += '<p style="margin:0;font-size:12px;">Beat: ' + window.AIPS.escapeHtml(item.beat) + ' • Format: ' + window.AIPS.escapeHtml(item.format) + ' • ' + (item.evergreen ? 'Evergreen' : 'Timely') + '</p>';
                if (item.warnings && item.warnings.length) {
                    html += '<ul style="margin:8px 0 0 18px;">';
                    item.warnings.forEach(function(warning) {
                        html += '<li>' + window.AIPS.escapeHtml(warning) + '</li>';
                    });
                    html += '</ul>';
                } else if (item.notes && item.notes.length) {
                    html += '<ul style="margin:8px 0 0 18px;">';
                    item.notes.slice(0, 2).forEach(function(note) {
                        html += '<li>' + window.AIPS.escapeHtml(note) + '</li>';
                    });
                    html += '</ul>';
                }
                html += '</div>';
            });
            html += '</div>';
            return html;
        },

        renderProjectedOverview: function(report) {
            if (!report.total_items) {
                return '<p style="margin:0;color:#64748b;">Projected mix details will appear here once candidate topics are selected.</p>';
            }

            var html = '';
            html += '<p style="margin-top:0;"><strong>' + report.total_items + '</strong> items in the next ' + report.window_days + ' days • Evergreen share ' + (report.evergreen_share || 0) + '% • Imbalance score ' + (report.imbalance_score || 0) + '</p>';
            if (report.warnings && report.warnings.length) {
                report.warnings.forEach(function(warning) {
                    html += '<div class="notice notice-warning inline" style="margin:0 0 8px 0;"><p>' + window.AIPS.escapeHtml(warning.message) + '</p></div>';
                });
            } else {
                html += '<div class="notice notice-success inline" style="margin:0 0 8px 0;"><p>Projected calendar stays within the current mix thresholds.</p></div>';
            }

            html += '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:10px; margin-top:12px;">';
            ['beats', 'formats', 'authors'].forEach(function(group) {
                var entries = report[group] || {};
                var keys = Object.keys(entries).slice(0, 3);
                html += '<div><strong>' + window.AIPS.escapeHtml(group.charAt(0).toUpperCase() + group.slice(1)) + '</strong>';
                if (!keys.length) {
                    html += '<div style="font-size:12px;color:#64748b;">No data yet.</div>';
                } else {
                    html += '<ul style="margin:8px 0 0 18px;">';
                    keys.forEach(function(key) {
                        html += '<li>' + window.AIPS.escapeHtml(key) + ': ' + entries[key].count + ' (' + entries[key].share + '%)</li>';
                    });
                    html += '</ul>';
                }
                html += '</div>';
            });
            html += '</div>';
            return html;
        },

        renderSuggestions: function(suggestions) {
            if (!suggestions.length) {
                return '<p style="margin:0;color:#64748b;">No better rebalance suggestions surfaced from research or approved-topic queues yet.</p>';
            }

            var html = '<ul style="margin:0; padding-left:18px; display:grid; gap:8px;">';
            suggestions.forEach(function(item) {
                html += '<li><strong>' + window.AIPS.escapeHtml(item.title) + '</strong>';
                html += '<div style="font-size:12px; color:#64748b;">' + window.AIPS.escapeHtml((item.source_label || 'Queue') + ' • ' + item.format + ' • score ' + item.score + ' • ' + item.impact) + '</div>';
                if (item.notes && item.notes.length) {
                    html += '<div style="font-size:12px; color:#64748b;">' + window.AIPS.escapeHtml(item.notes[0]) + '</div>';
                }
                html += '</li>';
            });
            html += '</ul>';
            return html;
        },

        renderCurrentWarnings: function(report) {
            var warnings = report.warnings || [];
            var beats = report.beats || {};
            var keys = Object.keys(beats).slice(0, 3);
            var html = '';

            if (warnings.length) {
                warnings.forEach(function(warning) {
                    html += '<div class="notice notice-warning inline" style="margin:0 0 8px 0;"><p>' + window.AIPS.escapeHtml(warning.message) + '</p></div>';
                });
            } else {
                html += '<div class="notice notice-success inline" style="margin:0 0 8px 0;"><p>The current upcoming calendar is within the configured balance thresholds.</p></div>';
            }

            if (keys.length) {
                html += '<ul style="margin:12px 0 0 18px;">';
                keys.forEach(function(key) {
                    html += '<li>' + window.AIPS.escapeHtml(key) + ': ' + beats[key].count + ' items (' + beats[key].share + '%)</li>';
                });
                html += '</ul>';
            }

            return html;
        },

        escapeHtml: function(value) {
            return $('<div>').text(value || '').html();
        }
    });

    $(document).ready(function() {
        $(document).on('click', '#btn-generate-topics', window.AIPS.generateTopics);
        $(document).on('click', '#btn-parse-manual', window.AIPS.parseManualTopics);
        $(document).on('click', '#btn-bulk-schedule', window.AIPS.bulkSchedule);
        $(document).on('click', '#btn-clear-topics', window.AIPS.clearTopics);
        $(document).on('click', '#btn-copy-topics', window.AIPS.copySelectedTopics);
        $(document).on('keyup search', '#planner-topic-search', window.AIPS.filterTopics);
        $(document).on('change', '#check-all-topics', window.AIPS.toggleAllTopics);
        $(document).on('change', '.topic-checkbox', function() {
            window.AIPS.updateSelectionCount();
            window.AIPS.scheduleMixEvaluation();
        });
        $(document).on('keyup change', '.topic-text-input', window.AIPS.scheduleMixEvaluation);
        $(document).on('change', '#bulk-template, #bulk-start-date, #bulk-frequency', window.AIPS.scheduleMixEvaluation);
        $(document).on('click', '#planner-topic-search-clear', window.AIPS.clearTopicSearch);
        $(document).on('click', '.aips-remove-topic-btn', window.AIPS.removeTopic);
    });
})(jQuery);
