<?php
if (!defined('ABSPATH')) {
	exit;
}

$aips_hub_subtab = isset($active_subtab_key) ? $active_subtab_key : 'settings-general';

include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
