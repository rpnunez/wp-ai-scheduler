<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Schedule', 'ai-post-scheduler');
$section_description = __('Control the recurring and one-off automation rules that decide when templates, authors, and generators run.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Primary Planner', 'ai-post-scheduler'),
		'title'       => __('Unified Schedule Manager', 'ai-post-scheduler'),
		'description' => __('Review scheduled runs, bulk actions, run-now commands, and history for the automation rules already in place.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Schedule', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('schedule'), 'primary' => true),
			array('label' => __('Open Calendar', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('schedule_calendar')),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
