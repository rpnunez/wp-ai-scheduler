<?php
if (!defined('ABSPATH')) {
	exit;
}

$voices_handler = new AIPS_Voices();
$voices         = $voices_handler->get_all();
include AIPS_PLUGIN_DIR . 'templates/admin/voices.php';
