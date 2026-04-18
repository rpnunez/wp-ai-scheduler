<?php

$files = [
    'ai-post-scheduler/includes/class-aips-telemetry-controller.php',
    'ai-post-scheduler/includes/class-aips-internal-links-controller.php',
    'ai-post-scheduler/includes/class-aips-system-status-controller.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;

    $content = file_get_contents($file);

    // Pattern to match check_ajax_referer calls with 2 arguments and properly replace them
    // It captures spacing and arguments, then adds ', false' and the if statement.
    // The previous regex logic might be too complex, let's just do it directly.

    $lines = explode("\n", $content);
    $new_lines = [];

    foreach ($lines as $line) {
        if (preg_match('/^\s*check_ajax_referer\(([^,]+),\s*([^)]+)\);/s', $line, $matches)) {
            $indent = '';
            if (preg_match('/^(\s+)/', $line, $indent_matches)) {
                $indent = $indent_matches[1];
            }

            $action = $matches[1];
            $nonce_key = $matches[2];

            // Check if it's already using AIPS_Ajax_Response::invalid_request or wp_send_json_error
            // If it's internal links controller, we should use wp_send_json_error.
            // If it's telemetry or system status, we should use AIPS_Ajax_Response::invalid_request().

            if (strpos($file, 'internal-links') !== false) {
                $new_lines[] = $indent . "if (!check_ajax_referer({$action}, {$nonce_key}, false)) {";
                $new_lines[] = $indent . "\twp_send_json_error(array('message' => __('Invalid nonce.', 'ai-post-scheduler')), 403);";
                $new_lines[] = $indent . "}";
            } else {
                $new_lines[] = $indent . "if (!check_ajax_referer({$action}, {$nonce_key}, false)) {";
                $new_lines[] = $indent . "\tAIPS_Ajax_Response::invalid_request(__('Invalid nonce.', 'ai-post-scheduler'));";
                $new_lines[] = $indent . "}";
            }

        } else {
            $new_lines[] = $line;
        }
    }

    file_put_contents($file, implode("\n", $new_lines));
    echo "Updated $file\n";
}
