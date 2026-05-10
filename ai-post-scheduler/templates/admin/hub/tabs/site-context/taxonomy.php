<?php
if (!defined('ABSPATH')) {
	exit;
}

$aips_hub_subtab = isset($active_subtab_key) ? $active_subtab_key : 'categories';

include AIPS_PLUGIN_DIR . 'templates/admin/taxonomy.php';
