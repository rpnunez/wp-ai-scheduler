<?php
if (!defined('ABSPATH')) {
	exit;
}

$structure_repo = new AIPS_Article_Structure_Repository();
$section_repo   = new AIPS_Prompt_Section_Repository();

$structures = $structure_repo->get_all(false);
$sections   = $section_repo->get_all(false);

include AIPS_PLUGIN_DIR . 'templates/admin/structures.php';
