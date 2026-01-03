<?php
// Mock WP environment for minimal execution
define('ABSPATH', '/var/www/html/');
define('WP_CONTENT_DIR', '/var/www/html/wp-content');
define('OBJECT', 'OBJECT');
define('OBJECT_K', 'OBJECT_K');
define('ARRAY_A', 'ARRAY_A');
define('ARRAY_N', 'ARRAY_N');

// Mock WP core functions
function current_time($type) {
    if ($type === 'mysql') return date('Y-m-d H:i:s');
    if ($type === 'timestamp') return time();
    return time();
}
function __($t, $d) { return $t; }
function sprintf($f, ...$a) { return vsprintf($f, $a); }

// Include core classes we modified
require_once 'ai-post-scheduler/includes/class-aips-interval-calculator.php';

// Test Interval Calculator Catch-Up
echo "Testing Interval Calculator Catch-Up...\n";
$calc = new AIPS_Interval_Calculator();
$frequency = 'hourly';
// Start time 5 hours ago
$start_time = date('Y-m-d H:i:s', strtotime('-5 hours -30 minutes')); // e.g. 10:30, now is 16:00
echo "Start time: $start_time\n";
echo "Now: " . date('Y-m-d H:i:s') . "\n";

$next_run = $calc->calculate_next_run($frequency, $start_time);
echo "Next run: $next_run\n";

// Expected: 10:30 -> 11:30 -> 12:30 -> 13:30 -> 14:30 -> 15:30 -> 16:30 (Future)
// If logic works, it should be 16:30 (preserving :30 minute phase)
// If logic broken (old logic), it would be Now + 1 hour (e.g. 17:00)

$start_timestamp = strtotime($start_time);
$next_timestamp = strtotime($next_run);
$minute_start = date('i', $start_timestamp);
$minute_next = date('i', $next_timestamp);

if ($minute_start === $minute_next) {
    echo "SUCCESS: Phase preserved ($minute_start)\n";
} else {
    echo "FAILURE: Phase lost. Expected $minute_start, got $minute_next\n";
}
if ($next_timestamp > time()) {
    echo "SUCCESS: Next run is in future\n";
} else {
    echo "FAILURE: Next run is in past\n";
}

// Test AIPS_Prompt_Builder
echo "\nTesting Prompt Builder...\n";
// Mock deps
class AIPS_Template_Processor {
    public function process($t, $c) { return "Processed: $t"; }
}
class AIPS_Article_Structure_Manager {
    public function build_prompt($id, $t) { return "Structure Prompt"; }
}
require_once 'ai-post-scheduler/includes/class-aips-prompt-builder.php';

$builder = new AIPS_Prompt_Builder(new AIPS_Template_Processor(), new AIPS_Article_Structure_Manager());
$template = (object)['prompt_template' => 'Base Prompt', 'article_structure_id' => null];
$topic = "Topic";
$prompt = $builder->build_content_prompt($template, $topic);

if (strpos($prompt, 'Processed: Base Prompt') !== false) {
    echo "SUCCESS: Builder used template processor\n";
} else {
    echo "FAILURE: Builder output unexpected: $prompt\n";
}

?>
