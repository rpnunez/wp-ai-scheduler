<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Article Structures', 'ai-post-scheduler');
$section_description = __('Shape how long-form output is organized by combining reusable prompt sections into article blueprints.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Composition', 'ai-post-scheduler'),
		'title'       => __('Structure Manager', 'ai-post-scheduler'),
		'description' => __('Build and revise the section order, framing, and prompt templates used to compose full articles.', 'ai-post-scheduler'),
		'items'       => array(
			__('Section ordering and prompt composition', 'ai-post-scheduler'),
			__('Draft and active structure variants', 'ai-post-scheduler'),
			__('Template-ready structure assignments', 'ai-post-scheduler'),
		),
		'actions'     => array(
			array('label' => __('Open Structures', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('structures'), 'primary' => true),
			array('label' => __('Open Prompt Blocks', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('prompt_sections')),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
