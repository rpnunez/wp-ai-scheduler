(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with Planner functionality
    Object.assign(window.AIPS, {

        generateTopicIdeas: function(e) {
            e.preventDefault();
            var keywords = $('#planner-niche').val();

            if (!keywords) {
                alert('Please enter keywords or a niche.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);
            $btn.nextAll('.spinner').addClass('is-active');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_generate_topic_ideas',
                    nonce: aipsAjax.nonce,
                    keywords: keywords
                },
                success: function(response) {
                    if (response.success) {
                        window.AIPS.renderTopics(response.data.topics, true); // true = append
                        $('#planner-results').slideDown();
                        $('#btn-fetch-more-topics').show(); // Show "Fetch More" button
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.nextAll('.spinner').removeClass('is-active');
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

            if (!$btn.data('original-text')) {
                $btn.data('original-text', originalText);
            }

            if ($btn.data('is-confirming')) {
                // Second click - Execute
                $('#topics-list').empty();
                $('#planner-results').slideUp();
                $('#planner-niche').val('');
                $('#planner-manual-topics').val('');
                $('#btn-fetch-more-topics').hide();
                window.AIPS.updateSelectionCount();

                // Reset button
                $btn.text(originalText);
                $btn.removeData('is-confirming');
                clearTimeout($btn.data('timeout'));
            } else {
                // First click - Ask for confirmation
                $btn.text('Click again to confirm');
                $btn.data('is-confirming', true);

                var timeout = setTimeout(function() {
                    $btn.text(originalText);
                    $btn.removeData('is-confirming');
                }, 3000);

                $btn.data('timeout', timeout);
            }
        },

        copySelectedTopics: function() {
            // ... (Existing copy logic is fine, omitting for brevity in this update unless user asks)
            // Reusing existing logic from previous file if possible, or re-implementing basic copy
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

            navigator.clipboard.writeText(textToCopy).then(function() {
                $btn.text('Copied!');
                setTimeout(function() { $btn.text(originalText); }, 2000);
            }).catch(function() {
                 alert('Manual copy required.');
            });
        },

        // --- Matrix Modal Functions ---

        openMatrix: function() {
            var count = $('.topic-checkbox:checked').length;
            if (count === 0) {
                alert('Please select at least one topic to schedule.');
                return;
            }
            $('#aips-matrix-modal').show();
        },

        closeMatrix: function() {
            $('#aips-matrix-modal').hide();
        },

        toggleMatrixRules: function() {
            var freq = $('#matrix-frequency').val();
            if (freq === 'custom') {
                $('#matrix-custom-rules').slideDown();
            } else {
                $('#matrix-custom-rules').slideUp();
            }
        },

        addTimeInput: function() {
            var html = '<div style="margin-top:5px;"><input type="time" name="times[]" value="12:00"> <button type="button" class="button button-small remove-time" onclick="$(this).parent().remove()">-</button></div>';
            $('#matrix-times-container').append(html);
        },

        saveMatrix: function() {
            var templateId = $('#matrix-template').val();
            if (!templateId) {
                alert('Please select a template.');
                return;
            }

            var topics = [];
            $('.topic-checkbox:checked').each(function() {
                var val = $(this).siblings('.topic-text-input').val();
                if (val) topics.push(val.trim());
            });

            // Prepare Data for Schedule
            var scheduleData = {
                action: 'aips_save_schedule',
                nonce: aipsAjax.nonce,
                template_id: templateId,
                frequency: $('#matrix-frequency').val(),
                start_time: $('#matrix-start-date').val(),
                is_active: 1,
                schedule_type: ($('#matrix-frequency').val() === 'custom' ? 'advanced' : 'simple')
            };

            // Gather Advanced Rules if Custom
            if (scheduleData.frequency === 'custom') {
                var rules = {
                    times: [],
                    days_of_week: [],
                    day_of_month: []
                };

                // Times
                $('input[name="times[]"]').each(function() {
                    if ($(this).val()) rules.times.push($(this).val());
                });

                // Days of Week
                $('input[name="days_of_week[]"]:checked').each(function() {
                    rules.days_of_week.push(parseInt($(this).val()));
                });

                // Day of Month
                var dom = $('input[name="day_of_month"]').val();
                if (dom) {
                    rules.day_of_month = dom.split(',').map(function(d) { return parseInt(d.trim()); });
                }

                scheduleData.advanced_rules = rules;
            }

            var $btn = $('#btn-save-matrix');
            $btn.prop('disabled', true).text('Saving...');

            // Step 1: Create Schedule
            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: scheduleData,
                success: function(response) {
                    if (response.success) {
                        var scheduleId = response.data.id;

                        // Step 2: Add Topics to Queue
                        $.ajax({
                            url: aipsAjax.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'aips_add_to_queue',
                                nonce: aipsAjax.nonce,
                                schedule_id: scheduleId,
                                topics: topics
                            },
                            success: function(queueRes) {
                                if (queueRes.success) {
                                    alert('Success! Schedule created and ' + queueRes.data.count + ' topics queued.');
                                    window.AIPS.closeMatrix();
                                    // Clear list
                                    $('#topics-list').empty();
                                    $('#planner-results').slideUp();
                                    window.location.reload(); // Reload to show new schedule or calendar
                                } else {
                                    alert('Schedule created but failed to queue topics: ' + queueRes.data.message);
                                }
                            }
                        });
                    } else {
                        alert('Failed to create schedule: ' + response.data.message);
                        $btn.prop('disabled', false).text('Save Schedule & Queue Topics');
                    }
                },
                error: function() {
                    alert('Server error.');
                    $btn.prop('disabled', false).text('Save Schedule & Queue Topics');
                }
            });
        }
    });

    // Bind Events
    $(document).ready(function() {
        $(document).on('click', '#btn-generate-topics, #btn-fetch-more-topics', window.AIPS.generateTopicIdeas);
        $(document).on('click', '#btn-parse-manual', window.AIPS.parseManualTopics);
        $(document).on('click', '#btn-clear-topics', window.AIPS.clearTopics);
        $(document).on('click', '#btn-copy-topics', window.AIPS.copySelectedTopics);
        $(document).on('change', '#check-all-topics', window.AIPS.toggleAllTopics);
        $(document).on('change', '.topic-checkbox', window.AIPS.updateSelectionCount);

        // Matrix Events
        $(document).on('click', '#btn-open-matrix', window.AIPS.openMatrix);
        $(document).on('click', '.aips-modal-close', window.AIPS.closeMatrix);
        $(document).on('change', '#matrix-frequency', window.AIPS.toggleMatrixRules);
        $(document).on('click', '#btn-add-time', window.AIPS.addTimeInput);
        $(document).on('click', '#btn-save-matrix', window.AIPS.saveMatrix);
    });

})(jQuery);
