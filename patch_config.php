<?php
$file = 'ai-post-scheduler/includes/class-aips-config.php';
$content = file_get_contents($file);

// Replace hardcoded false in get_retry_config()
$content = str_replace(
    "'enabled' => false,//(bool) \$this->get_option('aips_enable_retry', true),",
    "'enabled' => (bool) \$this->get_option('aips_enable_retry', true),",
    $content
);

file_put_contents($file, $content);
echo "Patched AIPS_Config\n";
