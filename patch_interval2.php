<?php
$file = 'ai-post-scheduler/tests/Test_AIPS_Interval_Calculator.php';
$content = file_get_contents($file);

// test_calculate_next_run_with_past_time: $next is already an int. We shouldn't use strtotime on it.
$content = str_replace(
    '$next_timestamp = strtotime($next);',
    '$next_timestamp = $next;',
    $content
);
$content = str_replace(
    '$current_timestamp = current_time(\'timestamp\');',
    '$current_timestamp = time();',
    $content
);

// test_calculate_next_run_invalid_defaults_to_daily: it uses $start as a string, we need to convert it.
// Actually $start is already parsed by our previous regex: $start = strtotime(date('Y-m-d 10:00:00', strtotime('+1 year')));
// Let's replace the whole test body to be clean.
$test_invalid = <<<TEST
    public function test_calculate_next_run_invalid_defaults_to_daily() {
        \$start = strtotime(date('Y-m-d 10:00:00', strtotime('+1 year')));
        \$next = \$this->calculator->calculate_next_run('invalid_frequency', \$start);

        // Should default to +1 day
        \$expected = strtotime('+1 day', \$start);
        \$this->assertEquals(\$expected, \$next);
    }
TEST;

$content = preg_replace('/public function test_calculate_next_run_invalid_defaults_to_daily\(\) \{.*?\}/s', $test_invalid, $content);

file_put_contents($file, $content);
