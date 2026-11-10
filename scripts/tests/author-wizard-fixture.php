<?php
// Render only the real Author modal, without booting WordPress or a database.
if (PHP_SAPI !== 'cli') { exit; }
if (!defined('ABSPATH')) { define('ABSPATH', true); }
if (!defined('AIPS_PLUGIN_DIR')) {
	define('AIPS_PLUGIN_DIR', dirname(__DIR__, 2) . '/ai-post-scheduler/');
}
function esc_html_e($value, $domain) { echo htmlspecialchars($value, ENT_QUOTES); }
function esc_attr_e($value, $domain) { esc_html_e($value, $domain); }
function esc_html__($value, $domain) { return htmlspecialchars($value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function __($value, $domain) { return $value; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url($value) { return esc_attr($value); }
function esc_textarea($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function admin_url($value) { return $value; }
function wp_kses($value, $allowed) { return $value; }
function get_terms($args) { return array((object) array('term_id' => 7, 'name' => 'Reference Group')); }
function is_wp_error($value) { return false; }
function selected($selected, $current = true, $echo = true) {
	$out = ((string) $selected === (string) $current) ? ' selected="selected"' : '';
	if ($echo) { echo $out; }
	return $out;
}
function _n($single, $plural, $number, $domain = 'default') { return ((int) $number === 1) ? $single : $plural; }
class AIPS_Admin_Menu_Helper { public static function get_page_url($page) { return $page; } }
class AIPS_Author_Topic_Approval_Policy { public static function describe_global() { return 'Manual review'; } }
class AIPS_Config {
	public static function get_instance() { return new self(); }
	public function get_max_post_images_limit() { return 5; }
	public function get_allowed_image_sources() { return array('ai_prompt', 'unsplash', 'media_library'); }
	public function get_option($key) { return ''; }
}
$article_structures = array();
$source = file_get_contents(AIPS_PLUGIN_DIR . 'templates/admin/authors.php');
$start = strpos($source, '<!-- Author Edit/Create Modal -->');
$end = strpos($source, '<!-- Author Suggestions Modal -->');
echo '<button class="aips-add-author-btn">Add</button><button class="aips-edit-author" data-id="9">Edit</button>';
eval('?>' . substr($source, $start, $end - $start));
