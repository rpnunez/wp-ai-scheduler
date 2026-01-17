<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Default_Data_Seeder
 *
 * Handles seeding of default data like prompt sections and article structures.
 */
class AIPS_Default_Data_Seeder {

    /**
     * Seed default data for prompt sections and article structures
     * Only inserts if data doesn't already exist (idempotent)
     */
    public static function seed() {
        global $wpdb;
        $tables = AIPS_DB_Manager::get_full_table_names();
        $table_sections = $tables['aips_prompt_sections'];
        $table_structures = $tables['aips_article_structures'];

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
    }
}
