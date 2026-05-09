<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Calendar', 'ai-post-scheduler');
$section_description = __('See upcoming scheduled output on a calendar before changing the underlying automation rules.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Time View', 'ai-post-scheduler'),
		'title'       => __('Calendar Workspace', 'ai-post-scheduler'),
		'description' => __('Use the calendar when you need a date-based view of upcoming posts and schedule density instead of rule-based configuration.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Calendar', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('schedule_calendar'), 'primary' => true),
			array('label' => __('Open Schedule Rules', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('schedule')),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
