<?php
if (!defined('ABSPATH')) {
	exit;
}

$voices_handler = new AIPS_Voices();
$voices         = $voices_handler->get_all();
$aips_hub_mode  = true;

include AIPS_PLUGIN_DIR . 'templates/admin/voices.php';
