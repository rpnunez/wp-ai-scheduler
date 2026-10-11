<?php
$file = "ai-post-scheduler/includes/class-aips-generator.php";
$content = file_get_contents($file);

$start_token = '    private function generate_post_from_context($context) {';
$end_token = '    public function record_source_snapshots_injected($snapshots, $term_ids = array(), $sources = array()) {';

$start_pos = strpos($content, $start_token);
$end_pos = strpos($content, $end_token);

$method_content = substr($content, $start_pos, $end_pos - $start_pos);
file_put_contents('method.txt', $method_content);
