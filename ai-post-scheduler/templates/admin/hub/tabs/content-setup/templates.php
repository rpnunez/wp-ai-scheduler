<?php
if (!defined('ABSPATH')) {
	exit;
}

$templates_handler = new AIPS_Templates();
$templates         = $templates_handler->get_all();
$categories        = get_categories(array('hide_empty' => false));
$users             = get_users(array('role__in' => array('administrator', 'editor', 'author')));
$aips_hub_mode     = true;

include AIPS_PLUGIN_DIR . 'templates/admin/templates.php';
