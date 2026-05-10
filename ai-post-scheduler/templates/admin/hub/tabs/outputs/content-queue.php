<?php
if (!defined('ABSPATH')) {
	exit;
}

$controller = new AIPS_Generated_Posts_Controller();
$controller->render_page(array(
	'initial_tab' => isset($active_subtab_key) ? $active_subtab_key : 'aips-generated-posts',
));
