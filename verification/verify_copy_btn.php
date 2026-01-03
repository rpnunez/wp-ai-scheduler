<?php
// Mock WordPress environment functions
function esc_html_e($text, $domain) { echo $text; }
function esc_html($text) { echo htmlspecialchars($text); }
function esc_attr_e($text, $domain) { echo htmlspecialchars($text); }
function date_i18n($format, $timestamp) { return date($format, $timestamp); }
function get_option($option) { return $option === 'date_format' ? 'F j, Y' : 'g:i a'; }
function wp_next_scheduled($hook) { return time() + 3600; }
function settings_fields($group) {}
function do_settings_sections($page) {}
function submit_button() {}
function date($format) { return 'May 23, 2024'; }
function current_time($format) { return '12:00'; }
function get_bloginfo($show) { return $show === 'name' ? 'My Site' : 'Just another site'; }
function rand($min, $max) { return 42; }

// Define constants
define('ABSPATH', true);

// Include the template file
include 'ai-post-scheduler/templates/admin/settings.php';
