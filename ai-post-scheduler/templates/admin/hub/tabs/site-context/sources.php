<?php
if (!defined('ABSPATH')) {
	exit;
}

$repo    = new AIPS_Sources_Repository();
$sources = $repo->get_all(false);

$source_groups = get_terms(array(
	'taxonomy'   => 'aips_source_group',
	'hide_empty' => false,
));
if (is_wp_error($source_groups)) {
	$source_groups = array();
}

$source_group_name_map = array();
foreach ($source_groups as $group) {
	$source_group_name_map[(int) $group->term_id] = $group->name;
}

$all_source_ids           = array_map(function ($source) {
	return (int) $source->id;
}, $sources);
$source_term_ids_map      = $repo->get_term_ids_for_sources($all_source_ids);
$data_repo                = new AIPS_Sources_Data_Repository();
$source_fetch_data_map    = $data_repo->get_by_source_ids($all_source_ids);
$source_content_count_map = $data_repo->get_counts_by_source_ids($all_source_ids);

include AIPS_PLUGIN_DIR . 'templates/admin/sources.php';
