(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with Planner functionality
    Object.assign(window.AIPS, {

        generateTopics: function(e) {
            e.preventDefault();
            var niche = $('#planner-niche').val();
            var count = $('#planner-count').val();

            if (!niche) {
                alert('Please enter a niche or topic.');
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
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
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
            if (!text) return;

            var topics = text.split('\n').map(function(t) { return t.trim(); }).filter(function(t) { return t.length > 0; });

            if (topics.length > 0) {
                window.AIPS.renderTopics(topics, true); // true = append
                $('#planner-results').slideDown();
                $('#planner-manual-topics').val('');
            }
        },

        renderTopics: function(topics, append) {
            var html = '';
            topics.forEach(function(topic) {
                // Escape HTML for value attribute
                var div = document.createElement('div');
                div.textContent = topic;
                var safeTopic = div.innerHTML.replace(/"/g, '&quot;');

                html += '<div class="topic-item">';
                html += '<input type="checkbox" class="topic-checkbox" checked>';
                html += '<input type="text" class="topic-text-input" value="' + safeTopic + '" aria-label="Edit topic title">';
                html += '</div>';
            });

            if (append) {
                $('#topics-list').append(html);
            } else {
                $('#topics-list').html(html);
            }

            window.AIPS.updateSelectionCount();
        },

        toggleAllTopics: function() {
            var isChecked = $(this).is(':checked');
            $('.topic-checkbox').prop('checked', isChecked);
            window.AIPS.updateSelectionCount();
        },

        updateSelectionCount: function() {
            var count = $('.topic-checkbox:checked').length;
            var text = count + ' selected';
            if (count === 0) text = '';
            $('.selection-count').text(text);
        },

        clearTopics: function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to clear the list?')) {
                return;
            }
            $('#topics-list').empty();
            $('#planner-results').slideUp();
            $('#planner-niche').val('');
            $('#check-all-topics').prop('checked', false);
            window.AIPS.updateSelectionCount();
        },

        copySelectedTopics: function(e) {
            e.preventDefault();
            var topics = [];
            $('.topic-checkbox:checked').each(function() {
                var val = $(this).siblings('.topic-text-input').val();
                if (val && val.trim().length > 0) {
                    topics.push(val.trim());
                }
            });

            if (topics.length === 0) {
                alert('Please select topics to copy.');
                return;
            }

            var textToCopy = topics.join('\n');
            var $btn = $(this);
            var originalText = $btn.html();

            // Use navigator.clipboard if available, otherwise fallback
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    window.AIPS.showCopyFeedback($btn, originalText);
                }).catch(function(err) {
                    console.error('Could not copy text: ', err);
                    window.AIPS.fallbackCopyText(textToCopy, $btn, originalText);
                });
            } else {
                window.AIPS.fallbackCopyText(textToCopy, $btn, originalText);
            }
        },

        showCopyFeedback: function($btn, originalText) {
            $btn.addClass('button-primary').text('Copied!');
            setTimeout(function() {
                $btn.removeClass('button-primary').html(originalText);
            }, 2000);
        },

        fallbackCopyText: function(text, $btn, originalText) {
            var textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";  // Avoid scrolling to bottom
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    window.AIPS.showCopyFeedback($btn, originalText);
                } else {
                    alert('Unable to copy to clipboard.');
                }
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
                alert('Unable to copy to clipboard.');
            }

            document.body.removeChild(textArea);
        },

        bulkSchedule: function(e) {
            e.preventDefault();
            var topics = [];

            // Iterate over checked checkboxes and get the value from the sibling text input
            $('.topic-checkbox:checked').each(function() {
                var val = $(this).siblings('.topic-text-input').val();
                if (val && val.trim().length > 0) {
                    topics.push(val.trim());
                }
            });

            if (topics.length === 0) {
                alert('Please select at least one topic.');
                return;
            }

            var templateId = $('#bulk-template').val();
            var startDate = $('#bulk-start-date').val();

            if (!templateId) {
                alert('Please select a template.');
                return;
            }
            if (!startDate) {
                alert('Please select a start date.');
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
                        alert(response.data.message);
                        // Clear list after successful scheduling
                         $('#topics-list').html('');
                         $('#planner-results').slideUp();
                         $('#planner-niche').val('');
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.next('.spinner').removeClass('is-active');
                }
            });
        }
    });

    // Bind Planner Events
    $(document).ready(function() {
        $(document).on('click', '#btn-generate-topics', window.AIPS.generateTopics);
        $(document).on('click', '#btn-parse-manual', window.AIPS.parseManualTopics);
        $(document).on('click', '#btn-bulk-schedule', window.AIPS.bulkSchedule);
        $(document).on('change', '#check-all-topics', window.AIPS.toggleAllTopics);
        $(document).on('change', '.topic-checkbox', window.AIPS.updateSelectionCount);
        $(document).on('click', '.aips-clear-list', window.AIPS.clearTopics);
        $(document).on('click', '.aips-copy-topics', window.AIPS.copySelectedTopics);
    });

})(jQuery);
