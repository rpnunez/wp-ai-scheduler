<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_repo = new AIPS_Prompt_Section_Repository();
$sections     = $section_repo->get_all(false);
$aips_hub_mode = true;

include AIPS_PLUGIN_DIR . 'templates/admin/sections.php';
