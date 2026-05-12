<?php
if (!defined('ABSPATH')) {
	exit;
}

global $aips_operations_insights_controller;

if ($aips_operations_insights_controller instanceof AIPS_Operations_Insights_Controller) {
	$aips_operations_insights_controller->render_page();
	return;
}

echo '<div class="notice notice-error"><p>' .
	esc_html__('The Operations Insights controller is not available, so the Operations workspace could not be loaded.', 'ai-post-scheduler') .
'</p></div>';
