<?php
if (!defined('ABSPATH')) {
	exit;
}

$post_slices_repo  = AIPS_Post_Slices_Repository::instance();
$post_slices       = $post_slices_repo->get_all(false);
$post_slice_counts = $post_slices_repo->get_counts();

include AIPS_PLUGIN_DIR . 'templates/admin/post-slices.php';
