<?php
$content = file_get_contents('ai-post-scheduler/templates/admin/settings.php');
$content = str_replace(
    "\$tabs.removeClass('active');
                \$('[data-tab=\"general\"]').addClass('active');",
    "\$tabs.removeClass('active');
                \$tabs.filter('[data-tab=\"general\"]').addClass('active');",
    $content
);
file_put_contents('ai-post-scheduler/templates/admin/settings.php', $content);
echo "JS updated.\n";
