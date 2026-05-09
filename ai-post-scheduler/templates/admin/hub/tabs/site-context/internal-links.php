<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Internal Links', 'ai-post-scheduler');
$section_description = __('Keep link suggestions and internal-linking context close to the rest of the site knowledge the generators rely on.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Link Graph', 'ai-post-scheduler'),
		'title'       => __('Internal Link Suggestions', 'ai-post-scheduler'),
		'description' => __('Review the link inventory and supporting data that can improve generated post navigation and contextual linking.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Internal Links', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('internal_links'), 'primary' => true),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
