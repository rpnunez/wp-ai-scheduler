<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Taxonomy', 'ai-post-scheduler');
$section_description = __('Curate taxonomy terms and supporting labels used to classify, enrich, or constrain generated content.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Classification', 'ai-post-scheduler'),
		'title'       => __('Taxonomy Manager', 'ai-post-scheduler'),
		'description' => __('Maintain the taxonomy options the plugin can rely on when generating or organizing posts.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Taxonomy', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('taxonomy'), 'primary' => true),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
