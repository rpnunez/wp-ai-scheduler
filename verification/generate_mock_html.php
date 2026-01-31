<?php
// Mock WordPress functions
function esc_html__($text, $domain) { return $text; }
function esc_attr__($text, $domain) { return $text; }
function esc_attr_e($text, $domain) { echo $text; }
function esc_html($text) { return $text; }
function esc_attr($text) { return $text; }
function _e($text, $domain) { echo $text; }
function __($text, $domain) { return $text; }

// Mock AIPS_Template_Helper
class AIPS_Template_Helper {
    public static function render_frequency_dropdown($field_id, $field_name, $selected, $label_text, $allowed = array()) {
        echo '<label for="' . $field_id . '">' . $label_text . '</label>';
        echo '<select id="' . $field_id . '"><option>Daily</option></select>';
    }
}

// Mock data
$templates = [
    (object)['id' => 1, 'name' => 'Test Template']
];
$default_planner_frequency = 'daily';

// Capture output
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Planner Mock</title>
    <style>
        /* Minimal WP Admin Styles */
        body { font-family: sans-serif; background: #f1f1f1; padding: 20px; }
        .aips-card { background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 800px; margin: 0 auto; }
        .aips-form-row { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], textarea { width: 100%; padding: 5px; }
        .aips-row { display: flex; gap: 20px; }
        .aips-col { flex: 1; }
    </style>
</head>
<body>
<?php
include 'ai-post-scheduler/templates/admin/planner.php';
?>
</body>
</html>
<?php
$html = ob_get_clean();
file_put_contents('verification/mock_planner.html', $html);
echo "Mock HTML generated at verification/mock_planner.html\n";
