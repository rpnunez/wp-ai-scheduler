<?php
$file = 'ai-post-scheduler/assets/js/admin.js';
$content = file_get_contents($file);

// Let's refine the replacement to be safer and match the exact HTML structure
// Empty state HTML: <div class="aips-content-panel">...<div class="aips-empty-state">
// Table HTML: <div class="aips-content-panel">...<table class="aips-table aips-schedule-table">

$search = <<<SEARCH
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message || 'Schedule saved successfully', 'success');
                        $('#aips-schedule-modal').hide();

                        // Dynamically update the schedules table
                        $.get(location.href, function(html) {
                            var \$newContent = \$(html).find('.aips-schedule-table').closest('.aips-content-panel');
                            if (\$newContent.length) {
                                // If table exists, replace it
                                var \$existingPanel = $('.aips-schedule-table').closest('.aips-content-panel');
                                if (\$existingPanel.length) {
                                    \$existingPanel.replaceWith(\$newContent);
                                } else {
                                    // If table didn't exist (empty state), replace the empty state
                                    $('.aips-content-panel').replaceWith(\$newContent);
                                }
                            } else {
                                // Fallback: empty state or just reload
                                location.reload();
                            }
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
SEARCH;

$replace = <<<REPLACE
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message || 'Schedule saved successfully', 'success');
                        $('#aips-schedule-modal').hide();

                        // Dynamically update the schedules table
                        $.get(location.href, function(html) {
                            var \$newDoc = \$(html);
                            var \$newContent = \$newDoc.find('.aips-schedule-table').closest('.aips-content-panel');
                            var \$existingPanel = $('.aips-schedule-table').closest('.aips-content-panel');

                            if (\$newContent.length) {
                                if (\$existingPanel.length) {
                                    \$existingPanel.replaceWith(\$newContent);
                                } else {
                                    // If table didn't exist (we were on the empty state), replace the empty state panel
                                    // We need to find the correct panel to replace.
                                    // It's the one containing .aips-empty-state that is related to schedules.
                                    var \$emptyStatePanel = $('.aips-empty-state').has('.dashicons-calendar-alt').closest('.aips-content-panel');
                                    if (\$emptyStatePanel.length) {
                                        \$emptyStatePanel.replaceWith(\$newContent);
                                    } else {
                                        location.reload();
                                    }
                                }

                                // Re-bind any dynamic event listeners or UI initializations if needed
                                // Currently, event delegation handles most interactions in admin.js
                                AIPS.updateScheduleBulkActions();
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
REPLACE;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Replaced saveSchedule success callback.\n";
