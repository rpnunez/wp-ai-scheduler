<?php
if (!defined('ABSPATH')) {
	exit;
}

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();

// Create article_structures table
$table_structures = $wpdb->prefix . 'aips_article_structures';
$sql_structures = "CREATE TABLE $table_structures (
	id bigint(20) NOT NULL AUTO_INCREMENT,
	name varchar(255) NOT NULL,
	description text,
	structure_data longtext NOT NULL,
	is_active tinyint(1) DEFAULT 1,
	is_default tinyint(1) DEFAULT 0,
	created_at datetime DEFAULT CURRENT_TIMESTAMP,
	updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY is_active (is_active),
	KEY is_default (is_default)
) $charset_collate;";

// Create prompt_sections table for modular composition
$table_sections = $wpdb->prefix . 'aips_prompt_sections';
$sql_sections = "CREATE TABLE $table_sections (
	id bigint(20) NOT NULL AUTO_INCREMENT,
	name varchar(255) NOT NULL,
	description text,
	section_key varchar(100) NOT NULL,
	content text NOT NULL,
	is_active tinyint(1) DEFAULT 1,
	created_at datetime DEFAULT CURRENT_TIMESTAMP,
	updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	UNIQUE KEY section_key (section_key),
	KEY is_active (is_active)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql_structures);
dbDelta($sql_sections);

// Add article_structure_id to schedule table
$table_schedule = $wpdb->prefix . 'aips_schedule';
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_schedule LIKE 'article_structure_id'");
if (empty($column_exists)) {
	$wpdb->query("ALTER TABLE $table_schedule ADD COLUMN article_structure_id bigint(20) DEFAULT NULL AFTER template_id");
	$wpdb->query("ALTER TABLE $table_schedule ADD KEY article_structure_id (article_structure_id)");
}

// Add rotation_pattern to schedule table for automated assignment
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_schedule LIKE 'rotation_pattern'");
if (empty($column_exists)) {
	$wpdb->query("ALTER TABLE $table_schedule ADD COLUMN rotation_pattern varchar(50) DEFAULT NULL AFTER article_structure_id");
}

// Seed default prompt sections
$default_sections = array(
	array(
		'name' => 'Introduction',
		'section_key' => 'introduction',
		'description' => 'Opening paragraph that hooks the reader',
		'content' => 'Write an engaging introduction that captures attention and clearly states what the article will cover.',
	),
	array(
		'name' => 'Prerequisites',
		'section_key' => 'prerequisites',
		'description' => 'Required knowledge or tools',
		'content' => 'List any prerequisites, required tools, or background knowledge needed.',
	),
	array(
		'name' => 'Step-by-Step Instructions',
		'section_key' => 'steps',
		'description' => 'Detailed procedural steps',
		'content' => 'Provide clear, numbered step-by-step instructions with explanations.',
	),
	array(
		'name' => 'Code Examples',
		'section_key' => 'examples',
		'description' => 'Practical code samples',
		'content' => 'Include relevant code examples with explanations of how they work.',
	),
	array(
		'name' => 'Tips and Best Practices',
		'section_key' => 'tips',
		'description' => 'Expert advice and recommendations',
		'content' => 'Share helpful tips, best practices, and common pitfalls to avoid.',
	),
	array(
		'name' => 'Troubleshooting',
		'section_key' => 'troubleshooting',
		'description' => 'Common issues and solutions',
		'content' => 'Address common problems readers might encounter and provide solutions.',
	),
	array(
		'name' => 'Conclusion',
		'section_key' => 'conclusion',
		'description' => 'Wrap-up and next steps',
		'content' => 'Summarize key points and suggest next steps or related topics.',
	),
	array(
		'name' => 'Resources',
		'section_key' => 'resources',
		'description' => 'Additional learning materials',
		'content' => 'Provide links to documentation, further reading, or related resources.',
	),
);

foreach ($default_sections as $section) {
	$exists = $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM $table_sections WHERE section_key = %s",
		$section['section_key']
	));
	
	if (!$exists) {
		$wpdb->insert($table_sections, $section);
	}
}

// Seed default article structures
$default_structures = array(
	array(
		'name' => 'How-To Guide',
		'description' => 'Step-by-step guide for accomplishing a specific task',
		'structure_data' => wp_json_encode(array(
			'sections' => array('introduction', 'prerequisites', 'steps', 'tips', 'troubleshooting', 'conclusion'),
			'prompt_template' => "Write a comprehensive how-to guide about {{topic}}.\n\n{{section:introduction}}\n\n{{section:prerequisites}}\n\n{{section:steps}}\n\n{{section:tips}}\n\n{{section:troubleshooting}}\n\n{{section:conclusion}}",
		)),
		'is_default' => 1,
	),
	array(
		'name' => 'Tutorial',
		'description' => 'In-depth educational content with practical examples',
		'structure_data' => wp_json_encode(array(
			'sections' => array('introduction', 'prerequisites', 'examples', 'steps', 'tips', 'resources', 'conclusion'),
			'prompt_template' => "Create a detailed tutorial on {{topic}}.\n\n{{section:introduction}}\n\n{{section:prerequisites}}\n\n{{section:examples}}\n\n{{section:steps}}\n\n{{section:tips}}\n\n{{section:resources}}\n\n{{section:conclusion}}",
		)),
	),
	array(
		'name' => 'Library Reference',
		'description' => 'Technical documentation for a library or API',
		'structure_data' => wp_json_encode(array(
			'sections' => array('introduction', 'prerequisites', 'examples', 'resources'),
			'prompt_template' => "Write technical reference documentation for {{topic}}.\n\n{{section:introduction}}\n\n{{section:prerequisites}}\n\n{{section:examples}}\n\nInclude comprehensive API documentation with parameter descriptions, return values, and usage examples.\n\n{{section:resources}}",
		)),
	),
	array(
		'name' => 'Listicle',
		'description' => 'List-based article with multiple items or tips',
		'structure_data' => wp_json_encode(array(
			'sections' => array('introduction', 'conclusion'),
			'prompt_template' => "Write a comprehensive listicle about {{topic}}.\n\n{{section:introduction}}\n\nPresent the main content as a numbered or bulleted list with detailed explanations for each item.\n\n{{section:conclusion}}",
		)),
	),
	array(
		'name' => 'Case Study',
		'description' => 'Real-world example with analysis and insights',
		'structure_data' => wp_json_encode(array(
			'sections' => array('introduction', 'examples', 'conclusion'),
			'prompt_template' => "Write a detailed case study about {{topic}}.\n\n{{section:introduction}}\n\nProvide background context and the problem/challenge being addressed.\n\n{{section:examples}}\n\nAnalyze the results, lessons learned, and key takeaways.\n\n{{section:conclusion}}",
		)),
	),
	array(
		'name' => 'Opinion/Editorial',
		'description' => 'Thought leadership or opinion piece',
		'structure_data' => wp_json_encode(array(
			'sections' => array('introduction', 'tips', 'conclusion'),
			'prompt_template' => "Write an opinion piece or editorial about {{topic}}.\n\n{{section:introduction}}\n\nPresent your main arguments with supporting evidence and examples.\n\n{{section:tips}}\n\nAddress counterarguments or alternative perspectives.\n\n{{section:conclusion}}",
		)),
	),
);

foreach ($default_structures as $structure) {
	$exists = $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM $table_structures WHERE name = %s",
		$structure['name']
	));
	
	if (!$exists) {
		$wpdb->insert($table_structures, $structure);
	}
}
?>
