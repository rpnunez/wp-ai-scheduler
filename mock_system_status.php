<?php
// Mock WordPress functions
define('ABSPATH', true);

function esc_html_e($text, $domain) { echo $text; }
function esc_html($text) { return htmlspecialchars($text); }
function esc_attr($text) { return htmlspecialchars($text); }
function esc_textarea($text) { return htmlspecialchars($text); }
function __($text, $domain) { return $text; }

// Mock data
$system_info = [
    'environment' => [
        'php_version' => [
            'label' => 'PHP Version',
            'value' => '8.0.0',
            'status' => 'ok',
            'details' => ['PHP Version: 8.0.0', 'Memory Limit: 256M', 'Max Execution Time: 300']
        ]
    ]
];

// Include template
include 'ai-post-scheduler/templates/admin/system-status.php';
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    var aipsAjax = { ajaxUrl: '', nonce: '' };
</script>
<script src="ai-post-scheduler/assets/js/admin.js"></script>
