<?php
$file = 'ai-post-scheduler/tests/Test_AIPS_Interval_Calculator.php';
$content = file_get_contents($file);

// Handle specific edge case test without variable
$content = str_replace(
    '$next = $this->calculator->calculate_next_run(\'hourly\');',
    '$start = strtotime(AIPS_DateTime::now()->format(\'Y-m-d H:i:s\'));' . "\n" . '        $next = $this->calculator->calculate_next_run(\'hourly\');',
    $content
);

// We should run the tests to see if we missed any string dates
file_put_contents($file, $content);
