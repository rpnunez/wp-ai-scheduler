<?php
$files = glob("ai-post-scheduler/includes/*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'get_post(') !== false) {
        $lines = explode("\n", $content);
        $in_loop = 0;
        foreach ($lines as $num => $line) {
            if (preg_match('/\b(foreach|while|for)\b\s*\(/', $line)) {
                $in_loop++;
            }
            if (preg_match('/^\s*\}\s*$/', $line) && $in_loop > 0) {
                $in_loop--;
            }
            // rudimentary check for end of block, doesn't account for nested blocks well but let's try.
            if ($in_loop > 0 && strpos($line, 'get_post(') !== false) {
                echo "Loop get_post found in $file on line " . ($num + 1) . "\n";
            }
        }
    }
}
