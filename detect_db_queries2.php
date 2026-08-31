<?php
$files = glob("ai-post-scheduler/includes/*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, '$wpdb->') !== false) {
        // Find $wpdb-> queries within a loop by simplistic analysis
        preg_match_all('/(?:for|foreach|while).*?\{.*?\$wpdb->/s', $content, $matches);
        if(!empty($matches[0])) {
             foreach($matches[0] as $match) {
                 if(strpos($match, '$wpdb->') !== false) {
                     // Check if it's inside the loop block
                     $open = substr_count($match, '{');
                     $close = substr_count($match, '}');
                     if ($open > $close) {
                         echo "Likely loop DB query found in $file\n";
                         // we can check the line
                         $lines = explode("\n", $match);
                         foreach($lines as $line) {
                             if(strpos($line, '$wpdb->') !== false) {
                                 echo "Line: " . trim($line) . "\n";
                             }
                         }
                         echo "----------\n";
                     }
                 }
             }
        }
    }
}
