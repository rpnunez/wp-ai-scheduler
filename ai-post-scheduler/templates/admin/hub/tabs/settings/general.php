<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('General Settings', 'ai-post-scheduler');
$section_description = __('Control the plugin configuration that affects runtime behavior, integrations, and editorial defaults.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Configuration', 'ai-post-scheduler'),
		'title'       => __('Plugin Settings', 'ai-post-scheduler'),
		'description' => __('Adjust the core plugin options and connected behavior without leaving the new settings workspace.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Settings', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('settings'), 'primary' => true),
		),
	),
	array(
		'eyebrow'     => __('Onboarding', 'ai-post-scheduler'),
		'title'       => __('Guided Setup', 'ai-post-scheduler'),
		'description' => __('Use the onboarding flow when you need to revisit the initial configuration path or validate the setup sequence.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Onboarding', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('onboarding')),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
