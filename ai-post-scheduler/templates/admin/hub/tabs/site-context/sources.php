<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Sources', 'ai-post-scheduler');
$section_description = __('Manage the URLs and source groups that can be injected into prompts as reference material.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Reference Inputs', 'ai-post-scheduler'),
		'title'       => __('Source Library', 'ai-post-scheduler'),
		'description' => __('Add, group, activate, and review the sources that feed source-aware topic and content generation.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Sources', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('sources'), 'primary' => true),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
