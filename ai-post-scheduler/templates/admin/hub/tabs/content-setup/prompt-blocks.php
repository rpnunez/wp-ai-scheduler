<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Prompt Blocks', 'ai-post-scheduler');
$section_description = __('Manage the reusable prompt fragments that structures can insert into full article instructions.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Reusable Guidance', 'ai-post-scheduler'),
		'title'       => __('Prompt Block Library', 'ai-post-scheduler'),
		'description' => __('Keep introduction, steps, tips, resources, and other reusable fragments centralized instead of hardcoding them into every structure.', 'ai-post-scheduler'),
		'items'       => array(
			__('Shared instructional fragments', 'ai-post-scheduler'),
			__('Clean separation between blocks and structure layout', 'ai-post-scheduler'),
			__('Lower-maintenance prompt editing', 'ai-post-scheduler'),
		),
		'actions'     => array(
			array('label' => __('Open Prompt Blocks', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('prompt_sections'), 'primary' => true),
			array('label' => __('Open Structures', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('structures')),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
