<?php
$file = 'ai-post-scheduler/assets/js/admin.js';
$content = file_get_contents($file);

// Make sure the empty state logic properly catches it
// Empty state HTML has `<div class="dashicons dashicons-calendar-alt aips-empty-state-icon"` inside `<div class="aips-empty-state">` inside `<div class="aips-panel-body">` inside `<div class="aips-content-panel">`.
// My query `$('.aips-empty-state').has('.dashicons-calendar-alt').closest('.aips-content-panel')` might be fragile.
// Actually, since there's only one main panel, we could just replace the whole `.aips-content-panel` that contains `.aips-empty-state` or `.aips-schedule-table`.
// A safer way is to replace the wrapper. Let's wrap the logic in a safer way.
$search = "var \$emptyStatePanel = \$('.aips-empty-state').has('.dashicons-calendar-alt').closest('.aips-content-panel');";
$replace = "var \$emptyStatePanel = \$('.aips-content-panel').has('.aips-empty-state').last();";
$content = str_replace($search, $replace, $content);

file_put_contents($file, $content);
echo "Replaced empty state logic.\n";
