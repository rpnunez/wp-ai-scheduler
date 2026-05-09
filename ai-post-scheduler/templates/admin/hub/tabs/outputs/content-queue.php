<?php
if (!defined('ABSPATH')) {
	exit;
}

$controller = new AIPS_Generated_Posts_Controller();
$controller->render_page(array('initial_tab' => 'aips-generated-posts'));
