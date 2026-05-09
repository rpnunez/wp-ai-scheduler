<?php
if (!defined('ABSPATH')) {
	exit;
}

$status_handler = new AIPS_System_Status();
$status_handler->render_page();
