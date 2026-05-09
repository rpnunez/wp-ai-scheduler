<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Templates', 'ai-post-scheduler');
$section_description = __('Define the reusable instructions and defaults that guide article generation from first draft to publishing behavior.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Core Manager', 'ai-post-scheduler'),
		'title'       => __('Template Library', 'ai-post-scheduler'),
		'description' => __('Create, edit, clone, and activate the templates that drive your day-to-day post generation workflows.', 'ai-post-scheduler'),
		'items'       => array(
			__('Prompt templates and publishing settings', 'ai-post-scheduler'),
			__('Voice, structure, and source-group selection', 'ai-post-scheduler'),
			__('Testing and quick preview workflows', 'ai-post-scheduler'),
		),
		'actions'     => array(
			array('label' => __('Open Templates', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('templates'), 'primary' => true),
		),
	),
	array(
		'eyebrow'     => __('Related Setup', 'ai-post-scheduler'),
		'title'       => __('Pair Templates With Supporting Blocks', 'ai-post-scheduler'),
		'description' => __('Move directly into the adjacent tools you usually adjust while refining template behavior.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Voices', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('voices')),
			array('label' => __('Open Structures', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('structures')),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
