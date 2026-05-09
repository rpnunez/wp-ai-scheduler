<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Developer Tools', 'ai-post-scheduler');
$section_description = __('Keep prompt debugging and advanced testing tools available without surfacing them to routine editorial navigation.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Advanced Tools', 'ai-post-scheduler'),
		'title'       => __('Developer Workspace', 'ai-post-scheduler'),
		'description' => __('Open the development-only tooling used for prompt inspection, test helpers, and advanced operator workflows.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Dev Tools', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('dev_tools'), 'primary' => true),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
