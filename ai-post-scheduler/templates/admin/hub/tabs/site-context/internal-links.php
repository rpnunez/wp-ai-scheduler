<?php
if (!defined('ABSPATH')) {
	exit;
}

global $aips_internal_links_controller;

if ($aips_internal_links_controller instanceof AIPS_Internal_Links_Controller) {
	$summary         = $aips_internal_links_controller->get_service()->get_dashboard_summary();
	$links_repo      = $aips_internal_links_controller->get_links_repo();
	$service         = $aips_internal_links_controller->get_service();
$aips_hub_subtab = isset($active_subtab_key) ? $active_subtab_key : 'suggestions';

	include AIPS_PLUGIN_DIR . 'templates/admin/internal-links.php';
	return;
}

echo '<div class="notice notice-error"><p>' .
	esc_html__('The Internal Links controller is not available, so the Internal Links page could not be loaded.', 'ai-post-scheduler') .
'</p></div>';
