<?php
$file = 'ai-post-scheduler/tests/Test_AIPS_Interval_Calculator.php';
$content = file_get_contents($file);

// Replace $start string with strtotime
$content = preg_replace('/\$start = \'([^\']+)\';/', '$start = strtotime(\'$1\');', $content);

// Convert expected variable assignments to strtotime
$content = preg_replace('/\$expected = \'([^\']+)\';/', '$expected = strtotime(\'$1\');', $content);

// For test_calculate_next_run_without_start_time
$content = str_replace(
    "\$this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', \$next);",
    "\$this->assertIsInt(\$next);",
    $content
);
$content = str_replace(
    "\$this->assertGreaterThan(current_time('mysql'), \$next);",
    "\$this->assertGreaterThan(time(), \$next);",
    $content
);

file_put_contents($file, $content);
