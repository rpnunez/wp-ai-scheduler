<?php
$files = glob("ai-post-scheduler/includes/*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'get_option') !== false) {
        $lines = explode("\n", $content);
        $in_loop = 0;
        foreach ($lines as $num => $line) {
            if (preg_match('/\b(foreach|while|for)\b\s*\(/', $line)) {
                $in_loop++;
            }
            if (preg_match('/^\s*\}\s*$/', $line) && $in_loop > 0) {
                $in_loop--;
            }
            if ($in_loop > 0 && strpos($line, 'get_option') !== false) {
                echo "Loop get_option found in $file on line " . ($num + 1) . "\n";
            }
        }
    }
}
