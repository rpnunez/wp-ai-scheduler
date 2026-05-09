<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Voices', 'ai-post-scheduler');
$section_description = __('Control tone, title patterns, and content instructions for the writing styles your templates can apply.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Writing Style', 'ai-post-scheduler'),
		'title'       => __('Voice Presets', 'ai-post-scheduler'),
		'description' => __('Maintain named writing styles for different brands, formats, or editorial intents.', 'ai-post-scheduler'),
		'items'       => array(
			__('Title prompt guidance', 'ai-post-scheduler'),
			__('Body-content instruction sets', 'ai-post-scheduler'),
			__('Reusable tone presets for templates and authors', 'ai-post-scheduler'),
		),
		'actions'     => array(
			array('label' => __('Open Voices', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('voices'), 'primary' => true),
		),
	),
	array(
		'eyebrow'     => __('Workflow Tip', 'ai-post-scheduler'),
		'title'       => __('Tune Voice and Template Together', 'ai-post-scheduler'),
		'description' => __('Voices work best when you check the downstream templates that consume them and confirm the prompts still fit.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Review Templates', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('templates')),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
