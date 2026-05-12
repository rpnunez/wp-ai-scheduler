<?php
if (!defined('ABSPATH')) {
	exit;
}

$structure_repo = new AIPS_Article_Structure_Repository();
$section_repo   = new AIPS_Prompt_Section_Repository();

$structures       = $structure_repo->get_all(false);
$sections         = $section_repo->get_all(false);
$aips_hub_subtab  = isset($active_subtab_key) ? $active_subtab_key : 'aips-structures';

include AIPS_PLUGIN_DIR . 'templates/admin/structures.php';
