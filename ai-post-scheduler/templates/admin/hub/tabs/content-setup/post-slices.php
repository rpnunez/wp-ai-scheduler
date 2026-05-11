<?php
if (!defined('ABSPATH')) {
	exit;
}

$post_slices = new AIPS_Post_Slices_Controller();
$post_slices->render_page();
