<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Utilities', 'ai-post-scheduler');
$section_description = __('Keep infrequent administrative tasks grouped together instead of scattering them through the main workflow navigation.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Seed Data', 'ai-post-scheduler'),
		'title'       => __('Seeder', 'ai-post-scheduler'),
		'description' => __('Populate default data or restore baseline configuration items used for testing and setup.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Seeder', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('seeder'), 'primary' => true),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
