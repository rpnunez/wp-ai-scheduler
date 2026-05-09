<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Research', 'ai-post-scheduler');
$section_description = __('Generate and curate topical research before it feeds the schedule or author workflows.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Idea Intake', 'ai-post-scheduler'),
		'title'       => __('Research Workspace', 'ai-post-scheduler'),
		'description' => __('Capture trending topics, source-backed ideas, and planning candidates before turning them into scheduled output.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Research', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('research'), 'primary' => true),
			array('label' => __('Open Schedule', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('schedule')),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
