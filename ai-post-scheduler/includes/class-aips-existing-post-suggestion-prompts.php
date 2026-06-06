<?php
/**
 * Prompt builders for Existing Post suggestions.
 *
 * @package AI_Post_Scheduler
 * @since 2.10.0
 */

if (!defined('ABSPATH')) {
exit;
}

class AIPS_Existing_Post_Suggestion_Prompts {

public function build_post_scan_prompt($post, $categories = array()) {
$category_names = array();
foreach ((array) $categories as $category) {
if ($category instanceof WP_Term) {
$category_names[] = $category->name;
}
}

$payload = array(
'post' => array(
'id' => (int) $post->ID,
'title' => (string) $post->post_title,
'excerpt' => (string) $post->post_excerpt,
'content' => wp_strip_all_tags((string) $post->post_content),
'categories' => $category_names,
),
'instructions' => array(
'Analyze readability and typos',
'Suggest title rewrite and excerpt rewrite if meaningful',
'Suggest content expansion or shortening improvements',
'Suggest category updates when category fit is poor',
'Flag factual staleness concerns and suggest citation sources',
),
'output_schema' => array(
'suggestions' => array(
array(
'component' => 'title|excerpt|content|categories|factuality|sources',
'item_type' => 'rewrite|expand|shorten|fix_typo|recommendation|freshness_check|citation_suggestion',
'suggested_value' => 'string|array',
'rationale' => 'string',
'confidence' => 0.0,
'priority' => 'low|medium|high',
'severity' => 'low|medium|high',
),
),
),
);

return "You are an expert content editor for existing blog posts.\nRespond with strict JSON only.\n" . wp_json_encode($payload);
}
}
