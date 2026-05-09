<?php
if (!defined('ABSPATH')) {
	exit;
}

$content_page        = AIPS_Admin_Menu_Helper::get_page_url('generated_posts');
$section_title       = __('Review Pipeline', 'ai-post-scheduler');
$section_description = __('Keep draft review, incomplete generations, and remediation work grouped together instead of buried behind separate pages.', 'ai-post-scheduler');
$cards               = array(
	array(
		'eyebrow'     => __('Editorial Review', 'ai-post-scheduler'),
		'title'       => __('Pending Review Queue', 'ai-post-scheduler'),
		'description' => __('Move directly to the draft-review tab when editors need to approve, publish, or clean up generated drafts.', 'ai-post-scheduler'),
		'actions'     => array(
			array('label' => __('Open Pending Review', 'ai-post-scheduler'), 'href' => $content_page . '#aips-pending-review', 'primary' => true),
			array('label' => __('Open Partial Generations', 'ai-post-scheduler'), 'href' => $content_page . '#aips-partial-generations'),
		),
	),
);

include AIPS_PLUGIN_DIR . 'templates/admin/hub/components/card-grid.php';
