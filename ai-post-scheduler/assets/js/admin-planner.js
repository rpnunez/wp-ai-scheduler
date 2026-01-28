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
            $('.selection-count').text(count + ' selected');
        },

        clearTopics: function() {
            var $btn = $(this);
            var originalText = $btn.data('original-text') || $btn.text();

            // Store original text if not already stored
            if (!$btn.data('original-text')) {
                $btn.data('original-text', originalText);
            }

            if ($btn.data('is-confirming')) {
                // Second click - Execute
                $('#topics-list').empty();
                $('#planner-results').slideUp();
                $('#planner-niche').val('');
                $('#planner-manual-topics').val('');
                $('#planner-topic-search').val(''); // Clear search input
                window.AIPS.updateSelectionCount();

                // Reset button
                $btn.text(originalText);
                $btn.removeData('is-confirming');
                clearTimeout($btn.data('timeout'));
            } else {
                // First click - Ask for confirmation
                $btn.text('Click again to confirm');
                $btn.data('is-confirming', true);

                // Reset after 3 seconds
                var timeout = setTimeout(function() {
                    $btn.text(originalText);
                    $btn.removeData('is-confirming');
                }, 3000);

                $btn.data('timeout', timeout);
            }
        },

        filterTopics: function() {
            var term = $(this).val().toLowerCase();
            $('.topic-item').each(function() {
                var text = $(this).find('.topic-text-input').val().toLowerCase();
                $(this).toggle(text.indexOf(term) > -1);
            });
        },

        copySelectedTopics: function() {
            var topics = [];
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

            var textToCopy = topics.join('\n');
            var $btn = $('#btn-copy-topics');
            var originalText = $btn.text();

            var fallbackCopy = function() {
                var $temp = $('<textarea>');
                // Position off-screen to avoid layout issues
                $temp.css({
                    position: 'fixed',
                    top: '-9999px',
                    left: '-9999px'
                });
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
                    alert('Unable to copy text automatically. Please select the topics and copy them manually (Ctrl+C or Cmd+C on Mac).');
                }
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    $btn.text('Copied!');
                    setTimeout(function() { $btn.text(originalText); }, 2000);
                }).catch(function() {
                    // If the modern API fails, attempt the legacy fallback
                    fallbackCopy();
                });
            } else {
                // Legacy fallback for older browsers
                fallbackCopy();
            }
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
        $(document).on('click', '#btn-clear-topics', window.AIPS.clearTopics);
        $(document).on('click', '#btn-copy-topics', window.AIPS.copySelectedTopics);
        $(document).on('change', '#check-all-topics', window.AIPS.toggleAllTopics);
        $(document).on('change', '.topic-checkbox', window.AIPS.updateSelectionCount);
        $(document).on('keyup search', '#planner-topic-search', window.AIPS.filterTopics);
    });

})(jQuery);
