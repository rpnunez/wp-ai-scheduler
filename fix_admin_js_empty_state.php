<?php
$file = 'ai-post-scheduler/assets/js/admin.js';
$content = file_get_contents($file);
if (strpos($content, "$('.aips-schedule-table').closest('.aips-content-panel');") !== false) {
    echo "The code has been properly replaced.\n";
} else {
    echo "Failed to replace the code.\n";
}
