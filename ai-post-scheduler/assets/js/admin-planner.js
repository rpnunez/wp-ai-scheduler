(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with Planner functionality
    Object.assign(window.AIPS, {

        /**
         * Generate blog topics from the AI engine based on a niche/keyword.
         *
         * Reads the niche from `#planner-niche` and the desired count from
         * `#planner-count`, then sends the `aips_generate_topics` AJAX action.
         * On success, passes the returned topics to `renderTopics` and slides
         * down the results panel.
         *
         * @param {Event} e - Click event from `#btn-generate-topics`.
         */
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

        /**
         * Parse a newline-separated list of topics from the manual entry textarea.
         *
         * Reads `#planner-manual-topics`, splits on newlines, trims whitespace,
         * and filters empty lines. Passes the resulting array to `renderTopics`
         * with `append=true` so existing topics are preserved. Clears the
         * textarea on success.
         *
         * @param {Event} e - Click event from `#btn-parse-manual`.
         */
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

        /**
         * Render an array of topic strings as editable checkbox rows in `#topics-list`.
         *
         * Each topic is HTML-escaped before being inserted as an `<input value>`.
         * When `append` is `true` the new rows are appended to any existing rows;
         * otherwise the list is replaced entirely. Calls `updateSelectionCount`
         * after rendering.
         *
         * @param {Array<string>} topics - Array of topic title strings to render.
         * @param {boolean}       [append=false] - If `true`, append rather than replace.
         */
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
                html += '<button type="button" class="aips-remove-topic-btn" aria-label="Remove Topic" title="Remove Topic"><span class="dashicons dashicons-dismiss"></span></button>';
                html += '</div>';
            });

            if (append) {
                $('#topics-list').append(html);
            } else {
                $('#topics-list').html(html);
            }

            window.AIPS.updateSelectionCount();
        },

        /**
         * Remove a single topic row from the list.
         *
         * Bound to the `click` event on `.aips-remove-topic-btn`.
         * Removes the row, updates the selection count, and hides the panel if no topics remain.
         *
         * @param {Event} e - Click event from `.aips-remove-topic-btn`.
         */
        removeTopic: function(e) {
            e.preventDefault();
            var $item = $(this).closest('.topic-item');

            $item.fadeOut(200, function() {
                $(this).remove();
                window.AIPS.updateSelectionCount();

                // Hide panel if list is completely empty
                if ($('#topics-list .topic-item').length === 0) {
                    $('#planner-results').slideUp();
                    $('#planner-niche').val('');
                    $('#planner-topic-search').val('');
                }
            });
        },

        /**
         * Show or hide `.topic-item` rows based on whether their text input
         * value matches the current `#planner-topic-search` value.
         *
         * Only tests `.topic-item` elements that are currently visible.
         * Calls `updateSelectionCount` after filtering to keep the count accurate.
         *
         * Bound to the first `keyup search` listener on `#planner-topic-search`.
         */
        filterTopics: function() {
            var filter = $('#planner-topic-search').val().toLowerCase();
            $('.topic-item').each(function() {
                var text = $(this).find('.topic-text-input').val().toLowerCase();
                if (text.indexOf(filter) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            window.AIPS.updateSelectionCount();
        },

        /**
         * Sync all `.topic-checkbox` elements with the state of the
         * `#check-all-topics` "select all" checkbox.
         *
         * Only `.topic-checkbox:visible` elements are toggled so hidden (filtered)
         * rows are not accidentally selected.
         * Calls `updateSelectionCount` to refresh the count label.
         *
         * Bound to the `change` event on `#check-all-topics`.
         */
        toggleAllTopics: function() {
            var isChecked = $(this).is(':checked');
            $('.topic-checkbox:visible').prop('checked', isChecked);
            window.AIPS.updateSelectionCount();
        },

        /**
         * Update the "N selected" label next to the topic list.
         *
         * Counts the number of checked `.topic-checkbox` elements (regardless of
         * visibility) and updates every `.selection-count` element.
         */
        updateSelectionCount: function() {
            var count = $('.topic-checkbox:checked').length;
            $('.selection-count').text(count + ' selected');
        },

        /**
         * Clear all generated topics using a two-click soft-confirm pattern.
         *
         * The first click changes the button label to "Click again to confirm" and
         * starts a 3-second auto-reset timer. The second click (within the window)
         * empties `#topics-list`, hides the results panel, clears all input fields,
         * and resets the selection count.
         *
         * Bound to the `click` event on `#btn-clear-topics`.
         */
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

        /**
         * Filter `.topic-item` rows in real time and manage the clear button.
         *
         * Shows or hides the `#planner-topic-search-clear` button depending on
         * whether the search field is non-empty. Shows an inline empty-state
         * message when no topics match the term. Removes the empty-state message
         * when the field is cleared or topics become visible again.
         *
         * Bound to the second `keyup search` listener on `#planner-topic-search`.
         */
        filterTopics: function() {
            var term = $(this).val().toLowerCase();
            var $clearBtn = $('#planner-topic-search-clear');
            
            // Show/hide clear button based on search term
            if (term) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }
            
            $('.topic-item').each(function() {
                var text = $(this).find('.topic-text-input').val().toLowerCase();
                $(this).toggle(text.indexOf(term) > -1);
            });
            
            // Show an empty state message when no topics match the filter
            var $topicsList = $('#topics-list');
            var visibleCount = $topicsList.find('.topic-item:visible').length;
            var $emptyState = $topicsList.find('.topics-empty-state');

            if (term && visibleCount === 0) {
                if ($emptyState.length === 0) {
                    $topicsList.append('<div class="topics-empty-state" style="padding: 20px; text-align: center; color: #666;">No topics match your search.</div>');
                }
            } else {
                if ($emptyState.length) {
                    $emptyState.remove();
                }
            }
        },

        /**
         * Clear the topic search field and re-trigger the keyup event to
         * restore all hidden rows.
         *
         * Bound to the `click` event on `#planner-topic-search-clear`.
         */
        clearTopicSearch: function() {
            $('#planner-topic-search').val('').trigger('keyup');
        },

        /**
         * Copy all checked topic titles to the clipboard as a newline-separated list.
         *
         * Collects the trimmed text value of each sibling `.topic-text-input` for
         * every checked `.topic-checkbox`. Uses the Clipboard API when available,
         * falling back to `document.execCommand('copy')` for older browsers.
         * Briefly changes the button label to "Copied!" on success.
         *
         * Bound to the `click` event on `#btn-copy-topics`.
         */
        copySelectedTopics: function() {
            var topics = [];
            $('.topic-checkbox:checked').each(function() {
                var val = $(this).siblings('.topic-text-input').val();
                if (val && val.trim().length > 0) {
                    topics.push(val.trim());
                }
            });

            if (topics.length === 0) {
                AIPS.Utilities.showToast('Please select at least one topic.', 'warning');
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
                    AIPS.Utilities.showToast('Unable to copy text automatically. Please select the topics and copy them manually (Ctrl+C or Cmd+C on Mac).', 'warning');
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

        /**
         * Generate all checked topics immediately via the `aips_bulk_generate_now` AJAX
         * action.
         *
         * Validates that at least one topic is selected and a template is chosen
         * before sending. Clears the topic list and hides the results panel on success.
         *
         * @param {Event} e - Click event from `#btn-bulk-generate-now`.
         */
        bulkGenerateNow: function(e) {
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
                AIPS.Utilities.showToast('Please select at least one topic.', 'warning');
                return;
            }

            var templateId = $('#bulk-template').val();

            if (!templateId) {
                AIPS.Utilities.showToast('Please select a template.', 'warning');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);
            $btn.nextAll('.spinner').first().addClass('is-active');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_generate_now',
                    nonce: aipsAjax.nonce,
                    topics: topics,
                    template_id: templateId
                },
                success: function(response) {
                    if (response && response.success) {
                        var data = response.data || {};
                        var failedTopics = data.failed_topics || data.errors || [];
                        var hasFailedTopics = $.isArray(failedTopics) ? failedTopics.length > 0 : false;

                        if (hasFailedTopics) {
                            // Partial success: keep topics so user can review/retry failed ones.
                            var partialMsg = data.message || 'Some topics could not be generated. Please review and try again.';
                            AIPS.Utilities.showToast(partialMsg, 'warning');
                        } else {
                            // Full success: clear list and reset planner inputs as before.
                            var successMsg = data.message || 'Posts generated successfully.';
                            AIPS.Utilities.showToast(successMsg, 'success');
                            // Clear list after successful scheduling
                            $('#topics-list').html('');
                            $('#planner-results').slideUp();
                            $('#planner-niche').val('');
                            $('#planner-manual-topics').val('');
                            $('#planner-topic-search').val('');
                            window.AIPS.updateSelectionCount();
                        }
                    } else {
                        var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'An error occurred. Please try again.';
                        AIPS.Utilities.showToast(errorMsg, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.nextAll('.spinner').first().removeClass('is-active');
                }
            });
        },

        /**
         * Schedule all checked topics in bulk via the `aips_bulk_schedule` AJAX
         * action.
         *
         * Validates that at least one topic is selected, a template is chosen,
         * and a start date is provided before sending. Clears the topic list and
         * hides the results panel on success.
         *
         * @param {Event} e - Click event from `#btn-bulk-schedule`.
         */
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
            $btn.nextAll('.spinner').first().addClass('is-active');

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
                        // Clear list after successful scheduling
                         $('#topics-list').html('');
                         $('#planner-results').slideUp();
                         $('#planner-niche').val('');
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.nextAll('.spinner').first().removeClass('is-active');
                }
            });
        }
    });

    // Bind Planner Events
    $(document).ready(function() {
        $(document).on('click', '#btn-generate-topics', window.AIPS.generateTopics);
        $(document).on('click', '#btn-parse-manual', window.AIPS.parseManualTopics);
        $(document).on('click', '#btn-bulk-schedule', window.AIPS.bulkSchedule);
        $(document).on('click', '#btn-bulk-generate-now', window.AIPS.bulkGenerateNow);
        $(document).on('click', '#btn-clear-topics', window.AIPS.clearTopics);
        $(document).on('click', '#btn-copy-topics', window.AIPS.copySelectedTopics);
        $(document).on('keyup search', '#planner-topic-search', window.AIPS.filterTopics);
        $(document).on('change', '#check-all-topics', window.AIPS.toggleAllTopics);
        $(document).on('change', '.topic-checkbox', window.AIPS.updateSelectionCount);
        $(document).on('keyup search', '#planner-topic-search', window.AIPS.filterTopics);
        $(document).on('click', '#planner-topic-search-clear', window.AIPS.clearTopicSearch);
        $(document).on('click', '.aips-remove-topic-btn', window.AIPS.removeTopic);
    });

})(jQuery);
