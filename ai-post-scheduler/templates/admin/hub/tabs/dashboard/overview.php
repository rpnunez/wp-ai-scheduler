<?php
if (!defined('ABSPATH')) {
	exit;
}

$controller = new AIPS_Dashboard_Controller();
$view_data  = $controller->get_view_data();

extract($view_data, EXTR_SKIP);

include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
