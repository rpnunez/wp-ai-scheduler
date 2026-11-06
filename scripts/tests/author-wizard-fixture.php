<?php
// Render only the real Author modal, without booting WordPress or a database.
if (PHP_SAPI !== 'cli') { exit; }
function esc_html_e($value, $domain) { echo htmlspecialchars($value, ENT_QUOTES); }
function esc_attr_e($value, $domain) { esc_html_e($value, $domain); }
function esc_html__($value, $domain) { return htmlspecialchars($value, ENT_QUOTES); }
function __($value, $domain) { return $value; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function admin_url($value) { return $value; }
function wp_kses($value, $allowed) { return $value; }
function get_terms($args) { return array((object) array('term_id' => 7, 'name' => 'Reference Group')); }
function is_wp_error($value) { return false; }
class AIPS_Admin_Menu_Helper { public static function get_page_url($page) { return $page; } }
class AIPS_Author_Topic_Approval_Policy { public static function describe_global() { return 'Manual review'; } }
$article_structures = array();
$source = file_get_contents(__DIR__ . '/../../ai-post-scheduler/templates/admin/authors.php');
$start = strpos($source, '<!-- Author Edit/Create Modal -->');
$end = strpos($source, '<!--', $start + strlen('<!-- Author Edit/Create Modal -->'));
echo '<button class="aips-add-author-btn">Add</button><button class="aips-edit-author" data-id="9">Edit</button>';
eval('?>' . substr($source, $start, $end - $start));
