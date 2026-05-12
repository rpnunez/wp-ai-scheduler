<?php
if (!defined('ABSPATH')) {
	exit;
}

$wizard    = new AIPS_Onboarding_Wizard();
$view_data = $wizard->get_view_data();

extract($view_data, EXTR_SKIP);

include AIPS_PLUGIN_DIR . 'templates/admin/onboarding.php';
