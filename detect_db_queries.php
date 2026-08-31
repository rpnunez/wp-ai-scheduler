<?php
$files = glob("ai-post-scheduler/includes/*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, '$wpdb->') !== false) {
        $lines = explode("\n", $content);
        $in_loop = 0;
        foreach ($lines as $num => $line) {
            if (preg_match('/\b(foreach|while|for)\b\s*\(/', $line)) {
                $in_loop++;
            }
            if (preg_match('/^\s*\}\s*$/', $line) && $in_loop > 0) {
                $in_loop--;
            }
            if ($in_loop > 0 && preg_match('/\$wpdb->(get_results|get_row|get_var|get_col|query|insert|update|delete)/', $line)) {
                echo "Loop DB query found in $file on line " . ($num + 1) . "\n";
                echo "Line: " . trim($line) . "\n\n";
            }
        }
    }
}
