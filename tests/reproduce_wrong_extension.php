<?php
/**
 * Verification Script for Image Extension Fix
 *
 * Usage: php tests/reproduce_wrong_extension.php
 */

// --- GLOBAL SPY ---
global $captured_filename;
$captured_filename = '';

// --- MOCK WORDPRESS CORE ---
if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/'); }

class WP_Error {
    public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($t) { return $t instanceof WP_Error; }

function wp_safe_remote_get($url) { return (object)[]; }
function wp_remote_retrieve_response_code($r) { return 200; }
function wp_remote_retrieve_header($r, $h) {
    if (strtolower($h) === 'content-type') return 'image/png';
    return '';
}
function wp_remote_retrieve_body($r) { return 'fake_png_data'; }
function wp_safe_remote_head($url) { return (object)[]; }
function sanitize_title($t) { return strtolower(str_replace(' ', '-', $t)); }

function wp_upload_bits($name, $d, $b) {
    global $captured_filename;
    $captured_filename = $name; // Capture the filename requested by the service
    return ['file' => '/tmp/' . $name, 'url' => 'http://ex.com/' . $name, 'error' => false];
}

function wp_check_filetype($filename) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $types = ['jpg'=>'image/jpeg', 'png'=>'image/png'];
    return ['type' => isset($types[$ext]) ? $types[$ext] : null, 'ext' => $ext];
}

function wp_insert_attachment($attachment, $file) { return 123; }
function wp_generate_attachment_metadata($i, $f) { return []; }
function wp_update_attachment_metadata($i, $d) {}
function get_option($o, $default=false) { return $default; }
function update_option($o, $v) {}

if (!class_exists('finfo')) {
    class finfo {
        public function __construct($const) {}
        public function buffer($data) { return 'image/png'; }
    }
    define('FILEINFO_MIME_TYPE', 1);
}

// --- MOCK AIPS DEPENDENCIES ---
class AIPS_Logger { public function log($m, $l, $c=[]) {} }
class AIPS_AI_Service { public function generate_image($p) { return 'url'; } }

// --- LOAD CLASS ---
require_once __DIR__ . '/../ai-post-scheduler/includes/class-aips-image-service.php';

// --- RUN TEST ---
echo "Starting verification...\n";
$service = new AIPS_Image_Service();
$service->upload_image_from_url('http://example.com/fake.png', 'My Test Image');

echo "Captured Filename: " . $captured_filename . "\n";

// VERIFY
if (strpos($captured_filename, '.png') !== false) {
    echo "PASS: Filename extension is .png as expected.\n";
    exit(0);
} else {
    echo "FAIL: Filename extension is not .png. Got: " . $captured_filename . "\n";
    exit(1);
}
