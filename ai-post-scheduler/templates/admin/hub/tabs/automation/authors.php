<?php
if (!defined('ABSPATH')) {
	exit;
}

$aips_hub_subtab = isset($active_subtab_key) ? $active_subtab_key : 'authors-list';

if ('author-topics' === $aips_hub_subtab) {
	include AIPS_PLUGIN_DIR . 'templates/admin/author-topics.php';
	return;
}

include AIPS_PLUGIN_DIR . 'templates/admin/authors.php';
