<?php
/**
 * Test Custom Intervals (Standalone)
 *
 * Mocks the required environment to test AIPS_Interval_Calculator logic
 * without a full WordPress stack.
 */

// Mock WordPress functions
if (!function_exists('defined')) {
    function defined($const) { return true; }
}
if (!function_exists('__')) {
    function __($text, $domain) { return $text; }
}
if (!function_exists('current_time')) {
    function current_time($type) {
        return ($type === 'mysql') ? date('Y-m-d H:i:s') : time();
    }
}
if (!function_exists('date_i18n')) {
    function date_i18n($fmt, $ts) { return date($fmt, $ts); }
}

// Load Class
require_once 'ai-post-scheduler/includes/class-aips-interval-calculator.php';

$calc = new AIPS_Interval_Calculator();

// --- Test 1: Daily at 9am ---
$now = strtotime('2024-01-01 08:00:00'); // Monday
$rules = ['times' => ['09:00']];
$next = $calc->calculate_next_run('custom', date('Y-m-d H:i:s', $now), $rules);
$expected = '2024-01-01 09:00:00';

echo "Test 1 (Today 9am): " . ($next === $expected ? "PASS" : "FAIL (Got $next)") . "\n";

// --- Test 2: Daily at 9am (Past 9am) ---
$now = strtotime('2024-01-01 10:00:00');
$next = $calc->calculate_next_run('custom', date('Y-m-d H:i:s', $now), $rules);
$expected = '2024-01-02 09:00:00';

echo "Test 2 (Tomorrow 9am): " . ($next === $expected ? "PASS" : "FAIL (Got $next)") . "\n";

// --- Test 3: MWF at 10am ---
$now = strtotime('2024-01-01 12:00:00'); // Monday (1)
$rules = [
    'times' => ['10:00'],
    'days_of_week' => [1, 3, 5] // Mon, Wed, Fri
];
// Next should be Wednesday (3) at 10am
$next = $calc->calculate_next_run('custom', date('Y-m-d H:i:s', $now), $rules);
$expected = '2024-01-03 10:00:00';

echo "Test 3 (Next Wed 10am): " . ($next === $expected ? "PASS" : "FAIL (Got $next)") . "\n";

// --- Test 4: Twice a Day (9am, 5pm) ---
$now = strtotime('2024-01-01 10:00:00'); // Mon 10am
$rules = [
    'times' => ['09:00', '17:00']
];
// Next should be today 17:00
$next = $calc->calculate_next_run('custom', date('Y-m-d H:i:s', $now), $rules);
$expected = '2024-01-01 17:00:00';

echo "Test 4 (Today 5pm): " . ($next === $expected ? "PASS" : "FAIL (Got $next)") . "\n";
