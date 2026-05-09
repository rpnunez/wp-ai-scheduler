<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Telemetry', 'ai-post-scheduler');
$section_description = __('Review telemetry and usage instrumentation without mixing it into the day-to-day content workflow screens.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Observability', 'ai-post-scheduler'),
		'title'       => __('Telemetry Dashboard', 'ai-post-scheduler'),
		'description' => __('Inspect recorded telemetry events and metrics when you need a higher-level operational view.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Telemetry', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('telemetry'), 'primary' => true),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
