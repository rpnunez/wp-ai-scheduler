<?php
// We can use $.get(location.href) to get the updated HTML, extract the table or the entire container, and replace it.
// This is the most reliable way to update the table without writing complex rendering logic in JS that duplicates PHP logic.
$file = 'ai-post-scheduler/assets/js/admin.js';
$content = file_get_contents($file);

$search = <<<SEARCH
                success: function(response) {
                    if (response.success) {
                        location.reload();
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
REPLACE;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Replaced saveSchedule success callback.\n";
