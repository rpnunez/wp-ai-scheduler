<?php
if (!defined('ABSPATH')) {
	exit;
}

$section_title       = __('Authors', 'ai-post-scheduler');
$section_description = __('Manage author-specific niches, generation settings, and topic queues from one editorial workflow branch.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Author Setup', 'ai-post-scheduler'),
		'title'       => __('Author Profiles', 'ai-post-scheduler'),
		'description' => __('Maintain author records, topical niches, and generator preferences used by the automated author flows.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Authors', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('authors'), 'primary' => true),
		),
	),
	array(
		'eyebrow'     => __('Review Queue', 'ai-post-scheduler'),
		'title'       => __('Author Topics', 'ai-post-scheduler'),
		'description' => __('Jump into a specific author topic queue to approve, reject, edit, or generate posts from author-generated ideas.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Author Topics', 'ai-post-scheduler'), 'href' => AIPS_Admin_Menu_Helper::get_page_url('author_topics')),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
