<?php
// Mock WP dependencies
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return false; }
}
if (!function_exists('__')) {
    function __($text, $domain) { return $text; }
}
if (!class_exists('WP_Error')) {
    class WP_Error {}
}

// Mock repositories and processor
class AIPS_Article_Structure_Repository {
    public function get_by_id($id) {
        $sections = array();
        for ($i = 0; $i < 100; $i++) {
            $sections[] = "section_$i";
        }
        $structure_data = json_encode(array(
            'sections' => $sections,
            'prompt_template' => str_repeat("{{section:section_0}} ", 10) . implode(" ", array_map(function($s) { return "{{section:$s}}"; }, $sections))
        ));

        return (object) array(
            'id' => $id,
            'name' => 'Test',
            'description' => 'Test',
            'structure_data' => $structure_data,
            'is_active' => 1,
            'is_default' => 0
        );
    }
}

class AIPS_Prompt_Section_Repository {
    public function get_by_keys($keys) {
        $sections = array();
        foreach ($keys as $key) {
            $sections[$key] = (object) array('content' => "Content for $key");
        }
        return $sections;
    }
}

class AIPS_Template_Processor {
    public function process($template, $topic = null) {
        return $template;
    }
}

require_once __DIR__ . '/../ai-post-scheduler/includes/class-aips-article-structure-manager.php';

// Verification Script
$manager = new AIPS_Article_Structure_Manager();

echo "Starting benchmark...\n";

$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $manager->build_prompt(1, 'Topic');
}
$end = microtime(true);

echo "Time taken: " . ($end - $start) . " seconds\n";
