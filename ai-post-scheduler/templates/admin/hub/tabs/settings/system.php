<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('System Status', 'ai-post-scheduler');
$section_description = __('Keep diagnostics, environment checks, and operational health in one place for maintenance and troubleshooting.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Diagnostics', 'ai-post-scheduler'),
		'title'       => __('System Status', 'ai-post-scheduler'),
		'description' => __('Inspect connectivity, database status, cron-related health, and repair tools when the plugin is not behaving as expected.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open System Status', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('system_status'), 'primary' => true),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
