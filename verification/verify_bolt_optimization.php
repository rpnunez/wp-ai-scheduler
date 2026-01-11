<?php
// Verification script for Bolt optimization

function test_build_prompt_logic() {
    $prompt_template = "Header: {{section:header}}\nBody: {{section:body}}\nFooter: {{section:footer}}";

    $section_contents = [
        'header' => 'Welcome to the post',
        'body' => 'This is the main content',
        'footer' => 'Copyright 2024'
    ];

    // Original Logic
    $prompt_original = $prompt_template;
    foreach ($section_contents as $section_key => $content) {
        $prompt_original = str_replace("{{section:$section_key}}", $content, $prompt_original);
    }

    // New Logic
    $prompt_new = $prompt_template;
    $search = array();
    $replace = array();
    foreach ($section_contents as $section_key => $content) {
        $search[] = "{{section:$section_key}}";
        $replace[] = $content;
    }
    $prompt_new = str_replace($search, $replace, $prompt_new);

    echo "Original:\n$prompt_original\n\n";
    echo "New:\n$prompt_new\n\n";

    if ($prompt_original === $prompt_new) {
        echo "SUCCESS: Outputs match.\n";
    } else {
        echo "FAILURE: Outputs do not match.\n";
    }
}

test_build_prompt_logic();
