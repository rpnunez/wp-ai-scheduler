<?php
if (!defined('ABSPATH')) {
	exit;
}

$aips_hub_subtab = isset($active_subtab_key) ? $active_subtab_key : 'trending';

include AIPS_PLUGIN_DIR . 'templates/admin/research.php';
