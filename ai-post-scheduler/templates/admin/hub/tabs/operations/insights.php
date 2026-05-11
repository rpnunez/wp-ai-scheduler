<?php
if (!defined('ABSPATH')) {
	exit;
}

$operations_insights = new AIPS_Operations_Insights_Controller();
$operations_insights->render_page();
