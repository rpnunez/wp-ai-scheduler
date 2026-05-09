<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('History', 'ai-post-scheduler');
$section_description = __('Use the session and lifecycle history when you need to debug a run, trace prompt assembly, or audit what happened over time.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Diagnostics', 'ai-post-scheduler'),
		'title'       => __('Generation History', 'ai-post-scheduler'),
		'description' => __('Inspect the structured history log for run status, session details, timestamps, and AI activity.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open History', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('history'), 'primary' => true),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
