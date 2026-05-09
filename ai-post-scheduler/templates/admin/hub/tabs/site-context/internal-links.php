<?php
if (!defined('ABSPATH')) {
	exit;
}

global $aips_internal_links_controller;

if ($aips_internal_links_controller instanceof AIPS_Internal_Links_Controller) {
	$aips_internal_links_controller->render_page();
	return;
}

echo '<div class="notice notice-error"><p>' .
	esc_html__('The Internal Links controller is not available, so the Internal Links page could not be loaded.', 'ai-post-scheduler') .
'</p></div>';
