<?php
$files = glob("ai-post-scheduler/includes/*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'get_option') !== false) {
        // extract all get_option calls with context
        preg_match_all('/(?:for|foreach|while).*?\{.*?get_option/s', $content, $matches);
        if(!empty($matches[0])) {
             foreach($matches[0] as $match) {
                 if(strpos($match, 'get_option') !== false) {
                     // Check if get_option is inside the loop block. It's a bit naive.
                     $open = substr_count($match, '{');
                     $close = substr_count($match, '}');
                     if ($open > $close) {
                         echo "Likely loop get_option found in $file\n";
                     }
                 }
             }
        }
    }
}
